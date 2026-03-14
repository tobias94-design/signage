<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$db  = getDB();
$me  = getUtenteCorrente();
$msg = '';
$force = isset($_GET['force']); // forza cambio password temporanea

// Carica dati freschi dal DB
$utente = $db->query("SELECT * FROM utenti WHERE id=".(int)$me['id'])->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'aggiorna_profilo') {
        $nome = trim($_POST['nome'] ?? '');
        if ($nome) {
            $db->prepare("UPDATE utenti SET nome=? WHERE id=?")->execute([$nome, $me['id']]);
            $_SESSION['utente_nome'] = $nome;
            $msg = 'ok|Profilo aggiornato!';
            $utente['nome'] = $nome;
        }
    }

    if ($action === 'cambia_password') {
        $vecchia  = $_POST['vecchia_password'] ?? '';
        $nuova    = $_POST['nuova_password'] ?? '';
        $conferma = $_POST['conferma_password'] ?? '';

        if ($force || password_verify($vecchia, $utente['password_hash'])) {
            if (strlen($nuova) < 6) {
                $msg = 'err|La password deve essere di almeno 6 caratteri.';
            } elseif ($nuova !== $conferma) {
                $msg = 'err|Le password non coincidono.';
            } else {
                $db->prepare("UPDATE utenti SET password_hash=?, temp_password=0 WHERE id=?")
                   ->execute([password_hash($nuova, PASSWORD_DEFAULT), $me['id']]);
                $_SESSION['temp_password'] = 0;
                $msg = 'ok|Password aggiornata con successo!';
                $force = false;
            }
        } else {
            $msg = 'err|La password attuale non è corretta.';
        }
    }
}

$titolo = 'Profilo';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width:640px;">

<?php if ($force): ?>
<div style="padding:16px 20px;background:rgba(245,158,11,0.10);border:1px solid rgba(245,158,11,0.25);border-radius:12px;color:#f59e0b;font-size:13px;margin-bottom:24px;">
    🔑 Stai usando una <strong>password temporanea</strong>. Imposta una nuova password per continuare.
</div>
<?php endif; ?>

<?php if ($msg): [$tm,$txt] = explode('|',$msg,2); ?>
<div style="padding:14px 18px;margin-bottom:20px;border-radius:12px;font-size:13px;
    background:<?= $tm==='ok'?'rgba(48,209,88,0.10)':'rgba(233,69,96,0.10)' ?>;
    border:1px solid <?= $tm==='ok'?'rgba(48,209,88,0.25)':'rgba(233,69,96,0.25)' ?>;
    color:<?= $tm==='ok'?'var(--sg-green)':'#e94560' ?>;">
    <?= $tm==='ok'?'✓':'⚠️' ?> <?= $txt ?>
</div>
<?php endif; ?>

<!-- ── INFO PROFILO ── -->
<div class="box" style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
        <div style="width:56px;height:56px;border-radius:50%;
             background:<?= $utente['ruolo']==='admin'?'rgba(232,80,2,0.20)':'rgba(255,255,255,0.07)' ?>;
             display:flex;align-items:center;justify-content:center;
             font-size:22px;font-weight:800;color:<?= $utente['ruolo']==='admin'?'var(--sg-orange)':'var(--sg-muted)' ?>;">
            <?= strtoupper(substr($utente['nome'],0,1)) ?>
        </div>
        <div>
            <div style="font-size:18px;font-weight:800;color:var(--sg-white);"><?= htmlspecialchars($utente['nome']) ?></div>
            <div style="font-size:12px;color:var(--sg-muted);">@<?= htmlspecialchars($utente['username']) ?></div>
            <span style="display:inline-block;margin-top:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
                background:<?= $utente['ruolo']==='admin'?'rgba(232,80,2,0.15)':'rgba(255,255,255,0.07)' ?>;
                color:<?= $utente['ruolo']==='admin'?'var(--sg-orange)':'var(--sg-muted)' ?>;">
                <?= $utente['ruolo'] === 'admin' ? '⬡ Admin' : '◈ Operatore' ?>
            </span>
        </div>
    </div>

    <?php if (!$force): ?>
    <form method="POST">
        <input type="hidden" name="action" value="aggiorna_profilo">
        <label>Nome visualizzato</label>
        <div style="display:flex;gap:10px;">
            <input type="text" name="nome" value="<?= htmlspecialchars($utente['nome']) ?>" required style="flex:1;">
            <button type="submit" class="btn btn-sm">💾 Salva</button>
        </div>
    </form>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.06);">
        <div style="font-size:12px;color:var(--sg-muted);">
            Username: <code style="color:var(--sg-white);">@<?= htmlspecialchars($utente['username']) ?></code>
            (non modificabile)
        </div>
        <?php if ($utente['ultimo_accesso']): ?>
        <div style="font-size:12px;color:var(--sg-muted);margin-top:4px;">
            Ultimo accesso: <?= date('d/m/Y H:i', strtotime($utente['ultimo_accesso'])) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── CAMBIO PASSWORD ── -->
<div class="box">
    <h2 style="margin-bottom:20px;"><?= $force ? '🔑 Imposta nuova password' : 'Cambia password' ?></h2>
    <form method="POST">
        <input type="hidden" name="action" value="cambia_password">
        <?php if (!$force): ?>
        <label>Password attuale</label>
        <input type="password" name="vecchia_password" required autocomplete="current-password">
        <?php endif; ?>
        <label>Nuova password <span style="font-size:11px;color:var(--sg-muted);">(min. 6 caratteri)</span></label>
        <div style="position:relative;">
            <input type="password" name="nuova_password" id="nuova-pass" required autocomplete="new-password" style="width:100%;padding-right:50px;">
            <button type="button" onclick="togglePass('nuova-pass')"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--sg-muted);cursor:pointer;font-size:16px;">👁</button>
        </div>
        <label>Conferma nuova password</label>
        <input type="password" name="conferma_password" required autocomplete="new-password">
        <div style="margin-top:8px;">
            <div id="pass-strength" style="height:4px;border-radius:2px;background:rgba(255,255,255,0.08);margin-bottom:6px;overflow:hidden;">
                <div id="pass-fill" style="height:100%;width:0;transition:width 0.3s;border-radius:2px;"></div>
            </div>
            <div id="pass-label" style="font-size:11px;color:var(--sg-muted);"></div>
        </div>
        <button type="submit" class="btn" style="margin-top:16px;width:100%;">
            <?= $force ? '✅ Imposta password' : '🔑 Aggiorna password' ?>
        </button>
    </form>
</div>

</div>

<script>
function togglePass(id) {
    var el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}
document.getElementById('nuova-pass').addEventListener('input', function() {
    var v = this.value;
    var score = 0;
    if (v.length >= 6)  score++;
    if (v.length >= 10) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    var colors = ['#e94560','#f59e0b','#f59e0b','#30d158','#30d158'];
    var labels = ['Troppo corta','Debole','Discreta','Buona','Ottima'];
    var fill = document.getElementById('pass-fill');
    var label = document.getElementById('pass-label');
    fill.style.width = (score * 20) + '%';
    fill.style.background = colors[score-1] || '#e94560';
    label.textContent = v.length > 0 ? labels[score-1] || '' : '';
    label.style.color = colors[score-1] || 'var(--sg-muted)';
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
