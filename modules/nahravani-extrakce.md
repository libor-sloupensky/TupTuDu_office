# Modul: Nahrávání a extrakce souborů

## Co tento modul dělá
Upload účetních dokladů (faktury, paragony), OCR přes AWS Textract, AI extrakce dat přes Claude Haiku, emailový příjem, Google Drive sync.

## Aktuální stav
Modul je plně funkční — upload, OCR, AI extrakce, email polling, Drive sync.

## Klíčové soubory

| Soubor | Řádky | Funkce |
|--------|-------|--------|
| `app/Services/DokladProcessor.php` | ~1367 | Hlavní procesor — PDF split, Textract OCR, Claude Vision, S3 upload, deduplikace |
| `app/Http/Controllers/InvoiceController.php` | ~787 | Web API — upload (store), editace, vyhledávání, preview, download |
| `app/Console/Commands/ProcessEmailDoklady.php` | ~668 | Email daemon — systémová + custom IMAP schránka, auto-reply šablony |
| `app/Console/Commands/SyncGoogleDrive.php` | ~89 | Cron — batch sync hotových dokladů do Google Drive (max 50/běh) |
| `app/Services/GoogleDriveService.php` | ~319 | Google Drive API — upload, folder cache, metadata embedding (PDF/JPEG) |
| `app/Services/DrivePathBuilder.php` | — | Šablony cest pro Drive (tokeny: `{nahrano:YYYY}`, `{dodavatel:15}`, atd.) |
| `app/Models/Doklad.php` | ~62 | Eloquent model — relace na Firma, Dodavatel, Polozka |
| `app/Models/Polozka.php` | ~31 | Model řádkové položky faktury (`fak_polozky`) |

## Datový tok

```
Upload / Email → DokladProcessor
  → PDF split (FPDI, multi-page → stránky)
  → AWS Textract OCR (text + bounding boxy)
  → Claude Vision AI (Haiku 4.5) — strukturovaná extrakce 25+ polí
  → Deduplikace (SHA256 hash + číslo dokladu + IČO dodavatele)
  → S3 upload (doklady/{ICO}/{YYYY-MM}/{datum}_{ID}.{ext})
  → DB zápis (fak_doklady + fak_polozky)
  → Google Drive sync (cron, batch 50)
```

## Stavy dokladu
`nahrano` → `zpracovava_se` → `dokonceno` / `nekvalitni` / `chyba`

## Email zpracování
- **Systémová schránka**: `doklady@tuptudu.cz` (mailový koš `{IČO}@tuptudu.cz`), IČO z To/Cc
- **Custom IMAP**: vlastní schránka per firma (bez auto-reply)
- **Filtrování**: přílohy PDF, JPG, PNG; inline obrázky se přeskakují
- **Auto-reply**: deterministické šablony (5 variant dle stavu zpracování), HTML + textová část

### Rozpoznání příloh
Typ se určuje z přípony **i z hlavičky `Content-Type`** — příloha bez názvu nebo
bez přípony dřív propadla úplně (nezpracovala se ani se neobjevila v odpovědi
mezi nepodporovanými soubory) a odesílatel dostal „zpráva neobsahovala žádné doklady".

**Přeposlané zprávy** (`message/rfc822`) se rozbalují o jednu úroveň — klienti tak
doklady přeposílají běžně a příloha uvnitř by jinak zůstala neviditelná.

### Diagnostika
Cron čte **jen INBOX a jen nepřečtené zprávy**. Když se na schránku někdo podívá
přes webmail, zpráva se označí jako přečtená a doklad se nikdy nenačte.

Důvod přeskočení se zapisuje do logu (`LOG_LEVEL=warning`) i s výpisem hlaviček
To a Cc — bez toho se zpětně nedalo zjistit, proč doklad nevznikl. Nejčastější
případ: adresa `{IČO}@tuptudu.cz` byla jen ve skryté kopii.

## Známé nedodělky
- **Zamčená PDF**: do dokladů chráněných proti zápisu se nevkládají metadata.
  `embedPdfMetadata()` staví nové PDF přes FPDI, jenže FPDI zašifrovaný zdroj
  vůbec neotevře (`setSourceFile()` skončí výjimkou). Odemčení by chtělo placené
  rozšíření FPDI nebo `qpdf` přes shell — hosting ale `exec` i `proc_open` zakazuje.
  Soubor se nahraje v pořádku, jen bez metadat; loguje se jako warning.
  **K vyřešení někdy později.**

## Důležitá rozhodnutí
- Soubory na AWS S3, ne lokální storage
- OCR: AWS Textract (bounding boxy pro zvýrazňování v UI)
- AI extrakce: Claude Haiku (claude-haiku-4-5-20251001), retry 2× při prázdné odpovědi
- Upload na Google Drive asynchronně přes cron, ne při uploadu
- Složka firmy na Disku se jmenuje `{IČO}_{název firmy}` a zakládá ji
  `GoogleDriveService`, ne šablona cesty — šablona popisuje cestu *uvnitř* ní
- Razítko `google_drive_nahrano_at` se staví **jen když se doklad opravdu nahrál**.
  Dřív se stavělo i bez aktivního Drivu, takže se fronta označila za hotovou,
  aniž by se cokoli nahrálo, a už se k ní nikdo nevrátil
- PDF split: každá stránka = samostatný doklad
- Deduplikace: hash souboru + obsahová (číslo dokladu + dodavatel IČO)
- JSON repair pro truncated AI responses

## Databázové tabulky

**`fak_doklady`** — hlavní tabulka (62 sloupců):
firma_ico, dodavatel_ico, nazev_souboru, cesta_souboru, cislo_dokladu, datum_vystaveni, datum_splatnosti, castka_celkem, castka_zaklad, castka_dph, mena, kategorie, typ_dokladu, kvalita, stav, zdroj, raw_text, raw_ai_odpoved, hash_souboru, duplicita_id, google_drive_file_id, google_drive_ucetni_file_id, google_drive_nahrano_at...

**`fak_polozky`** — řádkové položky:
doklad_id, poradi, text, mnozstvi, jednotka, cena_za_jednotku, zaklad_dane, sazba_dph, castka_dph, castka_celkem

---
*Aktualizováno: 2026-08-25*
