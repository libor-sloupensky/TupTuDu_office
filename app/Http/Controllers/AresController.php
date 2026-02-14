<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class AresController extends Controller
{
    public static function fetchAres(string $ico): ?array
    {
        $response = Http::timeout(10)->get(
            "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/{$ico}"
        );

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        $sidlo = $data['sidlo'] ?? [];

        $ulice = null;
        if (isset($sidlo['nazevUlice'])) {
            $ulice = $sidlo['nazevUlice'];
            if (isset($sidlo['cisloDomovni'])) {
                $ulice .= ' ' . $sidlo['cisloDomovni'];
                if (isset($sidlo['cisloOrientacni'])) {
                    $ulice .= '/' . $sidlo['cisloOrientacni'];
                }
            }
        } elseif (isset($sidlo['textovaAdresa'])) {
            $ulice = $sidlo['textovaAdresa'];
        }

        return [
            'ico' => $data['ico'] ?? $ico,
            'nazev' => $data['obchodniJmeno'] ?? null,
            'dic' => $data['dic'] ?? null,
            'ulice' => $ulice,
            'mesto' => $sidlo['nazevObce'] ?? null,
            'psc' => isset($sidlo['psc']) ? (string) $sidlo['psc'] : null,
        ];
    }

    public function lookup(string $ico)
    {
        if (!preg_match('/^\d{8}$/', $ico)) {
            return response()->json(['error' => 'IČO musí být 8 číslic.'], 422);
        }

        $data = self::fetchAres($ico);

        if (!$data) {
            return response()->json(['error' => 'Subjekt nenalezen v ARES.'], 404);
        }

        return response()->json($data);
    }
}
