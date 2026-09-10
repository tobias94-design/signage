<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (UTENTE_RUOLO !== 'superadmin') {
    http_response_code(403);
    die('Accesso riservato al Super Admin.');
}

$page_title = 'Super Admin';
$db = getDB();

// ── Azioni POST ──────────────────────────────────────────────
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // NUOVO TENANT
    if ($action === 'create_tenant') {
        $nome  = trim($_POST['nome'] ?? '');
        $slug  = trim(strtolower(preg_replace('/[^a-z0-9\-]/i', '-', $_POST['slug'] ?? '')));
        $email = trim($_POST['email'] ?? '');
        $piano = $_POST['piano'] ?? 'trial';
        $trial_giorni = (int)($_POST['trial_giorni'] ?? 14);

        if (!$nome || !$slug || !$email) {
            $error = 'Nome, slug ed email sono obbligatori.';
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM tenants WHERE slug=? OR email=?");
            $stmt->execute([$slug, $email]);
            if ((int)$stmt->fetchColumn() > 0) {
                $error = 'Slug o email già in uso da un altro tenant.';
            } else {
                $trial_scade = $piano === 'trial' ? date('Y-m-d H:i:s', strtotime("+$trial_giorni days")) : null;
                $stmt = $db->prepare("INSERT INTO tenants (nome, slug, email, piano, trial_scade_il, attivo, creato_il) VALUES (?,?,?,?,?,1,NOW())");
                $stmt->execute([$nome, $slug, $email, $piano, $trial_scade]);
                $success = "Tenant \"$nome\" creato.";
            }
        }
    }

    // MODIFICA TENANT
    if ($action === 'update_tenant') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $piano = $_POST['piano'] ?? 'trial';
        $attivo = isset($_POST['attivo']) ? 1 : 0;
        $trial_scade = $_POST['trial_scade_il'] ?? '';
        $trial_scade = $trial_scade ? $trial_scade . ' 23:59:59' : null;

        if ($nome && $id) {
            $stmt = $db->prepare("UPDATE tenants SET nome=?, piano=?, attivo=?, trial_scade_il=? WHERE id=?");
            $stmt->execute([$nome, $piano, $attivo, $trial_scade, $id]);
            $success = 'Tenant aggiornato.';
        }
    }

    // ELIMINA TENANT (solo se completamente vuoto, per sicurezza)
    if ($action === 'delete_tenant') {
        $id = (int)($_POST['id'] ?? 0);
        $checks = [
            'utenti'      => "SELECT COUNT(*) FROM utenti WHERE tenant_id=?",
            'dispositivi' => "SELECT COUNT(*) FROM dispositivi WHERE tenant_id=?",
            'contenuti'   => "SELECT COUNT(*) FROM contenuti WHERE tenant_id=?",
        ];
        $blocchi = [];
        foreach ($checks as $label => $sql) {
            $stmt = $db->prepare($sql);
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) $blocchi[] = $label;
        }
        if ($blocchi) {
            $error = 'Il tenant ha ancora: ' . implode(', ', $blocchi) . '. Rimuovi tutto prima di eliminarlo.';
        } else {
            $db->prepare("DELETE FROM tenants WHERE id=?")->execute([$id]);
            $success = 'Tenant eliminato.';
        }
    }

    // ENTRA COME (impersonificazione)
    if ($action === 'impersonate') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id=?");
        $stmt->execute([$id]);
        $target = $stmt->fetch();

        if ($target) {
            // Salva la sessione originale del super admin, solo se non e' gia' salvata
            // (evita di sovrascriverla se si passa da un tenant impersonato a un altro)
            if (empty($_SESSION['sa_original_tenant_id'])) {
                $_SESSION['sa_original_tenant_id']   = $_SESSION['tenant_id'];
                $_SESSION['sa_original_tenant_nome'] = $_SESSION['tenant_nome'] ?? '';
                $_SESSION['sa_original_tenant_slug'] = $_SESSION['tenant_slug'] ?? '';
                $_SESSION['sa_original_tenant_piano']= $_SESSION['tenant_piano'] ?? '';
            }
            $_SESSION['tenant_id']    = $target['id'];
            $_SESSION['tenant_nome']  = $target['nome'];
            $_SESSION['tenant_slug']  = $target['slug'];
            $_SESSION['tenant_piano'] = $target['piano'];

            header('Location: /dashboard.php');
            exit;
        }
    }
}

