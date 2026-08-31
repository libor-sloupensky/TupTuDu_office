<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#3498db">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Skenovat doklad</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, system-ui, Segoe UI, Roboto, sans-serif; background: #f4f6f8; color: #2c3e50; -webkit-tap-highlight-color: transparent; }
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1rem; background: white; border-bottom: 1px solid #e0e6ec; padding-top: calc(env(safe-area-inset-top, 0) + 0.85rem); }
        .topbar h1 { margin: 0; font-size: 1.05rem; font-weight: 600; }
        .firma { font-size: 0.78rem; color: #7f8c8d; margin-top: 0.1rem; }
        .logout { background: none; border: none; color: #7f8c8d; font-size: 0.85rem; cursor: pointer; padding: 0.4rem; }

        .firma-box { width: 100%; max-width: 420px; background: white; border-radius: 10px; padding: 0.85rem 1rem; margin-bottom: 1rem; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
        .firma-box label { display: block; font-size: 0.75rem; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.35rem; }
        .firma-box select { width: 100%; padding: 0.7rem; font-size: 1rem; border: 1px solid #d0d8e0; border-radius: 8px; background: white; color: #2c3e50; }
        .firma-box .jedna { font-size: 1rem; font-weight: 600; }

        .wrap { padding: 1.5rem; padding-bottom: calc(env(safe-area-inset-bottom, 0) + 1.5rem); display: flex; flex-direction: column; align-items: center; }

        .scan-btn { width: 100%; max-width: 420px; padding: 1.5rem; font-size: 1.15rem; font-weight: 600; background: #3498db; color: white; border: none; border-radius: 14px; cursor: pointer; margin-bottom: 1rem; box-shadow: 0 4px 14px rgba(52, 152, 219, 0.3); display: flex; align-items: center; justify-content: center; gap: 0.6rem; }
        .scan-btn:active { background: #2980b9; transform: scale(0.98); }
        .scan-btn[disabled] { background: #95a5a6; box-shadow: none; }
        .scan-icon { width: 28px; height: 28px; }

        .results { width: 100%; max-width: 420px; }
        .result-item { background: white; border-radius: 10px; padding: 0.85rem 1rem; margin-bottom: 0.6rem; display: flex; align-items: flex-start; gap: 0.6rem; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
        .result-icon { font-size: 1.3rem; flex-shrink: 0; line-height: 1.2; width: 1.3rem; text-align: center; }
        .result-body { flex: 1; min-width: 0; }
        .result-name { font-weight: 600; font-size: 0.92rem; word-break: break-word; }
        .result-msg { font-size: 0.8rem; color: #7f8c8d; margin-top: 0.15rem; }
        .result-ok { color: #27ae60; }
        .result-warn { color: #e67e22; }
        .result-err { color: #e74c3c; }
        .result-dup { color: #95a5a6; }

        .info { font-size: 0.85rem; color: #7f8c8d; text-align: center; margin: 1.5rem 0; padding: 0 1rem; }
        .web-warn { background: #fffbea; border: 1px solid #f0c36d; color: #856404; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1rem; max-width: 420px; width: 100%; }

        .spinner { display: inline-block; width: 18px; height: 18px; border: 3px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite; }
        .spinner-dark { width: 16px; height: 16px; border-color: rgba(52,152,219,0.25); border-top-color: #3498db; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="topbar">
    <div>
        <h1>Skenovat doklad</h1>
        <div class="firma">{{ $user->cele_jmeno }}</div>
    </div>
    <form method="POST" action="{{ route('mobile.logout') }}" style="margin:0">
        @csrf
        <button type="submit" class="logout">Odhlásit</button>
    </form>
</div>

<div class="wrap">
    <div id="webWarn" class="web-warn" style="display:none">
        Tato stránka je určená pro mobilní aplikaci TupTuDu. Otevřete ji v aplikaci pro spuštění skeneru.
    </div>

    {{-- Doklad se uloží k firmě vybrané zde (= aktivní firma v session, stejně jako na webu) --}}
    <div class="firma-box">
        <label for="firmaSelect">Uložit k firmě</label>
        @if ($firmy->count() > 1)
            <form method="POST" action="{{ route('mobile.prepnoutFirmu', ['ico' => '__ICO__']) }}" id="firmaForm" style="margin:0">
                @csrf
                <select id="firmaSelect" onchange="prepnoutFirmu(this.value)">
                    @foreach ($firmy as $f)
                        <option value="{{ $f->ico }}" @selected($firma && $f->ico === $firma->ico)>{{ $f->nazev }}</option>
                    @endforeach
                </select>
            </form>
        @else
            <div class="jedna">{{ $firma->nazev ?? 'Žádná firma' }}</div>
        @endif
    </div>

    <button id="scanBtn" class="scan-btn" onclick="startScan()">
        <x-ikona name="scan-line" :size="28" class="scan-icon" />
        <span id="scanBtnLabel">Skenovat doklad</span>
    </button>

    <p class="info">Vyfoťte doklad — aplikace ho automaticky ořízne, narovná a uloží jako PDF.</p>

    {{-- Jediný seznam dokladů. Skládá se ze dvou zdrojů: co se právě nahrává
         (žije jen v paměti stránky) a co server opravdu má. Jakmile je známé ID
         dokladu, oba zdroje splynou do jednoho řádku. --}}
    <div id="results" class="results"></div>
</div>

<script>
// Stavové ikony vkládá JavaScript, kde Blade komponentu ikony použít nejde —
// vloží se sem rovnou jako hotové SVG. Zdroj je stejný, tedy App\Support\Lucide.
// Pozor: název komponenty se sem nesmí napsat doslova ani v komentáři. Blade ho
// zkompiluje i tady a rozbije tím celou stránku.
const IKONY = {
    ok: @json(\App\Support\Lucide::svg('circle-check', 18)),
    duplicita: @json(\App\Support\Lucide::svg('copy', 18)),
    varovani: @json(\App\Support\Lucide::svg('triangle-alert', 18)),
    chyba: @json(\App\Support\Lucide::svg('circle-x', 18)),
};

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const firmaIco = @json($firma?->ico);
const uploadUrl = '{{ route("invoices.store") }}';

function isNative() {
    return window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform();
}

if (!isNative()) {
    document.getElementById('webWarn').style.display = 'block';
}

// Přepnutí firmy — POST na server, aby se změnila aktivní firma v session
// Přepnutí firmy. Kdyby token přece jen nesouhlasil (server hlásí 419), stránku
// jen načteme znovu — uživatel dostane čerstvý token místo hlášky "Page expired".
async function prepnoutFirmu(ico) {
    const zaklad = document.getElementById('firmaForm').action.replace(/[^/]*$/, '');

    try {
        const resp = await fetch(zaklad + encodeURIComponent(ico), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'include',
            // Přesměrování nenásledujeme — stránku stejně hned načteme znovu.
            redirect: 'manual',
        });

        if (resp.status === 419) {
            window.location.reload();
            return;
        }
    } catch (e) {
        // Bez sítě se nepřepneme; stránka zůstane, jak byla
        addResult({ status: 'error', name: 'Přepnutí firmy', message: 'Nepodařilo se spojit se serverem.' });
        return;
    }

    // Firma je součástí stránky (nadpis, cíl nahrávání), tak ji načteme znovu.
    window.location.reload();
}

function setBusy(busy) {
    const btn = document.getElementById('scanBtn');
    const lbl = document.getElementById('scanBtnLabel');
    btn.disabled = busy;
    if (busy) {
        lbl.innerHTML = '<span class="spinner"></span>&nbsp;Otevírám skener…';
    } else {
        lbl.textContent = 'Skenovat doklad';
    }
}

function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[<>&"]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
}

function cas() {
    const d = new Date();
    const dva = n => String(n).padStart(2, '0');
    return dva(d.getDate()) + '.' + dva(d.getMonth() + 1) + '. ' + dva(d.getHours()) + ':' + dva(d.getMinutes());
}

// Krátký název: 26-08-11-a3f9c1.pdf — datum kvůli řazení, hex kvůli jedinečnosti
function novyNazev() {
    const d = new Date();
    const dva = n => String(n).padStart(2, '0');
    const datum = dva(d.getFullYear() % 100) + '-' + dva(d.getMonth() + 1) + '-' + dva(d.getDate());
    let hex = '';
    for (let i = 0; i < 6; i++) hex += '0123456789abcdef'[Math.floor(Math.random() * 16)];
    return datum + '-' + hex + '.pdf';
}

// Jediný seznam dokladů. Řádek vzniká hned při skenování, ještě než o dokladu
// cokoli víme, a postupně se doplňuje — nejdřív z odpovědi na nahrání, pak ze
// serveru. Spojuje se podle ID dokladu, takže se nic nezdvojuje.
const polozky = [];
let posledniKlic = 0;

function radek(p) {
    let cls = 'result-ok', ikona = IKONY.ok;

    if (p.cekam) { cls = ''; ikona = '<span class="spinner spinner-dark"></span>'; }
    else if (p.stav === 'duplicate') { cls = 'result-dup'; ikona = IKONY.duplicita; }
    else if (p.stav === 'warning') { cls = 'result-warn'; ikona = IKONY.varovani; }
    else if (p.stav === 'error') { cls = 'result-err'; ikona = IKONY.chyba; }

    return '<div class="result-item">' +
        '<div class="result-icon ' + cls + '">' + ikona + '</div>' +
        '<div class="result-body">' +
            '<div class="result-name">' + escapeHtml(p.nazev) + '</div>' +
            '<div class="result-msg">' + escapeHtml(p.cas) +
                (p.zprava ? ' · ' + escapeHtml(p.zprava) : '') + '</div>' +
        '</div>' +
    '</div>';
}

function vykreslit() {
    document.getElementById('results').innerHTML = polozky
        .slice()
        .sort((a, b) => b.ts - a.ts)
        .map(radek)
        .join('');
}

// Založí položku ve stavu "nahrávám" a vrátí handle pro pozdější dokončení.
// Uživatel může mezitím skenovat dál — každý doklad si drží svůj řádek.
function pridatPolozku(nazev) {
    const p = {
        klic: 'l' + (++posledniKlic),
        id: null,
        nazev: nazev,
        zprava: 'zpracovávám…',
        cas: cas(),
        ts: Date.now(),
        cekam: true,
        stav: 'ok',
        zeServeru: false,
    };

    polozky.push(p);
    vykreslit();

    return {
        dokoncit(item) {
            p.cekam = false;
            p.stav = item.status || 'ok';
            p.zprava = item.message || 'Nahráno';
            p.cas = cas();
            if (item.name) p.nazev = item.name;
            if (item.id) p.id = item.id;
            vykreslit();
            nacistPosledni();
        },
    };
}

// Jednorázová hláška bez navazujícího zpracování (chyby skeneru apod.)
function addResult(item) {
    pridatPolozku(item.name || 'Doklad').dokoncit(item);
}

function base64NaBlob(base64, mime) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return new Blob([bytes], { type: mime });
}

async function startScan() {
    if (!isNative()) {
        addResult({ status: 'error', name: 'Skener není dostupný', message: 'Otevřete v mobilní aplikaci.' });
        return;
    }

    try {
        setBusy(true);
        const { DocumentScanner, Filesystem } = window.Capacitor.Plugins;

        // Skener je součást Google Play Services a na některých zařízeních
        // se stahuje až na vyžádání — dokud modul chybí, scanDocument() spadne.
        const dostupnost = await DocumentScanner.isGoogleDocumentScannerModuleAvailable();
        if (!dostupnost.available) {
            addResult({ status: 'warning', name: 'Instaluji skener', message: 'Stahuje se modul Google skeneru, zkuste to prosím za chvíli znovu.' });
            await DocumentScanner.installGoogleDocumentScannerModule();
            setBusy(false);
            return;
        }

        const result = await DocumentScanner.scanDocument({
            pageLimit: 10,
            resultFormats: 'PDF',
            scannerMode: 'FULL',
            galleryImportAllowed: true,
        });

        if (!result || !result.pdf || !result.pdf.uri) {
            setBusy(false);
            return;
        }

        // PDF nečteme přes fetch(file://) — WebView běží na https://office.tuptudu.cz
        // a lokální soubor by mu origin policy nedovolila. Filesystem plugin ho
        // podá přes nativní bridge jako base64.
        const soubor = await Filesystem.readFile({ path: result.pdf.uri });
        const blob = base64NaBlob(soubor.data, 'application/pdf');
        const fileName = novyNazev();

        // Tlačítko uvolníme hned po skenu — rozpoznávání AI trvá desítky sekund
        // a uživatel může mezitím skenovat další doklad. Upload doběhne na pozadí.
        setBusy(false);
        nahratNaPozadi(blob, fileName);
    } catch (err) {
        setBusy(false);
        const msg = (err && err.message) ? err.message : String(err);
        if (!msg || !msg.toLowerCase().includes('cancel')) {
            addResult({ status: 'error', name: 'Chyba skeneru', message: msg });
        }
    }
}

async function nahratNaPozadi(blob, fileName) {
    const polozka = pridatPolozku(fileName);

    try {
        const formData = new FormData();
        formData.append('documents[]', blob, fileName);
        // Firma se posílá výslovně — je to ta, kterou má uživatel na obrazovce.
        // Spoléhat se jen na session se ukázalo jako nebezpečné: dala se rozhodit
        // a doklad se pak tiše uložil jiné firmě.
        if (firmaIco) formData.append('firma_ico', firmaIco);

        const upResp = await fetch(uploadUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'include',
            body: formData,
        });

        if (upResp.status === 401 || upResp.status === 419) {
            window.location.href = '{{ route("mobile.prihlaseni") }}';
            return;
        }

        const data = await upResp.json();
        const items = Array.isArray(data) ? data : (data.results || []);

        if (items.length === 0) {
            polozka.dokoncit({ status: 'ok', message: 'Nahráno' });
        } else {
            // Z jednoho skenu může vzniknout víc dokladů — první doplní
            // stávající řádek, další dostanou vlastní.
            polozka.dokoncit(items[0]);
            items.slice(1).forEach(it => addResult(it));
        }

        nacistPosledni();
    } catch (err) {
        // Zpracování dokladu trvá desítky sekund a server v něm pokračuje, i když
        // klientovi mezitím spadne spojení (přepnutí sítě, uspání appky). Než to
        // ohlásíme jako chybu, ověříme podle otisku souboru, jestli doklad nevznikl.
        const doklad = await overitPodleHashe(blob);

        if (doklad) {
            polozka.dokoncit({
                status: 'ok',
                message: 'Nahráno (spojení se přerušilo, doklad se ale uložil)',
            });
            nacistPosledni();
            return;
        }

        const msg = (err && err.message) ? err.message : String(err);
        polozka.dokoncit({ status: 'error', message: 'Nahrání selhalo: ' + msg });
    }
}

/** Popis dokladu tak, jak ho zná server. */
function popisZeServeru(d) {
    if (d.stav === 'chyba') return 'Nepodařilo se vytěžit';
    if (d.stav === 'ulozeno') {
        return d.druh === 'dokument' ? 'Uložený dokument' : 'Uloženo, čeká na vytěžení';
    }

    return d.castka || 'Nahráno';
}

/**
 * Doplní seznam o to, co server opravdu má.
 *
 * Řádky se párují podle ID dokladu. Hlášku z nahrávání nepřepisujeme — bývá
 * podrobnější (třeba varování o jiném odběrateli); měníme ji jen u řádků, které
 * ze serveru i pocházejí. Díky tomu se stav sám aktualizuje, když se doklad
 * mezitím vytěží.
 */
async function nacistPosledni() {
    try {
        const resp = await fetch('{{ route("doklady.posledni") }}', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'include',
        });

        if (!resp.ok) return;

        const doklady = await resp.json();
        if (!Array.isArray(doklady)) return;

        doklady.forEach(d => {
            let p = polozky.find(x => x.id === d.id);

            if (!p) {
                p = { klic: 's' + d.id, id: d.id, cekam: false, zeServeru: true, ts: Date.now() };
                polozky.push(p);
            }

            if (p.cekam) return; // ještě se nahrává, do rozdělaného řádku nesaháme

            p.nazev = d.nazev || p.nazev || 'Doklad';
            p.cas = d.nahrano || p.cas || '';
            if (d.nahrano_iso) p.ts = Date.parse(d.nahrano_iso);
            if (d.stav === 'chyba') p.stav = 'error';
            if (p.zeServeru) p.zprava = popisZeServeru(d);
        });

        vykreslit();
    } catch (e) {
        // Bez sítě se seznam prostě nepřekreslí
    }
}

// Po návratu do aplikace (odemknutí telefonu, přepnutí z jiné appky) se
// seznam obnoví — právě tehdy chce uživatel vidět, jak dopadlo skenování.
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) nacistPosledni();
});

nacistPosledni();

/** Otisk souboru — stejný sha256, podle kterého server pozná duplicitu. */
async function spocitatHash(blob) {
    const buffer = await blob.arrayBuffer();
    const digest = await crypto.subtle.digest('SHA-256', buffer);

    return Array.from(new Uint8Array(digest))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
}

/**
 * Počká, jestli doklad na serveru dorazí. Zkouší se opakovaně — zpracování
 * mohlo v okamžiku přerušení ještě běžet.
 */
async function overitPodleHashe(blob) {
    let hash;

    try {
        hash = await spocitatHash(blob);
    } catch (e) {
        return null;
    }

    const url = '{{ url("/doklady/hash") }}/' + hash;

    for (let pokus = 0; pokus < 6; pokus++) {
        await new Promise(r => setTimeout(r, 5000));

        try {
            const resp = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'include',
            });

            if (!resp.ok) continue;

            const data = await resp.json();
            if (data.existuje) return data;
        } catch (e) {
            // Síť je pořád mimo, zkusíme to za chvíli znovu
        }
    }

    return null;
}
</script>

</body>
</html>
