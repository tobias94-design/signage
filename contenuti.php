<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Contenuti';
$db  = getDB();
$tid = TENANT_ID;

// ── Cartella corrente (root se non specificata) ───────────────
$cartella_id = isset($_GET['cartella']) && $_GET['cartella'] !== '' ? (int)$_GET['cartella'] : null;
$cartella_corrente = null;
if ($cartella_id) {
    $stmt = $db->prepare("SELECT id, nome FROM cartelle_contenuti WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$cartella_id, $tid]);
    $cartella_corrente = $stmt->fetch();
    if (!$cartella_corrente) $cartella_id = null; // cartella non tua o cancellata: torna a root
}

// ── Upload directory ─────────────────────────────────────────
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// ── Azioni POST ──────────────────────────────────────────────
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // UPLOAD
    if ($action === 'upload' && !empty($_FILES['files'])) {
        $allowed_types = ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/webm','video/ogg'];
        $max_size = 200 * 1024 * 1024; // 200MB
        $uploaded = 0;
        $rifiutati = []; // nome file => motivo, per mostrare un avviso chiaro invece di scartare in silenzio
        $upload_cartella = !empty($_POST['cartella_id']) ? (int)$_POST['cartella_id'] : null;

        $files = $_FILES['files'];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $nomeOriginale = $files['name'][$i];

            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $rifiutati[] = "$nomeOriginale (errore durante il caricamento)";
                continue;
            }
            if ($files['size'][$i] > $max_size) {
                $rifiutati[] = "$nomeOriginale (troppo grande: max " . format_bytes($max_size) . ")";
                continue;
            }
            if (!in_array($files['type'][$i], $allowed_types)) {
                $ext = strtoupper(pathinfo($nomeOriginale, PATHINFO_EXTENSION));
                $rifiutati[] = "$nomeOriginale (formato $ext non supportato — usa JPG, PNG, WEBP, GIF, MP4, WEBM o OGG)";
                continue;
            }

            $ext      = pathinfo($nomeOriginale, PATHINFO_EXTENSION);
            $filename = uniqid('pb_', true) . '.' . strtolower($ext);
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($files['tmp_name'][$i], $filepath)) {
                $tipo     = str_starts_with($files['type'][$i], 'video') ? 'video' : 'immagine';
                $nome_raw = pathinfo($nomeOriginale, PATHINFO_FILENAME);
                $nome     = substr($nome_raw, 0, 120);

                // I video devono restare in onda quanto durano davvero (altrimenti
                // il player li interrompe e li fa ripartire da capo a meta'); le
                // foto invece non hanno una durata propria, quindi usano il
                // default di 10s che l'utente potra' comunque modificare a mano.
                if ($tipo === 'video') {
                    $durata = 15; // fallback se ffprobe non e' disponibile o fallisce
                    $cmd = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($filepath);
                    $out = @shell_exec($cmd);
                    if ($out !== null && is_numeric(trim($out))) {
                        $durata = max(1, (int)round((float)trim($out)));
                    }
                } else {
                    $durata = 10;
                }

                $stmt = $db->prepare("INSERT INTO contenuti (tenant_id, nome, tipo, file, durata, cartella_id, creato_il) VALUES (?,?,?,?,?,?,NOW())");
                $stmt->execute([$tid, $nome, $tipo, $filename, $durata, $upload_cartella]);
                $uploaded++;
            } else {
                $rifiutati[] = "$nomeOriginale (errore nel salvataggio sul server)";
            }
        }

        if ($uploaded > 0) $success = "$uploaded file caricati con successo.";
        if (!empty($rifiutati)) {
            $error = ($success ? $success . ' ' : '') . count($rifiutati) . ' file NON caricati: ' . implode('; ', $rifiutati);
            $success = ''; // mostriamo tutto nel messaggio di errore, piu' visibile, per non perdere l'avviso
        }
    }

    // ELIMINA
    if ($action === 'delete') {
        $id = (int)($_POST['cont_id'] ?? 0);
        $stmt = $db->prepare("SELECT file FROM contenuti WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tid]);
        $row = $stmt->fetch();
        if ($row) {
            $filepath = $upload_dir . $row['file'];
            if (file_exists($filepath)) unlink($filepath);
            $db->prepare("DELETE FROM contenuti WHERE id = ? AND tenant_id = ?")->execute([$id, $tid]);
            $success = 'Contenuto eliminato.';
        }
    }

    // RINOMINA
    if ($action === 'rename') {
        $id   = (int)($_POST['cont_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if ($nome) {
            $db->prepare("UPDATE contenuti SET nome = ? WHERE id = ? AND tenant_id = ?")
               ->execute([$nome, $id, $tid]);
            $success = 'Contenuto rinominato.';
        }
    }

    // DURATA (solo immagini — i video usano sempre la loro durata reale)
    if ($action === 'set_durata') {
        $id     = (int)($_POST['cont_id'] ?? 0);
        $durata = (int)($_POST['durata'] ?? 0);
        if ($id && $durata > 0) {
            $stmt = $db->prepare("SELECT tipo FROM contenuti WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tid]);
            $row = $stmt->fetch();
            if ($row && $row['tipo'] === 'immagine') {
                $db->prepare("UPDATE contenuti SET durata = ? WHERE id = ? AND tenant_id = ?")
                   ->execute([$durata, $id, $tid]);
                $success = 'Durata aggiornata.';
            }
        }
    }

    // NUOVA CARTELLA
    if ($action === 'create_folder') {
        $nome = trim($_POST['nome_cartella'] ?? '');
        if ($nome) {
            $db->prepare("INSERT INTO cartelle_contenuti (tenant_id, nome, creato_il) VALUES (?,?,NOW())")
               ->execute([$tid, $nome]);
            $success = 'Cartella creata.';
        }
    }

    // RINOMINA CARTELLA
    if ($action === 'rename_folder') {
        $fid  = (int)($_POST['cartella_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if ($nome && $fid) {
            $db->prepare("UPDATE cartelle_contenuti SET nome = ? WHERE id = ? AND tenant_id = ?")
               ->execute([$nome, $fid, $tid]);
            $success = 'Cartella rinominata.';
        }
    }

    // ELIMINA CARTELLA (solo se vuota, per sicurezza)
    if ($action === 'delete_folder') {
        $fid = (int)($_POST['cartella_id'] ?? 0);
        $stmt = $db->prepare("SELECT COUNT(*) FROM contenuti WHERE cartella_id = ? AND tenant_id = ?");
        $stmt->execute([$fid, $tid]);
        if ((int)$stmt->fetchColumn() > 0) {
            $error = 'Sposta prima i contenuti fuori dalla cartella per poterla eliminare.';
        } else {
            $db->prepare("DELETE FROM cartelle_contenuti WHERE id = ? AND tenant_id = ?")->execute([$fid, $tid]);
            $success = 'Cartella eliminata.';
        }
    }

    // SPOSTA CONTENUTO IN CARTELLA
    if ($action === 'move') {
        $id   = (int)($_POST['cont_id'] ?? 0);
        $dest = !empty($_POST['dest_cartella']) ? (int)$_POST['dest_cartella'] : null;
        $db->prepare("UPDATE contenuti SET cartella_id = ? WHERE id = ? AND tenant_id = ?")
           ->execute([$dest, $id, $tid]);
        $success = 'Contenuto spostato.';
    }
}

// ── Query contenuti ──────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$filter = $_GET['tipo'] ?? 'tutti';

$where = "WHERE c.tenant_id = ?";
$params = [$tid];
if ($search) {
    // La ricerca guarda in tutte le cartelle, non solo in quella corrente —
    // altrimenti non trovi un file se non ricordi dove l'avevi messo
    $where .= " AND c.nome LIKE ?";
    $params[] = "%$search%";
} elseif ($cartella_id) {
    $where .= " AND c.cartella_id = ?";
    $params[] = $cartella_id;
} else {
    $where .= " AND c.cartella_id IS NULL";
}
if ($filter === 'video')    { $where .= " AND c.tipo = 'video'"; }
if ($filter === 'immagine') { $where .= " AND c.tipo = 'immagine'"; }

$stmt = $db->prepare("
    SELECT c.*,
           COUNT(DISTINCT pi.playlist_id) AS in_playlist
    FROM contenuti c
    LEFT JOIN playlist_items pi ON pi.contenuto_id = c.id
    $where
    GROUP BY c.id
    ORDER BY c.creato_il DESC
");
$stmt->execute($params);
$contenuti = $stmt->fetchAll();

// ── Cartelle: tile in cima (solo a root, senza ricerca attiva) ─
$cartelle = [];
if (!$cartella_id && !$search) {
    $stmt = $db->prepare("
        SELECT ca.*, COUNT(c.id) AS num_contenuti
        FROM cartelle_contenuti ca
        LEFT JOIN contenuti c ON c.cartella_id = ca.id
        WHERE ca.tenant_id = ?
        GROUP BY ca.id
        ORDER BY ca.nome
    ");
    $stmt->execute([$tid]);
    $cartelle = $stmt->fetchAll();
}

// Tutte le cartelle (per il menu "sposta in", indipendentemente da dove ti trovi)
$stmt = $db->prepare("SELECT id, nome FROM cartelle_contenuti WHERE tenant_id = ? ORDER BY nome");
$stmt->execute([$tid]);
$tutte_cartelle = $stmt->fetchAll();

// Stats
$tot_contenuti = count($contenuti);
$stmt = $db->prepare("SELECT COUNT(*) FROM contenuti WHERE tenant_id = ? AND tipo = 'video'"); $stmt->execute([$tid]); $n_video = (int)$stmt->fetchColumn();
$stmt = $db->prepare("SELECT COUNT(*) FROM contenuti WHERE tenant_id = ? AND tipo = 'immagine'"); $stmt->execute([$tid]); $n_img = (int)$stmt->fetchColumn();
$stmt = $db->prepare("SELECT COUNT(DISTINCT c.id) FROM contenuti c JOIN playlist_items pi ON pi.contenuto_id = c.id WHERE c.tenant_id = ?"); $stmt->execute([$tid]); $n_usati = (int)$stmt->fetchColumn();

function format_bytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes/1024, 1) . ' KB';
    return round($bytes/1048576, 1) . ' MB';
}
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
/* Upload zone */
.upload-zone{border:2px dashed var(--outline-var);border-radius:14px;padding:32px 24px;text-align:center;cursor:pointer;transition:all .2s;background:var(--surface-low);position:relative}
.upload-zone:hover,.upload-zone.dragover{border-color:var(--blue);background:var(--blue-bg)}
.upload-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upload-icon{margin:0 auto 12px;color:var(--on-variant)}
.upload-title{font-size:15px;font-weight:600;color:var(--on-surface);margin-bottom:4px}
.upload-sub{font-size:12px;color:var(--on-variant)}
.upload-btn-label{display:inline-flex;align-items:center;gap:6px;margin-top:12px;background:var(--blue);color:#fff;border-radius:8px;padding:8px 18px;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s}
.upload-btn-label:hover{background:var(--blue-dim)}

/* Progress */
.progress-list{margin-top:16px;display:flex;flex-direction:column;gap:8px}
.progress-item{background:var(--surface);border:1px solid var(--outline-var);border-radius:8px;padding:10px 14px}
.progress-name{font-size:12px;font-weight:500;color:var(--on-surface);margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.progress-bar-wrap{height:4px;background:var(--surface-high);border-radius:99px;overflow:hidden}
.progress-bar-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--blue),var(--violet));transition:width .2s}
.progress-pct{font-size:10px;color:var(--on-variant);margin-top:4px}

/* Toggle vista */
.view-toggle{display:flex;background:var(--surface-mid);border-radius:8px;padding:3px;gap:2px}
.view-btn{width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;border:none;background:none;color:var(--on-variant);cursor:pointer;transition:all .12s}
.view-btn.active{background:var(--surface);color:var(--on-surface);box-shadow:var(--shadow-sm)}

/* Filter tabs */
.filter-tabs{display:flex;gap:4px}
.filter-tab{padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;color:var(--on-variant);border:none;background:none;cursor:pointer;transition:all .12s}
.filter-tab:hover{background:var(--surface-mid);color:var(--on-surface)}
.filter-tab.active{background:var(--blue-bg);color:var(--blue-text)}

/* Grid view */
.cont-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
.cont-card{background:var(--surface);border:1px solid var(--outline-var);border-radius:12px;overflow:hidden;transition:all .18s;cursor:pointer}
.cont-card:hover{box-shadow:var(--shadow-md);border-color:var(--blue)}
.cont-thumb{aspect-ratio:16/9;background:var(--surface-mid);position:relative;overflow:hidden}
.cont-thumb img,.cont-thumb video{width:100%;height:100%;object-fit:cover}
.cont-thumb-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--blue-bg),var(--violet-bg))}
.cont-type-badge{position:absolute;top:8px;left:8px;background:rgba(0,0,0,.6);color:#fff;font-size:10px;font-weight:600;padding:2px 7px;border-radius:4px;backdrop-filter:blur(4px)}
.cont-usage{position:absolute;top:8px;right:8px;background:rgba(0,0,0,.6);color:#fff;font-size:10px;padding:2px 7px;border-radius:4px;backdrop-filter:blur(4px)}
.cont-info{padding:12px}
.cont-name{font-size:13px;font-weight:500;color:var(--on-surface);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px}
.cont-meta{font-size:11px;color:var(--on-variant)}
.cont-actions{display:flex;gap:6px;padding:10px 12px;border-top:1px solid var(--outline-var)}

/* List view */
.cont-list{display:flex;flex-direction:column;gap:2px}
.cont-row{background:var(--surface);border:1px solid var(--outline-var);border-radius:8px;display:flex;align-items:center;gap:14px;padding:10px 16px;transition:all .15s}
.cont-row:hover{border-color:var(--blue);box-shadow:var(--shadow-sm)}
.cont-row-thumb{width:64px;height:36px;border-radius:6px;overflow:hidden;background:var(--surface-mid);flex-shrink:0}
.cont-row-thumb img,.cont-row-thumb video{width:100%;height:100%;object-fit:cover}
.cont-row-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
.cont-row-name{flex:1;font-size:13px;font-weight:500;color:var(--on-surface);min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cont-row-type{width:80px;font-size:11px;font-weight:600;color:var(--on-variant);text-transform:uppercase}
.cont-row-size{width:70px;font-size:11px;color:var(--on-variant);text-align:right}
.cont-row-playlist{width:90px;text-align:center}
.cont-row-date{width:100px;font-size:11px;color:var(--on-variant);text-align:right}
.cont-row-actions{display:flex;gap:6px;flex-shrink:0}

/* Unused badge */
.badge-unused{background:var(--warn-bg);color:var(--warn);font-size:10px;font-weight:600;padding:2px 7px;border-radius:4px}
</style>
</head>
<body>

<?php require_once __DIR__ . '/includes/topnav.php'; ?>

<div class="app">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">

    <!-- PAGE HEAD -->
    <div class="page-head">
      <div>
        <div class="page-title">
          <a href="/contenuti.php" style="color:inherit;text-decoration:none">Contenuti</a>
          <?php if ($cartella_corrente): ?>
          <span style="opacity:.35;margin:0 6px;font-weight:400">/</span>
          <?php echo htmlspecialchars($cartella_corrente['nome']); ?>
          <?php endif; ?>
        </div>
        <div class="page-sub"><?php echo $n_video; ?> video · <?php echo $n_img; ?> immagini · <?php echo $n_usati; ?> in uso</div>
      </div>
      <div class="head-actions">
        <button class="btn-ghost" onclick="document.getElementById('folder-modal').style.display='flex'">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
          Nuova cartella
        </button>
        <div class="view-toggle" id="view-toggle">
          <button class="view-btn active" data-view="grid" onclick="setView('grid')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          </button>
          <button class="view-btn" data-view="list" onclick="setView('list')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          </button>
        </div>
      </div>
    </div>

    <?php if ($success): ?>
    <div class="alert-success" style="background:var(--success-bg);border:1px solid rgba(34,197,94,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--success);margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="background:var(--error-bg);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--error);margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- METRICHE -->
    <div class="g12" style="margin-bottom:24px">
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Totali</div>
        <div class="mval"><?php echo $n_video + $n_img; ?></div>
        <div class="msub">file caricati</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Video</div>
        <div class="mval"><?php echo $n_video; ?></div>
        <div class="msub">mp4, webm</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Immagini</div>
        <div class="mval"><?php echo $n_img; ?></div>
        <div class="msub">jpg, png, webp</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Non utilizzati</div>
        <div class="mval <?php echo ($n_video+$n_img-$n_usati)>0?'':''; ?>"><?php echo ($n_video+$n_img) - $n_usati; ?></div>
        <div class="msub <?php echo ($n_video+$n_img-$n_usati)>0?'dn':'up'; ?>">
          <?php echo ($n_video+$n_img-$n_usati)>0 ? 'non in nessuna playlist' : 'tutti in uso'; ?>
        </div>
      </div>
    </div>

    <!-- UPLOAD ZONE -->
    <div class="card cp" style="margin-bottom:24px">
      <div class="ch">
        <div class="ct">Carica file</div>
        <span style="font-size:11px;color:var(--on-variant)">Max 200MB · jpg, png, webp, gif, mp4, webm</span>
      </div>
      <div class="upload-zone" id="upload-zone">
        <form id="upload-form" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="cartella_id" value="<?php echo $cartella_id ? (int)$cartella_id : ''; ?>">
          <input type="file" name="files[]" id="file-input" multiple accept="image/*,video/mp4,video/webm,video/ogg">
        </form>
        <svg class="upload-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
          <polyline points="17 8 12 3 7 8"/>
          <line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        <div class="upload-title">Trascina i file qui</div>
        <div class="upload-sub">oppure clicca per selezionare</div>
        <label for="file-input" class="upload-btn-label" onclick="event.stopPropagation()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Scegli file
        </label>
      </div>
      <div class="progress-list" id="progress-list" style="display:none"></div>
    </div>

    <!-- FILTRI + RICERCA -->
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap">
      <div class="filter-tabs">
        <button class="filter-tab <?php echo $filter==='tutti'?'active':''; ?>" onclick="setFilter('tutti')">Tutti (<?php echo $n_video+$n_img; ?>)</button>
        <button class="filter-tab <?php echo $filter==='video'?'active':''; ?>" onclick="setFilter('video')">Video (<?php echo $n_video; ?>)</button>
        <button class="filter-tab <?php echo $filter==='immagine'?'active':''; ?>" onclick="setFilter('immagine')">Immagini (<?php echo $n_img; ?>)</button>
      </div>
      <div class="search-box" style="height:38px;width:260px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--on-variant)"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" id="cont-search" placeholder="Cerca contenuto..." value="<?php echo htmlspecialchars($search); ?>">
      </div>
    </div>

    <!-- CARTELLE (solo a root, senza ricerca attiva) -->
    <?php if (!empty($cartelle)): ?>
    <div class="cont-grid" style="margin-bottom:18px">
      <?php foreach ($cartelle as $f): ?>
      <div class="cont-card" style="cursor:default">
        <a href="?cartella=<?php echo $f['id']; ?>" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;aspect-ratio:16/9;background:linear-gradient(135deg,var(--blue-bg),var(--violet-bg));text-decoration:none;color:inherit">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="1.5"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
          <div style="font-size:12px;font-weight:600;color:var(--on-surface)"><?php echo htmlspecialchars($f['nome']); ?></div>
          <div style="font-size:10.5px;color:var(--on-variant)"><?php echo $f['num_contenuti']; ?> <?php echo $f['num_contenuti']==1?'file':'file'; ?></div>
        </a>
        <div class="cont-actions">
          <button class="btn-sm" style="flex:1;justify-content:center" onclick="renameFolder(<?php echo $f['id']; ?>,'<?php echo addslashes($f['nome']); ?>')">Rinomina</button>
          <form method="POST" onsubmit="return confirm('<?php echo $f['num_contenuti']>0 ? 'Questa cartella contiene ancora dei file: spostali prima fuori. Vuoi comunque provare?' : 'Eliminare questa cartella?'; ?>')" style="margin-left:auto">
            <input type="hidden" name="action" value="delete_folder">
            <input type="hidden" name="cartella_id" value="<?php echo $f['id']; ?>">
            <button type="submit" class="btn-sm danger">Elimina</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- CONTENUTI -->
    <?php if (empty($contenuti) && empty($cartelle)): ?>
    <div class="card cp" style="text-align:center;padding:60px">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="margin:0 auto 16px;opacity:.3"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
      <div style="font-size:17px;font-weight:700;margin-bottom:8px">Nessun contenuto</div>
      <div style="font-size:13px;color:var(--on-variant)">Carica il primo file per iniziare</div>
    </div>
    <?php elseif (empty($contenuti)): ?>
    <div class="card cp" style="text-align:center;padding:40px">
      <div style="font-size:13px;color:var(--on-variant)">Questa vista non ha altri file oltre alle cartelle qui sopra.</div>
    </div>
    <?php else: ?>

    <!-- GRID VIEW -->
    <div id="view-grid" class="cont-grid">
      <?php foreach ($contenuti as $c):
        $filepath = $upload_dir . $c['file'];
        $filesize = file_exists($filepath) ? filesize($filepath) : 0;
        $ext = strtolower(pathinfo($c['file'], PATHINFO_EXTENSION));
        $is_video = $c['tipo'] === 'video';
        $is_used = $c['in_playlist'] > 0;
      ?>
      <div class="cont-card" data-search="<?php echo strtolower(htmlspecialchars($c['nome'])); ?>" data-tipo="<?php echo $c['tipo']; ?>">
        <div class="cont-thumb">
          <?php if ($is_video): ?>
          <video src="/uploads/<?php echo htmlspecialchars($c['file']); ?>" preload="none" style="width:100%;height:100%;object-fit:cover"></video>
          <?php elseif (in_array($ext, ['jpg','jpeg','png','webp','gif'])): ?>
          <img src="/uploads/<?php echo htmlspecialchars($c['file']); ?>" alt="" loading="lazy">
          <?php else: ?>
          <div class="cont-thumb-placeholder">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
          </div>
          <?php endif; ?>
          <span class="cont-type-badge"><?php echo strtoupper($ext); ?></span>
          <?php if (!$is_used): ?>
          <span class="cont-usage" style="background:rgba(139,92,0,.7)">Non usato</span>
          <?php elseif ($c['in_playlist'] > 0): ?>
          <span class="cont-usage"><?php echo $c['in_playlist']; ?> playlist</span>
          <?php endif; ?>
        </div>
        <div class="cont-info">
          <div class="cont-name" title="<?php echo htmlspecialchars($c['nome']); ?>"><?php echo htmlspecialchars($c['nome']); ?></div>
          <div class="cont-meta"><?php echo format_bytes($filesize); ?> · <?php echo date('d/m/Y', strtotime($c['creato_il'])); ?></div>
        </div>
        <div class="cont-actions" style="flex-wrap:wrap">
          <button class="btn-sm" onclick="renameContent(<?php echo $c['id']; ?>,'<?php echo addslashes($c['nome']); ?>')" style="flex:1;justify-content:center">
            Rinomina
          </button>
          <?php if (!$is_video): ?>
          <button class="btn-sm" onclick="setDurata(<?php echo $c['id']; ?>, <?php echo (int)$c['durata']; ?>)" title="Quanto resta a schermo in ADV">
            Durata (<?php echo (int)$c['durata']; ?>s)
          </button>
          <?php endif; ?>
          <form method="POST" onsubmit="return confirm('Eliminare questo contenuto?')" style="margin-left:auto">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="cont_id" value="<?php echo $c['id']; ?>">
            <button type="submit" class="btn-sm danger" title="Elimina">
              Elimina
            </button>
          </form>
          <?php if (!empty($tutte_cartelle)): ?>
          <form method="POST" style="width:100%;margin-top:6px">
            <input type="hidden" name="action" value="move">
            <input type="hidden" name="cont_id" value="<?php echo $c['id']; ?>">
            <select name="dest_cartella" class="prop-input" onchange="this.form.submit()" style="width:100%;font-size:11px;padding:5px 8px">
              <option value="">Sposta in… (nessuna cartella)</option>
              <?php foreach ($tutte_cartelle as $fc): ?>
              <option value="<?php echo $fc['id']; ?>" <?php echo (isset($c['cartella_id']) && (int)$c['cartella_id']===(int)$fc['id'])?'selected':''; ?>><?php echo htmlspecialchars($fc['nome']); ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- LIST VIEW -->
    <div id="view-list" class="cont-list" style="display:none">
      <!-- Header -->
      <div style="display:flex;align-items:center;gap:14px;padding:6px 16px;font-size:11px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.05em">
        <div style="width:64px;flex-shrink:0"></div>
        <div style="flex:1">Nome</div>
        <div style="width:80px">Tipo</div>
        <div style="width:70px;text-align:right">Dimensione</div>
        <div style="width:90px;text-align:center">Playlist</div>
        <div style="width:100px;text-align:right">Data</div>
        <div style="width:80px"></div>
      </div>
      <?php foreach ($contenuti as $c):
        $filepath = $upload_dir . $c['file'];
        $filesize = file_exists($filepath) ? filesize($filepath) : 0;
        $ext = strtolower(pathinfo($c['file'], PATHINFO_EXTENSION));
        $is_video = $c['tipo'] === 'video';
        $is_used = $c['in_playlist'] > 0;
      ?>
      <div class="cont-row" data-search="<?php echo strtolower(htmlspecialchars($c['nome'])); ?>" data-tipo="<?php echo $c['tipo']; ?>">
        <div class="cont-row-thumb">
          <?php if ($is_video): ?>
          <video src="/uploads/<?php echo htmlspecialchars($c['file']); ?>" preload="none" style="width:100%;height:100%;object-fit:cover"></video>
          <?php elseif (in_array($ext, ['jpg','jpeg','png','webp','gif'])): ?>
          <img src="/uploads/<?php echo htmlspecialchars($c['file']); ?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
          <div class="cont-row-placeholder">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
          </div>
          <?php endif; ?>
        </div>
        <div class="cont-row-name" title="<?php echo htmlspecialchars($c['nome']); ?>"><?php echo htmlspecialchars($c['nome']); ?></div>
        <div class="cont-row-type">
          <span class="badge <?php echo $is_video ? 'vio' : 'blue'; ?>"><?php echo strtoupper($ext); ?></span>
        </div>
        <div class="cont-row-size"><?php echo format_bytes($filesize); ?></div>
        <div class="cont-row-playlist">
          <?php if ($is_used): ?>
          <span class="badge on"><?php echo $c['in_playlist']; ?> playlist</span>
          <?php else: ?>
          <span class="badge-unused">Non usato</span>
          <?php endif; ?>
        </div>
        <div class="cont-row-date"><?php echo date('d/m/Y', strtotime($c['creato_il'])); ?></div>
        <div class="cont-row-actions">
          <?php if (!empty($tutte_cartelle)): ?>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="move">
            <input type="hidden" name="cont_id" value="<?php echo $c['id']; ?>">
            <select name="dest_cartella" class="prop-input" onchange="this.form.submit()" style="font-size:11px;padding:4px 6px;width:120px">
              <option value="">Sposta in…</option>
              <?php foreach ($tutte_cartelle as $fc): ?>
              <option value="<?php echo $fc['id']; ?>" <?php echo (isset($c['cartella_id']) && (int)$c['cartella_id']===(int)$fc['id'])?'selected':''; ?>><?php echo htmlspecialchars($fc['nome']); ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <?php endif; ?>
          <button class="btn-sm" onclick="renameContent(<?php echo $c['id']; ?>,'<?php echo addslashes($c['nome']); ?>')">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
          </button>
          <?php if (isAdmin()): ?>
          <form method="POST" onsubmit="return confirm('Eliminare questo contenuto?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="cont_id" value="<?php echo $c['id']; ?>">
            <button type="submit" class="btn-sm danger">
              <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php endif; ?>
  </main>
</div>

<!-- RENAME MODAL -->
<div id="rename-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;display:none;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:400px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:6px">Rinomina contenuto</div>
    <form method="POST" style="margin-top:16px">
      <input type="hidden" name="action" value="rename">
      <input type="hidden" name="cont_id" id="rename-cont-id">
      <div class="form-group">
        <label class="form-label">Nome</label>
        <input type="text" name="nome" id="rename-cont-input" class="form-input" required>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('rename-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- NUOVA CARTELLA MODAL -->
<div id="folder-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:400px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:6px">Nuova cartella</div>
    <form method="POST" style="margin-top:16px">
      <input type="hidden" name="action" value="create_folder">
      <div class="form-group">
        <label class="form-label">Nome cartella</label>
        <input type="text" name="nome_cartella" class="form-input" placeholder="Es. Loghi sponsor" required>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('folder-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2">Crea</button>
      </div>
    </form>
  </div>
</div>

<!-- RINOMINA CARTELLA MODAL -->
<div id="rename-folder-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:400px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:6px">Rinomina cartella</div>
    <form method="POST" style="margin-top:16px">
      <input type="hidden" name="action" value="rename_folder">
      <input type="hidden" name="cartella_id" id="rename-folder-id">
      <div class="form-group">
        <label class="form-label">Nome</label>
        <input type="text" name="nome" id="rename-folder-input" class="form-input" required>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('rename-folder-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2">Salva</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
// ── Cartelle ─────────────────────────────────────────────────
function renameFolder(id, nome) {
  document.getElementById('rename-folder-id').value = id;
  document.getElementById('rename-folder-input').value = nome;
  document.getElementById('rename-folder-modal').style.display = 'flex';
}

// ── Vista grid/list ──────────────────────────────────────────
function setView(v) {
  localStorage.setItem('pb_cont_view', v);
  // Se non ci sono ancora contenuti ne' cartelle, questi due contenitori non
  // esistono nella pagina (il PHP non li stampa) — senza questo controllo lo
  // script si fermava qui, e tutto quello scritto dopo (drag&drop upload,
  // pulsante "Scegli file") non partiva mai.
  const grid = document.getElementById('view-grid');
  const list = document.getElementById('view-list');
  if (grid) grid.style.display = v === 'grid' ? '' : 'none';
  if (list) list.style.display = v === 'list' ? '' : 'none';
  document.querySelectorAll('.view-btn').forEach(b => b.classList.toggle('active', b.dataset.view === v));
}
// Ripristina vista preferita
(function(){ setView(localStorage.getItem('pb_cont_view') || 'grid'); })();

// ── Filtri ───────────────────────────────────────────────────
function setFilter(tipo) {
  document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
  event.target.classList.add('active');
  filterContents();
}

// ── Ricerca + filtro ─────────────────────────────────────────
function filterContents() {
  const q     = document.getElementById('cont-search').value.toLowerCase().trim();
  const tipo  = document.querySelector('.filter-tab.active')?.textContent.toLowerCase();
  const isAll = tipo?.startsWith('tutti');

  document.querySelectorAll('.cont-card, .cont-row').forEach(el => {
    const matchSearch = !q || el.dataset.search.includes(q);
    const matchType   = isAll || el.dataset.tipo === (tipo?.startsWith('video') ? 'video' : 'immagine');
    el.style.display  = matchSearch && matchType ? '' : 'none';
  });
}

document.getElementById('cont-search').addEventListener('input', filterContents);

// ── Rename modal ─────────────────────────────────────────────
function renameContent(id, nome) {
  document.getElementById('rename-cont-id').value = id;
  document.getElementById('rename-cont-input').value = nome;
  document.getElementById('rename-modal').style.display = 'flex';
}

function setDurata(id, durataAttuale) {
  const nuova = prompt('Per quanti secondi deve restare a schermo questa immagine in ADV?', durataAttuale);
  if (nuova === null) return; // annullato
  const secondi = parseInt(nuova, 10);
  if (!secondi || secondi <= 0) { alert('Inserisci un numero di secondi valido.'); return; }

  const form = document.createElement('form');
  form.method = 'POST';
  form.innerHTML = `
    <input type="hidden" name="action" value="set_durata">
    <input type="hidden" name="cont_id" value="${id}">
    <input type="hidden" name="durata" value="${secondi}">
  `;
  document.body.appendChild(form);
  form.submit();
}

// ── Upload con progress ──────────────────────────────────────
const zone    = document.getElementById('upload-zone');
const input   = document.getElementById('file-input');
const progList= document.getElementById('progress-list');

zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
  e.preventDefault();
  zone.classList.remove('dragover');
  handleFiles(e.dataTransfer.files);
});

input.addEventListener('change', () => handleFiles(input.files));

function handleFiles(files) {
  if (!files.length) return;
  progList.style.display = 'flex';

  const formData = new FormData();
  formData.append('action', 'upload');
  const cartellaIdField = document.querySelector('#upload-form input[name="cartella_id"]');
  formData.append('cartella_id', cartellaIdField ? cartellaIdField.value : '');

  Array.from(files).forEach((file, i) => {
    formData.append('files[]', file);
    // Crea progress item
    const item = document.createElement('div');
    item.className = 'progress-item';
    item.id = 'prog-' + i;
    item.innerHTML = `
      <div class="progress-name">${file.name}</div>
      <div class="progress-bar-wrap"><div class="progress-bar-fill" id="fill-${i}" style="width:0%"></div></div>
      <div class="progress-pct" id="pct-${i}">0%</div>
    `;
    progList.appendChild(item);
  });

  const xhr = new XMLHttpRequest();
  xhr.upload.addEventListener('progress', e => {
    if (e.lengthComputable) {
      const pct = Math.round(e.loaded / e.total * 100);
      Array.from(files).forEach((_, i) => {
        const fill = document.getElementById('fill-' + i);
        const pctEl = document.getElementById('pct-' + i);
        if (fill) fill.style.width = pct + '%';
        if (pctEl) pctEl.textContent = pct + '%';
      });
    }
  });
  xhr.addEventListener('load', () => {
    if (xhr.status === 200) {
      setTimeout(() => location.reload(), 600);
    }
  });
  xhr.open('POST', window.location.href);
  xhr.send(formData);
}
</script>
