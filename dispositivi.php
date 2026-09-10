<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Dispositivi';
$db  = getDB();
$tid = TENANT_ID;

// ── Azioni POST ──────────────────────────────────────────────
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // PAIRING: abbina dispositivo tramite codice
    if ($action === 'pair') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $nome = trim($_POST['nome'] ?? '');
        $club = trim($_POST['club'] ?? '');
        $hw   = $_POST['hw_type'] ?? 'altro';
        $n_tv = (int)($_POST['numero_tv'] ?? 1);

        if (!$code || !$nome || !$club) {
            $error = 'Compila tutti i campi obbligatori.';
        } else {
            // Cerca codice valido
            $stmt = $db->prepare("
                SELECT * FROM pairing_pending
                WHERE code = ? AND claimed = 0 AND expires > NOW()
                LIMIT 1
            ");
            $stmt->execute([$code]);
            $pending = $stmt->fetch();

            if (!$pending) {
                $error = 'Codice non valido o scaduto. Controlla il codice sulla TV.';
            } else {
                // Genera token univoco per il dispositivo
                $token = 'pb-' . bin2hex(random_bytes(12));

                // Crea dispositivo
                $stmt = $db->prepare("
                    INSERT INTO dispositivi
                    (tenant_id, nome, club, token, hw_type, numero_tv, stato, paired, creato_il)
                    VALUES (?, ?, ?, ?, ?, ?, 'offline', 1, NOW())
                ");
                $stmt->execute([$tid, $nome, $club, $token, $hw, $n_tv]);
                $disp_id = $db->lastInsertId();

                // Marca il codice come usato e salva il token
                $db->prepare("UPDATE pairing_pending SET claimed = 1, token = ? WHERE id = ?")
                   ->execute([$token, $pending['id']]);

                $success = "Dispositivo \"$nome\" abbinato con successo! Il player si aggiornerà entro pochi secondi.";
            }
        }
    }

    // ELIMINA dispositivo
    if ($action === 'delete' && isAdmin()) {
        $disp_id = (int)($_POST['disp_id'] ?? 0);
        $db->prepare("DELETE FROM dispositivi WHERE id = ? AND tenant_id = ?")
           ->execute([$disp_id, $tid]);
        $success = 'Dispositivo rimosso.';
    }

    // RELOAD dispositivo
    if ($action === 'reload') {
        $token = $_POST['token'] ?? '';
        $db->prepare("UPDATE dispositivi SET reload_richiesto = 1 WHERE token = ? AND tenant_id = ?")
           ->execute([$token, $tid]);
        $success = 'Ricarica inviata al dispositivo.';
    }

    // MODIFICA dispositivo
    if ($action === 'update') {
        $disp_id  = (int)($_POST['disp_id'] ?? 0);
        $nome     = trim($_POST['nome'] ?? '');
        $club     = trim($_POST['club'] ?? '');
        $hw       = $_POST['hw_type'] ?? 'altro';
        $n_tv     = (int)($_POST['numero_tv'] ?? 1);
        $note     = trim($_POST['note'] ?? '');
        $capture_device = trim($_POST['capture_device'] ?? '');
        if ($nome && $club) {
            $db->prepare("UPDATE dispositivi SET nome=?, club=?, hw_type=?, numero_tv=?, note=?, capture_device=? WHERE id=? AND tenant_id=?")
               ->execute([$nome, $club, $hw, $n_tv, $note, $capture_device, $disp_id, $tid]);
            $success = 'Dispositivo aggiornato.';
        }
    }

    // RINOMINA dispositivo
    if ($action === 'rename') {
        $disp_id = (int)($_POST['disp_id'] ?? 0);
        $nome    = trim($_POST['nome'] ?? '');
        if ($nome) {
            $db->prepare("UPDATE dispositivi SET nome = ? WHERE id = ? AND tenant_id = ?")
               ->execute([$nome, $disp_id, $tid]);
            $success = 'Dispositivo rinominato.';
        }
    }
}

