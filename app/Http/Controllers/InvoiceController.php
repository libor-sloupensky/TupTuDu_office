<?php

namespace App\Http\Controllers;

use Aws\Textract\TextractClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class InvoiceController extends Controller
{
    public function create()
    {
        return view('invoices.upload');
    }

    public function store(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $file = $request->file('document');
        $path = $file->store('invoices', 'local');
        $fullPath = storage_path('app/private/' . $path);

        try {
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
                'Document' => [
                    'Bytes' => $fileBytes,
                ],
            ]);

            $lines = [];
            foreach ($result['Blocks'] as $block) {
                if ($block['BlockType'] === 'LINE') {
                    $lines[] = $block['Text'];
                }
            }

            $extractedText = implode("\n", $lines);

            // Strukturování dat pomocí Claude Haiku
            $invoiceData = $this->extractInvoiceData($extractedText);

            return view('invoices.result', [
                'filename' => $file->getClientOriginalName(),
                'text' => $extractedText,
                'invoiceData' => $invoiceData,
                'rawJson' => json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['document' => 'Chyba při zpracování: ' . $e->getMessage()]);
        }
    }

    private function extractInvoiceData(string $text): array
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            return ['_error' => 'Anthropic API klíč není nastaven v .env'];
        }

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
            'system' => 'Jsi asistent pro zpracování českých faktur. Z textu faktury extrahuj strukturovaná data a vrať POUZE validní JSON objekt (žádný další text). Použij tyto klíče:
{
  "dodavatel": "název dodavatele/firmy",
  "ico": "IČO dodavatele",
  "dic": "DIČ dodavatele",
  "cislo_faktury": "číslo faktury",
  "datum_vystaveni": "datum vystavení ve formátu YYYY-MM-DD",
  "datum_splatnosti": "datum splatnosti ve formátu YYYY-MM-DD",
  "castka_celkem": 0.00,
  "mena": "CZK",
  "castka_dph": 0.00,
  "kategorie": "jedna z: služby, materiál, energie, telekomunikace, nájem, pojištění, ostatní"
}
Pokud některý údaj nelze z textu zjistit, použij null.',
        ]);

        if ($response->failed()) {
            return ['_error' => 'Claude API chyba: ' . $response->body()];
        }

        $body = $response->json();
        $content = $body['content'][0]['text'] ?? '';

        // Extrahovat JSON z odpovědi (může být obalený v markdown code block)
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        }

        return ['_error' => 'Nepodařilo se parsovat odpověď z Claude: ' . $content];
    }
}
