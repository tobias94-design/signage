<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();

$messaggio = '';

// ── UPLOAD SFONDO ──────────────────────────────────────────────────
function uploadSfondo($file) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) return '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) return '';
    $nome = 'sidebar_' . uniqid() . '.' . $ext;
    $dest = __DIR__ . '/uploads/' . $nome;
    if (move_uploaded_file($file['tmp_name'], $dest)) return $nome;
    return '';
}

// ── AZIONI POST ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione'])) {

    if ($_POST['azione'] === 'aggiungi_slide') {
        $profilo_id   = intval($_POST['profilo_id']);
        $tipo         = $_POST['tipo'] ?? 'info';
        $titolo       = trim($_POST['titolo'] ?? '');
        $durata       = max(3, intval($_POST['durata'] ?? 10));
        $colore_sf    = $_POST['colore_sfondo']  ?? '#111111';
        $colore_tx    = $_POST['colore_testo']   ?? '#ffffff';
        $sfondo       = uploadSfondo($_FILES['sfondo'] ?? null);

        // Costruisce JSON contenuto in base al tipo
        $contenuto = [];
        switch ($tipo) {
            case 'countdown':
                $contenuto = [
                    'data_target'    => $_POST['ct_data_target']    ?? '',
                    'messaggio_post' => $_POST['ct_messaggio_post'] ?? 'Evento in corso!',
                    'mostra_giorni'  => isset($_POST['ct_mostra_giorni']) ? 1 : 0,
                ];
                break;
            case 'meteo':
                $contenuto = [
                    'citta'    => trim($_POST['mt_citta'] ?? 'Milano'),
                    'lat'      => trim($_POST['mt_lat']   ?? ''),
                    'lon'      => trim($_POST['mt_lon']   ?? ''),
                ];
                break;
            case 'info':
                $contenuto = [
                    'testo' => trim($_POST['info_testo'] ?? ''),
                    'icona' => trim($_POST['info_icona'] ?? 'ℹ️'),
                ];
                break;
            case 'corsi':
                $contenuto = [];
                break;
        }

        $ordine = intval($db->query("SELECT COUNT(*) FROM sidebar_slides WHERE profilo_id=$profilo_id")->fetchColumn());
        $db->prepare('INSERT INTO sidebar_slides (profilo_id, tipo, titolo, contenuto, durata, ordine, sfondo, colore_sfondo, colore_testo, attivo) VALUES (?,?,?,?,?,?,?,?,?,1)')
           ->execute([$profilo_id, $tipo, $titolo, json_encode($contenuto), $durata, $ordine, $sfondo, $colore_sf, $colore_tx]);
        $messaggio = 'ok|Slide aggiunta!';
    }

    if ($_POST['azione'] === 'toggle_attivo') {
        $id  = intval($_POST['id']);
        $val = intval($_POST['attivo']);
        $db->prepare('UPDATE sidebar_slides SET attivo=? WHERE id=?')->execute([$val, $id]);
        echo 'ok'; exit;
    }

    if ($_POST['azione'] === 'riordina') {
        $ids = json_decode($_POST['ordine'], true);
        foreach ($ids as $pos => $id) {
            $db->prepare('UPDATE sidebar_slides SET ordine=? WHERE id=?')->execute([$pos, intval($id)]);
        }
        echo 'ok'; exit;
    }
}

if (isset($_GET['elimina'])) {
    $id = intval($_GET['elimina']);
    $p  = intval($_GET['p'] ?? 0);
    // Elimina anche l'eventuale sfondo
    $row = $db->query("SELECT sfondo FROM sidebar_slides WHERE id=$id")->fetch();
    if ($row && $row['sfondo']) @unlink(__DIR__ . '/uploads/' . $row['sfondo']);
    $db->exec("DELETE FROM sidebar_slides WHERE id=$id");
    header('Location: /sidebar.php' . ($p ? '?p='.$p : '')); exit;
}

