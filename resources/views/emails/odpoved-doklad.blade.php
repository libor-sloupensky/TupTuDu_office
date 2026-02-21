<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; padding: 2rem; margin: 0;">
    <div style="max-width: 520px; margin: 0 auto;">
        {{-- Header --}}
        <div style="background: #2c3e50; color: white; padding: 1rem 1.5rem; border-radius: 8px 8px 0 0; text-align: center;">
            <strong style="font-size: 1.1rem; letter-spacing: 0.5px;">TupTuDu</strong>
            <div style="font-size: 0.75rem; opacity: 0.7; margin-top: 2px;">automatická schránka pro příjem dokladů</div>
        </div>

        {{-- Body --}}
        <div style="background: white; padding: 1.5rem; border-left: 1px solid #e0e0e0; border-right: 1px solid #e0e0e0;">
            <div style="color: #333; font-size: 0.95rem; line-height: 1.6;">
                {!! nl2br(e($bodyText)) !!}
            </div>
        </div>

        {{-- Footer --}}
        <div style="background: #f8f9fa; padding: 1rem 1.5rem; border-radius: 0 0 8px 8px; border: 1px solid #e0e0e0; border-top: none;">
            <p style="color: #999; font-size: 0.75rem; margin: 0; text-align: center;">
                Toto je automatická odpověď &mdash; na tento email neodpovídejte.
            </p>
        </div>
    </div>
</body>
</html>
