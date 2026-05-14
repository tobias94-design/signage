<?php
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

$token = $_GET['token'] ?? '';
if (!$token) {
    $primo = $db->query('SELECT token FROM dispositivi LIMIT 1')->fetch();
    $token = $primo['token'] ?? '';
}

$stmt = $db->prepare('SELECT d.*, p.nome as profilo_nome FROM dispositivi d LEFT JOIN profili p ON p.id = d.profilo_id WHERE d.token = ?');
$stmt->execute([$token]);
$dispositivo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dispositivo) die('<body style="background:#000;color:#e94560;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;">Dispositivo non trovato</body>');

$lobby_citta      = $dispositivo['lobby_citta'] ?? '';
$lobby_sheet_url  = $dispositivo['lobby_sheet_url'] ?? '';
$lobby_corsi_url  = $dispositivo['lobby_corsi_url'] ?? '';
$club             = $dispositivo['club'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>PixelBridge Lobby</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; cursor:none !important; }
html,body { width:100vw; height:100vh; overflow:hidden; background:#000; font-family:'Figtree',sans-serif; color:#fff; }
#player-root { position:absolute; width:1920px; height:1080px; top:0; left:0; transform-origin:top left; overflow:hidden; background:#000; }

/* Layer contenuto fullscreen */
#layer-contenuto { position:absolute; inset:0; width:1920px; height:1080px; background:#000; }

/* Video fullscreen */
#lobby-video { position:absolute; inset:0; width:1920px; height:1080px; object-fit:cover; display:none; }

/* Immagine fullscreen */
#lobby-immagine { position:absolute; inset:0; width:1920px; height:1080px; object-fit:cover; display:none; }

/* Widget fullscreen (meteo, corsi, info) */
#lobby-widget { position:absolute; inset:0; width:1920px; height:1080px; display:none; flex-direction:column; }
.widget-sfondo { position:absolute; inset:0; background-size:cover; background-position:center; z-index:0; }
.widget-overlay { position:absolute; inset:0; background:rgba(0,0,0,0.45); z-index:1; }
.widget-content { position:relative; z-index:2; height:100%; display:flex; flex-direction:column; padding:80px 120px; }
.widget-header { font-size:28px; letter-spacing:6px; text-transform:uppercase; color:rgba(255,255,255,0.5); font-weight:400; border-bottom:1px solid rgba(255,255,255,0.15); padding-bottom:24px; margin-bottom:40px; flex-shrink:0; }

/* Meteo fullscreen */
.meteo-wrap { flex:1; display:flex; flex-direction:column; justify-content:center; align-items:center; gap:16px; }
.meteo-icona { font-size:180px; line-height:1; }
.meteo-temp { font-size:180px; font-weight:900; letter-spacing:-8px; line-height:1; }
.meteo-desc { font-size:48px; opacity:0.75; text-transform:capitalize; text-align:center; }
.meteo-citta { font-size:36px; opacity:0.5; letter-spacing:6px; margin-top:8px; }
.meteo-dettagli { display:flex; gap:48px; margin-top:24px; font-size:32px; opacity:0.65; }
.meteo-previsioni { display:flex; gap:0; margin-top:40px; width:100%; justify-content:center; }
.meteo-giorno { flex:1; max-width:220px; text-align:center; padding:24px 16px; background:rgba(255,255,255,0.06); border-radius:16px; margin:0 8px; }
.meteo-giorno-nome { font-size:20px; opacity:0.6; letter-spacing:3px; margin-bottom:12px; }
.meteo-giorno-icona { font-size:56px; margin:8px 0; }
.meteo-giorno-temp { font-size:22px; opacity:0.8; }

/* Corsi fullscreen */
.corsi-wrap { flex:1; display:flex; flex-direction:column; justify-content:center; gap:0; }
.corso-row { display:flex; align-items:center; padding:28px 0; border-bottom:1px solid rgba(255,255,255,0.08); }
.corso-row.attivo { border-left:6px solid #e94560; padding-left:24px; background:rgba(233,69,96,0.08); border-radius:0 12px 12px 0; }
.corso-orario { font-size:52px; font-weight:300; width:200px; flex-shrink:0; }
.corso-nome { font-size:60px; font-weight:800; text-transform:uppercase; letter-spacing:2px; flex:1; }
.corso-badge { font-size:20px; letter-spacing:3px; color:#e94560; margin-top:4px; }

/* Info fullscreen */
.info-wrap { flex:1; display:flex; flex-direction:column; justify-content:center; gap:32px; }
.info-icona { font-size:120px; line-height:1; }
.info-testo { font-size:56px; font-weight:300; line-height:1.4; }

/* Transizione */
#layer-contenuto { transition:opacity 0.8s ease; }
#layer-contenuto.fade-out { opacity:0; }
</style>
</head>
<body>
<div id="player-root">
    <div id="layer-contenuto">
        <video id="lobby-video" autoplay playsinline muted></video>
        <img id="lobby-immagine" src="">
        <div id="lobby-widget">
            <div class="widget-sfondo" id="widget-sfondo"></div>
            <div class="widget-overlay" id="widget-overlay"></div>
            <div class="widget-content" id="widget-content"></div>
        </div>
    </div>
</div>

<script>
const TOKEN      = '<?php echo htmlspecialchars($token); ?>';
const CLUB       = '<?php echo htmlspecialchars($club); ?>';
const BASE_URL   = '/';
const LOBBY_CITTA     = '<?php echo htmlspecialchars($lobby_citta); ?>';
const LOBBY_SHEET_URL = '<?php echo htmlspecialchars($lobby_sheet_url); ?>';
let LOBBY_CORSI_URL   = '<?php echo htmlspecialchars($lobby_corsi_url); ?>';

const GIORNI_IT = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
const GIORNI_SHORT = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];
const MESI = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

const WMO_ICONS = {0:'☀️',1:'🌤️',2:'⛅',3:'☁️',45:'🌫️',48:'🌫️',51:'🌦️',53:'🌦️',55:'🌧️',61:'🌧️',63:'🌧️',65:'🌧️',71:'❄️',73:'❄️',75:'❄️',80:'🌦️',81:'🌧️',82:'⛈️',95:'⛈️',96:'⛈️',99:'⛈️'};
const WMO_DESC  = {0:'Sereno',1:'Prevalenz. sereno',2:'Parz. nuvoloso',3:'Nuvoloso',45:'Nebbia',51:'Pioggerella',61:'Pioggia',65:'Pioggia intensa',71:'Neve',80:'Rovesci',95:'Temporale'};
const PRESETS   = {
    'dark_red':'linear-gradient(135deg,#000 0%,#1a0000 40%,#8b0000 100%)',
    'midnight':'linear-gradient(135deg,#0a0a1a 0%,#0f3460 100%)',
    'purple':'linear-gradient(135deg,#1a0030 0%,#4a0080 100%)',
    'forest':'linear-gradient(135deg,#001a0a 0%,#004d20 100%)',
    'gold':'linear-gradient(135deg,#1a1200 0%,#4d3800 100%)',
    'carbon':'linear-gradient(135deg,#111 0%,#2a2a2a 100%)',
};

let slides = [], indice = 0, timer = null;
let corsiOggi = [], corsiFuturi = [], meteoCache = {};

// ── ADATTA SCHERMO ────────────────────────────────────────────────
function adattaSchermo() {
    const scale = Math.min(window.innerWidth/1920, window.innerHeight/1080);
    const root  = document.getElementById('player-root');
    root.style.transform = 'scale('+scale+')';
    root.style.left = Math.round((window.innerWidth  - 1920*scale)/2)+'px';
    root.style.top  = Math.round((window.innerHeight - 1080*scale)/2)+'px';
}

// ── FETCH SLIDES DA API ───────────────────────────────────────────
async function fetchSlides() {
    try {
        const res   = await fetch(BASE_URL+'api/stato.php?token='+TOKEN+'&t='+Date.now());
        const stato = await res.json();
        const s = stato.sidebar_slides || [];
        if (s.length) {
            const newSlides = s.filter(sl => sl.attivo);
            // Aggiorna solo se le slide sono cambiate
            const oldFp = slides.map(sl=>sl.id+':'+sl.attivo).join('|');
            const newFp = newSlides.map(sl=>sl.id+':'+sl.attivo).join('|');
            if (oldFp !== newFp) {
                slides = newSlides;
                indice = 0;
            }
        }
        setTimeout(fetchSlides, 30000); // controlla ogni 30 secondi
    } catch(e) {
        setTimeout(fetchSlides, 15000);
    }
}

// Controlla reload ogni 5 secondi
async function controllaReload() {
    try {
        const res = await fetch(BASE_URL+'api/reload_check.php?token='+TOKEN+'&t='+Date.now());
        if (!res.ok) return;
        const data = await res.json();
        if (data.reload) { location.reload(); return; }
    } catch(e) {}
}
setInterval(controllaReload, 5000);

// Aggiorna lobby_corsi_url dall'API
async function aggiornaMeta() {
    try {
        const res = await fetch(BASE_URL+'api/stato.php?token='+TOKEN+'&t='+Date.now());
        const d = await res.json();
        if (d.lobby_corsi_url) LOBBY_CORSI_URL = d.lobby_corsi_url;
    } catch(e) {}
}
setInterval(aggiornaMeta, 60000);

// ── LOOP PRINCIPALE ───────────────────────────────────────────────
function prossima() {
    if (!slides.length) { timer = setTimeout(prossima, 5000); return; }
    indice = indice % slides.length;
    mostraSlide(slides[indice]);
    indice++;
}

function mostraSlide(slide) {
    const layer = document.getElementById('layer-contenuto');
    const cfg   = (() => { try { return JSON.parse(slide.contenuto||'{}'); } catch(e) { return {}; } })();

    layer.classList.add('fade-out');
    setTimeout(() => {
        // Nascondi tutto
        document.getElementById('lobby-video').style.display   = 'none';
        document.getElementById('lobby-immagine').style.display = 'none';
        document.getElementById('lobby-widget').style.display   = 'none';

        switch(slide.tipo) {
            case 'meteo':     mostraMeteo(slide, cfg); break;
            case 'corsi':     mostraCorsi(slide); break;
            case 'countdown': mostraCountdown(slide, cfg); break;
            case 'immagine':  mostraImmagine(slide, cfg); break;
            case 'video':     mostraVideo(slide, cfg); break;
            case 'info':      mostraInfo(slide, cfg); break;
            default:          mostraInfo(slide, cfg);
        }
        layer.classList.remove('fade-out');
    }, 800);
}

// ── METEO FULLSCREEN ──────────────────────────────────────────────
async function mostraMeteo(slide, cfg) {
    const citta = cfg.citta || LOBBY_CITTA || '';
    const layer = document.getElementById('layer-contenuto');

    let meteoDiv = document.getElementById('pb-meteo-layer');
    if (!meteoDiv) {
        meteoDiv = document.createElement('div');
        meteoDiv.id = 'pb-meteo-layer';
        meteoDiv.style.cssText = 'position:absolute;inset:0;width:1920px;height:1080px;z-index:10;background:#000;';
        layer.appendChild(meteoDiv);
    }
    meteoDiv.style.display = 'block';
    document.getElementById('lobby-video').style.display    = 'none';
    document.getElementById('lobby-immagine').style.display = 'none';
    document.getElementById('lobby-widget').style.display   = 'none';

    const durata = (parseInt(slide.durata)||20)*1000;
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const md = document.getElementById('pb-meteo-layer');
        if (md) md.style.display = 'none';
        prossima();
    }, durata);

    meteoDiv.innerHTML = `<div style="background:#0c0a09;width:1920px;height:1080px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;font-family:'Inter',sans-serif;color:#fff;">
        <div style="position:absolute;top:-150px;right:-100px;width:700px;height:700px;background:rgba(213,19,23,0.07);border-radius:50%;filter:blur(120px);"></div>
        <div style="position:absolute;bottom:-100px;left:-80px;width:500px;height:500px;background:rgba(255,255,255,0.02);border-radius:50%;filter:blur(100px);"></div>
        <div style="width:1600px;display:flex;flex-direction:column;gap:20px;">
            <div style="background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.08);border-radius:28px;padding:52px 72px;display:flex;justify-content:space-between;align-items:center;">
                <div><div style="font-size:64px;font-weight:700;letter-spacing:-0.02em;">Caricamento...</div></div>
                <div style="font-size:180px;font-weight:200;letter-spacing:-0.06em;line-height:1;">--°</div>
            </div>
        </div>
    </div>`;

    const data = await fetchMeteo(citta, cfg.lat, cfg.lon);
    if (!data || !data.current) { return; }

    const cur  = data.current;
    const wmo  = cur.weathercode;
    const temp = Math.round(cur.temperature_2m);
    const hum  = cur.relativehumidity_2m;
    const wind = Math.round(cur.windspeed_10m);
    const desc = WMO_DESC[wmo] || '';
    const icon = WMO_ICONS[wmo] || '🌡️';
    const minOggi = data.daily?.temperature_2m_min ? Math.round(data.daily.temperature_2m_min[0]) : '--';
    const maxOggi = data.daily?.temperature_2m_max ? Math.round(data.daily.temperature_2m_max[0]) : '--';
    const apparent = data.current.apparent_temperature ? Math.round(data.current.apparent_temperature) : Math.round(temp - 2);

    const GIORNI_S = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];
    let prevCards = '';
    if (data.daily?.time) {
        for (let i=1; i<=3 && i<data.daily.time.length; i++) {
            const d   = new Date(data.daily.time[i]);
            const min = Math.round(data.daily.temperature_2m_min[i]);
            const max = Math.round(data.daily.temperature_2m_max[i]);
            const ic  = WMO_ICONS[data.daily.weathercode[i]] || '🌡️';
            const br  = i < 3 ? 'border-right:1px solid rgba(255,255,255,0.07);' : '';
            prevCards += `<div style="flex:1;text-align:center;padding:32px 20px;${br}">
                <div style="font-size:32px;color:rgba(255,255,255,0.4);margin-bottom:24px;font-weight:500;">${GIORNI_S[d.getDay()]}</div>
                <div style="font-size:100px;margin-bottom:24px;">${ic}</div>
                <div style="font-size:48px;font-weight:600;">${min}° / ${max}°</div>
            </div>`;
        }
    }

    meteoDiv.innerHTML = `<div style="background:#0c0a09;width:1920px;height:1080px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;font-family:'Inter',sans-serif;color:#fff;">
        <div style="position:absolute;bottom:-200px;left:-100px;width:900px;height:700px;background:radial-gradient(ellipse,rgba(61,18,0,0.8) 0%,transparent 70%);pointer-events:none;"></div>
        <div style="position:absolute;top:-200px;right:-100px;width:700px;height:600px;background:radial-gradient(ellipse,rgba(26,8,0,0.6) 0%,transparent 70%);pointer-events:none;"></div>
        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:800px;height:600px;background:radial-gradient(ellipse,rgba(28,12,0,0.4) 0%,transparent 70%);pointer-events:none;"></div>
        <div style="width:1600px;display:flex;flex-direction:column;gap:20px;">
            <div style="background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.08);border-radius:28px;padding:52px 72px;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:72px;font-weight:700;letter-spacing:-0.02em;line-height:1;margin-bottom:12px;">${citta}</div>
                    <div style="font-size:26px;color:rgba(255,255,255,0.35);font-weight:300;margin-bottom:24px;">H:${maxOggi}° &nbsp; L:${minOggi}°</div>
                    <div style="display:flex;align-items:center;gap:20px;">
                        <span style="font-size:52px;">${icon}</span>
                        <span style="font-size:32px;font-weight:300;color:rgba(255,255,255,0.6);">${desc}</span>
                    </div>
                </div>
                <div style="font-size:200px;font-weight:200;letter-spacing:-0.06em;line-height:1;">${temp}<span style="font-size:72px;font-weight:200;vertical-align:top;margin-top:32px;display:inline-block;">°C</span></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
                <div style="background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.08);border-radius:24px;padding:36px 44px;">
                    <div style="font-size:22px;color:rgba(255,255,255,0.3);font-weight:500;margin-bottom:20px;">💨 Vento</div>
                    <div style="font-size:120px;font-weight:700;letter-spacing:-0.03em;line-height:1;">${wind}<span style="font-size:42px;font-weight:400;color:rgba(255,255,255,0.3);margin-left:10px;">km/h</span></div>
                </div>
                <div style="background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.08);border-radius:24px;padding:36px 44px;">
                    <div style="font-size:22px;color:rgba(255,255,255,0.3);font-weight:500;margin-bottom:20px;">💧 Umidità</div>
                    <div style="font-size:120px;font-weight:700;letter-spacing:-0.03em;line-height:1;">${hum}<span style="font-size:42px;font-weight:400;color:rgba(255,255,255,0.3);margin-left:4px;">%</span></div>
                    <div style="margin-top:20px;height:3px;background:rgba(255,255,255,0.08);border-radius:2px;"><div style="width:${hum}%;height:100%;background:rgba(255,255,255,0.45);border-radius:2px;"></div></div>
                </div>
                <div style="background:rgba(180,10,10,0.12);border:1px solid rgba(180,10,10,0.25);border-radius:24px;padding:36px 44px;">
                    <div style="font-size:22px;color:rgba(213,19,23,0.7);font-weight:500;margin-bottom:20px;">🌡️ Percepita</div>
                    <div style="font-size:120px;font-weight:700;letter-spacing:-0.03em;line-height:1;color:#d51317;">${apparent}<span style="font-size:42px;font-weight:400;">°</span></div>
                </div>
            </div>
            <div style="background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.08);border-radius:28px;padding:32px 44px;">
                <div style="font-size:13px;color:rgba(255,255,255,0.2);font-weight:600;letter-spacing:0.15em;text-transform:uppercase;margin-bottom:24px;">Prossimi 3 giorni</div>
                <div style="display:flex;">${prevCards}</div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0 8px;">
                <div style="font-family:'JetBrains Mono',monospace;font-size:12px;color:rgba(255,255,255,0.15);letter-spacing:0.15em;text-transform:uppercase;">PixelBridge · Digital Signage</div>
                <div style="width:8px;height:8px;border-radius:50%;background:#d51317;"></div>
            </div>
        </div>
    </div>`;
}


