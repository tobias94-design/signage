<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Inserzionisti';
$db  = getDB();
$tid = TENANT_ID;

// ── Azioni POST ──────────────────────────────────────────────
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // SALVA INSERZIONISTA (crea o aggiorna)
    if ($action === 'save_inserzionista') {
        $id = (int)($_POST['id'] ?? 0);
        $ragione_sociale = trim($_POST['ragione_sociale'] ?? '');
        $referente = trim($_POST['referente'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $settore = trim($_POST['settore'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $attivo = isset($_POST['attivo']) ? 1 : 0;

        if ($ragione_sociale === '') {
            $error = 'La ragione sociale è obbligatoria.';
        } elseif ($id) {
            $stmt = $db->prepare("UPDATE inserzionisti SET ragione_sociale=?, referente=?, email=?, telefono=?, settore=?, note=?, attivo=? WHERE id=? AND tenant_id=?");
            $stmt->execute([$ragione_sociale, $referente, $email, $telefono, $settore, $note, $attivo, $id, $tid]);
            $success = 'Inserzionista aggiornato.';
        } else {
            $stmt = $db->prepare("INSERT INTO inserzionisti (tenant_id, ragione_sociale, referente, email, telefono, settore, note, attivo, creato_il) VALUES (?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute([$tid, $ragione_sociale, $referente, $email, $telefono, $settore, $note, $attivo]);
            $success = 'Inserzionista aggiunto.';
        }
    }

    // ELIMINA INSERZIONISTA (solo se non ha contratti né contenuti collegati)
    if ($action === 'delete_inserzionista') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT COUNT(*) FROM contratti WHERE inserzionista_id=? AND tenant_id=?");
        $stmt->execute([$id, $tid]);
        $n_contratti = (int)$stmt->fetchColumn();
        $stmt = $db->prepare("SELECT COUNT(*) FROM contenuti WHERE inserzionista_id=? AND tenant_id=?");
        $stmt->execute([$id, $tid]);
        $n_contenuti = (int)$stmt->fetchColumn();

        if ($n_contratti > 0 || $n_contenuti > 0) {
            $error = 'Elimina prima i contratti e scollega i contenuti di questo inserzionista.';
        } else {
            $db->prepare("DELETE FROM inserzionisti WHERE id=? AND tenant_id=?")->execute([$id, $tid]);
            $success = 'Inserzionista eliminato.';
        }
    }

    // SALVA CONTRATTO (crea o aggiorna)
    if ($action === 'save_contratto') {
        $id = (int)($_POST['id'] ?? 0);
        $inserzionista_id = (int)($_POST['inserzionista_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $data_inizio = $_POST['data_inizio'] ?? '';
        $data_fine = $_POST['data_fine'] ?? '';
        $importo = (float)($_POST['importo'] ?? 0);
        $tipo_contenuto = $_POST['tipo_contenuto'] ?? 'entrambi';
        $club_target = trim($_POST['club_target'] ?? '');
        $fascia_oraria = trim($_POST['fascia_oraria'] ?? '');
        $frequenza_min = (int)($_POST['frequenza_min'] ?? 30);
        $stato = $_POST['stato'] ?? 'attivo';
        $note = trim($_POST['note'] ?? '');

        if (!$inserzionista_id || !$data_inizio || !$data_fine) {
            $error = 'Inserzionista, data inizio e data fine sono obbligatori.';
        } elseif ($data_fine < $data_inizio) {
            $error = 'La data fine non può essere precedente alla data inizio.';
        } elseif ($id) {
            $stmt = $db->prepare("UPDATE contratti SET inserzionista_id=?, nome=?, data_inizio=?, data_fine=?, importo=?, tipo_contenuto=?, club_target=?, fascia_oraria=?, frequenza_min=?, stato=?, note=? WHERE id=? AND tenant_id=?");
            $stmt->execute([$inserzionista_id, $nome, $data_inizio, $data_fine, $importo, $tipo_contenuto, $club_target, $fascia_oraria, $frequenza_min, $stato, $note, $id, $tid]);
            $success = 'Contratto aggiornato.';
        } else {
            $stmt = $db->prepare("INSERT INTO contratti (tenant_id, inserzionista_id, nome, data_inizio, data_fine, importo, tipo_contenuto, club_target, fascia_oraria, frequenza_min, stato, note, creato_il) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute([$tid, $inserzionista_id, $nome, $data_inizio, $data_fine, $importo, $tipo_contenuto, $club_target, $fascia_oraria, $frequenza_min, $stato, $note]);
            $success = 'Contratto creato.';
        }
    }

    // ELIMINA CONTRATTO
    if ($action === 'delete_contratto') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM contratti WHERE id=? AND tenant_id=?")->execute([$id, $tid]);
        $success = 'Contratto eliminato.';
    }
}

// ── Query inserzionisti con stato calcolato dal contratto più rilevante ─
$stmt = $db->prepare("
    SELECT i.*,
           COUNT(DISTINCT c.id) AS n_contratti,
           COUNT(DISTINCT con.id) AS n_contenuti,
           ct.id AS ultimo_contratto_id, ct.nome AS ultimo_contratto_nome,
           ct.data_inizio AS ultimo_data_inizio, ct.data_fine AS ultimo_data_fine,
           ct.importo AS ultimo_importo, ct.stato AS ultimo_stato,
           DATEDIFF(ct.data_fine, CURDATE()) AS giorni_rimasti
    FROM inserzionisti i
    LEFT JOIN contratti c ON c.inserzionista_id = i.id
    LEFT JOIN contenuti con ON con.inserzionista_id = i.id
    LEFT JOIN contratti ct ON ct.id = (
        SELECT id FROM contratti c2 WHERE c2.inserzionista_id = i.id ORDER BY data_fine DESC LIMIT 1
    )
    WHERE i.tenant_id = ?
    GROUP BY i.id, ct.id, ct.nome, ct.data_inizio, ct.data_fine, ct.importo, ct.stato
    ORDER BY i.ragione_sociale
");
$stmt->execute([$tid]);
$inserzionisti = $stmt->fetchAll();

// Stato calcolato per ogni inserzionista
foreach ($inserzionisti as &$ins) {
    if (!$ins['ultimo_contratto_id']) {
        $ins['stato_calcolato'] = 'nessuno';
    } elseif ($ins['ultimo_stato'] !== 'attivo') {
        $ins['stato_calcolato'] = 'sospeso';
    } elseif ($ins['ultimo_data_fine'] < date('Y-m-d')) {
        $ins['stato_calcolato'] = 'scaduto';
    } elseif ($ins['ultimo_data_inizio'] > date('Y-m-d')) {
        $ins['stato_calcolato'] = 'in_arrivo';
    } else {
        $ins['stato_calcolato'] = 'attivo';
    }
}
unset($ins);

// ── Metriche ─────────────────────────────────────────────────
$tot_inserzionisti = count($inserzionisti);
$tot_attivi = count(array_filter($inserzionisti, fn($i) => $i['attivo']));
$in_scadenza = count(array_filter($inserzionisti, fn($i) => $i['stato_calcolato']==='attivo' && $i['giorni_rimasti']!==null && $i['giorni_rimasti']<=7));

$stmt = $db->prepare("
    SELECT COALESCE(SUM(importo),0) FROM contratti
    WHERE tenant_id = ? AND stato='attivo' AND CURDATE() BETWEEN data_inizio AND data_fine
");
$stmt->execute([$tid]);
$fatturato_attivo = (float)$stmt->fetchColumn();

// ── Tutti i contratti (per il modal contratti di ogni inserzionista) ─
$stmt = $db->prepare("SELECT * FROM contratti WHERE tenant_id = ? ORDER BY data_fine DESC");
$stmt->execute([$tid]);
$tutti_contratti = $stmt->fetchAll();
$contratti_per_ins = [];
foreach ($tutti_contratti as $ct) {
    $contratti_per_ins[$ct['inserzionista_id']][] = $ct;
}

function badge_stato(string $stato): string {
    $map = [
        'attivo'    => ['on', 'Attivo'],
        'scaduto'   => ['err', 'Scaduto'],
        'sospeso'   => ['warn', 'Sospeso'],
        'in_arrivo' => ['blue', 'In arrivo'],
        'nessuno'   => ['', 'Nessun contratto'],
    ];
    [$cls, $label] = $map[$stato] ?? ['', $stato];
    return "<span class=\"badge $cls\">$label</span>";
}
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
.ins-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
.ins-card{background:var(--surface);border:1px solid var(--outline-var);border-radius:12px;padding:16px;transition:all .15s}
.ins-card:hover{border-color:var(--blue);box-shadow:var(--shadow-sm)}
.ins-card-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:8px;gap:8px}
.ins-name{font-size:14px;font-weight:700;color:var(--on-surface)}
.ins-settore{font-size:11px;color:var(--on-variant);margin-top:1px}
.ins-meta{font-size:12px;color:var(--on-variant);margin:10px 0;line-height:1.6}
.ins-meta div{display:flex;align-items:center;gap:6px}
.ins-contract-info{background:var(--surface-low);border-radius:8px;padding:10px 12px;margin:10px 0;font-size:11.5px}
.ins-contract-info .cn{font-weight:600;color:var(--on-surface);margin-bottom:2px}
.ins-contract-info .cd{color:var(--on-variant)}
.ins-actions{display:flex;gap:6px;margin-top:10px}
.warn-text{color:var(--warn)}
.badge.warn{background:var(--warn-bg);color:var(--warn)}
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
        <div class="page-title">Inserzionisti</div>
        <div class="page-sub"><?php echo $tot_inserzionisti; ?> totali · <?php echo $tot_attivi; ?> attivi</div>
      </div>
      <div class="head-actions">
        <button class="btn-primary" onclick="openInsModal()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nuovo inserzionista
        </button>
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
        <div class="mlabel">Inserzionisti</div>
        <div class="mval"><?php echo $tot_inserzionisti; ?></div>
        <div class="msub"><?php echo $tot_attivi; ?> attivi</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">In scadenza</div>
        <div class="mval <?php echo $in_scadenza>0?'warn-text':''; ?>"><?php echo $in_scadenza; ?></div>
        <div class="msub <?php echo $in_scadenza>0?'dn':'up'; ?>"><?php echo $in_scadenza>0 ? 'entro 7 giorni' : 'nessuno'; ?></div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Fatturato attivo</div>
        <div class="mval">€<?php echo number_format($fatturato_attivo, 0, ',', '.'); ?></div>
        <div class="msub">contratti in corso</div>
      </div>
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Contratti totali</div>
        <div class="mval"><?php echo count($tutti_contratti); ?></div>
        <div class="msub">storico completo</div>
      </div>
    </div>

    <!-- LISTA INSERZIONISTI -->
    <?php if (empty($inserzionisti)): ?>
    <div class="card cp" style="text-align:center;padding:60px">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="margin:0 auto 16px;opacity:.3"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      <div style="font-size:17px;font-weight:700;margin-bottom:8px">Nessun inserzionista</div>
      <div style="font-size:13px;color:var(--on-variant)">Aggiungi il primo cliente pubblicitario per iniziare</div>
    </div>
    <?php else: ?>
    <div class="ins-grid">
      <?php foreach ($inserzionisti as $ins): ?>
      <div class="ins-card">
        <div class="ins-card-head">
          <div>
            <div class="ins-name"><?php echo htmlspecialchars($ins['ragione_sociale']); ?></div>
            <?php if ($ins['settore']): ?><div class="ins-settore"><?php echo htmlspecialchars($ins['settore']); ?></div><?php endif; ?>
          </div>
          <?php echo badge_stato($ins['stato_calcolato']); ?>
        </div>

        <?php if ($ins['referente'] || $ins['email'] || $ins['telefono']): ?>
        <div class="ins-meta">
          <?php if ($ins['referente']): ?><div><?php echo htmlspecialchars($ins['referente']); ?></div><?php endif; ?>
          <?php if ($ins['email']): ?><div><?php echo htmlspecialchars($ins['email']); ?></div><?php endif; ?>
          <?php if ($ins['telefono']): ?><div><?php echo htmlspecialchars($ins['telefono']); ?></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($ins['ultimo_contratto_id']): ?>
        <div class="ins-contract-info">
          <div class="cn"><?php echo htmlspecialchars($ins['ultimo_contratto_nome'] ?: 'Contratto'); ?></div>
          <div class="cd">
            <?php echo date('d/m/Y', strtotime($ins['ultimo_data_inizio'])); ?> → <?php echo date('d/m/Y', strtotime($ins['ultimo_data_fine'])); ?>
            <?php if ($ins['stato_calcolato']==='attivo' && $ins['giorni_rimasti']!==null && $ins['giorni_rimasti']<=7): ?>
              <span class="warn-text"> · scade tra <?php echo max(0,(int)$ins['giorni_rimasti']); ?> giorni</span>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="ins-contract-info" style="color:var(--on-variant)">Nessun contratto ancora registrato</div>
        <?php endif; ?>

        <div style="font-size:11px;color:var(--on-variant)"><?php echo $ins['n_contenuti']; ?> contenuti collegati · <?php echo $ins['n_contratti']; ?> contratti totali</div>

        <div class="ins-actions">
          <button class="btn-sm" style="flex:1;justify-content:center" onclick='openContrattiModal(<?php echo json_encode(["id"=>$ins["id"],"nome"=>$ins["ragione_sociale"]]); ?>)'>Contratti</button>
          <button class="btn-sm" style="flex:1;justify-content:center" onclick='editInserzionista(<?php echo json_encode($ins); ?>)'>Modifica</button>
          <form method="POST" onsubmit="return confirm('Eliminare questo inserzionista?')">
            <input type="hidden" name="action" value="delete_inserzionista">
            <input type="hidden" name="id" value="<?php echo $ins['id']; ?>">
            <button type="submit" class="btn-sm danger">Elimina</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </main>
</div>

<!-- MODAL INSERZIONISTA -->
<div id="ins-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:460px;max-height:90vh;overflow-y:auto">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:6px" id="ins-modal-title">Nuovo inserzionista</div>
    <form method="POST" style="margin-top:16px">
      <input type="hidden" name="action" value="save_inserzionista">
      <input type="hidden" name="id" id="ins-id" value="">
      <div class="form-group">
        <label class="form-label">Ragione sociale</label>
        <input type="text" name="ragione_sociale" id="ins-ragione-sociale" class="form-input" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group">
          <label class="form-label">Referente</label>
          <input type="text" name="referente" id="ins-referente" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Settore</label>
          <input type="text" name="settore" id="ins-settore" class="form-input" placeholder="Es. Alimentare">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" id="ins-email" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Telefono</label>
          <input type="text" name="telefono" id="ins-telefono" class="form-input">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Note</label>
        <textarea name="note" id="ins-note" class="form-input" rows="2" style="resize:vertical"></textarea>
      </div>
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;margin-bottom:8px">
        <input type="checkbox" name="attivo" id="ins-attivo" checked style="width:14px;height:14px;accent-color:var(--blue)"> Inserzionista attivo
      </label>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('ins-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Salva</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL CONTRATTI -->
<div id="contratti-modal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:250;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:640px;max-height:88vh;overflow-y:auto">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:2px">Contratti — <span id="contratti-modal-nome"></span></div>
    <div style="font-size:11.5px;color:var(--on-variant);margin-bottom:16px">Questi campi descrivono l'accordo commerciale. La programmazione tecnica reale va configurata a parte su ADV & Scheduling.</div>

    <div id="contratti-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px"></div>

    <div style="border-top:1px solid var(--outline-var);padding-top:16px">
      <div style="font-size:12px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px">Nuovo contratto</div>
      <form method="POST">
        <input type="hidden" name="action" value="save_contratto">
        <input type="hidden" name="inserzionista_id" id="ct-inserzionista-id">
        <div class="form-group">
          <label class="form-label">Nome contratto</label>
          <input type="text" name="nome" class="form-input" placeholder="Es. Campagna estate 2026">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group">
            <label class="form-label">Data inizio</label>
            <input type="date" name="data_inizio" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label">Data fine</label>
            <input type="date" name="data_fine" class="form-input" required>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group">
            <label class="form-label">Importo (€)</label>
            <input type="number" step="0.01" name="importo" class="form-input" value="0">
          </div>
          <div class="form-group">
            <label class="form-label">Stato</label>
            <select name="stato" class="form-input">
              <option value="attivo">Attivo</option>
              <option value="sospeso">Sospeso</option>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div class="form-group">
            <label class="form-label">Tipo contenuto</label>
            <select name="tipo_contenuto" class="form-input">
              <option value="entrambi">Entrambi</option>
              <option value="video">Solo video</option>
              <option value="immagine">Solo immagine</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Frequenza (min)</label>
            <input type="number" name="frequenza_min" class="form-input" value="30">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Club target</label>
          <input type="text" name="club_target" class="form-input" placeholder="Es. Soave, Affi">
        </div>
        <div class="form-group">
          <label class="form-label">Fascia oraria</label>
          <input type="text" name="fascia_oraria" class="form-input" placeholder="Es. 18:00-21:00">
        </div>
        <div class="form-group">
          <label class="form-label">Note</label>
          <textarea name="note" class="form-input" rows="2" style="resize:vertical"></textarea>
        </div>
        <div style="display:flex;gap:10px;margin-top:8px">
          <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('contratti-modal').style.display='none'">Chiudi</button>
          <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Aggiungi contratto</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
const CONTRATTI_PER_INS = <?php echo json_encode($contratti_per_ins, JSON_UNESCAPED_UNICODE); ?>;

// ── Modal inserzionista ──────────────────────────────────────
function openInsModal() {
  document.getElementById('ins-modal-title').textContent = 'Nuovo inserzionista';
  document.getElementById('ins-id').value = '';
  document.getElementById('ins-ragione-sociale').value = '';
  document.getElementById('ins-referente').value = '';
  document.getElementById('ins-settore').value = '';
  document.getElementById('ins-email').value = '';
  document.getElementById('ins-telefono').value = '';
  document.getElementById('ins-note').value = '';
  document.getElementById('ins-attivo').checked = true;
  document.getElementById('ins-modal').style.display = 'flex';
}

function editInserzionista(ins) {
  document.getElementById('ins-modal-title').textContent = 'Modifica inserzionista';
  document.getElementById('ins-id').value = ins.id;
  document.getElementById('ins-ragione-sociale').value = ins.ragione_sociale || '';
  document.getElementById('ins-referente').value = ins.referente || '';
  document.getElementById('ins-settore').value = ins.settore || '';
  document.getElementById('ins-email').value = ins.email || '';
  document.getElementById('ins-telefono').value = ins.telefono || '';
  document.getElementById('ins-note').value = ins.note || '';
  document.getElementById('ins-attivo').checked = !!(+ins.attivo);
  document.getElementById('ins-modal').style.display = 'flex';
}

// ── Modal contratti ──────────────────────────────────────────
function openContrattiModal(ins) {
  document.getElementById('contratti-modal-nome').textContent = ins.nome;
  document.getElementById('ct-inserzionista-id').value = ins.id;
  renderContrattiList(ins.id);
  document.getElementById('contratti-modal').style.display = 'flex';
}

function statoBadge(stato, dataFine) {
  const oggi = new Date().toISOString().slice(0,10);
  if (stato !== 'attivo') return '<span class="badge warn">Sospeso</span>';
  if (dataFine < oggi) return '<span class="badge err">Scaduto</span>';
  return '<span class="badge on">Attivo</span>';
}

function renderContrattiList(insId) {
  const list = document.getElementById('contratti-list');
  const contratti = CONTRATTI_PER_INS[insId] || [];
  if (!contratti.length) {
    list.innerHTML = '<div style="text-align:center;padding:16px;font-size:12px;color:var(--on-variant)">Nessun contratto ancora. Aggiungine uno qui sotto.</div>';
    return;
  }
  list.innerHTML = contratti.map(ct => `
    <div style="background:var(--surface-low);border:1px solid var(--outline-var);border-radius:8px;padding:12px 14px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px">
        <div style="font-size:13px;font-weight:600;color:var(--on-surface)">${ct.nome || 'Contratto'}</div>
        ${statoBadge(ct.stato, ct.data_fine)}
      </div>
      <div style="font-size:11.5px;color:var(--on-variant)">
        ${ct.data_inizio} → ${ct.data_fine} · €${parseFloat(ct.importo||0).toFixed(2)}
        ${ct.club_target ? ' · ' + ct.club_target : ''}
      </div>
      <form method="POST" onsubmit="return confirm('Eliminare questo contratto?')" style="margin-top:8px">
        <input type="hidden" name="action" value="delete_contratto">
        <input type="hidden" name="id" value="${ct.id}">
        <button type="submit" class="btn-sm danger">Elimina</button>
      </form>
    </div>
  `).join('');
}
</script>
