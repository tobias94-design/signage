<?php
// includes/topnav.php
// Variabili disponibili: UTENTE_NOME, TENANT_NOME, TENANT_PIANO
$initial = strtoupper(substr(UTENTE_NOME, 0, 1)) ?: 'U';
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="topnav">
  <a href="/dashboard.php" class="logo">Pixel<em>Bridge</em></a>

  <div class="topnav-links">
    <a class="topnav-link <?php echo in_array($current_page, ['dashboard']) ? 'active' : ''; ?>" href="/dashboard.php">Network</a>
    <a class="topnav-link <?php echo in_array($current_page, ['contenuti','playlist']) ? 'active' : ''; ?>" href="/contenuti.php">Contenuti</a>
    <a class="topnav-link <?php echo in_array($current_page, ['adv','schedule']) ? 'active' : ''; ?>" href="/adv.php">Scheduling</a>
    <a class="topnav-link <?php echo in_array($current_page, ['report']) ? 'active' : ''; ?>" href="/report.php">Report</a>
  </div>

  <div class="topnav-right">
    <div class="search-box">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--on-variant)">
        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
      </svg>
      <input type="text" placeholder="Cerca dispositivi..." id="global-search">
    </div>

    <!-- Theme toggle -->
    <button class="icon-btn" onclick="toggleTheme()" title="Cambia tema">
      <svg id="theme-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
      </svg>
    </button>

    <!-- Notifiche -->
    <a href="/notifiche.php" class="icon-btn" style="position:relative">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 01-3.46 0"/>
      </svg>
      <?php
      // Conta alert attivi
      $n_alert = 0;
      try {
        $db = getDB();
        $n_alert = (int)$db->prepare("SELECT COUNT(*) FROM dispositivi WHERE tenant_id = ? AND stato = 'offline' AND ultimo_ping < DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->execute([TENANT_ID]) ? 0 : 0;
        // Semplificato — in produzione query reale
      } catch(Exception $e) {}
      if ($n_alert > 0): ?>
      <div class="notif-pip"></div>
      <?php endif; ?>
    </a>

    <!-- Settings -->
    <a href="/impostazioni.php" class="icon-btn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="3"/>
        <path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/>
      </svg>
    </a>

    <!-- Avatar -->
    <a href="/profilo.php" class="avatar" title="<?php echo htmlspecialchars(UTENTE_NOME); ?>">
      <?php echo $initial; ?>
    </a>

    <!-- Hamburger mobile -->
    <button class="hamburger" onclick="toggleSidebar()">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>
  </div>
</nav>