$profilo_attivo = isset($_GET['p']) ? intval($_GET['p']) : null;
$profili   = $db->query('SELECT * FROM profili ORDER BY creato_il DESC')->fetchAll(PDO::FETCH_ASSOC);
$slides    = [];
if ($profilo_attivo) {
    $slides = $db->query("SELECT * FROM sidebar_slides WHERE profilo_id=$profilo_attivo ORDER BY ordine")->fetchAll(PDO::FETCH_ASSOC);
}

$tipi = [
    'corsi'     => ['label' => '📋 Corsi del giorno', 'colore' => '#e94560'],
    'countdown' => ['label' => '⏳ Countdown evento',  'colore' => '#f59e0b'],
    'meteo'     => ['label' => '🌤️ Meteo',             'colore' => '#3b82f6'],
    'info'      => ['label' => 'ℹ️ Info / Avviso',     'colore' => '#10b981'],
];

$titolo = 'Sidebar Slides';
require_once 'includes/header.php';
?>

<div class="container" style="display:grid; grid-template-columns:300px 1fr; gap:24px;">

<!-- ── COLONNA SX ── -->
<div>
    <?php if ($messaggio): [$tm,$txt] = explode('|',$messaggio); ?>
    <div class="messaggio <?php echo $tm; ?>"><?php echo $txt; ?></div>
    <?php endif; ?>

    <div class="box">
        <h2>Profili</h2>
        <?php foreach ($profili as $pr): ?>
        <div style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
            <a href="/sidebar.php?p=<?php echo $pr['id']; ?>"
               style="flex:1; padding:10px 14px; background:#0f3460; border-radius:6px;
                      font-size:14px; text-decoration:none; color:#eee; display:block;
                      border-left:3px solid <?php echo $profilo_attivo==$pr['id'] ? '#e94560' : 'transparent'; ?>;">
                🎛️ <?php echo htmlspecialchars($pr['nome']); ?>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($profilo_attivo): ?>
    <!-- FORM AGGIUNTA -->
    <div class="box">
        <h2>+ Nuova Slide</h2>
        <form method="POST" enctype="multipart/form-data" id="formSlide">
            <input type="hidden" name="azione" value="aggiungi_slide">
            <input type="hidden" name="profilo_id" value="<?php echo $profilo_attivo; ?>">

            <label>Tipo slide</label>
            <select name="tipo" id="tipoSel" onchange="aggiornaCampi()">
                <?php foreach ($tipi as $k => $t): ?>
                <option value="<?php echo $k; ?>"><?php echo $t['label']; ?></option>
                <?php endforeach; ?>
            </select>

            <label>Titolo (mostrato in cima alla slide)</label>
            <input type="text" name="titolo" placeholder="Es. Oggi in palestra, Meteo Soave...">

            <label>Durata (secondi)</label>
            <input type="number" name="durata" value="10" min="3" max="120">

            <!-- Campi specifici per tipo -->
            <div id="campi-countdown" style="display:none;">
                <label>Data e ora evento</label>
                <input type="datetime-local" name="ct_data_target">
                <label>Messaggio dopo l'evento</label>
                <input type="text" name="ct_messaggio_post" placeholder="Es. Evento in corso!">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="ct_mostra_giorni" checked style="width:auto; display:inline;">
                    Mostra giorni separati
                </label>
            </div>

            <div id="campi-meteo" style="display:none;">
                <label>Città</label>
                <input type="text" name="mt_citta" placeholder="Es. Verona">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <div>
                        <label>Latitudine (opz.)</label>
                        <input type="text" name="mt_lat" placeholder="45.4384">
                    </div>
                    <div>
                        <label>Longitudine (opz.)</label>
                        <input type="text" name="mt_lon" placeholder="10.9916">
                    </div>
                </div>
                <p style="font-size:11px; color:#666; margin-top:4px;">
                    Se lasci lat/lon vuoti usa il nome città per la ricerca automatica.
                </p>
            </div>

            <div id="campi-info" style="display:none;">
                <label>Icona emoji</label>
                <input type="text" name="info_icona" placeholder="💪" value="ℹ️" style="width:80px;">
                <label>Testo</label>
                <textarea name="info_testo" rows="4"
                          placeholder="Es. La palestra resterà chiusa il 25 aprile"
                          style="width:100%; padding:10px 12px; background:#0f3460; border:1px solid #1a4a7a;
                                 border-radius:6px; color:#eee; font-size:14px; resize:vertical;"></textarea>
            </div>

            <!-- Stile slide -->
            <div style="border-top:1px solid #1a4a7a; margin-top:14px; padding-top:14px;">
                <label style="font-size:12px; color:#aaa; letter-spacing:1px; text-transform:uppercase;">Aspetto</label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">
                    <div>
                        <label>Colore sfondo</label>
                        <input type="color" name="colore_sfondo" value="#111111"
                               style="width:100%; height:40px; padding:2px; cursor:pointer;">
                    </div>
                    <div>
                        <label>Colore testo</label>
                        <input type="color" name="colore_testo" value="#ffffff"
                               style="width:100%; height:40px; padding:2px; cursor:pointer;">
                    </div>
                </div>
                <label>Immagine sfondo (opzionale)</label>
                <input type="file" name="sfondo" accept="image/*"
                       style="background:#0f3460; border:1px solid #1a4a7a; border-radius:6px;
                              color:#eee; padding:8px; width:100%; cursor:pointer;">
                <div id="sfondo-preview" style="display:none; margin-top:8px;">
                    <img id="sfondo-img" style="width:100%; border-radius:6px; max-height:80px; object-fit:cover;">
                </div>
            </div>

            <button type="submit" class="btn btn-full" style="margin-top:14px;">+ Aggiungi Slide</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- ── COLONNA DX ── -->
