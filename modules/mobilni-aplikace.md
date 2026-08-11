# Modul: Mobilní aplikace (Capacitor)

## Co tento modul dělá
Android aplikace pro skenování účetních dokladů. Tenká shell apka — Capacitor WebView načítá `office.tuptudu.cz/mobile/prihlaseni`, používá nativní Google ML Kit Document Scanner (stejný, co používá Google Drive).

## Aktuální stav
✅ **Rozchozeno** — APK se sestaví, Google login + email/heslo, výběr firmy, skenování

## Architektura

### Princip (inspirováno Kalkulio)
- Capacitor WebView načítá `https://office.tuptudu.cz/mobile/prihlaseni`
- Stejný kód, stejné Blade views — žádný oddělený frontend, appka se neaktualizuje při změně webu
- Nativní skener přes Capacitor JS plugin (bridge se injektuje i do remote URL)
- Web detekuje `Capacitor.isNativePlatform()` → spustí ML Kit; jinak zobrazí varování

### Tech stack
- **Capacitor 8** — hybrid framework
- **`@capacitor-mlkit/document-scanner`** — Google ML Kit Document Scanner (Apache-2.0, zdarma, jen Android)
- **`@capacitor/browser`** + **`@capacitor/app`** — Google OAuth přes Custom Tab + deep link
- **`@capacitor/filesystem`** — čtení naskenovaného PDF (fetch na `file://` WebView nedovolí, běží na https originu)
- Sken se vrací jako PDF → upload na existující `/upload` endpoint

### Distribuce (Fáze 1)
- APK ke stažení (sideload)
- Bez Play Store

## Přihlášení

### E-mail + heslo
Standardní formulář na `/mobile/prihlaseni` → `MobileController::login()` → session cookie zůstane ve WebView.

### Google
Google **blokuje OAuth v embedded WebView** (`disallowed_useragent`), takže:

```
1. Klik na "Přihlásit přes Google" v appce
2. JS otevře /auth/google/redirect?capacitor=1 v Custom Tabu (@capacitor/browser)
3. Backend si zapamatuje cookie oauth_capacitor a přesměruje na Google
4. Google → /auth/google/callback (Custom Tab má vlastní cookie jar!)
5. Backend najde uživatele, přihlásí ho a vygeneruje one-time token (cache, 60 s)
6. Vrátí HTML stránku, která skočí na cz.tuptudu.office://auth/done?token=XXX
7. Appka deep link zachytí (@capacitor/app appUrlOpen), zavře Custom Tab
8. WebView otevře /mobile/auth-bridge/{token} → Auth::loginUsingId → session ve WebView
```

**Nové účty se přes Google NEZAKLÁDAJÍ** — registrace zůstává jen přes IČO / pozvánku.
Neznámý Google e-mail dostane hlášku, ať se nejdřív zaregistruje. Existující účet
se při prvním Google přihlášení spáruje podle e-mailu (uloží se `google_id`).

Tlačítko je i na webovém loginu `/prihlaseni` (bez `?capacitor=1`, standardní redirect).

### Nutná konfigurace v Google Cloud Console
Do OAuth klienta (stejný `GOOGLE_CLIENT_ID` jako Drive sync) přidat redirect URI:
```
https://office.tuptudu.cz/auth/google/callback
```
Drive sync používá `/google/callback` — obě URI musí být whitelistnuté.

## Klíčové soubory

| Soubor | Funkce |
|--------|--------|
| `capacitor.config.json` | Capacitor konfigurace (URL, app ID, allowNavigation na Google) |
| `package.json` | Capacitor + pluginy, skripty `android:sync` / `android:open` |
| `scripts/android-deeplink.mjs` | Patch AndroidManifest.xml o deep link intent-filter (idempotentní) |
| `app/Http/Controllers/MobileController.php` | Mobilní routy (login, skenovat, přepnutí firmy) |
| `app/Http/Controllers/Auth/GoogleLoginController.php` | Google OAuth login + mobile auth bridge |
| `resources/views/mobile/prihlaseni.blade.php` | Mobilní login + Custom Tab bridge JS |
| `resources/views/mobile/skenovat.blade.php` | Skener UI, výběr firmy, upload |
| `routes/web.php` | `/mobile/*` a `/auth/google/*` routy |
| `android/` | Generovaný Android projekt (gitignore) |

**DB:** `sys_users.google_id` (nullable, unique) — migrace `2026_08_11_010000_add_google_id_to_sys_users.php`

## Build workflow (Windows)

### Prerekvizity (jednorázově)
1. Node.js 18+ (https://nodejs.org)
2. Android Studio (https://developer.android.com/studio) — přinese si i JDK (`jbr`)
3. V Android Studiu: SDK Manager → Android SDK 34+

### První build
```bash
npm install
npx cap add android          # vygeneruje android/ (je v .gitignore)
npm run android:sync         # cap sync + patch manifestu o deep link
npm run android:open         # otevře Android Studio
```

V Android Studiu: Build → Build Bundle(s) / APK(s) → Build APK(s)

Nebo z příkazové řádky (Git Bash):
```bash
export JAVA_HOME="/c/Program Files/Android/Android Studio/jbr"
export ANDROID_HOME="$LOCALAPPDATA/Android/Sdk"
cd android && ./gradlew assembleDebug
```
APK: `android/app/build/outputs/apk/debug/app-debug.apk`

### Po změně Capacitor configu nebo pluginů
```bash
npm run android:sync
```
`android:sync` vždy znovu doplní deep-link intent-filter — `cap sync` ho může přepsat.

### Instalace na Android telefon (test bez Play Store)
1. V telefonu: Nastavení → Zabezpečení → povolit "Neznámé zdroje"
2. Poslat APK přes USB / e-mail / Google Drive
3. Otevřít APK → instalovat

## Datový tok skenování

```
1. Uživatel otevře appku → WebView načte /mobile/prihlaseni
2. Login (e-mail+heslo nebo Google) → session cookie ve WebView storage
3. Redirect na /mobile/skenovat
4. Výběr firmy (select) → POST /mobile/prepnout-firmu/{ico} → session('aktivni_firma_ico')
5. Klik "Skenovat" → ML Kit scanner (auto edge, perspective, multi-page, max 10 stran)
6. ML Kit vrátí PDF (content:// URI)
7. Filesystem.readFile() → base64 → Blob → FormData → POST /upload
8. Backend (DokladProcessor) → Claude Vision → S3 + DB → Drive sync
```

Doklad se ukládá k firmě ze session (`InvoiceController::store()` → `aktivniFirma()`) —
tedy k té, která je vybraná v selectu. Stejné chování jako na webu.

### Modul skeneru
Google Document Scanner je součást Play Services a na některých zařízeních se stahuje
až na vyžádání. Před skenem se volá `isGoogleDocumentScannerModuleAvailable()`; pokud
chybí, spustí se `installGoogleDocumentScannerModule()` a uživatel to zkusí za chvíli znovu.

## Plánovaný rozvoj
- **Fáze 1:** Android APK + Google login + scanner + upload (✅ hotovo)
- **Fáze 2:** Distribuce — link na APK z webu
- **Fáze 3:** Google Play Store (podepsané release APK / AAB)
- **Fáze 4:** iOS verze (vyžaduje Mac + Apple Developer účet $99/rok; ML Kit scanner je jen Android, na iOS by se použil VisionKit plugin)

---
*Vytvořeno: 2026-04-16 · Aktualizováno: 2026-08-11*
