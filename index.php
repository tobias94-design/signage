<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PixelBridge — Digital Signage per aziende e negozi</title>
<meta name="description" content="Gestisci i contenuti sulle TV della tua azienda o del tuo negozio. Da qualsiasi dispositivo, in tempo reale.">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Syne:wght@800&family=Space+Mono:wght@400&display=swap" rel="stylesheet">
<style>

/* ── DESIGN TOKENS (speculari alla piattaforma) ──────── */
:root {
  --bg:        #0f0f0f;
  --bg-2:      #151515;
  --bg-3:      #1a1a1a;
  --bg-4:      #1f1f1f;
  --border:    rgba(255,255,255,.08);
  --border-h:  rgba(232,80,2,.35);
  --orange:    #E85002;
  --orange-l:  #FF8C00;
  --gold:      #FFC040;
  --text:      #EDEBE6;
  --text-2:    #A09890;
  --text-3:    #5c5550;
  --radius:    8px;
  --radius-sm: 5px;
}

/* ── RESET ──────────────────────────────────────────── */
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: 'Inter', sans-serif;
  font-weight: 400;
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
  cursor: none;
}
a { text-decoration: none; color: inherit; }
img { display: block; }

/* ── CURSOR ──────────────────────────────────────────── */
#cur {
  width: 4px; height: 4px;
  background: var(--orange-l); border-radius: 50%;
  position: fixed; pointer-events: none; z-index: 9999;
  box-shadow: 0 0 6px var(--orange);
}
#ring {
  width: 22px; height: 22px;
  border: 1px solid rgba(232,80,2,.2); border-radius: 50%;
  position: fixed; pointer-events: none; z-index: 9998;
  transform: translate(-50%,-50%);
  transition: width .18s ease, height .18s ease, border-color .18s ease;
}
body:has(a:hover) #ring,
body:has(button:hover) #ring { width: 36px; height: 36px; border-color: rgba(232,80,2,.45); }

/* ── ANIMATIONS ──────────────────────────────────────── */
@keyframes fadeUp   { from { opacity:0; transform:translateY(16px) } to { opacity:1; transform:none } }
@keyframes fadeIn   { from { opacity:0 } to { opacity:1 } }
@keyframes blink    { 0%,100%{opacity:.5} 50%{opacity:1} }
@keyframes ticker   { from{transform:translateX(0)} to{transform:translateX(-50%)} }
@keyframes scanline { from{top:-100%} to{top:100%} }

/* ── NAV ──────────────────────────────────────────────── */
nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 200;
  height: 60px;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 48px;
  border-bottom: 1px solid transparent;
  transition: background .25s, border-color .25s, backdrop-filter .25s;
}
nav.stuck {
  background: rgba(15,15,15,.92);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border-color: var(--border);
}
.nav-logo {
  display: flex; align-items: center; gap: 10px;
}
.nav-logo svg { flex-shrink: 0; }
.nav-wordmark {
  font-family: 'Syne', sans-serif;
  font-weight: 800;
  font-size: 16px;
  letter-spacing: -.02em;
}
.nw1 { color: #F2EAD8; }
.nw2 {
  background: linear-gradient(118deg, #FFC040, #FF5000);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  background-clip: text;
}
.nav-right { display: flex; align-items: center; gap: 10px; }
.btn-login {
  display: flex; align-items: center; gap: 7px;
  font-family: 'Space Mono', monospace; font-size: 9px;
  letter-spacing: .18em; text-transform: uppercase;
  color: var(--text-2); padding: 8px 16px;
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  background: transparent; cursor: none;
  transition: border-color .2s, color .2s, background .2s;
}
.btn-login:hover { border-color: var(--border-h); color: var(--text); background: rgba(232,80,2,.04); }

/* ── HERO ──────────────────────────────────────────────── */
.hero {
  min-height: 100vh;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  text-align: center;
  padding: 120px 48px 100px;
  position: relative; overflow: hidden;
}

/* Glow ambientale */
.hero-glow {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
  width: 900px; height: 700px; pointer-events: none;
  background: radial-gradient(ellipse 60% 50% at 50% 50%,
    rgba(232,80,2,.07) 0%, rgba(255,140,0,.03) 40%, transparent 70%);
}
.hero-glow-2 {
  position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(232,80,2,.3) 30%, rgba(255,140,0,.4) 50%, rgba(232,80,2,.3) 70%, transparent);
}

/* Hero canvas — grande, occupa tutta la hero sotto i copy */
#hero-canvas {
  display: block;
  position: absolute;
  left: 0; right: 0;
  bottom: 0;
  width: 100%;
  height: 65%;
  z-index: 3;
  pointer-events: none;
  opacity: 0;
  animation: fadeIn 1.6s 1s ease forwards;
}
/* Fade sfumato in cima al canvas così non taglia i copy */
#hero-canvas::after { content: ''; }
.hero-canvas-fade {
  position: absolute;
  left: 0; right: 0;
  bottom: 0;
  height: 65%;
  z-index: 4;
  pointer-events: none;
  background: linear-gradient(
    to bottom,
    var(--bg) 0%,
    rgba(15,15,15,.7) 18%,
    transparent 45%,
    transparent 78%,
    rgba(15,15,15,.6) 92%,
    var(--bg) 100%
  );
}

.hero-inner {
  position: relative; z-index: 10; max-width: 820px;
  padding-bottom: 200px;
}

/* Pill badge */
.hero-pill {
  display: inline-flex; align-items: center; gap: 8px;
  font-family: 'Space Mono', monospace; font-size: 9px;
  letter-spacing: .22em; text-transform: uppercase;
  color: var(--text-3); padding: 6px 14px 6px 10px;
  border: 1px solid var(--border); border-radius: 100px;
  margin-bottom: 40px;
  opacity: 0; animation: fadeUp .5s .05s ease forwards;
}
.hero-pill-dot {
  width: 5px; height: 5px; border-radius: 50%;
  background: var(--orange); box-shadow: 0 0 6px var(--orange);
  animation: blink 2s ease-in-out infinite;
}

