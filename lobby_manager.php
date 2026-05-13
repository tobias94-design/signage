<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();
$titolo = 'Lobby Manager';

// ── UPLOAD FILE ───────────────────────────────────────────────
function uploadFile($file, $allowedExts) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) return '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) return '';
    $nome = 'lobby_' . uniqid() . '.' . $ext;
    $dest = __DIR__ . '/uploads/' . $nome;
    return move_uploaded_file($file['tmp_name'], $dest) ? $nome : '';
}

// ── LEGGI TOKEN DISPOSITIVO ───────────────────────────────────
$token = $_GET['dev'] ?? '';
$msg   = '';

// ── AZIONI POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'aggiungi') {
        error_log('LOBBY_MGR aggiungi: tipo=' . ($_POST['tipo']??'') . ' FILES=' . json_encode(array_map(fn($f)=>['name'=>$f['name'],'error'=>$f['error'],'size'=>$f['size']], $_FILES)));
        $tipo   = $_POST['tipo'] ?? 'info';
        $titolo = trim($_POST['titolo'] ?? '');
        $durata = max(3, (int)($_POST['durata'] ?? 10));
        $colore_sfondo = $_POST['colore_sfondo'] ?? '#111111';
        $colore_testo  = $_POST['colore_testo']  ?? '#ffffff';
        $contenuto = [];

        switch ($tipo) {
            case 'immagine':
                $file = uploadFile($_FILES['media_file'] ?? null, ['jpg','jpeg','png','gif','webp']);
                $contenuto = ['file' => $file];
                break;
            case 'video':
                $file = uploadFile($_FILES['media_file'] ?? null, ['mp4','webm','mov']);
                $contenuto = ['file' => $file];
                break;
            case 'meteo':
                $contenuto = ['citta' => trim($_POST['mt_citta'] ?? '')];
                break;
            case 'corsi':
                $contenuto = [];
                break;
            case 'countdown':
                $contenuto = [
                    'data_target'   => $_POST['ct_data'] ?? '',
                    'messaggio_pre' => trim($_POST['ct_msg'] ?? '')
                ];
                break;
            case 'info':
                $contenuto = [
                    'icona' => trim($_POST['info_icona'] ?? 'ℹ️'),
                    'testo' => trim($_POST['info_testo'] ?? '')
                ];
                break;
        }

        $ordine = (int)$db->query("SELECT COUNT(*) FROM sidebar_slides WHERE dispositivo_token=" . $db->quote($token))->fetchColumn();
        $db->prepare("INSERT INTO sidebar_slides (dispositivo_token, profilo_id, tipo, titolo, contenuto, durata, ordine, colore_sfondo, colore_testo, attivo) VALUES (?,NULL,?,?,?,?,?,?,?,1)")
           ->execute([$token, $tipo, $titolo, json_encode($contenuto), $durata, $ordine, $colore_sfondo, $colore_testo]);
        $msg = 'ok|Slide aggiunta!';
        header('Location: /lobby_manager.php?dev=' . urlencode($token) . '&msg=ok');
        exit;
    }

    if ($azione === 'elimina') {
        $id = (int)($_POST['id'] ?? 0);
        $row = $db->query("SELECT sfondo, contenuto FROM sidebar_slides WHERE id=$id")->fetch();
        if ($row) {
            $cfg = json_decode($row['contenuto'] ?? '{}', true);
            if (!empty($cfg['file'])) @unlink(__DIR__ . '/uploads/' . $cfg['file']);
            if (!empty($row['sfondo'])) @unlink(__DIR__ . '/uploads/' . $row['sfondo']);
            $db->exec("DELETE FROM sidebar_slides WHERE id=$id");
        }
        header('Location: /lobby_manager.php?dev=' . urlencode($token));
        exit;
    }

    if ($azione === 'toggle') {
        $id  = (int)($_POST['id'] ?? 0);
        $val = (int)($_POST['attivo'] ?? 0);
        $db->prepare("UPDATE sidebar_slides SET attivo=? WHERE id=?")->execute([$val, $id]);
        header('Location: /lobby_manager.php?dev=' . urlencode($token));
        exit;
    }

    if ($azione === 'riordina') {
        $ids = json_decode($_POST['ordine'] ?? '[]', true);
        foreach ($ids as $pos => $id) {
            $db->prepare("UPDATE sidebar_slides SET ordine=? WHERE id=?")->execute([$pos, (int)$id]);
        }
        echo 'ok'; exit;
    }

    if ($azione === 'reload') {
        $db->prepare("UPDATE dispositivi SET reload_richiesto=1 WHERE token=?")->execute([$token]);
        header('Location: /lobby_manager.php?dev=' . urlencode($token) . '&msg=reload');
        exit;
    }

    if ($azione === 'salva_config') {
        $citta    = trim($_POST['lobby_citta'] ?? '');
        $sheet    = trim($_POST['lobby_sheet_url'] ?? '');
        $corsiurl = trim($_POST['lobby_corsi_url'] ?? '');
        $db->prepare("UPDATE dispositivi SET lobby_citta=?, lobby_sheet_url=?, lobby_corsi_url=? WHERE token=?")
           ->execute([$citta, $sheet, $corsiurl, $token]);
        header('Location: /lobby_manager.php?dev=' . urlencode($token) . '&msg=config');
        exit;
    }
}

