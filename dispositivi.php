<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
$db = getDB();

$msg    = '';
$view   = $_GET['view'] ?? 'lista'; // lista | nuovo | modifica
$token  = $_GET['token'] ?? '';

// ── AZIONI POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'nuovo') {
        $nome      = trim($_POST['nome'] ?? '');
        $club      = trim($_POST['club'] ?? '');
        $layout    = $_POST['layout'] ?? 'standard';
        $sheet_url = trim($_POST['sheet_url'] ?? '');
        $tok       = bin2hex(random_bytes(16));
        if ($nome) {
            $db->prepare("INSERT INTO dispositivi (nome, club, layout, sheet_url, token) VALUES (?, ?, ?, ?, ?)")
               ->execute([$nome, $club, $layout, $sheet_url, $tok]);
        }
        header('Location: dispositivi.php');
        exit;
    }

    if ($action === 'aggiorna') {
        $tok        = $_POST['token'] ?? '';
        $nome       = trim($_POST['nome'] ?? '');
        $club       = trim($_POST['club'] ?? '');
        $profilo_id = $_POST['profilo_id'] ?? null;
        $layout     = $_POST['layout'] ?? 'standard';
        $sheet_url  = trim($_POST['sheet_url'] ?? '');
        $db->prepare("UPDATE dispositivi SET nome=?, club=?, profilo_id=?, layout=?, sheet_url=? WHERE token=?")
           ->execute([$nome, $club, $profilo_id ?: null, $layout, $sheet_url, $tok]);
        header('Location: dispositivi.php');
        exit;
    }

    if ($action === 'elimina') {
        $tok = $_POST['token'] ?? '';
        $db->prepare("DELETE FROM dispositivi WHERE token=?")->execute([$tok]);
        header('Location: dispositivi.php');
        exit;
    }

    if ($action === 'layout_rapido') {
        $tok    = $_POST['token'] ?? '';
        $layout = $_POST['layout'] ?? 'standard';
        $db->prepare("UPDATE dispositivi SET layout=? WHERE token=?")->execute([$layout, $tok]);
        header('Location: dispositivi.php');
        exit;
    }
}