/* Headline */
.hero-h1 {
  font-weight: 800; font-size: clamp(38px, 5.5vw, 76px);
  line-height: 1.07; letter-spacing: -.035em;
  color: var(--text); margin-bottom: 24px;
  opacity: 0; animation: fadeUp .7s .15s ease forwards;
}
.hero-h1 .accent {
  background: linear-gradient(115deg, var(--gold) 0%, var(--orange) 70%);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-sub {
  font-size: 17px; line-height: 1.65; color: var(--text-2); font-weight: 300;
  max-width: 540px; margin: 0 auto 52px;
  opacity: 0; animation: fadeUp .7s .28s ease forwards;
}

/* CTA */
.hero-cta {
  display: flex; align-items: center; justify-content: center; gap: 12px;
  opacity: 0; animation: fadeUp .6s .42s ease forwards;
}
.btn-main {
  display: inline-flex; align-items: center; gap: 8px;
  font-weight: 600; font-size: 14px; letter-spacing: -.01em;
  padding: 14px 28px; border-radius: var(--radius);
  background: var(--orange);
  color: #fff; border: none; cursor: none;
  transition: background .2s, transform .18s, box-shadow .2s;
  box-shadow: 0 0 0 0 rgba(232,80,2,.0);
}
.btn-main:hover {
  background: #ff6020; transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(232,80,2,.25);
}
.btn-outline {
  display: inline-flex; align-items: center; gap: 8px;
  font-size: 13px; font-weight: 500; letter-spacing: -.01em;
  padding: 14px 24px; border-radius: var(--radius);
  border: 1px solid var(--border); color: var(--text-2);
  background: transparent; cursor: none;
  transition: border-color .2s, color .2s, background .2s;
}
.btn-outline:hover { border-color: var(--border-h); color: var(--text); background: rgba(232,80,2,.04); }

/* Freccia scroll */
.hero-scroll {
  position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%);
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  font-family: 'Space Mono', monospace; font-size: 8px; letter-spacing: .2em;
  text-transform: uppercase; color: var(--text-3);
  opacity: 0; animation: fadeIn .8s 1.2s ease forwards;
}
.hero-scroll-line {
  width: 1px; height: 32px;
  background: linear-gradient(to bottom, var(--orange), transparent);
  animation: blink 2s ease-in-out infinite;
}

/* ── TICKER ──────────────────────────────────────────── */
.ticker-wrap {
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  background: var(--bg-2);
  padding: 10px 0; overflow: hidden;
}
.ticker-track {
  display: flex; white-space: nowrap;
  animation: ticker 26s linear infinite;
}
.ticker-item {
  font-family: 'Space Mono', monospace; font-size: 8.5px;
  letter-spacing: .26em; text-transform: uppercase;
  color: var(--text-3); padding: 0 28px;
  display: flex; align-items: center; gap: 28px;
}
.ticker-dot { color: var(--orange); opacity: .5; }

/* ── SEZIONI BASE ─────────────────────────────────────── */
.divider { height: 1px; background: var(--border); }

.section { padding: 100px 48px; max-width: 1140px; margin: 0 auto; }

.section-eyebrow {
  display: inline-flex; align-items: center; gap: 10px;
  font-family: 'Space Mono', monospace; font-size: 8.5px;
  letter-spacing: .28em; text-transform: uppercase;
  color: var(--text-3); margin-bottom: 20px;
}
.section-eyebrow::before {
  content: '';
  display: block; width: 18px; height: 1px;
  background: var(--orange); opacity: .6;
}

.section-title {
  font-weight: 700; font-size: clamp(26px, 3vw, 46px);
  letter-spacing: -.03em; line-height: 1.1;
  color: var(--text); margin-bottom: 14px;
}
.section-title .accent {
  background: linear-gradient(115deg, var(--gold), var(--orange));
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  background-clip: text;
}
.section-desc {
  font-size: 15px; line-height: 1.7; color: var(--text-2);
  max-width: 480px; font-weight: 300;
}

/* ── COME FUNZIONA ─── 3 step ─────────────────────────── */
.steps-grid {
  display: grid; grid-template-columns: repeat(3,1fr);
  gap: 1px; margin-top: 60px;
  background: var(--border);
  border: 1px solid var(--border); border-radius: var(--radius);
  overflow: hidden;
}
.step-card {
  background: var(--bg-2); padding: 44px 36px 40px;
  position: relative; overflow: hidden;
  transition: background .22s;
}
.step-card:hover { background: var(--bg-3); }

/* Linea top on hover */
.step-card::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, var(--orange), var(--gold) 50%, transparent);
  opacity: 0; transition: opacity .22s;
}
.step-card:hover::before { opacity: 1; }

.step-num {
  font-family: 'Space Mono', monospace; font-size: 9px;
  letter-spacing: .25em; color: var(--text-3); margin-bottom: 24px;
  display: flex; align-items: center; gap: 10px;
}
.step-num::after { content: ''; flex: 1; height: 1px; background: var(--border); }

.step-icon { margin-bottom: 20px; }
.step-title {
  font-weight: 600; font-size: 17px; letter-spacing: -.02em;
  color: var(--text); margin-bottom: 10px;
}
.step-desc { font-size: 13px; line-height: 1.72; color: var(--text-2); }

/* ── SCHERMATA PIATTAFORMA ────────────────────────────── */
.platform-section {
  background: var(--bg-2);
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
}
.platform-inner {
  max-width: 1140px; margin: 0 auto;
  padding: 100px 48px;
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 80px; align-items: center;
}
.platform-text {}
.platform-checks { display: flex; flex-direction: column; gap: 14px; margin-top: 36px; }
.pcheck {
  display: flex; align-items: flex-start; gap: 12px;
  font-size: 14px; color: var(--text-2); line-height: 1.55;
}
.pcheck-dot {
  width: 5px; height: 5px; border-radius: 50%;
  background: var(--orange); flex-shrink: 0;
  margin-top: 6px;
  box-shadow: 0 0 6px rgba(232,80,2,.5);
}

