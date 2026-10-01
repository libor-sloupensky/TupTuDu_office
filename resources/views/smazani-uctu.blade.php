<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Smazání účtu — TupTuDu</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
               max-width: 680px; margin: 0 auto; padding: 2rem 1.25rem; color: #2c3e50; line-height: 1.6; }
        h1 { font-size: 1.5rem; }
        h2 { font-size: 1.05rem; margin-top: 2rem; }
        ul { padding-left: 1.2rem; }
        li { margin-bottom: 0.35rem; }
        .ramecek { background: #f6f8fa; border: 1px solid #e0e6ec; border-radius: 8px; padding: 1rem 1.25rem; }
        a { color: #2980b9; }
        footer { margin-top: 2.5rem; font-size: 0.85rem; color: #7f8c8d; }
    </style>
</head>
<body>
    <h1>Smazání účtu v aplikaci TupTuDu Doklady</h1>

    <p>Účet si můžete smazat sami přímo v aplikaci — není k tomu potřeba nikoho žádat.</p>

    <div class="ramecek">
        <strong>Jak na to</strong>
        <ol>
            <li>Přihlaste se v aplikaci nebo na <a href="{{ route('login') }}">office.tuptudu.cz</a>.</li>
            <li>Otevřete <strong>Můj účet</strong>.</li>
            <li>Dole zvolte <strong>Smazat účet</strong> a potvrďte opsáním svého e-mailu.</li>
        </ol>
    </div>

    <h2>Co se smaže</h2>
    <ul>
        <li>Váš účet — jméno, e-mail, telefon, heslo i případné napojení na účet Google.</li>
        <li>Vaše osobní doklady včetně nahraných souborů.</li>
        <li>Firmy, ve kterých jste jediným uživatelem, včetně jejich dokladů a souborů — nikdo jiný by se k nim už nedostal.</li>
        <li>Pozvánky vystavené na váš e-mail.</li>
    </ul>

    <h2>Co zůstane</h2>
    <ul>
        <li>Firmy, ve kterých jsou i další uživatelé. Z těch jen odejdete; jejich doklady patří firmě, ne vám.</li>
        <li>Souhrnné záznamy o spotřebě zpracování. Neobsahují osobní údaje a slouží k vyúčtování.</li>
    </ul>

    <h2>Kdy se to stane</h2>
    <p>
        Po potvrzení se účet hned uzavře — přihlásit se do něj už nejde — a běží
        <strong>{{ \App\Services\SmazaniUctu::DNI_LHUTY }}denní lhůta na rozmyšlenou</strong>.
        O uzavření vám přijde e-mail s odkazem, kterým účet i doklady vrátíte zpátky.
        Lhůta je tu proto, aby o doklady nikdo nepřišel omylem nebo cizím zásahem.
    </p>
    <p>
        Po {{ \App\Services\SmazaniUctu::DNI_LHUTY }} dnech se všechno výše uvedené smaže
        <strong>nenávratně</strong>. Zálohy, ve kterých se data mohou ještě krátce vyskytovat,
        se přepisují do 30 dnů.
    </p>
    <p>
        Nechcete čekat? Napište na <a href="mailto:info@tuptudu.cz">info@tuptudu.cz</a> a účet
        smažeme ihned.
    </p>

    <h2>Nemůžete se přihlásit?</h2>
    <p>
        Napište na <a href="mailto:info@tuptudu.cz">info@tuptudu.cz</a> z e-mailu, kterým jste se
        registroval. Žádost vyřídíme do 30 dnů.
    </p>

    <footer>
        <a href="{{ route('privacy') }}">Zásady ochrany osobních údajů</a>
    </footer>
</body>
</html>
