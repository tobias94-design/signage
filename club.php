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

    if ($action === 'claim_pairing') {
        $code  = preg_replace('/[^0-9]/', '', $_POST['code'] ?? '');
        $token = trim($_POST['token_dispositivo'] ?? '');
        if ($code && $token) {
            $db->prepare("UPDATE pairing_pending SET token=?, claimed=1 WHERE code=?")->execute([$token, $code]);
        }
        header('Location: club.php');
        exit;
    }

    if ($action === 'nuovo') {
        $nome      = trim($_POST['nome'] ?? '');
        $club      = trim($_POST['club'] ?? '');
        $layout    = $_POST['layout'] ?? 'standard';
        $sheet_url = trim($_POST['sheet_url'] ?? '');
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($club ?: $nome)));
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, 20);
        $tok  = $slug . '-' . bin2hex(random_bytes(3));
        if ($nome) {
            $db->prepare("INSERT INTO dispositivi (nome, club, layout, sheet_url, token) VALUES (?, ?, ?, ?, ?)")
               ->execute([$nome, $club, $layout, $sheet_url, $tok]);
        }
        header('Location: club.php');
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
        header('Location: club.php');
        exit;
    }

    if ($action === 'elimina') {
        $tok = $_POST['token'] ?? '';
        $db->prepare("DELETE FROM dispositivi WHERE token=?")->execute([$tok]);
        header('Location: club.php');
        exit;
    }

    if ($action === 'layout_rapido') {
        $tok    = $_POST['token'] ?? '';
        $layout = $_POST['layout'] ?? 'standard';
        $db->prepare("UPDATE dispositivi SET layout=? WHERE token=?")->execute([$layout, $tok]);
        header('Location: club.php');
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
    if (!$dev) { header('Location: club.php'); exit; }
}

$titolo = 'Dispositivi';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">

<?php if ($view === 'lista'): ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div></div>
        <a href="club.php?view=nuovo" class="btn">+ Nuovo dispositivo</a>
    </div>

    <!-- ── PAIRING IN ATTESA ─────────────────────────────────── -->
    <?php
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS pairing_pending (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            machine TEXT DEFAULT '',
            token TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires DATETIME,
            claimed INTEGER DEFAULT 0
        )");
        $db->exec("DELETE FROM pairing_pending WHERE expires < datetime('now')");
        $pending = $db->query("SELECT * FROM pairing_pending WHERE claimed=0 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $pending = []; }
    ?>
    <?php if (!empty($pending)): ?>
    <div class="box" style="margin-bottom:20px; border:1px solid rgba(232,80,2,0.3); background:rgba(232,80,2,0.05);">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
            <span style="font-size:18px;">🔗</span>
            <div>
                <div style="font-weight:700; color:var(--sg-white);">Pairing in attesa (<?= count($pending) ?>)</div>
                <div style="font-size:12px; color:var(--sg-muted);">Un PC ha mostrato un codice — associalo a un dispositivo</div>
            </div>
        </div>
        <?php foreach ($pending as $p):
            $mins_left = max(0, round((strtotime($p['expires']) - time()) / 60));
        ?>
        <div style="display:flex; align-items:center; gap:14px; padding:12px 16px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:12px; margin-bottom:8px;">
            <div style="font-size:28px; font-weight:900; font-family:monospace; color:var(--sg-white); letter-spacing:4px;"><?= htmlspecialchars($p['code']) ?></div>
            <div style="flex:1;">
                <div style="font-size:13px; color:var(--sg-white); font-weight:600;">💻 <?= htmlspecialchars($p['machine'] ?: 'PC sconosciuto') ?></div>
                <div style="font-size:11px; color:var(--sg-muted);">Scade tra <?= $mins_left ?> min · <?= date('H:i', strtotime($p['created_at'])) ?></div>
            </div>
            <form method="POST" style="display:flex; align-items:center; gap:8px; margin:0;">
                <input type="hidden" name="action" value="claim_pairing">
                <input type="hidden" name="code" value="<?= htmlspecialchars($p['code']) ?>">
                <select name="token_dispositivo" required
                        style="background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.12); border-radius:8px; color:var(--sg-white); padding:7px 12px; font-size:12px;">
                    <option value="">— Associa a dispositivo —</option>
                    <?php foreach ($dispositivi as $d): ?>
                    <option value="<?= htmlspecialchars($d['token']) ?>"><?= htmlspecialchars($d['club'] ?: $d['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm" style="background:rgba(232,80,2,0.8);">✓ Associa</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

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
            <a href="club.php?view=modifica&token=<?php echo $d['token']; ?>" class="btn btn-sm btn-secondary">✏️ Modifica</a>
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
            <a href="club.php" class="btn btn-secondary">← Torna alla lista</a>
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
                    <a href="club.php" class="btn btn-secondary">Annulla</a>
                </div>
            </form>
        </div>
    </div>


<?php elseif ($view === 'modifica' && $dev): ?>

    <div style="max-width:600px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <div></div>
            <a href="club.php" class="btn btn-secondary">← Torna alla lista</a>
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
                    <a href="club.php" class="btn btn-secondary">Annulla</a>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>