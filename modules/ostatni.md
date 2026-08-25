# Modul: Ostatní (Auth, Firmy, Nastavení, Google Drive, Deploy)

## Co tento modul obsahuje
Vše co nespadá do nahrávání/extrakce a zobrazení — auth, firmy, uživatelé, pozvánky, účetní vazby, nastavení, Google Drive, deploy.

## Aktuální stav
Plně funkční — registrace (s pozvánkou i bez), přihlášení, správa uživatelů, účetní vazby, Google Drive OAuth, deploy přes GitHub Actions.

## Klíčové soubory

| Soubor | Funkce |
|--------|--------|
| `app/Http/Controllers/Auth/RegisterController.php` | Registrace: s pozvánkou, s IČO, auto-detekce pozvánky pro obsazenou firmu |
| `app/Http/Controllers/Auth/LoginController.php` | Přihlášení/odhlášení, nastavení session `aktivni_firma_ico` |
| `app/Http/Controllers/FirmaController.php` | 28 metod — nastavení firmy, uživatelé, pozvánky, kategorie, email config, Drive šablona |
| `app/Http/Controllers/KlientiController.php` | Správa klientů pro účetní firmu (CRUD, ARES lookup, žádost o vedení) |
| `app/Http/Controllers/VazbyController.php` | Schválení/zamítnutí/odpojení účetní vazby, oprávnění |
| `app/Http/Controllers/AresController.php` | ARES API lookup |
| `app/Services/GoogleDriveService.php` | OAuth, upload, folder cache, metadata embedding, auto-deactivation |
| `app/Services/DrivePathBuilder.php` | Šablony cest pro Drive — 13 tokenů, validace, preview |
| `app/Models/User.php` | Relace firmy, `aktivniFirma()`, `jeSuperadmin()`, `dostupneIco()`, `prohlizimKlienta()` |
| `app/Models/Firma.php` | PK=IČO, relace users/klienti/ucetni/kategorie/doklady, `seedDefaultKategorie()` |
| `app/Models/Pozvani.php` | Token-based pozvánky, expire 7 dní, `jePlatna()` |
| `app/Models/UcetniVazba.php` | Stav: ceka_na_firmu→schvaleno/zamitnuto, granulární perm_* |
| `resources/views/firma/nastaveni.blade.php` | UI nastavení — rozbalovací sekce, AJAX operace |
| `resources/views/auth/registrace.blade.php` | Registrační formulář — 2-krokový, pozvánka, žádost o přístup |
| `routes/web.php` | Všechny routy — middleware: auth, verified, firma, role |
| `.github/workflows/deploy.yml` | Deploy: push main → lftp SFTP na Český hosting, change detection, migrace |

## Auth & registrace

### Registrační toky
1. **S pozvánkou** (URL `?pozvanka={token}`): email předvyplněn, bez IČO, email ověřen automaticky
2. **Se zakládáním firmy** (nové IČO): ARES lookup → vytvoření firmy → uživatel = superadmin
3. **Na obsazenou firmu** (IČO existuje + má uživatele):
   - Pokud existuje platná pozvánka pro email → automatické přijetí s rolí z pozvánky
   - Jinak → blokace + možnost „Požádat o přístup" (email superadminovi)

### Pozvánky
- Tabulka `sys_pozvani`: firma_ico, email, jmeno, interni_role, token (64 hex), expires_at (7 dní)
- Vytvoření: superadmin v nastavení → email `PozvankaDoFirmy`
- Zrušení: křížek v nastavení (`FirmaController::zrusitPozvanku`)
- Přijetí: registrace s tokenem nebo auto-detekce emailu při registraci s IČO

## Uživatelské role

```
Pivot: sys_user_firma (role, interni_role)

role: 'firma' (vlastní) | 'ucetni' (účetní)
interni_role: 'superadmin' | 'spravce'

Superadmin: nastavení firmy, správa uživatelů, přidávání pozvánek
Spravce: přístup k dokladům, bez správy uživatelů
Účetní: prohlížení klientských dokladů dle oprávnění (perm_vkladat/upravovat/mazat)
```

## Účetní vazby

```
sys_ucetni_vazby: ucetni_ico, klient_ico, stav, perm_*

Workflow:
1. Účetní přidá klienta (ARES lookup) → stav = 'ceka_na_firmu'
2. Klient obdrží email, schválí/zamítne ve VazbyController
3. Schváleno → účetní vidí doklady klienta s granulárními oprávněními
```

## Google Drive
- OAuth2 flow (drive.file scope), refresh token šifrovaný v DB
- Upload přes cron (ne real-time), batch 50 dokladů
- Folder cache pro hierarchii složek
- Metadata embedding: PDF (FPDI title/keywords), JPEG (IPTC)
- Auto-deactivation při `invalid_grant` / `Token revoked`

