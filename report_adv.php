<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();

// Filtri
$ins_id = isset($_GET['ins']) ? (int)$_GET['ins'] : 0;
$con_id = isset($_GET['con']) ? (int)$_GET['con'] : 0;
$da     = $_GET['da'] ?? date('Y-m-01');
$a      = $_GET['a']  ?? date('Y-m-d');

// Inserzionista selezionato
$inserzionista = null;
$contratto     = null;

if ($con_id) {
    $contratto     = $db->query("SELECT c.*,i.ragione_sociale,i.email,i.referente FROM contratti c JOIN inserzionisti i ON i.id=c.inserzionista_id WHERE c.id=$con_id")->fetch(PDO::FETCH_ASSOC);
    $ins_id        = $contratto['inserzionista_id'] ?? 0;
    $da            = $contratto['data_inizio'] ?? $da;
    $a             = $contratto['data_fine'] < date('Y-m-d') ? $contratto['data_fine'] : date('Y-m-d');
}
if ($ins_id) {
    $inserzionista = $db->query("SELECT * FROM inserzionisti WHERE id=$ins_id")->fetch(PDO::FETCH_ASSOC);
}

// Lista inserzionisti per filtro
$tutti_ins = $db->query("SELECT id, ragione_sociale FROM inserzionisti ORDER BY ragione_sociale")->fetchAll(PDO::FETCH_ASSOC);
$tutti_con = $db->query("SELECT c.id, c.nome, i.ragione_sociale FROM contratti c JOIN inserzionisti i ON i.id=c.inserzionista_id ORDER BY c.data_fine DESC")->fetchAll(PDO::FETCH_ASSOC);

// Query base log
function buildWhere($db, $ins_id, $con_id, $da, $a) {
    $where = ["l.passato_il BETWEEN '$da 00:00:00' AND '$a 23:59:59'"];
    if ($ins_id) $where[] = "con.inserzionista_id = $ins_id";
    if ($con_id) {
        $c = $db->query("SELECT data_inizio,data_fine FROM contratti WHERE id=$con_id")->fetch();
        if ($c) $where[] = "l.passato_il BETWEEN '{$c['data_inizio']} 00:00:00' AND '{$c['data_fine']} 23:59:59'";
    }
    return implode(' AND ', $where);
}

$where = buildWhere($db, $ins_id, $con_id, $da, $a);

