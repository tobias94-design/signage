<?php
/**
 * player/display_v2.php
 * Nuovo player — legge il template assegnato al dispositivo (via api/stato_v2.php)
 * e renderizza ogni layer con dati reali.
 *
 * Uso: /player/display_v2.php?token=IL_TOKEN_DISPOSITIVO
 */
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>PixelBridge Player</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;600;700;800&family=Montserrat:wght@400;600;700;800&family=Oswald:wght@400;600;700&family=Bebas+Neue&family=Space+Mono:wght@400;700&display=swap">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  html, body { width:100%; height:100%; overflow:hidden; background:#000; font-family:'Inter',Arial,sans-serif; cursor:none; }
  #stage { position:relative; overflow:hidden; background:#000; margin:0 auto; }
  .layer { position:absolute; overflow:hidden; box-sizing:border-box; }
  .layer img { display:block; }

  /* Messaggi di stato (nessun token, nessun template, errore) */
  #status-screen { position:fixed; inset:0; background:#000; color:#666; display:flex; align-items:center; justify-content:center; flex-direction:column; text-align:center; padding:40px; font-size:20px; }
  #status-screen .sub { font-size:14px; color:#444; margin-top:10px; }

  @keyframes tickerScroll { 0% { transform:translateX(0); } 100% { transform:translateX(-50%); } }
  @keyframes tickerScrollRTL { 0% { left:100%; } 100% { left:-100%; } }
  @keyframes fadeIn { from{opacity:0} to{opacity:1} }
  @keyframes neonPulse { 0%,100% { filter:brightness(1); } 50% { filter:brightness(1.3); } }
  @keyframes flapIn { 0% { transform:rotateX(-90deg); opacity:0; } 60% { transform:rotateX(12deg); opacity:1; } 100% { transform:rotateX(0deg); opacity:1; } }
  .flap-tile{display:inline-flex;align-items:center;justify-content:center;position:relative;border-radius:3px;font-family:'Space Mono',monospace;font-weight:700;text-transform:uppercase;transform-origin:center;animation:flapIn .4s ease backwards}
  .flap-tile::after{content:'';position:absolute;left:0;right:0;top:50%;height:1px;background:rgba(0,0,0,.5)}
</style>
</head>
<body>

<div id="status-screen" style="display:none">
  <div id="status-text">Caricamento…</div>
  <div class="sub" id="status-sub"></div>
</div>

<div id="pairing-screen" style="display:none;position:fixed;inset:0;background:#000;color:#fff;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:40px;font-family:'Inter',sans-serif">
  <div style="font-family:'Hanken Grotesk',sans-serif;font-size:26px;font-weight:800;letter-spacing:-0.5px;margin-bottom:40px">
    <span style="color:#5aaeff">Pixel</span><span style="color:#a06aff">Bridge</span>
  </div>
  <div style="font-size:22px;font-weight:700;margin-bottom:8px">Configura questo schermo</div>
  <div style="font-size:14px;color:#888;margin-bottom:28px;max-width:420px">Vai su Dispositivi nel pannello e inserisci il codice, oppure scansiona il QR con il telefono</div>
  <div id="pairing-code" style="font-size:44px;font-weight:800;letter-spacing:8px;font-family:'Space Mono',monospace;margin-bottom:24px">--------</div>
  <div style="font-size:12px;color:#555;margin-top:20px">Il codice scade tra 10 minuti — ne genero uno nuovo automaticamente</div>
</div>

<div id="stage"></div>

<!-- HLS.js per streaming IPTV (m3u8) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.5.13/hls.min.js"></script>
<script>
const TOKEN = <?php echo json_encode($token); ?>;
const PREVIEW_MODE = new URLSearchParams(window.location.search).get('preview') === '1';
const API_URL = '/api/stato_v2.php?token=' + encodeURIComponent(TOKEN);
const POLL_INTERVAL = 30000; // ricontrolla il template ogni 30s

let currentTemplateId = null;
let lastLayersSnapshot = null; // rileva modifiche ai layer anche senza cambio di template
let sidebarTimers = {};
let clockTimer = null;
let advPollTimers = {};   // un timer di polling per ogni layer ADV nel template
let advOverlayEl = null;  // overlay fullscreen condiviso, creato una sola volta
let imgRotateTimers = {}; // un timer di rotazione per ogni widget Immagine con piu' foto
let CAPTURE_DEVICE = ''; // nome (o parte del nome) della capture card configurata per questo dispositivo
let tvStream = null;     // lo stream video attivo del widget TV, per poterlo fermare quando serve
let tvWatchdogTimer = null;   // controlla periodicamente che il video TV non si sia bloccato
let tvWatchdogEl = null;      // il contenitore del widget TV attualmente monitorato
let tvLastTime = -1;          // ultimo video.currentTime visto dal watchdog
let tvStalledChecks = 0;      // quante volte di fila il tempo non e' avanzato
let CLUB_LAT = null;     // coordinate della sede (Club) a cui appartiene questo dispositivo —
let CLUB_LON = null;     // usate come ripiego quando un widget non ha lat/lon scritti a mano
let CLUB_SHEET_CORSI = '';
let countdownTimers = {}; // un timer per ogni widget Countdown

const GIORNI = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
const MESI = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];
const MESI_ESTESI = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

// ── Data: widget indipendente dall'Orologio, 2 stili ─────────────
function renderDataMarkup(cfg, scale) {
  const d = new Date();
  const stile = cfg.stile || 'minimale';
  const formato = cfg.formato || 'esteso';
  const textColor = cfg.text_color || '#ffffff';
  const colNumero = cfg.colore_numero || '#F7192E';
  const mostraAnno = !!cfg.mostra_anno;

  if (stile === 'calendario') {
    const fsNum = Math.round((cfg.font_size||44)*scale*0.7);
    const fsLabel = Math.round((cfg.font_size||44)*scale*0.22);
    return `<div class="data-widget" data-stile="calendario" data-anno="${mostraAnno?1:0}" style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center">
      <div class="data-num" style="font-size:${fsNum}px;font-weight:800;color:${colNumero};line-height:1;font-variant-numeric:tabular-nums">${d.getDate()}</div>
      <div class="data-mese" style="font-size:${fsLabel}px;color:${textColor};text-transform:uppercase;letter-spacing:.05em;margin-top:${Math.round(4*scale)}px">${MESI_ESTESI[d.getMonth()]}${mostraAnno?' '+d.getFullYear():''}</div>
      <div class="data-giorno" style="font-size:${Math.round(fsLabel*0.85)}px;color:${textColor};opacity:.6;margin-top:1px">${GIORNI[d.getDay()]}</div>
    </div>`;
  }

  // minimale (default)
  const fs = Math.round((cfg.font_size||20)*scale*0.7);
  const testo = formato === 'breve'
    ? `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}${mostraAnno?'/'+d.getFullYear():''}`
    : `${GIORNI[d.getDay()]} ${d.getDate()} ${MESI_ESTESI[d.getMonth()]}${mostraAnno?' '+d.getFullYear():''}`;
  return `<div class="data-widget" data-stile="minimale" data-formato="${formato}" data-anno="${mostraAnno?1:0}" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;text-align:center">
    <div class="data-testo" style="font-size:${fs}px;font-weight:600;color:${textColor}">${testo}</div>
  </div>`;
}

// ── Pairing: mostrato quando il player non ha ancora un token ──
let pairingStarted = false;
let pairingCheckTimer = null;

// Stesso modello di Yodeck: codice alfanumerico di 8 caratteri, senza caratteri
// ambigui da leggere a distanza (niente O/0, I/1, L) — inserimento manuale, no QR.
const PAIRING_CHARS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
function generatePairingCode() {
  let code = '';
  for (let i = 0; i < 8; i++) {
    code += PAIRING_CHARS[Math.floor(Math.random() * PAIRING_CHARS.length)];
  }
  return code;
}

async function startPairing() {
  document.getElementById('stage').style.display='none';
  document.getElementById('status-screen').style.display='none';
  const screenEl = document.getElementById('pairing-screen');
  screenEl.style.display='flex';

  const code = generatePairingCode();
  const machine = 'Player-' + screen.width + 'x' + screen.height;
  document.getElementById('pairing-code').textContent = code.slice(0,4) + ' ' + code.slice(4);

  try {
    await fetch('/api/claim.php?action=register&code=' + code + '&machine=' + encodeURIComponent(machine));
  } catch (e) { /* silenzioso, il polling di check riprovera' comunque */ }

  if (pairingCheckTimer) clearInterval(pairingCheckTimer);
  pairingCheckTimer = setInterval(() => checkPairing(code), 3000);

  // Rigenera un codice nuovo allo scadere (10 minuti), cosi' lo schermo non resta bloccato
  setTimeout(() => { if (!TOKEN) startPairing(); }, 10*60*1000);
}

async function checkPairing(code) {
  try {
    const res = await fetch('/api/claim.php?action=check&code=' + code);
    const data = await res.json();
    if (data.ok && data.status === 'claimed' && data.token) {
      clearInterval(pairingCheckTimer);
      const url = new URL(window.location.href);
      url.searchParams.set('token', data.token);
      window.location.href = url.toString();
    }
  } catch (e) { /* riprova al prossimo giro */ }
}

function showStatus(text, sub='') {
  document.getElementById('status-screen').style.display='flex';
  document.getElementById('status-text').textContent = text;
  document.getElementById('status-sub').textContent = sub;
  document.getElementById('stage').style.display='none';
}
function hideStatus() {
  document.getElementById('status-screen').style.display='none';
  document.getElementById('stage').style.display='block';
}

