<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
$db = getDB();

// Raggruppa dispositivi per club
$dispositivi = $db->query("
    SELECT d.*, p.nome as profilo_nome,
           CASE WHEN d.ultimo_ping > datetime('now','-2 minutes') THEN 1 ELSE 0 END AS is_online
    FROM dispositivi d
    LEFT JOIN profili p ON p.id = d.profilo_id
    ORDER BY d.club, d.nome
")->fetchAll(PDO::FETCH_ASSOC);

$clubs = [];
foreach ($dispositivi as $d) {
    $club = !empty($d['club']) ? $d['club'] : '(senza club)';
    if (!isset($clubs[$club])) {
        $clubs[$club] = [
            'nome'       => $club,
            'indirizzo'  => $d['indirizzo'] ?? '',
            'lat'        => $d['lat'] ?? null,
            'lon'        => $d['lon'] ?? null,
            'dispositivi'=> [],
            'online'     => 0,
            'offline'    => 0,
            'tv_totali'  => 0,
        ];
    }
    $clubs[$club]['dispositivi'][] = $d;
    if ($d['is_online']) $clubs[$club]['online']++;
    else $clubs[$club]['offline']++;
    $clubs[$club]['tv_totali'] += max(1, (int)($d['numero_tv'] ?? 0));
    // Prendi il primo indirizzo/coordinate non vuoti
    if (empty($clubs[$club]['lat']) && !empty($d['lat'])) {
        $clubs[$club]['lat'] = $d['lat'];
        $clubs[$club]['lon'] = $d['lon'];
        $clubs[$club]['indirizzo'] = $d['indirizzo'];
    }
}
$clubs = array_values($clubs);

// Markers per la mappa (solo club con coordinate)
$markers = array_filter($clubs, fn($c) => !empty($c['lat']));

$titolo = 'Club';
require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
#club-map { width:100%; height:320px; border-radius:16px; overflow:hidden; border:1px solid rgba(255,255,255,0.08); }
.club-card {
    background:rgba(255,255,255,0.035);
    border:1px solid rgba(255,255,255,0.08);
    border-radius:16px;
    padding:18px;
    transition:border-color 0.2s, background 0.2s;
    cursor:default;
}
.club-card:hover { border-color:rgba(232,80,2,0.30); background:rgba(232,80,2,0.04); }
.club-card.all-online { border-color:rgba(48,209,88,0.20); }
.club-card.has-offline { border-color:rgba(255,159,107,0.20); }
.dev-pill {
    display:inline-flex;align-items:center;gap:5px;
    padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
    background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);
    color:var(--sg-muted);margin:2px;
}
.dev-pill.online { background:rgba(48,209,88,0.10);border-color:rgba(48,209,88,0.20);color:var(--sg-green); }
.dev-pill.offline { background:rgba(255,255,255,0.04);border-color:rgba(255,255,255,0.06);color:rgba(255,255,255,0.30); }
</style>

<div class="container">

<!-- ── HEADER + RICERCA ── -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
    <input type="text" id="club-search" placeholder="🔍  Cerca club o indirizzo..."
           oninput="filtraClub(this.value)"
           style="flex:1;min-width:200px;max-width:400px;padding:10px 16px;
                  background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.10);
                  border-radius:10px;color:var(--sg-white);font-size:14px;">
    <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
        <span style="font-size:13px;color:var(--sg-muted);"><?= count($clubs) ?> club · <?= count($dispositivi) ?> dispositivi</span>
        <a href="dispositivi.php?view=nuovo" class="btn btn-sm">+ Nuovo dispositivo</a>
    </div>
</div>

<!-- ── MAPPA ── -->
<?php if (!empty($markers)): ?>
<div class="box" style="margin-bottom:24px;padding:16px;">
    <div style="font-size:11px;font-weight:700;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">
        📍 Mappa club <span style="font-weight:400;color:var(--sg-muted);text-transform:none;letter-spacing:0;">(<?= count($markers) ?> con coordinate)</span>
        <span style="float:right;font-size:10px;font-weight:400;">Per aggiungere coordinate vai su <a href="dispositivi.php" style="color:var(--sg-orange);">Dispositivi</a> → Modifica → Geocodifica</span>
    </div>
    <div id="club-map"></div>
</div>
<?php else: ?>
<div class="box" style="margin-bottom:24px;padding:20px;text-align:center;border:1px dashed rgba(255,255,255,0.08);">
    <div style="font-size:13px;color:var(--sg-muted);">
        🗺️ Nessuna coordinata ancora. Vai su <a href="dispositivi.php" style="color:var(--sg-orange);">Dispositivi</a> → Modifica → inserisci indirizzo → clicca Geocodifica.
    </div>
</div>
<?php endif; ?>

<!-- ── GRIGLIA CLUB ── -->
<div id="club-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
<?php foreach ($clubs as $c):
    $all_on  = $c['offline'] === 0 && $c['online'] > 0;
    $has_off = $c['offline'] > 0;
    $cls     = $all_on ? 'all-online' : ($has_off ? 'has-offline' : '');
