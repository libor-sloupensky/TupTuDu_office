<?php

namespace App\Http\Controllers;

use App\Models\Dodavatel;
use App\Models\Doklad;
use App\Models\Firma;
use Aws\Textract\TextractClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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

    public function store(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $firma = Firma::first();
        if (!$firma) {
            return back()->withErrors(['document' => 'Nejdříve vyplňte nastavení firmy.']);
        }

        $file = $request->file('document');
        $path = $file->store('invoices', 'local');
        $fullPath = storage_path('app/private/' . $path);

        // Vytvořit záznam dokladu
        $doklad = Doklad::create([
            'firma_ico' => $firma->ico,
            'nazev_souboru' => $file->getClientOriginalName(),
            'cesta_souboru' => $path,
            'stav' => 'zpracovava_se',
        ]);

        try {
            // 1. Textract OCR
            $client = new TextractClient([
                'region' => config('services.aws.region', 'eu-central-1'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('services.aws.key'),
                    'secret' => config('services.aws.secret'),
                ],
            ]);

            $fileBytes = file_get_contents($fullPath);
            $result = $client->detectDocumentText([
                'Document' => ['Bytes' => $fileBytes],
            ]);

            $lines = [];
            foreach ($result['Blocks'] as $block) {
                if ($block['BlockType'] === 'LINE') {
                    $lines[] = $block['Text'];
                }
            }
            $extractedText = implode("\n", $lines);
            $doklad->update(['raw_text' => $extractedText]);

            // 2. Claude Haiku - strukturování dat
            $invoiceData = $this->extractInvoiceData($extractedText);

            if (!empty($invoiceData['_error'])) {
                $doklad->update([
                    'stav' => 'chyba',
                    'chybova_zprava' => $invoiceData['_error'],
                ]);
                return view('invoices.result', [
                    'doklad' => $doklad,
                    'invoiceData' => $invoiceData,
                    'text' => $extractedText,
                    'filename' => $file->getClientOriginalName(),
                    'rawJson' => json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ]);
            }

            // 3. Auto-create/update dodavatel
            $dodavatelIco = $invoiceData['ico'] ?? null;
            if ($dodavatelIco) {
                Dodavatel::updateOrCreate(
                    ['ico' => $dodavatelIco],
                    [
                        'nazev' => $invoiceData['dodavatel'] ?? 'Neznámý',
                        'dic' => $invoiceData['dic'] ?? null,
                    ]
                );
            }

            // 4. Ověření adresáta
            $adresni = !empty($dodavatelIco);
            $overenoAdresat = false;
            if ($adresni) {
                // Doklad je adresní - ověříme jestli je adresován na naši firmu
                $odberatelIco = $invoiceData['odberatel_ico'] ?? null;
                $overenoAdresat = $odberatelIco === $firma->ico;
            }

            // 5. Uložit do DB
            $doklad->update([
                'dodavatel_ico' => $dodavatelIco,
                'dodavatel_nazev' => $invoiceData['dodavatel'] ?? null,
                'cislo_dokladu' => $invoiceData['cislo_faktury'] ?? null,
                'datum_vystaveni' => $invoiceData['datum_vystaveni'] ?? null,
                'datum_splatnosti' => $invoiceData['datum_splatnosti'] ?? null,
                'castka_celkem' => $invoiceData['castka_celkem'] ?? null,
                'mena' => $invoiceData['mena'] ?? 'CZK',
                'castka_dph' => $invoiceData['castka_dph'] ?? null,
                'kategorie' => $invoiceData['kategorie'] ?? null,
                'adresni' => $adresni,
                'overeno_adresat' => $overenoAdresat,
                'raw_ai_odpoved' => json_encode($invoiceData, JSON_UNESCAPED_UNICODE),
                'stav' => 'dokonceno',
            ]);

            return view('invoices.result', [
                'doklad' => $doklad->fresh(),
                'invoiceData' => $invoiceData,
                'firma' => $firma,
                'text' => $extractedText,
                'filename' => $file->getClientOriginalName(),
                'rawJson' => json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Exception $e) {
            $doklad->update([
                'stav' => 'chyba',
                'chybova_zprava' => $e->getMessage(),
            ]);
            return back()->withErrors(['document' => 'Chyba při zpracování: ' . $e->getMessage()]);
        }
    }

    private function extractInvoiceData(string $text): array
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            return ['_error' => 'Anthropic API klíč není nastaven v .env'];
        }

        $firma = Firma::first();
        $firmaInfo = $firma ? "IČO odběratele (naše firma): {$firma->ico}, DIČ: {$firma->dic}" : '';

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $text,
                ],
            ],
            'system' => "Jsi asistent pro zpracování českých faktur. Z textu faktury extrahuj strukturovaná data a vrať POUZE validní JSON objekt (žádný další text). {$firmaInfo}
Použij tyto klíče:
{
  \"dodavatel\": \"název dodavatele/firmy která fakturu VYSTAVILA\",
  \"ico\": \"IČO dodavatele (vystavitele)\",
  \"dic\": \"DIČ dodavatele (vystavitele)\",
  \"odberatel_ico\": \"IČO odběratele (komu je faktura adresována)\",
  \"cislo_faktury\": \"číslo faktury/dokladu\",
  \"datum_vystaveni\": \"datum vystavení ve formátu YYYY-MM-DD\",
  \"datum_splatnosti\": \"datum splatnosti ve formátu YYYY-MM-DD\",
  \"castka_celkem\": 0.00,
  \"mena\": \"CZK\",
  \"castka_dph\": 0.00,
  \"kategorie\": \"jedna z: služby, materiál, energie, telekomunikace, nájem, pojištění, ostatní\"
}
Pokud některý údaj nelze z textu zjistit, použij null. U neadresních dokladů (paragony, bloky) bude odberatel_ico null.",
        ]);

        if ($response->failed()) {
            return ['_error' => 'Claude API chyba: ' . $response->body()];
        }

        $body = $response->json();
        $content = $body['content'][0]['text'] ?? '';

        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }

        return ['_error' => 'Nepodařilo se parsovat odpověď z Claude: ' . $content];
    }
}