// ── Ciclo principale: fetch stato + render ──────────────────────
async function loadState() {
  if (!TOKEN) {
    if (!pairingStarted) { pairingStarted = true; startPairing(); }
    return;
  }
  try {
    const res = await fetch(API_URL);
    const data = await res.json();
    if (!data.ok) { showStatus('Errore', data.error||''); return; }
    if (!data.template) { showStatus('Nessun template assegnato', data.messaggio||''); return; }

    hideStatus();

    // Ri-renderizza se cambia il template OPPURE se cambia il contenuto dei layer
    // (prima veniva confrontato solo l'id del template: modificare testo/colori di un
    // widget già esistente non veniva mai rilevato, nemmeno dopo il polling)
    const layersSnapshot = JSON.stringify(data.layers);
    const templateChanged = data.template.id !== currentTemplateId;
    const layersChanged = layersSnapshot !== lastLayersSnapshot;

    if (templateChanged || layersChanged) {
      currentTemplateId = data.template.id;
      lastLayersSnapshot = layersSnapshot;
      renderTemplate(data);
    }
  } catch (e) {
    showStatus('Errore di connessione', 'Riprovo tra qualche secondo…');
  }
}

// ── Widget TV: cattura video da una capture card USB (vista dal browser
// come una webcam, standard UVC). Se e' configurato un nome per questo
// dispositivo (es. "ugreen") lo cerca tra le etichette; altrimenti prende il
// primo dispositivo video disponibile — utile per un PC/Pi passivo senza
// webcam integrata, dove il primo e sempre la capture card.
async function startTvCapture(el) {
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    el.innerHTML = '<div style="color:#666;font-size:13px;text-align:center;padding:10px">Cattura video non supportata da questo browser</div>';
    return;
  }
  // Ferma un eventuale stream precedente prima di richiederne uno nuovo
  // (il decoder satellitare puo' fare un breve resync HDMI periodico: quando
  // succede il video si blocca su un fotogramma fermo/nero senza generare
  // nessun errore JS, quindi va rilevato e riavviato da un watchdog esterno)
  if (tvStream) { tvStream.getTracks().forEach(t=>t.stop()); tvStream = null; }
  if (tvWatchdogTimer) { clearInterval(tvWatchdogTimer); tvWatchdogTimer = null; }
  tvWatchdogEl = null;

  try {
    const devices = await navigator.mediaDevices.enumerateDevices();
    const videoInputs = devices.filter(d => d.kind === 'videoinput');
    if (!videoInputs.length) {
      el.innerHTML = '<div style="color:#666;font-size:13px;text-align:center;padding:10px">Nessuna capture card trovata</div>';
      return;
    }
    let scelto = videoInputs[0];
    if (CAPTURE_DEVICE) {
      const match = videoInputs.find(d => d.label.toLowerCase().includes(CAPTURE_DEVICE.toLowerCase()));
      if (match) scelto = match;
    }

    const stream = await navigator.mediaDevices.getUserMedia({
      video: { deviceId: { exact: scelto.deviceId } },
      audio: true
    });
    tvStream = stream;

    const video = document.createElement('video');
    video.autoplay = true;
    video.playsInline = true;
    video.style.cssText = 'width:100%;height:100%;object-fit:cover;background:#000';
    video.srcObject = stream;
    el.innerHTML = '';
    el.appendChild(video);

    // Se la capture card stessa droppa il segnale (es. durante il resync del
    // decoder satellitare), il browser puo' terminare la track da solo
    stream.getVideoTracks().forEach(track => {
      track.onended = () => { if (tvWatchdogEl === el) startTvCapture(el); };
    });

    startTvWatchdog(el);
  } catch (e) {
    el.innerHTML = `<div style="color:#666;font-size:12px;text-align:center;padding:10px">Errore acquisizione video<br><span style="font-size:10px;opacity:.6">${e.message}</span></div>`;
  }
}

// ── Watchdog cattura TV: ogni 5s controlla che il video stia davvero
// avanzando. Se resta fermo per 10s di fila (2 controlli), il segnale e'
// probabilmente bloccato su "non supportato" o su un fotogramma fermo: si
// riavvia la cattura da zero senza bisogno di intervento manuale.
function startTvWatchdog(el) {
  if (tvWatchdogTimer) clearInterval(tvWatchdogTimer);
  tvWatchdogEl = el;
  tvLastTime = -1;
  tvStalledChecks = 0;

  tvWatchdogTimer = setInterval(() => {
    if (tvWatchdogEl !== el || !el.isConnected) { clearInterval(tvWatchdogTimer); tvWatchdogTimer = null; return; }
    const video = el.querySelector('video');
    if (!video) return; // schermata di errore attualmente mostrata, niente da controllare

    if (video.currentTime > 0 && video.currentTime === tvLastTime) {
      tvStalledChecks++;
      if (tvStalledChecks >= 2) {
        tvStalledChecks = 0;
        startTvCapture(el);
      }
    } else {
      tvStalledChecks = 0;
    }
    tvLastTime = video.currentTime;
  }, 5000);
}

// ── Rendering del template ──────────────────────────────────────
function renderTemplate(data) {
  // Ferma timer sidebar e ADV precedenti
  Object.values(sidebarTimers).forEach(t=>clearInterval(t));
  sidebarTimers = {};
  if (clockTimer) clearInterval(clockTimer);
  Object.values(advPollTimers).forEach(t=>clearTimeout(t));
  advPollTimers = {};
  advOverlayEl = null; // il nodo verra' distrutto da stage.innerHTML='' qui sotto: azzero il riferimento
  Object.values(imgRotateTimers).forEach(t=>clearInterval(t));
  imgRotateTimers = {};
  Object.values(countdownTimers).forEach(t=>clearInterval(t));
  countdownTimers = {};
  if (tvStream) { tvStream.getTracks().forEach(t=>t.stop()); tvStream = null; }
  CAPTURE_DEVICE = (data.dispositivo && data.dispositivo.capture_device) || '';
  CLUB_LAT = (data.club_data && data.club_data.lat) || null;
  CLUB_LON = (data.club_data && data.club_data.lon) || null;
  CLUB_SHEET_CORSI = (data.club_data && data.club_data.sheet_url_corsi) || '';

  const stage = document.getElementById('stage');
  stage.innerHTML = '';
  // Font brand come default ereditato da tutti i widget — un widget con un font
  // proprio (es. Space Mono negli stili tabellone) vince comunque sull'ereditarieta'
  stage.style.fontFamily = `'${(data.brand && data.brand.font) || 'Inter'}', sans-serif`;

  const CW = data.template.canvas_w, CH = data.template.canvas_h;
  const scale = Math.min(window.innerWidth/CW, window.innerHeight/CH);
  stage.style.width  = Math.round(CW*scale)+'px';
  stage.style.height = Math.round(CH*scale)+'px';

  const sorted = [...data.layers].filter(l=>l.visible).sort((a,b)=>a.z_index-b.z_index);
  sorted.forEach(layer => {
    const el = document.createElement('div');
    el.className = 'layer';
    el.style.left   = Math.round(layer.pos_x*scale)+'px';
    el.style.top    = Math.round(layer.pos_y*scale)+'px';
    el.style.width  = Math.round(layer.width*scale)+'px';
    el.style.height = Math.round(layer.height*scale)+'px';
    // I layer col lucchetto "sempre visibile durante ADV" salgono sempre molto
    // in alto nello stack, cosi' restano sopra all'overlay ADV fullscreen
    // senza dover toccare lo z-index che l'utente ha scelto per il design normale.
    el.style.zIndex = layer.adv_safe ? (9000 + layer.z_index) : layer.z_index;
    el.dataset.layerId = layer.id;
    stage.appendChild(el);
    renderWidget(el, layer, scale, data.brand);
  });

  // Avvia orologio globale (aggiorna tutti i .live-clock ogni secondo)
  clockTimer = setInterval(updateClocks, 1000);
  updateClocks();
}