// KPI totali
$kpi = $db->query("
    SELECT COUNT(*) as tot_passaggi,
           COUNT(DISTINCT l.dispositivo_token) as tot_schermi,
           COUNT(DISTINCT con.id) as tot_contenuti,
           SUM(l.durata_sec) as tot_secondi
    FROM log_adv l
    JOIN contenuti con ON con.id = l.contenuto_id
    WHERE $where
")->fetch(PDO::FETCH_ASSOC);

// Passaggi per giorno
$per_giorno = $db->query("
    SELECT date(l.passato_il) as giorno, COUNT(*) as n
    FROM log_adv l
    JOIN contenuti con ON con.id = l.contenuto_id
    WHERE $where
    GROUP BY giorno ORDER BY giorno
")->fetchAll(PDO::FETCH_ASSOC);

// Passaggi per club
$per_club = $db->query("
    SELECT l.club, COUNT(*) as n
    FROM log_adv l
    JOIN contenuti con ON con.id = l.contenuto_id
    WHERE $where AND l.club != ''
    GROUP BY l.club ORDER BY n DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Passaggi per contenuto
$per_contenuto = $db->query("
    SELECT con.nome, con.tipo, COUNT(*) as n, SUM(l.durata_sec) as sec
    FROM log_adv l
    JOIN contenuti con ON con.id = l.contenuto_id
    WHERE $where
    GROUP BY con.id ORDER BY n DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Passaggi per fascia oraria
$per_ora = $db->query("
    SELECT strftime('%H',l.passato_il) as ora, COUNT(*) as n
    FROM log_adv l
    JOIN contenuti con ON con.id = l.contenuto_id
    WHERE $where
    GROUP BY ora ORDER BY ora
")->fetchAll(PDO::FETCH_ASSOC);

$titolo = 'Report ADV';
require_once 'includes/header.php';
?>

<div class="container">

<!-- Header filtri -->
<div class="box" style="margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div>
            <label>Inserzionista</label>
            <select name="ins" onchange="this.form.submit()">
                <option value="">Tutti</option>
                <?php foreach ($tutti_ins as $i): ?>
                <option value="<?= $i['id'] ?>" <?= $ins_id===$i['id']?'selected':'' ?>><?= htmlspecialchars($i['ragione_sociale']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Contratto</label>
            <select name="con" onchange="this.form.submit()">
                <option value="">Tutti</option>
                <?php foreach ($tutti_con as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $con_id===$c['id']?'selected':'' ?>><?= htmlspecialchars($c['ragione_sociale'].' — '.$c['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Dal</label>
            <input type="date" name="da" value="<?= $da ?>">
        </div>
        <div>
            <label>Al</label>
            <input type="date" name="a" value="<?= $a ?>">
        </div>
        <button type="submit" class="btn btn-sm">🔍 Filtra</button>
        <a href="/report_adv.php" class="btn btn-secondary btn-sm">Reset</a>
    </form>
</div>

<?php if ($inserzionista): ?>
<div style="display:flex;align-items:center;gap:12px;padding:14px 18px;background:rgba(232,80,2,0.06);border:1px solid rgba(232,80,2,0.15);border-radius:14px;margin-bottom:20px;">
    <div style="font-size:28px;">🏢</div>
    <div>
        <div style="font-size:18px;font-weight:800;color:var(--sg-white);"><?= htmlspecialchars($inserzionista['ragione_sociale']) ?></div>
        <div style="font-size:12px;color:var(--sg-muted);">
            <?= $inserzionista['settore'] ? $inserzionista['settore'].' · ' : '' ?>
            <?= $inserzionista['referente'] ? htmlspecialchars($inserzionista['referente']).' · ' : '' ?>
            <?= htmlspecialchars($inserzionista['email']??'') ?>
        </div>
    </div>
    <?php if ($contratto): ?>
    <div style="margin-left:auto;text-align:right;">
        <div style="font-size:13px;font-weight:700;color:var(--sg-orange);"><?= htmlspecialchars($contratto['nome']??'') ?></div>
        <div style="font-size:11px;color:var(--sg-muted);"><?= date('d/m/Y',strtotime($contratto['data_inizio'])) ?> → <?= date('d/m/Y',strtotime($contratto['data_fine'])) ?></div>
        <div style="font-size:14px;font-weight:800;color:var(--sg-green);">€<?= number_format($contratto['importo'],0,',','.') ?></div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- KPI Cards -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
    <?php
    $kpis = [
        ['📺','Passaggi totali', number_format($kpi['tot_passaggi']??0), 'var(--sg-orange)'],
        ['📍','Schermi raggiunti', number_format($kpi['tot_schermi']??0), 'var(--sg-green)'],
        ['🎬','Contenuti', number_format($kpi['tot_contenuti']??0), '#818cf8'],
        ['⏱','Minuti in onda', number_format(($kpi['tot_secondi']??0)/60, 0), '#60a5fa'],
    ];
    foreach ($kpis as [$ico,$lab,$val,$col]): ?>
    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:18px;">
        <div style="font-size:22px;margin-bottom:6px;"><?= $ico ?></div>
        <div style="font-size:28px;font-weight:900;color:<?= $col ?>;"><?= $val ?: '0' ?></div>
        <div style="font-size:11px;color:var(--sg-muted);margin-top:4px;text-transform:uppercase;letter-spacing:1px;"><?= $lab ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px;">

    <!-- Grafico passaggi per giorno -->
    <div class="box">
        <h2>📈 Passaggi per giorno</h2>
        <?php if (empty($per_giorno)): ?>
        <div class="vuoto">Nessun dato nel periodo selezionato.</div>
        <?php else: ?>
        <div style="margin-top:12px;">
        <?php
        $max = max(array_column($per_giorno,'n')) ?: 1;
        foreach ($per_giorno as $r):
            $pct = round($r['n'] / $max * 100);
        ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <div style="font-size:11px;color:var(--sg-muted);width:70px;flex-shrink:0;"><?= date('d/m', strtotime($r['giorno'])) ?></div>
            <div style="flex:1;background:rgba(255,255,255,0.05);border-radius:4px;height:18px;position:relative;">
                <div style="position:absolute;left:0;top:0;bottom:0;width:<?= $pct ?>%;background:rgba(232,80,2,0.6);border-radius:4px;"></div>
            </div>
            <div style="font-size:12px;font-weight:700;width:30px;text-align:right;"><?= $r['n'] ?></div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Per club -->
    <div class="box">
        <h2>📍 Per club</h2>
        <?php if (empty($per_club)): ?>
        <div class="vuoto">Nessun dato.</div>
        <?php else: ?>
        <div style="margin-top:12px;">
        <?php
        $max = max(array_column($per_club,'n')) ?: 1;
        foreach ($per_club as $r):
            $pct = round($r['n'] / $max * 100);
        ?>
        <div style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
                <span style="font-weight:600;"><?= htmlspecialchars($r['club']) ?></span>
                <span style="color:var(--sg-orange);font-weight:700;"><?= $r['n'] ?></span>
            </div>
            <div style="background:rgba(255,255,255,0.05);border-radius:4px;height:8px;">
                <div style="height:100%;width:<?= $pct ?>%;background:rgba(232,80,2,0.5);border-radius:4px;"></div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

    <!-- Per contenuto -->
    <div class="box">
        <h2>🎬 Top contenuti</h2>
        <?php if (empty($per_contenuto)): ?>
        <div class="vuoto">Nessun dato.</div>
        <?php else: ?>
        <table style="margin-top:8px;">
            <tr><th>Contenuto</th><th>Tipo</th><th>Passaggi</th><th>Min in onda</th></tr>
            <?php foreach ($per_contenuto as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['nome']) ?></td>
                <td><span class="badge badge-<?= $r['tipo'] ?>"><?= strtoupper($r['tipo']) ?></span></td>
                <td style="font-weight:700;color:var(--sg-orange);"><?= $r['n'] ?></td>
                <td style="color:var(--sg-muted);"><?= round($r['sec']/60) ?>m</td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>

    <!-- Per fascia oraria -->
    <div class="box">
        <h2>🕐 Per fascia oraria</h2>
        <?php if (empty($per_ora)): ?>
        <div class="vuoto">Nessun dato.</div>
        <?php else: ?>
        <div style="margin-top:12px;">
        <?php
        $max = max(array_column($per_ora,'n')) ?: 1;
        foreach ($per_ora as $r):
            $pct = round($r['n'] / $max * 100);
        ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
            <div style="font-size:11px;color:var(--sg-muted);width:32px;flex-shrink:0;"><?= $r['ora'] ?>:00</div>
            <div style="flex:1;background:rgba(255,255,255,0.05);border-radius:3px;height:14px;position:relative;">
                <div style="position:absolute;left:0;top:0;bottom:0;width:<?= $pct ?>%;background:rgba(96,165,250,0.5);border-radius:3px;"></div>
            </div>
            <div style="font-size:11px;width:24px;text-align:right;color:var(--sg-muted);"><?= $r['n'] ?></div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

</div>

<?php require_once 'includes/footer.php'; ?>
<script>
document.querySelectorAll('input[type=date]').forEach(el => {
    el.addEventListener('click', function() { try { this.showPicker(); } catch(e) {} });
});
</script>
