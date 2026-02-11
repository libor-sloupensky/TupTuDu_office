<?php

namespace App\Http\Controllers;

use App\Models\Doklad;
use App\Models\Firma;
use App\Services\DokladProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        if (!$disk->exists($doklad->cesta_souboru)) {
            abort(404, 'Soubor nebyl nalezen.');
        }

        return response()->streamDownload(function () use ($disk, $doklad) {
            echo $disk->get($doklad->cesta_souboru);
        }, $doklad->nazev_souboru);
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
        $tempPath = $file->getRealPath();
        $fileHash = hash_file('sha256', $tempPath);

        $processor = new DokladProcessor();

        // Kontrola duplicit
        $existujici = $processor->isDuplicate($fileHash, $firma->ico);
        if ($existujici) {
            return back()->withErrors([
                'document' => 'Tento doklad byl již nahrán ('
                    . ($existujici->cislo_dokladu ?: $existujici->nazev_souboru)
                    . ', ' . $existujici->created_at->format('d.m.Y H:i') . ').',
            ]);
        }

        // Upload na S3
        $s3Path = 'doklady/' . $firma->ico . '/' . time() . '_' . $file->getClientOriginalName();
        Storage::disk('s3')->put($s3Path, file_get_contents($tempPath));

        $doklad = $processor->process(
            $tempPath,
            $file->getClientOriginalName(),
            $firma,
            $s3Path,
            $fileHash,
            'upload'
        );

        if ($doklad->stav === 'chyba') {
            return back()->withErrors(['document' => 'Chyba při zpracování: ' . $doklad->chybova_zprava]);
        }

        return redirect()->route('doklady.show', $doklad);
    }
}
