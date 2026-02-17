@extends('layouts.app')
@section('title', 'Klienti')

@section('styles')
<style>
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; }
    .form-group input { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95rem; }
    .form-group input:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 2px rgba(52,152,219,0.15); }
    .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 600; }
    .btn-primary { background: #3498db; color: white; }
    .btn-primary:hover { background: #2980b9; }
    .btn-success { background: #27ae60; color: white; }
    .btn-success:hover { background: #219a52; }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-danger:hover { background: #c0392b; }
    .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.8rem; }
    .client-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .client-table th, .client-table td { padding: 0.6rem 0.8rem; text-align: left; border-bottom: 1px solid #eee; font-size: 0.9rem; }
    .client-table th { background: #f8f9fa; font-weight: 600; color: #555; }
    .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
    .badge-success { background: #d4edda; color: #155724; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-danger { background: #f8d7da; color: #721c24; }
    .flash { background: #e8f8f0; border: 1px solid #27ae60; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem; color: #27ae60; }
    .error-msg { color: #e74c3c; font-size: 0.85rem; }
    .lookup-result { margin-top: 0.75rem; padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.9rem; }
    .lookup-result.info { background: #e8f4fd; border: 1px solid #bee3f8; color: #2b6cb0; }
    .lookup-result.warning { background: #fff8e1; border: 1px solid #ffe082; color: #795548; }
    .lookup-result.error { background: #fde8e8; border: 1px solid #f5c6cb; color: #721c24; }
</style>
@endsection

@section('content')
<div class="card">
    <h2 style="margin-bottom: 1.5rem;">Správa klientů</h2>

    @if (session('flash'))
        <div class="flash">{{ session('flash') }}</div>
    @endif

    <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 6px;">
        <h3 style="margin-bottom: 0.8rem; font-size: 1rem;">Přidat klienta</h3>
        <div class="form-group" style="margin-bottom: 0.5rem;">
            <label for="klient_ico">IČO klienta</label>
            <input type="text" id="klient_ico" maxlength="8" placeholder="12345678" style="max-width: 250px;">
        </div>
        <div id="lookupResult" style="display: none;"></div>
    </div>

    @if ($vazby->isEmpty())
        <p style="color: #666;">Zatím nemáte žádné klienty.</p>
    @else
        <table class="client-table">
            <thead>
                <tr>
                    <th>IČO</th>
                    <th>Název</th>
                    <th>Stav</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vazby as $vazba)
                    <tr>
                        <td>{{ $vazba->klient_ico }}</td>
                        <td>{{ $vazba->klientFirma?->nazev ?? '—' }}</td>
                        <td>
                            @if ($vazba->stav === 'schvaleno')
                                <span class="badge badge-success">Schváleno</span>
                            @elseif ($vazba->stav === 'ceka_na_firmu')
                                <span class="badge badge-warning">Čeká na schválení</span>
                            @elseif ($vazba->stav === 'zamitnuto')
                                <span class="badge badge-danger">Zamítnuto</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('klienti.destroy', $vazba->klient_ico) }}" onsubmit="return confirm('Opravdu odebrat klienta?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Odebrat</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection

@section('scripts')
<script>
(function() {
    var csrfToken = '{{ csrf_token() }}';
    var lookupUrl = '{{ route("klienti.lookup") }}';
    var zadostUrl = '{{ route("klienti.poslZadost") }}';
    var input = document.getElementById('klient_ico');
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
                    // Firma je registrována v systému
                    var html = '<div style="margin-bottom: 0.5rem;"><strong>' + escHtml(data.nazev) + '</strong></div>';
                    html += '<div style="margin-bottom: 0.75rem;">Firma je již v systému registrována. Žádost bude odeslána na <strong>' + escHtml(data.masked_email || '—') + '</strong></div>';
                    html += '<button type="button" class="btn btn-success" onclick="poslZadost()">Odeslat žádost</button>';
                    html += '<span id="zadostStatus" style="margin-left: 0.75rem; font-size: 0.85rem;"></span>';
                    resultDiv.className = 'lookup-result info';
                    resultDiv.innerHTML = html;
                } else {
                    // Firma neexistuje v systému
                    var html = '<div style="margin-bottom: 0.5rem;"><strong>' + escHtml(data.nazev) + '</strong></div>';
                    html += '<div style="margin-bottom: 0.75rem;">Zadejte email oprávněné osoby ve firmě ' + escHtml(data.nazev) + ':</div>';
                    html += '<div style="display: flex; gap: 0.5rem; align-items: center;">';
                    html += '<input type="email" id="zadostEmail" placeholder="email@firma.cz" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; flex: 1; max-width: 300px;">';
                    html += '<button type="button" class="btn btn-success" onclick="poslZadost()">Odeslat žádost</button>';
                    html += '</div>';
                    html += '<span id="zadostStatus" style="display: block; margin-top: 0.5rem; font-size: 0.85rem;"></span>';
                    resultDiv.className = 'lookup-result warning';
                    resultDiv.innerHTML = html;
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
        var emailInput = document.getElementById('zadostEmail');
        var email = emailInput ? emailInput.value.trim() : null;
        var status = document.getElementById('zadostStatus');

        if (emailInput && !email) {
            status.textContent = 'Vyplňte email.';
            status.style.color = '#e74c3c';
            return;
        }

        status.textContent = 'Odesílám...';
        status.style.color = '#666';

        fetch(zadostUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
            body: JSON.stringify({ ico: currentIco, email: email })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                status.textContent = data.message;
                status.style.color = '#27ae60';
                setTimeout(function() { window.location.reload(); }, 2000);
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
