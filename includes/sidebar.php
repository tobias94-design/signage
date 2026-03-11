<?php
$pagina_corrente = basename($_SERVER['PHP_SELF']);

// Conta allerte: dispositivi offline
$badge_club = 0;
try {
    $db2 = getDB();
    $badge_club = $db2->query("
        SELECT COUNT(*) FROM dispositivi
        WHERE (ultimo_ping IS NULL OR ultimo_ping < datetime('now','-2 minutes'))
    ")->fetchColumn();
} catch(Exception $e) {}

$voci = [
    'index.php'      => ['icona' => '⊞', 'label' => 'Dashboard',  'sezione' => 'principale'],
    'contenuti.php'  => ['icona' => '▤',  'label' => 'Contenuti',  'sezione' => 'principale'],
    'playlist.php'   => ['icona' => '⊟',  'label' => 'Playlist',   'sezione' => 'principale'],
    'profili.php'    => ['icona' => '◈',  'label' => 'Profili',    'sezione' => 'principale'],
    'club.php'       => ['icona' => '⬡',  'label' => 'Club',       'sezione' => 'principale', 'badge' => $badge_club > 0 ? $badge_club : null],
    'layout.php'     => ['icona' => '▣',  'label' => 'Layout',     'sezione' => 'sistema'],
];
?>

<nav class="sidebar" id="sg-sidebar">

    <!-- Toggle collapse -->
    <button class="sb-toggle" id="sb-toggle" title="Comprimi sidebar" onclick="toggleSidebar()">☰</button>

    <div class="sb-section">Principale</div>

    <?php foreach ($voci as $file => $voce):
        if ($voce['sezione'] !== 'principale') continue;
        $attiva = ($pagina_corrente === $file) ? 'active' : '';
    ?>
    <a href="/<?php echo $file; ?>" class="sb-item <?php echo $attiva; ?>">
        <span class="sb-icon"><?php echo $voce['icona']; ?></span>
        <span class="sb-label"><?php echo $voce['label']; ?></span>
        <?php if (!empty($voce['badge'])): ?>
        <span class="sb-badge"><?php echo $voce['badge']; ?></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>

    <div class="sb-section">Sistema</div>

    <?php foreach ($voci as $file => $voce):
        if ($voce['sezione'] !== 'sistema') continue;
        $attiva = ($pagina_corrente === $file) ? 'active' : '';
    ?>
    <a href="/<?php echo $file; ?>" class="sb-item <?php echo $attiva; ?>">
        <span class="sb-icon"><?php echo $voce['icona']; ?></span>
        <span class="sb-label"><?php echo $voce['label']; ?></span>
    </a>
    <?php endforeach; ?>

    <!-- Bottom: logout -->
    <div class="sb-btm">
        <a href="/logout.php" class="sb-item exit">
            <span class="sb-icon">⎋</span>
            <span class="sb-label">Esci</span>
        </a>
    </div>

</nav>

<script>
function toggleSidebar() {
    const sb = document.getElementById('sg-sidebar');
    sb.classList.toggle('collapsed');
    const collapsed = sb.classList.contains('collapsed');
    localStorage.setItem('sb_collapsed', collapsed ? '1' : '0');
    document.getElementById('sb-toggle').title = collapsed ? 'Espandi sidebar' : 'Comprimi sidebar';
}
// Restore state
(function() {
    if (localStorage.getItem('sb_collapsed') === '1') {
        document.getElementById('sg-sidebar').classList.add('collapsed');
    }
})();
</script>