<div>
    <?php if ($profilo_attivo): ?>

    <div class="box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0;">📱 Slide Sidebar (<?php echo count($slides); ?>)</h2>
            <span style="font-size:12px; color:#aaa;">Trascina per riordinare · <?php echo array_sum(array_column($slides,'durata')); ?>s totali</span>
        </div>

        <?php if (empty($slides)): ?>
            <div class="vuoto">Nessuna slide. Aggiungine una dal pannello a sinistra.</div>
        <?php else: ?>
        <div id="lista-slides">
        <?php foreach ($slides as $i => $sl):
            $cfg = json_decode($sl['contenuto'], true) ?: [];
            $tipo_info = $tipi[$sl['tipo']] ?? ['label'=>$sl['tipo'], 'colore'=>'#aaa'];
            $hasSfondo = !empty($sl['sfondo']);
        ?>
        <div data-id="<?php echo $sl['id']; ?>"
             style="display:flex; align-items:stretch; gap:0; border-radius:10px; margin-bottom:12px;
                    overflow:hidden; border:1px solid #1a4a7a;
                    opacity:<?php echo $sl['attivo'] ? '1' : '0.45'; ?>">

            <!-- Anteprima colore/sfondo -->
            <div style="width:80px; flex-shrink:0; position:relative;
                        background:<?php echo htmlspecialchars($sl['colore_sfondo']); ?>;
                        <?php if ($hasSfondo): ?>
                        background-image:url('/uploads/<?php echo $sl['sfondo']; ?>');
                        background-size:cover; background-position:center;
                        <?php endif; ?>">
                <div style="position:absolute; bottom:6px; left:0; right:0; text-align:center;
                             font-size:11px; color:<?php echo htmlspecialchars($sl['colore_testo']); ?>;
                             text-shadow:0 1px 3px rgba(0,0,0,0.8); font-weight:bold;">
                    <?php echo $sl['durata']; ?>s
                </div>
            </div>

            <!-- Contenuto card -->
            <div style="flex:1; padding:12px 16px; background:#0f3460; display:flex; flex-direction:column; justify-content:center; gap:4px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="drag-handle" style="color:#1a4a7a; cursor:grab; font-size:18px; user-select:none;">⠿</span>
                    <span style="font-size:11px; font-weight:bold; padding:2px 8px; border-radius:20px;
                                 background:<?php echo $tipo_info['colore']; ?>22;
                                 color:<?php echo $tipo_info['colore']; ?>; letter-spacing:0.5px;">
                        <?php echo $tipo_info['label']; ?>
                    </span>
                    <?php if ($sl['titolo']): ?>
                    <span style="font-size:14px; font-weight:bold; color:#fff;">
                        <?php echo htmlspecialchars($sl['titolo']); ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Dettagli per tipo -->
                <div style="font-size:12px; color:#aaa; padding-left:26px;">
                <?php if ($sl['tipo'] === 'countdown' && !empty($cfg['data_target'])): ?>
                    📅 <?php echo date('d/m/Y H:i', strtotime($cfg['data_target'])); ?>
                    <?php if (!empty($cfg['messaggio_post'])): ?> · "<?php echo htmlspecialchars($cfg['messaggio_post']); ?>"<?php endif; ?>
                <?php elseif ($sl['tipo'] === 'meteo' && !empty($cfg['citta'])): ?>
                    📍 <?php echo htmlspecialchars($cfg['citta']); ?>
                <?php elseif ($sl['tipo'] === 'info' && !empty($cfg['testo'])): ?>
                    <?php echo htmlspecialchars($cfg['icona'] ?? ''); ?> <?php echo htmlspecialchars(mb_substr($cfg['testo'], 0, 60)); ?>...
                <?php elseif ($sl['tipo'] === 'corsi'): ?>
                    Mostra i corsi del giorno dal Google Sheet
                <?php endif; ?>
                </div>
            </div>

            <!-- Azioni -->
            <div style="display:flex; flex-direction:column; background:#081428; padding:8px; gap:6px; align-items:center; justify-content:center;">
                <button onclick="toggleAttivo(<?php echo $sl['id']; ?>, <?php echo $sl['attivo'] ? 0 : 1; ?>, this)"
                        class="btn btn-sm"
                        style="font-size:11px; padding:4px 8px; background:<?php echo $sl['attivo'] ? '#10b981' : '#374151'; ?>;"
                        title="<?php echo $sl['attivo'] ? 'Disattiva' : 'Attiva'; ?>">
                    <?php echo $sl['attivo'] ? '● ON' : '○ OFF'; ?>
                </button>
                <a href="/sidebar.php?elimina=<?php echo $sl['id']; ?>&p=<?php echo $profilo_attivo; ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Eliminare questa slide?')"
                   style="font-size:11px; padding:4px 8px;">✕</a>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ANTEPRIMA SIDEBAR -->
    <div class="box">
        <h2>👁️ Anteprima Sidebar</h2>
        <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:8px;">
        <?php foreach ($slides as $sl):
            if (!$sl['attivo']) continue;
            $cfg = json_decode($sl['contenuto'], true) ?: [];
            $tipo_info = $tipi[$sl['tipo']] ?? ['label'=>$sl['tipo'], 'colore'=>'#aaa'];
        ?>
        <div style="flex-shrink:0; width:140px; height:200px; border-radius:8px; overflow:hidden;
                    position:relative; border:1px solid #1a4a7a;
                    background:<?php echo htmlspecialchars($sl['colore_sfondo']); ?>;
                    <?php if (!empty($sl['sfondo'])): ?>
                    background-image:url('/uploads/<?php echo $sl['sfondo']; ?>');
                    background-size:cover; background-position:center;
                    <?php endif; ?>
                    color:<?php echo htmlspecialchars($sl['colore_testo']); ?>;">
            <div style="position:absolute; inset:0; background:rgba(0,0,0,0.35);"></div>
            <div style="position:relative; z-index:1; padding:10px; height:100%; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="font-size:9px; font-weight:bold; opacity:0.7; text-transform:uppercase; letter-spacing:1px;">
                        <?php echo $tipo_info['label']; ?>
                    </div>
                    <?php if ($sl['titolo']): ?>
                    <div style="font-size:12px; font-weight:bold; margin-top:4px; line-height:1.3;">
                        <?php echo htmlspecialchars($sl['titolo']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($sl['tipo'] === 'countdown' && !empty($cfg['data_target'])): ?>
                    <div style="font-size:22px; font-weight:bold; margin-top:8px; letter-spacing:2px;">00:00</div>
                    <div style="font-size:9px; opacity:0.7; margin-top:2px;">GIORNI  ORE  MIN</div>
                    <?php elseif ($sl['tipo'] === 'meteo'): ?>
                    <div style="font-size:28px; margin-top:8px;">⛅</div>
                    <div style="font-size:22px; font-weight:bold;">--°C</div>
                    <?php elseif ($sl['tipo'] === 'info'): ?>
                    <div style="font-size:20px; margin-top:8px;"><?php echo $cfg['icona'] ?? 'ℹ️'; ?></div>
                    <div style="font-size:10px; margin-top:4px; opacity:0.85; line-height:1.4;">
                        <?php echo htmlspecialchars(mb_substr($cfg['testo'] ?? '', 0, 50)); ?>
                    </div>
                    <?php elseif ($sl['tipo'] === 'corsi'): ?>
                    <div style="font-size:10px; margin-top:8px; opacity:0.85; line-height:1.8;">
                        09:00 Yoga<br>10:30 Pilates<br>17:00 Spinning
                    </div>
                    <?php endif; ?>
                </div>
                <div style="font-size:10px; opacity:0.6; text-align:right;"><?php echo $sl['durata']; ?>s</div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty(array_filter($slides, fn($s) => $s['attivo']))): ?>
            <div class="vuoto">Nessuna slide attiva.</div>
        <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <div class="box">
        <div class="vuoto">👈 Seleziona un profilo per gestirne le slide</div>
    </div>
    <?php endif; ?>
