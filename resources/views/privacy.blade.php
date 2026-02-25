<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zásady ochrany osobních údajů – TupTuDu Office</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #1a1a2e; color: #ddd; min-height: 100vh; line-height: 1.7; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 3rem 1.5rem; }
        h1 { color: #e94560; font-size: 1.8rem; margin-bottom: 0.5rem; }
        .subtitle { color: #888; margin-bottom: 2.5rem; font-size: 0.9rem; }
        h2 { color: #eee; font-size: 1.15rem; margin-top: 2rem; margin-bottom: 0.5rem; }
        p, li { color: #bbb; font-size: 0.95rem; margin-bottom: 0.5rem; }
        ul { padding-left: 1.3rem; margin-bottom: 0.5rem; }
        a { color: #e94560; }
        .back { display: inline-block; margin-bottom: 2rem; color: #888; text-decoration: none; font-size: 0.9rem; }
        .back:hover { color: #eee; }
    </style>
</head>
<body>
<div class="wrap">
    <a href="https://office.tuptudu.cz" class="back">&larr; Zpět na office.tuptudu.cz</a>

    <h1>Zásady ochrany osobních údajů</h1>
    <p class="subtitle">Platné od 25. 2. 2026 &middot; TupTuDu Office (office.tuptudu.cz)</p>

    <h2>1. Provozovatel</h2>
    <p>Službu TupTuDu Office provozuje Libor Sloupenský, IČO 87700484, se sídlem v České republice (dále jen „provozovatel"). Kontakt: <a href="mailto:libor@sloupensky.net">libor@sloupensky.net</a>.</p>

    <h2>2. Jaké údaje zpracováváme</h2>
    <ul>
        <li><strong>Registrační údaje</strong> – jméno, e-mail, heslo (hashované).</li>
        <li><strong>Firemní údaje</strong> – IČO, název firmy, adresa (načtené z ARES).</li>
        <li><strong>Doklady</strong> – nahrané soubory (PDF, obrázky) a z nich extrahovaná data (dodavatel, částky, data, variabilní symboly aj.).</li>
        <li><strong>Přístupové tokeny</strong> – šifrované OAuth tokeny pro napojené služby (Google Drive). Tokeny jsou uloženy v šifrované podobě a slouží výhradně k synchronizaci souborů, které aplikace sama vytvořila.</li>
    </ul>

    <h2>3. Účel zpracování</h2>
    <p>Údaje zpracováváme výhradně za účelem poskytování služby – tedy zpracování, kategorizace a archivace účetních dokladů a jejich případné sdílení s účetní firmou uživatele.</p>

    <h2>4. Právní základ</h2>
    <p>Zpracování probíhá na základě plnění smlouvy (poskytování služby) dle čl. 6 odst. 1 písm. b) GDPR.</p>

    <h2>5. Sdílení s třetími stranami</h2>
    <ul>
        <li><strong>Amazon Web Services (AWS)</strong> – uložení souborů (S3) a OCR zpracování (Textract), region EU (eu-west-1).</li>
        <li><strong>Anthropic</strong> – AI analýza obsahu dokladů (Claude API). Odesílají se pouze obrazová data dokladů, nikoli osobní údaje uživatele.</li>
        <li><strong>Google Drive</strong> – volitelná synchronizace kopií dokladů na Google Drive uživatele. Aplikace používá omezený přístup (<code>drive.file</code>) a vidí pouze soubory, které sama vytvořila.</li>
        <li><strong>Webglobe</strong> – hosting aplikace a databáze (ČR).</li>
    </ul>
    <p>Údaje neprodáváme ani neposkytujeme žádným dalším třetím stranám.</p>

    <h2>6. Uložení a zabezpečení</h2>
    <p>Soubory dokladů jsou uloženy na Amazon S3 (EU). Databáze běží na serveru v ČR. Veškerá komunikace probíhá přes šifrované spojení (HTTPS/TLS). Citlivé údaje (hesla, tokeny) jsou uloženy v hashované nebo šifrované podobě.</p>

    <h2>7. Doba uchování</h2>
    <p>Údaje uchováváme po dobu trvání účtu. Po smazání účtu nebo firmy jsou veškeré související doklady a data smazány.</p>

    <h2>8. Vaše práva</h2>
    <p>Máte právo na přístup ke svým údajům, jejich opravu, výmaz, omezení zpracování a přenositelnost. Pro uplatnění těchto práv nás kontaktujte na <a href="mailto:libor@sloupensky.net">libor@sloupensky.net</a>.</p>

    <h2>9. Google Drive – omezený přístup</h2>
    <p>Aplikace TupTuDu Office využívá Google Drive API s rozsahem oprávnění <code>drive.file</code>. To znamená, že aplikace může přistupovat <strong>pouze k souborům, které sama vytvořila</strong>. Nemá přístup k žádným jiným souborům na vašem Google Disku. Propojení můžete kdykoliv zrušit v nastavení firmy.</p>

    <h2>10. Cookies</h2>
    <p>Používáme pouze technicky nezbytné cookies pro přihlášení a CSRF ochranu. Nepoužíváme analytické ani reklamní cookies.</p>

    <h2>11. Změny</h2>
    <p>Tyto zásady můžeme aktualizovat. O podstatných změnách budeme informovat e-mailem nebo oznámením v aplikaci.</p>
</div>
</body>
</html>
