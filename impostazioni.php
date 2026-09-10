<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Impostazioni';
$db  = getDB();
$tid = TENANT_ID;

// ── Helper: leggi tutte le impostazioni del tenant in un array ─
function get_impostazioni(PDO $db, int $tid): array {
    $stmt = $db->prepare("SELECT chiave, valore FROM impostazioni WHERE tenant_id = ?");
    $stmt->execute([$tid]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) $out[$row['chiave']] = $row['valore'];
    return $out;
}
function save_impostazione(PDO $db, int $tid, string $chiave, string $valore): void {
    $db->prepare("INSERT INTO impostazioni (tenant_id, chiave, valore) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)")
       ->execute([$tid, $chiave, $valore]);
}

// ── Azioni POST ──────────────────────────────────────────────
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // DATI AZIENDA
    if ($action === 'save_azienda') {
        save_impostazione($db, $tid, 'azienda_ragione_sociale', trim($_POST['ragione_sociale'] ?? ''));
        save_impostazione($db, $tid, 'azienda_indirizzo', trim($_POST['indirizzo'] ?? ''));
        save_impostazione($db, $tid, 'azienda_piva', trim($_POST['piva'] ?? ''));
        save_impostazione($db, $tid, 'azienda_email', trim($_POST['email'] ?? ''));
        save_impostazione($db, $tid, 'azienda_telefono', trim($_POST['telefono'] ?? ''));
        $success = 'Dati azienda salvati.';
    }

    // NOTIFICHE
    if ($action === 'save_notifiche') {
        save_impostazione($db, $tid, 'notifiche_email', trim($_POST['notifiche_email'] ?? ''));
        save_impostazione($db, $tid, 'notifiche_contratti_scadenza', isset($_POST['notifica_contratti']) ? '1' : '0');
        save_impostazione($db, $tid, 'notifiche_dispositivi_offline', isset($_POST['notifica_offline']) ? '1' : '0');
        $success = 'Preferenze notifiche salvate.';
    }

    // FUSO ORARIO
    if ($action === 'save_regionali') {
        save_impostazione($db, $tid, 'fuso_orario', $_POST['fuso_orario'] ?? 'Europe/Rome');
        $success = 'Impostazioni regionali salvate.';
    }

    // CAMBIO PASSWORD PERSONALE
    if ($action === 'change_password') {
        $attuale = $_POST['password_attuale'] ?? '';
        $nuova   = $_POST['password_nuova'] ?? '';
        $conferma = $_POST['password_conferma'] ?? '';

        $stmt = $db->prepare("SELECT password_hash FROM utenti WHERE id = ? AND tenant_id = ?");
        $stmt->execute([UTENTE_ID, $tid]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($attuale, $row['password_hash'])) {
            $error = 'Password attuale non corretta.';
        } elseif (strlen($nuova) < 8) {
            $error = 'La nuova password deve essere di almeno 8 caratteri.';
        } elseif ($nuova !== $conferma) {
            $error = 'Le due password non coincidono.';
        } else {
            $hash = password_hash($nuova, PASSWORD_DEFAULT);
            $db->prepare("UPDATE utenti SET password_hash=?, temp_password=0 WHERE id=? AND tenant_id=?")
               ->execute([$hash, UTENTE_ID, $tid]);
            $success = 'Password aggiornata.';
        }
    }
}

$imp = get_impostazioni($db, $tid);