function updateClocks() {
  const now = new Date();
  const hh = String(now.getHours()).padStart(2,'0');
  const mm = String(now.getMinutes()).padStart(2,'0');
  const ss = String(now.getSeconds()).padStart(2,'0');
  const secFrac = now.getSeconds()/60;

  document.querySelectorAll('.live-clock').forEach(el=>{
    el.textContent = el.dataset.secondi==='1' ? `${hh}:${mm}:${ss}` : `${hh}:${mm}`;
  });
  document.querySelectorAll('.live-date').forEach(el=>{
    el.textContent = `${GIORNI[now.getDay()]} ${now.getDate()} ${MESI[now.getMonth()]}`;
  });

  // Widget Data indipendente — si aggiorna da solo, anche a mezzanotte, senza
  // bisogno di un ricaricamento del template
  document.querySelectorAll('.data-widget').forEach(root => {
    const mostraAnno = root.dataset.anno === '1';
    if (root.dataset.stile === 'calendario') {
      const numEl = root.querySelector('.data-num');
      const meseEl = root.querySelector('.data-mese');
      const giornoEl = root.querySelector('.data-giorno');
      if (numEl) numEl.textContent = now.getDate();
      if (meseEl) meseEl.textContent = MESI_ESTESI[now.getMonth()] + (mostraAnno ? ' ' + now.getFullYear() : '');
      if (giornoEl) giornoEl.textContent = GIORNI[now.getDay()];
    } else {
      const testoEl = root.querySelector('.data-testo');
      if (testoEl) {
        testoEl.textContent = root.dataset.formato === 'breve'
          ? `${String(now.getDate()).padStart(2,'0')}/${String(now.getMonth()+1).padStart(2,'0')}${mostraAnno?'/'+now.getFullYear():''}`
          : `${GIORNI[now.getDay()]} ${now.getDate()} ${MESI_ESTESI[now.getMonth()]}${mostraAnno?' '+now.getFullYear():''}`;
      }
    }
  });

  // Widget Time nei suoi 6 stili — stesso meccanismo dell'editor
  document.querySelectorAll('.time-widget').forEach(root => {
    const stile = root.dataset.stile;
    const hmsEl = root.querySelector('.time-hms');

    if (stile === 'flip') {
      const targetChars = `${hh}:${mm}:${ss}`.split('').filter(c=>c!==':');
      root.querySelectorAll('.time-flip-digit').forEach((el, i) => {
        const textEl = el.querySelector('.time-flip-text');
        const newVal = targetChars[i];
        if (textEl && textEl.textContent !== newVal) {
          el.style.transform = 'scaleY(.8)';
          setTimeout(()=>{ textEl.textContent = newVal; el.style.transform='scaleY(1)'; }, 90);
        }
      });
    } else if (stile === 'bar_below') {
      const fill = root.querySelector('.time-bar-fill');
      if (fill) fill.style.width = (secFrac*100)+'%';
      if (hmsEl) hmsEl.textContent = `${hh}:${mm}:${ss}`;
    } else if (stile === 'ring_seconds') {
      const ring = root.querySelector('.time-ring-progress');
      const ssEl = root.querySelector('.time-ss');
      if (ring) { const peri=+ring.dataset.peri; ring.style.strokeDashoffset = peri*(1-secFrac); }
      if (ssEl) ssEl.textContent = ss;
      const hmEl = root.querySelector('.time-hm');
      if (hmEl) hmEl.textContent = `${hh}:${mm}:`;
    } else if (stile === 'ring_pill') {
      const ring = root.querySelector('.time-ring-progress');
      if (ring) { const peri=+ring.dataset.peri; ring.style.strokeDashoffset = peri*(1-secFrac); }
      if (hmsEl) hmsEl.textContent = `${hh}:${mm}:${ss}`;
    } else if (stile === 'neon_gym') {
      if (hmsEl) hmsEl.textContent = `${hh}:${mm}:${ss}`;
    } else if (stile === 'minimal_apple') {
      // Rispetta "Mostra secondi": prima il tick sovrascriveva sempre solo hh:mm
      if (hmsEl) hmsEl.textContent = root.dataset.secondi==='1' ? `${hh}:${mm}:${ss}` : `${hh}:${mm}`;
      const dateEl = root.querySelector('.time-date');
      if (dateEl) dateEl.textContent = `${GIORNI[now.getDay()]} ${now.getDate()} ${MESI[now.getMonth()]}`;
    }
  });
}

// ── Render del widget Time (6 stili) — stessa logica dell'editor ─
function timeStrokePerimeter(w, h) {
  return 2*(w-h) + Math.PI*h;
}

function hexToRgba(hex, alpha) {
  if (!hex || hex[0] !== '#') return `rgba(255,255,255,${alpha})`;
  const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
  return `rgba(${r},${g},${b},${alpha})`;
}

// ── Meteo: icone SVG colorate per condizione (no emoji unicode, compatibili BrightSign) ─
function weatherLabel(code) {
  const map = {0:'Sereno',1:'Poco nuvoloso',2:'Parzialmente nuvoloso',3:'Nuvoloso',45:'Nebbia',48:'Nebbia',51:'Pioviggine',53:'Pioviggine',55:'Pioviggine',56:'Gelicidio',57:'Gelicidio',61:'Pioggia debole',63:'Pioggia',65:'Pioggia forte',66:'Pioggia gelata',67:'Pioggia gelata',71:'Neve debole',73:'Neve',75:'Neve forte',77:'Neve granulare',80:'Rovesci',81:'Rovesci',82:'Rovesci forti',85:'Rovesci di neve',86:'Rovesci di neve forti',95:'Temporale',96:'Temporale con grandine',99:'Temporale con grandine'};
  return map[code] || 'Variabile';
}

// ── Info/Testo: libreria icone (Font Awesome, compatibili BrightSign) ─
const INFO_ICON_LIBRARY = {
  info:'fa-circle-info', avviso:'fa-triangle-exclamation', check:'fa-circle-check', stella:'fa-star', megafono:'fa-bullhorn',
  campana:'fa-bell', calendario:'fa-calendar-days', orologio:'fa-clock', regalo:'fa-gift', percentuale:'fa-percent',
  fuoco:'fa-fire', trofeo:'fa-trophy', cuore:'fa-heart', utenti:'fa-users', pin:'fa-location-dot',
  telefono:'fa-phone', wifi:'fa-wifi', musica:'fa-music', fotocamera:'fa-camera', manubrio:'fa-dumbbell',
  medaglia:'fa-medal', bandiera:'fa-flag', lucchetto:'fa-lock', scudo:'fa-shield-halved', fulmine:'fa-bolt',
  sole:'fa-sun', luna:'fa-moon', ombrello:'fa-umbrella-beach', tazza:'fa-mug-hot', vietato:'fa-ban',
};
function infoIconClass(icona) {
  return INFO_ICON_LIBRARY[icona] || null;
}
const INFO_STYLE_DEFAULTS = {
  semplice: { titolo:15, corpo:10, icona:16 },
  banner:   { titolo:14, corpo:10, icona:28 },
  poster:   { titolo:26, corpo:12, icona:32 },
};

// ── Info/Testo: 3 stili grafici, con supporto sfondo a immagine ─
// Stessa logica dell'editor, per zero disallineamento tra anteprima e player reale.
function renderInfoMarkup(cfg, scale) {
  const stile = cfg.stile || 'semplice';
  const defaults = INFO_STYLE_DEFAULTS[stile] || INFO_STYLE_DEFAULTS.semplice;
  const titolo = cfg.titolo || '';
  const corpo = cfg.corpo || cfg.testo || (titolo ? '' : '');
  const colTitolo = cfg.colore_titolo || '#ffffff';
  const colCorpo = cfg.colore_corpo || 'rgba(255,255,255,.75)';
  const colIcona = cfg.colore_icona || '#F7192E';
  const fsTitolo = Math.round((cfg.font_size_titolo||defaults.titolo)*scale);
  const fsCorpo = Math.round((cfg.font_size_corpo||defaults.corpo)*scale);
  const fsIcona = Math.round((cfg.font_size_icona||defaults.icona)*scale);
  const iconClass = infoIconClass(cfg.icona);
  const iconHtml = cfg.icon_emoji
    ? `<span style="font-size:${fsIcona}px;line-height:1;display:block">${cfg.icon_emoji}</span>`
    : (iconClass ? `<i class="fa-solid ${iconClass}" style="font-size:${fsIcona}px;color:${colIcona};display:block"></i>` : '');
  const bgOverlay = (cfg.usa_immagine_sfondo && cfg.bg_image) ? `
    <img src="/uploads/${cfg.bg_image}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
    <div style="position:absolute;inset:0;background:#000;opacity:${cfg.bg_image_oscura??0.5}"></div>
  ` : '';

  if (stile === 'banner') {
    return `<div style="position:relative;width:100%;height:100%;display:flex;align-items:center;padding:${Math.round(12*scale)}px;overflow:hidden">
      ${bgOverlay}
      <div style="position:relative;display:flex;align-items:center;gap:${Math.round(12*scale)}px;width:100%">
        ${iconHtml?`<div style="flex-shrink:0">${iconHtml}</div>`:''}
        <div style="border-left:3px solid ${colIcona};padding-left:${Math.round(10*scale)}px;flex:1">
          ${titolo?`<div style="font-size:${fsTitolo}px;font-weight:700;color:${colTitolo}">${titolo}</div>`:''}
          ${corpo?`<div style="font-size:${fsCorpo}px;color:${colCorpo};margin-top:2px">${corpo}</div>`:''}
        </div>
      </div>
    </div>`;
  }

  if (stile === 'poster') {
    return `<div style="position:relative;width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:${Math.round(16*scale)}px;overflow:hidden">
      ${bgOverlay}
      <div style="position:relative">
        ${iconHtml?`<div style="margin-bottom:${Math.round(8*scale)}px">${iconHtml}</div>`:''}
        ${titolo?`<div style="font-size:${fsTitolo}px;font-weight:800;color:${colTitolo};letter-spacing:.02em">${titolo}</div>`:''}
        ${corpo?`<div style="font-size:${fsCorpo}px;color:${colCorpo};margin-top:${Math.round(6*scale)}px">${corpo}</div>`:''}
      </div>
    </div>`;
  }

  // semplice (default)
  return `<div style="position:relative;width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:${Math.round(10*scale)}px;overflow:hidden">
    ${bgOverlay}
    <div style="position:relative">
      ${iconHtml?`<div style="margin-bottom:4px">${iconHtml}</div>`:''}
      ${titolo?`<div style="font-size:${fsTitolo}px;font-weight:700;color:${colTitolo}">${titolo}</div>`:''}
      ${corpo?`<div style="font-size:${fsCorpo}px;color:${colCorpo};margin-top:2px">${corpo}</div>`:''}
    </div>
  </div>`;
}

