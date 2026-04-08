<?php
header('X-Frame-Options: SAMEORIGIN');
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

$token = $_GET['token'] ?? '';
if (!$token) {
    $primo = $db->query('SELECT token FROM dispositivi LIMIT 1')->fetch();
    $token = $primo['token'] ?? '';
}

$stmt = $db->prepare('SELECT d.*, p.nome as profilo_nome, p.banner_colore, p.banner_testo_colore, p.banner_posizione, p.banner_altezza, p.logo, COALESCE(p.logo_size, 75) as logo_size, COALESCE(p.data_size, 28) as data_size, COALESCE(p.ora_size, 44) as ora_size FROM dispositivi d LEFT JOIN profili p ON p.id = d.profilo_id WHERE d.token = ?');
$stmt->execute([$token]);
$dispositivo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dispositivo) die('<body style="background:#000;color:#e94560;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;">Dispositivo non trovato</body>');

$club       = $dispositivo['club'] ?? '';
$sheet_url  = $dispositivo['sheet_url'] ?? '';
$stream_url = $dispositivo['stream_url'] ?? '';

$BANNER_H   = (int)($dispositivo['banner_altezza'] ?? 80);
$BANNER_POS = $dispositivo['banner_posizione'] ?? 'bottom';
$LOGO_SIZE  = (int)($dispositivo['logo_size'] ?? 75);
$DATA_SIZE  = (int)($dispositivo['data_size'] ?? 28);
$ORA_SIZE   = (int)($dispositivo['ora_size'] ?? 44);
$MAIN_H     = 1080 - $BANNER_H;
$MAIN_TOP   = $BANNER_POS === 'top' ? $BANNER_H : 0;
$SIDEBAR_W  = (int)round(1920 - ($MAIN_H * 16 / 9));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>PixelBridge</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.4.12/hls.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; cursor: none !important; }
        html, body { width: 100vw; height: 100vh; overflow: hidden; background: #000; font-family: 'Figtree', sans-serif; }
        #player-root { position: absolute; width: 1920px; height: 1080px; top: 0; left: 0; transform-origin: top left; overflow: hidden; background: #000; }
        #main { position: absolute; top: <?= $MAIN_TOP ?>px; left: 0; width: 1920px; height: <?= $MAIN_H ?>px; display: flex; flex-direction: row; }
        #layer-tv { flex: 1; background: #111; position: relative; overflow: hidden; }
        #tv-video { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: fill; }
        #tv-placeholder { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #222; font-size: 32px; text-align: center; }
        #layer-adv { position: absolute; top: 0; left: 0; width: 1920px; height: 1080px; background: #000; display: none; z-index: 20; }
        #adv-video { position: absolute; top: 0; left: 0; width: 1920px; height: 1080px; object-fit: contain; }
        #adv-immagine { position: absolute; top: 0; left: 0; width: 1920px; height: 1080px; object-fit: contain; display: none; }
        #colonna-corsi { width: <?= $SIDEBAR_W ?>px; background: #111; display: flex; flex-direction: column; overflow: hidden; border-left: 2px solid #222; position: relative; }
        #sidebar-widget { position: absolute; inset: 0; display: flex; flex-direction: column; transition: opacity 0.6s ease; }
        #sidebar-widget.fade-out { opacity: 0; }
        #sidebar-widget.fade-in  { opacity: 1; }
        .widget-sfondo { position: absolute; inset: 0; background-size: cover; background-position: center; z-index: 0; }
        .widget-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.45); z-index: 1; }
        .widget-content { position: relative; z-index: 2; height: 100%; display: flex; flex-direction: column; padding: 28px 28px 20px; }
        .widget-header { font-size: 16px; letter-spacing: 3px; text-transform: uppercase; color: rgba(255,255,255,0.55); font-weight: 400; border-bottom: 1px solid rgba(255,255,255,0.15); padding-bottom: 14px; margin-bottom: 20px; flex-shrink: 0; }
        .widget-countdown { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 10px; }
        .countdown-pre { font-size: 18px; opacity: 0.75; text-align: center; margin-bottom: 8px; font-weight: 300; }
        .countdown-titolo { font-size: 28px; font-weight: bold; text-align: center; line-height: 1.3; }
        .countdown-numeri { display: flex; gap: 16px; justify-content: center; margin-top: 10px; }
        .countdown-blocco { text-align: center; }
        .countdown-num { font-size: 64px; font-weight: 900; line-height: 1; letter-spacing: -2px; }
        .countdown-label { font-size: 14px; letter-spacing: 3px; text-transform: uppercase; opacity: 0.6; margin-top: 4px; }
        .widget-meteo { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 8px; }
        .meteo-icona { font-size: 80px; line-height: 1; }
        .meteo-temp { font-size: 72px; font-weight: 900; letter-spacing: -2px; }
        .meteo-desc { font-size: 22px; opacity: 0.75; text-transform: capitalize; text-align: center; }
        .meteo-citta { font-size: 18px; opacity: 0.55; letter-spacing: 2px; margin-top: 8px; }
        .meteo-dettagli { display: flex; gap: 24px; margin-top: 12px; font-size: 16px; opacity: 0.65; }
        .widget-info { flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 16px; }
        .info-icona { font-size: 56px; line-height: 1; }
        .info-testo { font-size: 26px; line-height: 1.5; font-weight: 300; }
        #layer-banner { position: absolute; left: 0; right: 0; <?= $BANNER_POS === 'top' ? 'top: 0;' : 'bottom: 0;' ?> height: <?= $BANNER_H ?>px; background: #000; display: flex; align-items: center; z-index: 30; overflow: hidden; transition: background-color 0.3s; }
        #banner-logo-wrap { display:flex; align-items:center; justify-content:flex-start; flex-shrink:0; }
        #banner-logo { object-fit:contain; display:none; }
        .banner-sep { width:1px; background:rgba(255,255,255,0.3); align-self:stretch; margin:14px 0; flex-shrink:0; }
        #banner-data-centro { flex:1; text-align:center; font-weight:500; letter-spacing:2px; }
        #banner-ora-dx { font-weight:bold; letter-spacing:3px; flex-shrink:0; text-align:right; font-variant-numeric:tabular-nums; transition: all 0.3s; }
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
            <div id="sidebar-widget">
                <div class="widget-sfondo" id="widget-sfondo"></div>
                <div class="widget-overlay" id="widget-overlay"></div>
                <div class="widget-content" id="widget-content"></div>
            </div>
        </div>
    </div>
    <div id="layer-adv">
        <video id="adv-video" preload="auto" muted playsinline autoplay></video>
        <img id="adv-immagine" src="">
    </div>
    <div id="layer-banner">
        <div id="banner-logo-wrap"><img id="banner-logo" src=""></div>
        <div class="banner-sep"></div>
        <div id="banner-data-centro"></div>
        <div class="banner-sep"></div>
        <div id="banner-ora-dx">--:--:--</div>
    </div>
