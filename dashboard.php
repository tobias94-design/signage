<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Dashboard';
$db = getDB();
$tid = TENANT_ID;

// ── Query metriche ───────────────────────────────────────────
$tot_disp    = (int)$db->prepare("SELECT COUNT(*) FROM dispositivi WHERE tenant_id = ?")->execute([$tid]) ? (int)$db->query("SELECT COUNT(*) FROM dispositivi WHERE tenant_id = $tid")->fetchColumn() : 0;

// Query più pulite con helper
$stmt = $db->prepare("SELECT COUNT(*) FROM dispositivi WHERE tenant_id = ?");
$stmt->execute([$tid]);
$tot_disp = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM dispositivi WHERE tenant_id = ? AND stato = 'online' AND ultimo_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
$stmt->execute([$tid]);
$online_disp = (int)$stmt->fetchColumn();

$offline_disp = $tot_disp - $online_disp;
$uptime_pct   = $tot_disp > 0 ? round($online_disp / $tot_disp * 100) : 0;

// TV totali
$stmt = $db->prepare("SELECT COALESCE(SUM(numero_tv), 0) FROM dispositivi WHERE tenant_id = ?");
$stmt->execute([$tid]);
$tot_tv = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(numero_tv), 0) FROM dispositivi WHERE tenant_id = ? AND stato = 'online' AND ultimo_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
$stmt->execute([$tid]);
$tv_online = (int)$stmt->fetchColumn();

// Contenuti
$stmt = $db->prepare("SELECT COUNT(*) FROM contenuti WHERE tenant_id = ?");
$stmt->execute([$tid]);
$tot_contenuti = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(DISTINCT c.id) FROM contenuti c JOIN playlist_items pi ON pi.contenuto_id = c.id JOIN playlist p ON p.id = pi.playlist_id WHERE c.tenant_id = ?");
$stmt->execute([$tid]);
$cont_usati = (int)$stmt->fetchColumn();
$cont_inutilizzati = $tot_contenuti - $cont_usati;
$cont_pct = $tot_contenuti > 0 ? round($cont_usati / $tot_contenuti * 100) : 0;

// Playlist
$stmt = $db->prepare("SELECT COUNT(*) FROM playlist WHERE tenant_id = ?");
$stmt->execute([$tid]);
$tot_playlist = (int)$stmt->fetchColumn();