</div>
</div>

<style>
input[type=date], input[type=time], input[type=datetime-local] {
    width: 100%;
    padding: 10px 12px;
    background: #0f3460 !important;
    border: 1px solid #1a4a7a !important;
    border-radius: 6px;
    color: #eee !important;
    font-size: 14px;
    margin-bottom: 12px;
    cursor: pointer;
}
input[type=datetime-local]::-webkit-calendar-picker-indicator {
    filter: invert(0.7); cursor: pointer;
}
.sortable-ghost { opacity:0.3; background:#1a4a7a !important; border-radius:10px; }
.drag-handle:hover { color:#e94560 !important; }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
// Mostra campi in base al tipo selezionato
function aggiornaCampi() {
    const tipo = document.getElementById('tipoSel').value;
    ['countdown','meteo','info'].forEach(t => {
        document.getElementById('campi-' + t).style.display = (t === tipo) ? 'block' : 'none';
    });
}
aggiornaCampi();

// Preview sfondo
document.querySelector('input[name=sfondo]').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('sfondo-img').src = e.target.result;
        document.getElementById('sfondo-preview').style.display = 'block';
    };
    reader.readAsDataURL(file);
});

// Toggle attivo via AJAX
function toggleAttivo(id, val, btn) {
    const fd = new FormData();
    fd.append('azione', 'toggle_attivo');
    fd.append('id', id);
    fd.append('attivo', val);
    fetch('/sidebar.php', { method:'POST', body:fd }).then(() => location.reload());
}

// Drag & drop riordino
const lista = document.getElementById('lista-slides');
if (lista) {
    Sortable.create(lista, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function() {
            const ids = [...lista.querySelectorAll('[data-id]')].map(el => el.dataset.id);
            const fd = new FormData();
            fd.append('azione', 'riordina');
            fd.append('ordine', JSON.stringify(ids));
            fetch('/sidebar.php', { method:'POST', body:fd }).then(r => r.text()).then(t => {
                if (t === 'ok') {
                    lista.style.outline = '2px solid #22c55e';
                    setTimeout(() => lista.style.outline = '', 800);
                }
            });
        }
    });
}

// showPicker su datetime
document.querySelectorAll('input[type=datetime-local]').forEach(input => {
    input.addEventListener('click', function() { try { this.showPicker(); } catch(e) {} });
});
</script>

<?php require_once 'includes/footer.php'; ?>