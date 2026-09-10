<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Playlist';
$db  = getDB();
$tid = TENANT_ID;

$success = $error = '';

// ── Azioni POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CREA playlist
    if ($action === 'create') {
        $nome = trim($_POST['nome'] ?? '');
        $tipo = $_POST['tipo'] ?? 'standard';
        $ins_id = (int)($_POST['inserzionista_id'] ?? 0) ?: null;
        if ($nome) {
            $db->prepare("INSERT INTO playlist (tenant_id, nome, creato_il) VALUES (?,?,NOW())")
               ->execute([$tid, $nome]);
            $pid = $db->lastInsertId();
            // Se ADV con inserzionista, salva in impostazioni playlist
            if ($tipo === 'adv' && $ins_id) {
                $db->prepare("INSERT INTO impostazioni (tenant_id, chiave, valore) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)")
                   ->execute([$tid, "playlist_{$pid}_tipo", 'adv']);
                $db->prepare("INSERT INTO impostazioni (tenant_id, chiave, valore) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)")
                   ->execute([$tid, "playlist_{$pid}_inserzionista", $ins_id]);
            }
            $success = "Playlist \"$nome\" creata.";
        } else {
            $error = 'Il nome è obbligatorio.';
        }
    }

    // ELIMINA playlist
    if ($action === 'delete') {
        $pid = (int)($_POST['playlist_id'] ?? 0);
        $db->prepare("DELETE FROM playlist WHERE id = ? AND tenant_id = ?")->execute([$pid, $tid]);
        $success = 'Playlist eliminata.';
    }

    // RINOMINA playlist
    if ($action === 'rename') {
        $pid  = (int)($_POST['playlist_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if ($nome) {
            $db->prepare("UPDATE playlist SET nome = ? WHERE id = ? AND tenant_id = ?")->execute([$nome, $pid, $tid]);
            $success = 'Playlist rinominata.';
        }
    }

    // AGGIUNGI contenuto a playlist
    if ($action === 'add_item') {
        $pid    = (int)($_POST['playlist_id'] ?? 0);
        $cid    = (int)($_POST['contenuto_id'] ?? 0);
        $d_ini  = $_POST['data_inizio'] ?: null;
        $d_fine = $_POST['data_fine'] ?: null;
        // Prendi ordine massimo
        $stmt = $db->prepare("SELECT COALESCE(MAX(ordine),0)+1 FROM playlist_items WHERE playlist_id = ?");
        $stmt->execute([$pid]);
        $ordine = (int)$stmt->fetchColumn();
        $db->prepare("INSERT INTO playlist_items (playlist_id, contenuto_id, ordine, data_inizio, data_fine) VALUES (?,?,?,?,?)")
           ->execute([$pid, $cid, $ordine, $d_ini, $d_fine]);
        $success = 'Contenuto aggiunto.';
    }

    // RIMUOVI contenuto da playlist
    if ($action === 'remove_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $db->prepare("DELETE FROM playlist_items WHERE id = ?")->execute([$item_id]);
        $success = 'Contenuto rimosso.';
    }

    // RIORDINA contenuti (chiamata AJAX)
    if ($action === 'reorder') {
        $items = json_decode($_POST['items'] ?? '[]', true);
        foreach ($items as $idx => $item_id) {
            $db->prepare("UPDATE playlist_items SET ordine = ? WHERE id = ?")->execute([$idx, $item_id]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // AGGIORNA date item
    if ($action === 'update_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $d_ini   = $_POST['data_inizio'] ?: null;
        $d_fine  = $_POST['data_fine'] ?: null;
        $db->prepare("UPDATE playlist_items SET data_inizio=?, data_fine=? WHERE id=?")->execute([$d_ini, $d_fine, $item_id]);
        $success = 'Date aggiornate.';
    }

    // ASSEGNA inserzionista
    if ($action === 'assign_ins') {
        $pid    = (int)($_POST['playlist_id'] ?? 0);
        $ins_id = (int)($_POST['inserzionista_id'] ?? 0) ?: null;
        $db->prepare("INSERT INTO impostazioni (tenant_id, chiave, valore) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)")
           ->execute([$tid, "playlist_{$pid}_tipo", $ins_id ? 'adv' : 'standard']);
        if ($ins_id) {
            $db->prepare("INSERT INTO impostazioni (tenant_id, chiave, valore) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)")
               ->execute([$tid, "playlist_{$pid}_inserzionista", $ins_id]);
        } else {
            $db->prepare("DELETE FROM impostazioni WHERE tenant_id=? AND chiave=?")->execute([$tid, "playlist_{$pid}_inserzionista"]);
        }
        $success = 'Inserzionista aggiornato.';
    }
}

// ── Playlist aperta (dettaglio) ──────────────────────────────
$open_pid = (int)($_GET['id'] ?? 0);
$open_playlist = null;
$open_items = [];
$all_contenuti = [];

if ($open_pid) {
    $stmt = $db->prepare("SELECT * FROM playlist WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$open_pid, $tid]);
    $open_playlist = $stmt->fetch();

    if ($open_playlist) {
        $stmt = $db->prepare("
            SELECT pi.*, c.nome, c.tipo, c.file, c.durata
            FROM playlist_items pi
            JOIN contenuti c ON c.id = pi.contenuto_id
            WHERE pi.playlist_id = ?
            ORDER BY pi.ordine ASC
        ");
        $stmt->execute([$open_pid]);
        $open_items = $stmt->fetchAll();

        // Tutti i contenuti non ancora in questa playlist
        $in_ids = array_column($open_items, 'contenuto_id') ?: [0];
        $placeholders = implode(',', array_fill(0, count($in_ids), '?'));
        $stmt = $db->prepare("SELECT id, nome, tipo, file FROM contenuti WHERE tenant_id = ? AND id NOT IN ($placeholders) ORDER BY nome");
        $stmt->execute(array_merge([$tid], $in_ids));
        $all_contenuti = $stmt->fetchAll();

        // Meta inserzionista
        $stmt = $db->prepare("SELECT valore FROM impostazioni WHERE tenant_id=? AND chiave=?");
        $stmt->execute([$tid, "playlist_{$open_pid}_tipo"]);
        $open_playlist['tipo'] = $stmt->fetchColumn() ?: 'standard';

        $stmt->execute([$tid, "playlist_{$open_pid}_inserzionista"]);
        $open_playlist['inserzionista_id'] = (int)($stmt->fetchColumn() ?: 0);
    }
}

// ── Lista playlist ───────────────────────────────────────────
$stmt = $db->prepare("
    SELECT p.*,
           COUNT(pi.id) AS num_items,
           GROUP_CONCAT(c.file ORDER BY pi.ordine SEPARATOR '|') AS previews
    FROM playlist p
    LEFT JOIN playlist_items pi ON pi.playlist_id = p.id
    LEFT JOIN contenuti c ON c.id = pi.contenuto_id
    WHERE p.tenant_id = ?
    GROUP BY p.id
    ORDER BY p.creato_il DESC
");
$stmt->execute([$tid]);
$playlists = $stmt->fetchAll();

// ── Inserzionisti disponibili ────────────────────────────────
$stmt = $db->prepare("SELECT id, ragione_sociale FROM inserzionisti WHERE tenant_id = ? AND attivo = 1 ORDER BY ragione_sociale");
$stmt->execute([$tid]);
$inserzionisti = $stmt->fetchAll();

$upload_dir = __DIR__ . '/uploads/';
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
.pl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
.pl-card{background:var(--surface);border:1px solid var(--outline-var);border-radius:14px;overflow:hidden;transition:all .18s;cursor:pointer}
.pl-card:hover{box-shadow:var(--shadow-md);border-color:var(--blue)}
.pl-card.active-card{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,120,209,.12)}
.pl-previews{display:grid;grid-template-columns:repeat(3,1fr);gap:2px;height:100px;overflow:hidden;background:var(--surface-mid)}
.pl-preview-thumb{background:var(--surface-mid);overflow:hidden}
.pl-preview-thumb img{width:100%;height:100%;object-fit:cover}
.pl-preview-empty{height:100px;background:linear-gradient(135deg,var(--blue-bg),var(--violet-bg));display:flex;align-items:center;justify-content:center}
.pl-info{padding:14px 16px}
.pl-name{font-size:14px;font-weight:600;color:var(--on-surface);margin-bottom:4px}
.pl-meta{font-size:11px;color:var(--on-variant);display:flex;align-items:center;gap:8px}
.pl-actions{padding:10px 16px;border-top:1px solid var(--outline-var);display:flex;gap:6px}

/* Detail panel */
.detail-panel{background:var(--surface);border:1px solid var(--outline-var);border-radius:14px;overflow:hidden}
.detail-head{padding:18px 20px;border-bottom:1px solid var(--outline-var);display:flex;align-items:center;gap:12px}
.detail-title{font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;color:var(--on-surface);flex:1}
.detail-body{padding:20px}

/* Item list */
.item-list{display:flex;flex-direction:column;gap:6px;margin-bottom:20px}
.item-row{background:var(--surface-low);border:1px solid var(--outline-var);border-radius:8px;display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:grab;transition:all .15s}
.item-row:hover{border-color:var(--blue);background:var(--surface)}
.item-row.dragging{opacity:.5;cursor:grabbing}
.item-thumb{width:56px;height:32px;border-radius:4px;overflow:hidden;background:var(--surface-mid);flex-shrink:0}
.item-thumb img{width:100%;height:100%;object-fit:cover}
.item-thumb-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
.item-name{font-size:13px;font-weight:500;color:var(--on-surface);flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.item-dates{font-size:10px;color:var(--on-variant);white-space:nowrap}
.item-expired{color:var(--error)}
.drag-handle{color:var(--outline);cursor:grab;flex-shrink:0}

/* Add content */
.add-section{border-top:1px solid var(--outline-var);padding-top:16px}
.add-title{font-size:13px;font-weight:600;color:var(--on-surface);margin-bottom:10px}
.add-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;max-height:220px;overflow-y:auto;padding-right:4px}
.add-item{border:1px solid var(--outline-var);border-radius:8px;overflow:hidden;cursor:pointer;transition:all .15s}
.add-item:hover{border-color:var(--blue);box-shadow:0 0 0 2px rgba(37,120,209,.1)}
.add-item-thumb{aspect-ratio:16/9;background:var(--surface-mid);overflow:hidden}
.add-item-thumb img{width:100%;height:100%;object-fit:cover}
.add-item-name{font-size:10px;font-weight:500;color:var(--on-surface);padding:5px 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* ADV badge */
.adv-badge{display:inline-flex;align-items:center;gap:4px;background:var(--violet-bg);color:var(--violet-text);font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px}
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
        <div class="page-title">Playlist</div>
        <div class="page-sub"><?php echo count($playlists); ?> playlist · gestisci sequenze di contenuti</div>
      </div>
      <div class="head-actions">
        <button class="btn-primary" onclick="document.getElementById('create-modal').style.display='flex'">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nuova playlist
        </button>
      </div>
    </div>

    <?php if ($success): ?>
    <div style="background:var(--success-bg);border:1px solid rgba(34,197,94,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--success);margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="background:var(--error-bg);border:1px solid rgba(192,24,12,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--error);margin-bottom:16px">
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <?php if ($open_playlist): ?>
    <!-- ── DETTAGLIO PLAYLIST ──────────────────────────────── -->
    <div style="margin-bottom:16px">
      <a href="/playlist.php" class="btn-ghost" style="display:inline-flex">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Tutte le playlist
      </a>
    </div>

    <div class="g12">
      <!-- Contenuti della playlist -->
      <div class="detail-panel" style="grid-column:span 8">
        <div class="detail-head">
          <div>
            <div class="detail-title"><?php echo htmlspecialchars($open_playlist['nome']); ?></div>
            <div style="font-size:11px;color:var(--on-variant);margin-top:2px">
              <?php echo count($open_items); ?> contenuti
              <?php if ($open_playlist['tipo'] === 'adv'): ?>
              · <span class="adv-badge">✦ ADV</span>
              <?php endif; ?>
            </div>
          </div>
          <button class="btn-ghost" onclick="document.getElementById('rename-modal').style.display='flex'">Rinomina</button>
          <form method="POST" onsubmit="return confirm('Eliminare questa playlist?')" style="margin-left:4px">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="playlist_id" value="<?php echo $open_pid; ?>">
            <button type="submit" class="btn-sm danger">Elimina</button>
          </form>
        </div>

        <div class="detail-body">
          <!-- Lista contenuti ordinabili -->
          <?php if (empty($open_items)): ?>
          <div style="text-align:center;padding:32px;color:var(--on-variant)">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="margin:0 auto 12px;opacity:.3"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            <div style="font-size:14px;font-weight:600;margin-bottom:4px">Nessun contenuto</div>
            <div style="font-size:12px">Aggiungi contenuti dalla lista a destra</div>
          </div>
          <?php else: ?>
          <div class="item-list" id="sortable-list">
            <?php foreach ($open_items as $item):
              $ext = strtolower(pathinfo($item['file'], PATHINFO_EXTENSION));
              $is_img = in_array($ext, ['jpg','jpeg','png','webp','gif']);
              $is_expired = $item['data_fine'] && strtotime($item['data_fine']) < time();
              $expires_soon = $item['data_fine'] && strtotime($item['data_fine']) < strtotime('+7 days') && !$is_expired;
            ?>
            <div class="item-row <?php echo $is_expired ? 'opacity-50' : ''; ?>" data-id="<?php echo $item['id']; ?>">
              <div class="drag-handle">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>
              </div>
              <div class="item-thumb">
                <?php if ($is_img): ?>
                <img src="/uploads/<?php echo htmlspecialchars($item['file']); ?>" alt="" loading="lazy">
                <?php else: ?>
                <div class="item-thumb-placeholder">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--violet)" stroke-width="1.8"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </div>
                <?php endif; ?>
              </div>
              <div style="flex:1;min-width:0">
                <div class="item-name"><?php echo htmlspecialchars($item['nome']); ?></div>
                <div class="item-dates <?php echo $is_expired ? 'item-expired' : ($expires_soon ? '' : ''); ?>">
                  <?php if ($item['data_inizio'] || $item['data_fine']): ?>
                    <?php echo $item['data_inizio'] ? date('d/m/Y', strtotime($item['data_inizio'])) : '∞'; ?>
                    →
                    <?php echo $item['data_fine'] ? date('d/m/Y', strtotime($item['data_fine'])) : '∞'; ?>
                    <?php if ($is_expired): ?> · <span style="color:var(--error)">Scaduto</span><?php endif; ?>
                    <?php if ($expires_soon): ?> · <span style="color:var(--warn)">Scade presto</span><?php endif; ?>
                  <?php else: ?>
                    Sempre attivo
                  <?php endif; ?>
                </div>
              </div>
              <button class="btn-sm" onclick="editDates(<?php echo $item['id']; ?>,'<?php echo $item['data_inizio'] ?? ''; ?>','<?php echo $item['data_fine'] ?? ''; ?>')" title="Date validità">
                Date
              </button>
              <form method="POST" onsubmit="return confirm('Rimuovere dalla playlist?')" style="margin:0">
                <input type="hidden" name="action" value="remove_item">
                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                <button type="submit" class="btn-sm danger">Rimuovi</button>
              </form>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Aggiungi contenuti -->
          <?php if (!empty($all_contenuti)): ?>
          <div class="add-section">
            <div class="add-title">Aggiungi contenuti</div>
            <div class="add-grid">
              <?php foreach ($all_contenuti as $cont):
                $ext = strtolower(pathinfo($cont['file'], PATHINFO_EXTENSION));
                $is_img = in_array($ext, ['jpg','jpeg','png','webp','gif']);
              ?>
              <form method="POST">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="playlist_id" value="<?php echo $open_pid; ?>">
                <input type="hidden" name="contenuto_id" value="<?php echo $cont['id']; ?>">
                <button type="submit" class="add-item" style="width:100%;border:none;background:none;padding:0;text-align:left">
                  <div class="add-item-thumb">
                    <?php if ($is_img): ?>
                    <img src="/uploads/<?php echo htmlspecialchars($cont['file']); ?>" alt="" loading="lazy">
                    <?php else: ?>
                    <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--violet-bg),var(--blue-bg));display:flex;align-items:center;justify-content:center">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--violet)" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    </div>
                    <?php endif; ?>
                  </div>
                  <div class="add-item-name"><?php echo htmlspecialchars($cont['nome']); ?></div>
                </button>
              </form>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Pannello laterale info -->
      <div style="grid-column:span 4;display:flex;flex-direction:column;gap:14px">

        <!-- Info playlist -->
        <div class="card cp">
          <div class="ch"><div class="ct">Informazioni</div></div>
          <div class="row" style="padding:8px 0">
            <div class="rsub" style="flex:1">Tipo</div>
            <span class="badge <?php echo $open_playlist['tipo']==='adv' ? 'vio' : 'blue'; ?>">
              <?php echo $open_playlist['tipo'] === 'adv' ? 'ADV' : 'Standard'; ?>
            </span>
          </div>
          <div class="row" style="padding:8px 0">
            <div class="rsub" style="flex:1">Contenuti</div>
            <span style="font-size:13px;font-weight:600;color:var(--on-surface)"><?php echo count($open_items); ?></span>
          </div>
          <div class="row" style="padding:8px 0;border:none">
            <div class="rsub" style="flex:1">Creata il</div>
            <span style="font-size:12px;color:var(--on-variant)"><?php echo date('d/m/Y', strtotime($open_playlist['creato_il'])); ?></span>
          </div>
        </div>

        <!-- Inserzionista -->
        <div class="card cp">
          <div class="ch"><div class="ct">Inserzionista ADV</div></div>
          <form method="POST">
            <input type="hidden" name="action" value="assign_ins">
            <input type="hidden" name="playlist_id" value="<?php echo $open_pid; ?>">
            <div class="form-group" style="margin-bottom:12px">
              <select name="inserzionista_id" class="form-input form-select">
                <option value="0">Nessuno (playlist standard)</option>
                <?php foreach ($inserzionisti as $ins): ?>
                <option value="<?php echo $ins['id']; ?>" <?php echo $open_playlist['inserzionista_id'] == $ins['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($ins['ragione_sociale']); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center">
              Salva assegnazione
            </button>
          </form>
          <?php if (empty($inserzionisti)): ?>
          <div style="font-size:12px;color:var(--on-variant);margin-top:10px;text-align:center">
            <a href="/inserzionisti.php" style="color:var(--blue-text)">+ Aggiungi inserzionisti</a>
          </div>
          <?php endif; ?>
        </div>

        <!-- Dispositivi che usano questa playlist -->
        <div class="card cp">
          <div class="ch"><div class="ct">Usata da</div></div>
          <?php
          // Cerca dispositivi che hanno questa playlist nel profilo
          $stmt = $db->prepare("
              SELECT d.nome, d.club FROM dispositivi d
              JOIN profili p ON p.id = d.profilo_id
              JOIN profilo_regole pr ON pr.profilo_id = p.id
              WHERE pr.playlist_id = ? AND d.tenant_id = ?
              LIMIT 5
          ");
          $stmt->execute([$open_pid, $tid]);
          $used_by = $stmt->fetchAll();
          ?>
          <?php if (empty($used_by)): ?>
          <div style="font-size:12px;color:var(--on-variant)">Non assegnata a nessun dispositivo</div>
          <?php else: ?>
          <?php foreach ($used_by as $d): ?>
          <div class="row" style="padding:7px 0">
            <div class="dot on"></div>
            <div style="flex:1">
              <div class="rname"><?php echo htmlspecialchars($d['nome']); ?></div>
              <div class="rsub"><?php echo htmlspecialchars($d['club']); ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </div>

    <?php else: ?>
    <!-- ── LISTA PLAYLIST ──────────────────────────────────── -->
    <?php if (empty($playlists)): ?>
    <div class="card cp" style="text-align:center;padding:60px">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="margin:0 auto 16px;opacity:.3"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
      <div style="font-size:17px;font-weight:700;margin-bottom:8px">Nessuna playlist</div>
      <div style="font-size:13px;color:var(--on-variant);margin-bottom:20px">Crea la prima playlist per organizzare i tuoi contenuti</div>
      <button class="btn-primary" style="margin:0 auto" onclick="document.getElementById('create-modal').style.display='flex'">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuova playlist
      </button>
    </div>
    <?php else: ?>
    <div class="pl-grid">
      <?php foreach ($playlists as $pl):
        $previews = $pl['previews'] ? explode('|', $pl['previews']) : [];
        $previews = array_slice($previews, 0, 3);

        // Tipo playlist
        $stmt = $db->prepare("SELECT valore FROM impostazioni WHERE tenant_id=? AND chiave=?");
        $stmt->execute([$tid, "playlist_{$pl['id']}_tipo"]);
        $tipo = $stmt->fetchColumn() ?: 'standard';
      ?>
      <div class="pl-card <?php echo $open_pid == $pl['id'] ? 'active-card' : ''; ?>">
        <!-- Anteprime -->
        <?php if (empty($previews)): ?>
        <div class="pl-preview-empty">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="1.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </div>
        <?php else: ?>
        <div class="pl-previews" style="grid-template-columns:repeat(<?php echo count($previews); ?>,1fr)">
          <?php foreach ($previews as $pf):
            $ext = strtolower(pathinfo($pf, PATHINFO_EXTENSION));
            $is_img = in_array($ext, ['jpg','jpeg','png','webp','gif']);
          ?>
          <div class="pl-preview-thumb">
            <?php if ($is_img): ?>
            <img src="/uploads/<?php echo htmlspecialchars($pf); ?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
            <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--violet-bg),var(--blue-bg));display:flex;align-items:center;justify-content:center">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--violet)" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="pl-info">
          <div class="pl-name"><?php echo htmlspecialchars($pl['nome']); ?></div>
          <div class="pl-meta">
            <span><?php echo $pl['num_items']; ?> contenuti</span>
            <?php if ($tipo === 'adv'): ?>
            <span class="adv-badge">✦ ADV</span>
            <?php endif; ?>
            <span>· <?php echo date('d/m/Y', strtotime($pl['creato_il'])); ?></span>
          </div>
        </div>
        <div class="pl-actions">
          <a href="/playlist.php?id=<?php echo $pl['id']; ?>" class="btn-sm" style="flex:1;justify-content:center">
            Apri
          </a>
          <form method="POST" onsubmit="return confirm('Eliminare questa playlist?')" style="margin-left:auto">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="playlist_id" value="<?php echo $pl['id']; ?>">
            <button type="submit" class="btn-sm danger">Elimina</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </main>
</div>

<!-- CREATE MODAL -->
<div id="create-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:440px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:20px;font-weight:700;margin-bottom:6px">Nuova playlist</div>
    <div style="font-size:13px;color:var(--on-variant);margin-bottom:20px">Crea una nuova sequenza di contenuti</div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label class="form-label">Nome playlist *</label>
        <input type="text" name="nome" class="form-input" placeholder="Es. Sala pesi — Mattina" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-input form-select" id="tipo-select" onchange="toggleIns(this.value)">
          <option value="standard">Standard — contenuti propri</option>
          <option value="adv">ADV — contenuti inserzionista</option>
        </select>
      </div>
      <div class="form-group" id="ins-wrap" style="display:none">
        <label class="form-label">Inserzionista</label>
        <select name="inserzionista_id" class="form-input form-select">
          <option value="0">Seleziona…</option>
          <?php foreach ($inserzionisti as $ins): ?>
          <option value="<?php echo $ins['id']; ?>"><?php echo htmlspecialchars($ins['ragione_sociale']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('create-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Crea playlist</button>
      </div>
    </form>
  </div>
</div>

<!-- RENAME MODAL -->
<div id="rename-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:400px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:16px">Rinomina playlist</div>
    <form method="POST">
      <input type="hidden" name="action" value="rename">
      <input type="hidden" name="playlist_id" value="<?php echo $open_pid; ?>">
      <div class="form-group">
        <label class="form-label">Nuovo nome</label>
        <input type="text" name="nome" class="form-input" value="<?php echo htmlspecialchars($open_playlist['nome'] ?? ''); ?>" required>
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('rename-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- DATE MODAL -->
<div id="dates-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:400px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:6px">Date di validità</div>
    <div style="font-size:13px;color:var(--on-variant);margin-bottom:20px">Lascia vuoto per mostrare sempre</div>
    <form method="POST">
      <input type="hidden" name="action" value="update_item">
      <input type="hidden" name="item_id" id="dates-item-id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div class="form-group" style="margin:0">
          <label class="form-label">Data inizio</label>
          <input type="date" name="data_inizio" id="dates-inizio" class="form-input">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Data fine</label>
          <input type="date" name="data_fine" id="dates-fine" class="form-input">
        </div>
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('dates-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Salva date</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
// ── Tipo playlist toggle ──────────────────────────────────────
function toggleIns(val) {
  document.getElementById('ins-wrap').style.display = val === 'adv' ? 'block' : 'none';
}

// ── Date modal ────────────────────────────────────────────────
function editDates(id, ini, fine) {
  document.getElementById('dates-item-id').value = id;
  document.getElementById('dates-inizio').value = ini;
  document.getElementById('dates-fine').value = fine;
  document.getElementById('dates-modal').style.display = 'flex';
}

// ── Drag & drop riordino ──────────────────────────────────────
const list = document.getElementById('sortable-list');
if (list) {
  let dragging = null;

  list.querySelectorAll('.item-row').forEach(row => {
    row.draggable = true;

    row.addEventListener('dragstart', () => {
      dragging = row;
      setTimeout(() => row.classList.add('dragging'), 0);
    });

    row.addEventListener('dragend', () => {
      row.classList.remove('dragging');
      dragging = null;
      // Salva ordine via AJAX
      const ids = [...list.querySelectorAll('.item-row')].map(r => r.dataset.id);
      const form = new FormData();
      form.append('action', 'reorder');
      form.append('items', JSON.stringify(ids));
      fetch(window.location.href, { method: 'POST', body: form });
    });

    row.addEventListener('dragover', e => {
      e.preventDefault();
      if (!dragging || dragging === row) return;
      const rect = row.getBoundingClientRect();
      const mid  = rect.top + rect.height / 2;
      if (e.clientY < mid) {
        list.insertBefore(dragging, row);
      } else {
        list.insertBefore(dragging, row.nextSibling);
      }
    });
  });
}
</script>