// ── Query dispositivi ────────────────────────────────────────
$stmt = $db->prepare("
    SELECT d.*,
           CASE WHEN d.ultimo_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 'online' ELSE 'offline' END AS stato_live,
           TIMESTAMPDIFF(MINUTE, d.ultimo_ping, NOW()) AS minuti_fa
    FROM dispositivi d
    WHERE d.tenant_id = ?
    ORDER BY d.club ASC, d.nome ASC
");
$stmt->execute([$tid]);
$dispositivi = $stmt->fetchAll();

// Raggruppa per club
$by_club = [];
foreach ($dispositivi as $d) {
    $by_club[$d['club']][] = $d;
}

// Stats
$tot     = count($dispositivi);
$online  = count(array_filter($dispositivi, fn($d) => $d['stato_live'] === 'online'));
$offline = $tot - $online;

// Club disponibili per il form
$stmt = $db->prepare("SELECT nome FROM club_config WHERE tenant_id = ? ORDER BY nome");
$stmt->execute([$tid]);
$clubs_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
.disp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px}
.disp-card{background:var(--surface);border:1px solid var(--outline-var);border-radius:14px;overflow:hidden;transition:box-shadow .18s,border-color .18s}
.disp-card:hover{box-shadow:var(--shadow-md)}
.disp-card.online-card{border-color:rgba(34,197,94,.25)}
.disp-header{padding:16px 18px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--outline-var)}
.disp-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.disp-ico.pi{background:rgba(37,120,209,.1)}
.disp-ico.android{background:rgba(34,197,94,.1)}
.disp-ico.brightsign{background:rgba(113,38,209,.1)}
.disp-ico.pc{background:rgba(120,119,118,.1)}
.disp-ico.altro{background:var(--surface-mid)}
.disp-name{font-size:14px;font-weight:600;color:var(--on-surface);flex:1}
.disp-club{font-size:11px;color:var(--on-variant);margin-top:2px}
.disp-body{padding:14px 18px}
.disp-stat{display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:12px}
.disp-stat-label{color:var(--on-variant)}
.disp-stat-val{font-weight:500;color:var(--on-surface)}
.disp-actions{padding:12px 18px;border-top:1px solid var(--outline-var);display:flex;gap:8px}
.btn-sm{display:flex;align-items:center;gap:5px;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;transition:all .12s;border:1px solid var(--outline-var);background:var(--surface-low);color:var(--on-variant)}
.btn-sm:hover{background:var(--surface-mid);color:var(--on-surface)}
.btn-sm.danger:hover{background:var(--error-bg);color:var(--error);border-color:transparent}
.btn-sm svg{width:13px;height:13px}

/* Pairing modal */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;display:flex;align-items:center;justify-content:center;padding:20px}
.modal{background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:460px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal-title{font-family:'Hanken Grotesk',sans-serif;font-size:20px;font-weight:700;color:var(--on-surface);margin-bottom:6px}
.modal-sub{font-size:13px;color:var(--on-variant);margin-bottom:24px}

/* Code input */
.code-input-wrap{display:flex;gap:6px;justify-content:center;margin-bottom:24px}
.code-digit{width:36px;height:52px;border:2px solid var(--outline-var);border-radius:9px;background:var(--surface-low);font-size:20px;font-weight:700;text-align:center;text-transform:uppercase;color:var(--on-surface);outline:none;transition:all .15s;font-family:'Space Mono',monospace;caret-color:var(--blue)}
.code-digit:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,120,209,.12);background:var(--surface)}

/* Club section */
.club-section{margin-bottom:24px}
.club-section-title{font-family:'Hanken Grotesk',sans-serif;font-size:16px;font-weight:700;color:var(--on-surface);margin-bottom:12px;display:flex;align-items:center;gap:8px}

/* Alert */
.alert-success{background:var(--success-bg);border:1px solid rgba(34,197,94,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--success);margin-bottom:16px;display:flex;align-items:center;gap:8px}
.alert-error{background:var(--error-bg);border:1px solid rgba(192,24,12,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--error);margin-bottom:16px;display:flex;align-items:center;gap:8px}

/* HW selector */
.hw-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px}
.hw-opt{border:2px solid var(--outline-var);border-radius:10px;padding:10px 6px;text-align:center;cursor:pointer;transition:all .12s;background:var(--surface-low)}
.hw-opt:hover{border-color:var(--blue)}
.hw-opt.selected{border-color:var(--blue);background:var(--blue-bg)}
.hw-opt input{display:none}
.hw-opt svg{margin:0 auto 4px}
.hw-opt-label{font-size:11px;font-weight:500;color:var(--on-variant)}
.hw-opt.selected .hw-opt-label{color:var(--blue-text)}
</style>
</head>
<body>

<?php require_once __DIR__ . '/includes/topnav.php'; ?>

