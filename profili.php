<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();

$messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione'])) {
    if ($_POST['azione'] === 'crea_profilo') {
        $nome          = trim($_POST['nome']);
        $banner_attivo = isset($_POST['banner_attivo']) ? 1 : 0;
        if ($nome) {
            $stmt = $db->prepare('INSERT INTO profili (nome, banner_attivo) VALUES (?, ?)');
            $stmt->execute([$nome, $banner_attivo]);
            $messaggio = 'ok|Profilo creato!';
        }
    }

    if ($_POST['azione'] === 'aggiungi_regola') {
        $profilo_id  = intval($_POST['profilo_id']);
        $playlist_id = intval($_POST['playlist_id']);
        $intervallo  = intval($_POST['intervallo_minuti']);
        $giorni      = isset($_POST['giorni']) ? implode(',', array_map('intval', $_POST['giorni'])) : '';
        if ($giorni && $playlist_id) {
            $stmt = $db->prepare('INSERT INTO profilo_regole (profilo_id, playlist_id, intervallo_minuti, giorni) VALUES (?, ?, ?, ?)');
            $stmt->execute([$profilo_id, $playlist_id, $intervallo, $giorni]);
            $messaggio = 'ok|Regola aggiunta!';
        } else {
            $messaggio = 'errore|Seleziona almeno un giorno e una playlist.';
        }
    }
}

if (isset($_GET['elimina_profilo'])) {
    $id = intval($_GET['elimina_profilo']);
    $db->exec("DELETE FROM profili WHERE id = $id");
    header('Location: /profili.php');
    exit;
}

if (isset($_GET['elimina_regola'])) {
    $id = intval($_GET['elimina_regola']);
    $p  = intval($_GET['p'] ?? 0);
    $db->exec("DELETE FROM profilo_regole WHERE id = $id");
    header('Location: /profili.php' . ($p ? '?p=' . $p : ''));
    exit;
}