// Dispositivi recenti con stato
$stmt = $db->prepare("
    SELECT d.id, d.nome, d.club, d.token, d.stato, d.hw_type, d.numero_tv, d.ultimo_ping,
           CASE WHEN d.ultimo_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 'online' ELSE 'offline' END AS stato_live
    FROM dispositivi d
    WHERE d.tenant_id = ?
    ORDER BY d.ultimo_ping DESC
    LIMIT 6
");
$stmt->execute([$tid]);
$dispositivi = $stmt->fetchAll();

// Club con conteggi
$stmt = $db->prepare("
    SELECT club,
           COUNT(*) AS num_player,
           COALESCE(SUM(numero_tv), 0) AS num_tv,
           SUM(CASE WHEN stato = 'online' AND ultimo_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 ELSE 0 END) AS player_online
    FROM dispositivi
    WHERE tenant_id = ?
    GROUP BY club
    ORDER BY club
");
$stmt->execute([$tid]);
$clubs = $stmt->fetchAll();

// Contenuti recenti
$stmt = $db->prepare("SELECT id, nome, tipo, file, creato_il FROM contenuti WHERE tenant_id = ? ORDER BY creato_il DESC LIMIT 4");
$stmt->execute([$tid]);
$contenuti_recenti = $stmt->fetchAll();

// Playlist attive
$stmt = $db->prepare("SELECT p.id, p.nome, COUNT(pi.id) AS num_items FROM playlist p LEFT JOIN playlist_items pi ON pi.playlist_id = p.id WHERE p.tenant_id = ? GROUP BY p.id ORDER BY p.creato_il DESC LIMIT 4");
$stmt->execute([$tid]);
$playlists = $stmt->fetchAll();

// Alert: dispositivi offline da più di 5 minuti
$stmt = $db->prepare("
    SELECT nome, club, ultimo_ping,
           TIMESTAMPDIFF(MINUTE, ultimo_ping, NOW()) AS minuti_offline
    FROM dispositivi
    WHERE tenant_id = ? AND (stato = 'offline' OR ultimo_ping < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
    ORDER BY ultimo_ping ASC
    LIMIT 5
");
$stmt->execute([$tid]);
$alerts_offline = $stmt->fetchAll();

// Contenuti in scadenza (adv con data_fine nei prossimi 3 giorni)
$stmt = $db->prepare("
    SELECT c.nome, pi.data_fine,
           DATEDIFF(pi.data_fine, CURDATE()) AS giorni_rimasti
    FROM playlist_items pi
    JOIN contenuti c ON c.id = pi.contenuto_id
    JOIN playlist p ON p.id = pi.playlist_id
    WHERE p.tenant_id = ? AND pi.data_fine IS NOT NULL AND pi.data_fine BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY pi.data_fine ASC
    LIMIT 3
");
$stmt->execute([$tid]);
$alerts_scadenza = $stmt->fetchAll();

$tot_alerts = count($alerts_offline) + count($alerts_scadenza);
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
</head>
<body>

<?php require_once __DIR__ . '/includes/topnav.php'; ?>

<div class="app">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">

    <!-- PAGE HEAD -->
    <div class="page-head">
      <div>
        <div class="page-title">Network Overview</div>
        <div class="page-sub">
          Monitoraggio in tempo reale ·
          <?php echo $tot_disp; ?> player ·
          <?php echo count($clubs); ?> <?php echo count($clubs) === 1 ? 'sede' : 'sedi'; ?>
        </div>
      </div>
      <div class="head-actions">
        <a href="/dispositivi.php" class="btn-ghost">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
          </svg>
          Tutti i dispositivi
        </a>
        <a href="/dispositivi.php?action=pair" class="btn-primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Aggiungi dispositivo
        </a>
        <div class="seg">
          <button class="seg-btn active">Live</button>
          <button class="seg-btn">Storico</button>
        </div>
      </div>
    </div>

    <!-- METRICS -->
    <div class="g12">
      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Dispositivi online</div>
        <div class="mval"><?php echo $online_disp; ?></div>
        <div class="msub">di <?php echo $tot_disp; ?> player totali</div>
        <div class="pbar"><div class="pfill" style="width:<?php echo $uptime_pct; ?>%"></div></div>
        <div class="pmeta"><span class="l">Uptime</span><span class="r"><?php echo $uptime_pct; ?>%</span></div>
      </div>

      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">TV attive</div>
        <div class="mval"><?php echo $tv_online; ?></div>
        <div class="msub">di <?php echo $tot_tv; ?> TV totali</div>
        <?php $tv_pct = $tot_tv > 0 ? round($tv_online / $tot_tv * 100) : 0; ?>
        <div class="pbar"><div class="pfill" style="width:<?php echo $tv_pct; ?>%"></div></div>
        <div class="pmeta"><span class="l">Copertura</span><span class="r"><?php echo $tv_pct; ?>%</span></div>
      </div>

      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Contenuti</div>
        <div class="mval"><?php echo $tot_contenuti; ?></div>
        <?php if ($cont_inutilizzati > 0): ?>
        <div class="msub dn"><?php echo $cont_inutilizzati; ?> non utilizzati</div>
        <?php else: ?>
        <div class="msub up">Tutti in uso</div>
        <?php endif; ?>
        <div class="pbar"><div class="pfill" style="width:<?php echo $cont_pct; ?>%"></div></div>
        <div class="pmeta"><span class="l">Utilizzo</span><span class="r"><?php echo $cont_pct; ?>%</span></div>
      </div>

      <div class="card cp" style="grid-column:span 3">
        <div class="mlabel">Playlist</div>
        <div class="mval"><?php echo $tot_playlist; ?></div>
        <div class="msub"><?php echo $tot_alerts > 0 ? $tot_alerts . ' alert attivi' : 'Tutto regolare'; ?></div>
        <div class="pbar"><div class="pfill" style="width:<?php echo min(100, $tot_playlist * 10); ?>%"></div></div>
        <div class="pmeta"><span class="l">Totali</span><span class="r"><?php echo $tot_playlist; ?></span></div>
      </div>
    </div>

    <!-- DISPOSITIVI + ALERT -->
    <div class="g12">

      <!-- Dispositivi -->
      <div class="card cp" style="grid-column:span 7">
        <div class="ch">
          <div class="ct">Dispositivi</div>
          <a href="/dispositivi.php" class="cl">Vedi tutti →</a>
        </div>

        <?php if (empty($dispositivi)): ?>
        <div class="empty">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
          <div class="empty-title">Nessun dispositivo</div>
          <div class="empty-sub">Aggiungi il primo player per iniziare</div>
        </div>
        <?php else: ?>
        <?php foreach ($dispositivi as $d):
          $is_online = $d['stato_live'] === 'online';
          $hw_label  = strtoupper($d['hw_type'] ?? 'N/D');
          $ping_ago  = $d['ultimo_ping'] ? human_time_diff($d['ultimo_ping']) : 'Mai';
        ?>
        <div class="row">
          <div class="dot <?php echo $is_online ? 'on' : 'off'; ?>"></div>
          <div style="flex:1">
            <div class="rname"><?php echo htmlspecialchars($d['nome']); ?></div>
            <div class="rsub">
              <?php echo htmlspecialchars($d['club']); ?>
              <?php if ($d['numero_tv']): ?> · <?php echo $d['numero_tv']; ?> TV<?php endif; ?>
              · <?php echo $hw_label; ?>
            </div>
          </div>
          <span class="badge <?php echo $is_online ? 'on' : 'off'; ?>">
            <?php echo $is_online ? 'Online' : 'Offline'; ?>
          </span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Alert -->
      <div class="card cp" style="grid-column:span 5">
        <div class="ch">
          <div class="ct">Alert</div>
          <?php if ($tot_alerts > 0): ?>
          <span class="badge err"><?php echo $tot_alerts; ?> attivi</span>
          <?php else: ?>
          <span class="badge on">Tutto OK</span>
          <?php endif; ?>
        </div>

        <?php foreach ($alerts_offline as $a): ?>
        <div class="alert-row">
          <div class="aico" style="background:var(--error-bg)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--error)" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
          </div>
          <div>
            <div class="atitle"><?php echo htmlspecialchars($a['nome']); ?> offline</div>
            <div class="ameta">
              <?php echo htmlspecialchars($a['club']); ?> ·
              Da <?php echo $a['minuti_offline']; ?> minuti
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <?php foreach ($alerts_scadenza as $a): ?>
        <div class="alert-row">
          <div class="aico" style="background:var(--warn-bg)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--warn)" stroke-width="2">
              <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
          </div>
          <div>
            <div class="atitle">"<?php echo htmlspecialchars($a['nome']); ?>" scade
              <?php echo $a['giorni_rimasti'] == 0 ? 'oggi' : 'tra ' . $a['giorni_rimasti'] . ' giorni'; ?>
            </div>
            <div class="ameta">Playlist · Aggiorna i contenuti</div>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if ($tot_alerts === 0): ?>
        <div class="empty" style="padding:24px">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <div class="empty-title" style="font-size:13px">Nessun alert</div>
          <div class="empty-sub">Tutti i dispositivi funzionano correttamente</div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- CLUB + CONTENUTI + PLAYLIST -->
    <div class="g12">

      <!-- Club & sedi -->
      <div class="card cp" style="grid-column:span 3">
        <div class="ch">
          <div class="ct">Club & sedi</div>
          <a href="/club.php" class="cl">Mappa →</a>
        </div>
        <?php if (empty($clubs)): ?>
        <div class="empty" style="padding:20px">
          <div class="empty-sub">Nessuna sede configurata</div>
        </div>
        <?php else: ?>
        <?php foreach ($clubs as $c): ?>
        <div class="row">
          <div style="flex:1">
            <div class="rname"><?php echo htmlspecialchars($c['club']); ?></div>
            <div class="rsub"><?php echo $c['num_player']; ?> player · <?php echo $c['num_tv']; ?> TV</div>
          </div>
          <div style="display:flex;gap:4px">
            <?php for ($i = 0; $i < $c['num_player']; $i++): ?>
            <div style="width:8px;height:8px;border-radius:50%;background:<?php echo $i < $c['player_online'] ? '#22c55e' : 'var(--outline)'; ?>"></div>
            <?php endfor; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Contenuti recenti -->
      <div class="card cp" style="grid-column:span 5">
        <div class="ch">
          <div class="ct">Contenuti recenti</div>
          <a href="/contenuti.php" class="cl">Gestisci →</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
          <?php foreach ($contenuti_recenti as $c): ?>
          <a href="/contenuti.php?id=<?php echo $c['id']; ?>" style="aspect-ratio:16/9;background:var(--surface-low);border-radius:8px;border:1px solid var(--outline-var);display:flex;align-items:center;justify-content:center;overflow:hidden;cursor:pointer;transition:all .15s;text-decoration:none" onmouseover="this.style.borderColor='var(--blue)'" onmouseout="this.style.borderColor='var(--outline-var)'">
            <?php if ($c['tipo'] === 'immagine' && $c['file']): ?>
            <img src="/uploads/<?php echo htmlspecialchars($c['file']); ?>" style="width:100%;height:100%;object-fit:cover" alt="">
            <?php else: ?>
            <div style="font-size:10px;font-weight:500;color:var(--on-variant);text-align:center;padding:6px;line-height:1.4">
              <?php echo htmlspecialchars(substr($c['nome'], 0, 20)); ?>
            </div>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
          <?php if (count($contenuti_recenti) < 4): ?>
          <a href="/contenuti.php?action=upload" style="aspect-ratio:16/9;background:var(--surface-low);border-radius:8px;border:1px dashed var(--outline-var);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;text-decoration:none;color:var(--outline)" onmouseover="this.style.borderColor='var(--blue)'" onmouseout="this.style.borderColor='var(--outline-var)'">
            <span style="font-size:22px;font-weight:300">+</span>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Playlist -->
      <div class="card cp" style="grid-column:span 4">
        <div class="ch">
          <div class="ct">Playlist</div>
          <a href="/playlist.php" class="cl">Gestisci →</a>
        </div>
        <?php if (empty($playlists)): ?>
        <div class="empty" style="padding:20px">
          <div class="empty-sub">Nessuna playlist creata</div>
        </div>
        <?php else: ?>
        <?php
        $colors = ['var(--blue)', 'var(--violet)', 'var(--success-dim)', 'var(--warn)'];
        foreach ($playlists as $i => $p):
          $col = $colors[$i % count($colors)];
        ?>
        <div class="row">
          <div style="width:6px;height:6px;border-radius:2px;background:<?php echo $col; ?>;flex-shrink:0"></div>
          <div style="flex:1">
            <div class="rname"><?php echo htmlspecialchars($p['nome']); ?></div>
            <div class="rsub"><?php echo $p['num_items']; ?> contenuti</div>
          </div>
          <span class="badge blue">Attiva</span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>

  </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php

function human_time_diff(string $datetime): string {
  $diff = time() - strtotime($datetime);
  if ($diff < 60)   return $diff . 's fa';
  if ($diff < 3600) return round($diff/60) . ' min fa';
  if ($diff < 86400) return round($diff/3600) . 'h fa';
  return round($diff/86400) . 'g fa';
}
?>
