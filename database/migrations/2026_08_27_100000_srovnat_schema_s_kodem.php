<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doplní části schématu, které v migracích chyběly.
 *
 * Databáze se dosud nedala postavit od nuly — `migrate:fresh` spadl, protože
 * dvě tabulky (`sys_pozvani`, `fak_kategorie`) a řada sloupců vznikly kdysi
 * ručně a migrace k nim nikdo nedopsal. Kód je přitom používá, takže produkce
 * je má; chybí jen předpis, jak je vytvořit.
 *
 * Proto je celá migrace psaná podmíněně: co už existuje, se nechává být. Na
 * produkci se tím prakticky nic nestane, na čisté databázi se doplní všechno.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Chybějící tabulky
        // ---------------------------------------------------------------

        if (!Schema::hasTable('sys_pozvani')) {
            Schema::create('sys_pozvani', function (Blueprint $table) {
                $table->id();
                $table->string('firma_ico', 20);
                $table->string('jmeno')->nullable();
                $table->string('email');
                $table->enum('interni_role', ['superadmin', 'spravce'])->default('spravce');
                $table->string('token', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamps();

                $table->foreign('firma_ico')->references('ico')->on('sys_firmy')->onDelete('cascade');
                $table->index(['firma_ico', 'email']);
            });
        }

        if (!Schema::hasTable('fak_kategorie')) {
            Schema::create('fak_kategorie', function (Blueprint $table) {
                $table->id();
                $table->string('firma_ico', 20);
                $table->string('nazev');
                $table->string('popis')->nullable();
                $table->unsignedSmallInteger('poradi')->default(0);
                $table->timestamps();

                $table->foreign('firma_ico')->references('ico')->on('sys_firmy')->onDelete('cascade');
                $table->index('firma_ico');
            });
        }

        // ---------------------------------------------------------------
        // sys_firmy — vlastní IMAP schránka a napojení na Google Drive
        // ---------------------------------------------------------------

        $this->doplnSloupce('sys_firmy', [
            // Systémová schránka {IČO}@tuptudu.cz je zapnutá automaticky —
            // bez toho by nově založená firma nemohla posílat doklady mailem.
            'email_system_aktivni' => fn (Blueprint $t) => $t->boolean('email_system_aktivni')->default(true),
            'email_vlastni_aktivni' => fn (Blueprint $t) => $t->boolean('email_vlastni_aktivni')->default(false),
            'email_vlastni' => fn (Blueprint $t) => $t->string('email_vlastni')->nullable(),
            'email_vlastni_host' => fn (Blueprint $t) => $t->string('email_vlastni_host')->nullable(),
            'email_vlastni_port' => fn (Blueprint $t) => $t->unsignedSmallInteger('email_vlastni_port')->nullable(),
            'email_vlastni_sifrovani' => fn (Blueprint $t) => $t->string('email_vlastni_sifrovani', 10)->nullable(),
            'email_vlastni_uzivatel' => fn (Blueprint $t) => $t->string('email_vlastni_uzivatel')->nullable(),
            'email_vlastni_heslo' => fn (Blueprint $t) => $t->text('email_vlastni_heslo')->nullable(),

            'google_drive_aktivni' => fn (Blueprint $t) => $t->boolean('google_drive_aktivni')->default(false),
            // Šifrovaný přes encrypt()/decrypt(), proto text a ne string.
            'google_refresh_token' => fn (Blueprint $t) => $t->text('google_refresh_token')->nullable(),
            'google_folder_id' => fn (Blueprint $t) => $t->string('google_folder_id')->nullable(),
            // Prázdné = jede se na DrivePathBuilder::DEFAULT_TEMPLATE.
            'google_drive_sablona' => fn (Blueprint $t) => $t->string('google_drive_sablona')->nullable(),
        ]);

        // ---------------------------------------------------------------
        // fak_doklady — odběratel, původní soubor, stopa po Google Drivu
        // ---------------------------------------------------------------

        $this->doplnSloupce('fak_doklady', [
            // Originál před převodem na PDF; prázdné = originál je cesta_souboru.
            'cesta_originalu' => fn (Blueprint $t) => $t->string('cesta_originalu')->nullable(),
            'odberatel_ico' => fn (Blueprint $t) => $t->string('odberatel_ico', 20)->nullable(),
            'odberatel_nazev' => fn (Blueprint $t) => $t->string('odberatel_nazev')->nullable(),
            // E-mail toho, kdo doklad nahrál (u mailového příjmu odesílatel).
            'nahral' => fn (Blueprint $t) => $t->string('nahral')->nullable(),

            'google_drive_file_id' => fn (Blueprint $t) => $t->string('google_drive_file_id')->nullable(),
            'google_drive_ucetni_file_id' => fn (Blueprint $t) => $t->string('google_drive_ucetni_file_id')->nullable(),
            'google_drive_nahrano_at' => fn (Blueprint $t) => $t->timestamp('google_drive_nahrano_at')->nullable(),
        ]);

        // Fronta pro Drive se čte přes `nahrano_at IS NULL` — bez indexu by to
        // s rostoucím počtem dokladů znamenalo projít celou tabulku.
        if (!$this->maIndex('fak_doklady', 'fak_doklady_google_drive_nahrano_at_index')) {
            Schema::table('fak_doklady', function (Blueprint $table) {
                $table->index('google_drive_nahrano_at');
            });
        }

        // ---------------------------------------------------------------
        // sys_ucetni_vazby — oprávnění účetní u klienta
        // ---------------------------------------------------------------

        $this->doplnSloupce('sys_ucetni_vazby', [
            'zadost_odeslana_at' => fn (Blueprint $t) => $t->timestamp('zadost_odeslana_at')->nullable(),
            // Schválení vazby samo o sobě oprávnění nenastavuje, takže je určuje
            // výchozí hodnota. Účetní má po schválení rovnou pracovat s doklady;
            // mazání zůstává vypnuté, to ať klient povolí výslovně.
            'perm_vkladat' => fn (Blueprint $t) => $t->boolean('perm_vkladat')->default(true),
            'perm_upravovat' => fn (Blueprint $t) => $t->boolean('perm_upravovat')->default(true),
            'perm_mazat' => fn (Blueprint $t) => $t->boolean('perm_mazat')->default(false),
        ]);

        // ---------------------------------------------------------------
        // sys_user_firma — role uvnitř firmy
        // ---------------------------------------------------------------

        $this->doplnSloupce('sys_user_firma', [
            'interni_role' => fn (Blueprint $t) => $t->enum('interni_role', ['superadmin', 'spravce'])->default('spravce'),
        ]);
    }

    public function down(): void
    {
        // Zpětný krok schválně neděláme. Migrace nic nemění, jen dorovnává
        // chybějící kusy schématu — vrátit je zpět by znamenalo smazat data,
        // která aplikace běžně používá.
    }

    /**
     * Přidá jen ty sloupce, které v tabulce ještě nejsou.
     *
     * @param  array<string, callable(Blueprint): mixed>  $sloupce
     */
    private function doplnSloupce(string $tabulka, array $sloupce): void
    {
        $chybejici = array_filter(
            $sloupce,
            fn (string $nazev) => !Schema::hasColumn($tabulka, $nazev),
            ARRAY_FILTER_USE_KEY,
        );

        if ($chybejici === []) {
            return;
        }

        Schema::table($tabulka, function (Blueprint $table) use ($chybejici) {
            foreach ($chybejici as $definice) {
                $definice($table);
            }
        });
    }

    private function maIndex(string $tabulka, string $index): bool
    {
        return collect(Schema::getIndexes($tabulka))
            ->contains(fn (array $i) => $i['name'] === $index);
    }
};
