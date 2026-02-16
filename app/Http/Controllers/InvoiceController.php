<?php

namespace App\Http\Controllers;

use App\Models\Doklad;
use App\Models\Firma;
use App\Services\DokladProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class InvoiceController extends Controller
{
    private function aktivniFirma(): Firma
    {
        return auth()->user()->aktivniFirma();
    }

    private function friendlyErrorMessage(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Claude Vision API')) {
            return 'Služba pro rozpoznávání dokladů je dočasně nedostupná. Zkuste to prosím znovu za chvíli.';
        }
        if (str_contains($msg, 'timeout') || str_contains($msg, 'timed out')) {
            return 'Zpracování trvalo příliš dlouho. Zkuste nahrát soubor znovu.';
        }
        if (str_contains($msg, 'S3') || str_contains($msg, 'storage')) {
            return 'Chyba při ukládání souboru. Zkuste to prosím znovu.';
        }
        if (str_contains($msg, 'parsovat') || str_contains($msg, 'JSON')) {
            return 'Doklad se nepodařilo rozpoznat. Zkuste jiný formát nebo kvalitnější sken.';
        }
        return 'Nastala neočekávaná chyba. Zkuste to prosím znovu.';
    }

    private function logFailedUpload(\Illuminate\Http\Request $request, \Throwable $e): void
    {
        $logDir = storage_path('logs/failed_uploads');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $entry = [
            'time' => now()->toIso8601String(),
            'user_id' => auth()->id(),
            'firma_ico' => session('aktivni_firma_ico'),
            'error' => $e->getMessage(),
            'error_file' => $e->getFile() . ':' . $e->getLine(),
            'files' => [],
        ];

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $entry['files'][] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ];
            }
        }

        $logFile = $logDir . '/' . date('Y-m-d') . '.json';
        $existing = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) ?: [] : [];
        $existing[] = $entry;
        @file_put_contents($logFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function autorizujDoklad(Doklad $doklad): void
    {
        $dostupne = auth()->user()->dostupneIco();
        if (!in_array($doklad->firma_ico, $dostupne)) {
            abort(403, 'Nemáte přístup k tomuto dokladu.');
        }
    }

    private function dokladToArray(Doklad $d): array
    {
        return [
            'id' => $d->id,
            'created_at' => $d->created_at->format('d.m.y'),
            'created_at_time' => $d->created_at->format('H:i'),
            'created_at_iso' => $d->created_at->toISOString(),
            'datum_prijeti' => $d->datum_prijeti ? $d->datum_prijeti->format('d.m.y') : null,
            'datum_prijeti_raw' => $d->datum_prijeti ? $d->datum_prijeti->format('Y-m-d') : null,
            'duzp' => $d->duzp ? $d->duzp->format('d.m.y') : null,
            'duzp_raw' => $d->duzp ? $d->duzp->format('Y-m-d') : null,
            'datum_vystaveni' => $d->datum_vystaveni ? $d->datum_vystaveni->format('d.m.y') : null,
            'datum_vystaveni_raw' => $d->datum_vystaveni ? $d->datum_vystaveni->format('Y-m-d') : null,
            'datum_splatnosti' => $d->datum_splatnosti ? $d->datum_splatnosti->format('d.m.y') : null,
            'datum_splatnosti_raw' => $d->datum_splatnosti ? $d->datum_splatnosti->format('Y-m-d') : null,
            'cislo_dokladu' => $d->cislo_dokladu,
            'nazev_souboru' => $d->nazev_souboru,
            'dodavatel_nazev' => $d->dodavatel_nazev,
            'dodavatel_ico' => $d->dodavatel_ico,
            'castka_celkem' => $d->castka_celkem,
            'mena' => $d->mena,
            'castka_dph' => $d->castka_dph,
            'kategorie' => $d->kategorie,
            'stav' => $d->stav,
            'typ_dokladu' => $d->typ_dokladu,
            'kvalita' => $d->kvalita,
            'kvalita_poznamka' => $d->kvalita_poznamka,
            'zdroj' => $d->zdroj,
            'cesta_souboru' => $d->cesta_souboru ? true : false,
            'duplicita_id' => $d->duplicita_id,
            'show_url' => route('doklady.show', $d),
            'update_url' => route('doklady.update', $d),
            'destroy_url' => route('doklady.destroy', $d),
            'preview_url' => $d->cesta_souboru ? route('doklady.preview', $d) : null,
            'preview_ext' => $d->cesta_souboru ? strtolower(pathinfo($d->cesta_souboru, PATHINFO_EXTENSION)) : null,
            'preview_original_url' => $d->cesta_originalu ? route('doklady.previewOriginal', $d) : null,
            'preview_original_ext' => $d->cesta_originalu ? strtolower(pathinfo($d->cesta_originalu, PATHINFO_EXTENSION)) : null,
            'adresni' => $d->adresni,
            'overeno_adresat' => $d->overeno_adresat,
            'chybova_zprava' => $d->chybova_zprava,
            'raw_ai_odpoved' => $d->raw_ai_odpoved,
            'created_at_full' => $d->created_at->format('d.m.Y H:i'),
        ];
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
        $dokladyJson = $doklady->map(fn($d) => $this->dokladToArray($d))->values();

        if ($request->ajax()) {
            return response()->json($dokladyJson);
        }

        $kategorieList = $firma->kategorie()->orderBy('poradi')->pluck('nazev')->toArray();

        return view('invoices.index', compact('doklady', 'firma', 'sort', 'dir', 'q', 'dokladyJson', 'kategorieList'));
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

    private function mimeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    public function preview(Doklad $doklad)
    {
        $this->autorizujDoklad($doklad);
        $disk = Storage::disk('s3');

        if (!$doklad->cesta_souboru || !$disk->exists($doklad->cesta_souboru)) {
            abort(404, 'Soubor nebyl nalezen.');
        }

        $mime = $this->mimeFromPath($doklad->cesta_souboru);

        return response($disk->get($doklad->cesta_souboru))
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . $doklad->nazev_souboru . '"');
    }

    public function previewOriginal(Doklad $doklad)
    {
        $this->autorizujDoklad($doklad);
        $disk = Storage::disk('s3');

        if (!$doklad->cesta_originalu || !$disk->exists($doklad->cesta_originalu)) {
            abort(404, 'Originální soubor nebyl nalezen.');
        }

        $mime = $this->mimeFromPath($doklad->cesta_originalu);

        return response($disk->get($doklad->cesta_originalu))
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="original_' . $doklad->nazev_souboru . '"');
    }

    public function store(Request $request)
    {
        try {
            $storeStart = microtime(true);

            $request->validate([
                'documents' => 'required|array|min:1',
                'documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            ]);

            $firma = $this->aktivniFirma();

            $processor = new DokladProcessor();
            $results = [];

            foreach ($request->file('documents') as $file) {
                $fileStart = microtime(true);
                $tempPath = $file->getRealPath();
                $fileHash = hash_file('sha256', $tempPath);
                $originalName = $file->getClientOriginalName();

                // Hash duplicate - exact same file, skip entirely
                $existujici = $processor->isDuplicate($fileHash, $firma->ico);
                if ($existujici) {
                    $dupInfo = $existujici->dodavatel_nazev ?: ($existujici->cislo_dokladu ?: $existujici->nazev_souboru);
                    $results[] = [
                        'name' => $originalName,
                        'status' => 'duplicate',
                        'message' => "Tento soubor již byl nahrán ({$dupInfo})",
                        'timing' => ['total_ms' => round((microtime(true) - $fileStart) * 1000), 'step' => 'duplicate_check'],
                    ];
                    continue;
                }

                $t = microtime(true);
                $doklady = $processor->process($tempPath, $originalName, $firma, $fileHash, 'upload');
                $processMs = round((microtime(true) - $t) * 1000);

                // Determine overall status and build human-friendly message
                $status = 'ok';
                $warnings = [];
                $typyDokladu = [
                    'faktura' => 'Faktura', 'uctenka' => 'Účtenka', 'pokladni_doklad' => 'Pokladní doklad',
                    'dobropis' => 'Dobropis', 'zalohova_faktura' => 'Zálohová faktura',
                    'pokuta' => 'Pokuta/Výzva', 'jine' => 'Jiný doklad',
                ];

                foreach ($doklady as $doklad) {
                    if ($doklad->stav === 'chyba') {
                        $status = 'error';
                        $warnings[] = $doklad->chybova_zprava;
                    } elseif ($doklad->kvalita === 'necitelna') {
                        if ($status !== 'error') $status = 'error';
                        $warnings[] = $doklad->kvalita_poznamka ?: 'Nečitelný doklad';
                    } elseif ($doklad->kvalita === 'nizka') {
                        if ($status === 'ok') $status = 'warning';
                        $warnings[] = $doklad->kvalita_poznamka ?: 'Nízká kvalita';
                    } elseif ($doklad->duplicita_id) {
                        if ($status === 'ok') $status = 'warning';
                        $warnings[] = 'Možná duplicita (č. ' . ($doklad->cislo_dokladu ?: '?') . ')';
                    }
                }

                // Build human-friendly message from extracted data
                $prvni = $doklady[0];
                $typLabel = $typyDokladu[$prvni->typ_dokladu] ?? 'Doklad';
                $dodavatel = $prvni->dodavatel_nazev ?: 'neznámý dodavatel';
                $castka = $prvni->castka_celkem ? number_format($prvni->castka_celkem, 2, ',', ' ') . ' ' . ($prvni->mena ?: 'CZK') : '';

                if (count($doklady) === 1) {
                    $message = "{$typLabel}: {$dodavatel}";
                    if ($castka) $message .= " — {$castka}";
                } else {
                    $message = count($doklady) . ' dokladů z ' . $originalName;
                    if ($status === 'ok') $status = 'warning';
                    $warnings[] = 'Nahrání více dokumentů na jedné stránce není spolehlivé. Nahrávejte pouze jeden dokument na stránku.';
                }

                if ($warnings) {
                    $message .= ' ⚠ ' . implode(', ', array_unique($warnings));
                }

                $results[] = [
                    'name' => $originalName,
                    'status' => $status,
                    'message' => $message,
                    'timing' => [
                        'process_ms' => $processMs,
                        'total_ms' => round((microtime(true) - $fileStart) * 1000),
                    ],
                ];
            }

            $totalMs = round((microtime(true) - $storeStart) * 1000);

            if ($request->ajax()) {
                return response()->json([
                    'results' => $results,
                    'total_ms' => $totalMs,
                ]);
            }

            $ok = collect($results)->where('status', 'ok')->count();
            $total = count($results);
            $message = "Zpracováno {$ok} z {$total} dokladů.";
            $warnResults = collect($results)->whereIn('status', ['warning', 'error']);
            if ($warnResults->isNotEmpty()) {
                $message .= ' ' . $warnResults->pluck('message')->implode('; ');
            }

            return redirect()->route('doklady.index')->with('flash', $message);

        } catch (\Throwable $e) {
            Log::error('InvoiceController::store error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Save error details to failed_uploads log
            $this->logFailedUpload($request, $e);

            $userMessage = $this->friendlyErrorMessage($e);

            if ($request->ajax()) {
                return response()->json([
                    'results' => [['name' => 'Chyba', 'status' => 'error', 'message' => $userMessage]],
                    'timing' => [],
                ], 500);
            }

            return redirect()->route('doklady.index')->with('flash', $userMessage);
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

    public function aiSearch(Request $request)
    {
        $request->validate(['q' => 'required|string|max:500']);
        $q = trim($request->input('q'));
        $firma = $this->aktivniFirma();

        try {
            $parsed = $this->parseSearchWithAI($q);
            $filters = $parsed['filters'] ?? [];
            $description = $parsed['description'] ?? 'Výsledky hledání';

            $query = $this->buildFilteredQuery($filters, $firma->ico);
            $doklady = $query->get();
            $data = $doklady->map(fn($d) => $this->dokladToArray($d))->values();

            return response()->json([
                'description' => $description,
                'data' => $data,
                'count' => $data->count(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI search failed, falling back to LIKE', ['error' => $e->getMessage()]);

            // Fallback: simple LIKE search
            $query = Doklad::where('firma_ico', $firma->ico)
                ->where(function ($sub) use ($q) {
                    $sub->where('cislo_dokladu', 'like', "%{$q}%")
                        ->orWhere('dodavatel_nazev', 'like', "%{$q}%")
                        ->orWhere('nazev_souboru', 'like', "%{$q}%")
                        ->orWhere('dodavatel_ico', 'like', "%{$q}%")
                        ->orWhere('raw_text', 'like', "%{$q}%");
                })
                ->orderBy('created_at', 'desc');

            $doklady = $query->get();
            $data = $doklady->map(fn($d) => $this->dokladToArray($d))->values();

            return response()->json([
                'description' => "AI nedostupné — textové hledání \"{$q}\"",
                'data' => $data,
                'count' => $data->count(),
            ]);
        }
    }

    private function parseSearchWithAI(string $query): array
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            throw new \RuntimeException('Anthropic API key not configured');
        }

        $firma = $this->aktivniFirma();
        $kategorieNames = $firma->kategorie()->orderBy('poradi')->pluck('nazev')->implode(', ');
        if (empty($kategorieNames)) {
            $kategorieNames = 'Ostatní';
        }

        $today = now()->format('Y-m-d');
        $currentYear = now()->year;
        $currentMonth = now()->month;

        $system = <<<PROMPT
Jsi asistent pro vyhledávání účetních dokladů. Uživatel napíše dotaz přirozeně česky a ty ho převedeš na strukturované filtry pro databázi.

Dnešní datum: {$today}

DOSTUPNÉ FILTRY (vrať POUZE tyto klíče):
- kategorie: {$kategorieNames}
- typ_dokladu: faktura, uctenka, pokladni_doklad, dobropis, zalohova_faktura, pokuta, jine
- stav: ok, chyba, nekvalitni
- kvalita: ok, nizka, necitelna
- mena: CZK, EUR, USD (vždy velkými)
- zdroj: upload, email
- dodavatel_nazev: textový řetězec (hledá se částečně)
- dodavatel_ico: přesné IČO
- cislo_dokladu: textový řetězec (hledá se částečně)
- castka_min: minimální částka (číslo)
- castka_max: maximální částka (číslo)
- duzp_od, duzp_do: datum DUZP od/do (YYYY-MM-DD)
- datum_vystaveni_od, datum_vystaveni_do: datum vystavení od/do (YYYY-MM-DD)
- datum_prijeti_od, datum_prijeti_do: datum přijetí od/do (YYYY-MM-DD)
- datum_splatnosti_od, datum_splatnosti_do: datum splatnosti od/do (YYYY-MM-DD)
- text: fulltext v raw_text dokladu
- sort_by: created_at, datum_vystaveni, datum_prijeti, duzp, datum_splatnosti, castka_celkem
- sort_dir: asc, desc

PRAVIDLA:
- "květen 2025" = duzp_od: 2025-05-01, duzp_do: 2025-05-31
- "nad 5000" = castka_min: 5000
- "pod 1000" = castka_max: 1000
- "od Alza" = dodavatel_nazev: "Alza"
- "s chybou" = stav: chyba
- "nekvalitní" = kvalita: nizka NEBO necitelna (použij kvalita: nizka)
- "minulý měsíc" = vypočítej z dnešního data
- Pokud uživatel nespecifikuje typ data, použij DUZP (duzp_od, duzp_do)
- Vrať POUZE filtry, které odpovídají dotazu. Nepoužité filtry nevkládej.

Odpověz POUZE validním JSON:
{"description": "Stručný český popis co se hledá", "filters": {"klíč": "hodnota", ...}}
PROMPT;

        $response = Http::timeout(15)->withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 1024,
            'system' => $system,
            'messages' => [
                ['role' => 'user', 'content' => $query],
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('AI API request failed: ' . $response->status());
        }

        $body = $response->json();
        $content = $body['content'][0]['text'] ?? '';

        if (preg_match('/\{[\s\S]*\}/s', $content, $matches)) {
            $result = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'description' => $result['description'] ?? 'Výsledky hledání',
                    'filters' => $result['filters'] ?? [],
                ];
            }
        }

        throw new \RuntimeException('Failed to parse AI response');
    }

    private function buildFilteredQuery(array $filters, string $firmaIco)
    {
        $query = Doklad::where('firma_ico', $firmaIco);

        // Enum whitelists - dynamic categories from DB
        $firma = Firma::find($firmaIco);
        $kategorieEnum = $firma ? $firma->kategorie()->pluck('nazev')->toArray() : [];
        $typEnum = ['faktura', 'uctenka', 'pokladni_doklad', 'dobropis', 'zalohova_faktura', 'pokuta', 'jine'];
        $stavEnum = ['ok', 'chyba', 'nekvalitni'];
        $kvalitaEnum = ['ok', 'nizka', 'necitelna'];
        $menaEnum = ['CZK', 'EUR', 'USD', 'GBP', 'PLN', 'CHF'];
        $zdrojEnum = ['upload', 'email'];
        $sortEnum = ['created_at', 'datum_vystaveni', 'datum_prijeti', 'duzp', 'datum_splatnosti', 'castka_celkem'];
        $dirEnum = ['asc', 'desc'];

        $dateRegex = '/^\d{4}-\d{2}-\d{2}$/';

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') continue;
            $value = is_string($value) ? trim($value) : $value;

            switch ($key) {
                case 'kategorie':
                    if (in_array($value, $kategorieEnum)) $query->where('kategorie', $value);
                    break;
                case 'typ_dokladu':
                    if (in_array($value, $typEnum)) $query->where('typ_dokladu', $value);
                    break;
                case 'stav':
                    if (in_array($value, $stavEnum)) $query->where('stav', $value);
                    break;
                case 'kvalita':
                    if (in_array($value, $kvalitaEnum)) $query->where('kvalita', $value);
                    break;
                case 'mena':
                    $v = strtoupper($value);
                    if (in_array($v, $menaEnum)) $query->where('mena', $v);
                    break;
                case 'zdroj':
                    if (in_array($value, $zdrojEnum)) $query->where('zdroj', $value);
                    break;
                case 'dodavatel_nazev':
                    $query->where('dodavatel_nazev', 'like', '%' . $value . '%');
                    break;
                case 'dodavatel_ico':
                    if (preg_match('/^\d{6,8}$/', $value)) $query->where('dodavatel_ico', $value);
                    break;
                case 'cislo_dokladu':
                    $query->where('cislo_dokladu', 'like', '%' . $value . '%');
                    break;
                case 'castka_min':
                    if (is_numeric($value)) $query->where('castka_celkem', '>=', (float)$value);
                    break;
                case 'castka_max':
                    if (is_numeric($value)) $query->where('castka_celkem', '<=', (float)$value);
                    break;
                case 'duzp_od':
                    if (preg_match($dateRegex, $value)) $query->where('duzp', '>=', $value);
                    break;
                case 'duzp_do':
                    if (preg_match($dateRegex, $value)) $query->where('duzp', '<=', $value);
                    break;
                case 'datum_vystaveni_od':
                    if (preg_match($dateRegex, $value)) $query->where('datum_vystaveni', '>=', $value);
                    break;
                case 'datum_vystaveni_do':
                    if (preg_match($dateRegex, $value)) $query->where('datum_vystaveni', '<=', $value);
                    break;
                case 'datum_prijeti_od':
                    if (preg_match($dateRegex, $value)) $query->where('datum_prijeti', '>=', $value);
                    break;
                case 'datum_prijeti_do':
                    if (preg_match($dateRegex, $value)) $query->where('datum_prijeti', '<=', $value);
                    break;
                case 'datum_splatnosti_od':
                    if (preg_match($dateRegex, $value)) $query->where('datum_splatnosti', '>=', $value);
                    break;
                case 'datum_splatnosti_do':
                    if (preg_match($dateRegex, $value)) $query->where('datum_splatnosti', '<=', $value);
                    break;
                case 'text':
                    $query->where('raw_text', 'like', '%' . $value . '%');
                    break;
                case 'sort_by':
                    $dir = isset($filters['sort_dir']) && in_array($filters['sort_dir'], $dirEnum) ? $filters['sort_dir'] : 'desc';
                    if (in_array($value, $sortEnum)) $query->orderBy($value, $dir);
                    break;
                // Unknown keys are silently ignored
            }
        }

        // Default sort if none specified
        if (!isset($filters['sort_by'])) {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }

    public function destroy(Doklad $doklad)
    {
        $this->autorizujDoklad($doklad);

        if ($doklad->cesta_souboru) {
            Storage::disk('s3')->delete($doklad->cesta_souboru);
        }
        if ($doklad->cesta_originalu) {
            Storage::disk('s3')->delete($doklad->cesta_originalu);
        }

        Doklad::where('duplicita_id', $doklad->id)->update(['duplicita_id' => null]);

        $nazev = $doklad->cislo_dokladu ?: $doklad->nazev_souboru;
        $doklad->delete();

        if (request()->ajax()) {
            return response()->json(['ok' => true, 'nazev' => $nazev]);
        }

        return redirect()->route('doklady.index')->with('flash', "Doklad {$nazev} byl smazán.");
    }
}