/* UI Mockup */
.ui-mockup {
  background: var(--bg-3);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  box-shadow: 0 24px 64px rgba(0,0,0,.4), 0 0 0 1px rgba(255,255,255,.04);
}
.ui-topbar {
  background: var(--bg-4);
  border-bottom: 1px solid var(--border);
  padding: 11px 18px;
  display: flex; align-items: center; justify-content: space-between;
}
.ui-topbar-title {
  font-family: 'Space Mono', monospace; font-size: 8px;
  letter-spacing: .22em; text-transform: uppercase; color: var(--text-3);
  display: flex; align-items: center; gap: 8px;
}
.ui-live-pill {
  font-family: 'Space Mono', monospace; font-size: 7.5px;
  letter-spacing: .15em; text-transform: uppercase;
  color: var(--orange); padding: 3px 8px;
  border: 1px solid rgba(232,80,2,.25); border-radius: 100px;
  background: rgba(232,80,2,.08);
  display: flex; align-items: center; gap: 5px;
}
.ui-live-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--orange); animation: blink 1.5s ease-in-out infinite; }

.ui-body { padding: 18px; display: flex; flex-direction: column; gap: 12px; }

/* Widget cards nel mockup */
.ui-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.ui-card {
  background: var(--bg-2); border: 1px solid var(--border);
  border-radius: var(--radius-sm); padding: 16px;
}
.ui-card-label {
  font-family: 'Space Mono', monospace; font-size: 7.5px;
  letter-spacing: .2em; text-transform: uppercase; color: var(--text-3);
  margin-bottom: 8px;
}
.ui-card-val {
  font-weight: 700; font-size: 26px; letter-spacing: -.03em; color: var(--text);
  line-height: 1;
}
.ui-card-val span { font-size: 13px; font-weight: 400; color: var(--text-3); }
.ui-card-sub { font-size: 11px; color: var(--text-3); margin-top: 5px; }
.ui-bar {
  height: 3px; background: var(--border); border-radius: 2px;
  margin-top: 10px; overflow: hidden;
}
.ui-bar-fill {
  height: 100%; border-radius: 2px;
  background: linear-gradient(90deg, var(--orange), var(--gold));
}
.ui-status-row {
  display: flex; flex-direction: column; gap: 7px;
}
.ui-device {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px;
  background: var(--bg-2); border: 1px solid var(--border);
  border-radius: var(--radius-sm);
}
.ui-device-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.ui-device-dot.on { background: #34d058; box-shadow: 0 0 6px rgba(52,208,88,.5); }
.ui-device-dot.off { background: var(--text-3); }
.ui-device-name { font-size: 12px; font-weight: 500; color: var(--text); flex: 1; }
.ui-device-tag {
  font-family: 'Space Mono', monospace; font-size: 7.5px;
  letter-spacing: .12em; text-transform: uppercase;
  padding: 2px 7px; border-radius: 100px;
}
.ui-device-tag.on { color: #34d058; background: rgba(52,208,88,.1); border: 1px solid rgba(52,208,88,.2); }
.ui-device-tag.off { color: var(--text-3); background: rgba(255,255,255,.04); border: 1px solid var(--border); }

/* ── COSA PUOI MOSTRARE ── 2×2 ────────────────────────── */
.features-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 1px; margin-top: 60px;
  background: var(--border); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden;
}
.feat {
  background: var(--bg-2); padding: 44px 40px;
  position: relative; overflow: hidden;
  transition: background .22s;
}
.feat:hover { background: var(--bg-3); }
.feat::after {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 2px;
  background: linear-gradient(to bottom, transparent, var(--orange) 40%, var(--gold) 60%, transparent);
  opacity: 0; transition: opacity .22s;
}
.feat:hover::after { opacity: 1; }
.feat-icon { margin-bottom: 22px; }
.feat-title { font-weight: 600; font-size: 17px; letter-spacing: -.02em; color: var(--text); margin-bottom: 10px; }
.feat-desc { font-size: 13px; line-height: 1.72; color: var(--text-2); }

/* ── CTA FINALE ───────────────────────────────────────── */
.cta-section {
  background: var(--bg-2);
  border-top: 1px solid var(--border);
  overflow: hidden; position: relative;
}
.cta-glow {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
  width: 800px; height: 500px; pointer-events: none;
  background: radial-gradient(ellipse, rgba(232,80,2,.06) 0%, transparent 65%);
}
.cta-inner {
  position: relative; z-index: 1;
  max-width: 660px; margin: 0 auto;
  padding: 110px 48px;
  text-align: center;
}
.cta-title {
  font-weight: 700; font-size: clamp(28px, 3.8vw, 52px);
  letter-spacing: -.03em; line-height: 1.1;
  color: var(--text); margin-bottom: 18px;
}
.cta-title .accent {
  background: linear-gradient(115deg, var(--gold), var(--orange));
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
  background-clip: text;
}
.cta-sub {
  font-size: 16px; color: var(--text-2); line-height: 1.65;
  font-weight: 300; margin-bottom: 44px;
}
.cta-actions { display: flex; align-items: center; justify-content: center; gap: 12px; }

/* ── LOGO CTA animato ────────────────────────────────── */
.cta-logo { margin: 32px auto 40px; display: flex; justify-content: center; }
footer {
  background: var(--bg);
  border-top: 1px solid var(--border);
  padding: 32px 48px;
  display: flex; align-items: center; justify-content: space-between;
}
.foot-left { display: flex; align-items: center; gap: 20px; }
.foot-wordmark { font-weight: 700; font-size: 14px; letter-spacing: -.02em; color: var(--text); }
.foot-wordmark span { color: var(--orange); }
.foot-copy {
  font-family: 'Space Mono', monospace; font-size: 8px;
  letter-spacing: .18em; text-transform: uppercase; color: var(--text-3);
}
.foot-right a {
  font-family: 'Space Mono', monospace; font-size: 8px;
  letter-spacing: .18em; text-transform: uppercase; color: var(--text-3);
  transition: color .2s;
}
.foot-right a:hover { color: var(--orange); }

/* ── REVEAL ──────────────────────────────────────────── */
.reveal { opacity: 0; transform: translateY(14px); transition: opacity .55s ease, transform .55s ease; }
.reveal.in { opacity: 1; transform: none; }

/* ── RESPONSIVE ──────────────────────────────────────── */
@media (max-width: 900px) {
  nav { padding: 0 24px; }
  .hero { padding: 110px 24px 80px; }
  .section { padding: 72px 24px; }
  .steps-grid { grid-template-columns: 1fr; }
  .platform-inner { grid-template-columns: 1fr; gap: 48px; padding: 72px 24px; }
  .features-grid { grid-template-columns: 1fr; }
  .cta-inner { padding: 80px 24px; }
  .cta-actions { flex-direction: column; }
  footer { flex-direction: column; gap: 14px; padding: 28px 24px; text-align: center; }
}

</style>
</head>
<body>

<div id="cur"></div>
<div id="ring"></div>

<!-- ── NAV ─────────────────────────────────────────────── -->
<nav id="nav">
  <div class="nav-logo">
    <!-- Mark orizzontale — brand guide lockup -->
    <svg width="44" height="18" viewBox="0 0 130 52" fill="none" aria-hidden="true">
      <defs>
        <radialGradient id="lm1" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stop-color="#FFD060"/>
          <stop offset="100%" stop-color="#FF5000" stop-opacity="0"/>
        </radialGradient>
        <radialGradient id="lm2" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stop-color="#FFC040"/>
          <stop offset="100%" stop-color="#FF5000" stop-opacity="0"/>
        </radialGradient>
      </defs>
      <!-- satellite sx -->
      <circle cx="16" cy="26" r="14" fill="url(#lm1)" opacity=".5"/>
      <circle cx="16" cy="26" r="7"  fill="#FF9400" opacity=".85"/>
      <circle cx="16" cy="26" r="3"  fill="#FFE060"/>
      <line x1="23" y1="26" x2="42" y2="26" stroke="rgba(255,180,60,.22)" stroke-width=".8"/>
      <!-- core -->
      <circle cx="65" cy="26" r="22" fill="url(#lm2)" opacity=".55"/>
      <circle cx="65" cy="26" r="14" fill="#FF7800" opacity=".85"/>
      <ellipse cx="65" cy="23" rx="11" ry="2.5" fill="none" stroke="rgba(255,220,80,.28)" stroke-width=".8"/>
      <ellipse cx="65" cy="26" rx="12.5" ry="2" fill="none" stroke="rgba(255,200,60,.32)" stroke-width=".9"/>
      <circle cx="65" cy="26" r="6"  fill="#FFC040" opacity=".9"/>
      <circle cx="65" cy="26" r="2.5" fill="#FFF0A0"/>
      <line x1="79" y1="26" x2="98" y2="26" stroke="rgba(255,180,60,.22)" stroke-width=".8"/>
      <!-- satellite dx -->
      <circle cx="114" cy="26" r="18" fill="url(#lm1)" opacity=".5"/>
      <circle cx="114" cy="26" r="10" fill="#FF9400" opacity=".85"/>
      <circle cx="114" cy="26" r="4"  fill="#FFE060" opacity=".9"/>
    </svg>
    <!-- Wordmark Syne 800 — brand guide -->
    <div class="nav-wordmark"><span class="nw1">PIXEL</span><span class="nw2">BRIDGE</span></div>
  </div>
  <div class="nav-right">
    <a href="/login.php" class="btn-login">
      <svg width="11" height="11" viewBox="0 0 11 11" fill="none" aria-hidden="true">
        <rect x=".5" y=".5" width="10" height="10" rx="1.5" stroke="currentColor" stroke-width=".9"/>
        <circle cx="5.5" cy="4.2" r="1.7" stroke="currentColor" stroke-width=".9"/>
        <path d="M1.5 10.2c.2-1.9 1.8-3 4-3s3.8 1.1 4 3" stroke="currentColor" stroke-width=".9" stroke-linecap="round"/>
      </svg>
      Accedi
    </a>
  </div>
</nav>

<!-- ── HERO ─────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-glow"></div>
  <div class="hero-glow-2"></div>

  <!-- Neural canvas — grande, dietro ai copy -->
  <canvas id="hero-canvas"></canvas>
  <div class="hero-canvas-fade"></div>

  <div class="hero-inner">
    <div class="hero-pill">
      <div class="hero-pill-dot"></div>
      Digital Signage · Cloud
    </div>

    <h1 class="hero-h1">
      Il ponte tra<br>
      <span class="accent">visione e realtà.</span>
    </h1>

    <p class="hero-sub">
      Gestisci cosa mostrano le TV della tua azienda o del tuo negozio — da qualsiasi dispositivo, in tempo reale.
    </p>

    <div class="hero-cta">
      <a href="mailto:tobiasola94@gmail.com" class="btn-main">
        Contattaci
        <svg width="14" height="10" viewBox="0 0 14 10" fill="none"><path d="M1 5h12M8 1l5 4-5 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
      <a href="#come-funziona" class="btn-outline">Come funziona</a>
    </div>
  </div>

  <div class="hero-scroll">
    <div class="hero-scroll-line"></div>
    Scopri
  </div>
</section>

<!-- ── TICKER ─────────────────────────────────────────────── -->
<div class="ticker-wrap" aria-hidden="true">
  <div class="ticker-track">
    <div class="ticker-item">Negozi<span class="ticker-dot">·</span>Uffici<span class="ticker-dot">·</span>Showroom<span class="ticker-dot">·</span>Aggiornamento in tempo reale<span class="ticker-dot">·</span>Promozioni<span class="ticker-dot">·</span>Comunicazioni interne<span class="ticker-dot">·</span>Orari e menu<span class="ticker-dot">·</span>Multi-sede<span class="ticker-dot">·</span></div>
    <div class="ticker-item" aria-hidden="true">Negozi<span class="ticker-dot">·</span>Uffici<span class="ticker-dot">·</span>Showroom<span class="ticker-dot">·</span>Aggiornamento in tempo reale<span class="ticker-dot">·</span>Promozioni<span class="ticker-dot">·</span>Comunicazioni interne<span class="ticker-dot">·</span>Orari e menu<span class="ticker-dot">·</span>Multi-sede<span class="ticker-dot">·</span></div>
  </div>
</div>

<div class="divider"></div>

<!-- ── COME FUNZIONA ──────────────────────────────────────── -->
<section class="section" id="come-funziona">
  <div class="section-eyebrow">Come funziona</div>
  <h2 class="section-title">Tre passi.<br><span class="accent">Tutto il resto è automatico.</span></h2>
  <p class="section-desc">Nessun tecnico. Funziona su qualsiasi TV con ingresso HDMI.</p>

  <div class="steps-grid">

    <div class="step-card reveal">
      <div class="step-num">01</div>
      <div class="step-icon">
        <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
          <rect x="2" y="6" width="32" height="22" rx="2.5" stroke="rgba(232,80,2,.4)" stroke-width="1"/>
          <rect x="14" y="28" width="8" height="3" fill="rgba(232,80,2,.25)"/>
          <rect x="10" y="31" width="16" height="1.5" rx=".75" fill="rgba(232,80,2,.2)"/>
          <circle cx="18" cy="17" r="6" stroke="rgba(232,80,2,.5)" stroke-width="1"/>
          <path d="M15 17l2 2 4-4" stroke="var(--orange)" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="step-title">Collega la TV</div>
      <div class="step-desc">Inserisci un piccolo dispositivo nell'ingresso HDMI. Si connette automaticamente a PixelBridge — niente configurazioni.</div>
    </div>

    <div class="step-card reveal">
      <div class="step-num">02</div>
      <div class="step-icon">
        <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
          <rect x="3" y="3" width="30" height="30" rx="2.5" stroke="rgba(232,80,2,.3)" stroke-width="1"/>
          <rect x="3" y="11" width="30" height="1" fill="rgba(232,80,2,.15)"/>
          <rect x="8" y="7" width="3" height="3" rx="1" fill="rgba(232,80,2,.4)"/>
          <rect x="13" y="7" width="3" height="3" rx="1" fill="rgba(232,80,2,.25)"/>
          <rect x="8" y="16" width="8" height="5" rx="1" fill="rgba(232,80,2,.12)"/>
          <rect x="20" y="16" width="8" height="5" rx="1" fill="rgba(232,80,2,.2)"/>
          <rect x="8" y="24" width="20" height="3" rx="1" fill="rgba(232,80,2,.1)"/>
        </svg>
      </div>
      <div class="step-title">Carica i contenuti</div>
      <div class="step-desc">Dal computer o dallo smartphone, carica immagini, video e comunicazioni. Sistemali nell'ordine che vuoi.</div>
    </div>

    <div class="step-card reveal">
      <div class="step-num">03</div>
      <div class="step-icon">
        <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
          <circle cx="18" cy="18" r="14" stroke="rgba(232,80,2,.25)" stroke-width="1"/>
          <circle cx="18" cy="18" r="9" stroke="rgba(232,80,2,.4)" stroke-width="1"/>
          <circle cx="18" cy="18" r="3.5" fill="var(--orange)" opacity=".8"/>
          <circle cx="18" cy="4" r="1.8" fill="rgba(232,80,2,.5)"/>
          <circle cx="18" cy="32" r="1.8" fill="rgba(232,80,2,.35)"/>
          <circle cx="4" cy="18" r="1.8" fill="rgba(232,80,2,.35)"/>
          <circle cx="32" cy="18" r="1.8" fill="rgba(232,80,2,.5)"/>
        </svg>
      </div>
      <div class="step-title">La TV si aggiorna</div>
      <div class="step-desc">I tuoi contenuti appaiono sullo schermo in pochi secondi. Fai una modifica? La TV si aggiorna ovunque tu sia.</div>
    </div>

  </div>
</section>

<div class="divider"></div>

<!-- ── PIATTAFORMA ─────────────────────────────────────── -->
<div class="platform-section">
  <div class="platform-inner">

    <div class="platform-text reveal">
      <div class="section-eyebrow">La piattaforma</div>
      <h2 class="section-title">Tutto sotto<br><span class="accent">controllo.</span></h2>
      <p class="section-desc">Un'unica schermata per gestire ogni TV, ogni sede, ogni contenuto. Come una dashboard, senza la complessità.</p>

      <div class="platform-checks">
        <div class="pcheck"><div class="pcheck-dot"></div>Funziona da PC, tablet e smartphone</div>
        <div class="pcheck"><div class="pcheck-dot"></div>Programma i contenuti per orario e giorno della settimana</div>
        <div class="pcheck"><div class="pcheck-dot"></div>Più sedi e più TV gestite da un unico account</div>
        <div class="pcheck"><div class="pcheck-dot"></div>Vedi in tempo reale cosa è online e cosa sta andando in onda</div>
      </div>
    </div>

    <!-- UI Mockup fedele alla dashboard -->
    <div class="ui-mockup reveal">
      <div class="ui-topbar">
        <div class="ui-topbar-title">
          <svg width="12" height="5" viewBox="0 0 52 20" fill="none" aria-hidden="true">
            <circle cx="6" cy="10" r="6" fill="#E85002" opacity=".7"/>
            <line x1="12" y1="10" x2="18" y2="10" stroke="rgba(255,160,60,.25)" stroke-width=".8"/>
            <circle cx="26" cy="10" r="9" fill="#E85002" opacity=".85"/>
            <circle cx="26" cy="10" r="4" fill="#FFC040" opacity=".9"/>
            <line x1="35" y1="10" x2="41" y2="10" stroke="rgba(255,160,60,.25)" stroke-width=".8"/>
            <circle cx="46" cy="10" r="6" fill="#E85002" opacity=".75"/>
          </svg>
          PixelBridge · Dashboard
        </div>
        <div class="ui-live-pill">
          <div class="ui-live-dot"></div>
          In onda
        </div>
      </div>

      <div class="ui-body">
        <div class="ui-row">
          <div class="ui-card">
            <div class="ui-card-label">TV attive</div>
            <div class="ui-card-val">8<span>/10</span></div>
            <div class="ui-card-sub">2 TV offline</div>
            <div class="ui-bar"><div class="ui-bar-fill" style="width:80%"></div></div>
          </div>
          <div class="ui-card">
            <div class="ui-card-label">In onda</div>
            <div class="ui-card-val">24<span> file</span></div>
            <div class="ui-card-sub">6 playlist attive</div>
            <div class="ui-bar"><div class="ui-bar-fill" style="width:65%"></div></div>
          </div>
        </div>

        <div class="ui-status-row">
          <div class="ui-device">
            <div class="ui-device-dot on"></div>
            <div class="ui-device-name">Sede Milano — Vetrina</div>
            <div class="ui-device-tag on">Online</div>
          </div>
          <div class="ui-device">
            <div class="ui-device-dot on"></div>
            <div class="ui-device-name">Sede Roma — Reception</div>
            <div class="ui-device-tag on">Online</div>
          </div>
          <div class="ui-device">
            <div class="ui-device-dot off"></div>
            <div class="ui-device-name">Sede Torino — Sala attesa</div>
            <div class="ui-device-tag off">Offline</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<div class="divider"></div>

<!-- ── COSA PUOI MOSTRARE ──────────────────────────────── -->
<section class="section">
  <div class="section-eyebrow">Cosa puoi mostrare</div>
  <h2 class="section-title">Creato per le aziende.<br><span class="accent">Progettato per il futuro.</span></h2>
  <p class="section-desc">Qualsiasi contenuto, al momento giusto, sullo schermo giusto.</p>

  <div class="features-grid">

    <div class="feat reveal">
      <div class="feat-icon">
        <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
          <rect x="2" y="6" width="30" height="22" rx="2" stroke="rgba(232,80,2,.35)" stroke-width=".9"/>
          <path d="M9 14h16M9 18h10" stroke="rgba(232,80,2,.4)" stroke-width=".9" stroke-linecap="round"/>
          <rect x="9" y="10" width="16" height="1.5" rx=".75" fill="rgba(255,192,64,.5)"/>
        </svg>
      </div>
      <div class="feat-title">Offerte e promozioni</div>
      <div class="feat-desc">Carica la promozione del giorno, imposta quando inizia e finisce. La TV la mostra e passa al contenuto successivo da sola.</div>
    </div>

    <div class="feat reveal">
      <div class="feat-icon">
        <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
          <rect x="3" y="3" width="28" height="28" rx="2" stroke="rgba(232,80,2,.3)" stroke-width=".9"/>
          <rect x="3" y="11" width="28" height="1" fill="rgba(232,80,2,.15)"/>
          <rect x="8" y="15" width="6" height="5" rx="1" fill="rgba(232,80,2,.15)"/>
          <rect x="16" y="15" width="6" height="5" rx="1" fill="rgba(255,192,64,.2)"/>
          <rect x="8" y="22" width="6" height="4" rx="1" fill="rgba(232,80,2,.1)"/>
          <rect x="16" y="22" width="6" height="4" rx="1" fill="rgba(232,80,2,.1)"/>
        </svg>
      </div>
      <div class="feat-title">Orari e comunicazioni</div>
      <div class="feat-desc">Orari di apertura, menu del giorno, avvisi per clienti o dipendenti. Aggiornali dal telefono in qualsiasi momento.</div>
    </div>

    <div class="feat reveal">
      <div class="feat-icon">
        <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
          <rect x="2" y="7" width="30" height="20" rx="2" stroke="rgba(232,80,2,.3)" stroke-width=".9"/>
          <polygon points="14,12 14,22 24,17" fill="rgba(232,80,2,.2)" stroke="rgba(232,80,2,.5)" stroke-width=".9" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="feat-title">Video e slideshow</div>
      <div class="feat-desc">Carica video o sequenze di immagini. Imposta la durata di ogni slide. Si aggiorna da solo, senza interventi.</div>
    </div>

    <div class="feat reveal">
      <div class="feat-icon">
        <svg width="34" height="34" viewBox="0 0 34 34" fill="none">
          <rect x="2" y="6" width="13" height="10" rx="1.5" stroke="rgba(232,80,2,.3)" stroke-width=".9"/>
          <rect x="19" y="6" width="13" height="10" rx="1.5" stroke="rgba(232,80,2,.3)" stroke-width=".9"/>
          <rect x="2" y="20" width="13" height="10" rx="1.5" stroke="rgba(232,80,2,.2)" stroke-width=".9"/>
          <rect x="19" y="20" width="13" height="10" rx="1.5" stroke="rgba(232,80,2,.2)" stroke-width=".9"/>
          <circle cx="8.5" cy="11" r="1.8" fill="rgba(255,192,64,.4)"/>
          <circle cx="25.5" cy="11" r="1.8" fill="rgba(232,80,2,.35)"/>
        </svg>
      </div>
      <div class="feat-title">Più sedi, un solo account</div>
      <div class="feat-desc">Hai più negozi o reparti? Gestisci tutte le TV da un unico posto. Ogni schermo può mostrare contenuti diversi.</div>
    </div>

  </div>
</section>

<div class="divider"></div>

<!-- ── CTA ─────────────────────────────────────────────── -->
<section class="cta-section">
  <div class="cta-glow"></div>
  <div class="cta-inner">
    <h2 class="cta-title reveal">Pronto a portare i tuoi<br>schermi nel <span class="accent">futuro?</span></h2>

    <!-- Logo constellation animato -->
    <div class="cta-logo reveal">
      <svg id="cta-svg" viewBox="0 0 460 200" fill="none" xmlns="http://www.w3.org/2000/svg" width="320" height="140">
        <defs>
          <radialGradient id="cta-core" cx="50%" cy="50%" r="50%">
            <stop offset="0%"   stop-color="#FFF0A0" stop-opacity="1"/>
            <stop offset="15%"  stop-color="#FFC040" stop-opacity="1"/>
            <stop offset="40%"  stop-color="#FF8C00" stop-opacity=".85"/>
            <stop offset="70%"  stop-color="#FF4800" stop-opacity=".4"/>
            <stop offset="100%" stop-color="#CC2000" stop-opacity="0"/>
          </radialGradient>
          <radialGradient id="cta-sat1" cx="50%" cy="50%" r="50%">
            <stop offset="0%"   stop-color="#FFD060" stop-opacity="1"/>
            <stop offset="35%"  stop-color="#FF9000" stop-opacity=".8"/>
            <stop offset="70%"  stop-color="#FF5000" stop-opacity=".3"/>
            <stop offset="100%" stop-color="#CC2000" stop-opacity="0"/>
          </radialGradient>
          <radialGradient id="cta-sat2" cx="50%" cy="50%" r="50%">
            <stop offset="0%"   stop-color="#FFD860" stop-opacity="1"/>
            <stop offset="30%"  stop-color="#FF9800" stop-opacity=".85"/>
            <stop offset="65%"  stop-color="#FF5500" stop-opacity=".35"/>
            <stop offset="100%" stop-color="#CC2000" stop-opacity="0"/>
          </radialGradient>
          <filter id="cta-f-core" x="-80%" y="-80%" width="260%" height="260%">
            <feGaussianBlur stdDeviation="11" result="b"/>
            <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
          </filter>
          <filter id="cta-f-sat" x="-100%" y="-100%" width="300%" height="300%">
            <feGaussianBlur stdDeviation="7" result="b"/>
            <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
          </filter>
          <filter id="cta-f-hot" x="-200%" y="-200%" width="500%" height="500%">
            <feGaussianBlur stdDeviation="20" result="b"/>
            <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
          </filter>
          <filter id="cta-f-line" x="-10%" y="-400%" width="120%" height="900%">
            <feGaussianBlur stdDeviation="1" result="b"/>
            <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
          </filter>
        </defs>
        <!-- Satellite SX -->
        <circle cx="88" cy="100" r="46" fill="url(#cta-sat1)" opacity=".18" filter="url(#cta-f-hot)"/>
        <circle cx="88" cy="100" r="26" fill="url(#cta-sat1)" opacity=".35" filter="url(#cta-f-sat)"/>
        <circle cx="88" cy="100" r="14" fill="url(#cta-sat1)" filter="url(#cta-f-sat)"/>
        <circle cx="88" cy="100" r="5.5" fill="#FFE070" opacity=".95"/>
        <line x1="102" y1="100" x2="182" y2="100" stroke="rgba(255,180,60,.22)" stroke-width=".8" filter="url(#cta-f-line)"/>
        <circle id="cta-sig-l" r="2.8" fill="#FFD060" opacity="0" filter="url(#cta-f-sat)"/>
        <!-- Core centrale -->
        <circle cx="230" cy="100" r="90" fill="url(#cta-core)" opacity=".12" filter="url(#cta-f-hot)"/>
        <circle cx="230" cy="100" r="65" fill="url(#cta-core)" opacity=".18" filter="url(#cta-f-hot)"/>
        <circle cx="230" cy="100" r="46" fill="url(#cta-core)" opacity=".28" filter="url(#cta-f-sat)"/>
        <circle cx="230" cy="100" r="34" fill="url(#cta-core)" filter="url(#cta-f-sat)"/>
        <circle cx="230" cy="100" r="28" fill="#FF7200" opacity=".9"/>
        <ellipse cx="230" cy="93"  rx="24" ry="5"   fill="none" stroke="rgba(255,220,80,.22)" stroke-width="1.2"/>
        <ellipse cx="230" cy="100" rx="27" ry="3.5" fill="none" stroke="rgba(255,200,60,.30)" stroke-width="1.4"/>
        <ellipse cx="230" cy="107" rx="24" ry="5"   fill="none" stroke="rgba(255,180,40,.18)" stroke-width="1"/>
        <circle cx="230" cy="100" r="16" fill="#FFA030" opacity=".8"/>
        <circle cx="230" cy="100" r="10" fill="#FFC840" opacity=".9"/>
        <circle cx="230" cy="100" r="5.5" fill="#FFF0A0" filter="url(#cta-f-core)"/>
        <circle cx="230" cy="100" r="3"   fill="#FFF" opacity=".9"/>
        <line x1="258" y1="100" x2="348" y2="100" stroke="rgba(255,180,60,.22)" stroke-width=".8" filter="url(#cta-f-line)"/>
        <circle id="cta-sig-r" r="2.8" fill="#FFD060" opacity="0" filter="url(#cta-f-sat)"/>
        <!-- Satellite DX -->
        <circle cx="372" cy="100" r="54" fill="url(#cta-sat2)" opacity=".20" filter="url(#cta-f-hot)"/>
        <circle cx="372" cy="100" r="32" fill="url(#cta-sat2)" opacity=".38" filter="url(#cta-f-sat)"/>
        <circle cx="372" cy="100" r="19" fill="url(#cta-sat2)" filter="url(#cta-f-sat)"/>
        <circle cx="372" cy="100" r="7.5" fill="#FFE070" opacity=".92"/>
        <circle cx="372" cy="100" r="3.5" fill="#FFF4B0" opacity=".85"/>
      </svg>
    </div>

    <p class="cta-sub reveal">Scrivici. Ti mostriamo come funziona in una chiamata di 20 minuti.</p>
    <div class="cta-actions reveal">
      <a href="mailto:tobiasola94@gmail.com" class="btn-main">
        Contattaci
        <svg width="14" height="10" viewBox="0 0 14 10" fill="none"><path d="M1 5h12M8 1l5 4-5 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </div>
  </div>
</section>

<!-- ── FOOTER ──────────────────────────────────────────── -->
<footer>
  <div class="foot-left">
    <!-- Logo lockup brand guide -->
    <div style="display:flex;align-items:center;gap:10px;">
      <svg width="44" height="18" viewBox="0 0 130 52" fill="none" aria-hidden="true">
        <defs>
          <radialGradient id="fm1" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#FFD060"/><stop offset="100%" stop-color="#FF5000" stop-opacity="0"/></radialGradient>
          <radialGradient id="fm2" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#FFC040"/><stop offset="100%" stop-color="#FF5000" stop-opacity="0"/></radialGradient>
        </defs>
        <circle cx="16" cy="26" r="14" fill="url(#fm1)" opacity=".5"/>
        <circle cx="16" cy="26" r="7"  fill="#FF9400" opacity=".85"/>
        <circle cx="16" cy="26" r="3"  fill="#FFE060"/>
        <line x1="23" y1="26" x2="42" y2="26" stroke="rgba(255,180,60,.22)" stroke-width=".8"/>
        <circle cx="65" cy="26" r="22" fill="url(#fm2)" opacity=".55"/>
        <circle cx="65" cy="26" r="14" fill="#FF7800" opacity=".85"/>
        <ellipse cx="65" cy="23" rx="11" ry="2.5" fill="none" stroke="rgba(255,220,80,.28)" stroke-width=".8"/>
        <ellipse cx="65" cy="26" rx="12.5" ry="2" fill="none" stroke="rgba(255,200,60,.32)" stroke-width=".9"/>
        <circle cx="65" cy="26" r="6"  fill="#FFC040" opacity=".9"/>
        <circle cx="65" cy="26" r="2.5" fill="#FFF0A0"/>
        <line x1="79" y1="26" x2="98" y2="26" stroke="rgba(255,180,60,.22)" stroke-width=".8"/>
        <circle cx="114" cy="26" r="18" fill="url(#fm1)" opacity=".5"/>
        <circle cx="114" cy="26" r="10" fill="#FF9400" opacity=".85"/>
        <circle cx="114" cy="26" r="4"  fill="#FFE060" opacity=".9"/>
      </svg>
      <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:15px;letter-spacing:-.02em;">
        <span style="color:#F2EAD8;">PIXEL</span><span style="background:linear-gradient(118deg,#FFC040,#FF5000);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">BRIDGE</span>
      </div>
    </div>
    <span class="foot-copy">© 2026 — Digital Signage</span>
  </div>
  <div class="foot-right">
    <a href="mailto:tobiasola94@gmail.com">tobiasola94@gmail.com</a>
  </div>
</footer>

<!-- ── SCRIPTS ─────────────────────────────────────────── -->
<script>
/* Cursor */
const cur = document.getElementById('cur'), ring = document.getElementById('ring');
let mx = 0, my = 0, rx = 0, ry = 0;
document.addEventListener('mousemove', e => {
  mx = e.clientX; my = e.clientY;
  cur.style.left = (mx - 2) + 'px'; cur.style.top = (my - 2) + 'px';
});
(function tick() {
  rx += (mx - rx) * .1; ry += (my - ry) * .1;
  ring.style.left = rx + 'px'; ring.style.top = ry + 'px';
  requestAnimationFrame(tick);
})();

/* Nav scroll */
window.addEventListener('scroll', () =>
  document.getElementById('nav').classList.toggle('stuck', scrollY > 40)
);

/* Neural canvas — grande, occupa la parte bassa della hero */
const canvas = document.getElementById('hero-canvas');
const ctx = canvas.getContext('2d');
let W, H, nodes = [];
function resize() {
  W = canvas.width  = canvas.offsetWidth;
  H = canvas.height = canvas.offsetHeight;
}
resize();
window.addEventListener('resize', () => { resize(); initNodes(); });
function initNodes() {
  nodes = [];
  const n = Math.floor(W * H / 9000) + 48;
  for (let i = 0; i < n; i++) nodes.push({
    x:  Math.random() * W,
    y:  Math.random() * H,
    vx: (Math.random() - .5) * .28,
    vy: (Math.random() - .5) * .28,
    r:  Math.random() * 2.2 + .7,
    op: Math.random() * .65 + .25
  });
}
initNodes();
function drawCanvas() {
  ctx.clearRect(0, 0, W, H);
  /* connessioni */
  for (let i = 0; i < nodes.length; i++) {
    for (let j = i + 1; j < nodes.length; j++) {
      const dx = nodes[i].x - nodes[j].x, dy = nodes[i].y - nodes[j].y;
      const d = Math.sqrt(dx*dx + dy*dy);
      if (d < 130) {
        const a = (1 - d / 130) * .22;
        const g = ctx.createLinearGradient(nodes[i].x, nodes[i].y, nodes[j].x, nodes[j].y);
        g.addColorStop(0,  `rgba(255,160,0,${a})`);
        g.addColorStop(.5, `rgba(255,80,0,${a * .6})`);
        g.addColorStop(1,  `rgba(255,160,0,${a})`);
        ctx.beginPath();
        ctx.strokeStyle = g; ctx.lineWidth = .6;
        ctx.moveTo(nodes[i].x, nodes[i].y);
        ctx.lineTo(nodes[j].x, nodes[j].y);
        ctx.stroke();
      }
    }
  }
  /* nodi con alone */
  nodes.forEach(n => {
    const halo = ctx.createRadialGradient(n.x, n.y, 0, n.x, n.y, n.r * 7);
    halo.addColorStop(0, `rgba(255,160,0,${n.op * .3})`);
    halo.addColorStop(1, 'rgba(255,80,0,0)');
    ctx.beginPath(); ctx.arc(n.x, n.y, n.r * 7, 0, Math.PI * 2);
    ctx.fillStyle = halo; ctx.fill();
    ctx.beginPath(); ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
    ctx.fillStyle = `rgba(255,200,64,${n.op})`; ctx.fill();
    n.x += n.vx; n.y += n.vy;
    if (n.x < -20) n.x = W + 20; if (n.x > W + 20) n.x = -20;
    if (n.y < -20) n.y = H + 20; if (n.y > H + 20) n.y = -20;
  });
  requestAnimationFrame(drawCanvas);
}
drawCanvas();

/* Scroll reveal */
const revEls = document.querySelectorAll('.reveal');
const io = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      const i = Array.from(revEls).indexOf(e.target);
      setTimeout(() => e.target.classList.add('in'), (i % 4) * 70);
      io.unobserve(e.target);
    }
  });
}, { threshold: .09 });
revEls.forEach(el => io.observe(el));

