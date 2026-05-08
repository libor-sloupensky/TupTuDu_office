# Modul: Mobilní aplikace (Capacitor)

## Co tento modul dělá
Android aplikace pro skenování účetních dokladů. Tenká shell apka — Capacitor WebView načítá `office.tuptudu.cz/mobile/prihlaseni`, používá nativní Google ML Kit Document Scanner (stejný co v Drive).

## Aktuální stav
🔧 **V implementaci** — Android shell + ML Kit scanner

## Architektura

### Princip (inspirováno Kalkulio)
- Capacitor WebView načítá `https://office.tuptudu.cz/mobile/prihlaseni`
- Stejný kód, stejné Blade views — žádný oddělený frontend
- Nativní skener přes Capacitor JS plugin
- Web detekuje `Capacitor.isNativePlatform()` → spustí ML Kit; jinak zobrazí varování

### Tech stack
- **Capacitor 8** — hybrid framework
- **`@capawesome/capacitor-mlkit-document-scanner`** — Google ML Kit (zdarma, jen Android)
- Sken se vrací jako PDF → upload na existující `/upload` endpoint

### Distribuce (Fáze 1)
- APK ke stažení (sideload)
- Bez Play Store

## Klíčové soubory

| Soubor | Funkce |
|--------|--------|
| `capacitor.config.json` | Capacitor konfigurace (URL, app ID) |
| `package.json` | Capacitor + plugin deps |
| `app/Http/Controllers/MobileController.php` | Mobilní routy (login, skenovat) |
| `resources/views/mobile/prihlaseni.blade.php` | Mobilní login |
| `resources/views/mobile/skenovat.blade.php` | Skener UI + bridge JS |
| `routes/web.php` | `/mobile/*` routy |
| `android/` | Generovaný Android projekt (gitignore) |

## Build workflow (Windows)

### Prerekvizity (jednorázově)
1. Node.js 18+ (https://nodejs.org)
2. Java JDK 17+ (Android Studio nainstaluje)
3. Android Studio (https://developer.android.com/studio)
4. V Android Studiu: SDK Manager → nainstaluj Android SDK 34+

### První build
```bash
# 1. Instalace npm balíčků
npm install

# 2. Vygeneruj Android projekt
npx cap add android

# 3. Sync (kopíruje webDir + plugin nativní kód)
npx cap sync android

# 4. Otevři v Android Studiu
npx cap open android
```

V Android Studiu: Build → Build Bundle(s) / APK(s) → Build APK(s)
APK je v `android/app/build/outputs/apk/debug/app-debug.apk`

### Po změně Capacitor configu nebo pluginů
```bash
npm run build  # pokud používáš vite assets
npx cap sync android
```

### Instalace na Android telefon (test bez Play Store)
1. V telefonu: Nastavení → Zabezpečení → Povol "Neznámé zdroje"
2. Pošli si APK přes USB / email / Google Drive
3. Otevři APK → instalovat

## Datový tok

```
1. Uživatel otevře apku → Capacitor WebView načte /mobile/prihlaseni
2. Login → Laravel session cookie zůstává v WebView storage
3. Redirect na /mobile/skenovat
4. Klik "Skenovat" → ML Kit scanner (auto edge, perspective, multi-page)
5. ML Kit vrátí PDF (file:// URI)
6. JS načte blob, FormData, POST na /upload
7. Backend (DokladProcessor) → Textract OCR + Claude AI → S3 + DB → Drive sync
```

## Plánovaný rozvoj
- **Fáze 1:** Android APK + scanner + upload (✅ implementováno, čeká na build/test)
- **Fáze 2:** Distribuce — link na APK z webu
- **Fáze 3:** Google Play Store
- **Fáze 4:** iOS verze (vyžaduje Mac + Apple Developer účet $99/rok, plugin podporuje VisionKit)

---
*Vytvořeno: 2026-04-16*