</div>

<script>
const TOKEN     = '<?php echo htmlspecialchars($token); ?>';
const CLUB      = '<?php echo htmlspecialchars($club); ?>';
const BASE_URL  = '/';
const SHEET_URL = '<?php echo htmlspecialchars($sheet_url); ?>';

const GIORNI_IT = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
const MESI      = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

let statoCorrente = null, advTimer = null, indiceContenuto = 0;
let contenuti = [], corsiOggi = [];
let bannerColore = '#000000', bannerTestoColore = '#ffffff';
let modalitaAttuale = 'tv';

let sidebarSlides = [];
let sidebarIndice = 0;
let sidebarTimer  = null;
let meteoCache    = {};
let countdownIntervals = {};

// Fingerprint per evitare restart carousel inutili
// Usa solo id+tipo+attivo — ignora campi che cambiano spesso
function slidesFingerprint(slides) {
    return slides.map(s => s.id + ':' + s.tipo + ':' + (s.attivo ? 1 : 0)).join('|');
}
let slidesFingerpr = '';

var PRESETS = {
    'dark_red': 'linear-gradient(135deg,#000 0%,#1a0000 40%,#8b0000 100%)',
    'midnight': 'linear-gradient(135deg,#0a0a1a 0%,#0f3460 100%)',
    'purple':   'linear-gradient(135deg,#1a0030 0%,#4a0080 100%)',
    'forest':   'linear-gradient(135deg,#001a0a 0%,#004d20 100%)',
    'gold':     'linear-gradient(135deg,#1a1200 0%,#4d3800 100%)',
    'carbon':   'linear-gradient(135deg,#111 0%,#2a2a2a 100%)',
};

