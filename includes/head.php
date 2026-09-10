<!DOCTYPE html>
<html lang="it" class="light" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' — PixelBridge' : 'PixelBridge'; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Hanken+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
<style>
/* ── TOKENS ─────────────────────────────────────────────────── */
.light {
  --bg:           #f0f0f4;
  --surface:      #ffffff;
  --surface-low:  #f7f7fa;
  --surface-mid:  #eeeef4;
  --surface-high: #e4e4ec;
  --on-surface:   #0d0d14;
  --on-variant:   #6b6b7a;
  --outline:      #c8c8d4;
  --outline-var:  #e8e8f0;
  --blue:         #2578D1;
  --blue-dim:     #1a5faa;
  --blue-bg:      #eaf2ff;
  --blue-text:    #1558a8;
  --violet:       #7126D1;
  --violet-dim:   #5a1aaa;
  --violet-bg:    #f2ebff;
  --violet-text:  #5a1aaa;
  --success:      #0d7a3e;
  --success-bg:   #e0f5ea;
  --success-dim:  #22c55e;
  --warn:         #8a5c00;
  --warn-bg:      #fff4d6;
  --error:        #c0180c;
  --error-bg:     #ffecea;
  --shadow-sm:    0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);
  --shadow-md:    0 4px 20px rgba(37,120,209,0.10);
  --shadow-card:  0 2px 8px rgba(0,0,0,0.05);
  --radius:       14px;
  --radius-sm:    8px;
  --radius-xs:    6px;
}
.dark {
  --bg:           #050e1a;
  --surface:      #0d1f33;
  --surface-low:  #112540;
  --surface-mid:  #1a2f46;
  --surface-high: #223550;
  --on-surface:   #e0eeff;
  --on-variant:   #8a9ab0;
  --outline:      #2e3f54;
  --outline-var:  #1e3250;
  --blue:         #5aaeff;
  --blue-dim:     #3d8fde;
  --blue-bg:      #0a1e38;
  --blue-text:    #90c8ff;
  --violet:       #a06aff;
  --violet-dim:   #8040e0;
  --violet-bg:    #1a0a36;
  --violet-text:  #c8a0ff;
  --success:      #4ade80;
  --success-bg:   #042214;
  --success-dim:  #22c55e;
  --warn:         #fbbf24;
  --warn-bg:      #2a1800;
  --error:        #ff7b6b;
  --error-bg:     #2a0800;
  --shadow-sm:    0 1px 3px rgba(0,0,0,0.4);
  --shadow-md:    0 4px 20px rgba(0,0,0,0.4);
  --shadow-card:  0 2px 12px rgba(0,0,0,0.5);
  --radius:       14px;
  --radius-sm:    8px;
  --radius-xs:    6px;
}

/* ── RESET ──────────────────────────────────────────────────── */
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--on-surface);font-family:'Inter',sans-serif;font-size:14px;line-height:1.5;min-height:100vh;transition:background .25s,color .25s}
a{text-decoration:none;color:inherit}
button{font-family:'Inter',sans-serif;cursor:pointer}
svg{display:block;flex-shrink:0}

