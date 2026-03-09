<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();

$messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione'])) {
    if ($_POST['azione'] === 'crea_playlist') {
        $nome = trim($_POST['nome']);
        if ($nome) {
            $stmt = $db->prepare('INSERT INTO playlist (nome) VALUES (?)');
            $stmt->execute([$nome]);
            $messaggio = 'ok|Playlist creata!';
        }
    }

    if ($_POST['azione'] === 'aggiungi_item') {
        $playlist_id  = intval($_POST['playlist_id']);
        $contenuto_id = intval($_POST['contenuto_id']);
        $data_inizio  = !empty($_POST['data_inizio']) ? $_POST['data_inizio'] : null;
        $data_fine    = !empty($_POST['data_fine'])   ? $_POST['data_fine']   : null;
        $ordine       = intval($db->query("SELECT COUNT(*) FROM playlist_items WHERE playlist_id = $playlist_id")->fetchColumn());
        $stmt = $db->prepare('INSERT INTO playlist_items (playlist_id, contenuto_id, ordine, data_inizio, data_fine) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$playlist_id, $contenuto_id, $ordine, $data_inizio, $data_fine]);
        $messaggio = 'ok|Contenuto aggiunto!';
    }
}

if (isset($_GET['elimina_playlist'])) {
    $id = intval($_GET['elimina_playlist']);
    $db->exec("DELETE FROM playlist WHERE id = $id");
    header('Location: /playlist.php');
    exit;
}

if (isset($_GET['elimina_item'])) {
    $id = intval($_GET['elimina_item']);
    $db->exec("DELETE FROM playlist_items WHERE id = $id");
    header('Location: /playlist.php' . (isset($_GET['p']) ? '?p=' . intval($_GET['p']) : ''));
    exit;
}