// ── SIDEBAR CAROUSEL ─────────────────────────────────────────────
function avviaSidebar(slides) {
    if (sidebarTimer) { clearTimeout(sidebarTimer); sidebarTimer = null; }
    Object.keys(countdownIntervals).forEach(id => {
        clearInterval(countdownIntervals[id]);
        delete countdownIntervals[id];
    });
    if (!slides || !slides.length) {
        sidebarSlides = [{
            id: 'default-corsi', tipo: 'corsi', titolo: 'In programma oggi',
            durata: 30, colore_sfondo: '#111111', colore_testo: '#ffffff',
            sfondo: '', sfondo_preset: '', contenuto: '{}', attivo: 1
        }];
    } else {
        sidebarSlides = slides;
    }
    sidebarIndice = 0;
    mostraSlide(0);
}

function mostraSlide(idx) {
    if (!sidebarSlides.length) return;
    idx = idx % sidebarSlides.length;
    let tentativi = 0;
    while (tentativi < sidebarSlides.length && (!sidebarSlides[idx] || !sidebarSlides[idx].attivo)) {
        idx = (idx + 1) % sidebarSlides.length;
        tentativi++;
    }
    if (tentativi >= sidebarSlides.length) {
        setTimeout(() => aggiornaDaAPI(), 500);
        return;
    }
    sidebarIndice = idx;
    const slide = sidebarSlides[idx];
    const cfg = (() => { try { return JSON.parse(slide.contenuto || '{}'); } catch(e) { return {}; } })();

    const widget    = document.getElementById('sidebar-widget');
    const sfondoEl  = document.getElementById('widget-sfondo');
    const overlayEl = document.getElementById('widget-overlay');
    const contentEl = document.getElementById('widget-content');

    widget.classList.add('fade-out');
    setTimeout(() => {
        const colTesto = slide.colore_testo || '#ffffff';
        if (slide.sfondo) {
            sfondoEl.style.cssText = `position:absolute;inset:0;background-size:cover;background-position:center;z-index:0;background-image:url('${BASE_URL}uploads/${slide.sfondo}')`;
            overlayEl.style.display = 'block';
        } else if (slide.sfondo_preset && PRESETS[slide.sfondo_preset]) {
            sfondoEl.style.cssText = `position:absolute;inset:0;background-size:cover;background-position:center;z-index:0;background:${PRESETS[slide.sfondo_preset]}`;
            overlayEl.style.display = 'none';
        } else {
            sfondoEl.style.cssText = `position:absolute;inset:0;z-index:0;background:${slide.colore_sfondo || '#111111'}`;
            overlayEl.style.display = 'none';
        }
        contentEl.style.color = colTesto;
        switch (slide.tipo) {
            case 'corsi':     renderCorsi(contentEl, slide, colTesto); break;
            case 'countdown': renderCountdown(contentEl, slide, cfg, colTesto); break;
            case 'meteo':     renderMeteo(contentEl, slide, cfg, colTesto); break;
            case 'info':      renderInfo(contentEl, slide, cfg, colTesto); break;
            default:          renderInfo(contentEl, slide, cfg, colTesto);
        }
        widget.classList.remove('fade-out');
        widget.classList.add('fade-in');
        if (sidebarTimer) clearTimeout(sidebarTimer);
        sidebarTimer = setTimeout(() => mostraSlide(sidebarIndice + 1), (parseInt(slide.durata)||10) * 1000);
    }, 600);
}

