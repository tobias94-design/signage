<?php
$pagina_corrente = basename($_SERVER['PHP_SELF']);
$me = getUtenteCorrente();

$badge_disp = 0;
try {
    $db2 = getDB();
    $badge_disp = $db2->query("
        SELECT COUNT(*) FROM dispositivi
        WHERE (ultimo_ping IS NULL OR ultimo_ping < datetime('now','-2 minutes'))
    ")->fetchColumn();
} catch(Exception $e) {}

$voci = [
    'dashboard.php'      => ['icona'=>'⊞', 'label'=>'Dashboard',     'sezione'=>'principale'],
    'contenuti.php'      => ['icona'=>'▤',  'label'=>'Contenuti',     'sezione'=>'principale'],
    'playlist.php'       => ['icona'=>'⊟',  'label'=>'Playlist',      'sezione'=>'principale'],
    'profili.php'        => ['icona'=>'◈',  'label'=>'Profili',       'sezione'=>'principale'],
    'club.php'           => ['icona'=>'⬡',  'label'=>'Club',          'sezione'=>'principale'],
    'dispositivi.php'    => ['icona'=>'▦',  'label'=>'Dispositivi',   'sezione'=>'principale', 'badge'=>$badge_disp>0?$badge_disp:null],
    'layout.php'         => ['icona'=>'▣',  'label'=>'Layout',        'sezione'=>'principale'],
    'lobby_manager.php'  => ['icona'=>'🏨', 'label'=>'Lobby',         'sezione'=>'principale'],
    'inserzionisti.php'  => ['icona'=>'🏢', 'label'=>'Inserzionisti', 'sezione'=>'adv'],
    'report_adv.php'     => ['icona'=>'📊', 'label'=>'Report ADV',    'sezione'=>'adv'],
    'utenti.php'         => ['icona'=>'👥', 'label'=>'Utenti',        'sezione'=>'sistema', 'admin_only'=>true],
];
?>

<nav class="sidebar" id="sg-sidebar">

    <button class="sb-toggle" id="sb-toggle" title="Comprimi sidebar" onclick="toggleSidebar()">☰</button>

    <div class="sb-section">Principale</div>
    <?php foreach ($voci as $file => $voce):
        if ($voce['sezione'] !== 'principale') continue;
        $attiva = ($pagina_corrente === $file) ? 'active' : '';
    ?>
    <a href="/<?= $file ?>" class="sb-item <?= $attiva ?>" onclick="closeMobileSidebar()">
        <span class="sb-icon"><?= $voce['icona'] ?></span>
        <span class="sb-label"><?= $voce['label'] ?></span>
        <?php if (!empty($voce['badge'])): ?>
        <span class="sb-badge"><?= $voce['badge'] ?></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>

    <div class="sb-section">Sistema</div>
    <?php foreach ($voci as $file => $voce):
        if ($voce['sezione'] !== 'sistema') continue;
        if (!empty($voce['admin_only']) && !isAdmin()) continue;
        $attiva = ($pagina_corrente === $file) ? 'active' : '';
    ?>
    <a href="/<?= $file ?>" class="sb-item <?= $attiva ?>" onclick="closeMobileSidebar()">
        <span class="sb-icon"><?= $voce['icona'] ?></span>
        <span class="sb-label"><?= $voce['label'] ?></span>
    </a>
    <?php endforeach; ?>

    <div class="sb-section">Pubblicità</div>
    <?php foreach ($voci as $file => $voce):
        if ($voce['sezione'] !== 'adv') continue;
        $attiva = ($pagina_corrente === $file) ? 'active' : '';
    ?>
    <a href="/<?= $file ?>" class="sb-item <?= $attiva ?>" onclick="closeMobileSidebar()">
        <span class="sb-icon"><?= $voce['icona'] ?></span>
        <span class="sb-label"><?= $voce['label'] ?></span>
    </a>
    <?php endforeach; ?>

    <div class="sb-btm">
        <a href="/profilo.php" class="sb-item <?= $pagina_corrente==='profilo.php'?'active':'' ?>" onclick="closeMobileSidebar()">
            <span class="sb-icon" style="font-size:13px;font-weight:800;color:<?= ($me['ruolo']??'')==='admin'?'var(--sg-orange)':'var(--sg-muted)' ?>;">
                <?= strtoupper(substr($me['nome']??'?',0,1)) ?>
            </span>
            <span class="sb-label" style="font-size:12px;"><?= htmlspecialchars($me['nome']??'') ?></span>
        </a>
        <a href="/logout.php" class="sb-item exit">
            <span class="sb-icon">⎋</span>
            <span class="sb-label">Esci</span>
        </a>
    </div>

</nav>

<script>
// Mostra hamburger solo su mobile
(function() {
    var btn = document.getElementById('mob-hamburger');
    if (btn) btn.style.display = window.innerWidth <= 768 ? 'flex' : 'none';
    window.addEventListener('resize', function() {
        var b = document.getElementById('mob-hamburger');
        if (b) b.style.display = window.innerWidth <= 768 ? 'flex' : 'none';
    });
})();

function toggleSidebar() {
    const sb = document.getElementById('sg-sidebar');
    if (window.innerWidth <= 768) { toggleMobileSidebar(); return; }
    sb.classList.toggle('collapsed');
    const collapsed = sb.classList.contains('collapsed');
    localStorage.setItem('sb_collapsed', collapsed ? '1' : '0');
    document.getElementById('sb-toggle').title = collapsed ? 'Espandi sidebar' : 'Comprimi sidebar';
}

function toggleMobileSidebar() {
    const sb = document.getElementById('sg-sidebar');
    const overlay = document.getElementById('mob-overlay');
    sb.classList.toggle('mob-open');
    overlay.classList.toggle('mob-overlay-visible');
    document.body.classList.toggle('mob-sidebar-open');
}

function closeMobileSidebar() {
    if (window.innerWidth > 768) return;
    const sb = document.getElementById('sg-sidebar');
    const overlay = document.getElementById('mob-overlay');
    sb.classList.remove('mob-open');
    overlay.classList.remove('mob-overlay-visible');
    document.body.classList.remove('mob-sidebar-open');
}

(function() {
    if (window.innerWidth > 768 && localStorage.getItem('sb_collapsed') === '1') {
        document.getElementById('sg-sidebar').classList.add('collapsed');
    }
})();
</script>
