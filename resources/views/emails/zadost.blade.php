<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"></head>
<body style="font-family: sans-serif; background: #f5f5f5; padding: 2rem;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 style="color: #2c3e50; margin-top: 0;">Žádost o přístup</h2>
        <p><strong>{{ $zadatelJmeno }}</strong> ({{ $zadatelEmail }}) žádá o přístup do firmy <strong>{{ $firma->nazev }}</strong> (IČO: {{ $firma->ico }}).</p>
        <p>Pokud tuto osobu znáte, můžete ji přidat v nastavení firmy:</p>
        <p style="text-align: center; margin: 2rem 0;">
            <a href="{{ url('/nastaveni') }}" style="background: #3498db; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;">Otevřít nastavení</a>
        </p>
        <p style="color: #666; font-size: 0.9rem;">Pokud tuto osobu neznáte, tento email ignorujte.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 1.5rem 0;">
        <p style="color: #999; font-size: 0.8rem;">TupTuDu - Zpracování dokladů</p>
    </div>
</body>
</html>