/* ── TOPNAV ─────────────────────────────────────────────────── */
.topnav{position:fixed;top:0;left:0;right:0;z-index:100;height:60px;background:var(--surface);border-bottom:1px solid var(--outline-var);display:flex;align-items:center;padding:0 20px;gap:12px;transition:background .25s}
.logo{font-family:'Hanken Grotesk',sans-serif;font-size:19px;font-weight:800;letter-spacing:-0.5px;color:var(--blue);white-space:nowrap;flex-shrink:0}
.logo em{color:var(--violet);font-style:normal}
.topnav-links{display:flex;gap:2px;flex:1;overflow:hidden}
.topnav-link{font-size:13px;font-weight:500;color:var(--on-variant);padding:6px 12px;border-radius:var(--radius-xs);white-space:nowrap;transition:all .12s}
.topnav-link:hover{background:var(--surface-mid);color:var(--on-surface)}
.topnav-link.active{color:var(--on-surface);font-weight:600}
.topnav-right{display:flex;align-items:center;gap:8px;flex-shrink:0}
.search-box{display:flex;align-items:center;gap:8px;background:var(--surface-low);border:1px solid var(--outline-var);border-radius:var(--radius-sm);padding:0 12px;height:36px;width:200px;transition:all .15s}
.search-box:focus-within{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,120,209,0.12);width:240px}
.search-box input{background:none;border:none;outline:none;font-size:13px;font-family:'Inter',sans-serif;color:var(--on-surface);width:100%}
.search-box input::placeholder{color:var(--on-variant)}
.icon-btn{width:36px;height:36px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;background:var(--surface-low);border:1px solid var(--outline-var);color:var(--on-variant);transition:all .12s;position:relative}
.icon-btn:hover{background:var(--surface-mid);color:var(--on-surface);border-color:var(--outline)}
.notif-pip{position:absolute;top:7px;right:7px;width:7px;height:7px;border-radius:50%;background:var(--error);border:1.5px solid var(--surface)}
.avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--blue),var(--violet));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;border:2px solid var(--outline-var)}
.hamburger{display:none;width:36px;height:36px;border-radius:var(--radius-sm);background:var(--surface-low);border:1px solid var(--outline-var);align-items:center;justify-content:center;color:var(--on-variant)}

/* ── LAYOUT ─────────────────────────────────────────────────── */
.app{display:flex;padding-top:60px;min-height:100vh}

/* ── SIDEBAR ────────────────────────────────────────────────── */
.sidebar{width:260px;flex-shrink:0;background:var(--surface);border-right:1px solid var(--outline-var);display:flex;flex-direction:column;position:sticky;top:60px;height:calc(100vh - 60px);overflow-y:auto;transition:transform .25s,background .25s}
.sidebar-top{padding:18px 16px 14px;border-bottom:1px solid var(--outline-var)}
.entity-row{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.entity-ico{width:42px;height:42px;border-radius:12px;flex-shrink:0;background:linear-gradient(135deg,var(--blue-bg),var(--violet-bg));border:1px solid var(--outline-var);display:flex;align-items:center;justify-content:center}
.entity-name{font-family:'Hanken Grotesk',sans-serif;font-size:14px;font-weight:700;color:var(--on-surface)}
.entity-sub{font-size:11px;color:var(--on-variant);margin-top:1px}
.deploy-btn{width:100%;background:linear-gradient(135deg,var(--blue),var(--violet));color:#fff;border:none;border-radius:10px;padding:10px 16px;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .15s}
.deploy-btn:hover{opacity:.88}
.nav{padding:10px;flex:1}
.nav-sep{font-size:11px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.06em;padding:14px 10px 4px}
.nav-item{display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:10px;color:var(--on-variant);font-size:13px;font-weight:500;transition:all .12s;margin-bottom:1px}
.nav-item:hover{background:var(--surface-mid);color:var(--on-surface)}
.nav-item.active{background:var(--blue-bg);color:var(--blue-text);font-weight:600}
.sidebar-footer{padding:12px;border-top:1px solid var(--outline-var)}
.tenant-card{background:var(--surface-low);border:1px solid var(--outline-var);border-radius:10px;padding:12px 14px}
.t-name{font-size:13px;font-weight:600;color:var(--on-surface)}
.t-sub{font-size:11px;color:var(--on-variant);margin-top:2px}
.t-badge{display:inline-flex;align-items:center;gap:5px;margin-top:8px;background:linear-gradient(135deg,var(--blue-bg),var(--violet-bg));border:1px solid var(--outline-var);color:var(--blue-text);font-size:10px;font-weight:700;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.05em}

/* ── MAIN ───────────────────────────────────────────────────── */
.main{flex:1;padding:24px;min-width:0}
.page-head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:22px;gap:12px;flex-wrap:wrap}
.page-title{font-family:'Hanken Grotesk',sans-serif;font-size:26px;font-weight:800;color:var(--on-surface);letter-spacing:-.6px}
.page-sub{font-size:12px;color:var(--on-variant);margin-top:3px}
.head-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}