// ── CORSI FULLSCREEN — template PixelBridge ──────────────────────
function mostraCorsi(slide) {
    const layer = document.getElementById('layer-contenuto');
    let corsiDiv = document.getElementById('pb-corsi-layer');
    if (!corsiDiv) {
        corsiDiv = document.createElement('div');
        corsiDiv.id = 'pb-corsi-layer';
        corsiDiv.style.cssText = 'position:absolute;inset:0;width:1920px;height:1080px;z-index:10;';
        layer.appendChild(corsiDiv);
    }
    corsiDiv.style.display = 'block';
    document.getElementById('lobby-video').style.display    = 'none';
    document.getElementById('lobby-immagine').style.display = 'none';
    document.getElementById('lobby-widget').style.display   = 'none';

    const oraOra = new Date().getHours()*60 + new Date().getMinutes();
    const now    = new Date();
    const GIORNI = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
    const MESI   = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
    const oraStr = String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0');
    const clubName = 'Gymnasium Club'+(CLUB?' '+CLUB:'');

    // Filtra corsi futuri
    corsiFuturi = corsiOggi.filter(c => {
        const p = c.orario.split(':');
        const s = parseInt(p[0])*60 + parseInt(p[1]);
        return (s + c.durata) > oraOra;
    });

    let attivoIdx = -1;
    corsiFuturi.forEach((c, i) => {
        const p = c.orario.split(':');
        const s = parseInt(p[0])*60 + parseInt(p[1]);
        if (s <= oraOra && oraOra < s + c.durata) attivoIdx = i;
    });
    let startIdx = attivoIdx >= 0 ? Math.max(0, attivoIdx) : 0;
    let endIdx = Math.min(corsiFuturi.length, startIdx + 3);
    if (endIdx - startIdx < 3) startIdx = Math.max(0, endIdx - 3);
    const visibili = corsiFuturi.slice(startIdx, endIdx);

    let righe = '';
    if (!corsiFuturi.length) {
        const qrUrl = LOBBY_CORSI_URL || 'https://www.gymnasiumclub.net/corsi/';
        const qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&color=ffffff&bgcolor=000000&data=' + encodeURIComponent(qrUrl);
        righe = `<div style="flex:1;display:flex;align-items:center;justify-content:center;gap:120px;padding:0 80px;">
            <div>
                <div style="font-family:'JetBrains Mono',monospace;font-size:16px;font-weight:700;letter-spacing:0.2em;color:#d51317;text-transform:uppercase;margin-bottom:40px;">Oggi nessun corso in programma</div>
                <div style="font-size:112px;font-weight:900;line-height:1;letter-spacing:-0.04em;margin-bottom:40px;">Allenati<br>ogni giorno.</div>
                <div style="font-size:36px;font-weight:300;color:rgba(255,255,255,0.5);margin-bottom:56px;line-height:1.4;">La costanza è il segreto<br>del successo.</div>
                <div style="display:flex;align-items:center;gap:20px;">
                    <div style="width:4px;height:56px;background:#d51317;flex-shrink:0;"></div>
                    <div>
                        <div style="font-family:'JetBrains Mono',monospace;font-size:14px;color:rgba(255,255,255,0.35);letter-spacing:0.15em;text-transform:uppercase;margin-bottom:8px;">Scopri i corsi</div>
                        <div style="font-family:'JetBrains Mono',monospace;font-size:18px;color:#fff;letter-spacing:0.05em;">${qrUrl}</div>
                    </div>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:center;gap:24px;flex-shrink:0;">
                <img src="${qrApiUrl}" style="width:300px;height:300px;" alt="QR">
                <div style="font-family:'JetBrains Mono',monospace;font-size:14px;color:rgba(255,255,255,0.35);letter-spacing:0.15em;text-transform:uppercase;">Scansiona per i corsi</div>
            </div>
        </div>`;
    } else {
        visibili.forEach(c => {
            const p = c.orario.split(':');
            const s = parseInt(p[0])*60+parseInt(p[1]);
            const live = s <= oraOra && oraOra < s+c.durata;
            const past = (s+c.durata) <= oraOra;
            const badge = live
                ? `<span style="background:#d51317;color:#fff;font-family:'JetBrains Mono',monospace;font-size:20px;font-weight:700;letter-spacing:0.15em;padding:18px 36px;display:inline-flex;align-items:center;gap:14px;"><span style="width:12px;height:12px;background:#fff;border-radius:50%;display:inline-block;animation:pb-pulse 1.2s ease-in-out infinite;"></span>LIVE</span>`
                : past
                ? `<span style="font-family:'JetBrains Mono',monospace;font-size:18px;letter-spacing:0.15em;color:rgba(255,255,255,0.2);">TERMINATO</span>`
                : `<span style="font-family:'JetBrains Mono',monospace;font-size:18px;letter-spacing:0.15em;color:rgba(255,255,255,0.5);border:1px solid rgba(255,255,255,0.25);padding:14px 32px;">${(s-oraOra)<60?'PROSSIMO':'PROGRAMMATO'}</span>`;

            const rowH = Math.floor(700 / Math.max(visibili.length, 1));
            const fsOra = live ? Math.min(130, rowH*0.55) : Math.min(104, rowH*0.44);
            const fsTxt = live ? Math.min(72, rowH*0.30) : Math.min(58, rowH*0.24);
            const fsSub = Math.min(22, rowH*0.09);
            righe += `<div style="display:grid;grid-template-columns:320px 1fr 320px;padding:0 80px;border-bottom:1px solid ${live?'rgba(213,19,23,0.5)':'rgba(255,255,255,0.1)'};align-items:center;height:${rowH}px;flex-shrink:0;box-sizing:border-box;${live?'background:rgba(213,19,23,0.08);':''}${past?'opacity:0.22;':''}">
                <div style="font-size:${fsOra}px;font-weight:900;letter-spacing:-0.04em;line-height:1;font-variant-numeric:tabular-nums;color:${live?'#d51317':'rgba(255,255,255,0.28)'};">${c.orario}</div>
                <div>
                    <div style="font-size:${fsTxt}px;font-weight:700;text-transform:uppercase;letter-spacing:0.02em;color:#fff;line-height:1.1;">${c.corso}</div>
                    <div style="font-family:'JetBrains Mono',monospace;font-size:${fsSub}px;color:rgba(255,255,255,0.28);letter-spacing:0.15em;margin-top:10px;text-transform:uppercase;">${c.durata} MIN${c.studio?' &nbsp;·&nbsp; '+c.studio.toUpperCase():''}</div>
                </div>
                <div style="text-align:right;">${badge}</div>
            </div>`;
        });
    }

    const tickerItems = corsiFuturi.map(c => {
        const p=c.orario.split(':'), s=parseInt(p[0])*60+parseInt(p[1]);
        const live=s<=oraOra&&oraOra<s+c.durata;
        return `<span style="margin:0 32px;">${c.orario} ${c.corso}${live?' — LIVE':''}</span><span style="opacity:0.4;">·</span>`;
    }).join('');

    corsiDiv.innerHTML = `<div style="background:#000;color:#fff;font-family:'Inter',sans-serif;height:1080px;width:1920px;display:flex;flex-direction:column;">
        <style>@keyframes pb-pulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:0.4;transform:scale(0.7);}}@keyframes pb-ticker{0%{transform:translateX(0);}100%{transform:translateX(-50%);}}</style>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:28px 80px;border-bottom:2px solid #d51317;">
            <div style="display:flex;align-items:baseline;gap:28px;">
                <div style="font-size:88px;font-weight:900;line-height:1;letter-spacing:-0.04em;">${GIORNI[now.getDay()]}</div>
                <div style="font-family:'JetBrains Mono',monospace;font-size:18px;color:rgba(255,255,255,0.35);letter-spacing:0.1em;text-transform:uppercase;">${now.getDate()} ${MESI[now.getMonth()]} ${now.getFullYear()} &nbsp;·&nbsp; ${clubName}</div>
            </div>
            <div id="pb-clock" style="font-size:88px;font-weight:900;line-height:1;color:#d51317;letter-spacing:-0.03em;font-variant-numeric:tabular-nums;">${oraStr}</div>
        </div>
        <div style="display:grid;grid-template-columns:320px 1fr 320px;padding:16px 80px;border-bottom:1px solid rgba(255,255,255,0.06);">
            <div style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:rgba(255,255,255,0.22);letter-spacing:0.2em;text-transform:uppercase;">Orario</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:rgba(255,255,255,0.22);letter-spacing:0.2em;text-transform:uppercase;">Corso</div>
            <div style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:rgba(255,255,255,0.22);letter-spacing:0.2em;text-transform:uppercase;text-align:right;">Stato</div>
        </div>
        <div style="flex:1;display:flex;flex-direction:column;">${righe}</div>
        <div style="background:#d51317;height:64px;display:flex;align-items:center;overflow:hidden;flex-shrink:0;">
            <div style="display:flex;gap:0;animation:pb-ticker 30s linear infinite;white-space:nowrap;font-family:'JetBrains Mono',monospace;font-size:20px;letter-spacing:0.12em;text-transform:uppercase;font-weight:600;">${tickerItems}${tickerItems}</div>
        </div>
    </div>`;

    const clockEl = document.getElementById('pb-clock');
    if (clockEl) {
        const iv = setInterval(() => {
            const el = document.getElementById('pb-clock');
            if (!el) { clearInterval(iv); return; }
            const n = new Date();
            el.textContent = String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0');
        }, 1000);
    }

    const durata = (parseInt(slide.durata)||30)*1000;
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const cd = document.getElementById('pb-corsi-layer');
        if (cd) cd.style.display = 'none';
        prossima();
    }, durata);
}

