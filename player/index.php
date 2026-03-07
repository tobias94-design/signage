<?php
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

$token = $_GET['token'] ?? '';
if (!$token) {
    $primo = $db->query('SELECT token FROM dispositivi LIMIT 1')->fetch();
    $token = $primo['token'] ?? '';
}

$dispositivo = $db->prepare('SELECT d.*, p.nome as profilo_nome FROM dispositivi d LEFT JOIN profili p ON p.id = d.profilo_id WHERE d.token = ?');
$dispositivo->execute([$token]);
$dispositivo = $dispositivo->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Player</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #000;
            width: 1920px;
            height: 1080px;
            overflow: hidden;
            font-family: Arial, sans-serif;
        }

        #layer-tv {
            position: absolute;
            inset: 0;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
        }
        #tv-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        #tv-placeholder {
            position: absolute;
            color: #222;
            font-size: 32px;
            text-align: center;
        }

        #layer-adv {
            position: absolute;
            inset: 0;
            background: #000;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }
        #adv-video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        #adv-immagine {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: none;
        }

        #layer-banner {
            position: absolute;
            left: 0; right: 0;
            display: flex;
            align-items: center;
            z-index: 10;
            overflow: hidden;
        }
        #banner-logo { object-fit: contain; flex-shrink: 0; }
        #banner-testo { opacity: 0.75; flex-shrink: 0; }
        .banner-spacer { flex: 1; }
        #banner-datetime { text-align: right; line-height: 1.3; flex-shrink: 0; }
        #banner-ora { font-weight: bold; letter-spacing: 3px; }

        #debug {
            position: absolute;
            bottom: 10px; left: 10px;
            background: rgba(0,0,0,0.85);
            color: #0f0;
            font-size: 14px;
            padding: 10px 14px;
            border-radius: 6px;
            z-index: 99;
            font-family: monospace;
            display: none;
            max-width: 600px;
            line-height: 1.6;
        }
    </style>
</head>
<body>

<!-- Layer 1: TV -->
<div id="layer-tv">
    <div id="tv-placeholder">📺 Connessione segnale TV...</div>
    <video id="tv-video" autoplay playsinline muted></video>
</div>

<!-- Layer 2: ADV -->
<div id="layer-adv">
    <video id="adv-video" preload="auto" muted playsinline autoplay></video>
    <img id="adv-immagine" src="">
</div>

<!-- Layer 3: Banner -->
<div id="layer-banner">
    <img id="banner-logo" src="" style="display:none;">
    <span id="banner-testo"></span>
    <div class="banner-spacer"></div>
    <div id="banner-datetime">
        <div id="banner-ora">--:--:--</div>
        <div id="banner-data"></div>
    </div>
</div>

<div id="debug"></div>

<script>
const TOKEN      = '<?php echo htmlspecialchars($token); ?>';
const BASE_URL   = '../';
const DEBUG_MODE = false;
const GIORNI     = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
const MESI       = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                    'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

let statoCorrente   = null;
let advTimer        = null;
let indiceContenuto = 0;
let contenuti       = [];
let tvStream        = null;

// ─── OROLOGIO ────────────────────────────────────────────────
function aggiornaOrologio() {
    const now  = new Date();
    const ora  = String(now.getHours()).padStart(2,'0') + ':' +
                 String(now.getMinutes()).padStart(2,'0') + ':' +
                 String(now.getSeconds()).padStart(2,'0');
    const data = GIORNI[now.getDay()] + '  ' + now.getDate() +
                 ' ' + MESI[now.getMonth()] + ' ' + now.getFullYear();
    document.getElementById('banner-ora').textContent  = ora;
    document.getElementById('banner-data').textContent = data;
}

// ─── LOG ─────────────────────────────────────────────────────
function log(msg) {
    if (!DEBUG_MODE) return;
    const d   = document.getElementById('debug');
    d.style.display = 'block';
    const ora = new Date().toLocaleTimeString();
    d.innerHTML = '[' + ora + '] ' + msg + '<br>' + d.innerHTML;
    const lines = d.innerHTML.split('<br>');
    if (lines.length > 10) d.innerHTML = lines.slice(0, 10).join('<br>');
}

// ─── CAPTURE CARD ────────────────────────────────────────────
async function avviaSegnaleTV() {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const capture = devices.find(d => d.kind === 'videoinput');

        if (!capture) {
            log('⚠️ Nessuna capture card trovata');
            document.getElementById('tv-placeholder').textContent = '📺 Nessuna sorgente TV';
            return;
        }

        log('📺 Capture: ' + capture.label);

        const stream = await navigator.mediaDevices.getUserMedia({
            video: { deviceId: { exact: capture.deviceId } },
            audio: true
        });

        tvStream = stream;
        const tvVideo = document.getElementById('tv-video');
        tvVideo.srcObject = stream;
        tvVideo.play();
        document.getElementById('tv-placeholder').style.display = 'none';
        log('✅ Segnale TV attivo');

    } catch (e) {
        log('⚠️ Errore TV: ' + e.message);
        document.getElementById('tv-placeholder').textContent = '📺 Errore segnale TV';
    }
}

