<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();

$messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione'])) {
    if ($_POST['azione'] === 'crea_dispositivo') {
        $nome       = trim($_POST['nome']);
        $club       = trim($_POST['club']);
        $profilo_id = !empty($_POST['profilo_id']) ? intval($_POST['profilo_id']) : null;
        $layout     = $_POST['layout'] ?? 'standard';
        $token      = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $club)) . '-' . substr(md5(uniqid()), 0, 6);
        if ($nome && $club) {
            $stmt = $db->prepare('INSERT INTO dispositivi (nome, club, token, profilo_id, layout) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$nome, $club, $token, $profilo_id, $layout]);
            $messaggio = 'ok|Dispositivo creato!';
        }
    }

    if ($_POST['azione'] === 'assegna_profilo') {
        $profilo_id = intval($_POST['profilo_id']);
        $layout     = $_POST['layout'] ?? '';
        $ids        = $_POST['dispositivi_ids'] ?? [];
        if (!empty($ids) && $profilo_id) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params       = array_merge([$profilo_id], array_map('intval', $ids));
            $stmt         = $db->prepare("UPDATE dispositivi SET profilo_id = ? WHERE id IN ($placeholders)");
            $stmt->execute($params);
            // Aggiorna layout se selezionato
            if ($layout) {
                $params2 = array_merge([$layout], array_map('intval', $ids));
                $stmt2   = $db->prepare("UPDATE dispositivi SET layout = ? WHERE id IN ($placeholders)");
                $stmt2->execute($params2);
            }
            $messaggio = 'ok|Profilo assegnato a ' . count($ids) . ' dispositivo/i!';
        } else {
            $messaggio = 'errore|Seleziona almeno un dispositivo e un profilo.';
        }
    }
}

if (isset($_GET['elimina'])) {
    $id = intval($_GET['elimina']);
    $db->exec("DELETE FROM dispositivi WHERE id = $id");
    header('Location: /dispositivi.php');
    exit;
}

