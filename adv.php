<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'ADV & Scheduling';
$db  = getDB();
$tid = TENANT_ID;

$success = $error = '';

// ── Dispositivo selezionato ──────────────────────────────────
$sel_disp = (int)($_GET['device'] ?? 0);

// ── Azioni POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Aggiungi regola ADV
    if ($action === 'add_adv') {
        $disp_id    = (int)($_POST['disp_id'] ?? 0);
        $playlist_id = (int)($_POST['playlist_id'] ?? 0);
        $loop_mode  = $_POST['loop_mode'] ?? 'alternata';
        $giorni_raw = $_POST['giorni'] ?? ['1','2','3','4','5','6','7'];
        $giorni     = is_array($giorni_raw) ? implode(',', $giorni_raw) : $giorni_raw;
        $ora_ini    = $_POST['ora_inizio'] ?: null;
        $ora_fine   = $_POST['ora_fine'] ?: null;
        $intervallo = (int)($_POST['intervallo_min'] ?? 20);
        $fullscreen = (int)isset($_POST['fullscreen']);

        $stmt = $db->prepare("SELECT token FROM dispositivi WHERE id=? AND tenant_id=?");
        $stmt->execute([$disp_id, $tid]);
        $token = $stmt->fetchColumn();

        if ($playlist_id && $token) {
            // adv_regole ha una foreign key verso adv_playlists (dove vive la modalita'
            // alternata/loop/smart di quella playlist ADV) — ma niente, prima d'ora,
            // creava quella riga quando si sceglieva una playlist per la prima volta.
            // La creiamo qui se manca, senza toccarla se esiste gia'.
            $db->prepare("INSERT IGNORE INTO adv_playlists (id, tenant_id, loop_mode) VALUES (?,?,?)")
               ->execute([$playlist_id, $tid, 'alternata']);

            // durata_adv_sec non esiste piu': la durata reale del blocco ADV e'
            // sempre la somma delle durate dei contenuti nella playlist (vedi
            // adv_status.php), niente da impostare a mano qui.
            $db->prepare("
                INSERT INTO adv_regole
                (tenant_id, playlist_id, nome, dispositivo_token, giorni, ora_inizio, ora_fine,
                 intervallo_min, fullscreen, attivo)
                VALUES (?,?,?,?,?,?,?,?,?,1)
            ")->execute([
                $tid, $playlist_id,
                'Regola ADV — ' . date('d/m/Y'),
                $token, $giorni, $ora_ini, $ora_fine,
                $intervallo, $fullscreen
            ]);

            // Aggiorna loop_mode sulla playlist ADV
            $db->prepare("UPDATE adv_playlists SET loop_mode=? WHERE id=? AND tenant_id=?")
               ->execute([$loop_mode, $playlist_id, $tid]);

            $success = 'Regola ADV aggiunta.';
        }
        $sel_disp = $disp_id;
    }

    // Modifica regola ADV esistente
    if ($action === 'edit_adv') {
        $adv_id      = (int)($_POST['adv_id'] ?? 0);
        $disp_id     = (int)($_POST['disp_id'] ?? 0);
        $playlist_id = (int)($_POST['playlist_id'] ?? 0);
        $loop_mode   = $_POST['loop_mode'] ?? 'alternata';
        $giorni_raw  = $_POST['giorni'] ?? ['1','2','3','4','5','6','7'];
        $giorni      = is_array($giorni_raw) ? implode(',', $giorni_raw) : $giorni_raw;
        $ora_ini     = $_POST['ora_inizio'] ?: null;
        $ora_fine    = $_POST['ora_fine'] ?: null;
        $intervallo  = (int)($_POST['intervallo_min'] ?? 20);
        $fullscreen  = (int)isset($_POST['fullscreen']);

        if ($adv_id && $playlist_id) {
            // Stessa garanzia di add_adv: crea la riga in adv_playlists se manca
            // (es. l'utente ha cambiato la playlist ADV scegliendone una nuova)
            $db->prepare("INSERT IGNORE INTO adv_playlists (id, tenant_id, loop_mode) VALUES (?,?,?)")
               ->execute([$playlist_id, $tid, $loop_mode]);

            $db->prepare("
                UPDATE adv_regole
                SET playlist_id=?, giorni=?, ora_inizio=?, ora_fine=?,
                    intervallo_min=?, fullscreen=?
                WHERE id=? AND tenant_id=?
            ")->execute([
                $playlist_id, $giorni, $ora_ini, $ora_fine,
                $intervallo, $fullscreen,
                $adv_id, $tid
            ]);

            $db->prepare("UPDATE adv_playlists SET loop_mode=? WHERE id=? AND tenant_id=?")
               ->execute([$loop_mode, $playlist_id, $tid]);

            $success = 'Regola ADV aggiornata.';
        }
        $sel_disp = $disp_id;
    }

    // Elimina regola ADV
    if ($action === 'delete_adv') {
        $adv_id = (int)($_POST['adv_id'] ?? 0);
        $db->prepare("DELETE FROM adv_regole WHERE id=? AND tenant_id=?")->execute([$adv_id, $tid]);
        $success = 'Regola ADV eliminata.';
        $sel_disp = (int)($_POST['disp_id'] ?? 0);
    }

    // Toggle attivo regola ADV
    if ($action === 'toggle_adv') {
        $adv_id = (int)($_POST['adv_id'] ?? 0);
        $db->prepare("UPDATE adv_regole SET attivo = 1 - attivo WHERE id=? AND tenant_id=?")->execute([$adv_id, $tid]);
        $sel_disp = (int)($_POST['disp_id'] ?? 0);
    }
}

