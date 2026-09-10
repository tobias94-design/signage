<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/widget_catalog.php';

$type = $_GET['widget'] ?? '';
$catalog = getWidgetCatalog();
$w = $catalog[$type] ?? null;

if (!$w) {
    header('Location: /widgets.php');
    exit;
}

$page_title = $w['label'];
$db  = getDB();
$tid = TENANT_ID;
$tenantPlan = getTenantPlan($db, $tid);
$unlocked = isWidgetUnlocked($type, $catalog, $tenantPlan);
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&display=swap">
<style>
.wd-header{display:flex;align-items:center;gap:16px;margin-bottom:24px}
.wd-icon{width:56px;height:56px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.wd-title{font-family:'Hanken Grotesk',sans-serif;font-size:24px;font-weight:700;color:var(--on-surface)}
.wd-desc{font-size:13px;color:var(--on-variant);margin-top:2px;max-width:600px}

.wd-section{margin-bottom:28px}
.wd-section-title{font-family:'Hanken Grotesk',sans-serif;font-size:15px;font-weight:700;color:var(--on-surface);margin-bottom:12px;display:flex;align-items:center;gap:8px}

.wd-feat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px}
.wd-feat-card{background:var(--surface);border:1px solid var(--outline-var);border-radius:10px;padding:12px 14px;font-size:12.5px;color:var(--on-variant);display:flex;gap:8px;align-items:flex-start}
.wd-feat-card i{color:var(--blue);margin-top:2px;flex-shrink:0}

.wd-settings-table{background:var(--surface);border:1px solid var(--outline-var);border-radius:12px;overflow:hidden}
.wd-setting-row{display:flex;gap:12px;padding:12px 16px;border-bottom:1px solid var(--outline-var);align-items:center}
.wd-setting-row:last-child{border-bottom:none}
.wd-setting-key{font-family:monospace;font-size:11px;color:var(--blue-text);background:var(--blue-bg);padding:2px 8px;border-radius:5px;flex-shrink:0;width:140px}
.wd-setting-info{flex:1;min-width:0}
.wd-setting-label{font-size:13px;font-weight:600;color:var(--on-surface)}
.wd-setting-type{font-size:11px;color:var(--on-variant);margin-left:6px}
.wd-setting-desc{font-size:11.5px;color:var(--on-variant);margin-top:2px}

.wd-variant-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
.wd-variant-card{background:var(--surface);border:1px solid var(--outline-var);border-radius:12px;padding:16px}
.wd-variant-tags{display:flex;gap:6px;margin-bottom:8px}
.wd-variant-tag{font-size:9px;font-weight:700;padding:2px 8px;border-radius:10px;background:var(--surface-mid);color:var(--on-variant);text-transform:uppercase}
.wd-variant-name{font-size:14px;font-weight:700;color:var(--on-surface);margin-bottom:6px}
.wd-variant-desc{font-size:12px;color:var(--on-variant);line-height:1.5}

