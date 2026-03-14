<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
$db = getDB();
$tot_cont    = (int)$db->query("SELECT COUNT(*) FROM contenuti")->fetchColumn();
$tot_play    = (int)$db->query("SELECT COUNT(*) FROM playlist")->fetchColumn();
$tot_profili = (int)$db->query("SELECT COUNT(*) FROM profili")->fetchColumn();
$tot_disp    = (int)$db->query("SELECT COUNT(*) FROM dispositivi")->fetchColumn();
$online_disp = (int)$db->query("SELECT COUNT(*) FROM dispositivi WHERE ultimo_ping > datetime('now','-2 minutes')")->fetchColumn();
$offline_disp = $tot_disp - $online_disp;
$uptime_pct   = $tot_disp > 0 ? round($online_disp / $tot_disp * 100) : 0;

// TV totali — legge numero_tv direttamente da dispositivi
$tv_online = 0; $tv_offline = 0; $tv_totali = 0;
try {
    $pb_rows = $db->query("
        SELECT d.club, d.numero_tv,
               CASE WHEN d.ultimo_ping > datetime('now','-2 minutes') THEN 1 ELSE 0 END AS pb_online
        FROM dispositivi d
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pb_rows as $pb) {
        $n = (int)($pb['numero_tv'] ?? 0);
        if ($n <= 0) $n = 1; // se non configurato, conta il dispositivo stesso come 1 TV
        $tv_totali += $n;
        if ($pb['pb_online']) $tv_online += $n;
        else $tv_offline += $n;
    }
} catch (Exception $e) {}

$giorno_anno = (int)date('z') + 1;
$giorni_tot  = date('L') ? 366 : 365;
$anno_pct    = round($giorno_anno / $giorni_tot * 100);

