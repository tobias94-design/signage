<?php
$php_maj   = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
$mem_used  = round(memory_get_usage(true)/1024/1024,1);
$mem_peak  = round(memory_get_peak_usage(true)/1024/1024,1);
$uptime_f  = sys_get_temp_dir().'/signage_start';
if(!file_exists($uptime_f)){file_put_contents($uptime_f,time());}
$up_sec    = time()-(int)file_get_contents($uptime_f);
$up_str    = $up_sec<3600 ? round($up_sec/60).'m' : round($up_sec/3600,1).'h';
$ud        = __DIR__.'/../uploads';
$ud_size   = 0;
if(is_dir($ud)){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ud,FilesystemIterator::SKIP_DOTS)) as $f){$ud_size+=$f->getSize();}}
$ud_mb     = round($ud_size/1024/1024,1);
$db_kb     = '—';
try{$dp=__DIR__.'/../database.sqlite';if(file_exists($dp))$db_kb=round(filesize($dp)/1024,1).' KB';}catch(Exception $e){}
?>

</div><!-- /sg-content -->
</div><!-- /sg-body -->

<!-- ══ STATUSBAR ══════════════════════════════════════════ -->
<footer class="statusbar">
    <div class="sb-brand">PIXEL<span>BRIDGE</span></div>
    <div class="sb-sep"></div>
    <div class="sb-item"><div class="sb-dot sb-dot-ok"></div>PHP <b><?= $php_maj ?></b></div>
    <div class="sb-sep"></div>
    <div class="sb-item">RAM <b><?= $mem_used ?> MB</b><span class="sb-sub">/ <?= $mem_peak ?> pk</span></div>
    <div class="sb-sep"></div>
    <div class="sb-item">Upload <b><?= $ud_mb ?> MB</b></div>
    <div class="sb-sep"></div>
    <div class="sb-item">DB <b><?= $db_kb ?></b></div>
    <div class="sb-sep"></div>
    <div class="sb-item">Sessione <b><?= $up_str ?></b></div>
    <div class="sb-ticker">
        SISTEMA OPERATIVO &nbsp;·&nbsp; TUTTI I MODULI ATTIVI &nbsp;·&nbsp;
        <?= date('d/m/Y H:i') ?> &nbsp;·&nbsp; PIXELBRIDGE SIGNAGE MANAGER &nbsp;·&nbsp;
    </div>
</footer>

</div><!-- /sg-app -->

<script>
(function(){
    function pad(n){return String(n).padStart(2,'0');}
    function tick(){
        var el=document.getElementById('sg-clock');
        if(!el)return;
        var n=new Date();
        el.textContent=pad(n.getHours())+':'+pad(n.getMinutes())+':'+pad(n.getSeconds());
    }
    setInterval(tick,1000); tick();
})();

(function(){
    var t = 30;
    var el = document.getElementById('sg-api-countdown');
    if (!el) return;
    setInterval(function(){
        t--;
        if (t <= 0) t = 30;
        el.textContent = t + 's';
        el.style.color = t <= 5 ? 'var(--sg-green)' : 'var(--sg-orange)';
    }, 1000);
})();
</script>
</body>
</html>