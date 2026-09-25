<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Club & sedi';
$db  = getDB();
$tid = TENANT_ID;

$success = $error = '';

// ── Azioni POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $nome     = trim($_POST['nome'] ?? '');
        $indirizzo = trim($_POST['indirizzo'] ?? '');
        $note     = trim($_POST['note'] ?? '');
        $n_tv     = (int)($_POST['num_tv_totali'] ?? 0);
        $lat      = $_POST['lat'] !== '' ? (float)($_POST['lat'] ?? 0) : null;
        $lon      = $_POST['lon'] !== '' ? (float)($_POST['lon'] ?? 0) : null;
        $sheet_corsi = trim($_POST['sheet_url_corsi'] ?? '');

        if (!$nome) {
            $error = 'Il nome è obbligatorio.';
        } elseif ($action === 'create') {
            $db->prepare("INSERT INTO club_config (tenant_id, nome, num_tv_totali, indirizzo, note, lat, lon, sheet_url_corsi) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$tid, $nome, $n_tv, $indirizzo, $note, $lat, $lon, $sheet_corsi]);
            $success = "Sede \"$nome\" aggiunta.";
        } else {
            $id = (int)($_POST['club_id'] ?? 0);
            $db->prepare("UPDATE club_config SET nome=?, num_tv_totali=?, indirizzo=?, note=?, lat=?, lon=?, sheet_url_corsi=? WHERE id=? AND tenant_id=?")
               ->execute([$nome, $n_tv, $indirizzo, $note, $lat, $lon, $sheet_corsi, $id, $tid]);
            $success = 'Sede aggiornata.';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['club_id'] ?? 0);
        // Verifica che non ci siano dispositivi
        $stmt = $db->prepare("SELECT COUNT(*) FROM dispositivi WHERE tenant_id=? AND club=(SELECT nome FROM club_config WHERE id=?)");
        $stmt->execute([$tid, $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $error = 'Non puoi eliminare una sede con dispositivi attivi. Riassegna prima i dispositivi.';
        } else {
            $db->prepare("DELETE FROM club_config WHERE id=? AND tenant_id=?")->execute([$id, $tid]);
            $success = 'Sede eliminata.';
        }
    }

    // Geocode manuale: salva lat/lon su tutti i dispositivi di quel club
    if ($action === 'set_coords') {
        $nome = trim($_POST['club_nome'] ?? '');
        $lat  = (float)($_POST['lat'] ?? 0);
        $lon  = (float)($_POST['lon'] ?? 0);
        if ($nome && $lat && $lon) {
            $db->prepare("UPDATE dispositivi SET lat=?, lon=? WHERE tenant_id=? AND club=?")
               ->execute([$lat, $lon, $tid, $nome]);
            $db->prepare("UPDATE club_config SET indirizzo=COALESCE(NULLIF(indirizzo,''),indirizzo) WHERE tenant_id=? AND nome=?")
               ->execute([$tid, $nome]);
            $success = 'Coordinate salvate.';
        }
    }
}

// ── Sede selezionata ─────────────────────────────────────────
$sel_club = $_GET['club'] ?? '';