$dispositivi = $db->query("
    SELECT d.nome, d.club, d.numero_tv, d.indirizzo, p.nome AS profilo_nome, d.ultimo_ping,
           CASE WHEN d.ultimo_ping > datetime('now','-2 minutes') THEN 'online' ELSE 'offline' END AS stato
    FROM dispositivi d LEFT JOIN profili p ON p.id = d.profilo_id
    ORDER BY stato DESC, d.nome ASC
")->fetchAll(PDO::FETCH_ASSOC);

// clubs_preview — aggrega per club
$clubs_preview = [];
foreach ($dispositivi as $d) {
    $club = !empty($d['club']) ? $d['club'] : null;
    if (!$club) continue;
    if (!isset($clubs_preview[$club])) {
        $clubs_preview[$club] = ['nome'=>$club,'online'=>0,'offline'=>0,'profilo'=>$d['profilo_nome']??'—','num_tv'=>0];
    }
    $clubs_preview[$club][$d['stato']]++;
    $clubs_preview[$club]['num_tv'] += max(1, (int)($d['numero_tv'] ?? 0));
}
$clubs_preview = array_values($clubs_preview);

$schedule = [];
try {
    $map_gg = ['Mon'=>'lun','Tue'=>'mar','Wed'=>'mer','Thu'=>'gio','Fri'=>'ven','Sat'=>'sab','Sun'=>'dom'];
    $oggi = $map_gg[date('D')] ?? 'lun';
    $schedule = $db->query("SELECT e.nome, e.ora_inizio, e.ora_fine, pl.nome AS playlist_nome FROM profili_eventi e LEFT JOIN playlist pl ON pl.id = e.playlist_id WHERE e.giorni LIKE '%$oggi%' ORDER BY e.ora_inizio LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$contenuti_recenti = [];
try { $contenuti_recenti = $db->query("SELECT nome, tipo FROM contenuti ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}

$titolo = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
<div class="wgrid">

    <!-- W1: TV attive -->
    <div class="w glass">
        <div class="wl">TV attive <a href="/dispositivi.php" class="wl-action">Club →</a></div>
        <div class="bignum"><?= $tv_online ?><sub>/<?= $tv_totali ?></sub></div>
        <div class="wsub"><?= $tv_offline ?> TV offline &middot; <?= $online_disp ?>/<?= $tot_disp ?> PixelBridge</div>
        <div class="dp <?= $tv_offline===0?'dp-up':($tv_online>0?'dp-warn':'dp-off') ?>">
            <?= $tv_offline===0?'↑ Tutte online':($tv_online>0?'⚠ '.$tv_offline.' TV offline':'✕ Tutte offline') ?>
        </div>
        <div class="wbar"><div class="wbar-fill <?= $tv_offline===0?'wbar-g':($tv_online>0?'wbar-y':'wbar-r') ?>"
            style="width:<?= $tv_totali>0?round($tv_online/$tv_totali*100):0 ?>%"></div></div>
    </div>

    <!-- W2: In onda -->
    <div class="w glass">
        <div class="wl">In onda adesso <a href="/playlist.php" class="wl-action">Vedi →</a></div>
        <div class="bignum"><?= $tot_cont ?><sub> file</sub></div>
        <div class="wsub"><?= $tot_play ?> playlist &middot; <?= $tot_profili ?> profili</div>
        <div class="dp dp-up">↑ Libreria attiva</div>
        <div class="wbar"><div class="wbar-fill wbar-o" style="width:<?= min(100,$tot_cont*10) ?>%"></div></div>
    </div>

    <!-- W3: Disponibilità -->
    <div class="w glass">
        <div class="wl">Disponibilità PixelBridge</div>
        <div class="bignum"><?= $uptime_pct ?><sub>%</sub></div>
        <div class="wsub"><?= $online_disp ?>/<?= $tot_disp ?> dispositivi online</div>
        <div class="dp <?= $uptime_pct>=80?'dp-up':($uptime_pct>=50?'dp-warn':'dp-off') ?>">
            <?= $uptime_pct>=80?'✓ Ottimo':($uptime_pct>=50?'⚠ Parziale':'✕ Critico') ?>
        </div>
        <div class="wbar"><div class="wbar-fill wbar-g" style="width:<?= $uptime_pct ?>%"></div></div>
    </div>

    <!-- W4: Stato sistema -->
    <div class="w glass <?= $offline_disp>0?'glass-o':'' ?>">
        <div class="wl">Stato sistema</div>
        <div class="bignum" <?= $offline_disp>0?'style="color:#FF9F6B"':'' ?>><?= $offline_disp ?></div>
        <div class="wsub"><?= $offline_disp===0?'Nessun problema rilevato':'PixelBridge offline' ?></div>
        <div class="dp <?= $offline_disp===0?'dp-up':'dp-warn' ?>"><?= $offline_disp===0?'✓ Tutto OK':'⚠ Verifica Club' ?></div>
        <div class="wbar"><div class="wbar-fill <?= $offline_disp===0?'wbar-g':'wbar-r' ?>"
            style="width:<?= $offline_disp===0?100:min(100,$offline_disp*20) ?>%"></div></div>
    </div>

    <!-- W5+W6: Lista monitor -->
    <div class="w glass w-2 w-r2" style="padding-bottom:0;">
        <div class="wl">Monitor registrati <a href="/dispositivi.php" class="wl-action">Gestisci →</a></div>
        <div class="slist">
        <?php if (empty($dispositivi)): ?>
            <div class="si"><div class="si-info"><div class="si-name" style="color:var(--sg-muted);">Nessun dispositivo</div></div></div>
        <?php else: foreach ($dispositivi as $d):
            $is_on = $d['stato']==='online';
            $ping = !empty($d['ultimo_ping']) ? date('H:i',strtotime($d['ultimo_ping'])) : '—';
            $num_tv = (int)($d['numero_tv'] ?? 0);
        ?>
            <div class="si">
                <div class="sdot <?= $is_on?'sd-on':'sd-off' ?>"></div>
                <div class="si-info">
                    <div class="si-name"><?= htmlspecialchars($d['nome']) ?><?= $num_tv>0?' <span style="font-size:10px;color:var(--sg-muted);">📺'.$num_tv.'</span>':'' ?></div>
                    <div class="si-meta">
                        <?= htmlspecialchars($d['club']??'—') ?>
                        <?= $d['profilo_nome']?' · '.htmlspecialchars($d['profilo_nome']):'' ?>
                        · <?= $ping ?>
                    </div>
                </div>
                <div class="stag <?= $is_on?'stag-on':'stag-off' ?>"><?= $is_on?'Online':'Offline' ?></div>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- W7: Live Preview -->
    <div class="w glass w-r2" style="padding-bottom:0;">
        <div class="wl">Preview live
            <span id="live-status-pill" style="font-size:9px;background:rgba(232,80,2,0.15);color:var(--sg-orange);padding:2px 7px;border-radius:5px;font-weight:700;border:1px solid rgba(232,80,2,0.25);">● IN ONDA</span>
        </div>
        <select class="lp-select" id="club-select" onchange="updatePreview(this.value)">
            <option value="">— Tutti i club —</option>
            <?php foreach ($clubs_preview as $c): ?>
            <option value="<?= htmlspecialchars($c['nome']) ?>">
                <?= htmlspecialchars($c['nome']) ?><?= $c['num_tv']>0?' ('.$c['num_tv'].' TV)':'' ?>
            </option>
            <?php endforeach; ?>
        </select>
        <div class="lp-screen">
            <div class="lp-badge" id="lp-badge">● LIVE</div>
            <div class="lp-canvas">
                <div class="lp-brand" id="lp-brand">ALL CLUBS</div>
                <div class="lp-bar"></div><div class="lp-bar s"></div>
            </div>
        </div>
        <div class="lp-stats">
            <div class="lp-stat"><div class="lp-stat-k">TV online</div><div class="lp-stat-v" id="lp-tv"><?= $tv_online.'/'.$tv_totali ?></div></div>
            <div class="lp-stat"><div class="lp-stat-k">Profilo</div><div class="lp-stat-v" id="lp-profilo">—</div></div>
            <div class="lp-stat"><div class="lp-stat-k">Stato</div><div class="lp-stat-v" id="lp-layout">—</div></div>
        </div>
    </div>

    <!-- W8: Anno dot grid -->
    <div class="w glass">
        <div class="wl">Anno <?= date('Y') ?> <span class="wl-action"><?= $anno_pct ?>%</span></div>
        <div class="dotgrid" id="dotgrid"></div>
        <div class="dg-num"><?= $anno_pct ?><span style="font-size:16px;font-weight:400;color:var(--sg-muted);">%</span></div>
        <div class="dg-sub"><?= $giorno_anno ?> giorni su <?= $giorni_tot ?></div>
    </div>

    <!-- W9: Schedule oggi -->
    <div class="w glass">
        <div class="wl">Oggi — <?= date('l d/m') ?> <a href="/profili.php" class="wl-action">Modifica →</a></div>
        <?php if (empty($schedule)): ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;">
            <div style="font-size:28px;opacity:0.15;">📅</div>
            <div style="font-size:12px;color:var(--sg-muted);">Nessun evento oggi</div>
            <a href="/profili.php" style="font-size:11px;color:var(--sg-orange);text-decoration:none;">+ Aggiungi evento →</a>
        </div>
        <?php else: ?>
        <div class="sched">
        <?php foreach ($schedule as $ev):
            $sh=8; $tot_min=14*60;
            $ini=strtotime($ev['ora_inizio']??'00:00'); $fin=strtotime($ev['ora_fine']??'23:59');
            $ini_m=max(0,(date('H',$ini)*60+date('i',$ini))-$sh*60);
            $fin_m=min($tot_min,(date('H',$fin)*60+date('i',$fin))-$sh*60);
            $l=round($ini_m/$tot_min*100); $w=max(5,round(($fin_m-$ini_m)/$tot_min*100));
            $name=htmlspecialchars(mb_substr($ev['nome'],0,10));
        ?>
        <div class="slane">
            <div class="sname"><?= $name ?></div>
            <div class="strack">
                <?php if($l>0): ?><div class="sblock sb-e" style="width:<?= $l ?>%"></div><?php endif; ?>
                <div class="sblock sb-o" style="width:<?= $w ?>%"><?= $name ?></div>
                <?php if($l+$w<100): ?><div class="sblock sb-e" style="width:<?= 100-$l-$w ?>%"></div><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /wgrid -->

<div class="box" style="margin-bottom:20px;">
    <h2>⚡ Azioni rapide</h2>
    <div class="azioni-rapide">
        <a href="/contenuti.php" class="btn">+ Contenuto</a>
        <a href="/playlist.php" class="btn btn-secondary">+ Playlist</a>
        <a href="/profili.php" class="btn btn-secondary">+ Profilo</a>
        <a href="/dispositivi.php" class="btn btn-secondary">+ PixelBridge</a>
        <a href="/layout.php" class="btn btn-secondary">⚙ Layout</a>
    </div>
</div>

<?php if (!empty($contenuti_recenti)): ?>
<div class="box">
    <h2>🕐 Caricati di recente</h2>
    <table>
        <thead><tr><th>Nome</th><th>Tipo</th></tr></thead>
        <tbody>
        <?php foreach ($contenuti_recenti as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['nome']) ?></td>
            <td><?php $t=strtolower($c['tipo']??''); ?>
                <span class="badge <?= strpos($t,'video')!==false?'badge-video':'badge-immagine' ?>"><?= htmlspecialchars($c['tipo']??'—') ?></span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

</div><!-- /container -->
<script>
(function(){
    var dg=document.getElementById('dotgrid'); if(!dg) return;
    var total=52, done=Math.round(52*<?= $anno_pct ?>/100);
    for(var i=0;i<total;i++){var d=document.createElement('div');d.className='dg '+(i<done?'dg-on':'dg-off');dg.appendChild(d);}
})();
var clubsData=<?= json_encode($clubs_preview) ?>;
function updatePreview(clubNome){
    var brand=document.getElementById('lp-brand'),tvEl=document.getElementById('lp-tv'),
        proEl=document.getElementById('lp-profilo'),layEl=document.getElementById('lp-layout'),
        badge=document.getElementById('lp-badge'),pill=document.getElementById('live-status-pill');
    if(!clubNome){
        brand.textContent='ALL CLUBS';
        tvEl.textContent='<?= $tv_online."/".$tv_totali ?>';
        proEl.textContent='—'; layEl.textContent='—'; badge.textContent='● LIVE';
        badge.className='lp-badge';
        pill.textContent='● IN ONDA';
        pill.style.color='var(--sg-orange)';
        pill.style.background='rgba(232,80,2,0.15)';
        pill.style.border='1px solid rgba(232,80,2,0.25)';
        return;
    }
    var club=clubsData.find(function(c){return c.nome===clubNome;}); if(!club) return;
    var isOn=club.online>0;
    brand.textContent=clubNome.substring(0,10).toUpperCase();
    tvEl.textContent=(isOn?club.num_tv:'0')+'/'+(club.num_tv||'1');
    proEl.textContent=club.profilo||'—';
    layEl.textContent=isOn?'Online':'Offline';
    badge.textContent=isOn?'● LIVE':'○ OFFLINE';
    badge.className=isOn?'lp-badge':'lp-offline-badge';
    pill.textContent=isOn?'● IN ONDA':'○ OFFLINE';
    pill.style.color=isOn?'var(--sg-orange)':'var(--sg-red)';
    pill.style.background=isOn?'rgba(232,80,2,0.15)':'rgba(255,69,58,0.10)';
    pill.style.border=isOn?'1px solid rgba(232,80,2,0.25)':'1px solid rgba(255,69,58,0.20)';
}
setTimeout(function(){window.location.reload();},60000);
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>