<div class="app">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">

    <!-- PAGE HEAD -->
    <div class="page-head">
      <div>
        <div class="page-title">Dispositivi</div>
        <div class="page-sub">
          <?php echo $online; ?> online · <?php echo $offline; ?> offline · <?php echo $tot; ?> totali
        </div>
      </div>
      <div class="head-actions">
        <button class="btn-primary" onclick="document.getElementById('pair-modal').style.display='flex'">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Abbina dispositivo
        </button>
      </div>
    </div>

    <!-- SEARCH BAR -->
    <div style="margin-bottom:16px">
      <div class="search-box" style="width:100%;max-width:400px;height:40px">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--on-variant)"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" id="device-search" placeholder="Cerca per nome, club o hardware..." style="font-size:13px">
      </div>
    </div>

    <!-- ALERTS -->
    <?php if ($success): ?>
    <div class="alert-success">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert-error">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- METRICHE -->
    <div class="g12" style="margin-bottom:24px">
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Totali</div>
        <div class="mval"><?php echo $tot; ?></div>
        <div class="msub"><?php echo count($by_club); ?> sedi</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Online</div>
        <div class="mval" style="color:var(--success)"><?php echo $online; ?></div>
        <div class="msub"><?php echo $tot > 0 ? round($online/$tot*100) : 0; ?>% uptime</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Offline</div>
        <div class="mval" style="color:<?php echo $offline > 0 ? 'var(--error)' : 'var(--on-variant)'; ?>">
          <?php echo $offline; ?>
        </div>
        <div class="msub"><?php echo $offline > 0 ? 'Richiede attenzione' : 'Tutto OK'; ?></div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">TV totali</div>
        <?php
        $stmt = $db->prepare("SELECT COALESCE(SUM(numero_tv),0) FROM dispositivi WHERE tenant_id = ?");
        $stmt->execute([$tid]);
        $tot_tv = (int)$stmt->fetchColumn();
        ?>
        <div class="mval"><?php echo $tot_tv; ?></div>
        <div class="msub">schermi gestiti</div>
      </div>
    </div>

    <!-- DISPOSITIVI PER CLUB -->
    <?php if (empty($dispositivi)): ?>
    <div class="card cp" style="text-align:center;padding:60px 24px">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="margin:0 auto 16px;opacity:.3"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
      <div style="font-family:'Hanken Grotesk',sans-serif;font-size:17px;font-weight:700;margin-bottom:8px">Nessun dispositivo</div>
      <div style="font-size:13px;color:var(--on-variant);margin-bottom:20px">Abbina il primo player per iniziare</div>
      <button class="btn-primary" style="margin:0 auto" onclick="document.getElementById('pair-modal').style.display='flex'">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Abbina dispositivo
      </button>
    </div>
    <?php else: ?>

    <?php foreach ($by_club as $club_name => $disps): ?>
    <div class="club-section">
      <div class="club-section-title">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--on-variant)" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        <?php echo htmlspecialchars($club_name); ?>
        <span class="badge <?php echo count(array_filter($disps, fn($d)=>$d['stato_live']==='online'))===count($disps) ? 'on' : (count(array_filter($disps, fn($d)=>$d['stato_live']==='online'))===0 ? 'off' : 'warn'); ?>" style="font-size:10px">
          <?php echo count(array_filter($disps, fn($d)=>$d['stato_live']==='online')); ?>/<?php echo count($disps); ?> online
        </span>
      </div>

      <div class="disp-grid">
        <?php foreach ($disps as $d):
          $is_online = $d['stato_live'] === 'online';
          $hw = $d['hw_type'] ?? 'altro';
          $hw_icons = [
            'pi'         => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>',
            'android'    => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="1.8"><path d="M5 16H4a2 2 0 01-2-2v-4a2 2 0 012-2h1"/><path d="M19 16h1a2 2 0 002-2v-4a2 2 0 00-2-2h-1"/><rect x="5" y="5" width="14" height="14" rx="2"/></svg>',
            'brightsign' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--violet)" stroke-width="1.8"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
            'pc'         => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--neutral)" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
            'altro'      => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--on-variant)" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
          ];
          $icon = $hw_icons[$hw] ?? $hw_icons['altro'];
        ?>
        <div class="disp-card <?php echo $is_online ? 'online-card' : ''; ?>" data-search="<?php echo strtolower(htmlspecialchars($d['nome'] . ' ' . $d['club'] . ' ' . ($d['hw_type'] ?? ''))); ?>">
          <div class="disp-header">
            <div class="disp-ico <?php echo $hw; ?>"><?php echo $icon; ?></div>
            <div style="flex:1;min-width:0">
              <div class="disp-name"><?php echo htmlspecialchars($d['nome']); ?></div>
              <div class="disp-club"><?php echo htmlspecialchars($d['club']); ?></div>
            </div>
            <div class="dot <?php echo $is_online ? 'on' : 'off'; ?>" style="<?php echo !$is_online ? 'animation:none' : ''; ?>"></div>
          </div>

          <div class="disp-body">
            <div class="disp-stat">
              <span class="disp-stat-label">Stato</span>
              <span class="badge <?php echo $is_online ? 'on' : 'off'; ?>"><?php echo $is_online ? 'Online' : 'Offline'; ?></span>
            </div>
            <div class="disp-stat">
              <span class="disp-stat-label">Hardware</span>
              <span class="disp-stat-val"><?php echo strtoupper($hw); ?></span>
            </div>
            <div class="disp-stat">
              <span class="disp-stat-label">TV collegate</span>
              <span class="disp-stat-val"><?php echo $d['numero_tv'] ?? '—'; ?></span>
            </div>
            <div class="disp-stat">
              <span class="disp-stat-label">Ultimo ping</span>
              <span class="disp-stat-val">
                <?php echo $d['ultimo_ping'] ? human_time_diff($d['ultimo_ping']) : 'Mai'; ?>
              </span>
            </div>
            <?php if ($d['token']): ?>
            <div class="disp-stat">
              <span class="disp-stat-label">Token</span>
              <span class="disp-stat-val" style="font-size:10px;font-family:monospace;color:var(--on-variant)">
                <?php echo substr($d['token'], 0, 16); ?>…
              </span>
            </div>
            <?php endif; ?>
          </div>

          <div class="disp-actions">
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="reload">
              <input type="hidden" name="token" value="<?php echo htmlspecialchars($d['token']); ?>">
              <button type="submit" class="btn-sm" title="Ricarica">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                Ricarica
              </button>
            </form>
            <button class="btn-sm" onclick="openEditModal(<?php echo $d['id']; ?>,'<?php echo addslashes($d['nome']); ?>','<?php echo addslashes($d['club']); ?>','<?php echo $d['hw_type'] ?? 'altro'; ?>',<?php echo (int)($d['numero_tv'] ?? 1); ?>,'<?php echo addslashes($d['note'] ?? ''); ?>','<?php echo addslashes($d['capture_device'] ?? ''); ?>')" title="Rinomina">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="8"/><line x1="12" y1="12" x2="12" y2="16"/></svg>
              Info
            </button>
            <a href="/player/display_v2.php?token=<?php echo urlencode($d['token']); ?>" target="_blank" class="btn-sm" title="Apri quello che sta mostrando davvero questo schermo, in una nuova scheda">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
              Apri player
            </a>
            <?php if (isAdmin()): ?>
            <form method="POST" style="margin-left:auto" onsubmit="return confirm('Rimuovere questo dispositivo?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="disp_id" value="<?php echo $d['id']; ?>">
              <button type="submit" class="btn-sm danger" title="Elimina">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

  </main>
