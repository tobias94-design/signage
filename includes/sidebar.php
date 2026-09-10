<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$piano_label  = strtoupper(TENANT_PIANO);

function nav_item(string $href, string $label, string $page_match, string $current, string $icon_svg): string {
    $active = $page_match === $current ? 'active' : '';
    return "<a href=\"{$href}\" class=\"nav-item {$active}\">{$icon_svg}{$label}</a>";
}
?>
<aside class="sidebar" id="sidebar">

  <div class="sidebar-top">
    <div class="entity-row">
      <div class="entity-ico">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="1.8">
          <rect x="2" y="7" width="20" height="14" rx="2"/>
          <path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/>
        </svg>
      </div>
      <div>
        <div class="entity-name"><?php echo htmlspecialchars(TENANT_NOME); ?></div>
        <div class="entity-sub">Admin Panel</div>
      </div>
    </div>

    <a href="/dispositivi.php?action=pair" class="deploy-btn">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
      </svg>
      Deploy contenuti
    </a>
  </div>

  <nav class="nav">
    <?php echo nav_item('/dashboard.php', 'Dashboard', 'dashboard', $current_page,
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>');
    ?>
    <?php echo nav_item('/dispositivi.php', 'Dispositivi', 'dispositivi', $current_page,
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>');
    ?>
    <?php echo nav_item('/templates.php', 'Template layout', 'templates', $current_page,
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>');
    ?>
    <?php echo nav_item('/widgets.php', 'Catalogo Widget', 'widgets', $current_page,
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>');
    ?>
    <?php echo nav_item('/contenuti.php', 'Contenuti', 'contenuti', $current_page,
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>');
    ?>
    <?php echo nav_item('/playlist.php', 'Playlist', 'playlist', $current_page,
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6" stroke-linecap="round" stroke-width="2.5"/><line x1="3" y1="12" x2="3.01" y2="12" stroke-linecap="round" stroke-width="2.5"/><line x1="3" y1="18" x2="3.01" y2="18" stroke-linecap="round" stroke-width="2.5"/></svg>');
    ?>
    <?php echo nav_item('/adv.php', 'ADV & Scheduling', 'adv', $current_page,
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>');
    ?>
    <?php echo nav_item('/inserzionisti.php', 'Inserzionisti', 'inserzionisti', $current_page,
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>');
    ?>

    <div class="nav-sep">Configurazione</div>

    <?php echo nav_item('/club.php', 'Club & sedi', 'club', $current_page,
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>');
    ?>
    <?php echo nav_item('/utenti.php', 'Utenti', 'utenti', $current_page,
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>');
    ?>
    <?php echo nav_item('/impostazioni.php', 'Impostazioni', 'impostazioni', $current_page,
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/></svg>');
    ?>

    <?php if (isSuperAdmin()): ?>
    <div class="nav-sep">Super Admin</div>
    <?php echo nav_item('/superadmin.php', 'Gestione tenant', 'superadmin', $current_page,
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>');
    ?>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="tenant-card">
      <div class="t-name"><?php echo htmlspecialchars(TENANT_NOME); ?></div>
      <div class="t-sub"><?php echo htmlspecialchars(UTENTE_NOME); ?></div>
      <div class="t-badge">✦ Piano <?php echo $piano_label; ?></div>
    </div>
  </div>

</aside>

<!-- Mobile overlay -->
<div id="sidebar-overlay" onclick="toggleSidebar()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:80"></div>
