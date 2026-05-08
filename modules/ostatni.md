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
| `.github/workflows/deploy.yml` | Deploy: push main → lftp SFTP na Webglobe, change detection, migrace |

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
- Šablona cesty: default `{nahrano:YYYY}/{duzp:YY-MM-DD}_{dodavatel:15}_{id}`
- 13 tokenů: id, nahrano, duzp, vystaveni, splatnost, dodavatel, dodavatel_ico, ico, castka, vs, typ, kategorie, cislo
- Metadata embedding: PDF (FPDI title/keywords), JPEG (IPTC)
- Auto-deactivation při `invalid_grant` / `Token revoked`

## Nastavení firmy (UI)
- Rozbalovací sekce s ikonkami (emoji + arrow toggle)
- Auto-rozbalení dle kontextu (URL parametr, čekající žádosti)
- AJAX operace: přidání/odebrání uživatelů, zrušení pozvánek, editace rolí
- Konfigurace: email dokladů (systémový/vlastní IMAP), Google Drive šablona, kategorie

## Deploy
- GitHub Actions → SFTP (lftp) na Webglobe
- Change detection: pouze změněné soubory
- `composer.lock` change → vendor install
- Migrace: inline PHP
- Verifikace: HTTP 200 check na `office.tuptudu.cz`

## Cron routy (tajný token)
- `GET /cron/{token}` — email processing
- `GET /cron-drive/{token}` — Google Drive sync

## Databázové tabulky

| Tabulka | PK | Popis |
|---------|-----|-------|
| `sys_users` | id | Uživatelé (email, password, jmeno, prijmeni) |
| `sys_firmy` | ico (string) | Firmy (nazev, je_ucetni, google_drive_*, email_*) |
| `sys_user_firma` | — | Pivot: user_id, firma_ico, role, interni_role |
| `sys_pozvani` | id | Pozvánky: firma_ico, email, token, expires_at, accepted_at |
| `sys_ucetni_vazby` | id | Účetní vazby: ucetni_ico, klient_ico, stav, perm_* |
| `fak_kategorie` | id | Kategorie: firma_ico, nazev, poradi (15 výchozích) |

---
*Aktualizováno: 2026-04-09*