</div>

<!-- PAIRING MODAL -->
<div id="pair-modal" class="modal-backdrop" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal">
    <div class="modal-title">Abbina dispositivo</div>
    <div class="modal-sub">Inserisci il codice di 8 caratteri che appare sulla TV</div>

    <?php if ($error): ?>
    <div class="alert-error" style="margin-bottom:16px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="pair-form">
      <input type="hidden" name="action" value="pair">
      <input type="hidden" name="code" id="code-hidden">

      <!-- Codice a 8 caratteri alfanumerici (stesso modello di Yodeck) -->
      <div class="code-input-wrap">
        <?php for ($i = 1; $i <= 8; $i++): ?>
        <input type="text" class="code-digit" maxlength="1" style="text-transform:uppercase" id="digit-<?php echo $i; ?>">
        <?php endfor; ?>
      </div>

      <!-- Hardware -->
      <div class="form-group">
        <label class="form-label">Tipo dispositivo</label>
        <div class="hw-grid">
          <label class="hw-opt selected">
            <input type="radio" name="hw_type" value="pi" checked>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="1" y1="9" x2="4" y2="9"/></svg>
            <div class="hw-opt-label">Raspberry Pi</div>
          </label>
          <label class="hw-opt">
            <input type="radio" name="hw_type" value="android">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="1.8"><path d="M5 16H4a2 2 0 01-2-2v-4a2 2 0 012-2h1"/><path d="M19 16h1a2 2 0 002-2v-4a2 2 0 00-2-2h-1"/><rect x="5" y="5" width="14" height="14" rx="2"/></svg>
            <div class="hw-opt-label">Android</div>
          </label>
          <label class="hw-opt">
            <input type="radio" name="hw_type" value="brightsign">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--violet)" stroke-width="1.8"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            <div class="hw-opt-label">BrightSign</div>
          </label>
          <label class="hw-opt">
            <input type="radio" name="hw_type" value="pc">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--on-variant)" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            <div class="hw-opt-label">PC / altro</div>
          </label>
        </div>
      </div>

      <!-- Nome e club -->
      <div class="form-group">
        <label class="form-label">Nome dispositivo *</label>
        <input type="text" name="nome" class="form-input" placeholder="Es. Pi Soave — Sala pesi" required>
      </div>

      <div class="form-group">
        <label class="form-label">Club / Sede *</label>
        <select name="club" class="form-input form-select" required>
          <option value="">Seleziona sede…</option>
          <?php foreach ($clubs_list as $c): ?>
          <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
          <?php endforeach; ?>
          <option value="__new__">+ Nuova sede…</option>
        </select>
      </div>

      <div class="form-group" id="new-club-wrap" style="display:none">
        <label class="form-label">Nome nuova sede</label>
        <input type="text" id="new-club-input" class="form-input" placeholder="Es. Milano Centro">
      </div>

      <div class="form-group">
        <label class="form-label">Numero TV collegate</label>
        <input type="number" name="numero_tv" class="form-input" value="1" min="1" max="100">
      </div>

      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('pair-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2" id="pair-submit" disabled>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
          Abbina dispositivo
        </button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div id="edit-modal" class="modal-backdrop" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal" style="max-width:480px">
    <div class="modal-title">Modifica dispositivo</div>
    <div class="modal-sub">Aggiorna le impostazioni del player</div>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="disp_id" id="edit-id">
      <div class="form-group">
        <label class="form-label">Nome dispositivo *</label>
        <input type="text" name="nome" id="edit-nome" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">Club / Sede *</label>
        <select name="club" id="edit-club" class="form-input form-select" required>
          <?php foreach ($clubs_list as $c): ?>
          <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Tipo hardware</label>
        <div class="hw-grid" id="edit-hw-grid">
          <label class="hw-opt" data-hw="pi">
            <input type="radio" name="hw_type" value="pi">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="1" y1="9" x2="4" y2="9"/></svg>
            <div class="hw-opt-label">Raspberry Pi</div>
          </label>
          <label class="hw-opt" data-hw="android">
            <input type="radio" name="hw_type" value="android">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="1.8"><path d="M5 16H4a2 2 0 01-2-2v-4a2 2 0 012-2h1"/><path d="M19 16h1a2 2 0 002-2v-4a2 2 0 00-2-2h-1"/><rect x="5" y="5" width="14" height="14" rx="2"/></svg>
            <div class="hw-opt-label">Android</div>
          </label>
          <label class="hw-opt" data-hw="brightsign">
            <input type="radio" name="hw_type" value="brightsign">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--violet)" stroke-width="1.8"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            <div class="hw-opt-label">BrightSign</div>
          </label>
          <label class="hw-opt" data-hw="pc">
            <input type="radio" name="hw_type" value="pc">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--on-variant)" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            <div class="hw-opt-label">PC / altro</div>
          </label>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Numero TV collegate</label>
        <input type="number" name="numero_tv" id="edit-tv" class="form-input" min="1" max="100">
      </div>
      <div class="form-group">
        <label class="form-label">Note</label>
        <input type="text" name="note" id="edit-note" class="form-input" placeholder="Opzionale">
      </div>
      <div class="form-group">
        <label class="form-label">Capture card (segnale TV)</label>
        <input type="text" name="capture_device" id="edit-capture" class="form-input" placeholder="Es. ugreen — vuoto = usa il primo dispositivo video trovato">
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('edit-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          Salva modifiche
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>

// ── Codice a 8 caratteri alfanumerici (stesso modello di Yodeck) ─
const digits = document.querySelectorAll('.code-digit');
const submitBtn = document.getElementById('pair-submit');
const codeHidden = document.getElementById('code-hidden');

digits.forEach((input, i) => {
  input.addEventListener('input', e => {
    // Accetta lettere e numeri, sempre maiuscolo
    input.value = input.value.replace(/[^A-Za-z0-9]/g, '').slice(-1).toUpperCase();
    if (input.value && i < digits.length - 1) {
      digits[i + 1].focus();
    }
    updateCode();
  });
  input.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !input.value && i > 0) {
      digits[i - 1].focus();
    }
    if (e.key === 'ArrowLeft' && i > 0) digits[i-1].focus();
    if (e.key === 'ArrowRight' && i < digits.length-1) digits[i+1].focus();
  });
  // Paste support
  input.addEventListener('paste', e => {
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text').replace(/[^A-Za-z0-9]/g,'').toUpperCase();
    text.split('').slice(0,8).forEach((ch, j) => {
      if (digits[i+j]) digits[i+j].value = ch;
    });
    updateCode();
    const next = Math.min(i + text.length, digits.length - 1);
    digits[next].focus();
  });
});