function weatherIconSvg(code, isDay, size) {
  const s = size;
  if (code === 0) {
    return isDay
      ? `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5" fill="#FDB813"/><g stroke="#FDB813" stroke-width="1.8" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.2" y1="4.2" x2="5.6" y2="5.6"/><line x1="18.4" y1="18.4" x2="19.8" y2="19.8"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.2" y1="19.8" x2="5.6" y2="18.4"/><line x1="18.4" y1="5.6" x2="19.8" y2="4.2"/></g></svg>`
      : `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M20 14.5A8.5 8.5 0 019.5 4 8.5 8.5 0 1020 14.5z" fill="#B8C4E0"/></svg>`;
  }
  if (code === 1 || code === 2) {
    return isDay
      ? `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><circle cx="9" cy="9" r="4.5" fill="#FDB813"/><path d="M6 20a4.5 4.5 0 01.4-9 5.5 5.5 0 0110.5 1.8A4 4 0 0118 20H6z" fill="#CBD5E1"/></svg>`
      : `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M15 11a5 5 0 01-4.6-7 5 5 0 106.2 6.8A5 5 0 0115 11z" fill="#B8C4E0"/><path d="M6 20a4.5 4.5 0 01.4-9 5.5 5.5 0 0110.5 1.8A4 4 0 0118 20H6z" fill="#CBD5E1"/></svg>`;
  }
  if (code === 3) {
    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M6 20a4.5 4.5 0 01.4-9 5.5 5.5 0 0110.5 1.8A4 4 0 0118 20H6z" fill="#94A3B8"/></svg>`;
  }
  if (code === 45 || code === 48) {
    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><g stroke="#94A3B8" stroke-width="2" stroke-linecap="round"><line x1="3" y1="8" x2="21" y2="8"/><line x1="3" y1="13" x2="21" y2="13"/><line x1="3" y1="18" x2="17" y2="18"/></g></svg>`;
  }
  if ([51,53,55,56,57,61,63,65,66,67,80,81,82].includes(code)) {
    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M6 15a4.5 4.5 0 01.3-9 5.5 5.5 0 0110.6 1.7A4 4 0 0118 15H6z" fill="#94A3B8"/><g stroke="#3B82F6" stroke-width="1.8" stroke-linecap="round"><line x1="8" y1="18" x2="7" y2="21"/><line x1="12" y1="18" x2="11" y2="21"/><line x1="16" y1="18" x2="15" y2="21"/></g></svg>`;
  }
  if ([71,73,75,77,85,86].includes(code)) {
    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M6 15a4.5 4.5 0 01.3-9 5.5 5.5 0 0110.6 1.7A4 4 0 0118 15H6z" fill="#94A3B8"/><g fill="#E0F2FE"><circle cx="8" cy="19" r="1.3"/><circle cx="12" cy="20.5" r="1.3"/><circle cx="16" cy="19" r="1.3"/></g></svg>`;
  }
  if ([95,96,99].includes(code)) {
    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M6 14a4.5 4.5 0 01.3-9 5.5 5.5 0 0110.6 1.7A4 4 0 0118 14H6z" fill="#64748B"/><path d="M13 14l-3 5h2.5l-1.5 4 4-6h-2.5l1.5-3z" fill="#FBBF24"/></svg>`;
  }
  return `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M6 20a4.5 4.5 0 01.4-9 5.5 5.5 0 0110.5 1.8A4 4 0 0118 20H6z" fill="#94A3B8"/></svg>`;
}

function renderTimeWidget(el, cfg, scale) {
  const stile = cfg.stile || 'ring_pill';
  const textColor = cfg.text_color || '#ffffff';
  const progressColor = cfg.colore_progresso || '#F7192E';
  const now = new Date();
  const hh = String(now.getHours()).padStart(2,'0');
  const mm = String(now.getMinutes()).padStart(2,'0');
  const ss = String(now.getSeconds()).padStart(2,'0');
  const dateStr = `${GIORNI[now.getDay()]} ${now.getDate()} ${MESI[now.getMonth()]}`;
  const showSeconds = ['ring_pill','flip','bar_below','ring_seconds'].includes(stile) ? true : (cfg.mostra_secondi||false);
  const hms = showSeconds ? `${hh}:${mm}:${ss}` : `${hh}:${mm}`;
  const fsOra = Math.round((cfg.font_size_ora||44)*scale);
  const fsData = Math.round((cfg.font_size_data||20)*scale);
  const mostraData = cfg.mostra_data!==false;

  if (stile === 'ring_pill') {
    const W = 200, H = 80;
    const strokeW = 5;
    const peri = timeStrokePerimeter(W-8, H-8);
    const fillInset = 4 + strokeW/2 + 3;
    const bgFill = (cfg.sfondo_ora && cfg.sfondo_ora!=='transparent') ? cfg.sfondo_ora : '#111111';
    const bgOpacity = cfg.sfondo_ora_opacity ?? 0.6;
    el.innerHTML = `<div class="time-widget" data-stile="ring_pill" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center">
      <div style="position:relative;width:100%;height:100%;display:flex;align-items:center;justify-content:center">
        <svg viewBox="0 0 ${W} ${H}" style="position:absolute;inset:0;width:100%;height:100%">
          <rect x="${fillInset}" y="${fillInset}" width="${W-fillInset*2}" height="${H-fillInset*2}" rx="${(H-fillInset*2)/2}" fill="${bgFill}" opacity="${bgOpacity}"/>
          <rect x="4" y="4" width="${W-8}" height="${H-8}" rx="${(H-8)/2}" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="${strokeW}"/>
          <rect class="time-ring-progress" data-peri="${peri}" x="4" y="4" width="${W-8}" height="${H-8}" rx="${(H-8)/2}" fill="none" stroke="${progressColor}" stroke-width="${strokeW}" stroke-dasharray="${peri}" stroke-dashoffset="${peri}"/>
        </svg>
        <span class="time-hms" style="position:relative;font-size:${fsOra}px;font-weight:700;color:${textColor};font-variant-numeric:tabular-nums">${hms}</span>
      </div>
    </div>`;
  } else if (stile === 'flip') {
    const chars = hms.split('');
    const bgFill = cfg.sfondo_ora || '#141414';
    const bgOpacity = cfg.sfondo_ora_opacity ?? 1;
    el.innerHTML = `<div class="time-widget" data-stile="flip" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;gap:${Math.round(4*scale)}px">
      ${chars.map(ch => ch===':'
        ? `<span style="font-size:${fsOra}px;font-weight:700;color:${textColor};opacity:.5">:</span>`
        : `<div class="time-flip-digit" style="position:relative;border-radius:6px;padding:${Math.round(4*scale)}px ${Math.round(8*scale)}px;transition:transform .15s ease">
             <div style="position:absolute;inset:0;background:${bgFill};opacity:${bgOpacity};border-radius:6px"></div>
             <span class="time-flip-text" style="position:relative;color:${textColor};font-size:${fsOra}px;font-weight:800;font-variant-numeric:tabular-nums">${ch}</span>
           </div>`
      ).join('')}
    </div>`;
  } else if (stile === 'bar_below') {
    const colorA = cfg.colore_barra_a || '#22c55e';
    const colorB = cfg.colore_barra_b || '#2dd4bf';
    const glowColor = cfg.sfondo_ora || null;
    const glowOpacity = cfg.sfondo_ora_opacity ?? 0.7;
    const glowShadow = glowColor ? `text-shadow:0 0 ${Math.round(10*scale)}px ${hexToRgba(glowColor,glowOpacity)},0 0 ${Math.round(22*scale)}px ${hexToRgba(glowColor,glowOpacity*0.6)};` : '';
    el.innerHTML = `<div class="time-widget" data-stile="bar_below" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center">
      <div style="text-align:center;width:80%">
        <div class="time-hms" style="font-size:${fsOra}px;font-weight:700;color:${textColor};font-variant-numeric:tabular-nums;${glowShadow}">${hms}</div>
        <div style="width:100%;height:${Math.round(6*scale)}px;background:rgba(255,255,255,.15);border-radius:999px;margin-top:${Math.round(6*scale)}px;overflow:hidden">
          <div class="time-bar-fill" style="height:100%;width:0%;background:linear-gradient(90deg,${colorA},${colorB});border-radius:999px"></div>
        </div>
      </div>
    </div>`;
  } else if (stile === 'ring_seconds') {
    const ringPx = Math.max(60, Math.round(fsOra*2.4));
    const fsHm = Math.round(fsOra*0.75);
    el.innerHTML = `<div class="time-widget" data-stile="ring_seconds" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;gap:${Math.round(8*scale)}px">
      <span class="time-hm" style="font-size:${fsHm}px;font-weight:600;color:${textColor};font-variant-numeric:tabular-nums">${hh}:${mm}:</span>
      <div style="position:relative;width:${ringPx}px;height:${ringPx}px;flex-shrink:0">
        <svg viewBox="0 0 100 100" style="width:100%;height:100%;transform:rotate(-90deg)">
          <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="8"/>
          <circle class="time-ring-progress" data-peri="${2*Math.PI*42}" cx="50" cy="50" r="42" fill="none" stroke="${progressColor}" stroke-width="8" stroke-linecap="round" stroke-dasharray="${2*Math.PI*42}" stroke-dashoffset="${2*Math.PI*42}"/>
        </svg>
        <span class="time-ss" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:${fsOra}px;font-weight:700;color:${textColor};font-variant-numeric:tabular-nums">${ss}</span>
      </div>
    </div>`;
  } else if (stile === 'neon_gym') {
    el.innerHTML = `<div class="time-widget" data-stile="neon_gym" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center">
      <div style="text-align:center;padding:${Math.round(10*scale)}px ${Math.round(18*scale)}px;border-radius:14px;background:${cfg.sfondo_ora||'#0a0a0a'}">
        <span class="time-hms" style="font-size:${fsOra}px;font-weight:800;color:${progressColor};text-shadow:0 0 6px ${progressColor},0 0 16px ${progressColor};letter-spacing:1px;font-variant-numeric:tabular-nums;animation:neonPulse 2s ease-in-out infinite">${hms}</span>
      </div>
    </div>`;
  } else {
    // minimal_apple
    const minimalBg = cfg.sfondo_ora || '#000000';
    const minimalOpacity = cfg.sfondo_ora_opacity ?? 0.5;
    el.innerHTML = `<div class="time-widget" data-stile="minimal_apple" data-secondi="${showSeconds?1:0}" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center">
      <div style="position:relative;text-align:center;padding:${Math.round(14*scale)}px ${Math.round(22*scale)}px;border-radius:14px">
        <div style="position:absolute;inset:0;background:${minimalBg};opacity:${minimalOpacity};border-radius:14px"></div>
        <div style="position:relative">
          <div class="time-hms" style="font-size:${fsOra}px;font-weight:300;letter-spacing:2px;color:${textColor};font-variant-numeric:tabular-nums">${hms}</div>
          ${mostraData ? `<div class="time-date" style="font-size:${fsData}px;font-weight:400;color:${textColor};opacity:.55;margin-top:4px;letter-spacing:1px">${dateStr}</div>` : ''}
        </div>
      </div>
    </div>`;
  }
}

