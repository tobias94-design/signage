<?php
$pagina_corrente = basename($_SERVER['PHP_SELF']);
?>
<nav class="nav">
    <a href="/index.php" <?php echo $pagina_corrente === 'index.php' ? 'class="active"' : ''; ?>>Dashboard</a>
    <a href="/contenuti.php" <?php echo $pagina_corrente === 'contenuti.php' ? 'class="active"' : ''; ?>>Contenuti</a>
    <a href="/playlist.php" <?php echo $pagina_corrente === 'playlist.php' ? 'class="active"' : ''; ?>>Playlist</a>
    <a href="/profili.php" <?php echo $pagina_corrente === 'profili.php' ? 'class="active"' : ''; ?>>Profili</a>
    <a href="/dispositivi.php" <?php echo $pagina_corrente === 'dispositivi.php' ? 'class="active"' : ''; ?>>Dispositivi</a>
    <a href="/layout.php" <?php echo $pagina_corrente === 'layout.php' ? 'class="active"' : ''; ?>>Layout</a>
    <a href="/logout.php" style="margin-left:auto; color:#e94560;">Esci →</a>
</nav>