// ── RENDER CORSI ─────────────────────────────────────────────────
function renderCorsi(el, slide, colTesto) {
    const oraOra = new Date().getHours() * 60 + new Date().getMinutes();
    const filtrati = corsiOggi.filter(c => {
        const p = c.orario.split(':');
        return (parseInt(p[0]) * 60 + parseInt(p[1]) + c.durata) > oraOra;
    });
    let html = `<div class="widget-header">${slide.titolo || 'In programma oggi'}</div>`;
    if (!filtrati.length) {
        html += `<div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;">
            <div style="font-size:48px;">💪</div>
            <div style="font-size:28px;font-weight:bold;text-align:center;line-height:1.4;">Buon<br>allenamento!</div>
        </div>`;
    } else {
        let start = 0, attivoIdx = -1;
        filtrati.forEach((c, i) => {
            const p = c.orario.split(':'), s = parseInt(p[0])*60 + parseInt(p[1]);
            if (s <= oraOra && oraOra < s + c.durata) attivoIdx = i;
        });
        start = Math.max(0, attivoIdx >= 0 ? attivoIdx - 1 : 0);
        const end = Math.min(filtrati.length, start + 5);
        if (end - start < 5) start = Math.max(0, end - 5);
        html += `<div style="flex:1;display:flex;flex-direction:column;justify-content:center;gap:0;">`;
        for (let i = start; i < end; i++) {
            const c = filtrati[i];
            const p = c.orario.split(':'), s = parseInt(p[0])*60 + parseInt(p[1]);
            const attivo = s <= oraOra && oraOra < s + c.durata;
            const borderStyle = attivo ? `border-left:4px solid #e94560; padding-left:12px; background:rgba(233,69,96,0.08);` : 'padding-left:16px;';
            html += `<div style="padding:12px 0; border-bottom:1px solid rgba(255,255,255,0.08); ${borderStyle}">
                <div style="font-size:24px;opacity:${attivo?'1':'0.6'};font-weight:300;color:${attivo?'#e94560':colTesto};">${c.orario}</div>
                <div style="font-size:28px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;color:${attivo?'#e94560':colTesto};">${c.corso}</div>
                ${attivo ? `<div style="font-size:12px;letter-spacing:2px;color:#e94560;margin-top:2px;">▶ IN CORSO</div>` : ''}
            </div>`;
        }
        html += `</div>`;
    }
    el.innerHTML = html;
}

// ── RENDER COUNTDOWN ─────────────────────────────────────────────
function renderCountdown(el, slide, cfg, colTesto) {
    const titolo = slide.titolo || 'Prossimo evento';
    const dataTarget = cfg.data_target ? new Date(cfg.data_target) : null;
    const messaggioPre = cfg.messaggio_pre || '';
    const autoDisable = cfg.auto_disable || 0;
    const slideId = slide.id;

    if (countdownIntervals[slideId]) {
        clearInterval(countdownIntervals[slideId]);
        delete countdownIntervals[slideId];
    }
    el.innerHTML = `<div class="widget-header">${titolo}</div>
                    <div class="widget-countdown" id="countdown-content-${slideId}"></div>`;

    function aggiorna() {
        const content = document.getElementById('countdown-content-' + slideId);
        if (!content) {
            if (countdownIntervals[slideId]) { clearInterval(countdownIntervals[slideId]); delete countdownIntervals[slideId]; }
            return;
        }
        if (!dataTarget) { content.innerHTML = `<div class="countdown-titolo">Evento configurato</div>`; return; }
        const diff = dataTarget - new Date();
        if (diff <= 0) {
            if (countdownIntervals[slideId]) { clearInterval(countdownIntervals[slideId]); delete countdownIntervals[slideId]; }
            if (autoDisable) {
                content.innerHTML = `<div class="countdown-titolo" style="font-size:32px;">✓ Evento in corso</div>`;
                fetch(BASE_URL + 'api/stato.php?token=' + TOKEN + '&action=disable_slide&id=' + slideId)
                    .then(r => r.json())
                    .then(() => setTimeout(() => aggiornaDaAPI(), 2000))
                    .catch(() => setTimeout(() => mostraSlide(sidebarIndice + 1), 1000));
            } else {
                content.innerHTML = `<div class="countdown-titolo" style="font-size:32px;">Evento in corso!</div>`;
            }
            return;
        }
        const giorni = Math.floor(diff / 86400000);
        const ore    = Math.floor((diff % 86400000) / 3600000);
        const minuti = Math.floor((diff % 3600000) / 60000);
        const sec    = Math.floor((diff % 60000) / 1000);
        let blocchi = '';
        if (giorni > 0) blocchi += `<div class="countdown-blocco"><div class="countdown-num">${String(giorni).padStart(2,'0')}</div><div class="countdown-label">Giorni</div></div>`;
        blocchi += `
            <div class="countdown-blocco"><div class="countdown-num">${String(ore).padStart(2,'0')}</div><div class="countdown-label">Ore</div></div>
            <div class="countdown-blocco"><div class="countdown-num">${String(minuti).padStart(2,'0')}</div><div class="countdown-label">Min</div></div>
            <div class="countdown-blocco"><div class="countdown-num">${String(sec).padStart(2,'0')}</div><div class="countdown-label">Sec</div></div>`;
        content.innerHTML = `
            ${messaggioPre ? `<div class="countdown-pre">${messaggioPre}</div>` : ''}
            <div class="countdown-titolo">${titolo}</div>
            <div class="countdown-numeri">${blocchi}</div>`;
    }
    aggiorna();
    countdownIntervals[slideId] = setInterval(aggiorna, 1000);
}

