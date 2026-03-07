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

        body {
            background: #000;
            width: 1920px;
            height: 1080px;
            overflow: hidden;
            font-family: 'Arial', sans-serif;
        }

        #main {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1080px;
            display: flex;
            flex-direction: row;
        }

        #layer-tv {
            flex: 1;
            background: #111;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        #tv-video { width: 100%; height: 100%; object-fit: cover; }

        #tv-placeholder {
            position: absolute;
            color: #222;
            font-size: 32px;
            text-align: center;
        }

        #layer-adv {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: #000;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }
        #adv-video { width: 100%; height: 100%; object-fit: contain; }
        #adv-immagine { width: 100%; height: 100%; object-fit: contain; display: none; }

        /* ── COLONNA CORSI ── */
        #colonna-corsi {
            width: 380px;
            background: #111;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-left: 2px solid #222;
        }

        #corsi-header {
            background: #1a1a1a;
            padding: 16px 28px;
            font-size: 20px;
            font-weight: bold;
            color: #aaa;
            letter-spacing: 2px;
            text-transform: uppercase;
            border-bottom: 1px solid #222;
            flex-shrink: 0;
        }

        #corsi-lista {
            display: none;
            flex-direction: column;
            justify-content: center;
            padding: 4px 0;
        }

        .corso-item {
            padding: 14px 28px;
            border-bottom: 1px solid #1a1a1a;
        }

        .corso-item.attivo {
            background: #1a0000;
            border-left: 4px solid #e94560;
            padding-left: 24px;
        }

        .corso-orario {
            font-size: 26px;
            color: #aaa;
            font-weight: 300;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .corso-item.attivo .corso-orario { color: #e94560; }

        .corso-nome {
            font-size: 32px;
            font-weight: bold;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .corso-indicatore {
            display: none;
            color: #e94560;
            font-size: 14px;
            margin-top: 4px;
            letter-spacing: 2px;
        }

        .corso-item.attivo .corso-indicatore { display: block; }

        /* ── BUON ALLENAMENTO ── */
        #buon-allenamento {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            flex: 1;
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, #000000 0%, #1a0000 25%, #8b0000 50%, #1a0000 75%, #000000 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.25;
            animation: float 20s infinite ease-in-out;
        }
        .orb-1 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, #e94560 0%, transparent 70%);
            top: -10%; left: -10%;
            animation-delay: 0s;
        }
        .orb-2 {
            width: 220px; height: 220px;
            background: radial-gradient(circle, #ff0000 0%, transparent 70%);
            bottom: -10%; right: -10%;
            animation-delay: 7s;
        }
        .orb-3 {
            width: 260px; height: 260px;
            background: radial-gradient(circle, #e94560 0%, transparent 70%);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: 14s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25%  { transform: translate(20px, -20px) scale(1.1); }
            50%  { transform: translate(-15px, 15px) scale(0.9); }
            75%  { transform: translate(15px, 20px) scale(1.05); }
        }

        .buon-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            transform: translateY(-120px);
        }

        .buon-linea {
            width: 40px;
            height: 3px;
            background: #e94560;
            margin: 14px auto;
            box-shadow: 0 0 10px #e94560;
        }

        .buon-testo {
            font-size: 30px;
            font-weight: bold;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 4px;
            line-height: 1.5;
            text-shadow: 0 2px 20px rgba(233, 69, 96, 0.5);
        }

        /* Banner */
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

<div id="main">
    <div id="layer-tv">
        <div id="tv-placeholder">📺 In attesa segnale TV...</div>
        <video id="tv-video" autoplay playsinline muted></video>
        <div id="layer-adv">
            <video id="adv-video" preload="auto" muted playsinline autoplay></video>
            <img id="adv-immagine" src="">
        </div>
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
const CLUB       = '<?php echo htmlspecialchars($club); ?>';
const BASE_URL   = '../';
const DEBUG_MODE = false;
const SHEET_URL  = '<?php echo htmlspecialchars($sheet_url); ?>';

const GIORNI_IT = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
const MESI      = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                   'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

let statoCorrente   = null;
let advTimer        = null;
let indiceContenuto = 0;
let contenuti       = [];
let corsiOggi       = [];

function aggiornaOrologio() {
    const now  = new Date();
    const ora  = String(now.getHours()).padStart(2,'0') + ':' +
                 String(now.getMinutes()).padStart(2,'0') + ':' +
                 String(now.getSeconds()).padStart(2,'0');
    const data = GIORNI_IT[now.getDay()] + '  ' + now.getDate() +
                 ' ' + MESI[now.getMonth()] + ' ' + now.getFullYear();
    document.getElementById('banner-ora').textContent  = ora;
    document.getElementById('banner-data').textContent = data;
}

function log(msg) {
    if (!DEBUG_MODE) return;
    const d = document.getElementById('debug');
    d.style.display = 'block';
    const ora = new Date().toLocaleTimeString();
    d.innerHTML = '[' + ora + '] ' + msg + '<br>' + d.innerHTML;
    const lines = d.innerHTML.split('<br>');
    if (lines.length > 10) d.innerHTML = lines.slice(0, 10).join('<br>');
}

async function avviaSegnaleTV() {
    try {
        await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        const devices = await navigator.mediaDevices.enumerateDevices();
        const capture = devices.find(d => d.kind === 'videoinput');
        if (!capture) return;
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { deviceId: { exact: capture.deviceId } },
            audio: true
        });
        const tvVideo = document.getElementById('tv-video');
        tvVideo.srcObject = stream;
        tvVideo.play();
        document.getElementById('tv-placeholder').style.display = 'none';
    } catch (e) {
        log('⚠️ Errore TV: ' + e.message);
    }
}

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
        document.getElementById('main').style.marginTop = altezza + 'px';
        document.getElementById('main').style.height    = (1080 - altezza) + 'px';
    } else {
        el.style.bottom = '0';
        el.style.top    = 'auto';
        document.getElementById('main').style.marginTop = '0';
        document.getElementById('main').style.height    = (1080 - altezza) + 'px';
    }
    const logo = document.getElementById('banner-logo');
    if (banner.logo) {
        logo.src = BASE_URL + 'assets/img/' + banner.logo;
        logo.style.display = 'block';
        logo.style.height  = Math.round(altezza * 0.78) + 'px';
        logo.style.width   = 'auto';
    } else {
        logo.style.display = 'none';
    }
    document.getElementById('banner-testo').textContent    = banner.banner_testo || '';
    document.getElementById('banner-testo').style.fontSize = Math.round(altezza * 0.22) + 'px';
    document.getElementById('banner-ora').style.fontSize   = Math.round(altezza * 0.42) + 'px';
    document.getElementById('banner-data').style.fontSize  = Math.round(altezza * 0.20) + 'px';
    document.getElementById('banner-datetime').style.color = banner.banner_testo_colore || '#fff';
}