// ── Render di un singolo widget dentro il suo layer ─────────────
function renderWidget(layerEl, layer, scale, brand) {
  const cfg = layer.config || {};

  // Sfondo (colore o gradiente) SEPARATO dal contenuto — l'opacità qui
  // non deve mai scurire testo/numeri sopra (bug corretto: prima opacity
  // veniva applicata all'intero layer, testo compreso).
  let bg = cfg.bg_color || '#111';
  if (cfg.gradient_type && cfg.grad_a && cfg.grad_b) {
    bg = `linear-gradient(${cfg.gradient_dir||'to right'}, ${cfg.grad_a}, ${cfg.grad_b})`;
  }
  layerEl.innerHTML = `<div class="wbg" style="position:absolute;inset:0;background:${bg};opacity:${cfg.bg_opacity??1}"></div><div class="wcontent" style="position:relative;width:100%;height:100%"></div>`;

  // Da qui in poi 'el' punta al contenuto (sempre opacità piena), non allo sfondo
  const el = layerEl.querySelector('.wcontent');

  switch (layer.widget_type) {

    case 'logo': {
      const file = cfg.file || brand.logo;
      el.style.display='flex'; el.style.alignItems='center';
      el.style.justifyContent = cfg.align==='left'?'flex-start':(cfg.align==='right'?'flex-end':'center');
      if (file) {
        const img = document.createElement('img');
        img.src = '/uploads/' + file;
        img.style.cssText = `max-width:${cfg.logo_size||80}%;max-height:${cfg.logo_size||80}%;object-fit:contain`;
        el.appendChild(img);
      }
      break;
    }

    case 'time': {
      el.style.display='flex'; el.style.alignItems='center'; el.style.justifyContent='center';
      renderTimeWidget(el, cfg, scale);
      break;
    }

    case 'data': {
      el.innerHTML = renderDataMarkup(cfg, scale);
      break;
    }

    case 'immagine': {
      const imgFiles = (cfg.files && cfg.files.length) ? cfg.files : (cfg.file ? [cfg.file] : []);
      if (!imgFiles.length) break;
      const fit = cfg.object_fit || 'cover';
      let imgIdx = 0;
      const showImg = (i) => {
        el.innerHTML = `<img src="/uploads/${imgFiles[i]}" style="width:100%;height:100%;object-fit:${fit};animation:fadeIn .6s ease">`;
      };
      showImg(0);
      // Rotazione reale se ci sono piu' immagini — con dissolvenza ad ogni cambio
      if (imgFiles.length > 1) {
        const durataMs = Math.max(2, cfg.durata_slide || 5) * 1000;
        imgRotateTimers[layer.id] = setInterval(() => {
          imgIdx = (imgIdx + 1) % imgFiles.length;
          showImg(imgIdx);
        }, durataMs);
      }
      break;
    }

    case 'info': {
      el.innerHTML = renderInfoMarkup(cfg, scale);
      break;
    }

    case 'ticker': {
      const testi = (cfg.testi||[]).filter(Boolean);
      const testoUnico = testi.length ? testi.join('   ·   ') : '';
      el.style.background = cfg.bg_ticker || '#F7192E';
      el.style.display='flex'; el.style.alignItems='center';
      const speed = Math.max(1.5, 400/(cfg.velocita||60));
      el.innerHTML = `<div style="font-size:${Math.round((cfg.font_size||20)*scale)}px;color:${cfg.text_color||'#fff'};font-weight:600;white-space:nowrap;position:absolute;left:0;animation:tickerScrollRTL ${speed}s linear infinite">${testoUnico}</div>`;
      break;
    }

    case 'qrcode': {
      el.style.display='flex'; el.style.flexDirection='column';
      el.style.alignItems='center'; el.style.justifyContent='center';
      const pct = (cfg.dimensione_qr || 70) / 100;
      const size = Math.round(Math.min(layer.width, layer.height) * scale * pct);
      const colTitolo = cfg.colore_titolo || 'rgba(255,255,255,.8)';
      const fsTitolo = Math.round((cfg.font_size_titolo || 16) * scale);
      if (cfg.url) {
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(cfg.url)}&color=${(cfg.colore_qr||'#000000').replace('#','')}&bgcolor=${(cfg.sfondo_qr||'#ffffff').replace('#','')}`;
        el.innerHTML = `<img src="${qrUrl}" style="width:${size}px;height:${size}px;border-radius:6px">`;
        if (cfg.titolo) el.innerHTML += `<div style="font-size:${fsTitolo}px;color:${colTitolo};margin-top:6px">${cfg.titolo}</div>`;
      }
      break;
    }

    case 'countdown': {
      const stile = cfg.stile || 'classico';
      const numColor = cfg.colore_numeri || '#fff';
      const titleColor = cfg.colore_titolo || 'rgba(255,255,255,.7)';
      const onExpiry = cfg.on_expiry || 'zero';
      const layerBox = el.closest('.layer');

      el.style.display='flex'; el.style.flexDirection='column';
      el.style.alignItems='center'; el.style.justifyContent='center';
      el.innerHTML = `
        ${cfg.titolo?`<div class="cd-titolo" style="font-size:${Math.round(14*scale)}px;color:${titleColor};margin-bottom:6px">${cfg.titolo}</div>`:''}
        <div class="countdown-nums" style="display:flex;gap:${Math.round(10*scale)}px"></div>
      `;
      const numsEl = el.querySelector('.countdown-nums');
      const target = cfg.data_target ? new Date(cfg.data_target) : null;
      const tileSize = Math.round(26*scale);
      let prevVals = null;
      let expired = false;

      function buildClassicGroup(v, label) {
        return `<div style="text-align:center"><div style="font-size:${Math.round(28*scale)}px;font-weight:700;color:${numColor};font-variant-numeric:tabular-nums">${String(v).padStart(2,'0')}</div><div style="font-size:${Math.round(9*scale)}px;color:rgba(255,255,255,.5)">${label}</div></div>`;
      }
      function buildFlipGroup(v, label) {
        return `<div style="text-align:center"><div class="flip-tiles" style="display:flex;gap:2px">${makeFlapTiles(String(v).padStart(2,'0'), tileSize, `background:#141414;color:${numColor}`)}</div><div style="font-size:${Math.round(9*scale)}px;color:rgba(255,255,255,.5);margin-top:4px;text-transform:uppercase;letter-spacing:.05em">${label}</div></div>`;
      }

      function handleExpiry() {
        expired = true;
        if (onExpiry === 'nascondi') {
          if (layerBox) layerBox.style.display = 'none';
        } else if (onExpiry === 'testo') {
          el.innerHTML = `<div style="text-align:center;font-size:${Math.round(18*scale)}px;font-weight:700;color:${numColor}">${cfg.testo_scadenza || 'Scaduto'}</div>`;
        }
        // 'zero': i numeri restano gia' fermi a 00 dal tick precedente, non serve altro
        if (countdownTimers[layer.id]) { clearInterval(countdownTimers[layer.id]); delete countdownTimers[layer.id]; }
      }

      function tick() {
        if (!target) { numsEl.textContent=''; return; }
        const diff = target - new Date();
        if (diff <= 0 && !expired) {
          // Ultimo giro: mostra 00 ovunque, poi applica il comportamento di scadenza
          const zeroVals = cfg.mostra_secondi ? [0,0,0,0] : [0,0,0];
          const labels = cfg.mostra_secondi ? ['giorni','ore','min','sec'] : ['giorni','ore','min'];
          numsEl.innerHTML = zeroVals.map((v,i)=>stile==='flip'?buildFlipGroup(v,labels[i]):buildClassicGroup(v,labels[i])).join('');
          handleExpiry();
          return;
        }
        if (expired) return;
        const gg = Math.floor(diff/86400000);
        const hh = Math.floor(diff%86400000/3600000);
        const mm = Math.floor(diff%3600000/60000);
        const ss = Math.floor(diff%60000/1000);
        const vals = cfg.mostra_secondi ? [gg,hh,mm,ss] : [gg,hh,mm];
        const labels = cfg.mostra_secondi ? ['giorni','ore','min','sec'] : ['giorni','ore','min'];

        if (stile === 'flip') {
          if (!prevVals || prevVals.length !== vals.length) {
            numsEl.innerHTML = vals.map((v,i)=>buildFlipGroup(v,labels[i])).join('');
          } else {
            // Aggiorna solo le tessere il cui valore e' davvero cambiato,
            // cosi' lo scatto di ingresso non riparte su cifre che non si muovono
            vals.forEach((v,i) => {
              if (v !== prevVals[i]) {
                const group = numsEl.children[i];
                const tiles = group && group.querySelector('.flip-tiles');
                if (tiles) tiles.innerHTML = makeFlapTiles(String(v).padStart(2,'0'), tileSize, `background:#141414;color:${numColor}`);
              }
            });
          }
        } else {
          numsEl.innerHTML = vals.map((v,i)=>buildClassicGroup(v,labels[i])).join('');
        }
        prevVals = vals;
      }
      tick();
      // Prima questo interval non veniva mai fermato: ogni volta che il template si
      // ri-renderizzava (e ora succede piu' spesso, dopo la fix del polling), se ne
      // accumulava uno nuovo in background senza mai fermare i precedenti.
      countdownTimers[layer.id] = setInterval(tick, 1000);
      break;
    }

    case 'meteo': {
      el.style.display='flex'; el.style.alignItems='center'; el.style.justifyContent='center';
      el.innerHTML = `<div class="meteo-content" style="text-align:center;font-size:${Math.round(12*scale)}px;color:${cfg.text_color||'#fff'}">Caricamento meteo…</div>`;
      if (cfg.lat && cfg.lon) loadMeteo(el, cfg, scale);
      break;
    }

    case 'corsi': {
      el.innerHTML = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.4);font-size:${Math.round(11*scale)}px">Caricamento corsi…</div>`;
      // Va chiamata sempre: se il widget non ha un foglio proprio, loadCorsi usa
      // comunque il fallback di sede (CLUB_SHEET_CORSI) al suo interno.
      loadCorsi(el, cfg, scale);
      break;
    }

    case 'streaming': {
      el.style.background = '#000';
      if (cfg.url) {
        const video = document.createElement('video');
        video.style.cssText = 'width:100%;height:100%;object-fit:contain';
        video.autoplay = true; video.muted = true; video.playsInline = true;
        el.appendChild(video);

        let hlsInstance = null;
        let lastTime = -1;
        let stallCount = 0;

        function avviaStream() {
          if (hlsInstance) {
            try { hlsInstance.destroy(); } catch(e) {}
            hlsInstance = null;
          }
          if (Hls.isSupported()) {
            hlsInstance = new Hls({
              maxBufferLength: 30,
              maxMaxBufferLength: 60,
              liveSyncDurationCount: 3
            });
            hlsInstance.loadSource(cfg.url);
            hlsInstance.attachMedia(video);
            hlsInstance.on(Hls.Events.ERROR, function(event, data) {
              if (data.fatal) {
                console.log('HLS errore fatale, riavvio stream:', data.type);
                setTimeout(avviaStream, 3000);
              }
            });
          } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = cfg.url;
          }
        }

        avviaStream();

        setInterval(function() {
          if (video.currentTime === lastTime && !video.paused) {
            stallCount++;
            console.log('Streaming: possibile freeze, tentativo', stallCount);
            if (stallCount >= 3) {
              console.log('Streaming: freeze confermato, riavvio HLS');
              stallCount = 0;
              avviaStream();
            }
          } else {
            stallCount = 0;
          }
          lastTime = video.currentTime;
        }, 10000);
      }
      break;
    }

    case 'sidebar': {
      renderSidebar(el, layer, cfg, scale);
      break;
    }

    // ── Placeholder: da collegare quando verifichiamo lo schema playlist/tuner ──
    case 'adv':
      el.style.display='flex'; el.style.alignItems='center'; el.style.justifyContent='center';
      el.innerHTML = '';
      pollAdvStatus(el, layer, scale);
      break;

    case 'tv':
      el.style.display='flex'; el.style.alignItems='center'; el.style.justifyContent='center'; el.style.background='#000';
      startTvCapture(el);
      break;

    default:
      el.style.display='flex'; el.style.alignItems='center'; el.style.justifyContent='center'; el.style.color='rgba(255,255,255,.5)';
      el.textContent = layer.widget_type;
  }
}