// ── RENDER METEO ─────────────────────────────────────────────────
const WMO_ICONS = {0:'☀️',1:'🌤️',2:'⛅',3:'☁️',45:'🌫️',48:'🌫️',51:'🌦️',53:'🌦️',55:'🌧️',61:'🌧️',63:'🌧️',65:'🌧️',71:'❄️',73:'❄️',75:'❄️',80:'🌦️',81:'🌧️',82:'⛈️',95:'⛈️',96:'⛈️',99:'⛈️'};
const WMO_DESC  = {0:'Sereno',1:'Prevalenz. sereno',2:'Parz. nuvoloso',3:'Nuvoloso',45:'Nebbia',51:'Pioggerella',61:'Pioggia',65:'Pioggia intensa',71:'Neve',80:'Rovesci',95:'Temporale'};

async function fetchMeteo(citta, lat, lon) {
    const key = citta || `${lat},${lon}`;
    if (meteoCache[key] && (Date.now() - meteoCache[key].ts < 1800000)) return meteoCache[key].data;
    try {
        let latitude = lat, longitude = lon;
        if (!latitude || !longitude) {
            const geo = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(citta)}&count=1&language=it`);
            const geoData = await geo.json();
            if (!geoData.results?.length) return null;
            latitude = geoData.results[0].latitude; longitude = geoData.results[0].longitude;
        }
        const res  = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${latitude}&longitude=${longitude}&current=temperature_2m,weathercode,windspeed_10m,relativehumidity_2m&daily=weathercode,temperature_2m_max,temperature_2m_min&timezone=auto&forecast_days=4`);
        const data = await res.json();
        meteoCache[key] = { ts: Date.now(), data };
        return data;
    } catch(e) { return null; }
}

async function renderMeteo(el, slide, cfg, colTesto) {
    const titolo = slide.titolo || cfg.citta || 'Meteo';
    el.innerHTML = `<div class="widget-header">${titolo}</div><div class="widget-meteo"><div style="font-size:40px;opacity:0.5;">Caricamento...</div></div>`;
    const data = await fetchMeteo(cfg.citta, cfg.lat, cfg.lon);
    if (!data || !data.current) {
        el.innerHTML = `<div class="widget-header">${titolo}</div><div class="widget-meteo"><div style="font-size:24px;opacity:0.5;">Dati non disponibili</div></div>`;
        return;
    }
    const cur = data.current;
    const wmo = cur.weathercode;
    let previsioni = '';
    if (data.daily && data.daily.time) {
        const giorni = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];
        for (let i = 1; i <= 3 && i < data.daily.time.length; i++) {
            const d = new Date(data.daily.time[i]);
            const wmo_day = data.daily.weathercode[i];
            const min = Math.round(data.daily.temperature_2m_min[i]);
            const max = Math.round(data.daily.temperature_2m_max[i]);
            previsioni += `<div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;font-size:14px;">
                <span style="min-width:35px;">${giorni[d.getDay()]}</span>
                <span style="font-size:20px;margin:0 8px;">${WMO_ICONS[wmo_day]||'🌡️'}</span>
                <span style="opacity:0.65;font-size:13px;">Min${min}° Max${max}°</span>
            </div>`;
        }
    }
    el.innerHTML = `
        <div class="widget-header">${titolo}</div>
        <div class="widget-meteo">
            <div class="meteo-icona">${WMO_ICONS[wmo]||'🌡️'}</div>
            <div class="meteo-temp">${Math.round(cur.temperature_2m)}°C</div>
            <div class="meteo-desc">${WMO_DESC[wmo]||''}</div>
            <div class="meteo-citta">${cfg.citta||''}</div>
            <div class="meteo-dettagli"><span>💧 ${cur.relativehumidity_2m}%</span><span>💨 ${Math.round(cur.windspeed_10m)} km/h</span></div>
        </div>
        ${previsioni ? `
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.15);">
            <div style="font-size:11px;opacity:0.5;letter-spacing:2px;text-transform:uppercase;margin-bottom:12px;">Prossimi 3 giorni</div>
            ${previsioni}
        </div>` : ''}`;
}

