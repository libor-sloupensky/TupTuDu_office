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
    private function aktivniFirma(): Firma
    {
        return auth()->user()->aktivniFirma();
    }

    private function autorizujDoklad(Doklad $doklad): void
    {
        $dostupne = auth()->user()->dostupneIco();
        if (!in_array($doklad->firma_ico, $dostupne)) {
            abort(403, 'Nemáte přístup k tomuto dokladu.');
        }
    }

    public function index(Request $request)
    {
        $firma = $this->aktivniFirma();

        $allowedSort = ['created_at', 'datum_vystaveni', 'datum_prijeti', 'duzp', 'datum_splatnosti'];
        $sort = in_array($request->query('sort'), $allowedSort) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $q = trim($request->query('q', ''));

        $query = Doklad::where('firma_ico', $firma->ico);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('cislo_dokladu', 'like', "%{$q}%")
                    ->orWhere('dodavatel_nazev', 'like', "%{$q}%")
                    ->orWhere('nazev_souboru', 'like', "%{$q}%")
                    ->orWhere('dodavatel_ico', 'like', "%{$q}%")
                    ->orWhere('raw_text', 'like', "%{$q}%");
            });
        }

        $doklady = $query->orderBy($sort, $dir)->get();

        return view('invoices.index', compact('doklady', 'firma', 'sort', 'dir', 'q'));
    }

    public function show(Doklad $doklad)
    {
        $this->autorizujDoklad($doklad);
        return view('invoices.show', compact('doklad'));
    }

    public function download(Doklad $doklad)
    {
        $this->autorizujDoklad($doklad);
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
        $this->autorizujDoklad($doklad);
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

        $firma = $this->aktivniFirma();

        $processor = new DokladProcessor();
        $results = [];

        foreach ($request->file('documents') as $file) {
            $tempPath = $file->getRealPath();
            $fileHash = hash_file('sha256', $tempPath);
            $originalName = $file->getClientOriginalName();

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

        if ($request->ajax()) {
            return response()->json(collect($results)->map(fn($r) => [
                'name' => $r['name'],
                'status' => empty($r['error']) ? 'ok' : (str_starts_with($r['error'] ?? '', 'Duplicita') ? 'duplicate' : 'error'),
                'message' => empty($r['error'])
                    ? ($r['name'] . ' - zpracováno')
                    : ($r['name'] . ' - ' . $r['error']),
            ])->values());
        }

        if (count($results) === 1 && !empty($results[0]['doklad']) && $results[0]['doklad']->stav !== 'chyba') {
            return redirect()->route('doklady.show', $results[0]['doklad']);
        }

        $ok = collect($results)->filter(fn($r) => empty($r['error']))->count();
        $errors = collect($results)->filter(fn($r) => !empty($r['error']));

        $message = "Zpracováno {$ok} z " . count($results) . " dokladů.";
        if ($errors->isNotEmpty()) {
            $message .= ' Chyby: ' . $errors->map(fn($r) => $r['name'] . ' - ' . $r['error'])->implode('; ');
        }

        return redirect()->route('doklady.index')->with('flash', $message);
    }

    public function update(Request $request, Doklad $doklad)
    {
        $this->autorizujDoklad($doklad);

        $editableFields = [
            'datum_prijeti', 'duzp', 'datum_vystaveni', 'datum_splatnosti',
            'dodavatel_nazev', 'dodavatel_ico', 'cislo_dokladu',
            'castka_celkem', 'mena', 'castka_dph', 'kategorie',
        ];

        $field = $request->input('field');
        $value = $request->input('value');

        if (!in_array($field, $editableFields)) {
            return response()->json(['ok' => false, 'error' => 'Pole nelze upravit.'], 422);
        }

        if ($value === '' || $value === null) {
            $value = null;
        }

        $doklad->update([$field => $value]);

        return response()->json(['ok' => true]);
    }

    public function downloadMonth(Request $request, string $mesic)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $mesic)) {
            abort(400, 'Neplatný formát měsíce. Použijte YYYY-MM.');
        }

        $firma = $this->aktivniFirma();

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
        $this->autorizujDoklad($doklad);

        if ($doklad->cesta_souboru) {
            Storage::disk('s3')->delete($doklad->cesta_souboru);
        }

        Doklad::where('duplicita_id', $doklad->id)->update(['duplicita_id' => null]);

        $nazev = $doklad->cislo_dokladu ?: $doklad->nazev_souboru;
        $doklad->delete();

        return redirect()->route('doklady.index')->with('flash', "Doklad {$nazev} byl smazán.");
    }
}
