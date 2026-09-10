<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireRole('admin'); // solo admin e superadmin possono gestire utenti

$page_title = 'Utenti';
$db  = getDB();
$tid = TENANT_ID;

$gerarchia = ['operatore' => 1, 'admin' => 2, 'superadmin' => 3];
$mio_livello = $gerarchia[UTENTE_RUOLO] ?? 1;

// ── Azioni POST ──────────────────────────────────────────────
$success = $error = '';
$nuova_password_generata = null;

function genera_password(): string {
    // Password leggibile ma abbastanza forte: 3 parole + 2 numeri
    $parole = ['sole','luna','monte','fiume','stella','ferro','vento','pietra','onda','ramo'];
    return ucfirst($parole[array_rand($parole)]) . rand(10,99) . ucfirst($parole[array_rand($parole)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // NUOVO UTENTE
    if ($action === 'create_user') {
        $nome = trim($_POST['nome'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $ruolo = $_POST['ruolo'] ?? 'operatore';

        if ($nome === '' || $username === '') {
            $error = 'Nome e username sono obbligatori.';
        } elseif (($gerarchia[$ruolo] ?? 1) > $mio_livello) {
            $error = 'Non puoi assegnare un ruolo superiore al tuo.';
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM utenti WHERE tenant_id=? AND username=?");
            $stmt->execute([$tid, $username]);
            if ((int)$stmt->fetchColumn() > 0) {
                $error = 'Username già in uso.';
            } else {
                $pw_generata = genera_password();
                $hash = password_hash($pw_generata, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO utenti (tenant_id, nome, username, password_hash, ruolo, attivo, temp_password, creato_il) VALUES (?,?,?,?,?,1,1,NOW())");
                $stmt->execute([$tid, $nome, $username, $hash, $ruolo]);
                $success = 'Utente creato.';
                $nuova_password_generata = $pw_generata;
            }
        }
    }

    // MODIFICA UTENTE
    if ($action === 'update_user') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $ruolo = $_POST['ruolo'] ?? 'operatore';
        $attivo = isset($_POST['attivo']) ? 1 : 0;

        $stmt = $db->prepare("SELECT ruolo FROM utenti WHERE id=? AND tenant_id=?");
        $stmt->execute([$id, $tid]);
        $target = $stmt->fetch();

        if (!$target) {
            $error = 'Utente non trovato.';
        } elseif (($gerarchia[$target['ruolo']] ?? 1) > $mio_livello) {
            $error = 'Non puoi modificare un utente con ruolo superiore al tuo.';
        } elseif (($gerarchia[$ruolo] ?? 1) > $mio_livello) {
            $error = 'Non puoi assegnare un ruolo superiore al tuo.';
        } elseif ($id === UTENTE_ID && !$attivo) {
            $error = 'Non puoi disattivare te stesso.';
        } elseif ($id === UTENTE_ID && $ruolo !== UTENTE_RUOLO) {
            $error = 'Non puoi cambiare il tuo stesso ruolo.';
        } else {
            $stmt = $db->prepare("UPDATE utenti SET nome=?, ruolo=?, attivo=? WHERE id=? AND tenant_id=?");
            $stmt->execute([$nome, $ruolo, $attivo, $id, $tid]);
            $success = 'Utente aggiornato.';
        }
    }

    // RESET PASSWORD
    if ($action === 'reset_password') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT ruolo FROM utenti WHERE id=? AND tenant_id=?");
        $stmt->execute([$id, $tid]);
        $target = $stmt->fetch();

        if (!$target) {
            $error = 'Utente non trovato.';
        } elseif (($gerarchia[$target['ruolo']] ?? 1) > $mio_livello) {
            $error = 'Non puoi resettare la password di un utente con ruolo superiore al tuo.';
        } else {
            $pw_generata = genera_password();
            $hash = password_hash($pw_generata, PASSWORD_DEFAULT);
            $db->prepare("UPDATE utenti SET password_hash=?, temp_password=1 WHERE id=? AND tenant_id=?")
               ->execute([$hash, $id, $tid]);
            $success = 'Password resettata.';
            $nuova_password_generata = $pw_generata;
        }
    }

    // ELIMINA UTENTE
    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === UTENTE_ID) {
            $error = 'Non puoi eliminare te stesso.';
        } else {
            $stmt = $db->prepare("SELECT ruolo FROM utenti WHERE id=? AND tenant_id=?");
            $stmt->execute([$id, $tid]);
            $target = $stmt->fetch();
            if (!$target) {
                $error = 'Utente non trovato.';
            } elseif (($gerarchia[$target['ruolo']] ?? 1) > $mio_livello) {
                $error = 'Non puoi eliminare un utente con ruolo superiore al tuo.';
            } else {
                $db->prepare("DELETE FROM utenti WHERE id=? AND tenant_id=?")->execute([$id, $tid]);
                $success = 'Utente eliminato.';
            }
        }
    }
}

// ── Query utenti ─────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM utenti WHERE tenant_id = ? ORDER BY FIELD(ruolo,'superadmin','admin','operatore'), nome");
$stmt->execute([$tid]);
$utenti = $stmt->fetchAll();

$tot_utenti = count($utenti);
$tot_attivi = count(array_filter($utenti, fn($u) => $u['attivo']));
$tot_admin = count(array_filter($utenti, fn($u) => in_array($u['ruolo'], ['admin','superadmin'])));

function ruolo_label(string $r): string {
    return ['superadmin'=>'Super Admin','admin'=>'Admin','operatore'=>'Operatore'][$r] ?? $r;
}
function ruolo_badge_class(string $r): string {
    return ['superadmin'=>'vio','admin'=>'blue','operatore'=>''][$r] ?? '';
}
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
.ut-table{display:flex;flex-direction:column;gap:2px}
.ut-row{background:var(--surface);border:1px solid var(--outline-var);border-radius:8px;display:flex;align-items:center;gap:14px;padding:12px 16px;transition:all .15s}
.ut-row:hover{border-color:var(--blue);box-shadow:var(--shadow-sm)}
.ut-avatar{width:36px;height:36px;border-radius:50%;background:var(--blue-bg);color:var(--blue-text);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
.ut-name{flex:1;min-width:0}
.ut-name .n{font-size:13px;font-weight:600;color:var(--on-surface)}
.ut-name .u{font-size:11.5px;color:var(--on-variant)}
.ut-role{width:110px}
.ut-status{width:90px}
.ut-lastlogin{width:130px;font-size:11.5px;color:var(--on-variant);text-align:right}
.ut-actions{display:flex;gap:6px;flex-shrink:0}
.pw-reveal{background:var(--surface-low);border:1px dashed var(--blue);border-radius:8px;padding:14px;margin-bottom:16px;text-align:center}
.pw-reveal .val{font-family:'Space Mono',monospace;font-size:18px;font-weight:700;color:var(--on-surface);letter-spacing:.02em;margin:6px 0}
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
        <div class="page-title">Utenti</div>
        <div class="page-sub"><?php echo $tot_utenti; ?> totali · <?php echo $tot_attivi; ?> attivi</div>
      </div>
      <div class="head-actions">
        <button class="btn-primary" onclick="openCreateModal()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nuovo utente
        </button>
      </div>
    </div>

    <?php if ($success): ?>
    <div class="alert-success" style="background:var(--success-bg);border:1px solid rgba(34,197,94,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--success);margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="background:var(--error-bg);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--error);margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <?php if ($nuova_password_generata): ?>
    <div class="pw-reveal">
      <div style="font-size:12px;color:var(--on-variant)">Password generata — copiala ora, non potrai più rivederla:</div>
      <div class="val"><?php echo htmlspecialchars($nuova_password_generata); ?></div>
      <div style="font-size:11px;color:var(--on-variant)">L'utente dovrà cambiarla al primo accesso.</div>
    </div>
    <?php endif; ?>

    <!-- METRICHE -->
    <div class="g12" style="margin-bottom:24px">
      <div class="card cp" style="grid-column:span 4">
        <div class="mlabel">Utenti totali</div>
        <div class="mval"><?php echo $tot_utenti; ?></div>
        <div class="msub"><?php echo $tot_attivi; ?> attivi</div>
      </div>
      <div class="card cp" style="grid-column:span 4">
        <div class="mlabel">Amministratori</div>
        <div class="mval"><?php echo $tot_admin; ?></div>
        <div class="msub">admin + super admin</div>
      </div>
      <div class="card cp" style="grid-column:span 4">
        <div class="mlabel">Il tuo ruolo</div>
        <div class="mval" style="font-size:20px"><?php echo ruolo_label(UTENTE_RUOLO); ?></div>
        <div class="msub"><?php echo htmlspecialchars(UTENTE_NOME); ?></div>
      </div>
    </div>

    <!-- LISTA UTENTI -->
    <?php if (empty($utenti)): ?>
    <div class="card cp" style="text-align:center;padding:60px">
      <div style="font-size:17px;font-weight:700;margin-bottom:8px">Nessun utente</div>
      <div style="font-size:13px;color:var(--on-variant)">Aggiungi il primo membro del team</div>
    </div>
    <?php else: ?>
    <div class="ut-table">
      <?php foreach ($utenti as $u):
        $puo_gestire = ($gerarchia[$u['ruolo']] ?? 1) <= $mio_livello;
        $iniziali = strtoupper(substr($u['nome'], 0, 1) . (strpos($u['nome'],' ') ? substr($u['nome'], strpos($u['nome'],' ')+1, 1) : ''));
      ?>
      <div class="ut-row">
        <div class="ut-avatar"><?php echo htmlspecialchars($iniziali); ?></div>
        <div class="ut-name">
          <div class="n"><?php echo htmlspecialchars($u['nome']); ?><?php echo $u['id']===UTENTE_ID ? ' <span style="color:var(--on-variant);font-weight:400">(tu)</span>' : ''; ?></div>
          <div class="u">@<?php echo htmlspecialchars($u['username']); ?></div>
        </div>
        <div class="ut-role"><span class="badge <?php echo ruolo_badge_class($u['ruolo']); ?>"><?php echo ruolo_label($u['ruolo']); ?></span></div>
        <div class="ut-status">
          <?php if (!$u['attivo']): ?><span class="badge err">Disattivo</span>
          <?php elseif ($u['temp_password']): ?><span class="badge warn">Da attivare</span>
          <?php else: ?><span class="badge on">Attivo</span><?php endif; ?>
        </div>
        <div class="ut-lastlogin"><?php echo $u['ultimo_accesso'] ? date('d/m/Y H:i', strtotime($u['ultimo_accesso'])) : 'Mai'; ?></div>
        <div class="ut-actions">
          <?php if ($puo_gestire): ?>
          <button class="btn-sm" onclick='editUser(<?php echo json_encode($u); ?>)'>Modifica</button>
          <form method="POST" onsubmit="return confirm('Generare una nuova password per questo utente?')">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
            <button type="submit" class="btn-sm">Reset password</button>
          </form>
          <?php if ($u['id'] !== UTENTE_ID): ?>
          <form method="POST" onsubmit="return confirm('Eliminare questo utente?')">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
            <button type="submit" class="btn-sm danger">Elimina</button>
          </form>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </main>
</div>

<!-- MODAL NUOVO UTENTE -->
<div id="create-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:420px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:6px">Nuovo utente</div>
    <div style="font-size:11.5px;color:var(--on-variant);margin-bottom:16px">Genero io una password provvisoria — te la mostro una sola volta dopo il salvataggio.</div>
    <form method="POST">
      <input type="hidden" name="action" value="create_user">
      <div class="form-group">
        <label class="form-label">Nome e cognome</label>
        <input type="text" name="nome" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">Ruolo</label>
        <select name="ruolo" class="form-input">
          <option value="operatore">Operatore</option>
          <option value="admin">Admin</option>
          <?php if (UTENTE_RUOLO === 'superadmin'): ?>
          <option value="superadmin">Super Admin</option>
          <?php endif; ?>
        </select>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('create-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Crea utente</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL MODIFICA UTENTE -->
<div id="edit-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:420px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:6px">Modifica utente</div>
    <form method="POST" style="margin-top:16px">
      <input type="hidden" name="action" value="update_user">
      <input type="hidden" name="id" id="edit-id">
      <div class="form-group">
        <label class="form-label">Nome e cognome</label>
        <input type="text" name="nome" id="edit-nome" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" id="edit-username" class="form-input" disabled style="opacity:.6">
        <div style="font-size:10.5px;color:var(--on-variant);margin-top:3px">L'username non è modificabile.</div>
      </div>
      <div class="form-group">
        <label class="form-label">Ruolo</label>
        <select name="ruolo" id="edit-ruolo" class="form-input">
          <option value="operatore">Operatore</option>
          <option value="admin">Admin</option>
          <?php if (UTENTE_RUOLO === 'superadmin'): ?>
          <option value="superadmin">Super Admin</option>
          <?php endif; ?>
        </select>
      </div>
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;margin-bottom:8px">
        <input type="checkbox" name="attivo" id="edit-attivo" style="width:14px;height:14px;accent-color:var(--blue)"> Utente attivo
      </label>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('edit-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Salva</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
function openCreateModal() {
  document.getElementById('create-modal').style.display = 'flex';
}

function editUser(u) {
  document.getElementById('edit-id').value = u.id;
  document.getElementById('edit-nome').value = u.nome || '';
  document.getElementById('edit-username').value = u.username || '';
  document.getElementById('edit-ruolo').value = u.ruolo || 'operatore';
  document.getElementById('edit-attivo').checked = !!(+u.attivo);
  document.getElementById('edit-modal').style.display = 'flex';
}
</script>