// ── INFO FULLSCREEN ───────────────────────────────────────────────
function mostraInfo(slide, cfg) {
    const widget = document.getElementById('lobby-widget');
    const sfondo = document.getElementById('widget-sfondo');
    const overlay= document.getElementById('widget-overlay');
    const content= document.getElementById('widget-content');

    applicaSfondo(sfondo, overlay, slide);
    content.style.color = slide.colore_testo || '#ffffff';
    content.innerHTML = `
        ${slide.titolo ? `<div class="widget-header">${slide.titolo}</div>` : ''}
        <div class="info-wrap">
            <div class="info-icona">${cfg.icona||'ℹ️'}</div>
            <div class="info-testo">${(cfg.testo||'').replace(/\n/g,'<br>')}</div>
        </div>`;
    widget.style.display = 'flex';

    const durata = (parseInt(slide.durata)||10)*1000;
    if (timer) clearTimeout(timer);
    timer = setTimeout(prossima, durata);
}

// ── COUNTDOWN ─────────────────────────────────────────────────────
function mostraCountdown(slide, cfg) {
    const widget = document.getElementById('lobby-widget');
    const sfondo = document.getElementById('widget-sfondo');
    const overlay= document.getElementById('widget-overlay');
    const content= document.getElementById('widget-content');

    applicaSfondo(sfondo, overlay, slide);
    content.style.color = slide.colore_testo || '#ffffff';
    widget.style.display = 'flex';

    const durata = (parseInt(slide.durata)||15)*1000;
    if (timer) clearTimeout(timer);
    timer = setTimeout(prossima, durata);

    const dataTarget = cfg.data_target ? new Date(cfg.data_target) : null;

    function aggiorna() {
        if (!dataTarget) { content.innerHTML = `<div class="widget-header">${slide.titolo||'Countdown'}</div><div style="flex:1;display:flex;align-items:center;justify-content:center;font-size:80px;">Evento in arrivo</div>`; return; }
        const diff = dataTarget - new Date();
        if (diff <= 0) { content.innerHTML = `<div class="widget-header">${slide.titolo}</div><div style="flex:1;display:flex;align-items:center;justify-content:center;font-size:80px;">🎉 È oggi!</div>`; return; }
        const gg = Math.floor(diff/86400000);
        const hh = Math.floor((diff%86400000)/3600000);
        const mm = Math.floor((diff%3600000)/60000);
        const ss = Math.floor((diff%60000)/1000);
        content.innerHTML = `
            <div class="widget-header">${slide.titolo||'Countdown'}</div>
            ${cfg.messaggio_pre ? `<div style="font-size:36px;opacity:0.7;margin-bottom:40px;">${cfg.messaggio_pre}</div>` : ''}
            <div style="flex:1;display:flex;align-items:center;justify-content:center;gap:40px;">
                ${gg>0?`<div style="text-align:center"><div style="font-size:160px;font-weight:900;line-height:1;">${String(gg).padStart(2,'0')}</div><div style="font-size:28px;opacity:0.5;letter-spacing:4px;">GIORNI</div></div>`:''}
                <div style="text-align:center"><div style="font-size:160px;font-weight:900;line-height:1;">${String(hh).padStart(2,'0')}</div><div style="font-size:28px;opacity:0.5;letter-spacing:4px;">ORE</div></div>
                <div style="text-align:center"><div style="font-size:160px;font-weight:900;line-height:1;">${String(mm).padStart(2,'0')}</div><div style="font-size:28px;opacity:0.5;letter-spacing:4px;">MIN</div></div>
                <div style="text-align:center"><div style="font-size:160px;font-weight:900;line-height:1;">${String(ss).padStart(2,'0')}</div><div style="font-size:28px;opacity:0.5;letter-spacing:4px;">SEC</div></div>
            </div>`;
    }
    aggiorna();
    const iv = setInterval(aggiorna, 1000);
    setTimeout(() => clearInterval(iv), durata);
}

