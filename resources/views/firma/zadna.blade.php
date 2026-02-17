@extends('layouts.app')
@section('title', 'Nemáte firmu')

@section('styles')
<style>
    .lookup-result { margin-top: 0.75rem; padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.9rem; }
    .lookup-result.info { background: #e8f4fd; border: 1px solid #bee3f8; color: #2b6cb0; }
    .lookup-result.warning { background: #fff8e1; border: 1px solid #ffe082; color: #795548; }
    .lookup-result.error { background: #fde8e8; border: 1px solid #f5c6cb; color: #721c24; }
    .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 600; }
    .btn-success { background: #27ae60; color: white; }
    .btn-success:hover { background: #219a52; }
</style>
@endsection

@section('content')
<div class="card" style="max-width: 550px; margin: 0 auto; text-align: center; padding: 2rem;">
    <h2 style="margin-bottom: 1rem;">Nemáte přiřazenou žádnou firmu</h2>
    <p style="color: #666; margin-bottom: 1.5rem;">
        Zadejte IČO firmy, ke které se chcete přihlásit. Správci firmy bude odeslána žádost o přiřazení.
    </p>

    <div style="margin-bottom: 1rem;">
        <input type="text" id="ico_input" maxlength="8" placeholder="IČO firmy (8 číslic)" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95rem; width: 200px; text-align: center;">
    </div>
    <div id="lookupResult" style="display: none; text-align: left;"></div>

    <hr style="border: none; border-top: 1px solid #eee; margin: 1.5rem 0;">
    <p style="color: #999; font-size: 0.85rem;">
        V případě komplikací nás kontaktujte na
        <a href="mailto:office@tuptudu.cz" style="color: #3498db;">office@tuptudu.cz</a>
    </p>
</div>
@endsection

@section('scripts')
<script>
(function() {
    var csrfToken = '{{ csrf_token() }}';
    var lookupUrl = '{{ route("firma.lookupPristup") }}';
    var zadostUrl = '{{ route("firma.zadostOPristup") }}';
    var userName = @json(auth()->user()->cele_jmeno);
    var userEmail = @json(auth()->user()->email);
    var input = document.getElementById('ico_input');
    var resultDiv = document.getElementById('lookupResult');
    var lookupTimer = null;
    var currentIco = '';

    input.addEventListener('input', function() {
        clearTimeout(lookupTimer);
        var ico = this.value.trim().replace(/\D/g, '');
        this.value = ico;
        resultDiv.style.display = 'none';
        resultDiv.innerHTML = '';
        currentIco = '';

        if (ico.length < 8) return;
        if (ico.length > 8) { this.value = ico.substring(0, 8); ico = this.value; }

        resultDiv.style.display = 'block';
        resultDiv.className = 'lookup-result info';
        resultDiv.textContent = 'Hledám v ARES...';

        lookupTimer = setTimeout(function() {
            fetch(lookupUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                body: JSON.stringify({ ico: ico })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                resultDiv.style.display = 'block';

                if (data.error) {
                    resultDiv.className = 'lookup-result error';
                    resultDiv.textContent = data.error;
                    return;
                }

                currentIco = ico;

                if (data.v_systemu) {
                    var html = '<div style="margin-bottom: 0.5rem;"><strong>' + escHtml(data.nazev) + '</strong></div>';
                    html += '<div style="margin-bottom: 0.5rem;">Firma je registrována v systému.</div>';

                    if (data.superadmins && data.superadmins.length > 1) {
                        html += '<div style="margin-bottom: 0.5rem;">Vyberte příjemce žádosti:</div>';
                        for (var i = 0; i < data.superadmins.length; i++) {
                            var sa = data.superadmins[i];
                            html += '<label style="display: block; margin-bottom: 0.3rem; cursor: pointer;">';
                            html += '<input type="radio" name="superadmin_id" value="' + sa.id + '"' + (i === 0 ? ' checked' : '') + ' style="margin-right: 0.4rem;">';
                            html += escHtml(sa.masked_email);
                            html += '</label>';
                        }
                    } else if (data.superadmins && data.superadmins.length === 1) {
                        html += '<div style="margin-bottom: 0.5rem;">Žádost bude odeslána správci na <strong>' + escHtml(data.superadmins[0].masked_email) + '</strong></div>';
                    } else {
                        html += '<div style="color: #856404;">Firma nemá žádného správce.</div>';
                    }

                    if (data.superadmins && data.superadmins.length > 0) {
                        html += '<div style="margin-top: 0.5rem;"><button type="button" class="btn btn-success" onclick="poslZadost()">Odeslat žádost o přiřazení</button></div>';
                    }
                    html += '<span id="zadostStatus" style="display: block; margin-top: 0.5rem; font-size: 0.85rem;"></span>';
                    resultDiv.className = 'lookup-result info';
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.className = 'lookup-result warning';
                    resultDiv.innerHTML = '<div><strong>' + escHtml(data.nazev) + '</strong></div><div style="margin-top: 0.3rem;">Tato firma není registrována v systému TupTuDu. Požádejte správce firmy o registraci.</div>';
                }
            })
            .catch(function() {
                resultDiv.className = 'lookup-result error';
                resultDiv.textContent = 'Chyba při komunikaci se serverem.';
            });
        }, 400);
    });

    window.poslZadost = function() {
        if (!currentIco) return;
        var status = document.getElementById('zadostStatus');

        var superadminId = null;
        var radios = document.querySelectorAll('input[name="superadmin_id"]');
        if (radios.length > 0) {
            for (var i = 0; i < radios.length; i++) {
                if (radios[i].checked) { superadminId = parseInt(radios[i].value); break; }
            }
        }

        status.textContent = 'Odesílám...';
        status.style.color = '#666';

        var body = { ico: currentIco, jmeno: userName, email: userEmail };
        if (superadminId) body.superadmin_id = superadminId;

        fetch(zadostUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
            body: JSON.stringify(body)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                status.textContent = 'Žádost odeslána. Vyčkejte na schválení správcem firmy.';
                status.style.color = '#27ae60';
            } else {
                status.textContent = data.error || 'Chyba';
                status.style.color = '#e74c3c';
            }
        })
        .catch(function() {
            status.textContent = 'Chyba připojení.';
            status.style.color = '#e74c3c';
        });
    };

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
})();
</script>
@endsection