// ── RENDER INFO ───────────────────────────────────────────────────
function renderInfo(el, slide, cfg, colTesto) {
    const titolo = slide.titolo || '';
    const icona  = cfg.icona || 'ℹ️';
    const testo  = cfg.testo || '';
    el.innerHTML = `
        ${titolo ? `<div class="widget-header">${titolo}</div>` : ''}
        <div class="widget-info" style="${!titolo ? 'justify-content:center;flex:1;' : ''}">
            <div class="info-icona">${icona}</div>
            <div class="info-testo">${testo.replace(/\n/g, '<br>')}</div>
        </div>`;
}

// ── SCHERMO ───────────────────────────────────────────────────────
function adattaSchermo() {
    const scale = Math.min(window.innerWidth / 1920, window.innerHeight / 1080);
    const root  = document.getElementById('player-root');
    root.style.transform = 'scale(' + scale + ')';
    root.style.left = Math.round((window.innerWidth  - 1920 * scale) / 2) + 'px';
    root.style.top  = Math.round((window.innerHeight - 1080 * scale) / 2) + 'px';
}

// ── OROLOGIO ─────────────────────────────────────────────────────
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

// ── SEGNALE TV / STREAM ──────────────────────────────────────────
const STREAM_URL = '<?php echo htmlspecialchars($stream_url); ?>';

async function avviaSegnaleTV() {
    await avviaSegnaleTVRuntime(STREAM_URL);
}

async function avviaSegnaleTVRuntime(streamUrl) {
    const v = document.getElementById('tv-video');
    if (streamUrl) {
        document.getElementById('tv-placeholder').style.display = 'none';
        if (streamUrl.includes('.m3u8')) {
            if (Hls.isSupported()) {
                const hls = new Hls({ autoStartLoad: true, startLevel: -1 });
                hls.loadSource(streamUrl);
                hls.attachMedia(v);
                hls.on(Hls.Events.MANIFEST_PARSED, () => v.play().catch(() => {}));
                hls.on(Hls.Events.ERROR, (e, data) => {
                    if (data.fatal) setTimeout(() => { hls.loadSource(streamUrl); }, 5000);
                });
            } else if (v.canPlayType('application/vnd.apple.mpegurl')) {
                v.src = streamUrl; v.play().catch(() => {});
            }
        } else {
            v.src = streamUrl; v.play().catch(() => {});
        }
        return;
    }
    try {
        await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        const devices = await navigator.mediaDevices.enumerateDevices();
        const capture = devices.find(d => d.kind === 'videoinput');
        if (!capture) return;
        const stream = await navigator.mediaDevices.getUserMedia({
    video: { 
        deviceId: { exact: capture.deviceId },
        width: { ideal: 1920 },
        height: { ideal: 1080 },
        aspectRatio: { ideal: 1.7777778 }
    }, 
    audio: true
});
        v.srcObject = stream; v.play();
        document.getElementById('tv-placeholder').style.display = 'none';
    } catch(e) {
        console.error('Errore avvio segnale TV:', e);
    }
}

