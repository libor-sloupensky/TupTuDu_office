<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"></head>
<body style="font-family: sans-serif; background: #f5f5f5; padding: 2rem;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 style="color: #2c3e50; margin-top: 0;">Obnovení hesla</h2>
        <p>Dobrý den, {{ $user->cele_jmeno }},</p>
        <p>obdrželi jsme žádost o obnovení hesla k vašemu účtu v systému TupTuDu. Klikněte na tlačítko níže pro nastavení nového hesla:</p>
        <p style="text-align: center; margin: 2rem 0;">
            <a href="{{ $resetUrl }}" style="background: #e67e22; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;">Nastavit nové heslo</a>
        </p>
        <p style="color: #666; font-size: 0.9rem;">Odkaz je platný 60 minut. Pokud jste o reset nežádali, tento email ignorujte.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 1.5rem 0;">
        <p style="color: #999; font-size: 0.8rem;">TupTuDu - Zpracování faktur</p>
    </div>
</body>
</html>