### Struktura na Disku
```
{kořenová složka}/{IČO}_{název firmy}/{cesta ze šablony}
```
Složku firmy zakládá `GoogleDriveService::nazevSlozkyFirmy()`, **ne šablona** —
dávat `{ico}` nebo `{firma}` do šablony proto znamená mít údaj v cestě dvakrát.

Šablona cesty: default `{nahrano:YYYY}/{duzp:YY-MM-DD}_{dodavatel:15}_{id}`,
14 tokenů: id, nahrano, duzp, vystaveni, splatnost, dodavatel, dodavatel_ico,
ico, **firma**, castka, vs, typ, kategorie, cislo.

Nastavení ukazuje, jestli firma jede na výchozí šabloně, nebo má vlastní.

**Pozor na režim synchronizace:** soubory jsou v cloudu; jestli je uživatel má
i fyzicky na disku, určuje aplikace Disk Google pro počítač (Streamování vs.
Zrcadlení). Ze serveru to ovlivnit nejde.

## Nastavení firmy (UI)
- Rozbalovací sekce s ikonkami (emoji + arrow toggle)
- Auto-rozbalení dle kontextu (URL parametr, čekající žádosti)
- AJAX operace: přidání/odebrání uživatelů, zrušení pozvánek, editace rolí
- Konfigurace: email dokladů (systémový/vlastní IMAP), Google Drive šablona, kategorie

## Deploy
- GitHub Actions → SFTP (lftp) na Český hosting (`irene.thinline.cz`, port 22, uživatel `tuptudu_cz`)
- Change detection: pouze změněné soubory (diff proti poslednímu úspěšnému deployi)
- Ruční spuštění s `full=true` → kompletní deploy včetně vendoru (stěhování serveru)
- `composer.lock` change → vendor install + mirror
- Migrace: `curl` na routu `/deploy-migrace/{token}` → `artisan migrate --force`
  (MariaDB poslouchá jen na 127.0.0.1, z runneru se k ní připojit nedá)
- Verifikace: HTTP 200 check na `office.tuptudu.cz`

### Konfigurace deploye (GitHub → Settings → Secrets and variables → Actions)
Vše je v **Variables**, aby šlo měnit bez commitu; hesla ve **Secrets**.

| Variable | Výchozí | Význam |
|----------|---------|--------|
| `SFTP_HOST` | `irene.thinline.cz` | SFTP server |
| `SFTP_USER` | `tuptudu_cz` | SFTP uživatel |
| `SFTP_PORT` | `22` | SFTP port |
| `REMOTE_APP` | `/office.tuptudu.cz` | Kořen Laravelu = adresář subdomény |
| `REMOTE_PUB` | `/office.tuptudu.cz/public` | Webroot subdomény (DocumentRoot posunutý na `public`) |
| `REMOTE_LANDING` | — | Webroot hlavní domény pro `landing/`; prázdné = nenasazuje se |
| `DB_HOST` / `DB_NAME` / `DB_USER` | `127.0.0.1` / `tuptuducz` / `tuptuducz001` | Produkční MariaDB |
| `MAIL_HOST` / `MAIL_USER` | `smtp.cesky-hosting.cz` / `info@tuptudu.cz` | Odchozí pošta |
| `IMAP_HOST` / `IMAP_USER` | `mail.cesky-hosting.cz` / `doklady@tuptudu.cz` | Sběrná schránka dokladů |
| `MAIL_DOKLADY_DOMAIN` | `tuptudu.cz` | Doména adres `{IČO}@…` pro příjem i odpovědi |
| `APP_URL` | `https://office.tuptudu.cz` | Cíl cronu a verifikace |
| `CRON_ENABLED` | — | `1` zapne plánovaný cron přes GitHub Actions |
| `PHP_VERSION` | `8.4` | Verze PHP pro composer install — musí odpovídat hostingu |

Secrets: `FTP_PASSWORD`, `DB_PASSWORD`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`,
`ANTHROPIC_API_KEY`, `MAIL_PASSWORD_INFO` (info@tuptudu.cz), `MAIL_PASSWORD_DOKLADY` (doklady@tuptudu.cz),
`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `SERVISNI_TOKEN`.

**`SERVISNI_TOKEN`** chrání servisní routy (`/cron`, `/cron-drive`, `/deploy-migrace`,
`/test-mail`, `/log`) i skripty `kontrola.php` a `diagnose.php`. Repozitář je veřejný,
proto token nesmí být v kódu — routy ho čtou přes `config('services.servisni_token')`,
skripty přímo z `.env`.

### Workflows
| Soubor | Spouštění | Účel |
|--------|-----------|------|
| `.github/workflows/deploy.yml` | push na main + ručně | Deploy |
| `.github/workflows/cron.yml` | každých 15 min (jen při `CRON_ENABLED=1`) | Náhrada cronu hostingu |
| `.github/workflows/remote-ls.yml` | ručně | Výpis struktury adresářů na serveru |
| `.github/workflows/fix-perms.yml` | ručně | Oprava práv souborů |
| `.github/workflows/remote-rm.yml` | ručně | Smazání adresáře na serveru (vyžaduje potvrzení) |