// ─── BANNER ──────────────────────────────────────────────────
function applicaBanner(banner) {
    const el      = document.getElementById('layer-banner');
    const altezza = parseInt(banner.banner_altezza) || 80;

    el.style.backgroundColor = banner.banner_colore || '#000';
    el.style.color            = banner.banner_testo_colore || '#fff';
    el.style.height           = altezza + 'px';
    el.style.padding          = '0 ' + Math.round(altezza * 0.3) + 'px';
    el.style.gap              = Math.round(altezza * 0.2) + 'px';

    if (banner.banner_posizione === 'top') {
        el.style.top    = '0';
        el.style.bottom = 'auto';
    } else {
        el.style.bottom = '0';
        el.style.top    = 'auto';
    }

    const logo = document.getElementById('banner-logo');
    if (banner.logo) {
        logo.src           = BASE_URL + 'assets/img/' + banner.logo;
        logo.style.display = 'block';
        logo.style.height  = Math.round(altezza * 0.78) + 'px';
        logo.style.width   = 'auto';
    } else {
        logo.style.display = 'none';
    }

    const testoEl = document.getElementById('banner-testo');
    testoEl.textContent    = banner.banner_testo || '';
    testoEl.style.fontSize = Math.round(altezza * 0.22) + 'px';

    document.getElementById('banner-ora').style.fontSize   = Math.round(altezza * 0.42) + 'px';
    document.getElementById('banner-data').style.fontSize  = Math.round(altezza * 0.20) + 'px';
    document.getElementById('banner-datetime').style.color = banner.banner_testo_colore || '#fff';
}

// ─── MOSTRA TV ───────────────────────────────────────────────
function mostraTV() {
    document.getElementById('layer-tv').style.zIndex  = '1';
    document.getElementById('layer-adv').style.display = 'none';
    const video = document.getElementById('adv-video');
    video.pause();
    video.src = '';
    if (advTimer) { clearTimeout(advTimer); advTimer = null; }
    log('📺 Modalità TV');
}

// ─── MOSTRA ADV ──────────────────────────────────────────────
function mostraADV(stato) {
    contenuti       = stato.contenuti || [];
    indiceContenuto = 0;

    if (stato.contenuto_ora) {
        const idx = contenuti.findIndex(c => c.id === stato.contenuto_ora.id);
        if (idx >= 0) indiceContenuto = idx;
    }

    document.getElementById('layer-adv').style.display = 'flex';
    document.getElementById('layer-adv').style.zIndex  = '3';

    log('📋 ADV — ' + stato.playlist_nome);
    mostraContenuto(indiceContenuto);
}

function mostraContenuto(idx) {
    if (!contenuti.length) { mostraTV(); return; }

    idx = idx % contenuti.length;
    indiceContenuto = idx;

    const c        = contenuti[idx];
    const video    = document.getElementById('adv-video');
    const immagine = document.getElementById('adv-immagine');
    const url      = BASE_URL + 'uploads/' + c.file;

    log('▶ [' + (idx+1) + '/' + contenuti.length + '] ' + c.nome);

    if (c.tipo === 'video') {
        immagine.style.display = 'none';
        video.style.display    = 'block';
        video.muted            = true;
        video.volume           = 0;
        video.defaultMuted     = true;
        video.setAttribute('muted', '');
        video.src              = url;
        video.load();
        video.addEventListener('canplay', function handler() {
            video.removeEventListener('canplay', handler);
            video.play().catch(e => log('⚠️ Autoplay: ' + e.message));
        });
        video.onended = () => mostraContenuto(indiceContenuto + 1);
    } else {
        video.pause();
        video.src              = '';
        video.style.display    = 'none';
        immagine.style.display = 'block';
        immagine.src           = url;
        if (advTimer) clearTimeout(advTimer);
        advTimer = setTimeout(() => mostraContenuto(indiceContenuto + 1), c.durata * 1000);
    }
}

// ─── POLLING API ─────────────────────────────────────────────
async function aggiornaDaAPI() {
    try {
        const res = await fetch(BASE_URL + 'api/stato.php?token=' + TOKEN + '&t=' + Date.now());
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const stato = await res.json();

        if (stato.errore) {
            log('❌ ' + stato.errore);
            setTimeout(aggiornaDaAPI, 15000);
            return;
        }

        if (stato.banner) applicaBanner(stato.banner);

        const modalitaCambiata = !statoCorrente || statoCorrente.modalita !== stato.modalita;

        if (stato.modalita === 'tv') {
            if (modalitaCambiata) mostraTV();
            const tra = Math.min((stato.secondi_alla_adv || 30) * 1000, 30000);
            setTimeout(aggiornaDaAPI, tra);
        } else if (stato.modalita === 'adv') {
            if (modalitaCambiata) mostraADV(stato);
            const tra = Math.min((stato.secondi_alla_tv || 60) * 1000, 30000);
            setTimeout(aggiornaDaAPI, tra);
        }

        statoCorrente = stato;

    } catch (e) {
        log('⚠️ Errore: ' + e.message);
        caricaCache();
    }
}

async function caricaCache() {
    try {
        const res = await fetch(BASE_URL + 'cache/stato-' + TOKEN + '.json?t=' + Date.now());
        if (!res.ok) throw new Error('Cache non trovata');
        const stato = await res.json();
        log('📦 Cache: ' + (stato.cache_salvata_il || '?'));
        applicaStato(stato);
    } catch (e) {
        log('❌ Nessuna cache');
        setTimeout(aggiornaDaAPI, 30000);
    }
}

function applicaStato(stato) {
    if (stato.banner) applicaBanner(stato.banner);
    const modalitaCambiata = !statoCorrente || statoCorrente.modalita !== stato.modalita;
    if (stato.modalita === 'tv') {
        if (modalitaCambiata) mostraTV();
        setTimeout(aggiornaDaAPI, 30000);
    } else if (stato.modalita === 'adv') {
        if (modalitaCambiata) mostraADV(stato);
        setTimeout(aggiornaDaAPI, 30000);
    }
    statoCorrente = stato;
}

// ─── AVVIO ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    aggiornaOrologio();
    setInterval(aggiornaOrologio, 1000);
    avviaSegnaleTV();
    setTimeout(aggiornaDaAPI, 500);
});
</script>

</body>
</html>