// ── IMMAGINE FULLSCREEN ───────────────────────────────────────────
function mostraImmagine(slide, cfg) {
    const img    = document.getElementById('lobby-immagine');
    const file   = cfg.file || '';
    if (!file) { prossima(); return; }
    img.src = BASE_URL + 'uploads/' + file;
    img.style.display = 'block';
    img.style.objectFit = 'cover';

    const durata = (parseInt(slide.durata)||10)*1000;
    if (timer) clearTimeout(timer);
    timer = setTimeout(prossima, durata);
}

// ── VIDEO FULLSCREEN ──────────────────────────────────────────────
function mostraVideo(slide, cfg) {
    const video  = document.getElementById('lobby-video');
    const file   = cfg.file || '';
    if (!file) { prossima(); return; }

    video.src     = BASE_URL + 'uploads/' + file;
    video.muted   = true;
    video.loop    = false;
    video.style.display    = 'block';
    video.style.objectFit  = 'cover';

    video.onended = () => {
        video.onended = null;
        prossima();
    };
    // Fallback se il video non parte entro 10 secondi
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        video.onended = null;
        prossima();
    }, 300000); // max 5 minuti

    video.load();
    video.play().catch(() => prossima());
}

// ── HELPER SFONDO ─────────────────────────────────────────────────
function applicaSfondo(sfondoEl, overlayEl, slide) {
    if (slide.sfondo) {
        sfondoEl.style.cssText = `position:absolute;inset:0;background-size:cover;background-position:center;z-index:0;background-image:url('${BASE_URL}uploads/${slide.sfondo}')`;
        overlayEl.style.display = 'block';
    } else if (slide.sfondo_preset && PRESETS[slide.sfondo_preset]) {
        sfondoEl.style.cssText = `position:absolute;inset:0;z-index:0;background:${PRESETS[slide.sfondo_preset]}`;
        overlayEl.style.display = 'none';
    } else {
        sfondoEl.style.cssText = `position:absolute;inset:0;z-index:0;background:${slide.colore_sfondo||'#111111'}`;
        overlayEl.style.display = 'none';
    }
}

