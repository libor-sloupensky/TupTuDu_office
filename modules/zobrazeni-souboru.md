# Modul: Zobrazení souborů

## Co tento modul dělá
Prohlížeč nahraných dokladů — seznam, detail, preview PDF/obrázků, bounding boxy, vyhledávání (text + AI).

## Aktuální stav
Modul je plně funkční — dynamická tabulka, inline editace, AI search, preview se zoom/pan.

## Klíčové soubory

| Soubor | Funkce |
|--------|--------|
| `resources/views/invoices/index.blade.php` | Hlavní seznam — dynamická tabulka, drag sloupců, rozbalitelné detaily, notifikace |
| `resources/views/invoices/show.blade.php` | Detail dokladu — všechna extrahovaná data, preview, OCR text, raw AI JSON |
| `resources/views/invoices/result.blade.php` | Výsledek importu dokladu |
| `app/Http/Controllers/InvoiceController.php` | index, show, preview, previewOriginal, update, destroy, aiSearch, downloadMonth |

## Funkce seznamu (index)
- Dynamická tabulka s draggable sloupci (localStorage pro preference)
- Sortování: created_at, datum_vystaveni, datum_prijeti, duzp, datum_splatnosti
- Filtrování: LIKE přes cislo_dokladu, dodavatel_nazev, nazev_souboru, dodavatel_ico, nahral, raw_text
- Rozbalovací řádky s detailem (položky, DPH rekapitulace, bounding boxy)
- Indikátory: stav, kvalita, adresát, typ dokladu
- Mazání: AJAX DELETE s `escAttr()` pro bezpečné escapování názvů

## AI vyhledávání
- Přirozený jazyk → strukturované filtry (Claude Haiku)
- 25+ filtrů: kategorie, typ, stav, kvalita, měna, zdroj, částkové/datumové rozsahy
- Fallback na LIKE vyhledávání při selhání AI

## Preview dokladu
- PDF: iframe s PDF.js (CDN v3.11.174)
- Obrázky: inline zobrazení s zoom (+, -, fit, 1:1) a drag & pan
- Bounding boxy: absolutní pozicování z AI souřadnic (`rgba(52, 152, 219, 0.18)`)

## Detail dokladu (show)
- Kompletní extrahovaná data v tabulce
- Sekce: identifikace, dodavatel, částky, data, platební údaje
- OCR text, raw AI JSON (toggle)
- Varování: duplicity (crosslink), nízká kvalita
- Tlačítko smazat s potvrzením

## Klíčové JS funkce (index.blade.php)
- `renderTable()` — vykreslení tabulky z `dokladyData` array
- `toggleDetail()` — rozbalení detailního řádku
- `cellValue()` — formátování buňky s HTML escapingem
- `startDetailEdit()` — inline editace pole
- `initDragDrop()` — drag & drop sloupců
- `doAiSearch()` — AI vyhledávání (fetch)
- `deleteDoklad()` — AJAX mazání
- `escHtml()`, `escAttr()` — escapování

## Frontend vzory
- Vanilla JS, žádný framework (Vue/React)
- Přímá DOM manipulace, inline onclick handlery
- CSS inline v Blade (`@section('styles')`)
- Notifikační systém: panel s historií, auto-hide 8s
- Data flow: Controller → `dokladyData` JSON → JS `renderTable()` → DOM

## API Endpoints
- `GET /doklady` — seznam (HTML + JSON pro AJAX)
- `GET /doklady/{id}` — detail HTML
- `GET /doklady/{id}/preview` — inline PDF/obrázek
- `GET /doklady/{id}/preview-original` — originální soubor
- `GET /doklady/{id}/download` — stažení
- `POST /doklady/{id}` — editace pole (JSON)
- `DELETE /doklady/{id}` — smazání
- `POST /doklady/aiSearch` — AI vyhledávání (JSON)
- `GET /doklady/downloadMonth` — ZIP za měsíc

---
*Aktualizováno: 2026-04-09*