// ── DATI ────────────────────────────────────────────────────
$dispositivi = $db->query("SELECT d.*, p.nome as profilo_nome FROM dispositivi d LEFT JOIN profili p ON p.id = d.profilo_id ORDER BY d.nome")->fetchAll(PDO::FETCH_ASSOC);
$profili     = $db->query("SELECT * FROM profili ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// dispositivo da modificare
$dev = null;
if ($view === 'modifica' && $token) {
    $s = $db->prepare("SELECT * FROM dispositivi WHERE token=?");
    $s->execute([$token]);
    $dev = $s->fetch(PDO::FETCH_ASSOC);
    if (!$dev) { header('Location: dispositivi.php'); exit; }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.dev-card {
    background: #1a1a2e;
    border: 1px solid #2a2a4a;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 16px;
}
.dev-card h3 { margin: 0 0 8px; font-size: 18px; color: #fff; }
.dev-meta { font-size: 13px; color: #888; margin-bottom: 4px; }
.dev-meta code { color: #e94560; }
.dev-actions { margin-top: 14px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.btn-sm { padding: 6px 14px; font-size: 13px; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-block; }
.btn-green  { background: #28a745; color: #fff; }
.btn-blue   { background: #0d6efd; color: #fff; }
.btn-red    { background: #dc3545; color: #fff; }
.btn-gray   { background: #444; color: #fff; }
.btn-pink   { background: #e94560; color: #fff; }
.form-page { max-width: 600px; margin: 0 auto; }
.form-page h2 { margin-bottom: 24px; }
.field { margin-bottom: 16px; }
.field label { display: block; font-size: 13px; color: #aaa; margin-bottom: 6px; }
.field input, .field select, .field textarea {
    width: 100%;
    padding: 10px 14px;
    background: #1a1a2e;
    border: 1px solid #333;
    border-radius: 6px;
    color: #fff;
    font-size: 15px;
}
.field .hint { font-size: 12px; color: #666; margin-top: 4px; }
.badge-layout {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 12px;
    background: #333;
    color: #fff;
    margin-left: 8px;
}
.sheet-ok  { color: #28a745; font-size: 13px; }
.sheet-no  { color: #e9a045; font-size: 13px; }
.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.top-bar h2 { margin: 0; }
select.layout-inline {
    background: #1a1a2e;
    border: 1px solid #333;
    border-radius: 6px;
    color: #fff;
    padding: 4px 10px;
    font-size: 13px;
    cursor: pointer;
}
</style>

<div style="padding: 24px;">

<?php if ($view === 'lista'): ?>

    <!-- ── LISTA ── -->
    <div class="top-bar">
        <h2>Dispositivi</h2>
        <a href="dispositivi.php?view=nuovo" class="btn-sm btn-pink">+ Nuovo dispositivo</a>
    </div>

    <?php foreach ($dispositivi as $d): ?>
    <div class="dev-card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <h3>
                    <?php echo htmlspecialchars($d['nome']); ?>
                    <span class="badge-layout"><?php echo htmlspecialchars($d['layout'] ?? 'standard'); ?></span>
                    <?php if ($d['profilo_nome']): ?>
                        <span class="badge-layout" style="background:#0d6efd;"><?php echo htmlspecialchars($d['profilo_nome']); ?></span>
                    <?php endif; ?>
                </h3>
                <div class="dev-meta">Club: <?php echo htmlspecialchars($d['club'] ?? '—'); ?></div>
                <div class="dev-meta">Token: <code><?php echo htmlspecialchars($d['token']); ?></code></div>
                <div class="dev-meta">
                    <?php if (!empty($d['sheet_url'])): ?>
                        <span class="sheet-ok">✅ Sheet configurato</span>
                    <?php else: ?>
                        <span class="sheet-no">⚠️ Sheet non configurato</span>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Layout rapido -->
            <form method="POST">
                <input type="hidden" name="action" value="layout_rapido">
                <input type="hidden" name="token" value="<?php echo $d['token']; ?>">
                <select name="layout" class="layout-inline" onchange="this.form.submit()">
                    <option value="standard" <?php echo ($d['layout'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                    <option value="corsi"    <?php echo ($d['layout'] ?? '') === 'corsi'    ? 'selected' : ''; ?>>Corsi Fitness</option>
                </select>
            </form>
        </div>

        <div class="dev-actions">
            <?php
            $playerUrl = ($d['layout'] ?? 'standard') === 'corsi'
                ? 'player/corsi.php?token=' . $d['token']
                : 'player/?token=' . $d['token'];
            ?>
            <a href="<?php echo $playerUrl; ?>" target="_blank" class="btn-sm btn-green">▶ Apri</a>
            <a href="dispositivi.php?view=modifica&token=<?php echo $d['token']; ?>" class="btn-sm btn-blue">✏️ Modifica</a>
            <form method="POST" onsubmit="return confirm('Eliminare questo dispositivo?')" style="margin:0;">
                <input type="hidden" name="action" value="elimina">
                <input type="hidden" name="token" value="<?php echo $d['token']; ?>">
                <button type="submit" class="btn-sm btn-red">🗑️ Elimina</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>


<?php elseif ($view === 'nuovo'): ?>

    <!-- ── NUOVO ── -->
    <div class="form-page">
        <div class="top-bar">
            <h2>Nuovo dispositivo</h2>
            <a href="dispositivi.php" class="btn-sm btn-gray">← Torna alla lista</a>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="nuovo">
            <div class="field">
                <label>Nome *</label>
                <input type="text" name="nome" required placeholder="Es: TV Sala Pesi">
            </div>
            <div class="field">
                <label>Club</label>
                <input type="text" name="club" placeholder="Es: Gymnasium Milano">
            </div>
            <div class="field">
                <label>Layout</label>
                <select name="layout">
                    <option value="standard">Standard</option>
                    <option value="corsi">Corsi Fitness</option>
                </select>
            </div>
            <div class="field">
                <label>URL Google Sheet Corsi</label>
                <input type="text" name="sheet_url" placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv">
                <div class="hint">Nel foglio: File → Pubblica sul web → CSV → copia link</div>
            </div>
            <div style="display:flex; gap:12px; margin-top:24px;">
                <button type="submit" class="btn-sm btn-pink">✅ Crea dispositivo</button>
                <a href="dispositivi.php" class="btn-sm btn-gray">Annulla</a>
            </div>
        </form>
    </div>


<?php elseif ($view === 'modifica' && $dev): ?>

    <!-- ── MODIFICA ── -->
    <div class="form-page">
        <div class="top-bar">
            <h2>Modifica: <?php echo htmlspecialchars($dev['nome']); ?></h2>
            <a href="dispositivi.php" class="btn-sm btn-gray">← Torna alla lista</a>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="aggiorna">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($dev['token']); ?>">
            <div class="field">
                <label>Nome *</label>
                <input type="text" name="nome" required value="<?php echo htmlspecialchars($dev['nome']); ?>">
            </div>
            <div class="field">
                <label>Club</label>
                <input type="text" name="club" value="<?php echo htmlspecialchars($dev['club'] ?? ''); ?>">
            </div>
            <div class="field">
                <label>Profilo playlist</label>
                <select name="profilo_id">
                    <option value="">— Nessuno —</option>
                    <?php foreach ($profili as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($dev['profilo_id'] ?? '') == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Layout</label>
                <select name="layout">
                    <option value="standard" <?php echo ($dev['layout'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                    <option value="corsi"    <?php echo ($dev['layout'] ?? '') === 'corsi'    ? 'selected' : ''; ?>>Corsi Fitness</option>
                </select>
            </div>
            <div class="field">
                <label>URL Google Sheet Corsi</label>
                <input type="text" name="sheet_url" value="<?php echo htmlspecialchars($dev['sheet_url'] ?? ''); ?>"
                       placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv">
                <div class="hint">Nel foglio: File → Pubblica sul web → CSV → copia link</div>
            </div>
            <div style="display:flex; gap:12px; margin-top:24px;">
                <button type="submit" class="btn-sm btn-pink">💾 Salva modifiche</button>
                <a href="dispositivi.php" class="btn-sm btn-gray">Annulla</a>
            </div>
        </form>
    </div>

<?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>