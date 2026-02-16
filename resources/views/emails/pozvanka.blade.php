<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"></head>
<body style="font-family: sans-serif; background: #f5f5f5; padding: 2rem;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 style="color: #2c3e50; margin-top: 0;">Pozvánka do firmy</h2>
        <p>Dobrý den, {{ $pozvani->jmeno }},</p>
        <p>byli jste pozváni do firmy <strong>{{ $firma->nazev }}</strong> v systému TupTuDu.</p>
        <p>Pro vytvoření účtu klikněte na tlačítko níže:</p>
        <p style="text-align: center; margin: 2rem 0;">
            <a href="{{ $registracniUrl }}" style="background: #3498db; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;">Vytvořit účet</a>
        </p>
        <p style="color: #666; font-size: 0.9rem;">Odkaz je platný 7 dní. Pokud jste tuto pozvánku neočekávali, tento email ignorujte.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 1.5rem 0;">
        <p style="color: #999; font-size: 0.8rem;">TupTuDu - Zpracování faktur</p>
    </div>
</body>
</html>
