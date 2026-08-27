<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\AresController;
use App\Http\Controllers\Controller;
use App\Models\Firma;
use App\Models\FirmaPartner;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Partnerské API.
 *
 * Partner smí napojit firmu a podívat se na její základní údaje. K dokladům
 * se nedostane — žádná zdejší odpověď je neobsahuje a jiné routy pod tímhle
 * klíčem neexistují.
 */
class PartnerController extends Controller
{
    private function partner(Request $request): Partner
    {
        return $request->attributes->get('partner');
    }

    /** Základní údaje o firmě tak, jak je partner smí vidět. */
    private function firmaProPartnera(Firma $firma, FirmaPartner $napojeni): array
    {
        return [
            'ico' => $firma->ico,
            'nazev' => $firma->nazev,
            'email_pro_doklady' => $firma->email_doklady,
            'napojeno_at' => $napojeni->napojeno_at?->toIso8601String(),
            // Firma je "převzatá", dokud se v ní nikdo nezaregistroval.
            'ma_uzivatele' => $firma->users()->exists(),
        ];
    }

    public function seznamFirem(Request $request): JsonResponse
    {
        $partner = $this->partner($request);

        $firmy = $partner->firmy()->orderBy('nazev')->get()->map(function (Firma $firma) {
            $napojeni = new FirmaPartner([
                'napojeno_at' => $firma->pivot->napojeno_at,
                'odpojeno_at' => $firma->pivot->odpojeno_at,
            ]);

            return $this->firmaProPartnera($firma, $napojeni);
        });

        return response()->json(['firmy' => $firmy]);
    }

    public function detailFirmy(Request $request, string $ico): JsonResponse
    {
        $partner = $this->partner($request);

        $napojeni = FirmaPartner::where('partner_id', $partner->id)
            ->where('firma_ico', $ico)
            ->whereNull('odpojeno_at')
            ->first();

        if (!$napojeni) {
            return response()->json(['chyba' => 'Firma není napojená na tohoto partnera.'], 404);
        }

        return response()->json($this->firmaProPartnera($napojeni->firma, $napojeni));
    }

    /**
     * Napojí firmu na partnera. Firmu, která v systému ještě není, přitom
     * podle IČO založí — údaje se berou z ARES, ať se nepřepisují ručně.
     *
     * Firma, kterou už veze jiný partner, se odmítne. Převod mezi partnery je
     * samostatná věc: musí u něj rozhodovat firma, ne partner, který si ji
     * chce vzít.
     */
    public function napojitFirmu(Request $request): JsonResponse
    {
        $partner = $this->partner($request);

        $request->validate([
            'ico' => 'required|string|regex:/^\d{8}$/',
        ]);

        $ico = $request->input('ico');

        try {
            $vysledek = DB::transaction(function () use ($partner, $ico) {
                $firma = Firma::find($ico);

                if (!$firma) {
                    $ares = AresController::fetchAres($ico);
                    if (!$ares || !$ares['nazev']) {
                        return ['chyba' => 'IČO nebylo nalezeno v ARES.', 'kod' => 422];
                    }

                    $firma = Firma::create([
                        'ico' => $ico,
                        'nazev' => $ares['nazev'],
                        'dic' => $ares['dic'],
                        'ulice' => $ares['ulice'],
                        'mesto' => $ares['mesto'],
                        'psc' => $ares['psc'],
                        'email_doklady' => $ico . '@' . config('mail.doklady_domain'),
                    ]);

                    Firma::seedDefaultKategorie($firma->ico);
                }

                $stavajici = FirmaPartner::where('firma_ico', $ico)->whereNull('odpojeno_at')->first();

                if ($stavajici) {
                    if ($stavajici->partner_id === $partner->id) {
                        return ['napojeni' => $stavajici, 'firma' => $firma, 'kod' => 200];
                    }

                    return ['chyba' => 'Firma je už napojená na jiného partnera.', 'kod' => 409];
                }

                $napojeni = FirmaPartner::create([
                    'firma_ico' => $firma->ico,
                    'partner_id' => $partner->id,
                    'napojeno_at' => now(),
                ]);

                return ['napojeni' => $napojeni, 'firma' => $firma, 'kod' => 201];
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Dva souběžné požadavky na tutéž firmu — jeden vyhrál, druhý ať
            // dostane stejnou odpověď, jako by přišel o vteřinu později.
            return response()->json(['chyba' => 'Firma je už napojená na jiného partnera.'], 409);
        }

        if (isset($vysledek['chyba'])) {
            return response()->json(['chyba' => $vysledek['chyba']], $vysledek['kod']);
        }

        return response()->json(
            $this->firmaProPartnera($vysledek['firma'], $vysledek['napojeni']),
            $vysledek['kod'],
        );
    }

    /** Ukončí napojení. Firma i její doklady zůstávají, mizí jen vazba. */
    public function odpojitFirmu(Request $request, string $ico): JsonResponse
    {
        $partner = $this->partner($request);

        $napojeni = FirmaPartner::where('partner_id', $partner->id)
            ->where('firma_ico', $ico)
            ->whereNull('odpojeno_at')
            ->first();

        if (!$napojeni) {
            return response()->json(['chyba' => 'Firma není napojená na tohoto partnera.'], 404);
        }

        $napojeni->update(['odpojeno_at' => now()]);

        return response()->json(['odpojeno' => true]);
    }
}