/* Signal dots — logo CTA */
const ctaSigL = document.getElementById('cta-sig-l');
const ctaSigR = document.getElementById('cta-sig-r');
let ctaTL = 0, ctaTR = 0.5;
(function animCtaSig() {
  ctaTL = (ctaTL + .003) % 1;
  ctaTR = (ctaTR + .003) % 1;
  const eL = ctaTL < .5 ? 2*ctaTL*ctaTL : 1 - Math.pow(-2*ctaTL+2,2)/2;
  const eR = ctaTR < .5 ? 2*ctaTR*ctaTR : 1 - Math.pow(-2*ctaTR+2,2)/2;
  ctaSigL.setAttribute('cx', 102 + (182-102)*eL);
  ctaSigL.setAttribute('cy', 100);
  const oL = ctaTL > .88 ? (1-ctaTL)*8.3 : Math.min(ctaTL*7, .9);
  ctaSigL.setAttribute('opacity', Math.max(0, Math.min(1, oL)));
  ctaSigR.setAttribute('cx', 358 + (258-358)*eR);
  ctaSigR.setAttribute('cy', 100);
  const oR = ctaTR > .88 ? (1-ctaTR)*8.3 : Math.min(ctaTR*7, .9);
  ctaSigR.setAttribute('opacity', Math.max(0, Math.min(1, oR)));
  requestAnimationFrame(animCtaSig);
})();

/* Smooth scroll */
document.querySelectorAll('a[href^="#"]').forEach(a =>
  a.addEventListener('click', e => {
    const t = document.querySelector(a.getAttribute('href'));
    if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); }
  })
);
</script>
</body>
</html>