$dispositivi = $db->query("
    SELECT d.*, p.nome as profilo_nome
    FROM dispositivi d
    LEFT JOIN profili p ON p.id = d.profilo_id
    ORDER BY d.club
")->fetchAll(PDO::FETCH_ASSOC);

$profili = $db->query('SELECT * FROM profili ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);

$titolo = 'Dispositivi';
require_once 'includes/header.php';
?>

<div class="container">

    <?php if ($messaggio):
        [$tipo_msg, $testo_msg] = explode('|', $messaggio);
    ?>
    <div class="messaggio <?php echo $tipo_msg; ?>"><?php echo $testo_msg; ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:320px 1fr; gap:24px;">

        <!-- Colonna sinistra -->
        <div>
            <div class="box">
                <h2>Nuovo Dispositivo</h2>
                <form method="POST">
                    <input type="hidden" name="azione" value="crea_dispositivo">
                    <label>Nome dispositivo</label>
                    <input type="text" name="nome" placeholder="Es. TV Sala Pesi" required>
                    <label>Club</label>
                    <input type="text" name="club" placeholder="Es. Milano Centro" required>
                    <label>Profilo (opzionale)</label>
                    <select name="profilo_id">
                        <option value="">-- Assegna dopo --</option>
                        <?php foreach ($profili as $pr): ?>
                        <option value="<?php echo $pr['id']; ?>"><?php echo htmlspecialchars($pr['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Layout player</label>
                    <select name="layout">
                        <option value="standard">📺 Standard (solo TV + ADV)</option>
                        <option value="corsi">📋 Con colonna corsi fitness</option>
                    </select>
                    <button type="submit" class="btn btn-full">+ Aggiungi Dispositivo</button>
                </form>
            </div>
        </div>

        <!-- Colonna destra -->
        <div>
            <?php if (!empty($dispositivi)): ?>

            <form method="POST">
                <input type="hidden" name="azione" value="assegna_profilo">

                <!-- Assegnazione rapida -->
                <div style="background:#16213e; border:2px solid #e94560; border-radius:10px;
                            padding:20px 24px; margin-bottom:24px;">
                    <div style="font-size:14px; color:#e94560; font-weight:bold; margin-bottom:14px;">
                        ⚡ Assegnazione rapida ai selezionati
                    </div>
                    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                        <select name="profilo_id" style="margin:0; flex:1;">
                            <option value="">-- Seleziona profilo --</option>
                            <?php foreach ($profili as $pr): ?>
                            <option value="<?php echo $pr['id']; ?>"><?php echo htmlspecialchars($pr['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="layout" style="margin:0; flex:1;">
                            <option value="">-- Layout invariato --</option>
                            <option value="standard">📺 Standard</option>
                            <option value="corsi">📋 Con corsi fitness</option>
                        </select>
                        <button type="submit" class="btn">Assegna</button>
                    </div>
                </div>

                <!-- Controlli selezione -->
                <div style="display:flex; gap:10px; margin-bottom:16px;">
                    <button type="button" onclick="selezionaTutti()"
                            style="background:none; border:none; color:#aaa; font-size:13px; cursor:pointer; text-decoration:underline;">
                        Seleziona tutti
                    </button>
                    <button type="button" onclick="deselezionaTutti()"
                            style="background:none; border:none; color:#aaa; font-size:13px; cursor:pointer; text-decoration:underline;">
                        Deseleziona tutti
                    </button>
                </div>

                <?php foreach ($dispositivi as $d):
                    $online = $d['stato'] === 'online' &&
                              $d['ultimo_ping'] &&
                              strtotime($d['ultimo_ping']) > strtotime('-2 minutes');
                    $layout = $d['layout'] ?? 'standard';
                    $player_url = '/player/' . ($layout === 'corsi' ? 'corsi' : 'index') . '.php?token=' . $d['token'];
                ?>
                <div id="card-<?php echo $d['id']; ?>"
                     style="background:#0f3460; border-radius:10px; padding:18px 20px;
                            margin-bottom:12px; display:flex; align-items:center; gap:16px;
                            border-left:4px solid #1a4a7a; transition:border-color 0.2s;">
                    <input type="checkbox" class="check" name="dispositivi_ids[]"
                           value="<?php echo $d['id']; ?>"
                           onclick="toggleCard(<?php echo $d['id']; ?>)"
                           style="width:18px; height:18px; accent-color:#e94560; cursor:pointer;">
                    <div style="width:10px; height:10px; border-radius:50%;
                                background:<?php echo $online ? '#2ecc71' : '#e74c3c'; ?>; flex-shrink:0;">
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:15px; font-weight:bold;"><?php echo htmlspecialchars($d['nome']); ?></div>
                        <div style="font-size:13px; color:#aaa; margin-top:2px;">🏋️ <?php echo htmlspecialchars($d['club']); ?></div>
                        <div style="font-size:11px; color:#555; margin-top:4px; font-family:monospace;"><?php echo $d['token']; ?></div>
                    </div>
                    <?php if ($d['profilo_nome']): ?>
                        <span style="font-size:12px; padding:4px 10px; border-radius:20px;
                                     background:#1a3d2b; color:#2ecc71; white-space:nowrap;">
                            🎛️ <?php echo htmlspecialchars($d['profilo_nome']); ?>
                        </span>
                    <?php else: ?>
                        <span style="font-size:12px; padding:4px 10px; border-radius:20px;
                                     background:#3d1a1a; color:#e74c3c; white-space:nowrap;">
                            Nessun profilo
                        </span>
                    <?php endif; ?>
                    <span style="font-size:12px; padding:4px 10px; border-radius:20px;
                                 background:#1a2a3a; color:#aaa; white-space:nowrap;">
                        <?php echo $layout === 'corsi' ? '📋 Corsi' : '📺 Standard'; ?>
                    </span>
                    <a href="<?php echo $player_url; ?>"
                       target="_blank" class="btn btn-sm">▶</a>
                    <a href="/dispositivi.php?elimina=<?php echo $d['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Eliminare questo dispositivo?')">✕</a>
                </div>
                <?php endforeach; ?>

            </form>

            <?php else: ?>
            <div class="box">
                <div class="vuoto">Nessun dispositivo ancora. Aggiungine uno!</div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function toggleCard(id) {
    const card = document.getElementById('card-' + id);
    const checked = card.querySelector('.check').checked;
    card.style.borderLeftColor = checked ? '#e94560' : '#1a4a7a';
}
function selezionaTutti() {
    document.querySelectorAll('.check').forEach(c => {
        c.checked = true;
        document.getElementById('card-' + c.value).style.borderLeftColor = '#e94560';
    });
}
function deselezionaTutti() {
    document.querySelectorAll('.check').forEach(c => {
        c.checked = false;
        document.getElementById('card-' + c.value).style.borderLeftColor = '#1a4a7a';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>