/* ── BUTTONS ─────────────────────────────────────────────────── */
.btn-primary{display:flex;align-items:center;gap:7px;background:var(--blue);color:#fff;border:none;border-radius:var(--radius-sm);padding:9px 16px;font-size:13px;font-weight:600;transition:all .15s}
.btn-primary:hover{background:var(--blue-dim);box-shadow:0 4px 12px rgba(37,120,209,.3)}
.btn-ghost{display:flex;align-items:center;gap:7px;background:var(--surface);color:var(--on-variant);border:1px solid var(--outline-var);border-radius:var(--radius-sm);padding:9px 16px;font-size:13px;font-weight:500;transition:all .15s}
.btn-ghost:hover{border-color:var(--outline);color:var(--on-surface);background:var(--surface-mid)}
.btn-danger{display:flex;align-items:center;gap:7px;background:var(--error-bg);color:var(--error);border:1px solid transparent;border-radius:var(--radius-sm);padding:9px 16px;font-size:13px;font-weight:600;transition:all .15s}
.btn-danger:hover{background:var(--error);color:#fff}
.seg{display:flex;background:var(--surface-mid);border-radius:var(--radius-sm);padding:3px;gap:2px}
.seg-btn{padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;color:var(--on-variant);border:none;background:none;transition:all .15s}
.seg-btn.active{background:var(--blue);color:#fff}

/* ── GRID ───────────────────────────────────────────────────── */
.g12{display:grid;grid-template-columns:repeat(12,1fr);gap:14px;margin-bottom:14px}

/* ── CARD ───────────────────────────────────────────────────── */
.card{background:var(--surface);border:1px solid var(--outline-var);border-radius:14px!important;-webkit-border-radius:14px!important;box-shadow:var(--shadow-card);transition:box-shadow .18s,background .25s,border-color .25s;overflow:hidden}
.card:hover{box-shadow:var(--shadow-md)}
.cp{padding:20px 22px}
.ch{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.ct{font-family:'Hanken Grotesk',sans-serif;font-size:15px;font-weight:700;color:var(--on-surface)}
.cl{font-size:11px;font-weight:600;color:var(--blue-text);cursor:pointer;display:flex;align-items:center;gap:4px}

/* ── METRIC ─────────────────────────────────────────────────── */
.mlabel{font-size:11px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px}
.mval{font-family:'Hanken Grotesk',sans-serif;font-size:38px;font-weight:800;color:var(--on-surface);letter-spacing:-2px;line-height:1}
.msub{font-size:12px;color:var(--on-variant);margin-top:8px}
.msub.up{color:var(--success)}
.msub.dn{color:var(--error)}
.pbar{height:4px;background:var(--surface-high);border-radius:99px;overflow:hidden;margin-top:12px}
.pfill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--blue),var(--violet))}
.pmeta{display:flex;justify-content:space-between;margin-top:5px;font-size:11px;font-weight:500}
.pmeta .l{color:var(--on-variant)}
.pmeta .r{color:var(--on-surface)}

/* ── ROW ────────────────────────────────────────────────────── */
.row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--outline-var)}
.row:last-child{border-bottom:none}
.rname{font-size:13px;font-weight:500;color:var(--on-surface)}
.rsub{font-size:11px;color:var(--on-variant);margin-top:2px}

/* ── STATUS DOT ─────────────────────────────────────────────── */
.dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.dot.on{background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.15)}
.dot.off{background:var(--outline)}
@keyframes dotpulse{0%,100%{box-shadow:0 0 0 3px rgba(34,197,94,.15)}50%{box-shadow:0 0 0 5px rgba(34,197,94,.05)}}
.dot.on{animation:dotpulse 2.5s ease infinite}

