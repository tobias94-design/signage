<?php
// Recupera info sistema per la topbar
$db_ok = false;
try {
    require_once __DIR__ . '/db.php';
    $db2 = getDB();
    $db_ok = true;
    $tot_disp    = $db2->query("SELECT COUNT(*) FROM dispositivi")->fetchColumn();
    $online_disp = $db2->query("SELECT COUNT(*) FROM dispositivi WHERE stato='online' AND ultimo_ping > datetime('now','-2 minutes')")->fetchColumn();
    $tot_cont    = $db2->query("SELECT COUNT(*) FROM contenuti")->fetchColumn();
    $tot_play    = $db2->query("SELECT COUNT(*) FROM playlist")->fetchColumn();
} catch (Exception $e) {
    $tot_disp = $online_disp = $tot_cont = $tot_play = 0;
}
$pagina_corrente = basename($_SERVER['PHP_SELF']);
$titolo_pagina   = $titolo ?? 'Signage Manager';
?>

<header class="topbar">

    <!-- Logo + brand -->
    <div class="topbar-brand">
        <div class="topbar-logo">S</div>
        <div class="topbar-title">
            <span class="topbar-name">SIGNAGE</span>
            <span class="topbar-sub">Manager</span>
        </div>
    </div>

    <!-- Divider -->
    <div class="topbar-div"></div>

    <!-- Titolo pagina corrente -->
    <div class="topbar-page"><?php echo htmlspecialchars($titolo_pagina); ?></div>

    <!-- Spacer -->
    <div style="flex:1;"></div>

    <!-- KPI pills -->
    <div class="topbar-kpis">
        <div class="topbar-kpi">
            <span class="topbar-kpi-val"><?php echo $online_disp; ?>/<?php echo $tot_disp; ?></span>
            <span class="topbar-kpi-lbl">Dispositivi</span>
        </div>
        <div class="topbar-kpi-sep"></div>
        <div class="topbar-kpi">
            <span class="topbar-kpi-val"><?php echo $tot_cont; ?></span>
            <span class="topbar-kpi-lbl">Contenuti</span>
        </div>
        <div class="topbar-kpi-sep"></div>
        <div class="topbar-kpi">
            <span class="topbar-kpi-val"><?php echo $tot_play; ?></span>
            <span class="topbar-kpi-lbl">Playlist</span>
        </div>
    </div>

    <!-- System status pill -->
    <div class="topbar-status <?php echo ($online_disp > 0 || $tot_disp == 0) ? 'status-ok' : 'status-warn'; ?>">
        <div class="status-dot"></div>
        <?php if ($tot_disp == 0): ?>
            Nessun dispositivo
        <?php elseif ($online_disp == $tot_disp): ?>
            Sistema OK
        <?php elseif ($online_disp > 0): ?>
            <?php echo ($tot_disp - $online_disp); ?> offline
        <?php else: ?>
            Tutti offline
        <?php endif; ?>
    </div>

    <!-- Clock -->
    <div class="topbar-clock" id="sg-clock"><?php echo date('H:i:s'); ?></div>

</header>