$fusi_orari = [
    'Europe/Rome'   => 'Italia (Roma)',
    'Europe/London' => 'Regno Unito (Londra)',
    'Europe/Paris'  => 'Francia (Parigi)',
    'Europe/Berlin' => 'Germania (Berlino)',
    'Europe/Madrid' => 'Spagna (Madrid)',
];
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
.imp-section{margin-bottom:24px}
.imp-section-head{margin-bottom:14px}
.imp-section-title{font-family:'Hanken Grotesk',sans-serif;font-size:16px;font-weight:700;color:var(--on-surface)}
.imp-section-sub{font-size:12px;color:var(--on-variant);margin-top:2px}
.imp-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.imp-check{display:flex;align-items:flex-start;gap:8px;cursor:pointer;padding:10px 0}
.imp-check input{width:15px;height:15px;accent-color:var(--blue);margin-top:1px;flex-shrink:0}
.imp-check .lbl{font-size:13px;font-weight:600;color:var(--on-surface)}
.imp-check .sub{font-size:11.5px;color:var(--on-variant);margin-top:1px}
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
        <div class="page-title">Impostazioni</div>
        <div class="page-sub">Dati azienda, notifiche, fuso orario e account</div>
      </div>
    </div>

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

    <!-- DATI AZIENDA -->
    <div class="card cp imp-section">
      <div class="imp-section-head">
        <div class="imp-section-title">Dati azienda</div>
        <div class="imp-section-sub">Usati per fatturazione e comunicazioni</div>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="save_azienda">
        <div class="form-group">
          <label class="form-label">Ragione sociale</label>
          <input type="text" name="ragione_sociale" class="form-input" value="<?php echo htmlspecialchars($imp['azienda_ragione_sociale'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Indirizzo</label>
          <input type="text" name="indirizzo" class="form-input" value="<?php echo htmlspecialchars($imp['azienda_indirizzo'] ?? ''); ?>">
        </div>
        <div class="imp-row">
          <div class="form-group">
            <label class="form-label">Partita IVA</label>
            <input type="text" name="piva" class="form-input" value="<?php echo htmlspecialchars($imp['azienda_piva'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Telefono</label>
            <input type="text" name="telefono" class="form-input" value="<?php echo htmlspecialchars($imp['azienda_telefono'] ?? ''); ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email di riferimento</label>
          <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($imp['azienda_email'] ?? ''); ?>">
        </div>
        <button type="submit" class="btn-primary">Salva dati azienda</button>
      </form>
    </div>

    <!-- NOTIFICHE -->
    <div class="card cp imp-section">
      <div class="imp-section-head">
        <div class="imp-section-title">Notifiche</div>
        <div class="imp-section-sub">Quando e a chi avvisare via email</div>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="save_notifiche">
        <div class="form-group">
          <label class="form-label">Email per le notifiche</label>
          <input type="email" name="notifiche_email" class="form-input" placeholder="es. admin@tuaazienda.it" value="<?php echo htmlspecialchars($imp['notifiche_email'] ?? ''); ?>">
        </div>
        <label class="imp-check">
          <input type="checkbox" name="notifica_contratti" <?php echo ($imp['notifiche_contratti_scadenza'] ?? '1')==='1' ? 'checked' : ''; ?>>
          <span>
            <span class="lbl">Contratti in scadenza</span>
            <span class="sub" style="display:block">Avviso quando un contratto inserzionista scade entro 7 giorni</span>
          </span>
        </label>
        <label class="imp-check">
          <input type="checkbox" name="notifica_offline" <?php echo ($imp['notifiche_dispositivi_offline'] ?? '1')==='1' ? 'checked' : ''; ?>>
          <span>
            <span class="lbl">Dispositivi offline</span>
            <span class="sub" style="display:block">Avviso quando uno schermo risulta offline da troppo tempo</span>
          </span>
        </label>
        <button type="submit" class="btn-primary" style="margin-top:10px">Salva notifiche</button>
      </form>
    </div>

    <!-- REGIONALI -->
    <div class="card cp imp-section">
      <div class="imp-section-head">
        <div class="imp-section-title">Fuso orario</div>
        <div class="imp-section-sub">Usato per orari corsi, countdown e programmazione ADV</div>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="save_regionali">
        <div class="form-group">
          <label class="form-label">Fuso orario</label>
          <select name="fuso_orario" class="form-input">
            <?php $attuale = $imp['fuso_orario'] ?? 'Europe/Rome'; ?>
            <?php foreach ($fusi_orari as $val => $label): ?>
            <option value="<?php echo $val; ?>" <?php echo $attuale===$val?'selected':''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn-primary">Salva</button>
      </form>
    </div>

    <!-- CAMBIO PASSWORD -->
    <div class="card cp imp-section">
      <div class="imp-section-head">
        <div class="imp-section-title">Il tuo account</div>
        <div class="imp-section-sub">Cambia la password del tuo utente (<?php echo htmlspecialchars(UTENTE_NOME); ?>)</div>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group">
          <label class="form-label">Password attuale</label>
          <input type="password" name="password_attuale" class="form-input" required>
        </div>
        <div class="imp-row">
          <div class="form-group">
            <label class="form-label">Nuova password</label>
            <input type="password" name="password_nuova" class="form-input" minlength="8" required>
          </div>
          <div class="form-group">
            <label class="form-label">Conferma nuova password</label>
            <input type="password" name="password_conferma" class="form-input" minlength="8" required>
          </div>
        </div>
        <button type="submit" class="btn-primary">Cambia password</button>
      </form>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
