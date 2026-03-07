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

        html, body {
            width: 100vw; height: 100vh;
            overflow: hidden; background: #000;
            font-family: 'Arial', sans-serif;
        }

        #player-root {
            position: absolute;
            width: 1920px; height: 1080px;
            top: 0; left: 0;
            transform-origin: top left;
            overflow: hidden; background: #000;
        }

        #layer-tv {
            position: absolute; top: 0; left: 0;
            width: 1920px; height: 1080px;
            background: #111; overflow: hidden;
        }
        #tv-video {
            position: absolute; top: 0; left: 0;
            width: 100%; height: 100%; object-fit: cover;
        }
        #tv-placeholder {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            color: #222; font-size: 32px; text-align: center;
        }

        /* ADV sempre 1920x1080 fissi */
        #layer-adv {
            position: absolute; top: 0; left: 0;
            width: 1920px; height: 1080px;
            background: #000; display: none; z-index: 20;
        }
        #adv-video {
            position: absolute; top: 0; left: 0;
            width: 1920px; height: 1080px;
            object-fit: contain;
        }
        #adv-immagine {
        position: absolute; top: 0; left: 0;
        width: 1920px; height: 1080px;
        object-fit: contain; /* ← era cover */
        display: none;
        }

        /* Banner sempre sopra tutto z-index 30 */
        #layer-banner {
            position: absolute;
            left: 0; right: 0; bottom: 0;
            height: 80px; background: #000;
            display: flex; align-items: center;
            z-index: 30; overflow: hidden;
            transition: background-color 0.3s;
        }
        #banner-logo-wrap { display:flex; align-items:center; justify-content:flex-start; flex-shrink:0; }
        #banner-logo { object-fit:contain; display:none; }
        .banner-sep { width:1px; background:rgba(255,255,255,0.3); align-self:stretch; margin:14px 0; flex-shrink:0; }
        #banner-data-centro { flex:1; text-align:center; font-weight:500; letter-spacing:2px; }
        #banner-ora-dx { font-weight:bold; letter-spacing:3px; flex-shrink:0; text-align:right; font-variant-numeric:tabular-nums; }
    </style>
</head>
<body>
<div id="player-root">

    <div id="layer-tv">
        <div id="tv-placeholder">📺 In attesa segnale TV...</div>
        <video id="tv-video" autoplay playsinline muted></video>
    </div>

    <div id="layer-adv">
        <video id="adv-video" preload="auto" muted playsinline autoplay></video>
        <img id="adv-immagine" src="">
    </div>

    <div id="layer-banner">
        <div id="banner-logo-wrap">
            <img id="banner-logo" src="">
        </div>
        <div class="banner-sep"></div>
        <div id="banner-data-centro"></div>
        <div class="banner-sep"></div>
        <div id="banner-ora-dx">--:--:--</div>
    </div>

</div>

<script>
const TOKEN    = '<?php echo htmlspecialchars($token); ?>';
const BASE_URL = '../';

const GIORNI_IT = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
const MESI      = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                   'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

let statoCorrente = null, advTimer = null, indiceContenuto = 0, contenuti = [];
let bannerColore = '#000000', bannerTestoColore = '#ffffff';

function adattaSchermo() {
    const scale = Math.min(window.innerWidth / 1920, window.innerHeight / 1080);
    const root  = document.getElementById('player-root');
    root.style.transform = 'scale(' + scale + ')';
    root.style.left = Math.round((window.innerWidth  - 1920 * scale) / 2) + 'px';
    root.style.top  = Math.round((window.innerHeight - 1080 * scale) / 2) + 'px';
}

function aggiornaOrologio() {
    const now  = new Date();
    const ora  = String(now.getHours()).padStart(2,'0') + ':' +
                 String(now.getMinutes()).padStart(2,'0') + ':' +
                 String(now.getSeconds()).padStart(2,'0');
    const data = GIORNI_IT[now.getDay()] + '  ' +
                 now.getDate() + ' ' + MESI[now.getMonth()] + ' ' + now.getFullYear();
    document.getElementById('banner-ora-dx').textContent      = ora;
    document.getElementById('banner-data-centro').textContent = data;
}

async function avviaSegnaleTV() {
    try {
        await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        const devices = await navigator.mediaDevices.enumerateDevices();
        const capture = devices.find(d => d.kind === 'videoinput');
        if (!capture) return;
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { deviceId: { exact: capture.deviceId } }, audio: true
        });
        const v = document.getElementById('tv-video');
        v.srcObject = stream; v.play();
        document.getElementById('tv-placeholder').style.display = 'none';
    } catch(e) {}
}