// ── Corsi Live: legge il Google Sheet via il proxy CSV esistente ─
// Supporta 3 stili grafici (lista/lobby/stazione), stessa logica dell'editor.
// ── Meteo: fetch reale con refresh automatico ogni 10 minuti ────
// Corregge il bug "temperatura sbagliata": prima veniva scaricata una sola volta
// e non si aggiornava mai piu durante il giorno.
// ── ADV Player: polling in tempo reale delle regole da /adv.php ────
// A differenza degli altri widget, ADV non ha una configurazione propria nel
// layer: le regole (playlist, giorni, fascia oraria, intervallo, fullscreen)
// vivono nella pagina ADV & Scheduling e sono legate al DISPOSITIVO, non al
// singolo layer. Questo widget e' solo il punto in cui il contenuto appare.
async function pollAdvStatus(el, layer, scale) {
  let nextPollMs = 10000;
  try {
    const res = await fetch('/api/adv_status.php?token=' + encodeURIComponent(TOKEN));
    const data = await res.json();

    if (!data.ok || data.modalita === 'tv') {
      el.innerHTML = '';
      hideAdvOverlay();
      nextPollMs = data.secondi_alla_adv ? Math.min(Math.max(data.secondi_alla_adv,2)*1000, 30000) : 10000;
    } else if (data.modalita === 'adv' && data.contenuto) {
      const c = data.contenuto;
      if (data.fullscreen) {
        el.innerHTML = ''; // il layer stesso resta vuoto: il contenuto va nell'overlay condiviso
        showAdvOverlay(c);
      } else {
        hideAdvOverlay();
        renderAdvContent(el, c);
      }
      nextPollMs = Math.max(2000, (c.secondi_rimanenti || 5) * 1000);
    }
  } catch(e) {
    nextPollMs = 15000;
  }
  advPollTimers[layer.id] = setTimeout(() => pollAdvStatus(el, layer, scale), nextPollMs);
}

function renderAdvContent(el, c) {
  if (c.tipo === 'video') {
    el.innerHTML = `<video src="/uploads/${c.file}" autoplay muted loop playsinline style="width:100%;height:100%;object-fit:cover;background:#000"></video>`;
  } else {
    el.innerHTML = `<img src="/uploads/${c.file}" style="width:100%;height:100%;object-fit:cover">`;
  }
}

function showAdvOverlay(c) {
  if (!advOverlayEl) {
    advOverlayEl = document.createElement('div');
    advOverlayEl.id = 'adv-fullscreen-overlay';
    advOverlayEl.style.cssText = 'position:absolute;inset:0;z-index:8000;background:#000;display:flex;align-items:center;justify-content:center;overflow:hidden';
    const stageEl = document.getElementById('stage');
    if (stageEl) stageEl.appendChild(advOverlayEl);
  }
  renderAdvContent(advOverlayEl, c);
  advOverlayEl.style.display = 'flex';
}

function hideAdvOverlay() {
  if (advOverlayEl) advOverlayEl.style.display = 'none';
}

