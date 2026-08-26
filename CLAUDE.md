# TupTuDu Office — Pravidla pro Claude Code

## Jazyk
- Commit messages, komentáře v kódu a komunikace: **česky**

## Stack
- Framework: Laravel 12, PHP 8.2+
- Databáze: MariaDB (Český hosting, dostupná jen z 127.0.0.1)
- Storage: AWS S3
- AI: Anthropic Claude API (Haiku)
- OCR: AWS Textract
- Cloud sync: Google Drive API
- Frontend: Vanilla JS + inline CSS v Blade (žádný framework, žádný build step)
- Deploy: GitHub Actions → SFTP (lftp) na Český hosting (`irene.thinline.cz`)

## Workflow
- Před úpravou kódu vždy přečíst aktuální stav souboru
- Preferovat úpravu existujících souborů před vytvářením nových
- Na začátku práce na modulu přečíst příslušný `modules/*.md`
- Na konci sezení nebo na výzvu „aktualizuj kontext": aktualizovat příslušný `modules/*.md`

## Ikony
- **Vždy Lucide** — `<x-ikona name="check" />`, volitelně `:size="16"`
- **Nikdy emoji ani ručně psané SVG** v UI. Emoji vypadají jinak na každém
  systému a ručně kreslené cesty se rozcházejí s designem sady
- Barvu ikona dědí z textu (`currentColor`), velikost výchozí 20 px
- Dostupné ikony: `App\Support\Lucide::seznam()`, přehled na lucide.dev/icons
- **Novou ikonu nekresli ručně** — stáhni z oficiálního balíčku a vlož do
  `app/Support/Lucide.php`:
  `curl -sSL https://unpkg.com/lucide-static@1.34.0/icons/NAZEV.svg`
- Výjimka: cizí značky (logo Google) zůstávají jako originální SVG

## Konvence
- Tabulky s prefixem `sys_` (systémové)
- PK firmy = IČO (string, 8 číslic), ne auto-increment
- Aktivní firma v session: `session('aktivni_firma_ico')`
- Refresh token šifrovaný v DB (`encrypt()` / `decrypt()`)
- Frontend: vanilla JS, žádný Alpine/React/Vue

## Styl práce
- Stručné odpovědi, rovnou k věci — žádné zbytečné vysvětlování
- Opravit → stručné shrnutí → **commit+push ihned**, bez ptaní
- Produkce na office.tuptudu.cz — push na main = deploy, každý commit musí být funkční
- Web je zatím v testovacím režimu, chodí na něj jen vlastník → deploy je levný, neváhat s ním

## Moduly
| Modul | Soubor | Popis |
|-------|--------|-------|
| Nahrávání a extrakce | `modules/nahravani-extrakce.md` | Upload souborů, OCR, AI zpracování |
| Zobrazení souborů | `modules/zobrazeni-souboru.md` | Prohlížeč, preview, zvýrazňování |
| Ostatní | `modules/ostatni.md` | Auth, firmy, nastavení, Google Drive, deploy |
| Mobilní aplikace | `modules/mobilni-aplikace.md` | Capacitor Android — skenování dokladů přes Google ML Kit |