async function caricaCorsi() {
    try {
        const res  = await fetch(SHEET_URL + '&t=' + Date.now());
        const text = await res.text();
        const rows = text.trim().split('\n').slice(1);
        const oggi = GIORNI_IT[new Date().getDay()];
        const corsiAll = rows.map(row => {
            const cols  = row.match(/(".*?"|[^",]+)(?=\s*,|\s*$)/g) || row.split(',');
            const clean = cols.map(c => c ? c.trim().replace(/^"|"$/g, '') : '');
            return {
                giorno: clean[0] || '',
                orario: clean[1] || '',
                corso:  clean[2] || '',
                club:   clean[3] || '',
                durata: parseInt(clean[4]) || 60
            };
        });
        const now    = new Date();
        const oraOra = now.getHours() * 60 + now.getMinutes();
        corsiOggi = corsiAll.filter(c => {
            if (c.giorno !== oggi) return false;
            if (CLUB && c.club.toLowerCase() !== CLUB.toLowerCase()) return false;
            const parti   = c.orario.split(':');
            const inizioM = parseInt(parti[0]) * 60 + parseInt(parti[1]);
            return (inizioM + c.durata) > oraOra;
        }).sort((a, b) => a.orario.localeCompare(b.orario));
        aggiornaListaCorsi();
        setTimeout(caricaCorsi, 3600000);
    } catch (e) {
        log('⚠️ Errore corsi: ' + e.message);
        setTimeout(caricaCorsi, 60000);
    }
}

function aggiornaListaCorsi() {
    const lista     = document.getElementById('corsi-lista');
    const buonAllen = document.getElementById('buon-allenamento');
    const header    = document.getElementById('corsi-header');
    const now       = new Date();
    const oraOra    = now.getHours() * 60 + now.getMinutes();

    const corsiFiltrati = corsiOggi.filter(c => {
        const parti   = c.orario.split(':');
        const inizioM = parseInt(parti[0]) * 60 + parseInt(parti[1]);
        return (inizioM + c.durata) > oraOra;
    });

    if (!corsiFiltrati.length) {
        lista.style.display     = 'none';
        header.style.display    = 'none';
        buonAllen.style.display = 'flex';
        return;
    }

    lista.style.display       = 'flex';
    lista.style.flexDirection = 'column';
    header.style.display      = 'block';
    buonAllen.style.display   = 'none';

    let attivoIdx = -1;
    corsiFiltrati.forEach((c, i) => {
        const parti   = c.orario.split(':');
        const inizioM = parseInt(parti[0]) * 60 + parseInt(parti[1]);
        const fineM   = inizioM + c.durata;
        if (inizioM <= oraOra && oraOra < fineM) attivoIdx = i;
    });

    let start = Math.max(0, attivoIdx >= 0 ? attivoIdx - 1 : 0);
    let end   = Math.min(corsiFiltrati.length, start + 5);
    if (end - start < 5) start = Math.max(0, end - 5);

    lista.innerHTML = '';
    for (let i = start; i < end; i++) {
        const c        = corsiFiltrati[i];
        const parti    = c.orario.split(':');
        const inizioM  = parseInt(parti[0]) * 60 + parseInt(parti[1]);
        const fineM    = inizioM + c.durata;
        const isAttivo = inizioM <= oraOra && oraOra < fineM;
        const div      = document.createElement('div');
        div.className  = 'corso-item' + (isAttivo ? ' attivo' : '');
        div.innerHTML  = `
            <div class="corso-orario">${c.orario}</div>
            <div class="corso-nome">${c.corso}</div>
            <div class="corso-indicatore">▶ IN CORSO</div>
        `;
        lista.appendChild(div);
    }
}

function mostraTV() {
    document.getElementById('layer-adv').style.display     = 'none';
    document.getElementById('colonna-corsi').style.display = 'flex';
    const video = document.getElementById('adv-video');
    video.pause(); video.src = '';
    if (advTimer) { clearTimeout(advTimer); advTimer = null; }
}

function mostraADV(stato) {
    contenuti       = stato.contenuti || [];
    indiceContenuto = 0;
    if (stato.contenuto_ora) {
        const idx = contenuti.findIndex(c => c.id === stato.contenuto_ora.id);
        if (idx >= 0) indiceContenuto = idx;
    }
    document.getElementById('colonna-corsi').style.display = 'none';
    document.getElementById('layer-adv').style.display     = 'flex';
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
    if (c.tipo === 'video') {
        immagine.style.display = 'none';
        video.style.display    = 'block';
        video.muted = true; video.volume = 0;
        video.defaultMuted = true;
        video.setAttribute('muted', '');
        video.src = url; video.load();
        video.addEventListener('canplay', function handler() {
            video.removeEventListener('canplay', handler);
            video.play().catch(() => {});
        });
        video.onended = () => mostraContenuto(indiceContenuto + 1);
    } else {
        video.pause(); video.src = '';
        video.style.display    = 'none';
        immagine.style.display = 'block';
        immagine.src           = url;
        if (advTimer) clearTimeout(advTimer);
        advTimer = setTimeout(() => mostraContenuto(indiceContenuto + 1), c.durata * 1000);
    }
}

async function aggiornaDaAPI() {
    try {
        const res   = await fetch(BASE_URL + 'api/stato.php?token=' + TOKEN + '&t=' + Date.now());
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const stato = await res.json();
        if (stato.errore) { setTimeout(aggiornaDaAPI, 15000); return; }
        if (stato.banner) applicaBanner(stato.banner);
        const modalitaCambiata = !statoCorrente || statoCorrente.modalita !== stato.modalita;
        if (stato.modalita === 'tv') {
            if (modalitaCambiata) mostraTV();
            setTimeout(aggiornaDaAPI, Math.min((stato.secondi_alla_adv || 30) * 1000, 30000));
        } else if (stato.modalita === 'adv') {
            if (modalitaCambiata) mostraADV(stato);
            setTimeout(aggiornaDaAPI, Math.min((stato.secondi_alla_tv || 60) * 1000, 30000));
        }
        statoCorrente = stato;
    } catch (e) {
        setTimeout(aggiornaDaAPI, 15000);
    }
}

document.addEventListener('DOMContentLoaded', () => {
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