// ── Torna al proprio account (esce dall'impersonificazione) ───
if (isset($_GET['exit_impersonate']) && !empty($_SESSION['sa_original_tenant_id'])) {
    $_SESSION['tenant_id']    = $_SESSION['sa_original_tenant_id'];
    $_SESSION['tenant_nome']  = $_SESSION['sa_original_tenant_nome'];
    $_SESSION['tenant_slug']  = $_SESSION['sa_original_tenant_slug'];
    $_SESSION['tenant_piano'] = $_SESSION['sa_original_tenant_piano'];
    unset($_SESSION['sa_original_tenant_id'], $_SESSION['sa_original_tenant_nome'], $_SESSION['sa_original_tenant_slug'], $_SESSION['sa_original_tenant_piano']);
    header('Location: /superadmin.php');
    exit;
}

$sto_impersonando = !empty($_SESSION['sa_original_tenant_id']);

// ── Query tenants con conteggi ──────────────────────────────
$stmt = $db->query("
    SELECT t.*,
           (SELECT COUNT(*) FROM utenti u WHERE u.tenant_id = t.id) AS n_utenti,
           (SELECT COUNT(*) FROM dispositivi d WHERE d.tenant_id = t.id) AS n_dispositivi,
           (SELECT COUNT(*) FROM contenuti c WHERE c.tenant_id = t.id) AS n_contenuti,
           DATEDIFF(t.trial_scade_il, CURDATE()) AS giorni_trial
    FROM tenants t
    ORDER BY t.creato_il DESC
");
$tenants_list = $stmt->fetchAll();

// ── Metriche ─────────────────────────────────────────────────
$tot_tenants = count($tenants_list);
$tot_attivi = count(array_filter($tenants_list, fn($t) => $t['attivo']));
$trial_in_scadenza = count(array_filter($tenants_list, fn($t) => $t['piano']==='trial' && $t['giorni_trial']!==null && $t['giorni_trial']<=3 && $t['giorni_trial']>=0));
$per_piano = ['trial'=>0,'starter'=>0,'pro'=>0,'enterprise'=>0];
foreach ($tenants_list as $t) { if (isset($per_piano[$t['piano']])) $per_piano[$t['piano']]++; }

function piano_badge(string $piano): string {
    $map = ['trial'=>['','Trial'],'starter'=>['blue','Starter'],'pro'=>['vio','Pro'],'enterprise'=>['on','Enterprise']];
    [$cls,$label] = $map[$piano] ?? ['', $piano];
    return "<span class=\"badge $cls\">$label</span>";
}
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
.sa-table{display:flex;flex-direction:column;gap:2px}
.sa-row{background:var(--surface);border:1px solid var(--outline-var);border-radius:8px;display:flex;align-items:center;gap:14px;padding:12px 16px;transition:all .15s}
.sa-row:hover{border-color:var(--blue);box-shadow:var(--shadow-sm)}
.sa-name{flex:1;min-width:0}
.sa-name .n{font-size:13px;font-weight:600;color:var(--on-surface)}
.sa-name .s{font-size:11.5px;color:var(--on-variant)}
.sa-stat{width:70px;text-align:center;font-size:12px;color:var(--on-variant)}
.sa-stat b{display:block;font-size:15px;color:var(--on-surface);font-weight:700}
.sa-actions{display:flex;gap:6px;flex-shrink:0}
.impersonate-banner{background:var(--warn-bg);color:var(--warn);border:1px solid rgba(154,105,0,.25);border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px}
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
        <div class="page-title">Super Admin</div>
        <div class="page-sub"><?php echo $tot_tenants; ?> aziende clienti · <?php echo $tot_attivi; ?> attive</div>
      </div>
      <div class="head-actions">
        <button class="btn-primary" onclick="document.getElementById('tenant-modal').style.display='flex'">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nuovo tenant
        </button>
      </div>
    </div>

    <?php if ($sto_impersonando): ?>
    <div class="impersonate-banner">
      <span>Stai visualizzando come <strong><?php echo htmlspecialchars($_SESSION['tenant_nome']); ?></strong></span>
      <a href="?exit_impersonate=1" class="btn-sm">Torna al tuo account</a>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert-success" style="background:var(--success-bg);border:1px solid rgba(34,197,94,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--success);margin-bottom:20px;display:flex;align-items:center;gap:8px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="background:var(--error-bg);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--error);margin-bottom:20px;display:flex;align-items:center;gap:8px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- METRICHE -->
    <div class="g12" style="margin-bottom:24px">
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Tenants totali</div>
        <div class="mval"><?php echo $tot_tenants; ?></div>
        <div class="msub"><?php echo $tot_attivi; ?> attivi</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Trial in scadenza</div>
        <div class="mval" style="color:<?php echo $trial_in_scadenza>0?'var(--warn)':'inherit'; ?>"><?php echo $trial_in_scadenza; ?></div>
        <div class="msub">entro 3 giorni</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Pro + Enterprise</div>
        <div class="mval"><?php echo $per_piano['pro'] + $per_piano['enterprise']; ?></div>
        <div class="msub">clienti paganti principali</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Distribuzione piani</div>
        <div style="font-size:11.5px;color:var(--on-variant);margin-top:6px;line-height:1.7">
          Trial <?php echo $per_piano['trial']; ?> · Starter <?php echo $per_piano['starter']; ?> · Pro <?php echo $per_piano['pro']; ?> · Enterprise <?php echo $per_piano['enterprise']; ?>
        </div>
      </div>
    </div>

    <!-- LISTA TENANTS -->
    <?php if (empty($tenants_list)): ?>
    <div class="card cp" style="text-align:center;padding:60px">
      <div style="font-size:17px;font-weight:700;margin-bottom:8px">Nessun tenant</div>
      <div style="font-size:13px;color:var(--on-variant)">Crea il primo cliente per iniziare</div>
    </div>
    <?php else: ?>
    <div class="sa-table">
      <?php foreach ($tenants_list as $t): ?>
      <div class="sa-row">
        <div class="sa-name">
          <div class="n"><?php echo htmlspecialchars($t['nome']); ?></div>
          <div class="s"><?php echo htmlspecialchars($t['slug']); ?> · <?php echo htmlspecialchars($t['email']); ?></div>
        </div>
        <div><?php echo piano_badge($t['piano']); ?></div>
        <?php if ($t['piano']==='trial' && $t['giorni_trial']!==null): ?>
        <div style="font-size:11px;color:<?php echo $t['giorni_trial']<=3?'var(--warn)':'var(--on-variant)'; ?>">
          <?php echo $t['giorni_trial']>=0 ? "scade tra {$t['giorni_trial']}g" : 'scaduto'; ?>
        </div>
        <?php endif; ?>
        <div class="sa-stat"><b><?php echo $t['n_utenti']; ?></b>utenti</div>
        <div class="sa-stat"><b><?php echo $t['n_dispositivi']; ?></b>schermi</div>
        <div class="sa-stat"><b><?php echo $t['n_contenuti']; ?></b>contenuti</div>
        <div>
          <?php if (!$t['attivo']): ?><span class="badge err">Sospeso</span>
          <?php else: ?><span class="badge on">Attivo</span><?php endif; ?>
        </div>
        <div class="sa-actions">
          <form method="POST">
            <input type="hidden" name="action" value="impersonate">
            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
            <button type="submit" class="btn-sm">Entra come</button>
          </form>
          <button class="btn-sm" onclick='editTenant(<?php echo json_encode($t); ?>)'>Modifica</button>
          <form method="POST" onsubmit="return confirm('Eliminare questo tenant?')">
            <input type="hidden" name="action" value="delete_tenant">
            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
            <button type="submit" class="btn-sm danger">Elimina</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </main>
</div>

<!-- MODAL NUOVO TENANT -->
<div id="tenant-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:440px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:6px">Nuovo tenant</div>
    <form method="POST" style="margin-top:16px">
      <input type="hidden" name="action" value="create_tenant">
      <div class="form-group">
        <label class="form-label">Nome azienda</label>
        <input type="text" name="nome" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">Slug (identificativo URL)</label>
        <input type="text" name="slug" class="form-input" placeholder="es. gymnasium-club" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-input" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group">
          <label class="form-label">Piano</label>
          <select name="piano" class="form-input">
            <option value="trial">Trial</option>
            <option value="starter">Starter</option>
            <option value="pro">Pro</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Durata trial (giorni)</label>
          <input type="number" name="trial_giorni" class="form-input" value="14">
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('tenant-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Crea tenant</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL MODIFICA TENANT -->
<div id="edit-tenant-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:440px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:6px">Modifica tenant</div>
    <form method="POST" style="margin-top:16px">
      <input type="hidden" name="action" value="update_tenant">
      <input type="hidden" name="id" id="et-id">
      <div class="form-group">
        <label class="form-label">Nome azienda</label>
        <input type="text" name="nome" id="et-nome" class="form-input" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group">
          <label class="form-label">Piano</label>
          <select name="piano" id="et-piano" class="form-input">
            <option value="trial">Trial</option>
            <option value="starter">Starter</option>
            <option value="pro">Pro</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Trial scade il</label>
          <input type="date" name="trial_scade_il" id="et-trial" class="form-input">
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;margin-bottom:8px">
        <input type="checkbox" name="attivo" id="et-attivo" style="width:14px;height:14px;accent-color:var(--blue)"> Tenant attivo
      </label>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('edit-tenant-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Salva</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
function editTenant(t) {
  document.getElementById('et-id').value = t.id;
  document.getElementById('et-nome').value = t.nome || '';
  document.getElementById('et-piano').value = t.piano || 'trial';
  document.getElementById('et-attivo').checked = !!(+t.attivo);
  document.getElementById('et-trial').value = t.trial_scade_il ? t.trial_scade_il.slice(0,10) : '';
  document.getElementById('edit-tenant-modal').style.display = 'flex';
}
</script>