async function loadMeteo(el, cfg, scale) {
  // Se il widget non ha lat/lon scritti a mano, usa quelli della sede (Club) a
  // cui appartiene questo dispositivo — cosi' lo stesso template duplicato su
  // piu' sedi mostra il meteo giusto per ciascuna, senza doverlo configurare
  // widget per widget.
  const lat = cfg.lat || CLUB_LAT;
  const lon = cfg.lon || CLUB_LON;
  if (!lat || !lon) {
    el.innerHTML = `<div style="text-align:center;font-size:${Math.round(11*scale)}px;color:rgba(255,255,255,.4)">Meteo non configurato<br><span style="font-size:${Math.round(9*scale)}px;opacity:.7">Imposta le coordinate sul widget o sulla sede</span></div>`;
    // Riprova comunque tra 10 minuti, nel caso le coordinate vengano aggiunte
    // alla sede dopo — altrimenti resterebbe bloccato su questo messaggio per
    // sempre, anche quando il dato diventa disponibile.
    setTimeout(()=>loadMeteo(el, cfg, scale), 10*60*1000);
    return;
  }
  try {
    const res = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,weather_code,is_day&daily=temperature_2m_max,temperature_2m_min&timezone=auto`);
    const w = await res.json();
    const temp = Math.round(w.current?.temperature_2m ?? 0);
    const code = w.current?.weather_code ?? 0;
    const isDay = w.current?.is_day === 1;
    const tempMax = Math.round(w.daily?.temperature_2m_max?.[0] ?? temp);
    const tempMin = Math.round(w.daily?.temperature_2m_min?.[0] ?? temp);

    el.innerHTML = buildMeteoMarkup({temp, code, isDay, tempMax, tempMin}, cfg, scale);
  } catch(e) {
    const contentEl = el.querySelector('.meteo-content') || el;
    contentEl.innerHTML = `<div style="text-align:center;font-size:${Math.round(11*scale)}px;color:rgba(255,255,255,.4)">Meteo non disponibile</div>`;
  }
  // Aggiorna ogni 10 minuti — questo e' il fix per la temperatura che restava vecchia
  setTimeout(()=>loadMeteo(el, cfg, scale), 10*60*1000);
}

function buildMeteoMarkup(data, cfg, scale) {
  const { temp, code, isDay, tempMax, tempMin } = data;
  const stile = cfg.stile || 'card';
  const textColor = cfg.text_color || '#ffffff';
  const fsTemp = Math.round((cfg.font_size_temp||32)*scale);
  const showDesc = cfg.mostra_descrizione !== false;
  const label = weatherLabel(code);
  // Icona e testo secondario proporzionali a fsTemp: crescono insieme alla temperatura
  const iconPxCard = Math.round(fsTemp*1.15);
  const iconPxDett = Math.round(fsTemp*1.25);
  const iconPxMin  = Math.round(fsTemp*0.85);
  const descPx = Math.round(fsTemp*0.35);
  const smallPx = Math.round(fsTemp*0.31);

  if (stile === 'minimal') {
    return `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;gap:${Math.round(8*scale)}px;color:${textColor}">
      ${weatherIconSvg(code, isDay, iconPxMin)}
      <span style="font-size:${fsTemp}px;font-weight:700">${temp}°</span>
    </div>`;
  }

  if (stile === 'dettagliato') {
    return `<div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:${textColor}">
      ${weatherIconSvg(code, isDay, iconPxDett)}
      <div style="font-size:${fsTemp}px;font-weight:700;margin-top:4px">${temp}°C</div>
      ${showDesc?`<div style="font-size:${descPx}px;opacity:.7">${label}</div>`:''}
      <div style="font-size:${smallPx}px;opacity:.5;margin-top:3px">↑${tempMax}° ↓${tempMin}°</div>
      <div style="font-size:${smallPx}px;opacity:.6;margin-top:2px">${cfg.citta||''}</div>
    </div>`;
  }

  // card (default)
  return `<div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:${textColor}">
    ${weatherIconSvg(code, isDay, iconPxCard)}
    <div style="font-size:${fsTemp}px;font-weight:700;margin-top:2px">${temp}°C</div>
    ${showDesc?`<div style="font-size:${descPx}px;opacity:.7">${label}</div>`:''}
    <div style="font-size:${smallPx}px;opacity:.6;margin-top:2px">${cfg.citta||''}</div>
  </div>`;
}

async function loadCorsi(el, cfg, scale) {
  // Se il widget non ha un foglio Google scritto a mano, usa quello della
  // sede (Club) a cui appartiene questo dispositivo — stessa logica del Meteo.
  const sheetUrl = cfg.sheet_url || CLUB_SHEET_CORSI;
  if (!sheetUrl) {
    el.innerHTML = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.4);font-size:${Math.round(11*scale)}px;text-align:center;padding:10px">Corsi non configurati<br><span style="font-size:${Math.round(9*scale)}px;opacity:.7">Imposta il foglio Google sul widget o sulla sede</span></div>`;
    return;
  }
  try {
    const res = await fetch('/api/csv_proxy.php?url=' + encodeURIComponent(sheetUrl));
    const csvText = await res.text();
    const rows = csvText.trim().split('\n').map(r=>r.split(','));
    const header = rows[0].map(h=>h.trim().toLowerCase());
    // Alias per fogli che usano nomi di colonna diversi (es. "Orario" invece di
    // "Ora", "Studio" invece di "Sala") — proviamo piu' varianti in ordine.
    const trovaColonna = (varianti) => {
      for (const v of varianti) { const i = header.indexOf(v); if (i>-1) return i; }
      return -1;
    };
    const idxGiorno = trovaColonna(['giorno']);
    const idxOra = trovaColonna(['ora','orario']);
    const idxCorso = trovaColonna(['corso']);
    const idxIstruttore = trovaColonna(['istruttore']);
    const idxSala = trovaColonna(['sala','studio']);
    const idxDurata = trovaColonna(['durata']);

    const maxCorsi = cfg.max_corsi || 4;
    const nowMin = new Date().getHours()*60 + new Date().getMinutes();

    // Se il foglio ha una colonna Giorno (palinsesto settimanale con tutti i
    // giorni nello stesso foglio), teniamo solo le righe di oggi — altrimenti
    // mostrerebbe sempre le prime righe del foglio, cioe' sempre lo stesso giorno.
    const giorniIt = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
    const oggiNome = giorniIt[new Date().getDay()];
    let sourceRows = rows.slice(1).filter(r=>r.length>1);
    if (idxGiorno > -1) {
      sourceRows = sourceRows.filter(r => (r[idxGiorno]||'').trim().toLowerCase() === oggiNome.toLowerCase());
    }

    // Stessa logica del vecchio player: parsiamo tutti i corsi di oggi con il
    // loro orario in minuti e la durata, poi teniamo solo quelli non ancora
    // finiti (inizio + durata > adesso) e li ordiniamo per orario.
    let tutti = sourceRows.map(r => {
      const oraStr = idxOra>-1 ? (r[idxOra]||'').trim() : '';
      const [hh,mm] = oraStr.split(':').map(Number);
      const corsoMin = (hh||0)*60 + (mm||0);
      const durata = idxDurata>-1 ? (parseInt((r[idxDurata]||'').trim()) || 60) : 60;
      return {
        ora: oraStr,
        corsoMin,
        durata,
        corso: idxCorso>-1 ? (r[idxCorso]||'').trim() : '',
        istruttore: idxIstruttore>-1 ? (r[idxIstruttore]||'').trim() : '',
        sala: idxSala>-1 ? (r[idxSala]||'').trim() : '',
      };
    });
    tutti.sort((a,b) => a.corsoMin - b.corsoMin);
    const nonFiniti = tutti.filter(c => (c.corsoMin + c.durata) > nowMin);

    let corsi = [];
    if (nonFiniti.length) {
      // Trova il corso live (se c'e') per centrare la finestra di 5 su di lui,
      // come faceva il vecchio player — cosi' si vede sempre cosa sta
      // succedendo ora e cosa viene subito dopo, non solo l'inizio giornata.
      let attivoIdx = -1;
      nonFiniti.forEach((c,i) => { if (c.corsoMin <= nowMin && nowMin < c.corsoMin + c.durata) attivoIdx = i; });
      let start = Math.max(0, attivoIdx >= 0 ? attivoIdx - 1 : 0);
      let end = Math.min(nonFiniti.length, start + maxCorsi);
      if (end - start < maxCorsi) start = Math.max(0, end - maxCorsi);

      corsi = nonFiniti.slice(start, end).map(c => {
        const attivo = c.corsoMin <= nowMin && nowMin < c.corsoMin + c.durata;
        return { ora: c.ora, corso: c.corso, istruttore: c.istruttore, sala: c.sala, stato: attivo ? 'attivo' : 'prossimo' };
      });
    }

    el.innerHTML = buildCorsiMarkup(corsi, cfg, scale);
  } catch(e) {
    el.innerHTML = `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.4);font-size:${Math.round(11*scale)}px">Impossibile caricare i corsi</div>`;
  }
  // Ricarica ogni 5 minuti (il palinsesto può cambiare durante il giorno)
  setTimeout(()=>loadCorsi(el, cfg, scale), 5*60*1000);
}

// ── Tessere flip stile tabellone stazione (stessa identita visiva dell'Orologio) ─
function makeFlapTiles(text, tileSize, extraStyle='') {
  return text.toUpperCase().split('').map((ch,i) => {
    if (ch === ' ') return `<span style="display:inline-block;width:${Math.round(tileSize*0.5)}px"></span>`;
    return `<div class="flap-tile" style="width:${Math.round(tileSize*1.3)}px;height:${Math.round(tileSize*1.6)}px;font-size:${tileSize}px;animation-delay:${i*30}ms;${extraStyle}">${ch}</div>`;
  }).join('');
}

function renderFlapRow(c, i, opts) {
  const { corsoColor, orarioColor, badgeColor, badgeTxt, showBadge, cols, tileSize, scaleFactor, colorTestoLive } = opts;
  const isLive = c.stato === 'attivo';
  const bg = isLive ? hexToRgba(badgeColor,0.7) : '#141414';
  const txtColor = isLive ? (colorTestoLive||'#000000') : corsoColor;
  const oraTiles = makeFlapTiles(c.ora, Math.round(tileSize*0.85), `background:${isLive?badgeColor:'#141414'};color:${isLive?(colorTestoLive||'#000000'):orarioColor};margin-right:2px`);
  const corsoTiles = makeFlapTiles(c.corso, tileSize, `background:${bg};color:${txtColor};margin-right:2px`);
  return `<div style="display:flex;align-items:center;gap:${Math.round(6*scaleFactor)}px;padding:${Math.round(4*scaleFactor)}px 0;margin-bottom:${Math.round(3*scaleFactor)}px;${c.stato==='passato'?'opacity:.35;':''}">
    <div style="display:flex;flex-shrink:0;margin-right:${Math.round(14*scaleFactor)}px">${oraTiles}</div>
    <div style="display:flex;flex-wrap:wrap;flex:1">${corsoTiles}</div>
    ${cols.includes('sala')?`<span style="font-size:${Math.round(tileSize*0.6)}px;color:rgba(255,255,255,.4);font-family:'Space Mono',monospace;flex-shrink:0;margin-left:${Math.round(8*scaleFactor)}px">${c.sala}</span>`:''}
    ${isLive&&showBadge?`<span style="font-size:${Math.round(tileSize*0.55)}px;font-weight:700;color:${badgeColor};letter-spacing:1px;flex-shrink:0;margin-left:${Math.round(6*scaleFactor)}px">${badgeTxt}</span>`:''}
  </div>`;
}