function updateCode() {
  const code = Array.from(digits).map(d => d.value).join('');
  codeHidden.value = code;
  submitBtn.disabled = code.length < 8;
}

// ── HW selector ──────────────────────────────────────────────
document.querySelectorAll('.hw-opt').forEach(opt => {
  opt.addEventListener('click', () => {
    document.querySelectorAll('.hw-opt').forEach(o => o.classList.remove('selected'));
    opt.classList.add('selected');
  });
});

// ── Nuova sede ───────────────────────────────────────────────
document.querySelector('select[name="club"]')?.addEventListener('change', function() {
  const wrap = document.getElementById('new-club-wrap');
  const input = document.getElementById('new-club-input');
  if (this.value === '__new__') {
    wrap.style.display = 'block';
    input.required = true;
    // Al submit usa il valore del campo testo
    document.getElementById('pair-form').addEventListener('submit', function(e) {
      if (document.querySelector('select[name="club"]').value === '__new__') {
        document.querySelector('select[name="club"]').value = input.value;
      }
    }, { once: true });
  } else {
    wrap.style.display = 'none';
    input.required = false;
  }
});

// ── Edit modal ───────────────────────────────────────────────
function openEditModal(id, nome, club, hw, n_tv, note, captureDevice) {
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-nome').value = nome;
  document.getElementById('edit-tv').value = n_tv;
  document.getElementById('edit-note').value = note || '';
  document.getElementById('edit-capture').value = captureDevice || '';

  const clubSel = document.getElementById('edit-club');
  for (let opt of clubSel.options) {
    opt.selected = opt.value === club;
  }

  document.querySelectorAll('#edit-hw-grid .hw-opt').forEach(opt => {
    const sel = opt.dataset.hw === hw;
    opt.classList.toggle('selected', sel);
    opt.querySelector('input').checked = sel;
  });

  document.getElementById('edit-modal').style.display = 'flex';
}

