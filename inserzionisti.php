<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();

$msg = '';

// ── AZIONI POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $az = $_POST['azione'] ?? '';

    // Salva inserzionista
    if ($az === 'salva_inserzionista') {
        $id = (int)($_POST['id'] ?? 0);
        $d  = [
            $_POST['ragione_sociale'] ?? '',
            $_POST['referente']       ?? '',
            $_POST['email']           ?? '',
            $_POST['telefono']        ?? '',
            $_POST['settore']         ?? '',
            $_POST['note']            ?? '',
            isset($_POST['attivo']) ? 1 : 0,
        ];
        if ($id) {
            $db->prepare('UPDATE inserzionisti SET ragione_sociale=?,referente=?,email=?,telefono=?,settore=?,note=?,attivo=? WHERE id=?')
               ->execute([...$d, $id]);
            $msg = 'ok|Inserzionista aggiornato!';
        } else {
            $db->prepare('INSERT INTO inserzionisti (ragione_sociale,referente,email,telefono,settore,note,attivo) VALUES (?,?,?,?,?,?,?)')
               ->execute($d);
            $msg = 'ok|Inserzionista aggiunto!';
        }
    }

    // Salva contratto
    if ($az === 'salva_contratto') {
        $id = (int)($_POST['id'] ?? 0);
        $d  = [
            (int)$_POST['inserzionista_id'],
            $_POST['nome']            ?? '',
            $_POST['data_inizio']     ?? '',
            $_POST['data_fine']       ?? '',
            (float)($_POST['importo'] ?? 0),
            $_POST['tipo_contenuto']  ?? 'entrambi',
            implode(',', $_POST['club_target'] ?? []),
            $_POST['fascia_oraria']   ?? '',
            (int)($_POST['frequenza_min'] ?? 30),
            $_POST['stato']           ?? 'attivo',
            $_POST['note']            ?? '',
        ];
        if ($id) {
            $db->prepare('UPDATE contratti SET inserzionista_id=?,nome=?,data_inizio=?,data_fine=?,importo=?,tipo_contenuto=?,club_target=?,fascia_oraria=?,frequenza_min=?,stato=?,note=? WHERE id=?')
               ->execute([...$d, $id]);
            $msg = 'ok|Contratto aggiornato!';
        } else {
            $db->prepare('INSERT INTO contratti (inserzionista_id,nome,data_inizio,data_fine,importo,tipo_contenuto,club_target,fascia_oraria,frequenza_min,stato,note) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
               ->execute($d);
            $msg = 'ok|Contratto salvato!';
        }
    }

    // Toggle attivo inserzionista
    if ($az === 'toggle_ins') {
        $id  = (int)$_POST['id'];
        $val = (int)$_POST['val'];
        $db->prepare('UPDATE inserzionisti SET attivo=? WHERE id=?')->execute([$val, $id]);
        echo 'ok'; exit;
    }
}

// ── DELETE ────────────────────────────────────────────────────────
if (isset($_GET['del_ins'])) {
    $db->exec("DELETE FROM contratti WHERE inserzionista_id=" . (int)$_GET['del_ins']);
    $db->exec("DELETE FROM inserzionisti WHERE id=" . (int)$_GET['del_ins']);
    header('Location: /inserzionisti.php'); exit;
}
if (isset($_GET['del_con'])) {
    $db->exec("DELETE FROM contratti WHERE id=" . (int)$_GET['del_con']);
    header('Location: /inserzionisti.php?tab=contratti'); exit;
}

// ── DATI ──────────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'inserzionisti';
$edit_ins = isset($_GET['edit_ins']) ? (int)$_GET['edit_ins'] : 0;
$edit_con = isset($_GET['edit_con']) ? (int)$_GET['edit_con'] : 0;