// ── BANNER ───────────────────────────────────────────────────────
function applicaBanner(banner) {
    const el      = document.getElementById('layer-banner');
    const altezza = parseInt(banner.banner_altezza) || 80;
    const pos     = banner.banner_posizione || 'bottom';
    const logoSize = parseInt(banner.logo_size) || 75;
    const dataSize = parseInt(banner.data_size) || 28;
    const oraSize  = parseInt(banner.ora_size) || 44;

    bannerColore      = banner.banner_colore       || '#000000';
    bannerTestoColore = banner.banner_testo_colore || '#ffffff';

    el.style.height  = altezza + 'px';
    el.style.padding = '0 ' + Math.round(altezza * 0.25) + 'px';
    el.style.gap     = Math.round(altezza * 0.25) + 'px';

    const mainEl  = document.getElementById('main');
    const sideEl  = document.getElementById('colonna-corsi');
    const altMain = 1080 - altezza;

    if (pos === 'top') {
        el.style.top = '0'; el.style.bottom = 'auto';
        mainEl.style.top = altezza + 'px'; mainEl.style.height = altMain + 'px';
    } else {
        el.style.bottom = '0'; el.style.top = 'auto';
        mainEl.style.top = '0'; mainEl.style.height = altMain + 'px';
    }

    if (sideEl) {
        const tvWidth      = Math.round(altMain * 16 / 9);
        const sidebarWidth = 1920 - tvWidth;
        sideEl.style.width = sidebarWidth + 'px';
    }

    const logo = document.getElementById('banner-logo');
    if (banner.logo) {
        logo.src = BASE_URL + 'assets/img/' + banner.logo;
        logo.style.display = 'block';
        logo.style.height  = Math.round(altezza * (logoSize / 100)) + 'px';
        logo.style.width   = 'auto';
    } else {
        logo.style.display = 'none';
    }

    document.getElementById('banner-ora-dx').style.fontSize      = Math.round(altezza * (oraSize / 100)) + 'px';
    document.getElementById('banner-data-centro').style.fontSize = Math.round(altezza * (dataSize / 100)) + 'px';

    if (modalitaAttuale !== 'adv') {
        el.style.backgroundColor = bannerColore;
        el.style.color = bannerTestoColore;
        document.getElementById('banner-logo-wrap').style.visibility   = 'visible';
        document.getElementById('banner-data-centro').style.visibility = 'visible';
        document.getElementById('banner-data-centro').style.color      = bannerTestoColore;
        document.querySelectorAll('.banner-sep').forEach(s => s.style.visibility = 'visible');
        const ora = document.getElementById('banner-ora-dx');
        ora.style.color = bannerTestoColore; ora.style.textShadow = '';
        ora.style.backgroundColor = ''; ora.style.borderRadius = ''; ora.style.padding = '';
    }
}

// ── MODALITÀ TV ──────────────────────────────────────────────────
function mostraTV() {
    modalitaAttuale = 'tv';
    document.getElementById('layer-adv').style.display = 'none';
    document.getElementById('main').style.display = 'flex';
    const banner = document.getElementById('layer-banner');
    banner.style.backgroundColor = bannerColore;
    document.getElementById('banner-logo-wrap').style.visibility   = 'visible';
    document.getElementById('banner-data-centro').style.visibility = 'visible';
    document.getElementById('banner-data-centro').style.color      = bannerTestoColore;
    document.querySelectorAll('.banner-sep').forEach(s => s.style.visibility = 'visible');
    const ora = document.getElementById('banner-ora-dx');
    ora.style.opacity = '1'; ora.style.color = bannerTestoColore;
    ora.style.textShadow = ''; ora.style.backgroundColor = '';
    ora.style.borderRadius = ''; ora.style.padding = '';
    const video = document.getElementById('adv-video');
    video.pause(); video.src = '';
    if (advTimer) { clearTimeout(advTimer); advTimer = null; }
}

// ── MODALITÀ ADV ─────────────────────────────────────────────────
function mostraADV(stato) {
    modalitaAttuale = 'adv';
    contenuti = [...(stato.contenuti || [])];
    indiceContenuto = 0;
    if (stato.contenuto_ora) {
        const idx = contenuti.findIndex(c => c.id === stato.contenuto_ora.id);
        if (idx >= 0) indiceContenuto = idx;
    }
    document.getElementById('main').style.display = 'none';
    const advEl = document.getElementById('layer-adv');
    advEl.style.top     = '0';
    advEl.style.height  = '1080px';
    advEl.style.display = 'block';
    document.getElementById('adv-video').style.height    = '1080px';
    document.getElementById('adv-immagine').style.height = '1080px';
    const banner = document.getElementById('layer-banner');
    banner.style.backgroundColor = 'transparent';
    document.getElementById('banner-logo-wrap').style.visibility   = 'hidden';
    document.getElementById('banner-data-centro').style.visibility = 'hidden';
    document.querySelectorAll('.banner-sep').forEach(s => s.style.visibility = 'hidden');
    const ora = document.getElementById('banner-ora-dx');
    ora.style.opacity         = '1';
    ora.style.color           = '#ffffff';
    ora.style.textShadow      = '0 0 8px rgba(0,0,0,0.9)';
    ora.style.backgroundColor = 'rgba(0,0,0,0.5)';
    ora.style.borderRadius    = '6px';
    ora.style.padding         = '4px 14px';
    const oraSize = parseInt(stato.banner?.ora_size) || 44;
    const bannerH = parseInt(stato.banner?.banner_altezza) || 80;
    ora.style.fontSize = Math.round(bannerH * (oraSize / 100)) + 'px';
    mostraContenuto(indiceContenuto);
}