### Rozložení na serveru
```
/office.tuptudu.cz/          kořen Laravelu (.env, app/, vendor/) — mimo dosah webu
    public/                  DocumentRoot (posunutý v panelu na podadresář public)
```
PHP má `open_basedir` omezený na adresář subdomény, takže kořen aplikace musí
ležet uvnitř něj — mimo něj (např. v `/data/`) by ho PHP nepřečetlo. `index.php`
proto bere kořen o úroveň výš a hledání `laravel-office/` zůstává jen jako
záloha pro jiné hostingy.

Prostředí serveru ověří `public/kontrola.php?key={token}` — vypíše verzi PHP,
rozšíření, `open_basedir`, cesty, práva k zápisu a spojení na DB, SMTP, IMAP a S3.
Má výjimku z vynucení HTTPS, aby šla spustit i před vydáním certifikátu.

## Cron routy (tajný token)
- `GET /cron/{token}` — email processing
- `GET /cron-drive/{token}` — Google Drive sync
- `GET /deploy-migrace/{token}` — spuštění migrací z deploye
- `GET /log/{token}?bytes=N` — konec aplikačního logu
- `GET /test-mail/{token}?to=…` — test odchozí pošty
- Volá je `.github/workflows/cron.yml` (nebo cron hostingu)

## Databázové tabulky

| Tabulka | PK | Popis |
|---------|-----|-------|
| `sys_users` | id | Uživatelé (email, password, jmeno, prijmeni) |
| `sys_firmy` | ico (string) | Firmy (nazev, je_ucetni, google_drive_*, email_*) |
| `sys_user_firma` | — | Pivot: user_id, firma_ico, role, interni_role |
| `sys_pozvani` | id | Pozvánky: firma_ico, email, token, expires_at, accepted_at |
| `sys_ucetni_vazby` | id | Účetní vazby: ucetni_ico, klient_ico, stav, perm_* |
| `fak_kategorie` | id | Kategorie: firma_ico, nazev, poradi (15 výchozích) |

## Pošta — ověření odesílatele

Kompletní řetěz, ověřený v hlavičkách skutečné odchozí zprávy:

| | |
|---|---|
| SPF | `v=spf1 ip4:91.239.200.81 ip6:2001:67c:e94:0:1:5bef:c851:1 include:spf.cesky-hosting.cz -all` |
| DKIM | selektory `irene-202608` (webserver), `rampa2-202608`, `webmails-202608` |
| DMARC | `_dmarc` → `v=DMARC1; p=none;` |
| DNSSEC | aktivní |

**Pozor na SPF:** `spf.cesky-hosting.cz` povoluje jen jejich *odesílací* servery.
Pošta z PHP `mail()` odchází přímo z webového serveru, jehož IP tam není — proto
musí být `ip4:` a `ip6:` naší adresy uvedené zvlášť. Server odesílá **přes IPv6**,
takže bez `ip6:` by SPF padalo i s IPv4 mechanismem.

`public/.user.ini` vypíná `mail.add_x_header` — PHP jinak přidává hlavičku
`X-PHP-Originating-Script`, kterou spamové filtry bodují. Direktiva je
`PHP_INI_PERDIR`, `ini_set()` ji za běhu nezmění a panel hostingu ji nenabízí;
`.user.ini` funguje, protože server běží jako FastCGI.

## Odesílání pošty
Hosting blokuje odchozí SMTP na všech portech (25/465/587) a zakazuje `proc_open`,
takže nefunguje ani `sendmail` transport Symfony. Jediná cesta ven je PHP `mail()`
(`sendmail_path=php_mail`), pro kterou má aplikace vlastní transport
`App\Mail\Transport\PhpMailTransport` registrovaný v `AppServiceProvider`.

Zapíná se `MAIL_MAILER=php_mail`; mailer `doklady` způsob odeslání dědí.
Transport nesestavuje zprávu znovu — rozdělí hotový MIME výstup Symfony na
hlavičky a tělo, takže přílohy i kódování zůstanou nedotčené.

## Email doklady — adresace
- Firmy přijímají doklady na `{IČO}@tuptudu.cz` (doména z `config('mail.doklady_domain')`).
- Mailový koš domény `tuptudu.cz` doručuje všechny takové adresy do `doklady@tuptudu.cz`.
- Parser bere i historickou variantu `{IČO}@doklady.tuptudu.cz`.
- Odpovědi odesílá `ProcessEmailDoklady::sendReply()` z `{IČO}@tuptudu.cz`
  přes mailer `doklady` (SMTP autentizace účtem `info@tuptudu.cz`).

---
*Aktualizováno: 2026-08-25*
