<?php

namespace App\Services;

use App\Models\Doklad;
use App\Models\Firma;
use App\Models\Pozvani;
use App\Models\UcetniVazba;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Smazání uživatelského účtu i s daty, která k němu patří.
 *
 * Vyžaduje to Google Play u každé aplikace, kde si člověk zakládá účet: musí
 * jít smazat přímo v aplikaci a zároveň musí existovat veřejná adresa, kde o to
 * lze požádat.
 *
 * Co se smaže:
 *   - samotný účet (jméno, e-mail, telefon, heslo, napojení na Google),
 *   - osobní doklady i se soubory v úložišti,
 *   - firmy, ve kterých byl posledním členem — nikdo jiný by se k nim nedostal,
 *   - pozvánky vystavené na jeho e-mail.
 *
 * Co zůstává:
 *   - firmy, kde jsou další lidé, i s jejich doklady. Nejsou jeho; odejde z nich
 *     jen on. Když byl jediným správcem, povýší se nejdéle přiřazený člen, aby
 *     firma nezůstala bez správy.
 *   - záznamy o nákladech na zpracování (`sys_ai_volani`) — nejsou osobní údaj
 *     a zpětně by se bez nich rozpadla čísla za uzavřené měsíce.
 *
 * Mazání je nevratné a dělá se hned. Proto se před ním ukazuje přehled toho, co
 * zmizí, a potvrzuje se opsáním e-mailu.
 */
class SmazaniUctu
{
    /**
     * Co se při smazání účtu stane — podklad pro rozhodnutí uživatele.
     *
     * @return array{osobnich_dokladu: int, firmy_zmizi: array<int, array{nazev: string, dokladu: int, ucetni: ?string}>, firmy_zustanou: array<int, string>}
     */
    public function prehled(User $user): array
    {
        $osobni = Firma::where('je_osobni', true)->where('vlastnik_user_id', $user->id)->first();

        $zmizi = [];
        $zustanou = [];

        foreach ($this->firmyUzivatele($user) as $firma) {
            if ($firma->jeOsobni()) {
                continue;
            }

            if ($this->jePoslednimClenem($user, $firma)) {
                $ucetni = UcetniVazba::where('klient_ico', $firma->ico)
                    ->where('stav', 'schvaleno')
                    ->first();

                $zmizi[] = [
                    'nazev' => $firma->nazev,
                    'dokladu' => $firma->doklady()->count(),
                    'ucetni' => $ucetni ? Firma::find($ucetni->ucetni_ico)?->nazev : null,
                ];
            } else {
                $zustanou[] = $firma->nazev;
            }
        }

        return [
            'osobnich_dokladu' => $osobni ? $osobni->doklady()->count() : 0,
            'firmy_zmizi' => $zmizi,
            'firmy_zustanou' => $zustanou,
        ];
    }

    public function smaz(User $user): void
    {
        $osobni = Firma::where('je_osobni', true)->where('vlastnik_user_id', $user->id)->first();

        // Firmy, které po odchodu zůstanou prázdné, mizí i s doklady.
        $keSmazani = [];
        foreach ($this->firmyUzivatele($user) as $firma) {
            if (!$firma->jeOsobni() && $this->jePoslednimClenem($user, $firma)) {
                $keSmazani[] = $firma;
            } elseif (!$firma->jeOsobni()) {
                $this->predejSpravu($user, $firma);
            }
        }

        if ($osobni) {
            $keSmazani[] = $osobni;
        }

        // Soubory se musí uklidit dřív, než zmizí záznamy — potom už by nebylo
        // podle čeho je najít.
        foreach ($keSmazani as $firma) {
            $this->smazSoubory($firma);
        }

        DB::transaction(function () use ($user, $keSmazani) {
            Pozvani::where('email', $user->email)->delete();
            DB::table('sys_sessions')->where('user_id', $user->id)->delete();

            foreach ($keSmazani as $firma) {
                // Doklady, kategorie, vazby i členství zmizí kaskádou.
                $firma->delete();
            }

            // Osobní prostor by sice zmizel kaskádou přes vlastnik_user_id, ale
            // výše je smazaný adresně, ať je pořadí úklidu souborů jisté.
            $user->delete();
        });
    }

    /** @return \Illuminate\Support\Collection<int, Firma> */
    private function firmyUzivatele(User $user)
    {
        return $user->firmy()->get();
    }

    private function jePoslednimClenem(User $user, Firma $firma): bool
    {
        return $firma->users()->where('sys_users.id', '!=', $user->id)->doesntExist();
    }

    /**
     * Když odchází jediný správce, povýší se nejdéle přiřazený ze zbývajících —
     * jinak by firma zůstala bez někoho, kdo může spravovat uživatele.
     */
    private function predejSpravu(User $user, Firma $firma): void
    {
        $jinySpravce = $firma->users()
            ->wherePivot('interni_role', 'superadmin')
            ->where('sys_users.id', '!=', $user->id)
            ->exists();

        if ($jinySpravce) {
            return;
        }

        $nastupce = $firma->users()
            ->where('sys_users.id', '!=', $user->id)
            ->orderBy('sys_user_firma.created_at')
            ->first();

        if ($nastupce) {
            $firma->users()->updateExistingPivot($nastupce->id, ['interni_role' => 'superadmin']);
        }
    }

    /** Smaže z úložiště soubory všech dokladů firmy i odložené souřadnice slov. */
    private function smazSoubory(Firma $firma): void
    {
        $disk = Storage::disk('s3');

        $firma->doklady()
            ->select(['id', 'cesta_souboru', 'cesta_originalu'])
            ->chunkById(200, function ($doklady) use ($disk) {
                $cesty = [];

                foreach ($doklady as $doklad) {
                    foreach ([$doklad->cesta_souboru, $doklad->cesta_originalu] as $cesta) {
                        if (!$cesta) {
                            continue;
                        }
                        $cesty[] = $cesta;
                        $cesty[] = DokladProcessor::cestaSlov($cesta);
                    }
                }

                if (!$cesty) {
                    return;
                }

                try {
                    $disk->delete($cesty);
                } catch (\Throwable $e) {
                    // Záznamy se smažou tak jako tak; osiřelý soubor v úložišti
                    // je menší zlo než účet, který nejde smazat.
                    Log::warning("Úklid souborů při mazání účtu selhal: {$e->getMessage()}");
                }
            });
    }
}