// ── Query dispositivi ────────────────────────────────────────
$stmt = $db->prepare("
    SELECT d.*, p.id AS profilo_id, p.nome AS profilo_nome,
           CASE WHEN d.ultimo_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 'online' ELSE 'offline' END AS stato_live
    FROM dispositivi d
    LEFT JOIN profili p ON p.id = d.profilo_id
    WHERE d.tenant_id = ?
    ORDER BY d.club, d.nome
");
$stmt->execute([$tid]);
$dispositivi = $stmt->fetchAll();

// ── Dati dispositivo selezionato ─────────────────────────────
$disp = null;
$regole_adv = [];

if ($sel_disp) {
    foreach ($dispositivi as $d) {
        if ($d['id'] == $sel_disp) { $disp = $d; break; }
    }

    if ($disp) {
        // Regole ADV — l'unico sistema di scheduling collegato davvero al player
        // (stato_v2.php / adv_status.php). "Playlist principale" e "secondarie"
        // sono state rimosse dalla UI: scrivevano su dispositivi.playlist_principale_id
        // e sulle vecchie tabelle profilo_regole/profilo_eventi, mai lette da
        // nessun endpoint usato dal player attuale — erano controlli morti.
        $stmt = $db->prepare("
            SELECT ar.*, ap.loop_mode, p.nome AS playlist_nome,
                   (SELECT COALESCE(SUM(c.durata),0)
                    FROM playlist_items pi
                    JOIN contenuti c ON c.id = pi.contenuto_id
                    WHERE pi.playlist_id = ar.playlist_id) AS durata_totale
            FROM adv_regole ar
            JOIN adv_playlists ap ON ap.id=ar.playlist_id
            LEFT JOIN playlist p ON p.id=ar.playlist_id
            WHERE ar.tenant_id=? AND ar.dispositivo_token=?
            ORDER BY ar.id
        ");
        $stmt->execute([$tid, $disp['token']]);
        $regole_adv = $stmt->fetchAll();
    }
}

// ── Tutte le playlist del tenant ────────────────────────────
$stmt = $db->prepare("SELECT id, nome FROM playlist WHERE tenant_id=? ORDER BY nome");
$stmt->execute([$tid]);
$tutte_playlist = $stmt->fetchAll();

// Giorni settimana
$giorni_map = ['1'=>'Lun','2'=>'Mar','3'=>'Mer','4'=>'Gio','5'=>'Ven','6'=>'Sab','7'=>'Dom'];

function format_giorni(string $giorni): string {
    global $giorni_map;
    if ($giorni === '1,2,3,4,5,6,7') return 'Tutti i giorni';
    $parts = array_map(fn($g) => $giorni_map[$g] ?? $g, explode(',', $giorni));
    return implode(' · ', $parts);
}
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
.device-list{display:flex;flex-direction:column;gap:4px}
.device-btn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;border:1px solid var(--outline-var);background:var(--surface);cursor:pointer;transition:all .12s;text-align:left;width:100%}
.device-btn:hover{border-color:var(--blue);background:var(--blue-bg)}
.device-btn.active{border-color:var(--blue);background:var(--blue-bg);color:var(--blue-text)}
.device-btn .dname{font-size:13px;font-weight:500;color:var(--on-surface);flex:1}
.device-btn.active .dname{color:var(--blue-text)}
.device-btn .dclub{font-size:11px;color:var(--on-variant)}

.rule-card{background:var(--surface-low);border:1px solid var(--outline-var);border-radius:10px;padding:14px 16px;margin-bottom:8px}
.rule-header{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.rule-playlist{font-size:13px;font-weight:600;color:var(--on-surface);flex:1}
.rule-meta{display:flex;gap:8px;flex-wrap:wrap}
.rule-tag{font-size:11px;font-weight:500;padding:2px 8px;border-radius:4px;background:var(--surface-mid);color:var(--on-variant)}

.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.section-title{font-family:'Hanken Grotesk',sans-serif;font-size:15px;font-weight:700;color:var(--on-surface)}

.day-selector{display:flex;gap:4px;flex-wrap:wrap}
.day-btn{display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;border:1px solid var(--outline-var);background:var(--surface-low);font-size:11px;font-weight:600;color:var(--on-variant);cursor:pointer;transition:all .12s;user-select:none}
.day-btn.sel{background:var(--blue);border-color:var(--blue);color:#fff}

.mode-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px}
.mode-opt{border:2px solid var(--outline-var);border-radius:10px;padding:12px 8px;text-align:center;cursor:pointer;transition:all .12s;background:var(--surface-low)}
.mode-opt:hover{border-color:var(--blue)}
.mode-opt.sel{border-color:var(--blue);background:var(--blue-bg)}
.mode-opt input{display:none}
.mode-label{font-size:12px;font-weight:600;color:var(--on-surface);margin-top:4px}
.mode-sub{font-size:10px;color:var(--on-variant);margin-top:2px;line-height:1.3}
.mode-opt.sel .mode-label{color:var(--blue-text)}

.adv-card{border-radius:10px;border:1px solid var(--outline-var);overflow:hidden;margin-bottom:8px}
.adv-card-head{display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--surface-low)}
.adv-card-body{padding:10px 14px;border-top:1px solid var(--outline-var);display:grid;grid-template-columns:1fr 1fr;gap:8px}
.adv-stat{font-size:11px;color:var(--on-variant)}
.adv-stat strong{display:block;font-size:13px;font-weight:600;color:var(--on-surface)}
</style>
</head>
<body>

<?php require_once __DIR__ . '/includes/topnav.php'; ?>
<div class="app">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main">

    <div class="page-head">
      <div>
        <div class="page-title">ADV & Scheduling</div>
        <div class="page-sub">Gestisci playlist e pubblicità per ogni dispositivo</div>
      </div>
    </div>

    <?php if ($success): ?>
    <div style="background:var(--success-bg);border:1px solid rgba(34,197,94,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--success);margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <div class="g12">

      <!-- ── COLONNA SINISTRA: lista dispositivi ─────────────── -->
      <div style="grid-column:span 3">
        <div class="card cp">
          <div style="font-size:11px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Dispositivi</div>
          <div class="search-box" style="height:36px;margin-bottom:10px">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--on-variant)"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" id="device-filter" placeholder="Cerca..." style="font-size:12px" oninput="filterDevices(this.value)">
          </div>
          <div class="device-list" id="device-list">
            <?php foreach ($dispositivi as $d): ?>
            <a href="/adv.php?device=<?php echo $d['id']; ?>" class="device-btn <?php echo $sel_disp==$d['id']?'active':''; ?>">
              <div class="dot <?php echo $d['stato_live']==='online'?'on':'off'; ?>" style="<?php echo $d['stato_live']!=='online'?'animation:none':''; ?>"></div>
              <div>
                <div class="dname"><?php echo htmlspecialchars($d['nome']); ?></div>
                <div class="dclub"><?php echo htmlspecialchars($d['club']); ?></div>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- ── COLONNA DESTRA: scheduling del dispositivo ────────── -->
      <div style="grid-column:span 9">

        <?php if (!$disp): ?>
        <div class="card cp" style="text-align:center;padding:60px">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="margin:0 auto 16px;opacity:.3"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
          <div style="font-size:17px;font-weight:700;margin-bottom:8px">Seleziona un dispositivo</div>
          <div style="font-size:13px;color:var(--on-variant)">Scegli un player dalla lista per gestire il suo scheduling</div>
        </div>

        <?php else: ?>

        <!-- Info dispositivo -->
        <div class="card cp" style="margin-bottom:14px">
          <div style="display:flex;align-items:center;gap:12px">
            <div class="dot <?php echo $disp['stato_live']==='online'?'on':'off'; ?>" style="width:10px;height:10px;<?php echo $disp['stato_live']!=='online'?'animation:none':''; ?>"></div>
            <div>
              <div style="font-family:'Hanken Grotesk',sans-serif;font-size:17px;font-weight:700;color:var(--on-surface)"><?php echo htmlspecialchars($disp['nome']); ?></div>
              <div style="font-size:12px;color:var(--on-variant)"><?php echo htmlspecialchars($disp['club']); ?> · <?php echo strtoupper($disp['hw_type']??''); ?> · <?php echo $disp['numero_tv']??0; ?> TV</div>
            </div>
            <span class="badge <?php echo $disp['stato_live']==='online'?'on':'off'; ?>" style="margin-left:auto"><?php echo $disp['stato_live']==='online'?'Online':'Offline'; ?></span>
          </div>
        </div>

        <div class="g12" style="margin-bottom:0">

          <!-- ── REGOLE ADV ────────────────────────────────────── -->
          <div style="grid-column:span 12">
            <div class="card cp">
              <div class="section-head">
                <div class="section-title">Regole ADV</div>
                <button class="btn-sm" onclick="document.getElementById('adv-form-action').value='add_adv'; document.getElementById('adv-form-id').value=''; document.getElementById('adv-form').reset(); setAdvFormIsBase(advBaseRuleId===null); toggleForm('form-adv')">+ Aggiungi</button>
              </div>

              <?php if (empty($regole_adv)): ?>
              <div style="font-size:12px;color:var(--on-variant);padding:12px 0;text-align:center">Nessuna regola ADV configurata</div>
              <?php endif; ?>

              <?php foreach ($regole_adv as $adv_idx => $adv): $is_base = ($adv_idx === 0); ?>
              <div class="adv-card">
                <div class="adv-card-head">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--violet)" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                  <span style="font-size:13px;font-weight:600;color:var(--on-surface);flex:1"><?php echo htmlspecialchars($adv['playlist_nome'] ?? 'Playlist ADV'); ?></span>
                  <?php if ($is_base): ?>
                  <span class="badge blue" title="Detta ritmo e modalità per tutte le regole. I suoi Giorni/Fascia oraria qui sotto non contano: si applica sempre come fallback.">Regola base</span>
                  <?php endif; ?>
                  <?php if ($adv['fullscreen']): ?>
                  <span class="badge vio">Fullscreen</span>
                  <?php endif; ?>
                  <span class="badge <?php echo $adv['attivo']?'on':'off'; ?>"><?php echo $adv['attivo']?'Attiva':'Pausa'; ?></span>

                  <button type="button" class="btn-sm" onclick='openAdvForm(<?php echo json_encode([
                    "adv_id"         => $adv["id"],
                    "playlist_id"    => $adv["playlist_id"],
                    "loop_mode"      => $adv["loop_mode"] ?? "alternata",
                    "giorni"         => $adv["giorni"],
                    "ora_inizio"     => $adv["ora_inizio"] ? substr($adv["ora_inizio"],0,5) : "",
                    "ora_fine"       => $adv["ora_fine"] ? substr($adv["ora_fine"],0,5) : "",
                    "intervallo_min" => $adv["intervallo_min"],
                    "fullscreen"     => (bool)$adv["fullscreen"],
                  ]); ?>)'>Modifica</button>
                  <form method="POST" style="margin:0">
                    <input type="hidden" name="action" value="toggle_adv">
                    <input type="hidden" name="adv_id" value="<?php echo $adv['id']; ?>">
                    <input type="hidden" name="disp_id" value="<?php echo $sel_disp; ?>">
                    <button type="submit" class="btn-sm"><?php echo $adv['attivo']?'Pausa':'Attiva'; ?></button>
                  </form>
                  <form method="POST" style="margin:0" onsubmit="return confirm('Eliminare questa regola ADV?')">
                    <input type="hidden" name="action" value="delete_adv">
                    <input type="hidden" name="adv_id" value="<?php echo $adv['id']; ?>">
                    <input type="hidden" name="disp_id" value="<?php echo $sel_disp; ?>">
                    <button type="submit" class="btn-sm danger">Elimina</button>
                  </form>
                </div>
                <div class="adv-card-body" style="grid-template-columns:1fr 1fr;grid-auto-rows:auto">
                  <div class="adv-stat">
                    <strong><?php echo format_giorni($adv['giorni']); ?></strong>
                    Giorni<?php echo $is_base ? ' <span style="opacity:.6">(ignorati, vedi nota)</span>' : ''; ?>
                  </div>
                  <div class="adv-stat">
                    <strong>
                      <?php echo $adv['ora_inizio'] ? substr($adv['ora_inizio'],0,5).' – '.substr($adv['ora_fine'],0,5) : 'Tutto il giorno'; ?>
                    </strong>
                    Fascia oraria<?php echo $is_base ? ' <span style="opacity:.6">(ignorata, vedi nota)</span>' : ''; ?>
                  </div>
                  <div class="adv-stat">
                    <strong>Ogni <?php echo $adv['intervallo_min']; ?> min</strong>
                    Frequenza<?php echo !$is_base ? ' <span style="opacity:.6">(non usata qui)</span>' : ''; ?>
                  </div>
                  <div class="adv-stat">
                    <strong><?php echo (int)$adv['durata_totale']; ?> sec</strong>
                    Durata totale playlist
                  </div>
                  <?php if ($is_base): ?>
                  <div style="grid-column:1 / -1;font-size:11px;color:var(--on-variant);background:var(--surface-mid);border-radius:8px;padding:8px 10px;margin-top:2px;line-height:1.4">
                    Questa è la regola base: si applica sempre, indipendentemente da Giorni/Fascia oraria qui sopra. Decide il ritmo (Modalità + Frequenza) per tutte le regole di questo dispositivo.
                  </div>
                  <?php else: ?>
                  <div style="grid-column:1 / -1;font-size:11px;color:var(--on-variant);background:var(--surface-mid);border-radius:8px;padding:8px 10px;margin-top:2px;line-height:1.4">
                    Sostituzione: nei suoi Giorni/Fascia oraria mostra questa playlist al posto di quella base, con lo stesso ritmo.
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>

              <!-- Form aggiungi ADV -->
              <div id="form-adv" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid var(--outline-var)">
                <form method="POST" id="adv-form">
                  <input type="hidden" name="action" id="adv-form-action" value="add_adv">
                  <input type="hidden" name="adv_id" id="adv-form-id" value="">
                  <input type="hidden" name="disp_id" value="<?php echo $sel_disp; ?>">

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">

                    <div>
                      <div class="form-group">
                        <label class="form-label">Playlist ADV</label>
                        <select name="playlist_id" class="form-input form-select" required>
                          <option value="">Seleziona…</option>
                          <?php foreach ($tutte_playlist as $p): ?>
                          <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div class="form-group" id="adv-field-modalita">
                        <label class="form-label">Modalità</label>
                        <div class="mode-grid">
                          <label class="mode-opt sel" onclick="selectMode(this)">
                            <input type="radio" name="loop_mode" value="alternata" checked>
                            <div class="mode-label">Alternata</div>
                            <div class="mode-sub">ADV ogni N minuti</div>
                          </label>
                          <label class="mode-opt" onclick="selectMode(this)">
                            <input type="radio" name="loop_mode" value="loop">
                            <div class="mode-label">Loop</div>
                            <div class="mode-sub">Solo ADV, no TV</div>
                          </label>
                          <label class="mode-opt" onclick="selectMode(this)">
                            <input type="radio" name="loop_mode" value="smart">
                            <div class="mode-label">Smart</div>
                            <div class="mode-sub">Solo nelle fasce</div>
                          </label>
                        </div>
                      </div>

                      <div class="form-group" id="adv-field-intervallo">
                        <label class="form-label">Ogni (minuti)</label>
                        <input type="number" name="intervallo_min" class="form-input" value="20" min="1" max="120">
                        <div style="font-size:11px;color:var(--on-variant);margin-top:4px">La durata del blocco ADV e' sempre quella reale della playlist (somma dei contenuti), non si imposta a mano.</div>
                      </div>

                      <div class="form-group" id="adv-field-nota-non-base" style="display:none">
                        <div style="font-size:12px;color:var(--on-variant);background:var(--surface-mid);border-radius:8px;padding:10px 12px;line-height:1.5">
                          Il ritmo (modalità e frequenza) è deciso dalla prima regola. Questa regola sostituisce solo la playlist nei giorni/fascia scelti qui accanto, mantenendo lo stesso ritmo.
                        </div>
                      </div>

                      <div class="form-group">
                        <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer">
                          <input type="checkbox" name="fullscreen" value="1" style="width:16px;height:16px;accent-color:var(--violet);margin-top:2px;flex-shrink:0">
                          <div>
                            <span class="form-label" style="margin:0;display:block">Fullscreen</span>
                            <span style="font-size:11px;color:var(--on-variant);line-height:1.4">L'ADV occupa tutto lo schermo coprendo tutti i layer — il widget dell'ora rimane sempre visibile in sovrimpressione.</span>
                          </div>
                        </label>
                      </div>
                    </div>

                    <div>
                      <div class="form-group">
                        <label class="form-label">Giorni</label>
                        <div class="day-selector" id="days-adv">
                          <?php foreach ($giorni_map as $n => $label): ?>
                          <div class="day-btn sel" data-day="<?php echo $n; ?>" onclick="toggleDay(this,'days-adv','giorni-adv')"><?php echo $label; ?></div>
                          <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="giorni" id="giorni-adv" value="1,2,3,4,5,6,7">
                      </div>

                      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <div class="form-group">
                          <label class="form-label">Ora inizio</label>
                          <input type="time" name="ora_inizio" class="form-input">
                        </div>
                        <div class="form-group">
                          <label class="form-label">Ora fine</label>
                          <input type="time" name="ora_fine" class="form-input">
                        </div>
                      </div>

                      <div style="margin-top:8px;display:flex;gap:8px">
                        <button type="button" class="btn-ghost" style="flex:1" onclick="toggleForm('form-adv')">Annulla</button>
                        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                          Aggiungi regola ADV
                        </button>
                      </div>
                    </div>
                  </div>
                </form>
              </div>

            </div>
          </div>

        </div><!-- /g12 inner -->
        <?php endif; ?>
      </div><!-- /col destra -->

    </div><!-- /g12 outer -->
  </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
