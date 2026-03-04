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
        $ordine       = intval($db->query("SELECT COUNT(*) FROM playlist_items WHERE playlist_id = $playlist_id")->fetchColumn());
        $stmt         = $db->prepare('INSERT INTO playlist_items (playlist_id, contenuto_id, ordine) VALUES (?, ?, ?)');
        $stmt->execute([$playlist_id, $contenuto_id, $ordine]);
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
            $pl_corrente  = array_values(array_filter($playlists, fn($p) => $p['id'] == $playlist_attiva))[0];
            $durata_totale = array_sum(array_column($items, 'durata'));
        ?>
        <div class="box">
            <h2>📋 <?php echo htmlspecialchars($pl_corrente['nome']); ?></h2>
            <div style="font-size:13px; color:#aaa; margin-bottom:16px;">
                Durata totale: <span style="color:#e94560; font-weight:bold;"><?php echo gmdate('i:s', $durata_totale); ?></span>
                (<?php echo $durata_totale; ?> secondi)
            </div>

            <form method="POST" style="display:flex; gap:10px; margin-bottom:20px;">
                <input type="hidden" name="azione" value="aggiungi_item">
                <input type="hidden" name="playlist_id" value="<?php echo $playlist_attiva; ?>">
                <select name="contenuto_id" style="margin-bottom:0; flex:1;">
                    <?php foreach ($contenuti as $c): ?>
                    <option value="<?php echo $c['id']; ?>">
                        <?php echo htmlspecialchars($c['nome']); ?> (<?php echo $c['durata']; ?>s)
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn" style="white-space:nowrap;">+ Aggiungi</button>
            </form>

            <?php if (empty($items)): ?>
                <div class="vuoto">Nessun contenuto. Aggiungine uno!</div>
            <?php else: ?>
                <?php foreach ($items as $i => $item): ?>
                <div style="display:flex; align-items:center; gap:12px; padding:10px;
                            background:#0f3460; border-radius:6px; margin-bottom:8px;">
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
                        <div style="font-size:14px;"><?php echo htmlspecialchars($item['nome']); ?></div>
                        <div style="font-size:12px; color:#aaa; margin-top:2px;">
                            <span class="badge badge-<?php echo $item['tipo']; ?>"><?php echo strtoupper($item['tipo']); ?></span>
                            &nbsp;<?php echo $item['durata']; ?> secondi
                        </div>
                    </div>
                    <a href="/playlist.php?elimina_item=<?php echo $item['id']; ?>&p=<?php echo $playlist_attiva; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Rimuovere questo contenuto?')">✕</a>
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