// ── LEGGI DISPOSITIVO ─────────────────────────────────────────
$dispositivi = $db->query("SELECT * FROM dispositivi WHERE tipo_display='lobby' ORDER BY club, nome")->fetchAll(PDO::FETCH_ASSOC);
$dev = null;
$slides = [];
if ($token) {
    $stmt = $db->prepare("SELECT * FROM dispositivi WHERE token=?");
    $stmt->execute([$token]);
    $dev = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dev) {
        $slides = $db->query("SELECT * FROM sidebar_slides WHERE dispositivo_token=" . $db->quote($token) . " ORDER BY ordine")->fetchAll(PDO::FETCH_ASSOC);
    }
}

$msgShow = $_GET['msg'] ?? '';
?>
<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/sidebar.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<style>
.lm-dev-item { display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;text-decoration:none;color:var(--sg-white);font-size:13px;border:1px solid transparent;transition:all 0.15s;margin-bottom:4px; }
.lm-dev-item:hover { background:rgba(255,255,255,0.04);border-color:var(--sg-glass-border); }
.lm-dev-item.active { background:rgba(139,92,246,0.12);border-color:rgba(139,92,246,0.3);color:#a78bfa;font-weight:600; }
.lm-dot { width:8px;height:8px;border-radius:50%;background:var(--sg-muted);flex-shrink:0; }
.lm-dot.online { background:#10b981; }
.grid2 { display:grid;grid-template-columns:360px 1fr;gap:24px;align-items:start; }
.slide-list { display:flex;flex-direction:column;gap:8px; }
.slide-row { display:flex;align-items:center;gap:12px;padding:14px 16px;background:rgba(255,255,255,0.03);border:1px solid var(--sg-glass-border);border-radius:10px;cursor:grab;transition:background 0.15s; }
.slide-row:hover { background:rgba(255,255,255,0.06); }
.slide-row.inactive { opacity:0.4; }
.drag-handle { color:var(--sg-muted);font-size:18px;user-select:none;flex-shrink:0; }
.slide-icon { font-size:22px;flex-shrink:0; }
.slide-info { flex:1;min-width:0; }
.slide-nome { font-size:14px;font-weight:600; }
.slide-sub { font-size:12px;color:var(--sg-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.slide-actions { display:flex;gap:6px;flex-shrink:0; }
.tipo-badge { display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-right:6px; }
.preview-link { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:rgba(139,92,246,0.1);border:1px solid rgba(139,92,246,0.3);border-radius:8px;color:#a78bfa;text-decoration:none;font-size:13px;font-weight:500; }
.preview-link:hover { background:rgba(139,92,246,0.2); }
.config-grid { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
.empty-state { text-align:center;padding:48px 24px;color:var(--sg-muted); }
#campi-meteo,#campi-countdown,#campi-info { display:none; }
.sortable-ghost { opacity:0.3; }
</style>
<div class="sg-content">
<div style="display:flex;gap:24px;align-items:start;">
<div style="width:220px;flex-shrink:0;">
    <div style="font-size:11px;font-weight:700;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">Dispositivi Lobby</div>
    <?php if (!$dispositivi): ?>
    <p style="font-size:12px;color:var(--sg-muted);">Nessun dispositivo lobby.</p>
    <?php else: ?>
    <?php foreach ($dispositivi as $d): ?>
    <a href="/lobby_manager.php?dev=<?= urlencode($d['token']) ?>" class="lm-dev-item <?= $d['token']===$token?'active':'' ?>">
        <span class="lm-dot <?= (strtotime($d['ultimo_ping']??'') > time()-120)?'online':'' ?>"></span>
        <div>
            <div><?= htmlspecialchars($d['nome']) ?></div>
            <div style="font-size:11px;color:var(--sg-muted);"><?= htmlspecialchars($d['club']) ?></div>
        </div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<div style="flex:1;min-width:0;">
        <?php if (!$dev): ?>
        <div class="empty-state">
            <div class="emoji">🏨</div>
            <div style="font-size:18px;font-weight:600;margin-bottom:8px;">Seleziona un dispositivo</div>
            <div>Scegli un dispositivo lobby dalla lista a sinistra</div>
        </div>

        <?php else: ?>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <div>
                <div class="page-title"><?= htmlspecialchars($dev['nome']) ?></div>
                <div class="page-sub"><?= htmlspecialchars($dev['club']) ?> — <?= count($slides) ?> slide nel loop</div>
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                <a href="/player/lobby.php?token=<?= urlencode($token) ?>" target="_blank" class="preview-link">
                    👁 Anteprima player
                </a>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="azione" value="reload">
                    <button type="submit" class="btn btn-ghost btn-sm" title="Ricarica TV">🔄 Ricarica TV</button>
                </form>
            </div>
        </div>

        <?php if ($msgShow === 'ok'): ?>
        <div class="alert alert-ok">✅ Slide aggiunta con successo!</div>
        <?php elseif ($msgShow === 'config'): ?>
        <div class="alert alert-ok">✅ Configurazione salvata!</div>
        <?php elseif ($msgShow === 'reload'): ?>
        <div class="alert alert-ok">🔄 Ricarica inviata alla TV!</div>
        <?php endif; ?>

        <!-- Config dispositivo -->
        <div class="box" style="margin-bottom:24px;">
            <div class="box-title">⚙️ Configurazione dispositivo</div>
            <form method="POST">
                <input type="hidden" name="azione" value="salva_config">
                <div class="config-grid">
                    <div>
                        <label>Città meteo</label>
                        <input type="text" name="lobby_citta" value="<?= htmlspecialchars($dev['lobby_citta']??'') ?>" placeholder="Es. Verona">
                    </div>
                    <div>
                        <label>Coordinate usate per meteo preciso</label>
                        <input type="text" value="<?= $dev['lat']??'' ?>, <?= $dev['lon']??'' ?>" disabled style="opacity:0.5;" placeholder="Da scheda dispositivo">
                    </div>
                </div>
                <label>URL Google Sheet corsi</label>
                <input type="text" name="lobby_sheet_url" value="<?= htmlspecialchars($dev['lobby_sheet_url']??'') ?>" placeholder="https://docs.google.com/spreadsheets/...">
                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-ghost btn-sm">💾 Salva configurazione</button>
                </div>
            </form>
        </div>

        <div class="grid2">
            <!-- Form aggiungi slide -->
            <div class="box">
                <div class="box-title">+ Aggiungi slide</div>
                <form method="POST" action="/lobby_manager.php?dev=<?= urlencode($token) ?>" enctype="multipart/form-data">
                    <input type="hidden" name="azione" value="aggiungi">

                    <label>Tipo</label>
                    <select name="tipo" onchange="aggiornaCampi(this.value)">
                        <option value="immagine">🖼️ Immagine</option>
                        <option value="video">🎬 Video</option>
                        <option value="meteo">🌤️ Meteo</option>
                        <option value="corsi">📋 Corsi del giorno</option>
                        <option value="countdown">⏳ Countdown</option>
                        <option value="info">ℹ️ Info / Avviso</option>
                    </select>

                    <label>Titolo <span style="color:var(--muted);font-weight:400;">(opzionale)</span></label>
                    <input type="text" name="titolo" placeholder="Es. Meteo Verona">

                    <label>Durata (secondi)</label>
                    <input type="number" name="durata" value="10" min="3" max="300">

                    <!-- Campo file SEMPRE nel DOM -->
                    <div id="campi-media">
                        <label id="media-label">File <span style="color:var(--muted);font-weight:400;">JPG, PNG, WebP, MP4</span></label>
                        <input type="file" name="media_file" id="media-input" accept="image/*,video/*">
                    </div>
                    <div id="campi-meteo" style="display:none;">
                        <label>Città</label>
                        <input type="text" name="mt_citta" placeholder="Es. Verona">
                    </div>
                    <div id="campi-countdown" style="display:none;">
                        <label>Data e ora evento</label>
                        <input type="datetime-local" name="ct_data">
                        <label>Messaggio introduttivo</label>
                        <input type="text" name="ct_msg" placeholder="Es. Manca poco a...">
                    </div>
                    <div id="campi-info" style="display:none;">
                        <label>Icona emoji</label>
                        <input type="text" name="info_icona" value="ℹ️" style="width:80px;">
                        <label>Testo</label>
                        <textarea name="info_testo" rows="4" placeholder="Testo informativo..."></textarea>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px;">
                        <div><label>Sfondo</label><input type="color" name="colore_sfondo" value="#111111"></div>
                        <div><label>Testo</label><input type="color" name="colore_testo" value="#ffffff"></div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:20px;justify-content:center;">+ Aggiungi slide</button>
                </form>
            </div>

            <!-- Lista slide -->
            <div class="box">
                <div class="box-title">Slideshow loop — <?= count($slides) ?> slide</div>
                <?php if (!$slides): ?>
                <div class="empty-state">
                    <div class="emoji" style="font-size:32px;">📭</div>
                    <div style="margin-top:8px;">Nessuna slide. Aggiungine una!</div>
                </div>
                <?php else: ?>
                <div class="slide-list" id="slide-list">
                <?php
                $tipiInfo = [
                    'immagine'  => ['emoji'=>'🖼️', 'color'=>'#8b5cf6'],
                    'video'     => ['emoji'=>'🎬', 'color'=>'#ef4444'],
                    'meteo'     => ['emoji'=>'🌤️', 'color'=>'#3b82f6'],
                    'corsi'     => ['emoji'=>'📋', 'color'=>'#e94560'],
                    'countdown' => ['emoji'=>'⏳', 'color'=>'#f59e0b'],
                    'info'      => ['emoji'=>'ℹ️', 'color'=>'#10b981'],
                ];
                foreach ($slides as $sl):
                    $cfg = json_decode($sl['contenuto']??'{}', true) ?: [];
                    $ti  = $tipiInfo[$sl['tipo']] ?? ['emoji'=>'?','color'=>'#aaa'];
                    $sub = '';
                    if ($sl['tipo']==='immagine'||$sl['tipo']==='video') $sub = $cfg['file'] ?? 'nessun file';
                    elseif ($sl['tipo']==='meteo') $sub = $cfg['citta'] ?? '';
                    elseif ($sl['tipo']==='info') $sub = mb_substr($cfg['testo']??'', 0, 50);
                    elseif ($sl['tipo']==='countdown') $sub = $cfg['data_target'] ?? '';
                    elseif ($sl['tipo']==='corsi') $sub = 'Dal Google Sheet del dispositivo';
                ?>
                <div class="slide-row <?= $sl['attivo']?'':'inactive' ?>" data-id="<?= $sl['id'] ?>">
                    <span class="drag-handle">⠿</span>
                    <span class="slide-icon"><?= $ti['emoji'] ?></span>
                    <div class="slide-info">
                        <div class="slide-nome">
                            <span class="tipo-badge" style="background:<?= $ti['color'] ?>22;color:<?= $ti['color'] ?>;"><?= strtoupper($sl['tipo']) ?></span>
                            <?= htmlspecialchars($sl['titolo'] ?: ucfirst($sl['tipo'])) ?>
                        </div>
                        <div class="slide-sub"><?= htmlspecialchars($sub) ?> — <?= $sl['durata'] ?>s</div>
                    </div>
                    <div class="slide-actions">
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="azione" value="toggle">
                            <input type="hidden" name="id" value="<?= $sl['id'] ?>">
                            <input type="hidden" name="attivo" value="<?= $sl['attivo']?0:1 ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" title="<?= $sl['attivo']?'Disattiva':'Attiva' ?>"><?= $sl['attivo']?'✅':'⭕' ?></button>
                        </form>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('Eliminare questa slide?')">
                            <input type="hidden" name="azione" value="elimina">
                            <input type="hidden" name="id" value="<?= $sl['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm">🗑️</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
<?php require_once 'includes/footer.php'; ?>
<script>
function aggiornaCampi(tipo) {
    // Mostra/nascondi campi specifici per tipo
    ['meteo','countdown','info'].forEach(function(t) {
        document.getElementById('campi-'+t).style.display = t===tipo ? 'block' : 'none';
    });
    // Campo file sempre visibile per immagine e video, nascosto per altri
    var mediaDiv = document.getElementById('campi-media');
    var mediaInput = document.getElementById('media-input');
    var mediaLabel = document.getElementById('media-label');
    var mediaHint = document.getElementById('media-hint');
    if (tipo === 'immagine') {
        mediaDiv.style.display = 'block';
        mediaInput.accept = 'image/*';
        mediaLabel.innerHTML = 'File immagine <span style="color:var(--muted);font-weight:400;">JPG, PNG, WebP — fullscreen 1920×1080</span>';
        mediaHint.style.display = 'none';
    } else if (tipo === 'video') {
        mediaDiv.style.display = 'block';
        mediaInput.accept = 'video/*';
        mediaLabel.innerHTML = 'File video <span style="color:var(--muted);font-weight:400;">MP4, WebM</span>';
        mediaHint.style.display = 'block';
    } else {
        mediaDiv.style.display = 'none';
    }
}

// Drag & drop riordina
var lista = document.getElementById('slide-list');
if (lista) {
    Sortable.create(lista, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function() {
            var ids = [...lista.querySelectorAll('[data-id]')].map(function(el) { return el.dataset.id; });
            var fd = new FormData();
            fd.append('azione', 'riordina');
            fd.append('ordine', JSON.stringify(ids));
            fetch('/lobby_manager.php?dev=<?= urlencode($token) ?>', { method: 'POST', body: fd });
        }
    });
}
</script>