function applicaBanner(banner) {
    const el        = document.getElementById('layer-banner');
    const altezza   = parseInt(banner.banner_altezza) || 80;
    const posizione = banner.banner_posizione || 'bottom';

    bannerColore      = banner.banner_colore       || '#000000';
    bannerTestoColore = banner.banner_testo_colore || '#ffffff';

    el.style.backgroundColor = bannerColore;
    el.style.color            = bannerTestoColore;
    el.style.height           = altezza + 'px';
    el.style.padding          = '0 ' + Math.round(altezza * 0.25) + 'px';
    el.style.gap              = Math.round(altezza * 0.25) + 'px';

    const tvEl = document.getElementById('layer-tv');

    if (posizione === 'top') {
        el.style.top = '0'; el.style.bottom = 'auto';
        tvEl.style.top    = altezza + 'px';
        tvEl.style.height = (1080 - altezza) + 'px';
    } else {
        el.style.bottom = '0'; el.style.top = 'auto';
        tvEl.style.top    = '0';
        tvEl.style.height = (1080 - altezza) + 'px';
    }

    // ADV non viene toccato — sempre 1920x1080

    const logo = document.getElementById('banner-logo');
    if (banner.logo) {
        logo.src = BASE_URL + 'assets/img/' + banner.logo;
        logo.style.display = 'block';
        logo.style.height  = Math.round(altezza * 0.75) + 'px';
        logo.style.width   = 'auto';
    } else { logo.style.display = 'none'; }

    document.getElementById('banner-ora-dx').style.fontSize      = Math.round(altezza * 0.44) + 'px';
    document.getElementById('banner-data-centro').style.fontSize = Math.round(altezza * 0.28) + 'px';
    document.getElementById('banner-ora-dx').style.color         = bannerTestoColore;
    document.getElementById('banner-data-centro').style.color    = bannerTestoColore;
}

function mostraTV() {
    document.getElementById('layer-adv').style.display = 'none';
    document.getElementById('layer-tv').style.display  = 'block';
    const banner = document.getElementById('layer-banner');
    banner.style.backgroundColor = bannerColore;
    document.getElementById('banner-logo-wrap').style.visibility   = 'visible';
    document.getElementById('banner-data-centro').style.visibility = 'visible';
    document.getElementById('banner-ora-dx').style.opacity         = '1';
    document.getElementById('banner-ora-dx').style.color           = bannerTestoColore;
    document.querySelectorAll('.banner-sep').forEach(s => s.style.visibility = 'visible');
    const video = document.getElementById('adv-video');
    video.pause(); video.src = '';
    if (advTimer) { clearTimeout(advTimer); advTimer = null; }
}

function mostraADV(stato) {
    contenuti = [...(stato.contenuti || [])];
    indiceContenuto = 0;
    if (stato.contenuto_ora) {
        const idx = contenuti.findIndex(c => c.id === stato.contenuto_ora.id);
        if (idx >= 0) indiceContenuto = idx;
    }
    document.getElementById('layer-tv').style.display  = 'none';
    document.getElementById('layer-adv').style.display = 'block';
    // Banner trasparente, solo ora visibile semitrasparente
    const banner = document.getElementById('layer-banner');
    banner.style.backgroundColor = 'transparent';
    document.getElementById('banner-logo-wrap').style.visibility   = 'hidden';
    document.getElementById('banner-data-centro').style.visibility = 'hidden';
    document.getElementById('banner-ora-dx').style.opacity         = '0.45';
    document.getElementById('banner-ora-dx').style.color           = '#ffffff';
    document.querySelectorAll('.banner-sep').forEach(s => s.style.visibility = 'hidden');
    mostraContenuto(indiceContenuto);
}

function mostraContenuto(idx) {
    if (!contenuti.length) { mostraTV(); return; }
    idx = idx % contenuti.length; indiceContenuto = idx;
    const c = contenuti[idx];
    const video    = document.getElementById('adv-video');
    const immagine = document.getElementById('adv-immagine');
    const url = BASE_URL + 'uploads/' + c.file;
    if (c.tipo === 'video') {
        immagine.style.display = 'none'; video.style.display = 'block';
        video.muted = true; video.volume = 0; video.defaultMuted = true;
        video.setAttribute('muted', '');
        video.src = url; video.load();
        video.addEventListener('canplay', function h() {
            video.removeEventListener('canplay', h); video.play().catch(() => {});
        });
        video.onended = () => mostraContenuto(indiceContenuto + 1);
    } else {
        video.pause(); video.src = ''; video.style.display = 'none';
        immagine.style.display = 'block'; immagine.src = url;
        if (advTimer) clearTimeout(advTimer);
        advTimer = setTimeout(() => mostraContenuto(indiceContenuto + 1), c.durata * 1000);
    }
}

async function aggiornaDaAPI() {
    try {
        const res   = await fetch(BASE_URL + 'api/stato.php?token=' + TOKEN + '&t=' + Date.now());
        if (!res.ok) throw new Error();
        const stato = await res.json();
        if (stato.errore) { setTimeout(aggiornaDaAPI, 15000); return; }
        if (stato.banner) applicaBanner(stato.banner);
        const cambiata = !statoCorrente || statoCorrente.modalita !== stato.modalita;
        if (stato.modalita === 'tv') {
            if (cambiata) mostraTV();
            setTimeout(aggiornaDaAPI, Math.min((stato.secondi_alla_adv || 30) * 1000, 30000));
        } else if (stato.modalita === 'adv') {
            if (cambiata) mostraADV(stato);
            setTimeout(aggiornaDaAPI, Math.min((stato.secondi_alla_tv || 60) * 1000, 30000));
        }
        statoCorrente = stato;
    } catch(e) { setTimeout(aggiornaDaAPI, 15000); }
}

document.addEventListener('DOMContentLoaded', () => {
    adattaSchermo();
    window.addEventListener('resize', adattaSchermo);
    aggiornaOrologio();
    setInterval(aggiornaOrologio, 1000);
    avviaSegnaleTV();
    setTimeout(aggiornaDaAPI, 500);
});
</script>
</body>
</html>