$playlist_attiva = isset($_GET['p']) ? intval($_GET['p']) : null;
$playlists       = $db->query('SELECT * FROM playlist ORDER BY creato_il DESC')->fetchAll(PDO::FETCH_ASSOC);
$contenuti       = $db->query('SELECT * FROM contenuti ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);

$items = [];
if ($playlist_attiva) {
    $items = $db->query("
        SELECT pi.*, c.nome, c.tipo, c.file, c.durata
        FROM playlist_items pi
        JOIN contenuti c ON c.id = pi.contenuto_id
        WHERE pi.playlist_id = $playlist_attiva
        ORDER BY pi.ordine
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$oggi = date('Y-m-d');

function isScaduto($item, $oggi) {
    return !empty($item['data_fine']) && $item['data_fine'] < $oggi;
}
function isNonAncoraAttivo($item, $oggi) {
    return !empty($item['data_inizio']) && $item['data_inizio'] > $oggi;
}

$titolo = 'Playlist';
require_once 'includes/header.php';
?>

<div class="container" style="display:grid; grid-template-columns:280px 1fr; gap:24px;">

    <!-- Colonna sinistra -->
    <div>
        <?php if ($messaggio):
            [$tipo_msg, $testo_msg] = explode('|', $messaggio);
        ?>
        <div class="messaggio <?php echo $tipo_msg; ?>"><?php echo $testo_msg; ?></div>
        <?php endif; ?>

        <div class="box">
            <h2>Nuova Playlist</h2>
            <form method="POST">
                <input type="hidden" name="azione" value="crea_playlist">
                <input type="text" name="nome" placeholder="Nome playlist..." required>
                <button type="submit" class="btn btn-full">+ Crea</button>
            </form>
        </div>

        <div class="box">
            <h2>Le tue Playlist</h2>
            <?php if (empty($playlists)): ?>
                <div class="vuoto">Nessuna playlist ancora.</div>
            <?php else: ?>
                <?php foreach ($playlists as $pl): ?>
                <div style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
                    <a href="/playlist.php?p=<?php echo $pl['id']; ?>"
                       style="flex:1; display:flex; align-items:center; padding:10px 14px;
                              background:#0f3460; border-radius:6px; font-size:14px;
                              text-decoration:none; color:#eee;
                              border-left:3px solid <?php echo $playlist_attiva == $pl['id'] ? '#e94560' : 'transparent'; ?>;">
                        📋 <?php echo htmlspecialchars($pl['nome']); ?>
                    </a>
                    <a href="/playlist.php?elimina_playlist=<?php echo $pl['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Eliminare questa playlist?')">✕</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Colonna destra -->
    <div>
        <?php if ($playlist_attiva):
            $pl_corrente   = array_values(array_filter($playlists, fn($p) => $p['id'] == $playlist_attiva))[0];
            $items_attivi  = array_filter($items, fn($it) => !isScaduto($it, $oggi) && !isNonAncoraAttivo($it, $oggi));
            $durata_totale = array_sum(array_map(fn($it) => $it['tipo'] === 'video' ? 30 : $it['durata'], $items_attivi));
        ?>
        <div class="box">
            <h2>📋 <?php echo htmlspecialchars($pl_corrente['nome']); ?></h2>
            <div style="font-size:13px; color:#aaa; margin-bottom:16px;">
                Durata totale (attivi): <span style="color:#e94560; font-weight:bold;"><?php echo gmdate('i:s', $durata_totale); ?></span>
            </div>

            <!-- Form aggiunta con date opzionali -->
            <form method="POST" style="margin-bottom:20px;">
                <input type="hidden" name="azione" value="aggiungi_item">
                <input type="hidden" name="playlist_id" value="<?php echo $playlist_attiva; ?>">
                <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                    <div style="flex:2; min-width:180px;">
                        <label style="font-size:12px; color:#aaa; display:block; margin-bottom:4px;">Contenuto</label>
                        <select name="contenuto_id" style="margin-bottom:0;">
                            <?php foreach ($contenuti as $c): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['nome']); ?>
                                (<?php echo $c['tipo'] === 'video' ? '▶ intero' : $c['durata'] . 's'; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1; min-width:130px;">
                        <label style="font-size:12px; color:#aaa; display:block; margin-bottom:4px;">Dal (opzionale)</label>
                        <input type="date" name="data_inizio" style="margin-bottom:0;">
                    </div>
                    <div style="flex:1; min-width:130px;">
                        <label style="font-size:12px; color:#aaa; display:block; margin-bottom:4px;">Al (opzionale)</label>
                        <input type="date" name="data_fine" style="margin-bottom:0;">
                    </div>
                    <div>
                        <button type="submit" class="btn" style="white-space:nowrap;">+ Aggiungi</button>
                    </div>
                </div>
            </form>

            <?php if (empty($items)): ?>
                <div class="vuoto">Nessun contenuto. Aggiungine uno!</div>
            <?php else: ?>
                <?php foreach ($items as $i => $item):
                    $scaduto        = isScaduto($item, $oggi);
                    $nonAncoraAttivo = isNonAncoraAttivo($item, $oggi);
                    $disattivo      = $scaduto || $nonAncoraAttivo;
                    $opacita        = $disattivo ? '0.35' : '1';
                    $durataLabel    = $item['tipo'] === 'video' ? '▶ intero' : $item['durata'] . ' secondi';
                ?>
                <div style="display:flex; align-items:center; gap:12px; padding:10px;
                            background:#0f3460; border-radius:6px; margin-bottom:8px;
                            opacity:<?php echo $opacita; ?>; position:relative;">

                    <div style="font-size:18px; font-weight:bold; color:#e94560; width:24px; text-align:center;">
                        <?php echo $i + 1; ?>
                    </div>

                    <?php if ($item['tipo'] === 'immagine'): ?>
                        <img src="/uploads/<?php echo $item['file']; ?>"
                             style="width:60px; height:40px; object-fit:cover; border-radius:4px;">
                    <?php else: ?>
                        <video src="/uploads/<?php echo $item['file']; ?>"
                               style="width:60px; height:40px; object-fit:cover; border-radius:4px;" muted></video>
                    <?php endif; ?>

                    <div style="flex:1;">
                        <div style="font-size:14px; display:flex; align-items:center; gap:8px;">
                            <?php echo htmlspecialchars($item['nome']); ?>
                            <?php if ($scaduto): ?>
                                <span style="font-size:11px; background:#7f1d1d; color:#fca5a5; padding:2px 6px; border-radius:4px;">SCADUTO</span>
                            <?php elseif ($nonAncoraAttivo): ?>
                                <span style="font-size:11px; background:#1c3a6e; color:#93c5fd; padding:2px 6px; border-radius:4px;">IN ATTESA</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:12px; color:#aaa; margin-top:4px; display:flex; gap:10px; flex-wrap:wrap;">
                            <span>
                                <span class="badge badge-<?php echo $item['tipo']; ?>"><?php echo strtoupper($item['tipo']); ?></span>
                                &nbsp;<?php echo $durataLabel; ?>
                            </span>
                            <?php if ($item['data_inizio'] || $item['data_fine']): ?>
                            <span style="color:#888;">
                                📅
                                <?php echo $item['data_inizio'] ? date('d/m/Y', strtotime($item['data_inizio'])) : '∞'; ?>
                                →
                                <?php echo $item['data_fine'] ? date('d/m/Y', strtotime($item['data_fine'])) : '∞'; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <a href="/playlist.php?elimina_item=<?php echo $item['id']; ?>&p=<?php echo $playlist_attiva; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Rimuovere questo contenuto?')"
                       style="opacity:1;">✕</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="box">
            <div class="vuoto">👈 Seleziona una playlist per gestirne i contenuti</div>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>