<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();

$msg = '';

// ── MIGRATIONS ────────────────────────────────────────────────
try { $db->exec("ALTER TABLE profili ADD COLUMN layout_tipo TEXT DEFAULT 'solo_banner'"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN layout_tipo TEXT DEFAULT 'solo_banner'"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE sidebar_slides ADD COLUMN sfondo_preset TEXT DEFAULT ''"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE sidebar_slides ADD COLUMN dispositivo_token TEXT DEFAULT ''"); } catch(Exception $e) {}

// ── AJAX: salva layout tipo dispositivo ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salva_layout_tipo'])) {
    $token = $_POST['token'] ?? '';
    $tipo  = $_POST['layout_tipo'] ?? 'solo_banner';
    if ($token) $db->prepare("UPDATE dispositivi SET layout_tipo=? WHERE token=?")->execute([$tipo, $token]);
    echo 'ok'; exit;
}

// ── UPLOAD SFONDO SLIDE ───────────────────────────────────────
function uploadSfondo($file) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) return '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) return '';
    $nome = 'sidebar_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $nome)) return $nome;
    return '';
}

// ── SALVA BANNER ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salva_banner'])) {
    $pid   = (int)($_POST['profilo_id_banner'] ?? 0);
    $logo  = $_POST['logo_attuale'] ?? '';
    if (!empty($_FILES['logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','svg','webp'])) {
            $nome = 'logo_global.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__.'/assets/img/'.$nome)) $logo = $nome;
        }
    }
    if (isset($_POST['rimuovi_logo'])) $logo = '';
    if ($pid)
        $db->prepare('UPDATE profili SET banner_colore=?,banner_testo_colore=?,banner_posizione=?,banner_altezza=?,logo=? WHERE id=?')
           ->execute([$_POST['banner_colore']??'#000',$_POST['banner_testo_colore']??'#fff',$_POST['banner_posizione']??'bottom',(int)($_POST['banner_altezza']??80),$logo,$pid]);
    $msg = 'ok|Banner salvato!';
}

// ── AZIONI SLIDE ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione'])) {
    $dev_token = $_POST['dev_token'] ?? '';

    if ($_POST['azione'] === 'aggiungi_slide') {
        $tipo     = $_POST['tipo'] ?? 'info';
        $titolo   = trim($_POST['titolo'] ?? '');
        $durata   = max(3, (int)($_POST['durata'] ?? 10));
        $preset   = $_POST['sfondo_preset'] ?? '';
        $sfondo   = uploadSfondo($_FILES['sfondo'] ?? null);
        $contenuto = [];
        switch ($tipo) {
            case 'countdown': $contenuto = ['data_target'=>$_POST['ct_data_target']??'','messaggio_post'=>$_POST['ct_messaggio_post']??'']; break;
            case 'meteo':     $contenuto = ['citta'=>trim($_POST['mt_citta']??''),'lat'=>trim($_POST['mt_lat']??''),'lon'=>trim($_POST['mt_lon']??'')]; break;
            case 'info':      $contenuto = ['testo'=>trim($_POST['info_testo']??''),'icona'=>trim($_POST['info_icona']??'ℹ️')]; break;
        }
        $ordine = (int)$db->query("SELECT COUNT(*) FROM sidebar_slides WHERE dispositivo_token=". $db->quote($dev_token))->fetchColumn();
        $db->prepare('INSERT INTO sidebar_slides (dispositivo_token,tipo,titolo,contenuto,durata,ordine,sfondo,sfondo_preset,colore_sfondo,colore_testo,attivo) VALUES (?,?,?,?,?,?,?,?,?,?,1)')
           ->execute([$dev_token,$tipo,$titolo,json_encode($contenuto),$durata,$ordine,$sfondo,$preset,$_POST['colore_sfondo']??'#111',$_POST['colore_testo']??'#fff']);
        $msg = 'ok|Slide aggiunta!';
    }
    if ($_POST['azione'] === 'toggle_attivo') {
        $db->prepare('UPDATE sidebar_slides SET attivo=? WHERE id=?')->execute([(int)$_POST['attivo'],(int)$_POST['id']]);
        echo 'ok'; exit;
    }
    if ($_POST['azione'] === 'riordina') {
        foreach (json_decode($_POST['ordine'],true) as $pos=>$id)
            $db->prepare('UPDATE sidebar_slides SET ordine=? WHERE id=?')->execute([$pos,(int)$id]);
        echo 'ok'; exit;
    }
}