/* ── BADGE ──────────────────────────────────────────────────── */
.badge{font-size:11px;font-weight:600;padding:3px 9px;border-radius:20px;white-space:nowrap}
.badge.on{background:var(--success-bg);color:var(--success)}
.badge.off{background:var(--surface-high);color:var(--on-variant)}
.badge.warn{background:var(--warn-bg);color:var(--warn)}
.badge.err{background:var(--error-bg);color:var(--error)}
.badge.blue{background:var(--blue-bg);color:var(--blue-text)}
.badge.vio{background:var(--violet-bg);color:var(--violet-text)}

/* ── ALERT ──────────────────────────────────────────────────── */
.alert-row{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--outline-var)}
.alert-row:last-child{border-bottom:none}
.aico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.atitle{font-size:13px;font-weight:500;color:var(--on-surface)}
.ameta{font-size:11px;color:var(--on-variant);margin-top:3px}

/* ── FORM ───────────────────────────────────────────────────── */
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:12px;font-weight:600;color:var(--on-variant);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em}
.form-input{width:100%;background:var(--surface-low);border:1px solid var(--outline-var);border-radius:var(--radius-sm);padding:10px 14px;font-size:14px;font-family:'Inter',sans-serif;color:var(--on-surface);outline:none;transition:all .15s}
.form-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,120,209,.12);background:var(--surface)}
.form-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b6b7a' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px}

/* ── TABLE ──────────────────────────────────────────────────── */
.table{width:100%;border-collapse:collapse}
.table th{text-align:left;padding:10px 14px;font-size:11px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--outline-var)}
.table td{padding:12px 14px;border-bottom:1px solid var(--outline-var);font-size:13px;color:var(--on-surface)}
.table tr:last-child td{border-bottom:none}
.table tr:hover td{background:var(--surface-low)}

/* ── BTN-SM (small action buttons, used in cards) ───────────── */
.btn-sm{display:flex;align-items:center;gap:5px;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:500;font-family:'Inter',sans-serif;cursor:pointer;transition:all .12s;border:1px solid var(--outline-var);background:var(--surface-low);color:var(--on-variant)}
.btn-sm:hover{background:var(--surface-mid);color:var(--on-surface);border-color:var(--outline)}
.btn-sm.danger:hover{background:var(--error-bg);color:var(--error);border-color:transparent}

/* ── EMPTY STATE ─────────────────────────────────────────────── */
.empty{text-align:center;padding:48px 24px;color:var(--on-variant)}
.empty svg{margin:0 auto 16px;opacity:.4}
.empty-title{font-size:15px;font-weight:600;color:var(--on-surface);margin-bottom:6px}
.empty-sub{font-size:13px}

/* ── RESPONSIVE ─────────────────────────────────────────────── */
@media(max-width:1100px){
  .g12>.c8{grid-column:span 12!important}
  .g12>.c4{grid-column:span 6!important}
  .g12>.c5{grid-column:span 6!important}
  .g12>.c3{grid-column:span 6!important}
}
@media(max-width:860px){
  .topnav-links,.search-box{display:none}
  .hamburger{display:flex}
  .sidebar{position:fixed;top:60px;left:0;bottom:0;z-index:90;transform:translateX(-100%);box-shadow:4px 0 24px rgba(0,0,0,.15)}
  .sidebar.open{transform:translateX(0)}
  .main{padding:16px}
  .g12>.c3,.g12>.c4,.g12>.c5,.g12>.c6,.g12>.c7,.g12>.c8{grid-column:span 12!important}
  .page-title{font-size:20px}
  .head-actions .btn-ghost{display:none}
}
@media(max-width:540px){
  .g12{gap:10px;margin-bottom:10px}
  .topnav{padding:0 14px}
  .cp{padding:16px}
  .mval{font-size:30px}
}
</style>
<script>
// Theme persistence
(function(){
  const t = localStorage.getItem('pb_theme') || 'light';
  document.documentElement.className = t;
})();
</script>
