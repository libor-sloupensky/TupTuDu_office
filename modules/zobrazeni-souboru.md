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

## Úrovně zpracování

| Úroveň | Co proběhne | Náklad |
|--------|-------------|--------|
| `ulozeni` | Soubor se uloží, nic se nečte | jen úložiště |
| `vycteni` | Textract — vyčte text ze stránky, pole zůstanou prázdná | ~3 haléře/stránku |
| `rozpoznani` | Textract + Claude — rozpozná, co které číslo znamená | ~0,22 Kč/stránku |

**Textract účtuje za stránku**, ne za plochu ani množství textu: malý paragon
stojí tolik co hustá A4. Vícestránkové PDF se násobí, protože se každá stránka
posílá zvlášť.

Vyčtení je proto ta úroveň, kterou jde nabídnout zdarma — doklad je plnotextově
dohledatelný podle libovolného slova, které na něm stojí, jen systém neví, co
které číslo znamená. Kredity za ně zatím neúčtuje (`Kredity::CENIK`); až se
bude dělat ceník, je to tam jediné místo, kde se to rozhodne.

Vyčtený doklad zůstává ve stavu `ulozeno`, takže se dá kdykoli
dotáhnout tlačítkem *Vytěžit*.

## Hledání v dokladech

`Doklad::scopeHledej()`. Krátká strukturovaná pole (číslo dokladu, dodavatel…)
jdou přes `LIKE '%…%'`, aby se hledalo i uprostřed slova. Přepis (`raw_text`)
jde přes **fulltextový index** podmínkou `MATCH … AGAINST` v poddotazu.

Proč poddotaz: kdyby MATCH stálo přímo v `OR` vedle LIKE, optimalizátor by
index zahodil. Ověřeno přes EXPLAIN — `type=fulltext` místo `type=ALL`.

Dvě omezení, která z toho plynou: MATCH hledá od začátku slova, ne uprostřed
(„servis" nenajde „pneuservis"), a slova kratší než tři znaky se do indexu
nedostanou — u nich se proto i na přepis sáhne po LIKE.

**Testy hledání nejedou přes RefreshDatabase.** InnoDB doplňuje fulltextový
index až při commitu, takže by MATCH nezacommitované řádky neviděl.

## Zvýraznění nalezeného výrazu

Při vyčtení se vedle souboru v S3 ukládá `<cesta>.slova.json` — slova i s jejich
pozicí na stránce. Do databáze by se to rozumně nevešlo (hustá A4 má kolem pěti
set slov). Načítá se až při rozbalení dokladu, přes
`GET /doklady/{doklad}/slova?q=výraz`.

V náhledu se nálezy podbarví žlutě (`.bbox-nalez`) a pod náhledem se vypíše,
kolikrát je výraz na dokladu. Vlastní třída je schválně: `clearBboxHighlight()`
maže `.bbox-highlight` při odhoveru z pole a nálezy mají zůstat. Kreslí se jen
nálezy z první stránky, protože náhled ukazuje ji.

### Doplnění souřadnic zpětně

Starší doklady souřadnice slov nemají — zvýraznit v nich nejde a uživatel to
pozná z hlášky, ne z tichého nic. Doplnit je jde workflow **Doplnit souřadnice
slov** (`workflow_dispatch`), který volá `doklady:doplnit-slova`.

Každý doklad projde znovu Textractem, takže to **stojí peníze**. Bez
zaškrtnutého „doopravdy" se jen spočítá odhad ceny a nic se nezpracuje.
Doklady, které souřadnice už mají, se přeskakují, takže opakované spuštění nic
neplatí dvakrát.

### Hledání uprostřed slova

Fulltextový index hledá od začátku slova, takže „servis" sám o sobě nenajde
„pneuservis". Seznam dokladů proto při prázdném výsledku zkusí ještě druhý
průchod přes `LIKE '%…%'` (`hledej($vyraz, iUprostred: true)`). Ten čte celou
tabulku, ale doběhne jen u dotazů, které jinak skončily naprázdno.
