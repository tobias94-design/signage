<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/widget_catalog.php';

$page_title = 'Catalogo Widget';
$db  = getDB();
$tid = TENANT_ID;

$catalog = getWidgetCatalog();
$tenantPlan = getTenantPlan($db, $tid);

// Super Admin: gestione piano/premium per il tenant (solo se implementato più avanti)
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isSuperAdmin()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_plan') {
        // Il piano vero si gestisce solo da Super Admin (tenants.piano) — qui si
        // sceglie soltanto QUALI widget premium specifici il piano Plus sblocca.
        // In precedenza questo form scriveva anche un 'piano' dentro impostazioni,
        // ma getTenantPlan() non lo legge piu' da li': quel controllo era diventato
        // silenziosamente inutile, cambiava un valore che nessuno guardava.
        $premium = $_POST['premium'] ?? [];
        $premium = array_slice($premium, 0, $tenantPlan['piano']==='professional' ? count($catalog) : 3);

        $db->prepare("INSERT INTO impostazioni (tenant_id,chiave,valore) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)")
           ->execute([$tid,'widget_premium',json_encode($premium)]);

        $success = 'Widget premium aggiornati.';
        $tenantPlan = getTenantPlan($db, $tid);
    }
}

// Difensivo: garantisce sempre un piano valido, anche se impostazioni è vuota o inattesa
$piano_attuale = is_string($tenantPlan['piano'] ?? null) ? $tenantPlan['piano'] : 'base';
if (!in_array($piano_attuale, ['base','plus','professional'], true)) $piano_attuale = 'base';
$tenantPlan['piano'] = $piano_attuale;
$tenantPlan['premium'] = is_array($tenantPlan['premium'] ?? null) ? $tenantPlan['premium'] : [];

