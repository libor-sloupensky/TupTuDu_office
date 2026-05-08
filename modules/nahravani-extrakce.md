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
- **Systémová schránka**: `faktury@tuptudu.cz`, IČO z To/Cc (`{8číslic}@tuptudu.cz`)
- **Custom IMAP**: vlastní schránka per firma (bez auto-reply)
- **Filtrování**: pouze přílohy (PDF, JPG, PNG), inline obrázky se přeskakují
- **Auto-reply**: deterministické šablony (5 variant dle stavu zpracování)

## Důležitá rozhodnutí
- Soubory na AWS S3, ne lokální storage
- OCR: AWS Textract (bounding boxy pro zvýrazňování v UI)
- AI extrakce: Claude Haiku (claude-haiku-4-5-20251001), retry 2× při prázdné odpovědi
- Upload na Google Drive asynchronně přes cron, ne při uploadu
- PDF split: každá stránka = samostatný doklad
- Deduplikace: hash souboru + obsahová (číslo dokladu + dodavatel IČO)
- JSON repair pro truncated AI responses

## Databázové tabulky

**`fak_doklady`** — hlavní tabulka (62 sloupců):
firma_ico, dodavatel_ico, nazev_souboru, cesta_souboru, cislo_dokladu, datum_vystaveni, datum_splatnosti, castka_celkem, castka_zaklad, castka_dph, mena, kategorie, typ_dokladu, kvalita, stav, zdroj, raw_text, raw_ai_odpoved, hash_souboru, duplicita_id, google_drive_file_id, google_drive_ucetni_file_id, google_drive_nahrano_at...

**`fak_polozky`** — řádkové položky:
doklad_id, poradi, text, mnozstvi, jednotka, cena_za_jednotku, zaklad_dane, sazba_dph, castka_dph, castka_celkem

---
*Aktualizováno: 2026-04-09*