// ── Filtra dispositivi ───────────────────────────────────────
function filterDevices(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#device-list .device-btn').forEach(btn => {
    const text = btn.querySelector('.dname').textContent.toLowerCase()
               + ' ' + btn.querySelector('.dclub').textContent.toLowerCase();
    btn.style.display = text.includes(q) ? '' : 'none';
  });
}

// ── Toggle form ───────────────────────────────────────────────
const advBaseRuleId = <?php echo (!empty($regole_adv) ? (int)$regole_adv[0]['id'] : 'null'); ?>;

// Mostra i campi Modalita/Ogni-minuti solo per la regola base (la prima
// creata); per tutte le altre mostra solo la nota informativa.
function setAdvFormIsBase(isBase) {
  document.getElementById('adv-field-modalita').style.display = isBase ? 'block' : 'none';
  document.getElementById('adv-field-intervallo').style.display = isBase ? 'block' : 'none';
  document.getElementById('adv-field-nota-non-base').style.display = isBase ? 'none' : 'block';
}

function toggleForm(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

// Riusa lo stesso form di "+ Aggiungi" per modificare una regola esistente:
// lo pre-compila con i valori attuali e lo fa puntare a edit_adv invece di add_adv.
function openAdvForm(data) {
  const form = document.getElementById('adv-form');
  document.getElementById('adv-form-action').value = 'edit_adv';
  document.getElementById('adv-form-id').value = data.adv_id;

  form.querySelector('[name="playlist_id"]').value = data.playlist_id;
  form.querySelector('[name="intervallo_min"]').value = data.intervallo_min;
  form.querySelector('[name="ora_inizio"]').value = data.ora_inizio || '';
  form.querySelector('[name="ora_fine"]').value = data.ora_fine || '';
  form.querySelector('[name="fullscreen"]').checked = !!data.fullscreen;

  // Modalita' (Alternata/Loop/Smart)
  document.querySelectorAll('.mode-opt').forEach(o => {
    const isMatch = o.querySelector('input').value === data.loop_mode;
    o.classList.toggle('sel', isMatch);
    o.querySelector('input').checked = isMatch;
  });

  // Giorni della settimana
  const giorniSel = (data.giorni || '').split(',');
  document.querySelectorAll('#days-adv .day-btn').forEach(b => {
    b.classList.toggle('sel', giorniSel.includes(b.dataset.day));
  });
  updateGiorni('days-adv', 'giorni-adv');

  setAdvFormIsBase(data.adv_id === advBaseRuleId);

  document.getElementById('form-adv').style.display = 'block';
  document.getElementById('form-adv').scrollIntoView({behavior:'smooth', block:'center'});
}

// ── Day selector ──────────────────────────────────────────────
function toggleDay(btn, groupId, inputId) {
  btn.classList.toggle('sel');
  updateGiorni(groupId, inputId);
}

function updateGiorni(groupId, inputId) {
  const days = [...document.querySelectorAll(`#${groupId} .day-btn.sel`)].map(b => b.dataset.day);
  document.getElementById(inputId).value = days.join(',') || '1';
}

// ── Mode selector ─────────────────────────────────────────────
function selectMode(label) {
  document.querySelectorAll('.mode-opt').forEach(o => o.classList.remove('sel'));
  label.classList.add('sel');
  label.querySelector('input').checked = true;
}
</script>
