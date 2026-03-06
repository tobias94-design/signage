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
$club = $dispositivo['club'] ?? '';
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
        }
        #layer-tv video {
            width: 100%; height: 100%;
            object-fit: cover;
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

        /* Colonna corsi */
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
        }

        #corsi-lista {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4px 0;
        }

        .corso-item {
            padding: 14px 28px;
            border-bottom: 1px solid #1a1a1a;
            transition: all 0.3s;
        }

        .corso-item.passato {
            opacity: 0.3;
        }

        .corso-item.attivo {
            background: #1a0000;
            border-left: 4px solid #e94560;
            padding-left: 24px;
        }

        .corso-item.prossimo {
            opacity: 1;
        }

        .corso-orario {
            font-size: 26px;
            color: #aaa;
            font-weight: 300;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .corso-item.attivo .corso-orario {
            color: #e94560;
        }

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

        .corso-item.attivo .corso-indicatore {
            display: block;
        }

        #nessun-corso {
            padding: 40px 28px;
            color: #555;
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 2px;
            display: none;
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
        <div id="tv-placeholder" style="color:#222; font-size:32px;">📺 In attesa segnale TV...</div>
        <div id="layer-adv">
            <video id="adv-video" preload="auto" muted playsinline autoplay></video>
            <img id="adv-immagine" src="">
        </div>
    </div>

    <div id="colonna-corsi">
        <div id="corsi-header">In programma oggi</div>
        <div id="corsi-lista">
            <div id="nessun-corso">💪 Buon<br>allenamento!</div>
        </div>
    </div>
</div>

<!-- Banner -->
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
const SHEET_URL  = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vRMNctWV8FW0DlvPauHdGfKwAuF8wtFeSjYsazQnx9WVm1UDHcttFQTzytB66oRNg/pub?output=csv';

const GIORNI_IT = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
const MESI      = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                   'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

let statoCorrente   = null;
let advTimer        = null;
let indiceContenuto = 0;
let contenuti       = [];
let corsiOggi       = [];

// ─── OROLOGIO ────────────────────────────────────────────────
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

// ─── LOG ─────────────────────────────────────────────────────
function log(msg) {
    if (!DEBUG_MODE) return;
    const d = document.getElementById('debug');
    d.style.display = 'block';
    const ora = new Date().toLocaleTimeString();
    d.innerHTML = '[' + ora + '] ' + msg + '<br>' + d.innerHTML;
    const lines = d.innerHTML.split('<br>');
    if (lines.length > 10) d.innerHTML = lines.slice(0, 10).join('<br>');
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

// ─── CORSI ───────────────────────────────────────────────────
async function caricaCorsi() {
    try {
        const res  = await fetch(SHEET_URL + '&t=' + Date.now());
        const text = await res.text();
        const rows = text.trim().split('\n').slice(1);

        const oggi = GIORNI_IT[new Date().getDay()];

        const corsiAll = rows.map(row => {
            const cols = row.split(',');
            return {
                giorno: cols[0]?.trim().replace(/"/g, ''),
                orario: cols[1]?.trim().replace(/"/g, ''),
                corso:  cols[2]?.trim().replace(/"/g, ''),
                club:   cols[3]?.trim().replace(/"/g, '')
            };
        });

        corsiOggi = corsiAll.filter(c =>
            c.giorno === oggi &&
            (!CLUB || c.club.toLowerCase() === CLUB.toLowerCase())
        ).sort((a, b) => a.orario.localeCompare(b.orario));

        log('📅 Corsi oggi: ' + corsiOggi.length + ' club: ' + CLUB);
        aggiornaListaCorsi();
        setTimeout(caricaCorsi, 3600000);

    } catch (e) {
        log('⚠️ Errore corsi: ' + e.message);
        setTimeout(caricaCorsi, 60000);
    }
}

function aggiornaListaCorsi() {
    const lista   = document.getElementById('corsi-lista');
    const nessuno = document.getElementById('nessun-corso');

    if (!corsiOggi.length) {
        lista.innerHTML = '';
        nessuno.style.display = 'block';
        lista.appendChild(nessuno);
        return;
    }

    nessuno.style.display = 'none';

    const now    = new Date();
    const oraOra = String(now.getHours()).padStart(2,'0') + ':' +
                   String(now.getMinutes()).padStart(2,'0');

    let attivoIdx   = -1;
    let prossimoIdx = -1;

    corsiOggi.forEach((c, i) => {
        if (c.orario <= oraOra) attivoIdx = i;
        else if (prossimoIdx === -1) prossimoIdx = i;
    });

    let start = Math.max(0, attivoIdx - 1);
    let end   = Math.min(corsiOggi.length, start + 6);
    if (end - start < 6) start = Math.max(0, end - 6);

    lista.innerHTML = '';

    for (let i = start; i < end; i++) {
        const c   = corsiOggi[i];
        const div = document.createElement('div');
        div.className = 'corso-item';

        if (i === attivoIdx) {
            div.classList.add('attivo');
        } else if (i < attivoIdx) {
            div.classList.add('passato');
        } else {
            div.classList.add('prossimo');
        }

        div.innerHTML = `
            <div class="corso-orario">${c.orario}</div>
            <div class="corso-nome">${c.corso}</div>
            <div class="corso-indicatore">▶ IN CORSO</div>
        `;
        lista.appendChild(div);
    }
}

// ─── MOSTRA TV ───────────────────────────────────────────────
function mostraTV() {
    document.getElementById('layer-adv').style.display     = 'none';
    document.getElementById('colonna-corsi').style.display = 'flex';
    const video = document.getElementById('adv-video');
    video.pause();
    video.src = '';
    if (advTimer) { clearTimeout(advTimer); advTimer = null; }
    log('📺 Modalità TV attiva');
}

// ─── MOSTRA ADV ──────────────────────────────────────────────
function mostraADV(stato) {
    contenuti       = stato.contenuti || [];
    indiceContenuto = 0;

    if (stato.contenuto_ora) {
        const idx = contenuti.findIndex(c => c.id === stato.contenuto_ora.id);
        if (idx >= 0) indiceContenuto = idx;
    }

    document.getElementById('colonna-corsi').style.display = 'none';
    document.getElementById('layer-adv').style.display     = 'flex';

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
        const res   = await fetch(BASE_URL + 'api/stato.php?token=' + TOKEN + '&t=' + Date.now());
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
        setTimeout(aggiornaDaAPI, 15000);
    }
}

// ─── AVVIO ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    aggiornaOrologio();
    setInterval(aggiornaOrologio, 1000);
    setInterval(aggiornaListaCorsi, 60000);
    caricaCorsi();
    setTimeout(aggiornaDaAPI, 500);
});
</script>

</body>
</html>