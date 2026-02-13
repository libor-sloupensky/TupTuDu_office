<?php

namespace App\Http\Controllers;

use App\Models\Doklad;
use App\Models\Firma;
use App\Services\DokladProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class InvoiceController extends Controller
{
    public function create()
    {
        return view('invoices.upload');
    }

    public function index()
    {
        $firma = Firma::first();
        $doklady = $firma
            ? Doklad::where('firma_ico', $firma->ico)->orderByDesc('created_at')->get()
            : collect();

        return view('invoices.index', compact('doklady', 'firma'));
    }

    public function show(Doklad $doklad)
    {
        return view('invoices.show', compact('doklad'));
    }

    public function download(Doklad $doklad)
    {
        $disk = Storage::disk('s3');

        if (!$doklad->cesta_souboru || !$disk->exists($doklad->cesta_souboru)) {
            abort(404, 'Soubor nebyl nalezen.');
        }

        return response()->streamDownload(function () use ($disk, $doklad) {
            echo $disk->get($doklad->cesta_souboru);
        }, $doklad->nazev_souboru);
    }

    public function preview(Doklad $doklad)
    {
        $disk = Storage::disk('s3');

        if (!$doklad->cesta_souboru || !$disk->exists($doklad->cesta_souboru)) {
            abort(404, 'Soubor nebyl nalezen.');
        }

        $ext = strtolower(pathinfo($doklad->nazev_souboru, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

        return response($disk->get($doklad->cesta_souboru))
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . $doklad->nazev_souboru . '"');
    }

    public function store(Request $request)
    {
        $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $firma = Firma::first();
        if (!$firma) {
            return back()->withErrors(['documents' => 'Nejdříve vyplňte nastavení firmy.']);
        }

        $processor = new DokladProcessor();
        $results = [];

        foreach ($request->file('documents') as $file) {
            $tempPath = $file->getRealPath();
            $fileHash = hash_file('sha256', $tempPath);
            $originalName = $file->getClientOriginalName();

            // Kontrola duplicit
            $existujici = $processor->isDuplicate($fileHash, $firma->ico);
            if ($existujici) {
                $results[] = [
                    'name' => $originalName,
                    'error' => 'Duplicita (' . ($existujici->cislo_dokladu ?: $existujici->nazev_souboru) . ')',
                ];
                continue;
            }

            $doklad = $processor->process(
                $tempPath,
                $originalName,
                $firma,
                $fileHash,
                'upload'
            );

            $results[] = [
                'name' => $originalName,
                'doklad' => $doklad,
                'error' => $doklad->stav === 'chyba' ? $doklad->chybova_zprava : null,
            ];
        }

        // Jeden soubor -> redirect na detail
        if (count($results) === 1 && !empty($results[0]['doklad']) && $results[0]['doklad']->stav !== 'chyba') {
            return redirect()->route('doklady.show', $results[0]['doklad']);
        }

        // Více souborů -> redirect na seznam s flash message
        $ok = collect($results)->filter(fn($r) => empty($r['error']))->count();
        $errors = collect($results)->filter(fn($r) => !empty($r['error']));

        $message = "Zpracováno {$ok} z " . count($results) . " dokladů.";
        if ($errors->isNotEmpty()) {
            $message .= ' Chyby: ' . $errors->map(fn($r) => $r['name'] . ' - ' . $r['error'])->implode('; ');
        }

        return redirect()->route('doklady.index')->with('flash', $message);
    }

    public function downloadMonth(Request $request, string $mesic)
    {
        // Validate format YYYY-MM
        if (!preg_match('/^\d{4}-\d{2}$/', $mesic)) {
            abort(400, 'Neplatný formát měsíce. Použijte YYYY-MM.');
        }

        $firma = Firma::first();
        if (!$firma) {
            abort(404, 'Firma nenalezena.');
        }

        $doklady = Doklad::where('firma_ico', $firma->ico)
            ->where('cesta_souboru', 'like', "doklady/{$firma->ico}/{$mesic}/%")
            ->where('cesta_souboru', '!=', '')
            ->get();

        if ($doklady->isEmpty()) {
            abort(404, 'Žádné doklady za tento měsíc.');
        }

        $zipName = "doklady_{$mesic}.zip";
        $tempZip = tempnam(sys_get_temp_dir(), 'doklady_') . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($tempZip, ZipArchive::CREATE) !== true) {
            abort(500, 'Nepodařilo se vytvořit ZIP archiv.');
        }

        $disk = Storage::disk('s3');
        foreach ($doklady as $doklad) {
            if ($disk->exists($doklad->cesta_souboru)) {
                $zip->addFromString($doklad->nazev_souboru, $disk->get($doklad->cesta_souboru));
            }
        }

        $zip->close();

        return response()->download($tempZip, $zipName)->deleteFileAfterSend(true);
    }

    public function destroy(Doklad $doklad)
    {
        // Smazat soubor z S3
        if ($doklad->cesta_souboru) {
            Storage::disk('s3')->delete($doklad->cesta_souboru);
        }

        // Odpojit duplicity které na tento doklad odkazují
        Doklad::where('duplicita_id', $doklad->id)->update(['duplicita_id' => null]);

        $nazev = $doklad->cislo_dokladu ?: $doklad->nazev_souboru;
        $doklad->delete();

        return redirect()->route('doklady.index')->with('flash', "Doklad {$nazev} byl smazán.");
    }
}
