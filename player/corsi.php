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
$club      = $dispositivo['club'] ?? '';
$sheet_url = $dispositivo['sheet_url'] ?? '';
if (!$sheet_url) {
    $sheet_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vRMNctWV8FW0DlvPauHdGfKwAuF8wtFeSjYsazQnx9WVm1UDHcttFQTzytB66oRNg/pub?output=csv';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Player Corsi</title>
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
            overflow: hidden;
            background: #000;
        }

        #main {
            position: absolute;
            top: 0; left: 0;
            width: 1920px; height: 1080px;
            display: flex;
            flex-direction: row;
        }

        #layer-tv {
            flex: 1;
            background: #111;
            position: relative;
            overflow: hidden;
        }
        #tv-video {
            position: absolute; top: 0; left: 0;
            width: 100%; height: 100%;
            object-fit: cover;
        }
        #tv-placeholder {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            color: #222; font-size: 32px; text-align: center;
        }

        /* ADV sempre 1920x1080 fissi, sopra tutto tranne banner */
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

        /* Colonna corsi */
        #colonna-corsi {
            width: 380px; background: #111;
            display: flex; flex-direction: column;
            overflow: hidden; border-left: 2px solid #222;
        }
        #corsi-header {
            background: #1a1a1a; padding: 16px 28px;
            font-size: 20px; font-weight: bold; color: #aaa;
            letter-spacing: 2px; text-transform: uppercase;
            border-bottom: 1px solid #222; flex-shrink: 0;
        }
        #corsi-lista {
            display: none; flex-direction: column;
            justify-content: center; padding: 4px 0;
        }
        .corso-item { padding: 14px 28px; border-bottom: 1px solid #1a1a1a; }
        .corso-item.attivo { background: #1a0000; border-left: 4px solid #e94560; padding-left: 24px; }
        .corso-orario { font-size: 26px; color: #aaa; font-weight: 300; letter-spacing: 1px; margin-bottom: 4px; }
        .corso-item.attivo .corso-orario { color: #e94560; }
        .corso-nome { font-size: 32px; font-weight: bold; color: #fff; text-transform: uppercase; letter-spacing: 1px; }
        .corso-indicatore { display: none; color: #e94560; font-size: 14px; margin-top: 4px; letter-spacing: 2px; }
        .corso-item.attivo .corso-indicatore { display: block; }

        /* Buon allenamento */
        #buon-allenamento {
            display: none; flex-direction: column;
            align-items: center; justify-content: center;
            text-align: center; flex: 1;
            overflow: hidden; position: relative;
            background: linear-gradient(135deg, #000 0%, #1a0000 25%, #8b0000 50%, #1a0000 75%, #000 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }
        @keyframes gradientShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .orb { position: absolute; border-radius: 50%; filter: blur(60px); opacity: 0.25; animation: orbFloat 20s infinite ease-in-out; }
        .orb-1 { width:300px; height:300px; background:radial-gradient(circle,#e94560 0%,transparent 70%); top:-10%; left:-10%; }
        .orb-2 { width:220px; height:220px; background:radial-gradient(circle,#ff0000 0%,transparent 70%); bottom:-10%; right:-10%; animation-delay:7s; }
        .orb-3 { width:260px; height:260px; background:radial-gradient(circle,#e94560 0%,transparent 70%); top:50%; left:50%; transform:translate(-50%,-50%); animation-delay:14s; }
        @keyframes orbFloat {
            0%,100% { transform:translate(0,0) scale(1); }
            25%  { transform:translate(20px,-20px) scale(1.1); }
            50%  { transform:translate(-15px,15px) scale(0.9); }
            75%  { transform:translate(15px,20px) scale(1.05); }
        }
        .buon-content { position:relative; z-index:2; display:flex; flex-direction:column; align-items:center; transform:translateY(-120px); }
        .buon-linea { width:40px; height:3px; background:#e94560; margin:14px auto; box-shadow:0 0 10px #e94560; }
        .buon-testo { font-size:30px; font-weight:bold; color:#fff; text-transform:uppercase; letter-spacing:4px; line-height:1.5; text-shadow:0 2px 20px rgba(233,69,96,0.5); }

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

    <div id="main">
        <div id="layer-tv">
            <div id="tv-placeholder">📺 In attesa segnale TV...</div>
            <video id="tv-video" autoplay playsinline muted></video>
        </div>
        <div id="colonna-corsi">
            <div id="corsi-header">In programma oggi</div>
            <div id="corsi-lista"></div>
            <div id="buon-allenamento">
                <div class="orb orb-1"></div>
                <div class="orb orb-2"></div>
                <div class="orb orb-3"></div>
                <div class="buon-content">
                    <div class="buon-linea"></div>
                    <div class="buon-testo">Buon<br>allenamento!</div>
                    <div class="buon-linea"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ADV fullscreen sotto il banner -->
    <div id="layer-adv">
        <video id="adv-video" preload="auto" muted playsinline autoplay></video>
        <img id="adv-immagine" src="">
    </div>

    <!-- Banner sempre sopra tutto -->
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
const TOKEN     = '<?php echo htmlspecialchars($token); ?>';
const CLUB      = '<?php echo htmlspecialchars($club); ?>';
const BASE_URL  = '../';
const SHEET_URL = '<?php echo htmlspecialchars($sheet_url); ?>';

const GIORNI_IT = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
const MESI      = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                   'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

let statoCorrente = null, advTimer = null, indiceContenuto = 0;
let contenuti = [], corsiOggi = [];
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

    const mainEl = document.getElementById('main');

    if (posizione === 'top') {
        el.style.top = '0'; el.style.bottom = 'auto';
        mainEl.style.top    = altezza + 'px';
        mainEl.style.height = (1080 - altezza) + 'px';
    } else {
        el.style.bottom = '0'; el.style.top = 'auto';
        mainEl.style.top    = '0';
        mainEl.style.height = (1080 - altezza) + 'px';
    }

    // ADV non viene mai toccato — sempre 1920x1080 fissi

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
    document.getElementById('main').style.display      = 'flex';
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
    document.getElementById('main').style.display      = 'none';
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

async function caricaCorsi() {
    try {
        const res  = await fetch(SHEET_URL + '&t=' + Date.now());
        const text = await res.text();
        const rows = text.trim().split('\n').slice(1);
        const oggi = GIORNI_IT[new Date().getDay()];
        const oraOra = new Date().getHours() * 60 + new Date().getMinutes();

        const corsiAll = rows.map(row => {
            const cols  = row.match(/(".*?"|[^",]+)(?=\s*,|\s*$)/g) || row.split(',');
            const clean = cols.map(c => c ? c.trim().replace(/^"|"$/g, '') : '');
            return { giorno:clean[0]||'', orario:clean[1]||'', corso:clean[2]||'', club:clean[3]||'', durata:parseInt(clean[4])||60 };
        });

        corsiOggi = corsiAll.filter(c => {
            if (c.giorno !== oggi) return false;
            if (CLUB && c.club.toLowerCase() !== CLUB.toLowerCase()) return false;
            const p = c.orario.split(':');
            return (parseInt(p[0]) * 60 + parseInt(p[1]) + c.durata) > oraOra;
        }).sort((a, b) => a.orario.localeCompare(b.orario));

        aggiornaListaCorsi();
        setTimeout(caricaCorsi, 3600000);
    } catch(e) { setTimeout(caricaCorsi, 60000); }
}

function aggiornaListaCorsi() {
    const lista     = document.getElementById('corsi-lista');
    const buonAllen = document.getElementById('buon-allenamento');
    const header    = document.getElementById('corsi-header');
    const oraOra    = new Date().getHours() * 60 + new Date().getMinutes();

    const corsiFiltrati = corsiOggi.filter(c => {
        const p = c.orario.split(':');
        return (parseInt(p[0]) * 60 + parseInt(p[1]) + c.durata) > oraOra;
    });

    if (!corsiFiltrati.length) {
        lista.style.display = 'none'; header.style.display = 'none';
        buonAllen.style.display = 'flex'; return;
    }

    lista.style.display = 'flex'; lista.style.flexDirection = 'column';
    header.style.display = 'block'; buonAllen.style.display = 'none';

    let attivoIdx = -1;
    corsiFiltrati.forEach((c, i) => {
        const p = c.orario.split(':'), s = parseInt(p[0]) * 60 + parseInt(p[1]);
        if (s <= oraOra && oraOra < s + c.durata) attivoIdx = i;
    });

    let start = Math.max(0, attivoIdx >= 0 ? attivoIdx - 1 : 0);
    let end   = Math.min(corsiFiltrati.length, start + 5);
    if (end - start < 5) start = Math.max(0, end - 5);

    lista.innerHTML = '';
    for (let i = start; i < end; i++) {
        const c = corsiFiltrati[i];
        const p = c.orario.split(':'), s = parseInt(p[0]) * 60 + parseInt(p[1]);
        const attivo = s <= oraOra && oraOra < s + c.durata;
        const div = document.createElement('div');
        div.className = 'corso-item' + (attivo ? ' attivo' : '');
        div.innerHTML = `<div class="corso-orario">${c.orario}</div>
                         <div class="corso-nome">${c.corso}</div>
                         <div class="corso-indicatore">▶ IN CORSO</div>`;
        lista.appendChild(div);
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
    setInterval(aggiornaListaCorsi, 60000);
    avviaSegnaleTV();
    caricaCorsi();
    setTimeout(aggiornaDaAPI, 500);
});
</script>
</body>
</html>