// ── METEO API ─────────────────────────────────────────────────────
async function fetchMeteo(citta, lat, lon) {
    const key = citta||`${lat},${lon}`;
    if (meteoCache[key] && (Date.now()-meteoCache[key].ts < 1800000)) return meteoCache[key].data;
    try {
        let latitude=lat, longitude=lon;
        if (!latitude||!longitude) {
            const geo = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(citta)}&count=1&language=it`);
            const gd  = await geo.json();
            if (!gd.results?.length) return null;
            latitude=gd.results[0].latitude; longitude=gd.results[0].longitude;
        }
        const res  = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${latitude}&longitude=${longitude}&current=temperature_2m,weathercode,windspeed_10m,relativehumidity_2m&daily=weathercode,temperature_2m_max,temperature_2m_min&timezone=auto&forecast_days=4`);
        const data = await res.json();
        meteoCache[key] = { ts:Date.now(), data };
        return data;
    } catch(e) { return null; }
}

// ── CORSI SHEET ───────────────────────────────────────────────────
async function caricaCorsi() {
    const url = LOBBY_SHEET_URL;
    if (!url) { setTimeout(caricaCorsi, 3600000); return; }
    try {
        const proxyUrl = BASE_URL + 'api/csv_proxy.php?url=' + encodeURIComponent(url) + '&t=' + Date.now();
        const res  = await fetch(proxyUrl);
        const text = await res.text();
        const rows = text.trim().split('\n').slice(1);
        const oggi = GIORNI_IT[new Date().getDay()];
        const oraOra = new Date().getHours()*60+new Date().getMinutes();
        corsiOggi = rows.map(row => {
            const cols  = row.match(/(".*?"|[^",]+)(?=\s*,|\s*$)/g)||row.split(',');
            const clean = cols.map(c => c?c.trim().replace(/^"|"$/g,''):'');
            return { giorno:clean[0]||'', orario:clean[1]||'', corso:clean[2]||'', club:clean[3]||'', durata:parseInt(clean[4])||60, studio:clean[5]||'' };
        }).filter(c => {
            if (c.giorno!==oggi) return false;
            if (CLUB && c.club.toLowerCase()!==CLUB.toLowerCase()) return false;
            return true;
        }).sort((a,b)=>a.orario.localeCompare(b.orario));
        setTimeout(caricaCorsi, 3600000);
    } catch(e) { setTimeout(caricaCorsi, 60000); }
}

// ── INIT ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    adattaSchermo();
    window.addEventListener('resize', adattaSchermo);
    await fetchSlides();
    await caricaCorsi();
    prossima();
});
</script>
</body>
</html>