if (isset($_GET['elimina_slide'])) {
    $id  = (int)$_GET['elimina_slide'];
    $tok = $_GET['dev'] ?? '';
    $row = $db->query("SELECT sfondo FROM sidebar_slides WHERE id=$id")->fetch();
    if ($row && $row['sfondo']) @unlink(__DIR__.'/uploads/'.$row['sfondo']);
    $db->exec("DELETE FROM sidebar_slides WHERE id=$id");
    header("Location: /layout.php?dev=".urlencode($tok)."&tab=slides"); exit;
}

// ── DATI ──────────────────────────────────────────────────────
$dispositivi = $db->query("
    SELECT d.token, d.nome, d.club, d.layout_tipo,
           p.nome AS profilo_nome, p.id AS profilo_id,
           p.banner_colore, p.banner_testo_colore, p.banner_posizione, p.banner_altezza, p.logo,
           CASE WHEN d.ultimo_ping > datetime('now','-2 minutes') THEN 'online' ELSE 'offline' END AS stato
    FROM dispositivi d
    LEFT JOIN profili p ON p.id = d.profilo_id
    ORDER BY d.club, d.nome
")->fetchAll(PDO::FETCH_ASSOC);

$sel_token = $_GET['dev'] ?? ($dispositivi[0]['token'] ?? '');
$sel_dev   = null;
foreach ($dispositivi as $d) if ($d['token'] === $sel_token) { $sel_dev = $d; break; }
if (!$sel_dev && !empty($dispositivi)) { $sel_dev = $dispositivi[0]; $sel_token = $sel_dev['token']; }

$tab = $_GET['tab'] ?? 'banner';
$layout_attivo = $sel_dev['layout_tipo'] ?? 'solo_banner';

$slides = [];
if ($sel_token) {
    $slides = $db->query("SELECT * FROM sidebar_slides WHERE dispositivo_token=".$db->quote($sel_token)." ORDER BY ordine")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($slides) && $sel_dev && $sel_dev['profilo_id']) {
        $old = $db->query("SELECT * FROM sidebar_slides WHERE profilo_id={$sel_dev['profilo_id']} AND (dispositivo_token='' OR dispositivo_token IS NULL) ORDER BY ordine")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($old)) $slides = $old;
    }
}

$tipi = [
    'corsi'     => ['label'=>'📋 Corsi del giorno','colore'=>'#e94560'],
    'countdown' => ['label'=>'⏳ Countdown evento', 'colore'=>'#f59e0b'],
    'meteo'     => ['label'=>'🌤️ Meteo',            'colore'=>'#3b82f6'],
    'info'      => ['label'=>'ℹ️ Info / Avviso',    'colore'=>'#10b981'],
];
$presets = [
    ''         => ['label'=>'Nessuno (usa colore)',    'css'=>''],
    'dark_red' => ['label'=>'🔴 Gradient scuro rosso', 'css'=>'background:linear-gradient(135deg,#000 0%,#1a0000 40%,#8b0000 100%)'],
    'midnight' => ['label'=>'🔵 Midnight blue',         'css'=>'background:linear-gradient(135deg,#0a0a1a 0%,#0f3460 100%)'],
    'purple'   => ['label'=>'🟣 Deep purple',           'css'=>'background:linear-gradient(135deg,#1a0030 0%,#4a0080 100%)'],
    'forest'   => ['label'=>'🟢 Forest dark',           'css'=>'background:linear-gradient(135deg,#001a0a 0%,#004d20 100%)'],
    'gold'     => ['label'=>'🟡 Dark gold',             'css'=>'background:linear-gradient(135deg,#1a1200 0%,#4d3800 100%)'],
    'carbon'   => ['label'=>'⚫ Carbon',                'css'=>'background:linear-gradient(135deg,#111 0%,#2a2a2a 100%)'],
];

$titolo = 'Layout';
require_once 'includes/header.php';
?>

<div class="container">

<?php if ($msg): [$tm,$txt] = explode('|',$msg,2); ?>
<div class="messaggio <?= $tm ?>"><?= htmlspecialchars($txt) ?></div>
<?php endif; ?>