document.querySelectorAll('#edit-hw-grid .hw-opt').forEach(opt => {
  opt.addEventListener('click', () => {
    document.querySelectorAll('#edit-hw-grid .hw-opt').forEach(o => o.classList.remove('selected'));
    opt.classList.add('selected');
  });
});

// ── Ricerca dispositivi ──────────────────────────────────────
document.getElementById('device-search')?.addEventListener('input', function() {
  const q = this.value.toLowerCase().trim();

  document.querySelectorAll('.disp-card').forEach(card => {
    const match = !q || card.dataset.search.includes(q);
    card.style.display = match ? '' : 'none';
  });

  // Nascondi sezioni club se tutti i dispositivi sono nascosti
  document.querySelectorAll('.club-section').forEach(section => {
    const visible = [...section.querySelectorAll('.disp-card')].some(c => c.style.display !== 'none');
    section.style.display = visible ? '' : 'none';
  });
});

// ── Apri modal se c'è errore di pairing ──────────────────────
<?php if ($error && strpos($error, 'Codice') !== false): ?>
document.getElementById('pair-modal').style.display = 'flex';
<?php endif; ?>

</script>
</body>
</html>
<?php
function human_time_diff(string $datetime): string {
  $diff = time() - strtotime($datetime);
  if ($diff < 60)    return $diff . 's fa';
  if ($diff < 3600)  return round($diff/60) . ' min fa';
  if ($diff < 86400) return round($diff/3600) . 'h fa';
  return round($diff/86400) . 'g fa';
}
?>
