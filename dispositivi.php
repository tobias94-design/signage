<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
$db = getDB();

$msg    = '';
$view   = $_GET['view'] ?? 'lista';
$token  = $_GET['token'] ?? '';

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

$dispositivi = $db->query("SELECT d.*, p.nome as profilo_nome FROM dispositivi d LEFT JOIN profili p ON p.id = d.profilo_id ORDER BY d.nome")->fetchAll(PDO::FETCH_ASSOC);
$profili     = $db->query("SELECT * FROM profili ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$dev = null;
if ($view === 'modifica' && $token) {
    $s = $db->prepare("SELECT * FROM dispositivi WHERE token=?");
    $s->execute([$token]);
    $dev = $s->fetch(PDO::FETCH_ASSOC);
    if (!$dev) { header('Location: dispositivi.php'); exit; }
}

$titolo = 'Dispositivi';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">

<?php if ($view === 'lista'): ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div></div>
        <a href="dispositivi.php?view=nuovo" class="btn">+ Nuovo dispositivo</a>
    </div>

    <?php if (empty($dispositivi)): ?>
        <div class="box"><div class="vuoto">Nessun dispositivo ancora. Creane uno!</div></div>
    <?php endif; ?>

    <?php foreach ($dispositivi as $d):
        $playerUrl = ($d['layout'] ?? 'standard') === 'corsi'
            ? 'player/corsi.php?token=' . $d['token']
            : 'player/?token=' . $d['token'];
    ?>
    <div class="box" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px;">

            <div style="flex:1;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px; flex-wrap:wrap;">
                    <span style="font-size:17px; font-weight:bold; color:#fff;">
                        <?php echo htmlspecialchars($d['nome']); ?>
                    </span>
                    <span class="badge badge-profilo"><?php echo htmlspecialchars($d['layout'] ?? 'standard'); ?></span>
                    <?php if ($d['profilo_nome']): ?>
                        <span class="badge" style="background:#0f3460; color:#5dade2;"><?php echo htmlspecialchars($d['profilo_nome']); ?></span>
                    <?php endif; ?>
                </div>

                <div style="font-size:13px; color:#888; margin-bottom:4px;">
                    Club: <span style="color:#ccc;"><?php echo htmlspecialchars($d['club'] ?? '—'); ?></span>
                </div>
                <div style="font-size:13px; color:#888; margin-bottom:4px;">
                    Token: <code style="color:#e94560; font-size:12px;"><?php echo htmlspecialchars($d['token']); ?></code>
                </div>
                <div style="font-size:13px; margin-top:6px;">
                    <?php if (!empty($d['sheet_url'])): ?>
                        <span class="badge badge-online">✅ Sheet configurato</span>
                    <?php else: ?>
                        <span class="badge" style="background:#3d2e00; color:#f39c12;">⚠️ Sheet non configurato</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Layout rapido -->
            <form method="POST" style="flex-shrink:0;">
                <input type="hidden" name="action" value="layout_rapido">
                <input type="hidden" name="token" value="<?php echo $d['token']; ?>">
                <select name="layout" onchange="this.form.submit()"
                        style="background:#0f3460; border:1px solid #1a4a7a; border-radius:6px; color:#eee; padding:6px 10px; font-size:13px; cursor:pointer;">
                    <option value="standard" <?php echo ($d['layout'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                    <option value="corsi"    <?php echo ($d['layout'] ?? '') === 'corsi'    ? 'selected' : ''; ?>>Corsi Fitness</option>
                </select>
            </form>
        </div>

        <div style="display:flex; gap:10px; margin-top:16px; flex-wrap:wrap; align-items:center; border-top:1px solid #0f3460; padding-top:14px;">
            <a href="<?php echo $playerUrl; ?>" target="_blank" class="btn btn-sm btn-success">▶ Apri Player</a>
            <a href="dispositivi.php?view=modifica&token=<?php echo $d['token']; ?>" class="btn btn-sm btn-secondary">✏️ Modifica</a>
            <form method="POST" onsubmit="return confirm('Eliminare questo dispositivo?')" style="margin:0;">
                <input type="hidden" name="action" value="elimina">
                <input type="hidden" name="token" value="<?php echo $d['token']; ?>">
                <button type="submit" class="btn btn-sm btn-danger">🗑️ Elimina</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>


<?php elseif ($view === 'nuovo'): ?>

    <div style="max-width:600px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <div></div>
            <a href="dispositivi.php" class="btn btn-secondary">← Torna alla lista</a>
        </div>
        <div class="box">
            <h2>Nuovo dispositivo</h2>
            <form method="POST">
                <input type="hidden" name="action" value="nuovo">
                <label>Nome *</label>
                <input type="text" name="nome" required placeholder="Es: TV Sala Pesi">
                <label>Club</label>
                <input type="text" name="club" placeholder="Es: Gymnasium Milano">
                <label>Layout</label>
                <select name="layout">
                    <option value="standard">Standard</option>
                    <option value="corsi">Corsi Fitness</option>
                </select>
                <label>URL Google Sheet Corsi</label>
                <input type="text" name="sheet_url" placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv">
                <div style="font-size:12px; color:#666; margin-top:-8px; margin-bottom:16px;">Nel foglio: File → Pubblica sul web → CSV → copia link</div>
                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn">✅ Crea dispositivo</button>
                    <a href="dispositivi.php" class="btn btn-secondary">Annulla</a>
                </div>
            </form>
        </div>
    </div>


<?php elseif ($view === 'modifica' && $dev): ?>

    <div style="max-width:600px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <div></div>
            <a href="dispositivi.php" class="btn btn-secondary">← Torna alla lista</a>
        </div>
        <div class="box">
            <h2>Modifica: <?php echo htmlspecialchars($dev['nome']); ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="aggiorna">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($dev['token']); ?>">
                <label>Nome *</label>
                <input type="text" name="nome" required value="<?php echo htmlspecialchars($dev['nome']); ?>">
                <label>Club</label>
                <input type="text" name="club" value="<?php echo htmlspecialchars($dev['club'] ?? ''); ?>">
                <label>Profilo playlist</label>
                <select name="profilo_id">
                    <option value="">— Nessuno —</option>
                    <?php foreach ($profili as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($dev['profilo_id'] ?? '') == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label>Layout</label>
                <select name="layout">
                    <option value="standard" <?php echo ($dev['layout'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                    <option value="corsi"    <?php echo ($dev['layout'] ?? '') === 'corsi'    ? 'selected' : ''; ?>>Corsi Fitness</option>
                </select>
                <label>URL Google Sheet Corsi</label>
                <input type="text" name="sheet_url" value="<?php echo htmlspecialchars($dev['sheet_url'] ?? ''); ?>"
                       placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv">
                <div style="font-size:12px; color:#666; margin-top:-8px; margin-bottom:16px;">Nel foglio: File → Pubblica sul web → CSV → copia link</div>
                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn">💾 Salva modifiche</button>
                    <a href="dispositivi.php" class="btn btn-secondary">Annulla</a>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>