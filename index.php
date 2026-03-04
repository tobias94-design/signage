<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();

$num_contenuti      = $db->query('SELECT COUNT(*) FROM contenuti')->fetchColumn();
$num_playlist       = $db->query('SELECT COUNT(*) FROM playlist')->fetchColumn();
$totale_dispositivi = $db->query('SELECT COUNT(*) FROM dispositivi')->fetchColumn();

$dispositivi_online = $db->query("
    SELECT COUNT(*) FROM dispositivi
    WHERE stato = 'online'
    AND ultimo_ping > datetime('now', '-2 minutes')
")->fetchColumn();

$dispositivi = $db->query("
    SELECT d.*, p.nome as profilo_nome
    FROM dispositivi d
    LEFT JOIN profili p ON p.id = d.profilo_id
    ORDER BY d.club
")->fetchAll(PDO::FETCH_ASSOC);

$titolo = 'Dashboard';
require_once 'includes/header.php';
?>

<div class="container">

    <div class="cards">
        <div class="card">
            <div class="numero"><?php echo $num_contenuti; ?></div>
            <div class="label">Contenuti caricati</div>
        </div>
        <div class="card">
            <div class="numero"><?php echo $num_playlist; ?></div>
            <div class="label">Playlist create</div>
        </div>
        <div class="card">
            <div class="numero"><?php echo $totale_dispositivi; ?></div>
            <div class="label">Dispositivi configurati</div>
        </div>
    </div>

    <div class="grid-top">
        <div class="box">
            <h2>Stato sistema</h2>
            <div class="status-row">
                <div class="dot"></div>
                <span>Server PHP attivo</span>
            </div>
            <div class="status-row">
                <div class="dot"></div>
                <span>Database connesso</span>
            </div>
            <div class="status-row">
                <div class="dot <?php echo $dispositivi_online > 0 ? '' : 'off'; ?>"></div>
                <span>
                    Player —
                    <?php if ($totale_dispositivi == 0): ?>
                        nessun dispositivo configurato
                    <?php elseif ($dispositivi_online > 0): ?>
                        <?php echo $dispositivi_online; ?>/<?php echo $totale_dispositivi; ?> dispositivi online ✅
                    <?php else: ?>
                        nessun dispositivo online ❌
                    <?php endif; ?>
                </span>
            </div>
            <div class="status-row">
                <div class="dot warning"></div>
                <span style="color:#aaa;">Auto-refresh tra <span id="countdown">30</span>s</span>
            </div>
        </div>

        <div class="box">
            <h2>Azioni rapide</h2>
            <div class="azioni-rapide">
                <a href="/contenuti.php" class="btn">+ Contenuto</a>
                <a href="/playlist.php" class="btn btn-secondary">+ Playlist</a>
                <a href="/profili.php" class="btn btn-secondary">+ Profilo</a>
                <a href="/dispositivi.php" class="btn btn-secondary">+ Dispositivo</a>
                <a href="/layout.php" class="btn btn-secondary">🎨 Banner</a>
                <a href="/player/" class="btn btn-secondary" target="_blank">▶ Player</a>
            </div>
        </div>
    </div>

    <div class="box">
        <h2>Stato dispositivi</h2>
        <?php if (empty($dispositivi)): ?>
            <div class="vuoto">Nessun dispositivo ancora. <a href="/dispositivi.php" style="color:#e94560;">Aggiungine uno</a></div>
        <?php else: ?>
        <table>
            <tr>
                <th>Stato</th>
                <th>Nome</th>
                <th>Club</th>
                <th>Profilo</th>
                <th>Ultimo ping</th>
                <th>Azioni</th>
            </tr>
            <?php foreach ($dispositivi as $d):
                $online = $d['stato'] === 'online' &&
                          $d['ultimo_ping'] &&
                          strtotime($d['ultimo_ping']) > strtotime('-2 minutes');
            ?>
            <tr>
                <td><span class="badge <?php echo $online ? 'badge-online' : 'badge-offline'; ?>">
                    <?php echo $online ? '● ONLINE' : '● OFFLINE'; ?>
                </span></td>
                <td><?php echo htmlspecialchars($d['nome']); ?></td>
                <td>🏋️ <?php echo htmlspecialchars($d['club']); ?></td>
                <td>
                    <?php if ($d['profilo_nome']): ?>
                        <span class="badge badge-profilo"><?php echo htmlspecialchars($d['profilo_nome']); ?></span>
                    <?php else: ?>
                        <span style="color:#555;">Nessuno</span>
                    <?php endif; ?>
                </td>
                <td style="color:#aaa; font-size:13px;">
                    <?php echo $d['ultimo_ping'] ? date('d/m H:i:s', strtotime($d['ultimo_ping'])) : '—'; ?>
                </td>
                <td>
                    <a href="/player/?token=<?php echo $d['token']; ?>"
                       target="_blank" class="btn btn-sm">▶ Apri</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>

</div>

<style>
.grid-top { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
.cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
.card { background: #16213e; border-radius: 10px; padding: 24px; border-left: 4px solid #e94560; }
.card .numero { font-size: 42px; font-weight: bold; color: #e94560; }
.card .label { font-size: 14px; color: #aaa; margin-top: 6px; }
.status-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #0f3460; font-size: 14px; }
.status-row:last-child { border-bottom: none; }
.azioni-rapide { display: flex; gap: 12px; flex-wrap: wrap; }
</style>

<script>
setTimeout(() => location.reload(), 30000);
function aggiornaContatore() {
    const el = document.getElementById('countdown');
    if (el && parseInt(el.textContent) > 0) el.textContent = parseInt(el.textContent) - 1;
}
setInterval(aggiornaContatore, 1000);
</script>

<?php require_once 'includes/footer.php'; ?>