// ── CONTENUTO ADV ────────────────────────────────────────────────
function mostraContenuto(idx) {
    if (!contenuti.length) { mostraTV(); return; }
    idx = idx % contenuti.length; indiceContenuto = idx;
    const c = contenuti[idx];
    const durata = c.tipo === 'video' ? 30 : (c.durata || 10);
    fetch(BASE_URL + 'api/stato.php?token=' + TOKEN + '&log_contenuto=' + c.id + '&log_durata=' + durata).catch(() => {});
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
        advTimer = setTimeout(() => mostraContenuto(indiceContenuto + 1), (c.durata||10) * 1000);
    }
}

// ── CORSI ────────────────────────────────────────────────────────
async function caricaCorsi() {
    if (!SHEET_URL) { setTimeout(caricaCorsi, 3600000); return; }
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
        setTimeout(caricaCorsi, 3600000);
    } catch(e) {
        setTimeout(caricaCorsi, 60000);
    }
}

// ── API POLLING ──────────────────────────────────────────────────
async function aggiornaDaAPI() {
    try {
        const res   = await fetch(BASE_URL + 'api/stato.php?token=' + TOKEN + '&t=' + Date.now());
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const stato = await res.json();

        if (stato.errore) { setTimeout(aggiornaDaAPI, 15000); return; }

        if (stato.banner) applicaBanner(stato.banner);

        // Confronta solo id+tipo+attivo — evita restart per campi irrilevanti
        const slideRicevute = stato.sidebar_slides || [];
        const fp = slidesFingerprint(slideRicevute);
        if (fp !== slidesFingerpr) {
            slidesFingerpr = fp;
            if (sidebarTimer) clearTimeout(sidebarTimer);
            Object.keys(countdownIntervals).forEach(id => {
                clearInterval(countdownIntervals[id]); delete countdownIntervals[id];
            });
            // Aggiorna i dati delle slide esistenti senza restartare il carousel
            // se solo i contenuti sono cambiati (stesso set di id)
            const vecchiIds = sidebarSlides.map(s => s.id).join('|');
            const nuoviIds  = slideRicevute.map(s => s.id).join('|');
            if (vecchiIds === nuoviIds && sidebarSlides.length > 0) {
                // Aggiorna i dati in-place senza resettare l'indice
                sidebarSlides = slideRicevute;
            } else {
                // Set di slide cambiato — restart dal primo
                avviaSidebar(slideRicevute);
            }
        }

        const cambiata = !statoCorrente || statoCorrente.modalita !== stato.modalita;
        const streamCambiato = statoCorrente && statoCorrente.stream_url !== stato.stream_url;

        if (stato.modalita === 'tv') {
            if (cambiata || streamCambiato) {
                mostraTV();
                if (streamCambiato || !statoCorrente) {
                    const v = document.getElementById('tv-video');
                    v.pause(); v.src = ''; v.srcObject = null;
                    setTimeout(() => avviaSegnaleTVRuntime(stato.stream_url || ''), 500);
                }
            }
            setTimeout(aggiornaDaAPI, Math.min((stato.secondi_alla_adv || 30) * 1000, 30000));
        } else if (stato.modalita === 'adv') {
            if (cambiata) mostraADV(stato);
            setTimeout(aggiornaDaAPI, Math.min((stato.secondi_alla_tv || 60) * 1000, 30000));
        }
        statoCorrente = stato;
    } catch(e) {
        setTimeout(aggiornaDaAPI, 15000);
    }
}

// ── INIT ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    adattaSchermo();
    window.addEventListener('resize', adattaSchermo);
    aggiornaOrologio();
    setInterval(aggiornaOrologio, 1000);
    avviaSegnaleTV();
    caricaCorsi();
    setTimeout(aggiornaDaAPI, 500);
});
</script>
</body>
</html>