$inserzionisti = $db->query("SELECT i.*, 
    COUNT(DISTINCT c.id) as n_contratti,
    COUNT(DISTINCT con.id) as n_contenuti
    FROM inserzionisti i
    LEFT JOIN contratti c ON c.inserzionista_id = i.id
    LEFT JOIN contenuti con ON con.inserzionista_id = i.id
    GROUP BY i.id ORDER BY i.ragione_sociale")->fetchAll(PDO::FETCH_ASSOC);

$contratti = $db->query("SELECT c.*, i.ragione_sociale,
    (SELECT COUNT(*) FROM log_adv l 
     JOIN contenuti con ON con.id = l.contenuto_id 
     WHERE con.inserzionista_id = c.inserzionista_id
     AND l.passato_il BETWEEN c.data_inizio AND c.data_fine||' 23:59:59') as n_passaggi
    FROM contratti c
    LEFT JOIN inserzionisti i ON i.id = c.inserzionista_id
    ORDER BY c.data_fine DESC")->fetchAll(PDO::FETCH_ASSOC);

$clubs = $db->query("SELECT DISTINCT club FROM dispositivi WHERE club != '' ORDER BY club")->fetchAll(PDO::FETCH_COLUMN);

$ins_edit = $edit_ins ? $db->query("SELECT * FROM inserzionisti WHERE id=$edit_ins")->fetch(PDO::FETCH_ASSOC) : null;
$con_edit = $edit_con ? $db->query("SELECT * FROM contratti WHERE id=$edit_con")->fetch(PDO::FETCH_ASSOC) : null;

$settori = ['Fitness','Food & Beverage','Abbigliamento','Integratori','Benessere','Tecnologia','Assicurazioni','Immobiliare','Automotive','Altro'];

$titolo = 'Inserzionisti';
require_once 'includes/header.php';
?>

<div class="container">

<?php if ($msg): [$tm,$txt] = explode('|',$msg,2); ?>
<div class="messaggio <?= $tm ?>"><?= htmlspecialchars($txt) ?></div>
<?php endif; ?>

<!-- Tabs -->
<div style="display:flex;gap:6px;margin-bottom:20px;align-items:center;">
    <a href="/inserzionisti.php?tab=inserzionisti"
       class="btn <?= $tab==='inserzionisti'?'':'btn-secondary' ?> btn-sm">
        🏢 Inserzionisti <span style="background:rgba(255,255,255,0.15);padding:1px 6px;border-radius:8px;margin-left:4px;font-size:10px;"><?= count($inserzionisti) ?></span>
    </a>
    <a href="/inserzionisti.php?tab=contratti"
       class="btn <?= $tab==='contratti'?'':'btn-secondary' ?> btn-sm">
        📋 Contratti <span style="background:rgba(255,255,255,0.15);padding:1px 6px;border-radius:8px;margin-left:4px;font-size:10px;"><?= count($contratti) ?></span>
    </a>
    <div style="flex:1;"></div>
    <?php if ($tab==='inserzionisti'): ?>
    <a href="/inserzionisti.php?tab=inserzionisti&edit_ins=new" class="btn btn-sm">+ Nuovo inserzionista</a>
    <?php else: ?>
    <a href="/inserzionisti.php?tab=contratti&edit_con=new" class="btn btn-sm">+ Nuovo contratto</a>
    <?php endif; ?>
</div>

<?php if ($tab === 'inserzionisti'): ?>
<!-- ══ TAB INSERZIONISTI ══════════════════════════════════════ -->

<?php if ($edit_ins): ?>
<!-- Form edit/nuovo inserzionista -->
<div class="box" style="margin-bottom:20px;">
    <h2><?= $ins_edit ? 'Modifica inserzionista' : 'Nuovo inserzionista' ?></h2>
    <form method="POST">
        <input type="hidden" name="azione" value="salva_inserzionista">
        <input type="hidden" name="id" value="<?= $ins_edit['id'] ?? '' ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
                <label>Ragione sociale *</label>
                <input type="text" name="ragione_sociale" value="<?= htmlspecialchars($ins_edit['ragione_sociale']??'') ?>" required>
            </div>
            <div>
                <label>Referente</label>
                <input type="text" name="referente" value="<?= htmlspecialchars($ins_edit['referente']??'') ?>">
            </div>
            <div>
                <label>Settore</label>
                <select name="settore">
                    <?php foreach ($settori as $s): ?>
                    <option value="<?= $s ?>" <?= ($ins_edit['settore']??'')===$s?'selected':'' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($ins_edit['email']??'') ?>">
            </div>
            <div>
                <label>Telefono</label>
                <input type="tel" name="telefono" value="<?= htmlspecialchars($ins_edit['telefono']??'') ?>">
            </div>
        </div>
        <div style="margin-bottom:12px;">
            <label>Note</label>
            <textarea name="note" rows="2" style="width:100%;padding:10px;background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.10);border-radius:10px;color:#fff;font-size:13px;resize:vertical;"><?= htmlspecialchars($ins_edit['note']??'') ?></textarea>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="attivo" value="1" <?= ($ins_edit['attivo']??1)?'checked':'' ?>> Attivo
            </label>
            <button type="submit" class="btn">💾 Salva</button>
            <a href="/inserzionisti.php?tab=inserzionisti" class="btn btn-secondary btn-sm">Annulla</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Lista inserzionisti -->
<div class="box">
    <h2>Tutti gli inserzionisti (<?= count($inserzionisti) ?>)</h2>
    <?php if (empty($inserzionisti)): ?>
    <div class="vuoto">Nessun inserzionista. Aggiungine uno con il bottone in alto.</div>
    <?php else: ?>
    <table>
        <tr>
            <th>Azienda</th>
            <th>Referente</th>
            <th>Settore</th>
            <th>Contatti</th>
            <th>Contratti</th>
            <th>Contenuti</th>
            <th>Stato</th>
            <th>Azioni</th>
        </tr>
        <?php foreach ($inserzionisti as $i): ?>
        <tr style="opacity:<?= $i['attivo']?'1':'0.45' ?>">
            <td><strong><?= htmlspecialchars($i['ragione_sociale']) ?></strong></td>
            <td><?= htmlspecialchars($i['referente']??'-') ?></td>
            <td><?php if ($i['settore']): ?><span class="badge" style="background:rgba(99,102,241,0.15);color:#818cf8;"><?= $i['settore'] ?></span><?php else: ?>-<?php endif; ?></td>
            <td style="font-size:12px;color:var(--sg-muted);">
                <?php if ($i['email']): ?><div>✉ <?= htmlspecialchars($i['email']) ?></div><?php endif; ?>
                <?php if ($i['telefono']): ?><div>📞 <?= htmlspecialchars($i['telefono']) ?></div><?php endif; ?>
            </td>
            <td><a href="/inserzionisti.php?tab=contratti" style="color:var(--sg-orange);"><?= $i['n_contratti'] ?> contratti</a></td>
            <td><a href="/contenuti.php" style="color:var(--sg-muted);"><?= $i['n_contenuti'] ?> contenuti</a></td>
            <td>
                <button onclick="toggleIns(<?= $i['id'] ?>, <?= $i['attivo']?0:1 ?>, this)"
                        class="btn btn-sm" style="font-size:10px;padding:3px 8px;background:<?= $i['attivo']?'rgba(48,209,88,0.15)':'rgba(255,255,255,0.04)' ?>;color:<?= $i['attivo']?'var(--sg-green)':'var(--sg-muted)' ?>;border:1px solid <?= $i['attivo']?'rgba(48,209,88,0.2)':'rgba(255,255,255,0.08)' ?>;">
                    <?= $i['attivo']?'● Attivo':'○ Inattivo' ?>
                </button>
            </td>
            <td>
                <a href="/inserzionisti.php?tab=inserzionisti&edit_ins=<?= $i['id'] ?>" class="btn btn-sm btn-secondary">✏</a>
                <a href="/report_adv.php?ins=<?= $i['id'] ?>" class="btn btn-sm" style="background:rgba(59,130,246,0.15);color:#60a5fa;border:1px solid rgba(59,130,246,0.2);">📊</a>
                <a href="/inserzionisti.php?del_ins=<?= $i['id'] ?>" class="btn btn-sm btn-danger"
                   onclick="return confirm('Eliminare inserzionista e tutti i suoi contratti?')">✕</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ══ TAB CONTRATTI ══════════════════════════════════════════ -->

<?php if ($edit_con): ?>
<!-- Form edit/nuovo contratto -->
<div class="box" style="margin-bottom:20px;">
    <h2><?= $con_edit ? 'Modifica contratto' : 'Nuovo contratto' ?></h2>
    <form method="POST">
        <input type="hidden" name="azione" value="salva_contratto">
        <input type="hidden" name="id" value="<?= $con_edit['id'] ?? '' ?>">

        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
                <label>Inserzionista *</label>
                <select name="inserzionista_id" required>
                    <option value="">— Scegli —</option>
                    <?php foreach ($inserzionisti as $i): ?>
                    <option value="<?= $i['id'] ?>" <?= ($con_edit['inserzionista_id']??'')==$i['id']?'selected':'' ?>>
                        <?= htmlspecialchars($i['ragione_sociale']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Nome contratto</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($con_edit['nome']??'') ?>" placeholder="Es. Campagna Estate 2025">
            </div>
            <div>
                <label>Stato</label>
                <select name="stato">
                    <option value="attivo"   <?= ($con_edit['stato']??'attivo')==='attivo'?'selected':'' ?>>✅ Attivo</option>
                    <option value="sospeso"  <?= ($con_edit['stato']??'')==='sospeso'?'selected':'' ?>>⏸ Sospeso</option>
                    <option value="scaduto"  <?= ($con_edit['stato']??'')==='scaduto'?'selected':'' ?>>❌ Scaduto</option>
                </select>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
                <label>Data inizio *</label>
                <input type="date" name="data_inizio" value="<?= $con_edit['data_inizio']??date('Y-m-d') ?>" required>
            </div>
            <div>
                <label>Data fine *</label>
                <input type="date" name="data_fine" value="<?= $con_edit['data_fine']??'' ?>" required>
            </div>
            <div>
                <label>Importo (€)</label>
                <input type="number" name="importo" value="<?= $con_edit['importo']??0 ?>" step="0.01" min="0">
            </div>
            <div>
                <label>Frequenza (ogni X min)</label>
                <input type="number" name="frequenza_min" value="<?= $con_edit['frequenza_min']??30 ?>" min="1" max="120">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
                <label>Tipo contenuto</label>
                <select name="tipo_contenuto">
                    <option value="entrambi" <?= ($con_edit['tipo_contenuto']??'entrambi')==='entrambi'?'selected':'' ?>>🎬 Video + Immagini</option>
                    <option value="video"    <?= ($con_edit['tipo_contenuto']??'')==='video'?'selected':'' ?>>🎬 Solo video</option>
                    <option value="immagine" <?= ($con_edit['tipo_contenuto']??'')==='immagine'?'selected':'' ?>>🖼 Solo immagini</option>
                </select>
            </div>
            <div>
                <label>Fascia oraria</label>
                <input type="text" name="fascia_oraria" value="<?= htmlspecialchars($con_edit['fascia_oraria']??'') ?>" placeholder="Es. 08:00-22:00">
            </div>
            <div>
                <label>Club target</label>
                <div style="display:flex;flex-direction:column;gap:4px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.10);border-radius:10px;padding:10px;max-height:120px;overflow-y:auto;">
                    <?php
                    $club_sel = explode(',', $con_edit['club_target'] ?? '');
                    foreach ($clubs as $c):
                    ?>
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;">
                        <input type="checkbox" name="club_target[]" value="<?= htmlspecialchars($c) ?>"
                               <?= in_array($c, $club_sel)?'checked':'' ?>>
                        <?= htmlspecialchars($c) ?>
                    </label>
                    <?php endforeach; ?>
                    <?php if (empty($clubs)): ?><span style="font-size:11px;color:var(--sg-muted);">Nessun club configurato</span><?php endif; ?>
                </div>
            </div>
        </div>

        <div style="margin-bottom:12px;">
            <label>Note</label>
            <textarea name="note" rows="2" style="width:100%;padding:10px;background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.10);border-radius:10px;color:#fff;font-size:13px;resize:vertical;"><?= htmlspecialchars($con_edit['note']??'') ?></textarea>
        </div>

        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn">💾 Salva contratto</button>
            <a href="/inserzionisti.php?tab=contratti" class="btn btn-secondary btn-sm">Annulla</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Lista contratti -->
<div class="box">
    <h2>Tutti i contratti (<?= count($contratti) ?>)</h2>
    <?php if (empty($contratti)): ?>
    <div class="vuoto">Nessun contratto. Creane uno con il bottone in alto.</div>
    <?php else: ?>
    <table>
        <tr>
            <th>Inserzionista</th>
            <th>Nome</th>
            <th>Periodo</th>
            <th>Importo</th>
            <th>Tipo</th>
            <th>Club</th>
            <th>Passaggi</th>
            <th>Stato</th>
            <th>Azioni</th>
        </tr>
        <?php foreach ($contratti as $c):
            $oggi = date('Y-m-d');
            $scaduto = $c['data_fine'] < $oggi;
            $colore_stato = match($c['stato']) {
                'attivo'  => ['bg'=>'rgba(48,209,88,0.12)','col'=>'var(--sg-green)'],
                'sospeso' => ['bg'=>'rgba(255,214,10,0.12)','col'=>'var(--sg-yellow)'],
                default   => ['bg'=>'rgba(255,69,58,0.10)','col'=>'var(--sg-red)'],
            };
        ?>
        <tr>
            <td><strong><?= htmlspecialchars($c['ragione_sociale']??'-') ?></strong></td>
            <td><?= htmlspecialchars($c['nome']??'-') ?></td>
            <td style="font-size:12px;white-space:nowrap;">
                <?= date('d/m/Y', strtotime($c['data_inizio'])) ?> →<br>
                <span style="color:<?= $scaduto?'var(--sg-red)':'var(--sg-muted)' ?>"><?= date('d/m/Y', strtotime($c['data_fine'])) ?></span>
            </td>
            <td style="font-weight:700;color:var(--sg-green);">€<?= number_format($c['importo'],0,',','.') ?></td>
            <td style="font-size:12px;"><?= $c['tipo_contenuto'] ?></td>
            <td style="font-size:11px;color:var(--sg-muted);"><?= $c['club_target'] ? str_replace(',','<br>', htmlspecialchars($c['club_target'])) : 'Tutti' ?></td>
            <td style="text-align:center;">
                <a href="/report_adv.php?con=<?= $c['id'] ?>" style="color:var(--sg-orange);font-weight:700;">
                    <?= number_format($c['n_passaggi']) ?>
                </a>
            </td>
            <td>
                <span style="font-size:11px;font-weight:600;padding:3px 8px;border-radius:12px;background:<?= $colore_stato['bg'] ?>;color:<?= $colore_stato['col'] ?>;">
                    <?= ucfirst($c['stato']) ?>
                </span>
            </td>
            <td>
                <a href="/inserzionisti.php?tab=contratti&edit_con=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">✏</a>
                <a href="/report_adv.php?con=<?= $c['id'] ?>" class="btn btn-sm" style="background:rgba(59,130,246,0.15);color:#60a5fa;border:1px solid rgba(59,130,246,0.2);">📊</a>
                <a href="/inserzionisti.php?del_con=<?= $c['id'] ?>" class="btn btn-sm btn-danger"
                   onclick="return confirm('Eliminare questo contratto?')">✕</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
</div>

<?php endif; ?>
</div>

<script>
function toggleIns(id, val, btn) {
    const fd = new FormData();
    fd.append('azione','toggle_ins'); fd.append('id',id); fd.append('val',val);
    fetch('/inserzionisti.php',{method:'POST',body:fd}).then(()=>location.reload());
}
</script>

<?php require_once 'includes/footer.php'; ?>