function pianoLabel(string $p): string {
    return match($p) { 'plus'=>'Plus', 'professional'=>'Professional', default=>'Base' };
}
function pianoColore(string $p): string {
    return match($p) { 'plus'=>'#2578D1', 'professional'=>'#F7192E', default=>'#64748b' };
}
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
.plan-banner{display:flex;align-items:center;gap:14px;padding:18px 22px;border-radius:14px;margin-bottom:20px;color:#fff;background:linear-gradient(135deg,<?php echo pianoColore($tenantPlan['piano']); ?>,<?php echo pianoColore($tenantPlan['piano']); ?>dd)}
.plan-banner-icon{width:44px;height:44px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.plan-banner-title{font-family:'Hanken Grotesk',sans-serif;font-size:16px;font-weight:700}
.plan-banner-sub{font-size:12px;opacity:.85;margin-top:2px}

.wcat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}
.wcat-card{background:var(--surface);border:1px solid var(--outline-var);border-radius:14px;overflow:hidden;transition:all .18s;position:relative}
.wcat-card:hover{box-shadow:var(--shadow-md)}
.wcat-card.locked{opacity:.6}
.wcat-head{padding:16px 18px 12px;display:flex;align-items:center;gap:12px}
.wcat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:16px}
.wcat-name{font-family:'Hanken Grotesk',sans-serif;font-size:15px;font-weight:700;color:var(--on-surface)}
.wcat-badges{display:flex;gap:4px;margin-top:4px}
.wcat-badge{font-size:9px;font-weight:700;padding:2px 7px;border-radius:10px;text-transform:uppercase;letter-spacing:.03em}
.wcat-badge.base{background:var(--success-bg);color:var(--success)}
.wcat-badge.premium{background:var(--blue-bg);color:var(--blue-text)}
.wcat-badge.context{background:var(--surface-mid);color:var(--on-variant)}
.wcat-open-link{color:var(--on-variant);font-size:13px;padding:6px;border-radius:6px;transition:all .12s;flex-shrink:0;align-self:flex-start}
.wcat-open-link:hover{color:var(--blue);background:var(--blue-bg)}
.wcat-desc{padding:0 18px 12px;font-size:12.5px;color:var(--on-variant);line-height:1.5}
.wcat-dims{padding:10px 18px;border-top:1px solid var(--outline-var);display:flex;justify-content:space-between;font-size:11px;color:var(--on-variant)}
.wcat-lock-overlay{position:absolute;top:12px;right:12px;width:26px;height:26px;border-radius:50%;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;color:#fff}

.section-title{font-family:'Hanken Grotesk',sans-serif;font-size:14px;font-weight:700;color:var(--on-surface);margin:24px 0 12px;display:flex;align-items:center;gap:8px}
.section-count{font-size:11px;font-weight:600;color:var(--on-variant);background:var(--surface-mid);padding:2px 8px;border-radius:10px}

/* Dettagli espandibili */
.wcat-toggle{width:100%;text-align:left;background:none;border:none;border-top:1px solid var(--outline-var);padding:9px 18px;font-size:11px;font-weight:600;color:var(--blue-text);cursor:pointer;display:flex;align-items:center;justify-content:space-between}
.wcat-toggle:hover{background:var(--surface-mid)}
.wcat-details{display:none;padding:4px 18px 16px;border-top:1px solid var(--outline-var);background:var(--surface-low)}
.wcat-details.open{display:block}
.wcat-details-title{font-size:10px;font-weight:700;color:var(--on-variant);text-transform:uppercase;letter-spacing:.05em;margin:12px 0 6px}
.wcat-details-title:first-child{margin-top:12px}
.wcat-feat-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:5px}
.wcat-feat-list li{font-size:11.5px;color:var(--on-variant);padding-left:14px;position:relative;line-height:1.4}
.wcat-feat-list li::before{content:'';position:absolute;left:0;top:6px;width:5px;height:5px;border-radius:50%;background:var(--blue)}
.wcat-setting-row{display:flex;gap:8px;padding:5px 0;border-bottom:1px solid var(--outline-var);font-size:11.5px}
.wcat-setting-row:last-child{border-bottom:none}
.wcat-setting-key{font-family:monospace;color:var(--blue-text);background:var(--blue-bg);padding:1px 6px;border-radius:4px;flex-shrink:0;font-size:10px;height:fit-content}
.wcat-setting-body{flex:1;min-width:0}
.wcat-setting-label{font-weight:600;color:var(--on-surface)}
.wcat-setting-desc{color:var(--on-variant);margin-top:1px}
.wcat-variant-card{border:1px solid var(--outline-var);border-radius:8px;padding:10px 12px;margin-bottom:6px;background:var(--surface)}
.wcat-variant-head{display:flex;align-items:center;gap:6px;margin-bottom:4px}
.wcat-variant-label{font-size:12px;font-weight:600;color:var(--on-surface)}
.wcat-variant-tag{font-size:9px;font-weight:700;padding:1px 6px;border-radius:8px;background:var(--surface-mid);color:var(--on-variant);text-transform:uppercase}
.wcat-variant-desc{font-size:11px;color:var(--on-variant);line-height:1.4}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/topnav.php'; ?>
<div class="app">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main">

    <div class="page-head">
      <div>
        <div class="page-title">Catalogo Widget</div>
        <div class="page-sub">Tutti i widget disponibili per i tuoi template</div>
      </div>
    </div>

    <?php if ($success): ?>
    <div style="background:var(--success-bg);border:1px solid rgba(34,197,94,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--success);margin-bottom:16px">
      <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <!-- Piano attuale -->
    <div class="plan-banner">
      <div class="plan-banner-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z"/></svg>
      </div>
      <div style="flex:1">
        <div class="plan-banner-title">Piano <?php echo pianoLabel($tenantPlan['piano']); ?></div>
        <div class="plan-banner-sub">
          <?php if ($tenantPlan['piano']==='base'): ?>
          Widget base inclusi. Passa a Plus o Professional per sbloccare i widget avanzati.
          <?php elseif ($tenantPlan['piano']==='plus'): ?>
          Base + <?php echo count($tenantPlan['premium']); ?>/3 widget premium selezionati.
          <?php else: ?>
          Tutti i widget sbloccati, nessuna sede limitata.
          <?php endif; ?>
        </div>
      </div>
      <?php if ($tenantPlan['piano'] !== 'professional'): ?>
      <a href="mailto:info@pixelbridge.it?subject=Upgrade piano" class="btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:none">Scopri di più</a>
      <?php endif; ?>
    </div>

    <!-- Widget Base -->
    <div class="section-title">Inclusi in ogni piano <span class="section-count"><?php echo count(array_filter($catalog,fn($w)=>$w['tier']==='base')); ?></span></div>
    <div class="wcat-grid">
      <?php foreach ($catalog as $type => $w): if ($w['tier']!=='base') continue; ?>
      <div class="wcat-card" style="cursor:pointer" onclick="window.location.href='/widget_detail.php?widget=<?php echo $type; ?>'">
        <div class="wcat-head">
          <div class="wcat-icon" style="background:<?php echo $w['color']; ?>22;color:<?php echo $w['color']; ?>">
            <i class="fa-solid <?php echo $w['icon']; ?>"></i>
          </div>
          <div style="flex:1">
            <div class="wcat-name"><?php echo $w['label']; ?></div>
            <div class="wcat-badges">
              <span class="wcat-badge base">Base</span>
              <span class="wcat-badge context"><?php echo ucfirst($w['contesto']); ?></span>
            </div>
          </div>
          <span class="wcat-open-link" title="Vedi scheda widget">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
          </span>
        </div>
        <div class="wcat-desc"><?php echo $w['descrizione']; ?></div>
        <div class="wcat-dims">
          <span>Default: <?php echo $w['default']['w']; ?>×<?php echo $w['default']['h']; ?>px</span>
          <?php if (!empty($w['aspect_consigliato'])): ?><span><?php echo $w['aspect_consigliato']; ?></span><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Widget Premium -->
    <div class="section-title">Pool Premium — Plus (3 a scelta) · Professional (tutti) <span class="section-count"><?php echo count(array_filter($catalog,fn($w)=>$w['tier']==='premium')); ?></span></div>
    <div class="wcat-grid">
      <?php foreach ($catalog as $type => $w): if ($w['tier']!=='premium') continue;
        $unlocked = isWidgetUnlocked($type, $catalog, $tenantPlan);
      ?>
      <div class="wcat-card <?php echo $unlocked?'':'locked'; ?>" style="cursor:pointer" onclick="window.location.href='/widget_detail.php?widget=<?php echo $type; ?>'">
        <?php if (!$unlocked): ?>
        <div class="wcat-lock-overlay">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        </div>
        <?php endif; ?>
        <div class="wcat-head">
          <div class="wcat-icon" style="background:<?php echo $w['color']; ?>22;color:<?php echo $w['color']; ?>">
            <i class="fa-solid <?php echo $w['icon']; ?>"></i>
          </div>
          <div style="flex:1">
            <div class="wcat-name"><?php echo $w['label']; ?></div>
            <div class="wcat-badges">
              <span class="wcat-badge premium"><?php echo $unlocked?'Sbloccato':'Premium'; ?></span>
              <span class="wcat-badge context"><?php echo ucfirst($w['contesto']); ?></span>
            </div>
          </div>
          <span class="wcat-open-link" title="Vedi scheda widget">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
          </span>
        </div>
        <div class="wcat-desc"><?php echo $w['descrizione']; ?></div>
        <div class="wcat-dims">
          <span>Default: <?php echo $w['default']['w']; ?>×<?php echo $w['default']['h']; ?>px</span>
          <?php if (!empty($w['aspect_consigliato'])): ?><span><?php echo $w['aspect_consigliato']; ?></span><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (isSuperAdmin()): ?>
    <!-- Pannello Super Admin: gestione piano tenant -->
    <div class="section-title" style="margin-top:32px">Gestione piano (Super Admin)</div>

    <!-- Spiegazione dei 3 piani -->
    <div class="card cp" style="max-width:480px;margin-bottom:14px">
      <div style="font-size:12px;font-weight:700;color:var(--on-variant);text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px">Cosa include ogni piano</div>
      <div style="display:flex;flex-direction:column;gap:10px;font-size:12.5px">
        <div><strong>Base</strong> — tutti i widget gratuiti (Orologio, Data, Immagine, Info/Testo...), nessun widget premium.</div>
        <div><strong>Plus</strong> — Base + fino a 3 widget premium a scelta tra quelli qui sotto.</div>
        <div><strong>Professional / Enterprise</strong> — accesso illimitato a tutti i widget, inclusi tutti i premium.</div>
      </div>
    </div>

    <div class="card cp" style="max-width:480px">
      <div class="form-group">
        <label class="form-label">Piano attuale</label>
        <div style="display:flex;align-items:center;gap:10px">
          <span class="badge <?php echo $tenantPlan['piano']==='base'?'':($tenantPlan['piano']==='plus'?'blue':'on'); ?>" style="font-size:13px;padding:6px 12px"><?php echo pianoLabel($tenantPlan['piano']); ?></span>
          <a href="/superadmin.php" style="font-size:12px;color:var(--blue-text)">Cambia piano da Super Admin →</a>
        </div>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="save_plan">
        <div class="form-group" id="premium-select-group">
          <label class="form-label">Widget premium selezionati (max 3 per il piano Plus, illimitati per Professional/Enterprise)</label>
          <div style="display:flex;flex-direction:column;gap:6px">
            <?php foreach ($catalog as $type => $w): if ($w['tier']!=='premium') continue; ?>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
              <input type="checkbox" name="premium[]" value="<?php echo $type; ?>" class="premium-check" <?php echo in_array($type,$tenantPlan['premium'])?'checked':''; ?> onchange="limitPremiumChecks()">
              <?php echo $w['label']; ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <button type="submit" class="btn-primary" style="margin-top:8px">Salva piano</button>
      </form>
    </div>
    <script>
    const PIANO_ATTUALE = <?php echo json_encode($tenantPlan['piano']); ?>;
    function limitPremiumChecks() {
      if (PIANO_ATTUALE === 'professional') return;
      const checked = document.querySelectorAll('.premium-check:checked');
      if (checked.length > 3) {
        checked[checked.length-1].checked = false;
        alert('Massimo 3 widget premium per il piano Plus. Per sbloccarli tutti, passa a Professional da Super Admin.');
      }
    }
    function togglePremiumLimit() { limitPremiumChecks(); }
    </script>
    <?php endif; ?>

  </main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
