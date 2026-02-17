<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"></head>
<body style="font-family: sans-serif; background: #f5f5f5; padding: 2rem;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 style="color: #2c3e50; margin-top: 0;">Žádost o vedení účetnictví</h2>

        <p>Účetní firma <strong>{{ $ucetniFirma->nazev }}</strong> (IČO: {{ $ucetniFirma->ico }}) vás žádá o souhlas s vedením účetnictví pro firmu <strong>{{ $klientFirma->nazev }}</strong> (IČO: {{ $klientFirma->ico }}).</p>

        <p>Kontaktní osoba: <strong>{{ $user->cele_jmeno }}</strong> ({{ $user->email }})</p>

        @if ($vSystemu)
            <p>Žádost můžete schválit nebo zamítnout přímo v nastavení vaší firmy:</p>
            <p style="text-align: center; margin: 2rem 0;">
                <a href="{{ url('/nastaveni') }}" style="background: #27ae60; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;">Schválit v nastavení</a>
            </p>
        @else
            <p>Pro správu dokladů vaší firmy se zaregistrujte v systému TupTuDu:</p>
            <p style="text-align: center; margin: 2rem 0;">
                <a href="{{ url('/registrace') }}" style="background: #3498db; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;">Registrovat se</a>
            </p>
            <p style="font-size: 0.9rem; color: #666;">Po registraci s IČO {{ $klientFirma->ico }} se žádost automaticky zobrazí ve vašem nastavení ke schválení.</p>
        @endif

        <p style="color: #666; font-size: 0.9rem;">Pokud s tímto nesouhlasíte nebo tuto firmu neznáte, tento email ignorujte.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 1.5rem 0;">
        <p style="color: #999; font-size: 0.8rem;">TupTuDu - Zpracování dokladů</p>
    </div>
</body>
</html>