$profilo_attivo = isset($_GET['p']) ? intval($_GET['p']) : null;
$profili        = $db->query('SELECT * FROM profili ORDER BY creato_il DESC')->fetchAll(PDO::FETCH_ASSOC);
$playlists      = $db->query('SELECT * FROM playlist ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);

$regole = [];
if ($profilo_attivo) {
    $regole = $db->query("
        SELECT pr.*, p.nome as playlist_nome
        FROM profilo_regole pr
        JOIN playlist p ON p.id = pr.playlist_id
        WHERE pr.profilo_id = $profilo_attivo
        ORDER BY pr.id
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$giorni_nomi = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom'];

$titolo = 'Profili';
require_once 'includes/header.php';
?>

<div class="container" style="display:grid; grid-template-columns:300px 1fr; gap:24px;">

    <!-- Colonna sinistra -->
    <div>
        <?php if ($messaggio):
            [$tipo_msg, $testo_msg] = explode('|', $messaggio);
        ?>
        <div class="messaggio <?php echo $tipo_msg; ?>"><?php echo $testo_msg; ?></div>
        <?php endif; ?>

        <div class="box">
            <h2>Nuovo Profilo</h2>
            <form method="POST">
                <input type="hidden" name="azione" value="crea_profilo">
                <label>Nome profilo</label>
                <input type="text" name="nome" placeholder="Es. Palestra Standard" required>
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                    <input type="checkbox" name="banner_attivo" id="banner_attivo" checked style="width:auto; margin:0;">
                    <label for="banner_attivo" style="margin:0;">Banner H24 attivo</label>
                </div>
                <button type="submit" class="btn btn-full">+ Crea Profilo</button>
            </form>
        </div>

        <div class="box">
            <h2>Profili salvati</h2>
            <?php if (empty($profili)): ?>
                <div class="vuoto">Nessun profilo ancora.</div>
            <?php else: ?>
                <?php foreach ($profili as $pr): ?>
                <div style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
                    <a href="/profili.php?p=<?php echo $pr['id']; ?>"
                       style="flex:1; display:flex; align-items:center; padding:10px 14px;
                              background:#0f3460; border-radius:6px; font-size:14px;
                              text-decoration:none; color:#eee;
                              border-left:3px solid <?php echo $profilo_attivo == $pr['id'] ? '#e94560' : 'transparent'; ?>;">
                        🎛️ <?php echo htmlspecialchars($pr['nome']); ?>
                    </a>
                    <a href="/profili.php?elimina_profilo=<?php echo $pr['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Eliminare questo profilo?')">✕</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Colonna destra -->
    <div>
        <?php if ($profilo_attivo):
            $pr_corrente = array_values(array_filter($profili, fn($p) => $p['id'] == $profilo_attivo))[0];
        ?>

        <div class="box">
            <h2>🎛️ <?php echo htmlspecialchars($pr_corrente['nome']); ?></h2>
            <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:16px;">
                <div style="display:flex; align-items:center; gap:10px; padding:10px 14px; background:#0f3460; border-radius:6px; font-size:13px;">
                    <div style="width:10px; height:10px; border-radius:50%; background:#2ecc71;"></div>
                    <span><strong>Layer 3 — Banner:</strong> <?php echo $pr_corrente['banner_attivo'] ? 'H24 attivo ✅' : 'Disattivato ❌'; ?></span>
                </div>
                <div style="display:flex; align-items:center; gap:10px; padding:10px 14px; background:#0f3460; border-radius:6px; font-size:13px;">
                    <div style="width:10px; height:10px; border-radius:50%; background:#5dade2;"></div>
                    <span><strong>Layer 1 — TV:</strong> H24 attivo ✅</span>
                </div>
                <div style="display:flex; align-items:center; gap:10px; padding:10px 14px; background:#0f3460; border-radius:6px; font-size:13px;">
                    <div style="width:10px; height:10px; border-radius:50%; background:#c39bd3;"></div>
                    <span><strong>Layer 2 — Pubblicità:</strong> Gestita dalle regole</span>
                </div>
            </div>
        </div>

        <div class="box">
            <h2>+ Aggiungi Regola Pubblicità</h2>
            <form method="POST">
                <input type="hidden" name="azione" value="aggiungi_regola">
                <input type="hidden" name="profilo_id" value="<?php echo $profilo_attivo; ?>">

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label>Playlist</label>
                        <select name="playlist_id">
                            <option value="">-- Seleziona playlist --</option>
                            <?php foreach ($playlists as $pl): ?>
                            <option value="<?php echo $pl['id']; ?>"><?php echo htmlspecialchars($pl['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Vai in onda ogni (minuti)</label>
                        <input type="number" name="intervallo_minuti" value="20" min="1" max="480">
                    </div>
                </div>

                <label>Giorni di messa in onda</label>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
                    <?php foreach ($giorni_nomi as $num => $nome): ?>
                    <div>
                        <input type="checkbox" name="giorni[]" value="<?php echo $num; ?>"
                               id="g<?php echo $num; ?>" checked
                               style="display:none;">
                        <label for="g<?php echo $num; ?>"
                               style="padding:6px 12px; background:#0f3460; border:1px solid #1a4a7a;
                                      border-radius:6px; font-size:13px; cursor:pointer; user-select:none;"
                               onclick="this.previousElementSibling.checked = !this.previousElementSibling.checked;
                                        this.style.background = this.previousElementSibling.checked ? '#e94560' : '#0f3460';
                                        this.style.borderColor = this.previousElementSibling.checked ? '#e94560' : '#1a4a7a';">
                            <?php echo $nome; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn">+ Aggiungi Regola</button>
            </form>
        </div>

        <div class="box">
            <h2>Regole configurate (<?php echo count($regole); ?>)</h2>
            <?php if (empty($regole)): ?>
                <div class="vuoto">Nessuna regola ancora. Aggiungine una!</div>
            <?php else: ?>
                <?php foreach ($regole as $r):
                    $giorni_attivi = explode(',', $r['giorni']);
                ?>
                <div style="background:#0f3460; border-radius:8px; padding:16px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <div style="font-size:15px; font-weight:bold;">📋 <?php echo htmlspecialchars($r['playlist_nome']); ?></div>
                        <a href="/profili.php?elimina_regola=<?php echo $r['id']; ?>&p=<?php echo $profilo_attivo; ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Eliminare questa regola?')">✕</a>
                    </div>
                    <div style="font-size:13px; color:#aaa; margin-bottom:8px;">
                        🔁 Va in onda ogni <strong style="color:#eee;"><?php echo $r['intervallo_minuti']; ?> minuti</strong>
                    </div>
                    <div style="display:flex; gap:4px; flex-wrap:wrap;">
                        <?php foreach ($giorni_nomi as $num => $nome): ?>
                            <?php if (in_array($num, $giorni_attivi)): ?>
                            <span style="padding:3px 8px; border-radius:20px; font-size:11px; font-weight:bold;
                                         background:<?php echo $num >= 6 ? '#3d1a5c' : '#1a3a5c'; ?>;
                                         color:<?php echo $num >= 6 ? '#c39bd3' : '#5dade2'; ?>;">
                                <?php echo $nome; ?>
                            </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="box">
            <div class="vuoto">👈 Seleziona un profilo per configurarlo</div>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>