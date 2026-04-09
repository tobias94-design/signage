<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();

$messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file       = $_FILES['file'];
    $nome       = trim($_POST['nome']);
    $estensione = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $tipi_video    = ['mp4', 'webm'];
    $tipi_immagine = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($estensione, $tipi_video)) {
        $tipo   = 'video';
        // Legge la durata reale inviata dal browser via JavaScript
        $durata = intval($_POST['durata_video']) ?: 30;
    } elseif (in_array($estensione, $tipi_immagine)) {
        $tipo   = 'immagine';
        $durata = intval($_POST['durata']) ?: 10;
    } else {
        $messaggio = 'errore|Formato non supportato. Usa MP4, WEBM, JPG, PNG.';
    }

    if (!$messaggio) {
        $filename     = uniqid() . '.' . $estensione;
        $destinazione = __DIR__ . '/uploads/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $destinazione)) {
            $ins_id = (int)($_POST['inserzionista_id'] ?? 0) ?: null;
            $stmt = $db->prepare('INSERT INTO contenuti (nome, tipo, file, durata, inserzionista_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$nome, $tipo, $filename, $durata, $ins_id]);
            $messaggio = 'ok|Contenuto caricato con successo!';
        } else {
            $messaggio = 'errore|Errore durante il caricamento del file.';
        }
    }
}

if (isset($_GET['elimina'])) {
    $id  = intval($_GET['elimina']);
    $row = $db->query("SELECT file FROM contenuti WHERE id = $id")->fetch();
    if ($row) {
        @unlink(__DIR__ . '/uploads/' . $row['file']);
        $db->exec("DELETE FROM contenuti WHERE id = $id");
    }
    header('Location: /contenuti.php');
    exit;
}

$contenuti = $db->query('SELECT c.*, i.ragione_sociale FROM contenuti c LEFT JOIN inserzionisti i ON i.id = c.inserzionista_id ORDER BY c.creato_il DESC')->fetchAll(PDO::FETCH_ASSOC);
$inserzionisti = $db->query("SELECT id, ragione_sociale FROM inserzionisti WHERE attivo=1 ORDER BY ragione_sociale")->fetchAll(PDO::FETCH_ASSOC);
$titolo    = 'Contenuti';
require_once 'includes/header.php';
?>

<div class="container">

    <?php if ($messaggio):
        [$tipo_msg, $testo_msg] = explode('|', $messaggio);
    ?>
    <div class="messaggio <?php echo $tipo_msg; ?>"><?php echo $testo_msg; ?></div>
    <?php endif; ?>

    <div class="box">
        <h2>Carica nuovo contenuto</h2>
        <form method="POST" enctype="multipart/form-data" onsubmit="return checkDurata()">
            <div class="form-grid">
                <div>
                    <label>Nome contenuto</label>
                    <input type="text" name="nome" placeholder="Es. Spot gennaio" required>
                </div>
                <div id="campo-durata">
                    <label>Durata (secondi)</label>
                    <input type="number" name="durata" value="10" min="1" max="300">
                </div>
                <div>
                    <label>Inserzionista</label>
                    <select name="inserzionista_id">
                        <option value="">— Nessuno —</option>
                        <?php foreach ($inserzionisti as $i): ?>
                        <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['ragione_sociale']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>File (video o immagine)</label>
                    <input type="file" name="file" id="fileInput" accept="video/*,image/*" required>
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button type="submit" class="btn">Carica</button>
                </div>
            </div>
        </form>
    </div>

    <div class="box">
        <h2>Contenuti caricati (<?php echo count($contenuti); ?>)</h2>
        <?php if (empty($contenuti)): ?>
            <div class="vuoto">Nessun contenuto ancora caricato.</div>
        <?php else: ?>
        <table>
            <tr>
                <th>Preview</th>
                <th>Nome</th>
                <th>Tipo</th>
                <th>Inserzionista</th>
                <th>Durata</th>
                <th>Caricato il</th>
                <th>Azioni</th>
            </tr>
            <?php foreach ($contenuti as $c): ?>
            <tr>
                <td>
                    <?php if ($c['tipo'] === 'immagine'): ?>
                        <img src="/uploads/<?php echo $c['file']; ?>" class="preview" alt="">
                    <?php else: ?>
                        <video src="/uploads/<?php echo $c['file']; ?>" class="preview" muted></video>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($c['nome']); ?></td>
                <td><span class="badge badge-<?php echo $c['tipo']; ?>"><?php echo strtoupper($c['tipo']); ?></span></td>
                <td>
                    <?php if ($c['ragione_sociale']): ?>
                    <a href="/report_adv.php?ins=<?= $c['inserzionista_id'] ?>" style="color:var(--sg-orange);font-size:12px;"><?= htmlspecialchars($c['ragione_sociale']) ?></a>
                    <?php else: ?>
                    <span style="color:var(--sg-muted);font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td><?php echo $c['tipo'] === 'video' ? '▶ intero' : $c['durata'] . 's'; ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($c['creato_il'])); ?></td>
                <td>
                    <a href="/contenuti.php?elimina=<?php echo $c['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Eliminare questo contenuto?')">Elimina</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>

</div>

<style>
.form-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; align-items: end; }
.preview { width: 80px; height: 50px; object-fit: cover; border-radius: 4px; }
</style>

<script>
function checkDurata() {
    const fileInput = document.getElementById('fileInput');
    const file = fileInput.files[0];
    if (!file) return true;
    const ext = file.name.split('.').pop().toLowerCase();
    if (['mp4', 'webm'].includes(ext)) {
        const hidden = document.getElementById('durata_video_hidden');
        if (!hidden || !hidden.value || hidden.value === '0') {
            alert('Attendi che il video venga analizzato prima di inviare.');
            return false;
        }
    }
    return true;
}

document.getElementById('fileInput').addEventListener('change', function() {
    const file   = this.files[0];
    const campo  = document.getElementById('campo-durata');
    if (!file) return;
    const ext    = file.name.split('.').pop().toLowerCase();
    const isVideo = ['mp4', 'webm'].includes(ext);
    campo.style.display = isVideo ? 'none' : 'block';

    if (isVideo) {
        const video = document.createElement('video');
        video.preload = 'metadata';
        video.onloadedmetadata = function() {
            URL.revokeObjectURL(video.src);
            let hidden = document.getElementById('durata_video_hidden');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'durata_video';
                hidden.id = 'durata_video_hidden';
                document.querySelector('form').appendChild(hidden);
            }
            hidden.value = Math.ceil(video.duration);
            console.log('Durata video rilevata:', hidden.value, 'secondi');
        };
        video.src = URL.createObjectURL(file);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
