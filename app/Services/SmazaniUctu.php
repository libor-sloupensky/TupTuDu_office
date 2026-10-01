<?php

namespace App\Services;

use App\Mail\UcetKeSmazani;
use App\Models\Doklad;
use App\Models\Firma;
use App\Models\Pozvani;
use App\Models\UcetniVazba;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Smazání uživatelského účtu i s daty, která k němu patří.
 *
 * Právo na výmaz plyne z GDPR (čl. 17) a Google Play totéž vyžaduje u aplikací,
 * kde si člověk zakládá účet — musí jít smazat přímo v aplikaci a zároveň musí
 * existovat veřejná adresa, kde o to lze požádat.
 *
 * **Nemaže se hned.** Účet se uzavře a teprve po uplynutí lhůty se nenávratně
 * smaže; do té doby ho jde obnovit. Je to ochrana proti omylu, kterou dělají
 * i velké služby, a GDPR ji připouští, protože je popsaná v zásadách a data se
 * mezitím k ničemu nepoužívají. Kdo chce smazat okamžitě, napíše nám — právo na
 * výmaz bez odkladu lhůtou obejít nejde.
 *
 * Co se nakonec smaže:
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
 */
class SmazaniUctu
{
    /** Kolik dní má uživatel na rozmyšlenou. */
    public const DNI_LHUTY = 7;

    /**
     * Uzavře účet a naplánuje jeho smazání.
     *
     * Rovnou se nemaže nic — jen se nastaví datum a odejde e-mail s odkazem na
     * obnovení. Které firmy nakonec zmizí, se počítá až při dokončení: členství
     * se do té doby může změnit a tehdejší odhad by už nemusel platit.
     */
    public function pozadej(User $user): User
    {
        $user->forceFill([
            'smazani_k' => now()->addDays(self::DNI_LHUTY),
            'obnoveni_token' => Str::random(64),
        ])->save();

        try {
            Mail::to($user->email)->send(new UcetKeSmazani($user));
        } catch (\Throwable $e) {
            // E-mail je služba navíc; obnovit účet jde i po přihlášení.
            Log::warning("Zpráva o uzavření účtu neodešla: {$e->getMessage()}", ['user_id' => $user->id]);
        }

        return $user;
    }

    /** Vrátí uzavřený účet do běžného stavu. */
    public function obnov(User $user): User
    {
        $user->forceFill(['smazani_k' => null, 'obnoveni_token' => null])->save();

        return $user;
    }

    /** Účet podle obnovovacího odkazu z e-mailu, nebo null. */
    public function podleTokenu(string $token): ?User
    {
        if ($token === '') {
            return null;
        }

        return User::whereNotNull('smazani_k')->where('obnoveni_token', $token)->first();
    }

    /**
     * Dokončí smazání u účtů, kterým lhůta uplynula.
     *
     * Volá se z cronu, který stejně běží každou minutu.
     *
     * @return int Počet smazaných účtů
     */
    public function dokonciSplatne(): int
    {
        $splatne = User::whereNotNull('smazani_k')->where('smazani_k', '<=', now())->get();
        $hotovo = 0;

        foreach ($splatne as $user) {
            try {
                $this->smaz($user);
                $hotovo++;
            } catch (\Throwable $e) {
                // Jeden zaseknutý účet nesmí zablokovat ostatní; příště se zkusí znovu.
                Log::error("Smazání účtu selhalo: {$e->getMessage()}", ['user_id' => $user->id]);
            }
        }

        return $hotovo;
    }

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

        foreach ($user->firmy()->get() as $firma) {
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

    /** Nenávratné smazání. Volá se až po uplynutí lhůty. */
    public function smaz(User $user): void
    {
        $osobni = Firma::where('je_osobni', true)->where('vlastnik_user_id', $user->id)->first();

        // Firmy, které po odchodu zůstanou prázdné, mizí i s doklady.
        $keSmazani = [];
        foreach ($user->firmy()->get() as $firma) {
            if ($firma->jeOsobni()) {
                continue;
            }

            if ($this->jePoslednimClenem($user, $firma)) {
                $keSmazani[] = $firma;
            } else {
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

            $user->delete();
        });
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