function buildCorsiMarkup(corsi, cfg, scale) {
  const stile = cfg.stile || 'lista';
  const cols = cfg.colonne || ['ora','corso','istruttore'];
  const titoloColor = cfg.colore_titolo || '#ffffff';
  const corsoColor = cfg.colore_corso || '#ffffff';
  const orarioColor = cfg.colore_orario || '#ffffff';
  const badgeColor = cfg.colore_badge || '#F7192E';
  const fsCorso = Math.round((cfg.font_size_corso||18)*scale*0.8);
  const badgeTxt = cfg.badge_live || 'LIVE';
  const showBadge = cfg.mostra_badge !== false;

  if (stile === 'lobby') {
    const now = new Date();
    const dateStr = `${GIORNI[now.getDay()]} ${now.getDate()} ${MESI[now.getMonth()]}`;
    const tickerText = corsi.map(c=>`${c.corso} ${c.ora}`).join('   ·   ') || 'Nessun corso in programma';
    return `<div style="width:100%;height:100%;background:#000;display:flex;flex-direction:column;overflow:hidden">
      <div style="display:flex;justify-content:space-between;align-items:baseline;padding:${Math.round(10*scale)}px ${Math.round(14*scale)}px;border-bottom:2px solid ${badgeColor}">
        <span style="font-size:${Math.round(15*scale)}px;font-weight:800;color:${titoloColor};text-transform:uppercase;letter-spacing:${Math.round(1*scale)}px">${cfg.titolo||'In programma oggi'}</span>
        <span style="font-size:${Math.round(9*scale)}px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:1px">${dateStr}</span>
      </div>
      <div style="flex:1;overflow:hidden;padding:${Math.round(6*scale)}px ${Math.round(14*scale)}px">
        ${corsi.map(c=>`
        <div style="display:flex;align-items:center;gap:${Math.round(10*scale)}px;padding:${Math.round(6*scale)}px 0;${c.stato==='attivo'?`background:${hexToRgba(badgeColor,0.1)};border-left:3px solid ${badgeColor};padding-left:${Math.round(8*scale)}px;`:''}${c.stato==='passato'?'opacity:.35;':''}">
          <span style="font-size:${Math.round(11*scale)}px;font-weight:300;color:${c.stato==='attivo'?badgeColor:orarioColor};min-width:${Math.round(34*scale)}px;letter-spacing:1px">${c.ora}</span>
          <span style="font-size:${fsCorso}px;font-weight:800;color:${corsoColor};text-transform:uppercase;letter-spacing:${Math.round(0.5*scale)}px;flex:1">${c.corso}</span>
          ${c.stato==='attivo'&&showBadge?`<span style="display:flex;align-items:center;gap:4px;font-size:${Math.round(8*scale)}px;font-weight:700;color:${badgeColor};text-transform:uppercase;letter-spacing:1px"><span style="width:${Math.round(5*scale)}px;height:${Math.round(5*scale)}px;border-radius:50%;background:${badgeColor};animation:neonPulse 1.4s ease-in-out infinite"></span>${badgeTxt}</span>`:''}
        </div>`).join('')}
      </div>
      <div style="border-top:1px solid rgba(255,255,255,.1);padding:${Math.round(4*scale)}px 0;overflow:hidden;position:relative;height:${Math.round(16*scale)}px">
        <div style="position:absolute;left:0;white-space:nowrap;font-size:${Math.round(9*scale)}px;color:rgba(255,255,255,.5);animation:tickerScrollRTL 14s linear infinite">${tickerText}</div>
      </div>
    </div>`;
  }

  if (stile === 'stazione') {
    const tileSize = Math.max(9, Math.round(fsCorso*0.75));
    return `<div style="width:100%;height:100%;background:#000;padding:${Math.round(8*scale)}px;overflow:hidden">
      <div style="font-size:${Math.round(11*scale)}px;font-weight:700;color:${titoloColor};text-transform:uppercase;letter-spacing:${Math.round(2*scale)}px;margin-bottom:${Math.round(8*scale)}px;border-bottom:1px solid rgba(255,255,255,.2);padding-bottom:${Math.round(4*scale)}px;font-family:'Space Mono',monospace">${cfg.titolo||'In programma oggi'}</div>
      ${corsi.map((c,i)=>renderFlapRow(c,i,{corsoColor,orarioColor,badgeColor,badgeTxt,showBadge,cols,tileSize,scaleFactor:scale,colorTestoLive:cfg.colore_testo_live})).join('')}
    </div>`;
  }

  // lista (default)
  if (!corsi.length) {
    return `<div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:${Math.round(12*scale)}px;color:${titoloColor}">
      <div style="font-size:${Math.round(32*scale)}px"><i class="fa-solid fa-dumbbell"></i></div>
      <div style="font-size:${Math.round(18*scale)}px;font-weight:700;text-align:center;line-height:1.4">Buon<br>allenamento!</div>
    </div>`;
  }
  return `<div style="width:100%;height:100%;padding:${Math.round(10*scale)}px;overflow:hidden">
    <div style="font-size:${Math.round(16*scale)}px;font-weight:700;color:${titoloColor};margin-bottom:${Math.round(6*scale)}px">${cfg.titolo||'In programma oggi'}</div>
    ${corsi.map(c=>`
    <div style="display:flex;align-items:center;gap:${Math.round(10*scale)}px;padding:${Math.round(16*scale)}px ${Math.round(4*scale)}px;border-bottom:1px solid rgba(255,255,255,.1);${c.stato==='attivo'?`background:${hexToRgba(badgeColor,0.08)};border-left:3px solid ${badgeColor};padding-left:${Math.round(6*scale)}px;`:''}${c.stato==='passato'?'opacity:.35;':''}">
      ${cols.includes('ora')?`<span style="font-size:${Math.round(fsCorso*0.65)}px;font-weight:600;color:${c.stato==='attivo'?badgeColor:orarioColor};min-width:${Math.round(fsCorso*1.8)}px;letter-spacing:1px">${c.ora}</span>`:''}
      ${cols.includes('corso')?`<span style="font-size:${fsCorso}px;font-weight:700;color:${corsoColor};text-transform:uppercase;letter-spacing:${Math.round(0.5*scale)}px;flex:1">${c.corso}</span>`:''}
      ${c.stato==='attivo'&&showBadge?`<span style="font-size:${Math.round(8*scale)}px;font-weight:700;color:#fff;background:${badgeColor};padding:1px ${Math.round(5*scale)}px;border-radius:8px;letter-spacing:.5px">${badgeTxt}</span>`:''}
      ${cols.includes('istruttore')?`<span style="font-size:${Math.round(10*scale)}px;color:rgba(255,255,255,.5)">${c.istruttore}</span>`:''}
      ${cols.includes('sala')?`<span style="font-size:${Math.round(9*scale)}px;color:rgba(255,255,255,.4)">${c.sala}</span>`:''}
    </div>`).join('')}
  </div>`;
}

// ── Sidebar: cicla le slide con la loro durata reale ─────────────
function renderSidebar(el, layer, cfg, scale) {
  const slides = cfg.slides || [];
  if (!slides.length) {
    el.style.display='flex'; el.style.alignItems='center'; el.style.justifyContent='center'; el.style.color='rgba(255,255,255,.3)';
    el.textContent = 'Sidebar vuota';
    return;
  }
  let idx = 0;
  function showSlide() {
    const slide = slides[idx];
    el.innerHTML = '';
    const inner = document.createElement('div');
    inner.style.cssText = 'width:100%;height:100%;animation:fadeIn .4s ease';
    el.appendChild(inner);
    // Riusa renderWidget passando un layer "virtuale" per la slide
    renderWidget(inner, { widget_type: slide.widget_type, width: layer.width, height: layer.height }, scale, {});
    // Applica la config specifica della slide (rimappando chiavi tipiche del modal slide)
    applySlideConfig(inner, slide, scale);
    idx = (idx+1) % slides.length;
  }
  showSlide();
  const timer = setInterval(showSlide, (slides[0].durata||10)*1000);
  sidebarTimers[layer.id] = timer;
}

// Le slide della sidebar usano nomi di campo leggermente diversi (dal modal "Aggiungi slide"):
// es. sheet_url, max_corsi, titolo_widget invece di titolo, ecc. Li normalizziamo qui.
function applySlideConfig(el, slide, scale) {
  const c = slide.config || {};
  const normalized = { ...c };
  if (slide.widget_type === 'corsi') {
    normalized.titolo = c.titolo_widget || 'In programma oggi';
    // Va chiamata sempre: se la slide non ha un foglio proprio, loadCorsi usa
    // comunque il fallback di sede (CLUB_SHEET_CORSI) al suo interno.
    loadCorsi(el, normalized, scale);
  }
  if (slide.widget_type === 'countdown') {
    normalized.titolo = c.titolo_countdown || '';
  }
  if (slide.widget_type === 'immagine' && c.file) {
    normalized.file = c.file;
  }
  if (slide.widget_type === 'qrcode') {
    normalized.titolo = c.qr_titolo || '';
  }
  // Re-render con la config normalizzata (eccetto corsi già gestito sopra)
  if (slide.widget_type !== 'corsi') {
    renderWidget(el, { widget_type: slide.widget_type, width: el.offsetWidth, height: el.offsetHeight, config: normalized }, scale, {});
  }
}

// ── Avvio ────────────────────────────────────────────────────────
// Modalità anteprima: usata dal popup "Anteprima" dell'editor. Invece di
// scaricare il template dal DB (che potrebbe non essere ancora salvato),
// riceve i layer correnti via postMessage e li renderizza con lo stesso
// identico motore del player reale — stessa resa esatta, zero duplicazione.
if (PREVIEW_MODE) {
  window.addEventListener('message', (e) => {
    if (e.origin !== window.location.origin) return;
    if (!e.data || e.data.type !== 'pixelbridge-preview') return;
    hideStatus();
    renderTemplate(e.data);
  });
} else {
  window.addEventListener('load', () => {
    loadState();
    setInterval(loadState, POLL_INTERVAL);
  });
  window.addEventListener('resize', () => {
    // Ricalcola scala mantenendo lo stesso template
    currentTemplateId = null;
    loadState();
  });
}
</script>
</body>
</html>