?>
<div class="club-card <?= $cls ?>" data-search="<?= strtolower(htmlspecialchars($c['nome'].' '.($c['indirizzo']??''))) ?>">

    <!-- Nome + stato -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:10px;">
        <div style="font-size:15px;font-weight:800;color:var(--sg-white);line-height:1.2;"><?= htmlspecialchars($c['nome']) ?></div>
        <div style="flex-shrink:0;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
            background:<?= $all_on?'rgba(48,209,88,0.12)':($has_off?'rgba(255,159,107,0.12)':'rgba(255,255,255,0.06)') ?>;
            color:<?= $all_on?'var(--sg-green)':($has_off?'#ff9f6b':'var(--sg-muted)') ?>;">
            <?= $all_on?'✓ Online':($has_off?$c['offline'].' offline':'—') ?>
        </div>
    </div>

    <!-- Indirizzo -->
    <?php if (!empty($c['indirizzo'])): ?>
    <div style="font-size:12px;color:var(--sg-muted);margin-bottom:10px;">📍 <?= htmlspecialchars($c['indirizzo']) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div style="display:flex;gap:12px;margin-bottom:12px;">
        <div style="text-align:center;">
            <div style="font-size:18px;font-weight:800;color:var(--sg-white);"><?= count($c['dispositivi']) ?></div>
            <div style="font-size:10px;color:var(--sg-muted);">PixelBridge</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:18px;font-weight:800;color:var(--sg-white);"><?= $c['tv_totali'] ?></div>
            <div style="font-size:10px;color:var(--sg-muted);">TV totali</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:18px;font-weight:800;color:<?= $c['online']>0?'var(--sg-green)':'var(--sg-muted)' ?>;"><?= $c['online'] ?></div>
            <div style="font-size:10px;color:var(--sg-muted);">Online</div>
        </div>
    </div>

    <!-- Dispositivi pills -->
    <div style="display:flex;flex-wrap:wrap;gap:2px;margin-bottom:12px;">
    <?php foreach ($c['dispositivi'] as $d): ?>
    <a href="dispositivi.php?view=modifica&token=<?= $d['token'] ?>"
       class="dev-pill <?= $d['is_online']?'online':'offline' ?>"
       title="<?= htmlspecialchars($d['nome']) ?> · <?= $d['is_online']?'Online':'Offline' ?>">
        <span style="width:5px;height:5px;border-radius:50%;background:<?= $d['is_online']?'var(--sg-green)':'rgba(255,255,255,0.2)' ?>;flex-shrink:0;"></span>
        <?= htmlspecialchars($d['nome']) ?>
    </a>
    <?php endforeach; ?>
    </div>

    <!-- Azioni -->
    <div style="display:flex;gap:6px;border-top:1px solid rgba(255,255,255,0.05);padding-top:12px;">
        <a href="layout.php?dev=<?= urlencode($c['dispositivi'][0]['token'] ?? '') ?>"
           class="btn btn-sm btn-secondary" style="font-size:11px;">⚙ Layout</a>
        <a href="dispositivi.php?view=modifica&token=<?= urlencode($c['dispositivi'][0]['token'] ?? '') ?>"
           class="btn btn-sm btn-secondary" style="font-size:11px;">✏️ Modifica</a>
        <?php if (!empty($c['dispositivi'][0]['token'])): ?>
        <a href="player/corsi.php?token=<?= urlencode($c['dispositivi'][0]['token']) ?>"
           target="_blank" class="btn btn-sm btn-success" style="font-size:11px;">▶ Player</a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<div id="no-results-club" style="display:none;padding:40px;text-align:center;color:var(--sg-muted);font-size:13px;">
    Nessun club trovato.
</div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
// ── Mappa ──
<?php if (!empty($markers)): ?>
var map = L.map('club-map', { zoomControl: true });
L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '© OpenStreetMap © CARTO', maxZoom: 18
}).addTo(map);

var markers = [];
<?php foreach ($markers as $c):
    $all_on = $c['offline'] === 0 && $c['online'] > 0;
    $color  = $all_on ? '#30d158' : ($c['offline'] > 0 ? '#ff9f6b' : '#888');
?>
(function() {
    var icon = L.divIcon({
        html: '<div style="width:14px;height:14px;border-radius:50%;background:<?= $color ?>;border:2px solid #fff;box-shadow:0 0 8px <?= $color ?>44;"></div>',
        iconSize: [14,14], iconAnchor: [7,7], className: ''
    });
    var m = L.marker([<?= $c['lat'] ?>, <?= $c['lon'] ?>], {icon: icon})
        .bindPopup('<b><?= htmlspecialchars($c['nome']) ?></b><br><?= htmlspecialchars($c['indirizzo']) ?><br><?= $c['online'] ?>/<?= count($c['dispositivi']) ?> online · <?= $c['tv_totali'] ?> TV');
    m.addTo(map);
    markers.push(m);
})();
<?php endforeach; ?>

if (markers.length > 0) {
    var group = L.featureGroup(markers);
    map.fitBounds(group.getBounds().pad(0.2));
}
<?php endif; ?>

// ── Ricerca ──
function filtraClub(q) {
    q = q.toLowerCase().trim();
    var cards = document.querySelectorAll('.club-card');
    var found = 0;
    cards.forEach(function(c) {
        var match = !q || c.dataset.search.includes(q);
        c.style.display = match ? '' : 'none';
        if (match) found++;
    });
    document.getElementById('no-results-club').style.display = (found === 0 && q) ? 'block' : 'none';
}

// ── Responsive griglia ──
function aggiornaGriglia() {
    var grid = document.getElementById('club-grid');
    if (!grid) return;
    var w = window.innerWidth;
    grid.style.gridTemplateColumns = w < 640 ? '1fr' : (w < 1024 ? 'repeat(2,1fr)' : 'repeat(3,1fr)');
}
window.addEventListener('resize', aggiornaGriglia);
aggiornaGriglia();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>