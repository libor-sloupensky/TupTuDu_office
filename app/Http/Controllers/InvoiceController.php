<?php

namespace App\Http\Controllers;

use Aws\Textract\TextractClient;
use Illuminate\Http\Request;

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

            return view('invoices.result', [
                'filename' => $file->getClientOriginalName(),
                'text' => $extractedText,
                'rawJson' => json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['document' => 'Chyba při zpracování: ' . $e->getMessage()]);
        }
    }
}
