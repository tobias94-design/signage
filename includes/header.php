<?php
$titolo = $titolo ?? 'Signage Manager';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titolo); ?> — Signage Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style-glass.css">
</head>
<body>
<div class="sg-blob sg-b1"></div>
<div class="sg-blob sg-b2"></div>
<div class="sg-blob sg-b3"></div>
<div class="sg-grain"></div>
<div class="sg-app">
<?php
// KPI per topbar
$_topbar_online = 0; $_topbar_tot = 0;
$_topbar_cont = 0; $_topbar_play = 0;
try {
    require_once __DIR__ . '/db.php';
    $_tdb = getDB();
    $_topbar_tot    = $_tdb->query("SELECT COUNT(*) FROM dispositivi")->fetchColumn();
    $_topbar_online = $_tdb->query("SELECT COUNT(*) FROM dispositivi WHERE ultimo_ping > datetime('now','-2 minutes')")->fetchColumn();
    $_topbar_cont   = $_tdb->query("SELECT COUNT(*) FROM contenuti")->fetchColumn();
    $_topbar_play   = $_tdb->query("SELECT COUNT(*) FROM playlist")->fetchColumn();
} catch(Exception $e) {}
?>
<!-- ══ TOPBAR ══════════════════════════════════════════════ -->
<header class="topbar">
    <div class="topbar-brand">
        <div class="topbar-logo">S</div>
        <div class="topbar-title">
            <span class="topbar-name">SIGNAGE</span>
            <span class="topbar-sub">Manager</span>
        </div>
    </div>
    <div class="topbar-div"></div>
    <div class="topbar-page"><?php echo htmlspecialchars($titolo); ?></div>
    <div style="flex:1;"></div>
    <div class="topbar-kpis">
        <div class="topbar-kpi">
            <span class="topbar-kpi-val"><?php echo $_topbar_online; ?>/<?php echo $_topbar_tot; ?></span>
            <span class="topbar-kpi-lbl">Schermi</span>
        </div>
        <div class="topbar-kpi-sep"></div>
        <div class="topbar-kpi">
            <span class="topbar-kpi-val"><?php echo $_topbar_cont; ?></span>
            <span class="topbar-kpi-lbl">Contenuti</span>
        </div>
        <div class="topbar-kpi-sep"></div>
        <div class="topbar-kpi">
            <span class="topbar-kpi-val"><?php echo $_topbar_play; ?></span>
            <span class="topbar-kpi-lbl">Playlist</span>
        </div>
    </div>
    <div class="topbar-status <?php echo ($_topbar_online > 0 || $_topbar_tot == 0) ? 'status-ok' : 'status-warn'; ?>">
        <div class="status-dot"></div>
        <?php
        if ($_topbar_tot == 0)                   echo 'Nessun schermo';
        elseif ($_topbar_online == $_topbar_tot)  echo 'Sistema OK';
        elseif ($_topbar_online > 0)              echo ($_topbar_tot - $_topbar_online).' offline';
        else                                      echo 'Tutti offline';
        ?>
    </div>
    <div class="topbar-clock" id="sg-clock"><?php echo date('H:i:s'); ?></div>
</header>
<!-- ══ BODY ════════════════════════════════════════════════ -->
<div class="sg-body">
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="sg-content">