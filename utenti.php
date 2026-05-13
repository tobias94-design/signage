<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
$db  = getDB();
$me  = getUtenteCorrente();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'nuovo') {
        $nome     = trim($_POST['nome'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $ruolo    = $_POST['ruolo'] === 'admin' ? 'admin' : 'operatore';
        $pass     = trim($_POST['password'] ?? '');
        if ($nome && $username && $pass) {
            try {
                $db->prepare("INSERT INTO utenti (nome, username, password_hash, ruolo) VALUES (?,?,?,?)")
                   ->execute([$nome, $username, password_hash($pass, PASSWORD_DEFAULT), $ruolo]);
                $msg = 'ok|Utente creato!';
            } catch(Exception $e) {
                $msg = 'err|Username già esistente.';
            }
        } else {
            $msg = 'err|Compila tutti i campi.';
        }
    }

    if ($action === 'aggiorna') {
        $id    = (int)$_POST['id'];
        $nome  = trim($_POST['nome'] ?? '');
        $ruolo = $_POST['ruolo'] === 'admin' ? 'admin' : 'operatore';
        // Non permettere di degradare se stessi
        if ($id === (int)$me['id'] && $ruolo !== 'admin') {
            $msg = 'err|Non puoi rimuovere il ruolo admin a te stesso.';
        } else {
            $db->prepare("UPDATE utenti SET nome=?, ruolo=? WHERE id=?")->execute([$nome, $ruolo, $id]);
            $msg = 'ok|Modifiche salvate!';
        }
    }

    if ($action === 'toggle_attivo') {
        $id  = (int)$_POST['id'];
        $val = (int)$_POST['attivo'];
        if ($id === (int)$me['id']) {
            $msg = 'err|Non puoi disattivare te stesso.';
        } else {
            $db->prepare("UPDATE utenti SET attivo=? WHERE id=?")->execute([$val, $id]);
            $msg = 'ok|Stato aggiornato.';
        }
    }

    if ($action === 'elimina') {
        $id = (int)$_POST['id'];
        if ($id === (int)$me['id']) {
            $msg = 'err|Non puoi eliminare te stesso.';
        } else {
            $db->prepare("DELETE FROM utenti WHERE id=?")->execute([$id]);
            $msg = 'ok|Utente eliminato.';
        }
    }

    if ($action === 'reset_password') {
        $id        = (int)$_POST['id'];
        $temp_pass = strtoupper(substr(md5(random_bytes(8)), 0, 8));
        $db->prepare("UPDATE utenti SET password_hash=?, temp_password=1 WHERE id=?")
           ->execute([password_hash($temp_pass, PASSWORD_DEFAULT), $id]);
        $msg = 'ok|Password temporanea: <strong style="font-family:monospace;font-size:16px;letter-spacing:2px;">'.$temp_pass.'</strong> — comunicala all\'utente.';
    }
}

$utenti = $db->query("SELECT * FROM utenti ORDER BY ruolo DESC, nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$titolo = 'Utenti';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">

<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;flex-wrap:wrap;">
    <div style="flex:1;">
        <div style="font-size:11px;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:2px;">Gestione</div>
        <div style="font-size:20px;font-weight:800;color:var(--sg-white);">Utenti (<?= count($utenti) ?>)</div>
    </div>
    <button onclick="document.getElementById('modal-nuovo').style.display='flex'" class="btn">+ Nuovo utente</button>
</div>

<?php if ($msg): [$tm,$txt] = explode('|',$msg,2); ?>
<div style="padding:14px 18px;margin-bottom:20px;border-radius:12px;font-size:13px;
    background:<?= $tm==='ok'?'rgba(48,209,88,0.10)':'rgba(233,69,96,0.10)' ?>;
    border:1px solid <?= $tm==='ok'?'rgba(48,209,88,0.25)':'rgba(233,69,96,0.25)' ?>;
    color:<?= $tm==='ok'?'var(--sg-green)':'#e94560' ?>;">
    <?= $tm==='ok'?'✓':'⚠️' ?> <?= $txt ?>
</div>
<?php endif; ?>

<!-- ── TABELLA UTENTI ── -->
<div class="box" style="padding:0;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="border-bottom:1px solid rgba(255,255,255,0.07);">
                <th style="padding:12px 18px;text-align:left;font-size:11px;color:var(--sg-muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;">Utente</th>
                <th style="padding:12px 18px;text-align:left;font-size:11px;color:var(--sg-muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;">Ruolo</th>
                <th style="padding:12px 18px;text-align:left;font-size:11px;color:var(--sg-muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;">Ultimo accesso</th>
                <th style="padding:12px 18px;text-align:left;font-size:11px;color:var(--sg-muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;">Stato</th>
                <th style="padding:12px 18px;text-align:left;font-size:11px;color:var(--sg-muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;">Azioni</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($utenti as $u):
            $is_me = (int)$u['id'] === (int)$me['id'];
        ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
            <td style="padding:14px 18px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;border-radius:50%;background:<?= $u['ruolo']==='admin'?'rgba(232,80,2,0.20)':'rgba(255,255,255,0.07)' ?>;
                         display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:<?= $u['ruolo']==='admin'?'var(--sg-orange)':'var(--sg-muted)' ?>;flex-shrink:0;">
                        <?= strtoupper(substr($u['nome'],0,1)) ?>
                    </div>
                    <div>
                        <div style="font-size:14px;font-weight:600;color:var(--sg-white);">
                            <?= htmlspecialchars($u['nome']) ?>
                            <?php if ($is_me): ?><span style="font-size:10px;color:var(--sg-orange);margin-left:6px;">tu</span><?php endif; ?>
                        </div>
                        <div style="font-size:11px;color:var(--sg-muted);">@<?= htmlspecialchars($u['username']) ?></div>
                    </div>
                </div>
            </td>
            <td style="padding:14px 18px;">
                <span style="padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;
                    background:<?= $u['ruolo']==='admin'?'rgba(232,80,2,0.15)':'rgba(255,255,255,0.07)' ?>;
                    color:<?= $u['ruolo']==='admin'?'var(--sg-orange)':'var(--sg-muted)' ?>;">
                    <?= $u['ruolo'] === 'admin' ? '⬡ Admin' : '◈ Operatore' ?>
                </span>
            </td>
            <td style="padding:14px 18px;font-size:12px;color:var(--sg-muted);">
                <?= $u['ultimo_accesso'] ? date('d/m/Y H:i', strtotime($u['ultimo_accesso'])) : 'Mai' ?>
            </td>
            <td style="padding:14px 18px;">
                <form method="POST" style="margin:0;display:inline;">
                    <input type="hidden" name="action" value="toggle_attivo">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="attivo" value="<?= $u['attivo'] ? 0 : 1 ?>">
                    <button type="submit" <?= $is_me?'disabled':'' ?>
                            style="padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;cursor:<?= $is_me?'default':'pointer' ?>;border:none;
                                   background:<?= $u['attivo']?'rgba(48,209,88,0.12)':'rgba(255,255,255,0.06)' ?>;
                                   color:<?= $u['attivo']?'var(--sg-green)':'var(--sg-muted)' ?>;">
                        <?= $u['attivo'] ? '● Attivo' : '○ Inattivo' ?>
                    </button>
                </form>
            </td>
            <td style="padding:14px 18px;">
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                    <button onclick="apriModifica(<?= htmlspecialchars(json_encode($u)) ?>)"
                            class="btn btn-sm btn-secondary" style="font-size:11px;">✏️ Modifica</button>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Reset password per <?= htmlspecialchars($u['nome']) ?>?')">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-secondary" style="font-size:11px;">🔑 Reset</button>
                    </form>
                    <?php if (!$is_me): ?>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Eliminare <?= htmlspecialchars($u['nome']) ?>?')">
                        <input type="hidden" name="action" value="elimina">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" style="font-size:11px;">✕</button>
                    </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</div>

<!-- ── MODAL NUOVO UTENTE ── -->
<div id="modal-nuovo" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#0f0f1a;border:1px solid rgba(255,255,255,0.10);border-radius:20px;padding:28px;width:100%;max-width:440px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h2 style="margin:0;">Nuovo utente</h2>
            <button onclick="document.getElementById('modal-nuovo').style.display='none'"
                    style="background:none;border:none;color:var(--sg-muted);font-size:24px;cursor:pointer;line-height:1;">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="nuovo">
            <label>Nome completo</label>
            <input type="text" name="nome" required placeholder="Es: Marco Rossi">
            <label>Username</label>
            <input type="text" name="username" required placeholder="Es: marco.rossi" autocomplete="off">
            <label>Password iniziale</label>
            <div style="display:flex;gap:8px;">
                <input type="text" name="password" id="new-pass" required placeholder="Password" autocomplete="off" style="flex:1;">
                <button type="button" onclick="generaPass()" class="btn btn-sm btn-secondary">Genera</button>
            </div>
            <label style="margin-top:12px;">Ruolo</label>
            <select name="ruolo">
                <option value="operatore">◈ Operatore</option>
                <option value="admin">⬡ Admin</option>
            </select>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" class="btn">✅ Crea utente</button>
                <button type="button" onclick="document.getElementById('modal-nuovo').style.display='none'"
                        class="btn btn-secondary">Annulla</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL MODIFICA ── -->
<div id="modal-modifica" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#0f0f1a;border:1px solid rgba(255,255,255,0.10);border-radius:20px;padding:28px;width:100%;max-width:440px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h2 style="margin:0;">Modifica utente</h2>
            <button onclick="document.getElementById('modal-modifica').style.display='none'"
                    style="background:none;border:none;color:var(--sg-muted);font-size:24px;cursor:pointer;line-height:1;">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="aggiorna">
            <input type="hidden" name="id" id="mod-id">
            <label>Nome completo</label>
            <input type="text" name="nome" id="mod-nome" required>
            <label>Username</label>
            <input type="text" id="mod-username" disabled
                   style="opacity:0.5;cursor:not-allowed;">
            <label>Ruolo</label>
            <select name="ruolo" id="mod-ruolo">
                <option value="operatore">◈ Operatore</option>
                <option value="admin">⬡ Admin</option>
            </select>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" class="btn">💾 Salva</button>
                <button type="button" onclick="document.getElementById('modal-modifica').style.display='none'"
                        class="btn btn-secondary">Annulla</button>
            </div>
        </form>
    </div>
</div>

<script>
function apriModifica(u) {
    document.getElementById('mod-id').value       = u.id;
    document.getElementById('mod-nome').value     = u.nome;
    document.getElementById('mod-username').value = u.username;
    document.getElementById('mod-ruolo').value    = u.ruolo;
    document.getElementById('modal-modifica').style.display = 'flex';
}
function generaPass() {
    var chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#';
    var pass = '';
    for (var i=0; i<10; i++) pass += chars[Math.floor(Math.random()*chars.length)];
    document.getElementById('new-pass').value = pass;
}
// Chiudi modal cliccando fuori
['modal-nuovo','modal-modifica'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