// ── Query club con stats ─────────────────────────────────────
$stmt = $db->prepare("
    SELECT
        cc.id, cc.nome, cc.indirizzo, cc.num_tv_totali, cc.note,
        cc.lat, cc.lon, cc.sheet_url_corsi,
        COUNT(d.id) AS num_player,
        COALESCE(SUM(d.numero_tv), 0) AS tv_player,
        SUM(CASE WHEN d.ultimo_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 ELSE 0 END) AS player_online
    FROM club_config cc
    LEFT JOIN dispositivi d ON d.club = cc.nome AND d.tenant_id = cc.tenant_id
    WHERE cc.tenant_id = ?
    GROUP BY cc.id
    ORDER BY cc.nome
");
$stmt->execute([$tid]);
$clubs = $stmt->fetchAll();

// ── Dispositivi per sede selezionata ─────────────────────────
$sel_dispositivi = [];
if ($sel_club) {
    $stmt = $db->prepare("
        SELECT d.*,
               CASE WHEN d.ultimo_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 'online' ELSE 'offline' END AS stato_live,
               TIMESTAMPDIFF(MINUTE, d.ultimo_ping, NOW()) AS minuti_fa
        FROM dispositivi d
        WHERE d.tenant_id = ? AND d.club = ?
        ORDER BY d.nome
    ");
    $stmt->execute([$tid, $sel_club]);
    $sel_dispositivi = $stmt->fetchAll();
}

// Stats globali
$tot_clubs   = count($clubs);
$tot_player  = array_sum(array_column($clubs, 'num_player'));
$tot_online  = array_sum(array_column($clubs, 'player_online'));
$tot_tv      = array_sum(array_column($clubs, 'tv_player'));

// Prepara dati mappa (club con coordinate)
$map_data = array_filter($clubs, fn($c) => $c['lat'] && $c['lon']);
$map_json = json_encode(array_values(array_map(fn($c) => [
    'nome'    => $c['nome'],
    'lat'     => (float)$c['lat'],
    'lon'     => (float)$c['lon'],
    'online'  => (int)$c['player_online'],
    'totale'  => (int)$c['num_player'],
    'tv'      => (int)$c['tv_player'],
    'indirizzo' => $c['indirizzo'],
], $map_data)));
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
/* Map */
#map { height: 380px; border-radius: 0; overflow: hidden; z-index: 1; background: #1a1a2e; }
#map.dark-tiles .leaflet-tile-pane { filter: invert(1) hue-rotate(180deg) brightness(0.92) contrast(0.9); }
.leaflet-popup-content-wrapper { border-radius: 10px !important; box-shadow: 0 4px 20px rgba(0,0,0,.15) !important; }
.map-popup { font-family: 'Inter', sans-serif; font-size: 13px; }
.map-popup-title { font-weight: 700; font-size: 14px; margin-bottom: 6px; color: #0d0d14; }
.map-popup-row { display: flex; justify-content: space-between; gap: 12px; font-size: 12px; color: #6b6b7a; margin-bottom: 3px; }
.map-popup-val { font-weight: 600; color: #0d0d14; }

/* Club cards */
.club-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap: 14px; }
.club-card { background: var(--surface); border: 1px solid var(--outline-var); border-radius: 14px; overflow: hidden; transition: all .18s; cursor: pointer; }
.club-card:hover { box-shadow: var(--shadow-md); border-color: var(--blue); }
.club-card.active { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,120,209,.1); }
.club-card-head { padding: 16px 18px; border-bottom: 1px solid var(--outline-var); display: flex; align-items: center; gap: 12px; }
.club-icon { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, var(--blue-bg), var(--violet-bg)); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.club-name { font-family: 'Hanken Grotesk', sans-serif; font-size: 15px; font-weight: 700; color: var(--on-surface); }
.club-addr { font-size: 11px; color: var(--on-variant); margin-top: 2px; }
.club-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 0; }
.club-stat { padding: 12px 14px; text-align: center; border-right: 1px solid var(--outline-var); }
.club-stat:last-child { border-right: none; }
.club-stat-val { font-family: 'Hanken Grotesk', sans-serif; font-size: 20px; font-weight: 700; color: var(--on-surface); }
.club-stat-lbl { font-size: 10px; font-weight: 600; color: var(--on-variant); text-transform: uppercase; letter-spacing: .05em; margin-top: 2px; }
.club-actions { padding: 10px 14px; border-top: 1px solid var(--outline-var); display: flex; gap: 6px; }
.club-dots { display: flex; gap: 4px; padding: 10px 14px; }
.cdot { width: 8px; height: 8px; border-radius: 50%; }

/* Device detail */
.device-detail { background: var(--surface); border: 1px solid var(--outline-var); border-radius: 14px; overflow: hidden; }
.device-detail-head { padding: 16px 20px; border-bottom: 1px solid var(--outline-var); display: flex; align-items: center; justify-content: space-between; }
</style>
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/topnav.php'; ?>
<div class="app">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main">

    <div class="page-head">
      <div>
        <div class="page-title">Club & sedi</div>
        <div class="page-sub"><?php echo $tot_clubs; ?> sedi · <?php echo $tot_player; ?> player · <?php echo $tot_tv; ?> TV</div>
      </div>
      <div class="head-actions">
        <button class="btn-primary" onclick="document.getElementById('create-modal').style.display='flex'">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Aggiungi sede
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

    <!-- METRICHE -->
    <div class="g12" style="margin-bottom:16px">
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Sedi totali</div>
        <div class="mval"><?php echo $tot_clubs; ?></div>
        <div class="msub">club attivi</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Player</div>
        <div class="mval"><?php echo $tot_player; ?></div>
        <div class="msub up"><?php echo $tot_online; ?> online</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">TV gestite</div>
        <div class="mval"><?php echo $tot_tv; ?></div>
        <div class="msub">schermi totali</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Uptime</div>
        <div class="mval"><?php echo $tot_player > 0 ? round($tot_online/$tot_player*100) : 0; ?>%</div>
        <div class="msub"><?php echo $tot_player - $tot_online; ?> offline</div>
        <div class="pbar"><div class="pfill" style="width:<?php echo $tot_player > 0 ? round($tot_online/$tot_player*100) : 0; ?>%"></div></div>
      </div>
    </div>

    <!-- MAPPA -->
    <div class="card" style="margin-bottom:16px;overflow:hidden">
      <!-- Search bar sopra la mappa -->
      <div style="padding:12px 16px;border-bottom:1px solid var(--outline-var);display:flex;align-items:center;gap:10px">
        <div class="search-box" style="flex:1;height:36px">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--on-variant)"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input type="text" id="map-search" placeholder="Cerca sede o indirizzo..." style="font-size:13px">
        </div>

      </div>
      <div id="map"></div>
      <?php if (empty($map_data)): ?>
      <div style="padding:16px;font-size:12px;color:var(--on-variant);text-align:center;border-top:1px solid var(--outline-var)">
        Nessuna sede con coordinate. Clicca su "Info" di una sede per aggiungere le coordinate GPS.
      </div>
      <?php endif; ?>
    </div>

    <!-- CLUB CARDS + DETTAGLIO -->
    <div class="g12">

      <!-- Club cards -->
      <div style="grid-column:span <?php echo $sel_club ? 5 : 12; ?>">
        <?php if (empty($clubs)): ?>
        <div class="card cp" style="text-align:center;padding:48px">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="margin:0 auto 14px;opacity:.3"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          <div style="font-size:16px;font-weight:700;margin-bottom:6px">Nessuna sede</div>
          <div style="font-size:13px;color:var(--on-variant);margin-bottom:16px">Aggiungi la prima sede</div>
          <button class="btn-primary" style="margin:0 auto" onclick="document.getElementById('create-modal').style.display='flex'">Aggiungi sede</button>
        </div>
        <?php else: ?>
        <div class="club-grid">
          <?php foreach ($clubs as $club): ?>
          <div class="club-card <?php echo $sel_club===$club['nome']?'active':''; ?>">
            <div class="club-card-head">
              <div class="club-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
              </div>
              <div style="flex:1;min-width:0">
                <div class="club-name"><?php echo htmlspecialchars($club['nome']); ?></div>
                <?php if ($club['indirizzo']): ?>
                <div class="club-addr"><?php echo htmlspecialchars($club['indirizzo']); ?></div>
                <?php endif; ?>
              </div>
              <!-- Status dots -->
              <div style="display:flex;gap:3px">
                <?php for ($i = 0; $i < $club['num_player']; $i++): ?>
                <div class="cdot" style="background:<?php echo $i < $club['player_online'] ? '#22c55e' : 'var(--outline)'; ?>"></div>
                <?php endfor; ?>
              </div>
            </div>

            <div class="club-stats">
              <div class="club-stat">
                <div class="club-stat-val" style="color:<?php echo $club['player_online']==$club['num_player']&&$club['num_player']>0?'var(--success)':($club['player_online']<$club['num_player']&&$club['player_online']>0?'var(--warn)':'var(--on-surface)'); ?>">
                  <?php echo $club['player_online']; ?>/<?php echo $club['num_player']; ?>
                </div>
                <div class="club-stat-lbl">Online</div>
              </div>
              <div class="club-stat">
                <div class="club-stat-val"><?php echo $club['tv_player']; ?></div>
                <div class="club-stat-lbl">TV</div>
              </div>
              <div class="club-stat">
                <div class="club-stat-val"><?php echo $club['num_tv_totali'] ?: '—'; ?></div>
                <div class="club-stat-lbl">TV totali</div>
              </div>
            </div>

            <div class="club-actions">
              <a href="/club.php?club=<?php echo urlencode($club['nome']); ?>" class="btn-sm" style="flex:1;justify-content:center">
                <?php echo $sel_club===$club['nome'] ? 'Chiudi' : 'Dispositivi'; ?>
              </a>
              <button class="btn-sm" onclick="openEdit(<?php echo $club['id']; ?>,'<?php echo addslashes($club['nome']); ?>','<?php echo addslashes($club['indirizzo']??''); ?>',<?php echo (int)$club['num_tv_totali']; ?>,'<?php echo addslashes($club['note']??''); ?>',<?php echo $club['lat']!==null?$club['lat']:'null'; ?>,<?php echo $club['lon']!==null?$club['lon']:'null'; ?>,'<?php echo addslashes($club['sheet_url_corsi']??''); ?>')">
                Info
              </button>
              <form method="POST" onsubmit="return confirm('Eliminare questa sede?')" style="margin-left:auto">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                <button type="submit" class="btn-sm danger">Elimina</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Dettaglio sede selezionata -->
      <?php if ($sel_club && !empty($sel_dispositivi)): ?>
      <div style="grid-column:span 7">
        <div class="device-detail">
          <div class="device-detail-head">
            <div>
              <div style="font-family:'Hanken Grotesk',sans-serif;font-size:16px;font-weight:700;color:var(--on-surface)"><?php echo htmlspecialchars($sel_club); ?></div>
              <div style="font-size:12px;color:var(--on-variant);margin-top:2px"><?php echo count($sel_dispositivi); ?> player · <?php echo count(array_filter($sel_dispositivi, fn($d)=>$d['stato_live']==='online')); ?> online</div>
            </div>
            <a href="/club.php" class="btn-sm">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              Chiudi
            </a>
          </div>

          <?php foreach ($sel_dispositivi as $d):
            $is_online = $d['stato_live'] === 'online';
            $ping = $d['ultimo_ping'] ? human_time_diff($d['ultimo_ping']) : 'Mai';
          ?>
          <div style="padding:14px 20px;border-bottom:1px solid var(--outline-var);display:flex;align-items:center;gap:12px">
            <div class="dot <?php echo $is_online?'on':'off'; ?>" style="<?php echo !$is_online?'animation:none':''; ?>"></div>
            <div style="flex:1">
              <div style="font-size:13px;font-weight:500;color:var(--on-surface)"><?php echo htmlspecialchars($d['nome']); ?></div>
              <div style="font-size:11px;color:var(--on-variant);margin-top:2px">
                <?php echo strtoupper($d['hw_type']??''); ?>
                <?php if ($d['numero_tv']): ?> · <?php echo $d['numero_tv']; ?> TV<?php endif; ?>
                · <?php echo $ping; ?>
              </div>
            </div>
            <span class="badge <?php echo $is_online?'on':'off'; ?>"><?php echo $is_online?'Online':'Offline'; ?></span>
            <a href="/dispositivi.php" class="btn-sm">Gestisci</a>
          </div>
          <?php endforeach; ?>

          <?php if ($sel_club): ?>
          <!-- Imposta coordinate -->
          <div style="padding:16px 20px">
            <div style="font-size:12px;font-weight:600;color:var(--on-variant);margin-bottom:10px;text-transform:uppercase;letter-spacing:.05em">Coordinate GPS</div>
            <form method="POST" style="display:flex;gap:8px;align-items:flex-end">
              <input type="hidden" name="action" value="set_coords">
              <input type="hidden" name="club_nome" value="<?php echo htmlspecialchars($sel_club); ?>">
              <div style="flex:1">
                <label class="form-label">Latitudine</label>
                <input type="number" step="0.000001" name="lat" class="form-input" placeholder="45.4654" value="<?php echo $sel_dispositivi[0]['lat'] ?? ''; ?>">
              </div>
              <div style="flex:1">
                <label class="form-label">Longitudine</label>
                <input type="number" step="0.000001" name="lon" class="form-input" placeholder="9.1859" value="<?php echo $sel_dispositivi[0]['lon'] ?? ''; ?>">
              </div>
              <button type="submit" class="btn-primary" style="white-space:nowrap">Salva su mappa</button>
            </form>
          </div>
          <?php endif; ?>

        </div>
      </div>
      <?php endif; ?>

    </div>

  </main>
</div>

<!-- CREATE MODAL -->
<div id="create-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:440px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:20px;font-weight:700;margin-bottom:20px">Aggiungi sede</div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label class="form-label">Nome sede *</label>
        <input type="text" name="nome" class="form-input" placeholder="Es. Soave" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Indirizzo</label>
        <input type="text" name="indirizzo" class="form-input" placeholder="Via Roma 1, Soave (VR)">
      </div>
      <div class="form-group">
        <label class="form-label">TV totali nella sede</label>
        <input type="number" name="num_tv_totali" class="form-input" value="0" min="0">
      </div>
      <div class="form-group">
        <label class="form-label">Note</label>
        <input type="text" name="note" class="form-input" placeholder="Opzionale">
      </div>
      <div style="border-top:1px solid var(--outline-var);margin:14px 0"></div>
      <div style="font-size:11px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px">Dati letti dai widget (opzionali)</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group">
          <label class="form-label">Latitudine</label>
          <input type="number" step="0.000001" name="lat" class="form-input" placeholder="Es. 45.400">
        </div>
        <div class="form-group">
          <label class="form-label">Longitudine</label>
          <input type="number" step="0.000001" name="lon" class="form-input" placeholder="Es. 11.100">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Foglio Google Corsi Live</label>
        <input type="url" name="sheet_url_corsi" class="form-input" placeholder="https://docs.google.com/...">
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('create-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Aggiungi</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div id="edit-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:440px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:20px;font-weight:700;margin-bottom:20px">Modifica sede</div>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="club_id" id="edit-club-id">
      <div class="form-group">
        <label class="form-label">Nome sede *</label>
        <input type="text" name="nome" id="edit-nome" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">Indirizzo</label>
        <input type="text" name="indirizzo" id="edit-indirizzo" class="form-input">
      </div>
      <div class="form-group">
        <label class="form-label">TV totali nella sede</label>
        <input type="number" name="num_tv_totali" id="edit-tv" class="form-input" min="0">
      </div>
      <div class="form-group">
        <label class="form-label">Note</label>
        <input type="text" name="note" id="edit-note" class="form-input">
      </div>
      <div style="border-top:1px solid var(--outline-var);margin:14px 0"></div>
      <div style="font-size:11px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px">Dati letti dai widget (opzionali)</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group">
          <label class="form-label">Latitudine</label>
          <input type="number" step="0.000001" name="lat" id="edit-lat" class="form-input" placeholder="Es. 45.400">
        </div>
        <div class="form-group">
          <label class="form-label">Longitudine</label>
          <input type="number" step="0.000001" name="lon" id="edit-lon" class="form-input" placeholder="Es. 11.100">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Foglio Google Corsi Live</label>
        <input type="url" name="sheet_url_corsi" id="edit-sheet" class="form-input" placeholder="https://docs.google.com/...">
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('edit-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Salva</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Leaflet JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
window.addEventListener('load', function() {
// ── Mappa OpenStreetMap ───────────────────────────────────────
const mapData = <?php echo $map_json; ?>;
const isDark  = document.documentElement.classList.contains('dark');

const map = L.map('map', { zoomControl: true, preferCanvas: true });

// Tile layer — OpenStreetMap standard, gratuito e senza API key.
// Il tema scuro e' simulato con un filtro CSS (vedi #map.dark-tiles
// nello style sopra), non con un secondo set di tile a pagamento.
const osmTile = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap contributors', maxZoom: 19, subdomains: 'abc'
});
osmTile.addTo(map);

function applyTile() {
  const dark = document.documentElement.classList.contains('dark');
  document.getElementById('map').classList.toggle('dark-tiles', dark);
}
applyTile();
setTimeout(() => map.invalidateSize(), 200);

const allMarkers = [];

// Aggiorna tile quando cambia tema
const origToggle = window.toggleTheme;
window.toggleTheme = function() { origToggle(); setTimeout(applyTile, 50); };

// Custom icon
function makeIcon(color) {
  return L.divIcon({
    html: `<div style="width:28px;height:28px;background:${color};border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="white"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
    </div>`,
    className: '',
    iconSize: [28, 28],
    iconAnchor: [14, 14],
    popupAnchor: [0, -16]
  });
}

if (mapData.length > 0) {
  const bounds = [];
  mapData.forEach(club => {
    const allOnline = club.online === club.totale && club.totale > 0;
    const anyOnline = club.online > 0;
    const color = allOnline ? '#22c55e' : (anyOnline ? '#f59e0b' : '#9ca3af');

    const marker = L.marker([club.lat, club.lon], { icon: makeIcon(color), title: club.nome }).addTo(map);
    allMarkers.push(marker);
    marker.bindPopup(`
      <div class="map-popup">
        <div class="map-popup-title">${club.nome}</div>
        ${club.indirizzo ? `<div style="font-size:11px;color:#6b6b7a;margin-bottom:8px">${club.indirizzo}</div>` : ''}
        <div class="map-popup-row"><span>Player online</span><span class="map-popup-val" style="color:${color}">${club.online}/${club.totale}</span></div>
        <div class="map-popup-row"><span>TV gestite</span><span class="map-popup-val">${club.tv}</span></div>
      </div>
    `);
    bounds.push([club.lat, club.lon]);
  });

  if (bounds.length === 1) {
    map.setView(bounds[0], 13);
  } else {
    map.fitBounds(bounds, { padding: [40, 40] });
  }
} else {
  // Default Italia centro
  map.setView([42.5, 12.5], 6);
}

// ── Ricerca mappa ────────────────────────────────────────────
document.getElementById('map-search')?.addEventListener('input', function() {
  const q = this.value.toLowerCase().trim();
  if (!q) { allMarkers.forEach(m => m.setOpacity(1)); return; }

  allMarkers.forEach(m => {
    const name = (m.options.title || '').toLowerCase();
    m.setOpacity(name.includes(q) ? 1 : 0.2);
    if (name.includes(q)) m.openPopup();
  });
});

}); // end window.load

// ── Edit modal ────────────────────────────────────────────────
function openEdit(id, nome, indirizzo, tv, note, lat, lon, sheetCorsi) {
  document.getElementById('edit-club-id').value = id;
  document.getElementById('edit-nome').value = nome;
  document.getElementById('edit-indirizzo').value = indirizzo;
  document.getElementById('edit-tv').value = tv;
  document.getElementById('edit-note').value = note;
  document.getElementById('edit-lat').value = lat || '';
  document.getElementById('edit-lon').value = lon || '';
  document.getElementById('edit-sheet').value = sheetCorsi || '';
  document.getElementById('edit-modal').style.display = 'flex';
}
</script>

<?php
function human_time_diff(string $datetime): string {
  $diff = time() - strtotime($datetime);
  if ($diff < 60)    return $diff . 's fa';
  if ($diff < 3600)  return round($diff/60) . ' min fa';
  if ($diff < 86400) return round($diff/3600) . 'h fa';
  return round($diff/86400) . 'g fa';
}
?>
