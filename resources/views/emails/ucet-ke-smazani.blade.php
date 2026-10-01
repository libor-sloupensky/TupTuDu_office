<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"></head>
<body style="font-family: sans-serif; background: #f5f5f5; padding: 2rem;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 style="color: #2c3e50; margin-top: 0;">Váš účet je uzavřený</h2>

        <p>Dobrý den, {{ $user->jmeno }},</p>

        <p>
            v systému TupTuDu byla podána žádost o smazání vašeho účtu. Účet je od této chvíle
            uzavřený a <strong>{{ $smazaniK }}</strong> se i s vašimi doklady nenávratně smaže.
        </p>

        <p>Pokud jste o to nežádali, obnovte účet tímto odkazem:</p>

        <p style="text-align: center; margin: 2rem 0;">
            <a href="{{ $obnoveniUrl }}" style="background: #27ae60; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;">Obnovit účet</a>
        </p>

        <p style="color: #666; font-size: 0.9rem;">
            Obnovit ho jde i po přihlášení. Po uvedeném datu už to možné nebude a data nepůjdou
            vrátit.
        </p>

        <p style="color: #666; font-size: 0.9rem;">
            Chcete-li smazat účet okamžitě, bez čekání, napište nám na
            <a href="mailto:info@tuptudu.cz">info@tuptudu.cz</a>.
        </p>

        <hr style="border: none; border-top: 1px solid #eee; margin: 1.5rem 0;">
        <p style="color: #999; font-size: 0.8rem;">TupTuDu - Zpracování faktur</p>
    </div>
</body>
</html>
