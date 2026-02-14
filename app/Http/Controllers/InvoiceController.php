<?php

namespace App\Http\Controllers;

use App\Models\Doklad;
use App\Models\Firma;
use App\Services\DokladProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    private function debugLog(string $msg): void
    {
        $logFile = storage_path('logs/upload_debug.log');
        file_put_contents($logFile, date('H:i:s') . " {$msg}\n", FILE_APPEND);
    }

    public function store(Request $request)
    {
        $this->debugLog('=== START store() ===');
        $this->debugLog('Method: ' . $request->method());
        $this->debugLog('Ajax: ' . ($request->ajax() ? 'YES' : 'NO'));
        $this->debugLog('Accept: ' . $request->header('Accept'));
        $this->debugLog('Content-Type: ' . $request->header('Content-Type'));
        $this->debugLog('Files: ' . ($request->hasFile('documents') ? count($request->file('documents')) : 'NONE'));

        try {
            $this->debugLog('Validating...');
            $request->validate([
                'documents' => 'required|array|min:1',
                'documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            ]);
            $this->debugLog('Validation OK');

            $firma = $this->aktivniFirma();
            $this->debugLog("Firma: {$firma->nazev} (ICO: {$firma->ico})");

            $processor = new DokladProcessor();
            $results = [];

            foreach ($request->file('documents') as $i => $file) {
                $tempPath = $file->getRealPath();
                $fileHash = hash_file('sha256', $tempPath);
                $originalName = $file->getClientOriginalName();
                $this->debugLog("File [{$i}]: {$originalName} ({$file->getSize()} bytes, hash: " . substr($fileHash, 0, 12) . ')');

                $existujici = $processor->isDuplicate($fileHash, $firma->ico);
                if ($existujici) {
                    $this->debugLog("File [{$i}]: DUPLICITA #{$existujici->id}");
                    $results[] = [
                        'name' => $originalName,
                        'error' => 'Duplicita (' . ($existujici->cislo_dokladu ?: $existujici->nazev_souboru) . ')',
                    ];
                    continue;
                }

                $this->debugLog("File [{$i}]: Calling DokladProcessor::process()...");
                $start = microtime(true);
                $doklady = $processor->process(
                    $tempPath,
                    $originalName,
                    $firma,
                    $fileHash,
                    'upload'
                );
                $elapsed = round(microtime(true) - $start, 2);
                $this->debugLog("File [{$i}]: DokladProcessor done ({$elapsed}s), " . count($doklady) . ' dokladů');

                foreach ($doklady as $doklad) {
                    $this->debugLog("  Doklad #{$doklad->id}: stav={$doklad->stav}, typ={$doklad->typ_dokladu}");
                    $warning = null;
                    if ($doklad->kvalita === 'nizka') {
                        $warning = $doklad->kvalita_poznamka ?: 'Nízká kvalita dokladu';
                    } elseif ($doklad->kvalita === 'necitelna') {
                        $warning = $doklad->kvalita_poznamka ?: 'Doklad je nečitelný';
                    }

                    $results[] = [
                        'name' => $doklad->nazev_souboru,
                        'doklad' => $doklad,
                        'error' => $doklad->stav === 'chyba' ? $doklad->chybova_zprava : null,
                        'warning' => $warning,
                    ];
                }
            }

            $this->debugLog('All files processed. Results: ' . count($results));

            if ($request->ajax()) {
                $jsonResponse = collect($results)->map(fn($r) => [
                    'name' => $r['name'],
                    'status' => !empty($r['error'])
                        ? (str_starts_with($r['error'] ?? '', 'Duplicita') ? 'duplicate' : 'error')
                        : (!empty($r['warning']) ? 'warning' : 'ok'),
                    'message' => !empty($r['error'])
                        ? ($r['name'] . ' - ' . $r['error'])
                        : ($r['name'] . ' - zpracováno' . (!empty($r['warning']) ? ' | ' . $r['warning'] : '')),
                ])->values();
                $this->debugLog('Returning JSON: ' . json_encode($jsonResponse, JSON_UNESCAPED_UNICODE));
                return response()->json($jsonResponse);
            }

            $this->debugLog('Non-AJAX request, redirecting...');
            $allDoklady = collect($results)->filter(fn($r) => !empty($r['doklad']) && $r['doklad']->stav !== 'chyba');

            if ($allDoklady->count() === 1) {
                return redirect()->route('doklady.show', $allDoklady->first()['doklad']);
            }

            $ok = $allDoklady->count();
            $errors = collect($results)->filter(fn($r) => !empty($r['error']));
            $warnings = collect($results)->filter(fn($r) => !empty($r['warning']));

            $message = "Zpracováno {$ok} z " . count($results) . " dokladů.";
            if ($warnings->isNotEmpty()) {
                $message .= ' Upozornění: ' . $warnings->count() . ' dokladů s nízkou kvalitou.';
            }
            if ($errors->isNotEmpty()) {
                $message .= ' Chyby: ' . $errors->map(fn($r) => $r['name'] . ' - ' . $r['error'])->implode('; ');
            }

            return redirect()->route('doklady.index')->with('flash', $message);

        } catch (\Throwable $e) {
            $this->debugLog('EXCEPTION: ' . $e->getMessage());
            $this->debugLog('  File: ' . $e->getFile() . ':' . $e->getLine());
            $this->debugLog('  Trace: ' . substr($e->getTraceAsString(), 0, 500));

            Log::error('InvoiceController::store error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            if ($request->ajax()) {
                return response()->json([
                    ['name' => 'Chyba', 'status' => 'error', 'message' => 'Chyba serveru: ' . $e->getMessage()]
                ], 500);
            }

            return redirect()->route('doklady.index')->with('flash', 'Chyba při zpracování: ' . $e->getMessage());
        }
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