<div class="box" style="margin-bottom:20px;">

    <!-- ── SELECTOR CLUB DROPDOWN ── -->
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
        <span style="font-size:11px;font-weight:700;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;flex-shrink:0;">Club:</span>
        <select onchange="window.location.href='/layout.php?dev='+encodeURIComponent(this.value)+'&tab=<?= $tab ?>'"
                style="background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.10);border-radius:10px;
                       color:var(--sg-white);padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;min-width:200px;">
            <?php foreach ($dispositivi as $d): ?>
            <option value="<?= htmlspecialchars($d['token']) ?>" <?= $d['token']===$sel_token?'selected':'' ?>>
                <?= $d['stato']==='online' ? '● ' : '○ ' ?><?= htmlspecialchars($d['club'] ?: $d['nome']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($sel_dev): ?>

    <!-- Info club selezionato -->
    <div style="display:flex;align-items:center;gap:14px;padding:14px 16px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:14px;margin-bottom:20px;">
        <div class="<?= $sel_dev['stato']==='online'?'sdot sd-on':'sdot sd-off' ?>" style="flex-shrink:0;width:12px;height:12px;"></div>
        <div style="flex:1;">
            <div style="font-size:15px;font-weight:800;color:var(--sg-white);"><?= htmlspecialchars($sel_dev['club'] ?: $sel_dev['nome']) ?></div>
            <div style="font-size:11px;color:var(--sg-muted);">
                <?= htmlspecialchars($sel_dev['nome']) ?>
                <?= $sel_dev['profilo_nome'] ? ' · Profilo: '.htmlspecialchars($sel_dev['profilo_nome']) : '' ?>
                · <?= $sel_dev['stato'] === 'online' ? '<span style="color:var(--sg-green);">Online</span>' : '<span style="color:var(--sg-red);">Offline</span>' ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:11px;color:var(--sg-muted);font-weight:600;">Template:</span>
            <div class="layout-pill-group" data-token="<?= htmlspecialchars($sel_token) ?>">
                <button class="layout-pill <?= $layout_attivo==='solo_banner'?'active':'' ?>"
                        onclick="setLayout(this,'solo_banner','<?= htmlspecialchars($sel_token) ?>')">
                    📺 Solo Banner
                </button>
                <button class="layout-pill <?= $layout_attivo==='banner_sidebar'?'active':'' ?>"
                        onclick="setLayout(this,'banner_sidebar','<?= htmlspecialchars($sel_token) ?>')">
                    📺+📋 Con Sidebar
                </button>
            </div>
            <span class="saved-badge" style="font-size:10px;color:var(--sg-green);opacity:0;transition:opacity 0.3s;white-space:nowrap;">✓ Salvato</span>
        </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:4px;margin-bottom:20px;">
        <a href="/layout.php?dev=<?= urlencode($sel_token) ?>&tab=banner"
           class="btn <?= $tab==='banner'?'':'btn-secondary' ?> btn-sm">🎨 Banner</a>
        <a href="/layout.php?dev=<?= urlencode($sel_token) ?>&tab=slides"
           class="btn <?= $tab==='slides'?'':'btn-secondary' ?> btn-sm"
           style="<?= $layout_attivo!=='banner_sidebar'?'opacity:0.4;pointer-events:none;':'' ?>"
           title="<?= $layout_attivo!=='banner_sidebar'?'Seleziona \'Con Sidebar\' per abilitare':'' ?>">
            📱 Slide Sidebar
            <?php if (!empty($slides)): ?>
            <span style="background:var(--sg-orange);color:#fff;font-size:9px;padding:1px 5px;border-radius:8px;margin-left:4px;"><?= count($slides) ?></span>
            <?php endif; ?>
        </a>
    </div>

    <?php if ($tab === 'banner'): ?>
    <!-- ── BANNER ── -->
    <div style="display:grid;grid-template-columns:300px 1fr;gap:16px;align-items:start;">
        <div>
            <?php if (!$sel_dev['profilo_id']): ?>
            <div style="padding:12px 16px;background:rgba(255,214,10,0.07);border:1px solid rgba(255,214,10,0.15);border-radius:10px;font-size:12px;color:var(--sg-yellow);margin-bottom:16px;">
                ⚠ Nessun profilo assegnato a questo dispositivo. Assegna un profilo in <a href="/club.php" style="color:var(--sg-orange);">Club</a>.
            </div>
            <?php else: ?>
            <form method="POST" enctype="multipart/form-data" id="bannerForm">
                <input type="hidden" name="salva_banner" value="1">
                <input type="hidden" name="profilo_id_banner" value="<?= $sel_dev['profilo_id'] ?>">
                <input type="hidden" name="logo_attuale" id="logoAttuale" value="<?= htmlspecialchars($sel_dev['logo']??'') ?>">
                <label>Posizione Banner</label>
                <select name="banner_posizione" id="inp_posizione">
                    <option value="bottom" <?= ($sel_dev['banner_posizione']??'bottom')==='bottom'?'selected':'' ?>>Basso</option>
                    <option value="top"    <?= ($sel_dev['banner_posizione']??'')==='top'?'selected':'' ?>>Alto</option>
                </select>
                <label>Altezza Banner (px)</label>
                <input type="number" name="banner_altezza" id="inp_altezza" value="<?= (int)($sel_dev['banner_altezza']??80) ?>" min="40" max="200">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div><label>Colore Sfondo</label><input type="color" name="banner_colore" id="inp_colore" value="<?= htmlspecialchars($sel_dev['banner_colore']??'#000000') ?>" style="width:100%;height:44px;margin-bottom:12px;"></div>
                    <div><label>Colore Testo</label><input type="color" name="banner_testo_colore" id="inp_testo_colore" value="<?= htmlspecialchars($sel_dev['banner_testo_colore']??'#ffffff') ?>" style="width:100%;height:44px;margin-bottom:12px;"></div>
                </div>
                <label>Logo <span style="font-size:10px;color:var(--sg-muted);">400×120px consigliato</span></label>
                <input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg,image/svg+xml,image/webp" style="margin-bottom:10px;">
                <?php if (!empty($sel_dev['logo'])): ?>
                <div id="logoCurrentWrap" style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:8px 12px;margin-bottom:14px;">
                    <img id="logoCurrentImg" src="assets/img/<?= htmlspecialchars($sel_dev['logo']) ?>" style="height:28px;object-fit:contain;max-width:90px;">
                    <span style="flex:1;font-size:11px;color:var(--sg-muted);"><?= htmlspecialchars($sel_dev['logo']) ?></span>
                    <button type="button" id="btnRimuoviLogo" class="btn btn-danger btn-sm">✕</button>
                </div>
                <?php else: ?>
                <div id="logoCurrentWrap" style="display:none;"></div>
                <?php endif; ?>
                <button type="submit" class="btn" style="width:100%;">💾 Salva Banner</button>
            </form>
            <?php endif; ?>
        </div>
        <div>
            <div style="font-size:11px;font-weight:700;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Anteprima</div>
            <div id="previewScreen" style="position:relative;width:100%;aspect-ratio:16/9;background:rgba(255,255,255,0.03);border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,0.08);">
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:rgba(255,255,255,0.06);font-size:13px;">📺 Segnale TV</div>
                <?php if ($layout_attivo === 'banner_sidebar'): ?>
                <div style="position:absolute;top:0;right:0;bottom:0;width:20%;background:rgba(0,0,0,0.5);border-left:1px solid rgba(255,255,255,0.05);display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <div style="font-size:8px;color:rgba(255,255,255,0.2);letter-spacing:2px;">SIDEBAR</div>
                </div>
                <?php endif; ?>
                <div id="previewBanner" style="position:absolute;left:0;right:0;display:flex;align-items:center;overflow:hidden;">
                    <img id="previewLogoImg" src="<?= !empty($sel_dev['logo'])?'assets/img/'.htmlspecialchars($sel_dev['logo']):'' ?>" style="object-fit:contain;flex-shrink:0;display:<?= !empty($sel_dev['logo'])?'block':'none' ?>">
                    <div style="width:1px;background:rgba(255,255,255,0.2);align-self:stretch;margin:5px 0;flex-shrink:0;"></div>
                    <div id="previewData" style="flex:1;text-align:center;font-weight:500;letter-spacing:1px;white-space:nowrap;overflow:hidden;">
                        <?php $gg=['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato']; $mm=['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre']; echo $gg[date('w')].' '.date('j').' '.$mm[date('n')-1].' '.date('Y'); ?>
                    </div>
                    <div style="width:1px;background:rgba(255,255,255,0.2);align-self:stretch;margin:5px 0;flex-shrink:0;"></div>
                    <div id="previewOra" style="font-weight:bold;flex-shrink:0;font-variant-numeric:tabular-nums;white-space:nowrap;">--:--:--</div>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ── SLIDE SIDEBAR ── -->
    <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start;">
        <div>
            <div style="font-size:11px;font-weight:700;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">
                Nuova slide per <span style="color:var(--sg-orange);"><?= htmlspecialchars($sel_dev['club']?:$sel_dev['nome']) ?></span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="azione" value="aggiungi_slide">
                <input type="hidden" name="dev_token" value="<?= htmlspecialchars($sel_token) ?>">
                <label>Tipo</label>
                <select name="tipo" id="tipoSel" onchange="aggiornaCampi()">
                    <?php foreach ($tipi as $k=>$t): ?>
                    <option value="<?= $k ?>"><?= $t['label'] ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Titolo</label>
                <input type="text" name="titolo" placeholder="Es. Oggi in palestra...">
                <label>Durata (sec)</label>
                <input type="number" name="durata" value="10" min="3" max="120">
                <div id="campi-countdown" style="display:none;">
                    <label>Data e ora evento</label>
                    <input type="datetime-local" name="ct_data_target">
                    <label>Messaggio post-evento</label>
                    <input type="text" name="ct_messaggio_post" placeholder="Evento in corso!">
                </div>
                <div id="campi-meteo" style="display:none;">
                    <label>Città</label>
                    <input type="text" name="mt_citta" placeholder="Es. Verona">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <div><label>Lat</label><input type="text" name="mt_lat" placeholder="45.43"></div>
                        <div><label>Lon</label><input type="text" name="mt_lon" placeholder="10.99"></div>
                    </div>
                </div>
                <div id="campi-info" style="display:none;">
                    <label>Icona emoji</label>
                    <input type="text" name="info_icona" value="ℹ️" style="width:70px;">
                    <label>Testo</label>
                    <textarea name="info_testo" rows="3" style="width:100%;padding:10px;background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.10);border-radius:10px;color:var(--sg-white);font-size:13px;resize:vertical;"></textarea>
                </div>
                <div style="border-top:1px solid rgba(255,255,255,0.06);margin-top:12px;padding-top:12px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <div><label>Sfondo</label><input type="color" name="colore_sfondo" value="#111111" style="width:100%;height:38px;cursor:pointer;"></div>
                        <div><label>Testo</label><input type="color" name="colore_testo" value="#ffffff" style="width:100%;height:38px;cursor:pointer;"></div>
                    </div>
                    <label style="margin-top:8px;">Preset sfondo</label>
                    <select name="sfondo_preset" id="sfondoPreset">
                        <?php foreach ($presets as $k=>$p): ?>
                        <option value="<?= $k ?>"><?= $p['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="presetPreview" style="height:32px;border-radius:8px;margin-top:6px;margin-bottom:10px;background:rgba(255,255,255,0.04);"></div>
                    <label>Immagine sfondo <span style="color:var(--sg-muted);font-size:10px;">380×1080px</span></label>
                    <input type="file" name="sfondo" accept="image/*">
                </div>
                <button type="submit" class="btn" style="width:100%;margin-top:12px;">+ Aggiungi Slide</button>
            </form>
        </div>

        <div>
            <div style="font-size:11px;font-weight:700;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">
                Slide di questo club (<?= count($slides) ?>) <span style="font-weight:400;color:var(--sg-muted);font-size:10px;text-transform:none;letter-spacing:0;">— ⠿ trascina per riordinare</span>
            </div>
            <?php if (empty($slides)): ?>
            <div class="vuoto">Nessuna slide. Aggiungine una dal form a sinistra.</div>
            <?php else: ?>
            <div id="lista-slides">
            <?php foreach ($slides as $sl):
                $cfg = json_decode($sl['contenuto'],true) ?: [];
                $ti  = $tipi[$sl['tipo']] ?? ['label'=>$sl['tipo'],'colore'=>'#aaa'];
                $hasPreset = !empty($sl['sfondo_preset']) && isset($presets[$sl['sfondo_preset']]) && $presets[$sl['sfondo_preset']]['css'];
                $hasSfondo = !empty($sl['sfondo']);
                $bgCss = $hasSfondo ? "background-image:url('/uploads/{$sl['sfondo']}');background-size:cover;" : ($hasPreset ? $presets[$sl['sfondo_preset']]['css'] : "background:{$sl['colore_sfondo']};");
            ?>
            <div data-id="<?= $sl['id'] ?>" style="display:flex;align-items:stretch;border-radius:12px;margin-bottom:8px;overflow:hidden;border:1px solid rgba(255,255,255,0.08);opacity:<?= $sl['attivo']?'1':'0.4' ?>">
                <div style="width:55px;flex-shrink:0;position:relative;<?= $bgCss ?>">
                    <div style="position:absolute;bottom:4px;left:0;right:0;text-align:center;font-size:10px;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,0.9);font-weight:bold;"><?= $sl['durata'] ?>s</div>
                </div>
                <div style="flex:1;padding:10px 14px;background:rgba(255,255,255,0.03);display:flex;flex-direction:column;justify-content:center;gap:4px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span class="drag-handle" style="color:var(--sg-muted);cursor:grab;font-size:18px;user-select:none;">⠿</span>
                        <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;background:<?= $ti['colore'] ?>22;color:<?= $ti['colore'] ?>;"><?= $ti['label'] ?></span>
                        <?php if ($sl['titolo']): ?><span style="font-size:13px;font-weight:600;color:var(--sg-white);"><?= htmlspecialchars($sl['titolo']) ?></span><?php endif; ?>
                    </div>
                    <div style="font-size:11px;color:var(--sg-muted);padding-left:26px;">
                        <?php if ($sl['tipo']==='countdown'&&!empty($cfg['data_target'])): ?>📅 <?= date('d/m/Y H:i',strtotime($cfg['data_target']));
                        elseif ($sl['tipo']==='meteo'&&!empty($cfg['citta'])): ?>📍 <?= htmlspecialchars($cfg['citta']);
                        elseif ($sl['tipo']==='info'&&!empty($cfg['testo'])): ?><?= htmlspecialchars($cfg['icona']??'') ?> <?= htmlspecialchars(mb_substr($cfg['testo'],0,50));
                        elseif ($sl['tipo']==='corsi'): ?>Corsi dal Google Sheet<?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;padding:8px;gap:4px;align-items:center;justify-content:center;background:rgba(0,0,0,0.1);">
                    <button onclick="toggleAttivo(<?= $sl['id'] ?>,<?= $sl['attivo']?0:1 ?>,'<?= urlencode($sel_token) ?>')" class="btn btn-sm" style="font-size:10px;padding:3px 8px;background:<?= $sl['attivo']?'rgba(48,209,88,0.15)':'rgba(255,255,255,0.04)' ?>;color:<?= $sl['attivo']?'var(--sg-green)':'var(--sg-muted)' ?>;border:1px solid <?= $sl['attivo']?'rgba(48,209,88,0.2)':'rgba(255,255,255,0.08)' ?>;"><?= $sl['attivo']?'● ON':'○ OFF' ?></button>
                    <a href="/layout.php?elimina_slide=<?= $sl['id'] ?>&dev=<?= urlencode($sel_token) ?>&tab=slides" class="btn btn-danger btn-sm" onclick="return confirm('Eliminare?')" style="font-size:10px;padding:3px 8px;">✕</a>
                </div>
            </div>
            <?php endforeach; ?>
            </div>

            <?php $slidesAttive = array_filter($slides, fn($s)=>$s['attivo']); ?>
            <?php if (!empty($slidesAttive)): ?>
            <div style="margin-top:16px;">
                <div style="font-size:11px;color:var(--sg-muted);margin-bottom:8px;">👁 Anteprima carousel</div>
                <div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:8px;">
                <?php foreach ($slidesAttive as $sl):
                    $cfg=json_decode($sl['contenuto'],true)?:[];
                    $ti=$tipi[$sl['tipo']]??['colore'=>'#aaa'];
                    $hasSfondo=!empty($sl['sfondo']);
                    $hasPreset=!empty($sl['sfondo_preset'])&&isset($presets[$sl['sfondo_preset']])&&$presets[$sl['sfondo_preset']]['css'];
                    $bgCss=$hasSfondo?"background-image:url('/uploads/{$sl['sfondo']}');background-size:cover;background-position:center;":($hasPreset?$presets[$sl['sfondo_preset']]['css']:"background:{$sl['colore_sfondo']};");
                ?>
                <div style="flex-shrink:0;width:80px;height:130px;border-radius:10px;overflow:hidden;position:relative;border:1px solid rgba(255,255,255,0.08);<?= $bgCss ?>color:<?= $sl['colore_testo'] ?>;">
                    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.4);"></div>
                    <div style="position:relative;z-index:1;padding:7px;height:100%;display:flex;flex-direction:column;justify-content:space-between;">
                        <div style="font-size:7px;opacity:0.7;text-transform:uppercase;"><?= $ti['label']??'' ?></div>
                        <div>
                            <?php if ($sl['tipo']==='countdown'): ?><div style="font-size:14px;font-weight:900;">00:00</div>
                            <?php elseif ($sl['tipo']==='meteo'): ?><div style="font-size:20px;">⛅</div>
                            <?php elseif ($sl['tipo']==='info'): ?><div style="font-size:14px;"><?= $cfg['icona']??'ℹ️' ?></div>
                            <?php elseif ($sl['tipo']==='corsi'): ?><div style="font-size:7px;line-height:1.6;opacity:0.8;">09:00 Yoga<br>10:30 Pilates</div>
                            <?php endif; ?>
                            <?php if ($sl['titolo']): ?><div style="font-size:7px;font-weight:bold;margin-top:3px;"><?= htmlspecialchars($sl['titolo']) ?></div><?php endif; ?>
                        </div>
                        <div style="font-size:7px;opacity:0.5;text-align:right;"><?= $sl['durata'] ?>s</div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; // tab ?>

    <?php endif; // sel_dev ?>
</div>

<!-- ══ RIEPILOGO TUTTI I DISPOSITIVI ══════════════════════════ -->
<div class="box">
    <div class="wl" style="margin-bottom:14px;">
        Riepilogo layout tutti i club
        <span style="font-size:11px;color:var(--sg-muted);font-weight:400;">— click sulle pill per cambiare rapidamente</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;">
    <?php foreach ($dispositivi as $dev):
        $lt = $dev['layout_tipo'] ?? 'solo_banner';
        $is_on = $dev['stato'] === 'online';
        $n_slides = (int)$db->query("SELECT COUNT(*) FROM sidebar_slides WHERE dispositivo_token=".$db->quote($dev['token']))->fetchColumn();
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:8px 14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:10px;<?= $dev['token']===$sel_token?'border-color:rgba(232,80,2,0.20);background:rgba(232,80,2,0.04);':'' ?>">
        <div style="width:7px;height:7px;border-radius:50%;background:<?= $is_on?'var(--sg-green)':'var(--sg-red)' ?>;flex-shrink:0;"></div>
        <div style="flex:1;min-width:0;">
            <span style="font-size:13px;font-weight:600;color:var(--sg-white);"><?= htmlspecialchars($dev['club']?:$dev['nome']) ?></span>
            <?php if ($n_slides > 0): ?><span style="font-size:10px;color:var(--sg-muted);margin-left:8px;"><?= $n_slides ?> slide</span><?php endif; ?>
        </div>
        <div class="layout-pill-group" data-token="<?= htmlspecialchars($dev['token']) ?>">
            <button class="layout-pill-sm <?= $lt==='solo_banner'?'active':'' ?>"
                    onclick="setLayout(this,'solo_banner','<?= htmlspecialchars($dev['token']) ?>')">📺 Solo Banner</button>
            <button class="layout-pill-sm <?= $lt==='banner_sidebar'?'active':'' ?>"
                    onclick="setLayout(this,'banner_sidebar','<?= htmlspecialchars($dev['token']) ?>')">📺+📋 Sidebar</button>
        </div>
        <a href="/layout.php?dev=<?= urlencode($dev['token']) ?>&tab=<?= $lt==='banner_sidebar'?'slides':'banner' ?>" style="font-size:11px;color:var(--sg-orange);text-decoration:none;white-space:nowrap;">Configura →</a>
    </div>
    <?php endforeach; ?>
    </div>
</div>

</div>

<style>
.layout-pill-group { display:flex;gap:5px; }
.layout-pill {
    padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;
    border:1px solid rgba(255,255,255,0.10);background:rgba(255,255,255,0.04);
    color:var(--sg-muted);cursor:pointer;transition:all 0.15s;
}
.layout-pill:hover { border-color:rgba(232,80,2,0.30);color:var(--sg-white); }
.layout-pill.active { background:rgba(232,80,2,0.15);border-color:rgba(232,80,2,0.40);color:var(--sg-orange); }
.layout-pill-sm {
    padding:4px 10px;border-radius:14px;font-size:11px;font-weight:600;
    border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);
    color:var(--sg-muted);cursor:pointer;transition:all 0.15s;
}
.layout-pill-sm:hover { border-color:rgba(232,80,2,0.25);color:var(--sg-white); }
.layout-pill-sm.active { background:rgba(232,80,2,0.12);border-color:rgba(232,80,2,0.30);color:var(--sg-orange); }
.sortable-ghost { opacity:0.3; }
.drag-handle:hover { color:var(--sg-orange) !important; }
input[type=datetime-local] {
    width:100%;padding:10px 12px;background:rgba(255,255,255,0.055)!important;
    border:1px solid rgba(255,255,255,0.10)!important;border-radius:10px;
    color:var(--sg-white)!important;font-size:14px;margin-bottom:12px;
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
function setLayout(btn, tipo, token) {
    document.querySelectorAll('.layout-pill-group[data-token="'+token+'"]').forEach(function(group){
        group.querySelectorAll('.layout-pill, .layout-pill-sm').forEach(function(b){
            b.classList.remove('active');
            if (b.textContent.includes('Sidebar') && tipo==='banner_sidebar') b.classList.add('active');
            if (!b.textContent.includes('Sidebar') && tipo==='solo_banner') b.classList.add('active');
        });
    });
    const badge = document.querySelector('.saved-badge');
    const fd = new FormData();
    fd.append('salva_layout_tipo','1');
    fd.append('token',token);
    fd.append('layout_tipo',tipo);
    fetch('/layout.php',{method:'POST',body:fd}).then(r=>r.text()).then(t=>{
        if(t==='ok'&&badge){badge.style.opacity='1';setTimeout(()=>badge.style.opacity='0',2000);}
    });
}

const previewScreen=document.getElementById('previewScreen');
function aggiornaPreview(){
    if(!previewScreen)return;
    const colore=document.getElementById('inp_colore')?.value;
    const testoColore=document.getElementById('inp_testo_colore')?.value;
    const altezza=parseInt(document.getElementById('inp_altezza')?.value)||80;
    const posizione=document.getElementById('inp_posizione')?.value;
    if(!colore)return;
    const scaleW=previewScreen.offsetWidth/1920;
    const altPx=Math.round(altezza*scaleW);
    const banner=document.getElementById('previewBanner');
    if(!banner)return;
    banner.style.backgroundColor=colore;
    banner.style.color=testoColore;
    banner.style.height=altPx+'px';
    banner.style.padding='0 '+Math.round(altPx*0.25)+'px';
    banner.style.gap=Math.round(altPx*0.25)+'px';
    banner.style.bottom=posizione==='bottom'?'0':'auto';
    banner.style.top=posizione==='top'?'0':'auto';
    const ora=document.getElementById('previewOra');
    const data=document.getElementById('previewData');
    if(ora){ora.style.fontSize=Math.round(altPx*0.44)+'px';ora.style.color=testoColore;}
    if(data){data.style.fontSize=Math.round(altPx*0.28)+'px';data.style.color=testoColore;}
    const logo=document.getElementById('previewLogoImg');
    if(logo&&logo.src&&!logo.src.endsWith('/')&&logo.style.display!=='none'){logo.style.height=Math.round(altPx*0.75)+'px';logo.style.width='auto';}
}
function tickOra(){
    const el=document.getElementById('previewOra');if(!el)return;
    const n=new Date();
    el.textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');
}
setInterval(tickOra,1000);tickOra();
['inp_colore','inp_testo_colore','inp_altezza','inp_posizione'].forEach(id=>{
    const el=document.getElementById(id);if(el)el.addEventListener('input',aggiornaPreview);
});
window.addEventListener('resize',aggiornaPreview);
aggiornaPreview();

const logoInput=document.getElementById('logoInput');
if(logoInput){
    logoInput.addEventListener('change',function(){
        const file=this.files[0];if(!file)return;
        const reader=new FileReader();
        reader.onload=e=>{
            const img=document.getElementById('previewLogoImg');
            img.src=e.target.result;img.style.display='block';
            const wrap=document.getElementById('logoCurrentWrap');
            if(wrap){document.getElementById('logoCurrentImg').src=e.target.result;wrap.style.display='flex';}
            aggiornaPreview();
        };
        reader.readAsDataURL(file);
    });
}
const btnRimuovi=document.getElementById('btnRimuoviLogo');
if(btnRimuovi){
    btnRimuovi.addEventListener('click',function(){
        document.getElementById('logoAttuale').value='';
        if(logoInput)logoInput.value='';
        const wrap=document.getElementById('logoCurrentWrap');if(wrap)wrap.style.display='none';
        const img=document.getElementById('previewLogoImg');if(img){img.style.display='none';img.src='';}
        let h=document.getElementById('rimuoviLogoHidden');
        if(!h){h=document.createElement('input');h.type='hidden';h.name='rimuovi_logo';h.id='rimuoviLogoHidden';document.getElementById('bannerForm').appendChild(h);}
        h.value='1';aggiornaPreview();
    });
}

function aggiornaCampi(){
    const tipo=document.getElementById('tipoSel')?.value;if(!tipo)return;
    ['countdown','meteo','info'].forEach(t=>{
        const el=document.getElementById('campi-'+t);if(el)el.style.display=(t===tipo)?'block':'none';
    });
}
aggiornaCampi();

const presetMap=<?php $pm=[];foreach($presets as $k=>$p)$pm[$k]=$p['css'];echo json_encode($pm);?>;
const sfondoPreset=document.getElementById('sfondoPreset');
if(sfondoPreset){
    sfondoPreset.addEventListener('change',function(){
        const prev=document.getElementById('presetPreview');
        const css=presetMap[this.value]||'';
        prev.style.cssText=(css?css:'background:rgba(255,255,255,0.04)')+';border-radius:8px;height:32px;';
    });
}

function toggleAttivo(id,val,devToken){
    const fd=new FormData();
    fd.append('azione','toggle_attivo');fd.append('id',id);fd.append('attivo',val);
    fetch('/layout.php',{method:'POST',body:fd}).then(()=>location.reload());
}

const lista=document.getElementById('lista-slides');
if(lista){
    Sortable.create(lista,{
        handle:'.drag-handle',animation:150,ghostClass:'sortable-ghost',
        onEnd:function(){
            const ids=[...lista.querySelectorAll('[data-id]')].map(el=>el.dataset.id);
            const fd=new FormData();fd.append('azione','riordina');fd.append('ordine',JSON.stringify(ids));
            fetch('/layout.php',{method:'POST',body:fd});
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>