/* Galleria template grafici */
.wd-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
.wd-preset-card{background:var(--surface);border:2px solid var(--outline-var);border-radius:14px;overflow:hidden;transition:all .18s;cursor:pointer}
.wd-preset-card:hover{border-color:var(--blue);box-shadow:var(--shadow-md)}
.wd-preset-preview{height:140px;background:#0d0d14;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden}
.wd-preset-info{padding:14px 16px}
.wd-preset-name{font-size:14px;font-weight:700;color:var(--on-surface);margin-bottom:4px}
.wd-preset-desc{font-size:11.5px;color:var(--on-variant);line-height:1.4}
.wd-preset-cta{padding:10px 16px;border-top:1px solid var(--outline-var);font-size:11px;font-weight:600;color:var(--blue-text);display:flex;align-items:center;justify-content:center;gap:6px}

.wd-back{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--on-variant);margin-bottom:16px}
.wd-back:hover{color:var(--on-surface)}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/topnav.php'; ?>
<div class="app">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main">

    <a href="/widgets.php" class="wd-back">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Torna al Catalogo
    </a>

    <div class="wd-header">
      <div class="wd-icon" style="background:<?php echo $w['color']; ?>22;color:<?php echo $w['color']; ?>">
        <i class="fa-solid <?php echo $w['icon']; ?>"></i>
      </div>
      <div>
        <div class="wd-title"><?php echo $w['label']; ?></div>
        <div class="wd-desc"><?php echo $w['descrizione']; ?></div>
      </div>
      <div style="margin-left:auto;display:flex;gap:8px">
        <span class="wcat-badge <?php echo $w['tier']; ?>" style="padding:5px 12px;font-size:10px"><?php echo $w['tier']==='base'?'Base':($unlocked?'Sbloccato':'Premium'); ?></span>
      </div>
    </div>

    <?php if (!empty($w['template_grafici'])): ?>
    <div class="wd-section">
      <div class="wd-section-title">
        <i class="fa-solid fa-palette" style="color:<?php echo $w['color']; ?>"></i>
        Template grafici
      </div>
      <div class="wd-gallery">
        <?php foreach ($w['template_grafici'] as $preset):
          $pc = $preset['config'];
          $stile = $pc['stile'] ?? 'ring_pill';
          $textColor = $pc['text_color'] ?? '#ffffff';
          $progressColor = $pc['colore_progresso'] ?? '#F7192E';
        ?>
        <div class="wd-preset-card" onclick="window.location.href='/templates.php'">
          <div class="wd-preset-preview">
            <?php if ($stile==='ring_pill'): ?>
            <div style="position:relative;width:150px;height:60px;display:flex;align-items:center;justify-content:center">
              <div style="position:absolute;inset:2px;border-radius:999px;background:<?php echo $pc['sfondo_ora']??'#111111'; ?>;opacity:<?php echo $pc['sfondo_ora_opacity']??0.55; ?>"></div>
              <svg viewBox="0 0 150 60" style="position:absolute;inset:0;width:100%;height:100%">
                <rect x="4" y="4" width="142" height="52" rx="26" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="4"/>
                <rect x="4" y="4" width="142" height="52" rx="26" fill="none" stroke="<?php echo $progressColor; ?>" stroke-width="4" stroke-dasharray="280" stroke-dashoffset="95"/>
              </svg>
              <span style="position:relative;font-size:20px;font-weight:700;color:<?php echo $textColor; ?>;font-variant-numeric:tabular-nums">14:32:07</span>
            </div>
            <?php elseif ($stile==='flip'): ?>
            <div style="display:flex;align-items:center;gap:4px">
              <?php foreach (['1','4',':','3','2',':','0','7'] as $ch): ?>
                <?php if ($ch===':'): ?>
                <span style="font-size:20px;font-weight:700;color:<?php echo $textColor; ?>;opacity:.5">:</span>
                <?php else: ?>
                <div style="background:<?php echo $pc['sfondo_ora']??'#141414'; ?>;color:<?php echo $textColor; ?>;font-size:20px;font-weight:800;border-radius:5px;padding:4px 7px;position:relative;font-variant-numeric:tabular-nums"><?php echo $ch; ?>
                  <div style="position:absolute;left:0;right:0;top:50%;height:1px;background:rgba(0,0,0,.5)"></div>
                </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
            <?php elseif ($stile==='bar_below'): ?>
            <div style="text-align:center;width:75%">
              <div style="font-size:22px;font-weight:700;color:<?php echo $textColor; ?>;font-variant-numeric:tabular-nums">14:32:07</div>
              <div style="width:100%;height:5px;background:rgba(255,255,255,.15);border-radius:999px;margin-top:8px;overflow:hidden">
                <div style="height:100%;width:35%;background:linear-gradient(90deg,<?php echo $pc['colore_barra_a']??'#22c55e'; ?>,<?php echo $pc['colore_barra_b']??'#2dd4bf'; ?>);border-radius:999px"></div>
              </div>
            </div>
            <?php elseif ($stile==='ring_seconds'): ?>
            <div style="display:flex;flex-direction:column;align-items:center;gap:6px">
              <span style="font-size:12px;font-weight:600;color:<?php echo $textColor; ?>;opacity:.7;font-variant-numeric:tabular-nums">14:32</span>
              <div style="position:relative;width:64px;height:64px">
                <svg viewBox="0 0 100 100" style="width:100%;height:100%;transform:rotate(-90deg)">
                  <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="8"/>
                  <circle cx="50" cy="50" r="42" fill="none" stroke="<?php echo $progressColor; ?>" stroke-width="8" stroke-linecap="round" stroke-dasharray="264" stroke-dashoffset="172"/>
                </svg>
                <span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:<?php echo $textColor; ?>;font-variant-numeric:tabular-nums">07</span>
              </div>
            </div>
            <?php elseif ($stile==='neon_gym'): ?>
            <div style="text-align:center;padding:12px 18px;border-radius:12px;background:<?php echo $pc['sfondo_ora']??'#0a0a0a'; ?>">
              <span style="font-size:20px;font-weight:800;color:<?php echo $progressColor; ?>;text-shadow:0 0 6px <?php echo $progressColor; ?>,0 0 16px <?php echo $progressColor; ?>;font-variant-numeric:tabular-nums">14:32:07</span>
            </div>
            <?php elseif ($stile==='lista'): ?>
            <div style="width:100%;padding:10px;text-align:left">
              <div style="font-size:11px;font-weight:700;color:<?php echo $textColor; ?>;margin-bottom:6px">In programma oggi</div>
              <div style="display:flex;align-items:center;gap:6px;padding:4px;border-bottom:1px solid rgba(255,255,255,.1);opacity:.35">
                <span style="font-size:10px;color:<?php echo $textColor; ?>">17:00</span>
                <span style="font-size:11px;font-weight:700;color:<?php echo $pc['colore_corso']??'#fff'; ?>;text-transform:uppercase;flex:1">Pilates</span>
              </div>
              <div style="display:flex;align-items:center;gap:6px;padding:4px 6px;background:rgba(247,25,46,.08);border-left:3px solid <?php echo $progressColor; ?>;border-bottom:1px solid rgba(255,255,255,.1)">
                <span style="font-size:10px;color:<?php echo $progressColor; ?>">18:00</span>
                <span style="font-size:11px;font-weight:700;color:<?php echo $pc['colore_corso']??'#fff'; ?>;text-transform:uppercase;flex:1">Spinning</span>
                <span style="font-size:8px;font-weight:700;color:#fff;background:<?php echo $progressColor; ?>;padding:1px 5px;border-radius:8px">LIVE</span>
              </div>
              <div style="display:flex;align-items:center;gap:6px;padding:4px">
                <span style="font-size:10px;color:<?php echo $textColor; ?>">19:00</span>
                <span style="font-size:11px;font-weight:700;color:<?php echo $pc['colore_corso']??'#fff'; ?>;text-transform:uppercase;flex:1">Yoga</span>
              </div>
            </div>
            <?php elseif ($stile==='lobby'): ?>
            <div style="width:100%;background:#000;padding:8px 12px">
              <div style="display:flex;justify-content:space-between;border-bottom:2px solid <?php echo $progressColor; ?>;padding-bottom:5px;margin-bottom:6px">
                <span style="font-size:11px;font-weight:800;color:<?php echo $textColor; ?>;text-transform:uppercase;letter-spacing:1px">In programma</span>
                <span style="font-size:8px;color:rgba(255,255,255,.5);text-transform:uppercase">Lun 08 Lug</span>
              </div>
              <div style="display:flex;align-items:center;gap:8px;padding:4px 6px;background:rgba(247,25,46,.1);border-left:3px solid <?php echo $progressColor; ?>">
                <span style="font-size:9px;color:<?php echo $progressColor; ?>">18:00</span>
                <span style="font-size:13px;font-weight:800;color:<?php echo $pc['colore_corso']??'#fff'; ?>;text-transform:uppercase;flex:1">Spinning</span>
                <span style="display:flex;align-items:center;gap:3px;font-size:8px;font-weight:700;color:<?php echo $progressColor; ?>;text-transform:uppercase"><span style="width:5px;height:5px;border-radius:50%;background:<?php echo $progressColor; ?>"></span>LIVE</span>
              </div>
              <div style="font-size:8px;color:rgba(255,255,255,.4);margin-top:6px;border-top:1px solid rgba(255,255,255,.1);padding-top:4px">Spinning 18:00 ★ Yoga 19:00</div>
            </div>
            <?php elseif ($stile==='stazione'): ?>
            <div style="width:100%;background:#000;padding:8px">
              <div style="font-size:9px;font-weight:700;color:<?php echo $textColor; ?>;text-transform:uppercase;letter-spacing:2px;border-bottom:1px solid rgba(255,255,255,.2);padding-bottom:4px;margin-bottom:6px;font-family:'Space Mono',monospace">In programma</div>
              <div style="display:flex;align-items:center;gap:4px;margin-bottom:4px">
                <?php foreach (str_split('18:00') as $i => $ch): ?>
                <div style="display:inline-flex;align-items:center;justify-content:center;width:12px;height:15px;font-size:9px;font-weight:800;font-family:'Space Mono',monospace;border-radius:2px;background:<?php echo $progressColor; ?>;color:#000;position:relative">
                  <?php echo $ch; ?>
                  <div style="position:absolute;left:0;right:0;top:50%;height:1px;background:rgba(0,0,0,.5)"></div>
                </div>
                <?php endforeach; ?>
                <div style="display:flex;gap:2px;margin-left:10px">
                <?php foreach (str_split('SPINNING') as $ch): ?>
                <div style="display:inline-flex;align-items:center;justify-content:center;width:11px;height:14px;font-size:9px;font-weight:800;font-family:'Space Mono',monospace;border-radius:2px;background:<?php echo $progressColor; ?>;opacity:.85;color:#000;position:relative">
                  <?php echo $ch; ?>
                  <div style="position:absolute;left:0;right:0;top:50%;height:1px;background:rgba(0,0,0,.5)"></div>
                </div>
                <?php endforeach; ?>
                </div>
              </div>
              <div style="font-size:8px;color:rgba(255,255,255,.4);font-family:'Space Mono',monospace">17:00 &nbsp; PILATES (passato, sfumato)</div>
            </div>
            <?php elseif ($stile==='card'): ?>
            <div style="text-align:center;color:<?php echo $textColor; ?>">
              <svg width="36" height="36" viewBox="0 0 24 24"><circle cx="9" cy="9" r="4.5" fill="#FDB813"/><path d="M6 20a4.5 4.5 0 01.4-9 5.5 5.5 0 0110.5 1.8A4 4 0 0118 20H6z" fill="#CBD5E1"/></svg>
              <div style="font-size:22px;font-weight:700;margin-top:2px">22°C</div>
              <?php if ($pc['mostra_descrizione']??true): ?><div style="font-size:10px;opacity:.7">Parzialmente nuvoloso</div><?php endif; ?>
              <div style="font-size:9px;opacity:.6;margin-top:2px">Soave</div>
            </div>
            <?php elseif ($stile==='minimal'): ?>
            <div style="display:flex;align-items:center;gap:8px;color:<?php echo $textColor; ?>">
              <svg width="24" height="24" viewBox="0 0 24 24"><circle cx="9" cy="9" r="4.5" fill="#FDB813"/><path d="M6 20a4.5 4.5 0 01.4-9 5.5 5.5 0 0110.5 1.8A4 4 0 0118 20H6z" fill="#CBD5E1"/></svg>
              <span style="font-size:20px;font-weight:700">22°</span>
            </div>
            <?php elseif ($stile==='dettagliato'): ?>
            <div style="text-align:center;color:<?php echo $textColor; ?>">
              <svg width="44" height="44" viewBox="0 0 24 24"><circle cx="9" cy="9" r="4.5" fill="#FDB813"/><path d="M6 20a4.5 4.5 0 01.4-9 5.5 5.5 0 0110.5 1.8A4 4 0 0118 20H6z" fill="#CBD5E1"/></svg>
              <div style="font-size:24px;font-weight:700;margin-top:4px">22°C</div>
              <?php if ($pc['mostra_descrizione']??true): ?><div style="font-size:10px;opacity:.7">Parzialmente nuvoloso</div><?php endif; ?>
              <div style="font-size:9px;opacity:.5;margin-top:3px">↑26° ↓17°</div>
              <div style="font-size:9px;opacity:.6;margin-top:2px">Soave</div>
            </div>
            <?php elseif ($stile==='classico'): ?>
            <div style="text-align:center;color:<?php echo $textColor; ?>">
              <div style="font-size:9px;opacity:.7;margin-bottom:4px">Riapriamo tra</div>
              <div style="display:flex;gap:8px;justify-content:center">
                <?php foreach (['12'=>'giorni','04'=>'ore','23'=>'min'] as $v=>$l): ?>
                <div style="text-align:center"><div style="font-size:18px;font-weight:700;font-variant-numeric:tabular-nums"><?php echo $v; ?></div><div style="font-size:7px;opacity:.5"><?php echo $l; ?></div></div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php elseif ($stile==='flip' && $type==='countdown'): ?>
            <div style="text-align:center;color:<?php echo $textColor; ?>">
              <div style="font-size:9px;opacity:.7;margin-bottom:6px">Riapriamo tra</div>
              <div style="display:flex;gap:8px;justify-content:center">
                <?php foreach (['12'=>'giorni','04'=>'ore','23'=>'min'] as $v=>$l): ?>
                <div style="text-align:center">
                  <div style="display:flex;gap:2px">
                  <?php foreach (str_split($v) as $ch): ?>
                    <div style="width:14px;height:17px;display:flex;align-items:center;justify-content:center;background:#141414;border-radius:2px;font-family:'Space Mono',monospace;font-weight:800;font-size:11px;position:relative">
                      <?php echo $ch; ?>
                      <div style="position:absolute;left:0;right:0;top:50%;height:1px;background:rgba(0,0,0,.5)"></div>
                    </div>
                  <?php endforeach; ?>
                  </div>
                  <div style="font-size:7px;opacity:.5;margin-top:3px;text-transform:uppercase"><?php echo $l; ?></div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php else: /* minimal_apple */ ?>
            <div style="text-align:center">
              <div style="font-size:26px;font-weight:300;letter-spacing:2px;color:<?php echo $textColor; ?>;font-variant-numeric:tabular-nums">14:32</div>
              <?php if ($pc['mostra_data']??true): ?><div style="font-size:11px;color:<?php echo $textColor; ?>;opacity:.55;margin-top:4px;letter-spacing:1px">Lun 08 Lug</div><?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="wd-preset-info">
            <div class="wd-preset-name"><?php echo $preset['nome']; ?></div>
            <div class="wd-preset-desc"><?php echo $preset['descrizione']; ?></div>
          </div>
          <div class="wd-preset-cta">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            Usa nell'editor
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="font-size:11px;color:var(--on-variant);margin-top:10px">
        Questi template si applicano dal pannello proprietà del widget nell'editor Template Layout — sono un punto di partenza, ogni valore resta modificabile dopo.
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($w['funzionalita'])): ?>
    <div class="wd-section">
      <div class="wd-section-title">
        <i class="fa-solid fa-bolt" style="color:<?php echo $w['color']; ?>"></i>
        Funzionalità principali
      </div>
      <div class="wd-feat-grid">
        <?php foreach ($w['funzionalita'] as $f): ?>
        <div class="wd-feat-card">
          <i class="fa-solid fa-check"></i>
          <span><?php echo $f; ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($w['impostazioni'])): ?>
    <div class="wd-section">
      <div class="wd-section-title">
        <i class="fa-solid fa-sliders" style="color:<?php echo $w['color']; ?>"></i>
        Impostazioni disponibili
      </div>
      <div class="wd-settings-table">
        <?php foreach ($w['impostazioni'] as $s): ?>
        <div class="wd-setting-row">
          <span class="wd-setting-key"><?php echo $s['chiave']; ?></span>
          <div class="wd-setting-info">
            <span class="wd-setting-label"><?php echo $s['label']; ?></span><span class="wd-setting-type"><?php echo $s['tipo']; ?></span>
            <div class="wd-setting-desc"><?php echo $s['descrizione']; ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($w['varianti'])): ?>
    <div class="wd-section">
      <div class="wd-section-title">
        <i class="fa-solid fa-shapes" style="color:<?php echo $w['color']; ?>"></i>
        Varianti di contesto
      </div>
      <div class="wd-variant-grid">
        <?php foreach ($w['varianti'] as $v): ?>
        <div class="wd-variant-card">
          <div class="wd-variant-tags">
            <span class="wd-variant-tag"><?php echo $v['contesto']; ?></span>
            <span class="wd-variant-tag"><?php echo $v['formato']; ?></span>
          </div>
          <div class="wd-variant-name"><?php echo $v['label']; ?></div>
          <div class="wd-variant-desc"><?php echo $v['descrizione']; ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div style="margin-top:8px">
      <a href="/templates.php" class="btn-primary" style="display:inline-flex">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        Apri Editor Template
      </a>
    </div>

  </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
