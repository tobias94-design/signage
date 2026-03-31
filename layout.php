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
// ── NUOVI CAMPI CONTROLLO GRANULARE ──
try { $db->exec("ALTER TABLE profili ADD COLUMN logo_size INTEGER DEFAULT 75"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE profili ADD COLUMN data_size INTEGER DEFAULT 28"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE profili ADD COLUMN ora_size INTEGER DEFAULT 44"); } catch(Exception $e) {}

// Fix: rendi profilo_id nullable se non lo è già
try { $db->exec("CREATE TABLE IF NOT EXISTS sidebar_slides_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    dispositivo_token TEXT NOT NULL DEFAULT '',
    profilo_id INTEGER DEFAULT NULL,
    tipo TEXT NOT NULL DEFAULT 'info',
    titolo TEXT DEFAULT '',
    contenuto TEXT DEFAULT '{}',
    durata INTEGER DEFAULT 10,
    ordine INTEGER DEFAULT 0,
    sfondo TEXT DEFAULT '',
    sfondo_preset TEXT DEFAULT '',
    colore_sfondo TEXT DEFAULT '#111',
    colore_testo TEXT DEFAULT '#fff',
    attivo INTEGER DEFAULT 1
)"); } catch(Exception $e) {}

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
        $db->prepare('UPDATE profili SET banner_colore=?,banner_testo_colore=?,banner_posizione=?,banner_altezza=?,logo=?,logo_size=?,data_size=?,ora_size=? WHERE id=?')
           ->execute([
               $_POST['banner_colore']??'#000',
               $_POST['banner_testo_colore']??'#fff',
               $_POST['banner_posizione']??'bottom',
               (int)($_POST['banner_altezza']??80),
               $logo,
               (int)($_POST['logo_size']??75),
               (int)($_POST['data_size']??28),
               (int)($_POST['ora_size']??44),
               $pid
           ]);
    $msg = 'ok|Banner salvato!';
}

// ── AZIONI SLIDE ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione'])) {
    $dev_token = $_POST['dev_token'] ?? '';

    if ($_POST['azione'] === 'aggiungi_slide') {
        $tipo      = $_POST['tipo'] ?? 'info';
        $titolo    = trim($_POST['titolo'] ?? '');
        $durata    = max(3, (int)($_POST['durata'] ?? 10));
        $preset    = $_POST['sfondo_preset'] ?? '';
        $sfondo    = uploadSfondo($_FILES['sfondo'] ?? null);
        $contenuto = [];
        switch ($tipo) {
            case 'countdown': 
                $contenuto = [
                    'data_target' => $_POST['ct_data_target'] ?? '',
                    'messaggio_pre' => $_POST['ct_messaggio_pre'] ?? '',
                    'auto_disable' => isset($_POST['ct_auto_disable']) ? 1 : 0
                ]; 
                break;
            case 'meteo':     
                $contenuto = [
                    'citta' => trim($_POST['mt_citta'] ?? ''),
                    'lat' => trim($_POST['mt_lat'] ?? ''),
                    'lon' => trim($_POST['mt_lon'] ?? '')
                ]; 
                break;
            case 'info':      
                $contenuto = [
                    'testo' => trim($_POST['info_testo'] ?? ''),
                    'icona' => trim($_POST['info_icona'] ?? 'ℹ️')
                ]; 
                break;
        }
        $ordine = (int)$db->query("SELECT COUNT(*) FROM sidebar_slides WHERE dispositivo_token=".$db->quote($dev_token))->fetchColumn();
        $db->prepare('INSERT INTO sidebar_slides (dispositivo_token, profilo_id, tipo, titolo, contenuto, durata, ordine, sfondo, sfondo_preset, colore_sfondo, colore_testo, attivo) VALUES (?,NULL,?,?,?,?,?,?,?,?,?,1)')
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
    SELECT d.token, d.nome, d.club, d.layout_tipo, COALESCE(d.tipo_display,'tv') as tipo_display,
           p.nome AS profilo_nome, p.id AS profilo_id,
           p.banner_colore, p.banner_testo_colore, p.banner_posizione, p.banner_altezza, p.logo,
           COALESCE(p.logo_size, 75) as logo_size,
           COALESCE(p.data_size, 28) as data_size,
           COALESCE(p.ora_size, 44) as ora_size,
           CASE WHEN d.ultimo_ping > datetime('now','-2 minutes') THEN 'online' ELSE 'offline' END AS stato
    FROM dispositivi d
    LEFT JOIN profili p ON p.id = d.profilo_id
    ORDER BY d.club, d.nome
")->fetchAll(PDO::FETCH_ASSOC);

$sel_token = $_GET['dev'] ?? '';
$sel_dev   = null;
if ($sel_token) {
    foreach ($dispositivi as $d) if ($d['token'] === $sel_token) { $sel_dev = $d; break; }
}

$tab = $_GET['tab'] ?? 'banner';
$layout_attivo = $sel_dev['layout_tipo'] ?? 'solo_banner';

$slides = [];
if ($sel_token) {
    $slides = $db->query("SELECT * FROM sidebar_slides WHERE dispositivo_token=".$db->quote($sel_token)." ORDER BY ordine")->fetchAll(PDO::FETCH_ASSOC);
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

    <!-- ── SELECTOR CLUB ── -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
        <span style="font-size:11px;font-weight:700;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;flex-shrink:0;">Dispositivo:</span>
        <select id="dev-select" onchange="window.location.href='/layout.php?dev='+encodeURIComponent(this.value)+'&tab=banner'"
                style="background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.10);border-radius:10px;
                       color:var(--sg-white);padding:8px 14px;font-size:13px;font-weight:500;cursor:pointer;min-width:220px;flex:1;max-width:400px;">
            <option value="" disabled <?= !$sel_token?'selected':'' ?> style="color:var(--sg-muted);">— Seleziona un dispositivo —</option>
            <?php
            $gruppi = ['tv'=>'📺 TV', 'led'=>'💡 Insegne LED', 'totem'=>'🗼 Totem'];
            foreach ($gruppi as $gtipo => $glabel):
                $gdisps = array_filter($dispositivi, fn($d) => ($d['tipo_display']??'tv') === $gtipo);
                if (empty($gdisps)) continue;
            ?>
            <optgroup label="<?= $glabel ?>">
            <?php foreach ($gdisps as $d): ?>
            <option value="<?= htmlspecialchars($d['token']) ?>" <?= $d['token']===$sel_token?'selected':'' ?>>
                <?= $d['stato']==='online' ? '● ' : '○ ' ?><?= htmlspecialchars($d['club'] ?: $d['nome']) ?>
            </option>
            <?php endforeach; ?>
            </optgroup>
            <?php endforeach; ?>
        </select>

        <?php if (!$sel_token): ?>
    <div style="padding:48px;text-align:center;color:var(--sg-muted);">
        <div style="font-size:36px;margin-bottom:12px;">📺</div>
        <div style="font-size:15px;font-weight:600;color:var(--sg-white);margin-bottom:6px;">Seleziona un dispositivo TV</div>
        <div style="font-size:13px;">Scegli un dispositivo dal menu in alto per configurare banner e slide sidebar.</div>
        <?php if (empty($dispositivi)): ?>
        <a href="/dispositivi.php?view=nuovo" class="btn" style="margin-top:20px;display:inline-block;">+ Aggiungi dispositivo</a>
        <?php endif; ?>
    </div>
    <?php elseif ($sel_dev): ?>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="font-size:11px;color:var(--sg-muted);font-weight:600;white-space:nowrap;">Template:</span>
            <div class="layout-pill-group" data-token="<?= htmlspecialchars($sel_token) ?>">
                <button id="pill-banner" class="layout-pill <?= $layout_attivo==='solo_banner'?'active':'' ?>"
                        onclick="setLayout(this,'solo_banner','<?= htmlspecialchars($sel_token) ?>')">
                    📺 Solo Banner
                </button>
                <button id="pill-sidebar" class="layout-pill <?= $layout_attivo==='banner_sidebar'?'active':'' ?>"
                        onclick="setLayout(this,'banner_sidebar','<?= htmlspecialchars($sel_token) ?>')">
                    📺+📋 Con Sidebar
                </button>
            </div>
            <span class="saved-badge" id="saved-badge" style="font-size:10px;color:var(--sg-green);opacity:0;transition:opacity 0.3s;white-space:nowrap;">✓ Salvato</span>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($sel_dev): ?>

    <!-- Info dispositivo -->
    <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:12px;margin-bottom:20px;">
        <?php
        $tipi_disp = ['tv'=>['icona'=>'📺','color'=>'#3b82f6'],'led'=>['icona'=>'💡','color'=>'#f59e0b'],'totem'=>['icona'=>'🗼','color'=>'#8b5cf6']];
        $td_info = $tipi_disp[$sel_dev['tipo_display']??'tv'] ?? $tipi_disp['tv'];
        ?>
        <div class="<?= $sel_dev['stato']==='online'?'sdot sd-on':'sdot sd-off' ?>" style="flex-shrink:0;width:10px;height:10px;"></div>
        <div style="flex:1;">
            <span style="font-size:14px;font-weight:700;color:var(--sg-white);"><?= htmlspecialchars($sel_dev['club'] ?: $sel_dev['nome']) ?></span>
            <span style="margin-left:8px;padding:2px 8px;border-radius:10px;font-size:11px;background:<?= $td_info['color'] ?>22;color:<?= $td_info['color'] ?>;"><?= $td_info['icona'] ?> <?= strtoupper($sel_dev['tipo_display']??'tv') ?></span>
            <span style="font-size:11px;color:var(--sg-muted);margin-left:8px;">
                <?= $sel_dev['profilo_nome'] ? '· Profilo: <strong style="color:var(--sg-white);">'.htmlspecialchars($sel_dev['profilo_nome']).'</strong>' : '· <span style="color:#f59e0b;">⚠ nessun profilo</span>' ?>
                · <?= $sel_dev['stato'] === 'online' ? '<span style="color:var(--sg-green);">● Online</span>' : '<span style="color:var(--sg-red);">○ Offline</span>' ?>
            </span>
        </div>
    </div>

    <?php $tipo_display = $sel_dev['tipo_display'] ?? 'tv'; ?>

    <?php if ($tipo_display === 'tv'): ?>
    <?php // ══ TV: banner + sidebar ══════════════════════════════ ?>

    <!-- Tabs -->
    <div style="display:flex;gap:4px;margin-bottom:24px;" id="tabs-container">
        <a href="/layout.php?dev=<?= urlencode($sel_token) ?>&tab=banner"
           class="btn <?= $tab==='banner'?'':'btn-secondary' ?> btn-sm">🎨 Banner</a>
        <a id="tab-slides-link"
           href="/layout.php?dev=<?= urlencode($sel_token) ?>&tab=slides"
           class="btn <?= $tab==='slides'?'':'btn-secondary' ?> btn-sm tab-slides-btn"
           style="<?= $layout_attivo!=='banner_sidebar'?'opacity:0.35;pointer-events:none;':'' ?>">
            📱 Slide Sidebar
            <?php if (!empty($slides)): ?>
            <span style="background:var(--sg-orange);color:#fff;font-size:9px;padding:1px 5px;border-radius:8px;margin-left:4px;"><?= count($slides) ?></span>
            <?php endif; ?>
            <?php if ($layout_attivo!=='banner_sidebar'): ?>
            <span style="font-size:9px;color:var(--sg-muted);margin-left:4px;">(attiva sidebar prima)</span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Pills template TV -->
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
        <span style="font-size:11px;color:var(--sg-muted);font-weight:600;">Template:</span>
        <div class="layout-pill-group" data-token="<?= htmlspecialchars($sel_token) ?>">
            <button id="pill-banner" class="layout-pill <?= $layout_attivo==='solo_banner'?'active':'' ?>"
                    onclick="setLayout(this,'solo_banner','<?= htmlspecialchars($sel_token) ?>')">
                📺 Solo Banner
            </button>
            <button id="pill-sidebar" class="layout-pill <?= $layout_attivo==='banner_sidebar'?'active':'' ?>"
                    onclick="setLayout(this,'banner_sidebar','<?= htmlspecialchars($sel_token) ?>')">
                📺+📋 Con Sidebar
            </button>
        </div>
        <span class="saved-badge" id="saved-badge" style="font-size:10px;color:var(--sg-green);opacity:0;transition:opacity 0.3s;white-space:nowrap;">✓ Salvato</span>
    </div>

    <?php if ($tab === 'banner'): ?>
    <!-- ── BANNER ── -->
    <div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start;">
        <div>
            <?php if (!$sel_dev['profilo_id']): ?>
            <div style="padding:14px 16px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.20);border-radius:12px;font-size:13px;color:#f59e0b;">
                ⚠️ Nessun profilo assegnato.<br>
                <a href="/dispositivi.php" style="color:var(--sg-orange);font-weight:700;">Vai su Dispositivi →</a> e assegna un profilo a questo dispositivo.
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
                
                <label>Altezza Banner (px) <span id="val_altezza" style="color:var(--sg-orange);font-weight:700;"><?= (int)($sel_dev['banner_altezza']??80) ?></span></label>
                <input type="range" name="banner_altezza" id="inp_altezza" value="<?= (int)($sel_dev['banner_altezza']??80) ?>" min="40" max="200" oninput="document.getElementById('val_altezza').textContent=this.value">
                
                <!-- ══ NUOVI CONTROLLI GRANULARI ══ -->
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);">
                    <div style="font-size:11px;color:var(--sg-muted);margin-bottom:12px;font-weight:600;">Dimensioni Elementi</div>
                    
                    <label>Dimensione Logo (%) <span id="val_logo" style="color:var(--sg-orange);font-weight:700;"><?= (int)($sel_dev['logo_size']??75) ?></span></label>
                    <input type="range" name="logo_size" id="inp_logo_size" value="<?= (int)($sel_dev['logo_size']??75) ?>" min="40" max="100" oninput="document.getElementById('val_logo').textContent=this.value">
                    
                    <label>Dimensione Giorno/Data (%) <span id="val_data" style="color:var(--sg-orange);font-weight:700;"><?= (int)($sel_dev['data_size']??28) ?></span></label>
                    <input type="range" name="data_size" id="inp_data_size" value="<?= (int)($sel_dev['data_size']??28) ?>" min="16" max="44" oninput="document.getElementById('val_data').textContent=this.value">
                    
                    <label>Dimensione Orario (%) <span id="val_ora" style="color:var(--sg-orange);font-weight:700;"><?= (int)($sel_dev['ora_size']??44) ?></span></label>
                    <input type="range" name="ora_size" id="inp_ora_size" value="<?= (int)($sel_dev['ora_size']??44) ?>" min="24" max="64" oninput="document.getElementById('val_ora').textContent=this.value">
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;">
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
                <button type="submit" class="btn" style="width:100%;margin-top:4px;">💾 Salva Banner</button>
            </form>
            <?php endif; ?>
        </div>
        <!-- Anteprima -->
        <div>
            <div style="font-size:11px;font-weight:700;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Anteprima live</div>
            <div id="previewScreen" style="position:relative;width:100%;aspect-ratio:16/9;background:rgba(255,255,255,0.03);border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,0.08);">
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:rgba(255,255,255,0.05);font-size:13px;letter-spacing:2px;">SEGNALE TV</div>
                <?php if ($layout_attivo === 'banner_sidebar'): ?>
                <div style="position:absolute;top:0;right:0;bottom:0;width:20%;background:rgba(0,0,0,0.4);border-left:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;">
                    <div style="font-size:7px;color:rgba(255,255,255,0.15);letter-spacing:2px;writing-mode:vertical-rl;">SIDEBAR</div>
                </div>
                <?php endif; ?>
                <div id="previewBanner" style="position:absolute;left:0;right:0;display:flex;align-items:center;overflow:hidden;">
                    <div id="previewLogoWrap" style="display:flex;align-items:center;justify-content:flex-start;flex-shrink:0;">
                        <img id="previewLogoImg" src="<?= !empty($sel_dev['logo'])?'assets/img/'.htmlspecialchars($sel_dev['logo']):'' ?>" style="object-fit:contain;flex-shrink:0;display:<?= !empty($sel_dev['logo'])?'block':'none' ?>">
                    </div>
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
    <div style="display:grid;grid-template-columns:320px 1fr;gap:24px;align-items:start;">
        <!-- Form aggiunta -->
        <div style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:18px;">
            <div style="font-size:11px;font-weight:700;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:14px;">
                + Nuova slide
                <span style="color:var(--sg-orange);text-transform:none;letter-spacing:0;font-size:10px;font-weight:400;"> per <?= htmlspecialchars($sel_dev['club']?:$sel_dev['nome']) ?></span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="azione" value="aggiungi_slide">
                <input type="hidden" name="dev_token" value="<?= htmlspecialchars($sel_token) ?>">
                <label>Tipo slide</label>
                <select name="tipo" id="tipoSel" onchange="aggiornaCampi()">
                    <?php foreach ($tipi as $k=>$t): ?>
                    <option value="<?= $k ?>"><?= $t['label'] ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Titolo</label>
                <input type="text" name="titolo" placeholder="Es. Oggi in palestra...">
                <label>Durata (secondi)</label>
                <input type="number" name="durata" value="10" min="3" max="120">

                <div id="campi-countdown" style="display:none;">
                    <label>Data e ora evento</label>
                    <input type="datetime-local" name="ct_data_target">
                    <label>Messaggio pre-evento <span style="font-size:10px;color:var(--sg-muted);">(opzionale)</span></label>
                    <input type="text" name="ct_messaggio_pre" placeholder="Es. Preparati per...">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:8px;">
                        <input type="checkbox" name="ct_auto_disable" value="1" checked style="width:auto;margin:0;">
                        <span style="font-size:13px;">Disattiva slide automaticamente al termine countdown</span>
                    </label>
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

                <div style="border-top:1px solid rgba(255,255,255,0.06);margin-top:14px;padding-top:14px;">
                    <div style="font-size:11px;color:var(--sg-muted);margin-bottom:10px;font-weight:600;">Sfondo</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">
                        <div><label>Sfondo</label><input type="color" name="colore_sfondo" value="#111111" style="width:100%;height:38px;cursor:pointer;"></div>
                        <div><label>Testo</label><input type="color" name="colore_testo" value="#ffffff" style="width:100%;height:38px;cursor:pointer;"></div>
                    </div>
                    <label>Preset sfondo</label>
                    <select name="sfondo_preset" id="sfondoPreset" onchange="aggiornaPreset(this.value)">
                        <?php foreach ($presets as $k=>$p): ?>
                        <option value="<?= $k ?>"><?= $p['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="presetPreview" style="height:32px;border-radius:8px;margin-top:6px;margin-bottom:10px;background:rgba(255,255,255,0.04);transition:all 0.2s;"></div>
                    <label>Immagine sfondo <span style="color:var(--sg-muted);font-size:10px;">380×1080px</span></label>
                    <input type="file" name="sfondo" accept="image/*">
                </div>
                <button type="submit" class="btn" style="width:100%;margin-top:14px;">+ Aggiungi Slide</button>
            </form>
        </div>

        <!-- Lista slide -->
        <div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div style="font-size:11px;font-weight:700;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;">
                    Slide di questo dispositivo
                    <span style="background:var(--sg-orange);color:#fff;font-size:9px;padding:2px 7px;border-radius:8px;margin-left:6px;"><?= count($slides) ?></span>
                </div>
                <div style="font-size:10px;color:var(--sg-muted);">⠿ trascina per riordinare</div>
            </div>

            <?php if (empty($slides)): ?>
            <div style="padding:32px;text-align:center;background:rgba(255,255,255,0.02);border:1px dashed rgba(255,255,255,0.08);border-radius:14px;color:var(--sg-muted);font-size:13px;">
                Nessuna slide ancora.<br>Aggiungine una dal form a sinistra.
            </div>
            <?php else: ?>
            <div id="lista-slides">
            <?php foreach ($slides as $sl):
                $cfg = json_decode($sl['contenuto'],true) ?: [];
                $ti  = $tipi[$sl['tipo']] ?? ['label'=>$sl['tipo'],'colore'=>'#aaa'];
                $hasPreset = !empty($sl['sfondo_preset']) && isset($presets[$sl['sfondo_preset']]) && $presets[$sl['sfondo_preset']]['css'];
                $hasSfondo = !empty($sl['sfondo']);
                $bgCss = $hasSfondo ? "background-image:url('/uploads/{$sl['sfondo']}');background-size:cover;" : ($hasPreset ? $presets[$sl['sfondo_preset']]['css'] : "background:{$sl['colore_sfondo']};");
            ?>
            <div data-id="<?= $sl['id'] ?>" style="display:flex;align-items:stretch;border-radius:12px;margin-bottom:8px;overflow:hidden;border:1px solid rgba(255,255,255,0.08);opacity:<?= $sl['attivo']?'1':'0.45' ?>;transition:opacity 0.2s;">
                <div style="width:52px;flex-shrink:0;position:relative;<?= $bgCss ?>">
                    <div style="position:absolute;bottom:4px;left:0;right:0;text-align:center;font-size:10px;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,1);font-weight:bold;"><?= $sl['durata'] ?>s</div>
                </div>
                <div style="flex:1;padding:10px 14px;background:rgba(255,255,255,0.03);display:flex;flex-direction:column;justify-content:center;gap:5px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span class="drag-handle" style="color:var(--sg-muted);cursor:grab;font-size:18px;user-select:none;line-height:1;">⠿</span>
                        <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;background:<?= $ti['colore'] ?>22;color:<?= $ti['colore'] ?>;"><?= $ti['label'] ?></span>
                        <?php if ($sl['titolo']): ?><span style="font-size:13px;font-weight:600;color:var(--sg-white);"><?= htmlspecialchars($sl['titolo']) ?></span><?php endif; ?>
                    </div>
                    <div style="font-size:11px;color:var(--sg-muted);padding-left:26px;">
                        <?php if ($sl['tipo']==='countdown'&&!empty($cfg['data_target'])): ?>
                            📅 <?= date('d/m/Y H:i',strtotime($cfg['data_target'])) ?>
                            <?php if (!empty($cfg['messaggio_pre'])): ?> · Pre: <?= htmlspecialchars(mb_substr($cfg['messaggio_pre'],0,30)) ?><?php endif; ?>
                            <?php if (!empty($cfg['auto_disable'])): ?> · <span style="color:var(--sg-orange);">⚡ Auto-off</span><?php endif; ?>
                        <?php elseif ($sl['tipo']==='meteo'&&!empty($cfg['citta'])): ?>📍 <?= htmlspecialchars($cfg['citta']);
                        elseif ($sl['tipo']==='info'&&!empty($cfg['testo'])): ?><?= htmlspecialchars($cfg['icona']??'') ?> <?= htmlspecialchars(mb_substr($cfg['testo'],0,60));
                        elseif ($sl['tipo']==='corsi'): ?>Corsi dal Google Sheet<?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;padding:8px 10px;gap:5px;align-items:center;justify-content:center;background:rgba(0,0,0,0.12);">
                    <button onclick="toggleAttivo(<?= $sl['id'] ?>,<?= $sl['attivo']?0:1 ?>)" class="btn btn-sm"
                            style="font-size:10px;padding:4px 10px;min-width:48px;background:<?= $sl['attivo']?'rgba(48,209,88,0.12)':'rgba(255,255,255,0.04)' ?>;color:<?= $sl['attivo']?'var(--sg-green)':'var(--sg-muted)' ?>;border:1px solid <?= $sl['attivo']?'rgba(48,209,88,0.20)':'rgba(255,255,255,0.08)' ?>;">
                        <?= $sl['attivo']?'● ON':'○ OFF' ?>
                    </button>
                    <a href="/layout.php?elimina_slide=<?= $sl['id'] ?>&dev=<?= urlencode($sel_token) ?>&tab=slides"
                       onclick="return confirm('Eliminare questa slide?')"
                       class="btn btn-danger btn-sm" style="font-size:10px;padding:4px 10px;">✕</a>
                </div>
            </div>
            <?php endforeach; ?>
            </div>

            <!-- Anteprima carousel -->
            <?php $slidesAttive = array_filter($slides, fn($s)=>$s['attivo']); ?>
            <?php if (!empty($slidesAttive)): ?>
            <div style="margin-top:20px;padding:14px 16px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:12px;">
                <div style="font-size:10px;color:var(--sg-muted);margin-bottom:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;">👁 Anteprima carousel (<?= count($slidesAttive) ?> attive)</div>
                <div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;">
                <?php foreach ($slidesAttive as $sl):
                    $cfg=json_decode($sl['contenuto'],true)?:[];
                    $ti=$tipi[$sl['tipo']]??['colore'=>'#aaa'];
                    $hasSfondo=!empty($sl['sfondo']);
                    $hasPreset=!empty($sl['sfondo_preset'])&&isset($presets[$sl['sfondo_preset']])&&$presets[$sl['sfondo_preset']]['css'];
                    $bgCss=$hasSfondo?"background-image:url('/uploads/{$sl['sfondo']}');background-size:cover;background-position:center;":($hasPreset?$presets[$sl['sfondo_preset']]['css']:"background:{$sl['colore_sfondo']};");
                ?>
                <div style="flex-shrink:0;width:75px;height:124px;border-radius:10px;overflow:hidden;position:relative;border:1px solid rgba(255,255,255,0.10);<?= $bgCss ?>color:<?= $sl['colore_testo'] ?>;">
                    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.38);"></div>
                    <div style="position:relative;z-index:1;padding:7px;height:100%;display:flex;flex-direction:column;justify-content:space-between;">
                        <div style="font-size:7px;opacity:0.65;text-transform:uppercase;"><?= $ti['label']??'' ?></div>
                        <div>
                            <?php if ($sl['tipo']==='countdown'): ?><div style="font-size:13px;font-weight:900;">00:00</div>
                            <?php elseif ($sl['tipo']==='meteo'): ?><div style="font-size:20px;">⛅</div>
                            <?php elseif ($sl['tipo']==='info'): ?><div style="font-size:14px;"><?= $cfg['icona']??'ℹ️' ?></div>
                            <?php elseif ($sl['tipo']==='corsi'): ?><div style="font-size:7px;line-height:1.6;opacity:0.8;">09:00 Yoga<br>10:30 Pilates</div>
                            <?php endif; ?>
                            <?php if ($sl['titolo']): ?><div style="font-size:7px;font-weight:700;margin-top:3px;word-break:break-word;"><?= htmlspecialchars($sl['titolo']) ?></div><?php endif; ?>
                        </div>
                        <div style="font-size:7px;opacity:0.45;text-align:right;"><?= $sl['durata'] ?>s</div>
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

    <?php elseif ($tipo_display === 'led'): ?>
    <?php // ══ LED: logo + video + orario ═══════════════════════ ?>
    <div style="display:grid;grid-template-columns:320px 1fr;gap:24px;align-items:start;">
        <div class="box" style="background:rgba(245,158,11,0.06);border-color:rgba(245,158,11,0.20);">
            <div style="font-size:11px;font-weight:700;color:#f59e0b;text-transform:uppercase;letter-spacing:1px;margin-bottom:16px;">💡 Configurazione LED</div>
            <p style="font-size:12px;color:var(--sg-muted);margin-bottom:16px;">
                Il player LED mostra il logo del club. Dalle <strong style="color:var(--sg-white);">23:00 alle 06:00</strong> lo schermo è nero automaticamente.<br>
                Per cambiare il logo usa la sezione <a href="/contenuti.php" style="color:var(--sg-orange);">Contenuti</a> e assegna una playlist al dispositivo.
            </p>
            <div style="padding:14px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;font-size:12px;color:var(--sg-muted);">
                <div style="margin-bottom:8px;"><strong style="color:var(--sg-white);">Risoluzione consigliata:</strong> 1720×320px</div>
                <div style="margin-bottom:8px;"><strong style="color:var(--sg-white);">Formati:</strong> PNG, JPG, SVG, GIF, MP4</div>
                <div><strong style="color:var(--sg-white);">Spegnimento automatico:</strong> 23:00 – 06:00</div>
            </div>
            <div style="margin-top:16px;">
                <a href="/dispositivi.php?view=modifica&token=<?= urlencode($sel_token) ?>" class="btn btn-sm btn-secondary" style="width:100%;">✏️ Modifica dispositivo</a>
            </div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:700;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Anteprima LED</div>
            <div style="width:100%;aspect-ratio:1720/320;background:#000;border-radius:10px;border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center;overflow:hidden;">
                <?php if (!empty($sel_dev['logo'])): ?>
                <img src="/assets/img/<?= htmlspecialchars($sel_dev['logo']) ?>" style="max-height:80%;max-width:80%;object-fit:contain;">
                <?php else: ?>
                <div style="color:rgba(255,255,255,0.15);font-size:13px;">Nessun logo configurato</div>
                <?php endif; ?>
            </div>
            <div style="margin-top:12px;padding:10px 14px;background:rgba(255,255,255,0.03);border-radius:8px;font-size:11px;color:var(--sg-muted);">
                Il logo viene preso dal <strong style="color:var(--sg-white);">profilo</strong> assegnato al dispositivo.
                <?php if (!$sel_dev['profilo_id']): ?>
                <a href="/dispositivi.php?view=modifica&token=<?= urlencode($sel_token) ?>" style="color:var(--sg-orange);margin-left:4px;">Assegna un profilo →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php elseif ($tipo_display === 'totem'): ?>
    <?php // ══ TOTEM: playlist video ═════════════════════════════ ?>
    <div style="display:grid;grid-template-columns:320px 1fr;gap:24px;align-items:start;">
        <div class="box" style="background:rgba(139,92,246,0.06);border-color:rgba(139,92,246,0.20);">
            <div style="font-size:11px;font-weight:700;color:#8b5cf6;text-transform:uppercase;letter-spacing:1px;margin-bottom:16px;">🗼 Configurazione Totem</div>
            <p style="font-size:12px;color:var(--sg-muted);margin-bottom:16px;">
                Il player Totem riproduce video in loop dalla playlist assegnata. Nessun banner, nessuna sidebar.
            </p>
            <div style="padding:14px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;font-size:12px;color:var(--sg-muted);">
                <div style="margin-bottom:8px;"><strong style="color:var(--sg-white);">Risoluzione consigliata:</strong> 256×768px</div>
                <div style="margin-bottom:8px;"><strong style="color:var(--sg-white);">Orientamento:</strong> Verticale</div>
                <div><strong style="color:var(--sg-white);">Contenuto:</strong> Video in loop dalla playlist</div>
            </div>
            <?php if ($sel_dev['profilo_nome']): ?>
            <div style="margin-top:14px;padding:10px 14px;background:rgba(139,92,246,0.10);border:1px solid rgba(139,92,246,0.20);border-radius:8px;font-size:12px;">
                <span style="color:var(--sg-muted);">Profilo attivo:</span>
                <strong style="color:var(--sg-white);margin-left:6px;"><?= htmlspecialchars($sel_dev['profilo_nome']) ?></strong>
            </div>
            <?php else: ?>
            <div style="margin-top:14px;padding:10px 14px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.20);border-radius:8px;font-size:12px;color:#f59e0b;">
                ⚠️ Nessun profilo assegnato — vai su <a href="/dispositivi.php?view=modifica&token=<?= urlencode($sel_token) ?>" style="color:var(--sg-orange);">Dispositivi</a> e assegnane uno.
            </div>
            <?php endif; ?>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <a href="/playlist.php" class="btn btn-sm btn-secondary" style="flex:1;">📋 Gestisci Playlist</a>
                <a href="/dispositivi.php?view=modifica&token=<?= urlencode($sel_token) ?>" class="btn btn-sm btn-secondary" style="flex:1;">✏️ Modifica</a>
            </div>
        </div>
        <div>
            <div style="font-size:11px;font-weight:700;color:var(--sg-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Anteprima Totem</div>
            <div style="width:140px;aspect-ratio:256/768;background:#000;border-radius:10px;border:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center;">
                <div style="color:rgba(255,255,255,0.15);font-size:11px;text-align:center;padding:10px;">Video<br>in loop</div>
            </div>
        </div>
    </div>

    <?php endif; // tipo_display ?>

    <?php endif; // sel_dev/sel_token ?>
</div>

<!-- ══ RIEPILOGO TUTTI I DISPOSITIVI ══ -->
<div class="box">
    <div class="wl" style="margin-bottom:14px;">
        Riepilogo tutti i dispositivi
        <span style="font-size:11px;color:var(--sg-muted);font-weight:400;">— click sulle pill per cambiare template</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;">
    <?php foreach ($dispositivi as $dev):
        $lt = $dev['layout_tipo'] ?? 'solo_banner';
        $is_on = $dev['stato'] === 'online';
        $n_slides = (int)$db->query("SELECT COUNT(*) FROM sidebar_slides WHERE dispositivo_token=".$db->quote($dev['token']))->fetchColumn();
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);border-radius:10px;<?= $dev['token']===$sel_token?'border-color:rgba(232,80,2,0.25);background:rgba(232,80,2,0.04);':'' ?>">
        <div style="width:8px;height:8px;border-radius:50%;background:<?= $is_on?'var(--sg-green)':'rgba(255,255,255,0.15)' ?>;flex-shrink:0;"></div>
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
        <a href="/layout.php?dev=<?= urlencode($dev['token']) ?>&tab=<?= $lt==='banner_sidebar'?'slides':'banner' ?>"
           style="font-size:11px;color:var(--sg-orange);text-decoration:none;white-space:nowrap;padding:4px 10px;border:1px solid rgba(232,80,2,0.20);border-radius:8px;">
            Configura →
        </a>
    </div>
    <?php endforeach; ?>
    </div>
</div>

</div>

<style>
.layout-pill-group { display:flex;gap:5px; }
.layout-pill {
    padding:7px 16px;border-radius:20px;font-size:12px;font-weight:600;
    border:1px solid rgba(255,255,255,0.10);background:rgba(255,255,255,0.04);
    color:var(--sg-muted);cursor:pointer;transition:all 0.2s;
}
.layout-pill:hover { border-color:rgba(232,80,2,0.35);color:var(--sg-white); }
.layout-pill.active { background:rgba(232,80,2,0.15);border-color:rgba(232,80,2,0.45);color:var(--sg-orange); }
.layout-pill-sm {
    padding:4px 11px;border-radius:14px;font-size:11px;font-weight:600;
    border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);
    color:var(--sg-muted);cursor:pointer;transition:all 0.15s;
}
.layout-pill-sm:hover { border-color:rgba(232,80,2,0.25);color:var(--sg-white); }
.layout-pill-sm.active { background:rgba(232,80,2,0.12);border-color:rgba(232,80,2,0.30);color:var(--sg-orange); }
.sortable-ghost { opacity:0.25; }
.drag-handle:hover { color:var(--sg-orange) !important; }
input[type=datetime-local] {
    width:100%;padding:10px 12px;background:rgba(255,255,255,0.055)!important;
    border:1px solid rgba(255,255,255,0.10)!important;border-radius:10px;
    color:var(--sg-white)!important;font-size:14px;margin-bottom:12px;
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
var currentToken = <?= json_encode($sel_token) ?>;

function setLayout(btn, tipo, token) {
    document.querySelectorAll('.layout-pill-group[data-token="'+token+'"]').forEach(function(group){
        group.querySelectorAll('.layout-pill, .layout-pill-sm').forEach(function(b){
            b.classList.remove('active');
        });
        btn.classList.add('active');
        group.querySelectorAll('button').forEach(function(b){
            if (tipo === 'banner_sidebar' && b.textContent.includes('Sidebar')) b.classList.add('active');
            if (tipo === 'solo_banner' && !b.textContent.includes('Sidebar')) b.classList.add('active');
        });
    });

    if (token === currentToken) {
        var tabSlides = document.getElementById('tab-slides-link');
        if (tabSlides) {
            if (tipo === 'banner_sidebar') {
                tabSlides.style.opacity = '1';
                tabSlides.style.pointerEvents = 'auto';
                tabSlides.querySelector && tabSlides.querySelectorAll('span').forEach(function(s){
                    if (s.textContent.includes('attiva sidebar')) s.style.display='none';
                });
            } else {
                tabSlides.style.opacity = '0.35';
                tabSlides.style.pointerEvents = 'none';
            }
        }
    }

    var badge = document.getElementById('saved-badge');
    var fd = new FormData();
    fd.append('salva_layout_tipo','1');
    fd.append('token', token);
    fd.append('layout_tipo', tipo);
    fetch('/layout.php', {method:'POST', body:fd}).then(r=>r.text()).then(function(t){
        if (t==='ok' && badge) {
            badge.style.opacity='1';
            setTimeout(function(){ badge.style.opacity='0'; }, 2000);
        }
    });
}

// ── Preview banner CON CONTROLLI GRANULARI ──
var previewScreen = document.getElementById('previewScreen');
function aggiornaPreview(){
    if(!previewScreen) return;
    var colore     = document.getElementById('inp_colore')?.value;
    var testoCol   = document.getElementById('inp_testo_colore')?.value;
    var altezza    = parseInt(document.getElementById('inp_altezza')?.value)||80;
    var posizione  = document.getElementById('inp_posizione')?.value;
    var logoSize   = parseInt(document.getElementById('inp_logo_size')?.value)||75;
    var dataSize   = parseInt(document.getElementById('inp_data_size')?.value)||28;
    var oraSize    = parseInt(document.getElementById('inp_ora_size')?.value)||44;
    
    if(!colore) return;
    var scaleW = previewScreen.offsetWidth/1920;
    var altPx  = Math.round(altezza * scaleW);
    var banner = document.getElementById('previewBanner');
    if(!banner) return;
    
    banner.style.backgroundColor = colore;
    banner.style.color           = testoCol;
    banner.style.height          = altPx+'px';
    banner.style.padding         = '0 '+Math.round(altPx*0.25)+'px';
    banner.style.gap             = Math.round(altPx*0.25)+'px';
    banner.style.bottom          = posizione==='bottom'?'0':'auto';
    banner.style.top             = posizione==='top'?'0':'auto';
    
    // Applicare dimensioni granulari
    var ora  = document.getElementById('previewOra');
    var data = document.getElementById('previewData');
    var logo = document.getElementById('previewLogoImg');
    
    if(ora)  { 
        ora.style.fontSize  = Math.round(altPx * (oraSize / 100))+'px'; 
        ora.style.color  = testoCol; 
    }
    if(data) { 
        data.style.fontSize = Math.round(altPx * (dataSize / 100))+'px'; 
        data.style.color = testoCol; 
    }
    if(logo && logo.src && !logo.src.endsWith('/') && logo.style.display!=='none') {
        logo.style.height = Math.round(altPx * (logoSize / 100))+'px';
        logo.style.width  = 'auto';
    }
}

function tickOra(){
    var el=document.getElementById('previewOra'); if(!el) return;
    var n=new Date();
    el.textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');
}
setInterval(tickOra,1000); tickOra();

['inp_colore','inp_testo_colore','inp_altezza','inp_posizione','inp_logo_size','inp_data_size','inp_ora_size'].forEach(function(id){
    var el=document.getElementById(id); if(el) el.addEventListener('input',aggiornaPreview);
});
window.addEventListener('resize',aggiornaPreview);
aggiornaPreview();

// ── Logo preview ──
var logoInput=document.getElementById('logoInput');
if(logoInput){
    logoInput.addEventListener('change',function(){
        var file=this.files[0]; if(!file) return;
        var reader=new FileReader();
        reader.onload=function(e){
            var img=document.getElementById('previewLogoImg');
            img.src=e.target.result; img.style.display='block';
            var wrap=document.getElementById('logoCurrentWrap');
            if(wrap){document.getElementById('logoCurrentImg').src=e.target.result; wrap.style.display='flex';}
            aggiornaPreview();
        };
        reader.readAsDataURL(file);
    });
}
var btnRimuovi=document.getElementById('btnRimuoviLogo');
if(btnRimuovi){
    btnRimuovi.addEventListener('click',function(){
        document.getElementById('logoAttuale').value='';
        if(logoInput) logoInput.value='';
        var wrap=document.getElementById('logoCurrentWrap'); if(wrap) wrap.style.display='none';
        var img=document.getElementById('previewLogoImg'); if(img){img.style.display='none';img.src='';}
        var h=document.getElementById('rimuoviLogoHidden');
        if(!h){h=document.createElement('input');h.type='hidden';h.name='rimuovi_logo';h.id='rimuoviLogoHidden';document.getElementById('bannerForm').appendChild(h);}
        h.value='1'; aggiornaPreview();
    });
}

// ── Campi dinamici per tipo slide ──
function aggiornaCampi(){
    var tipo=document.getElementById('tipoSel')?.value; if(!tipo) return;
    ['countdown','meteo','info'].forEach(function(t){
        var el=document.getElementById('campi-'+t); if(el) el.style.display=(t===tipo)?'block':'none';
    });
}
aggiornaCampi();

// ── Preset sfondo preview ──
var presetMap=<?php $pm=[];foreach($presets as $k=>$p)$pm[$k]=$p['css'];echo json_encode($pm);?>;

function aggiornaPreset(val) {
    var prev = document.getElementById('presetPreview');
    if (!prev) return;
    var css = presetMap[val] || '';
    prev.style.cssText = (css ? css : 'background:rgba(255,255,255,0.04)') + ';border-radius:8px;height:32px;transition:all 0.2s;';
}

var sfondoPreset=document.getElementById('sfondoPreset');
if(sfondoPreset){
    sfondoPreset.addEventListener('change',function(){
        var prev=document.getElementById('presetPreview');
        var css=presetMap[this.value]||'';
        prev.style.cssText=(css?css:'background:rgba(255,255,255,0.04)')+';border-radius:8px;height:32px;margin-top:6px;margin-bottom:10px;transition:all 0.2s;';
    });
}

// ── Toggle attivo slide ──
function toggleAttivo(id, val){
    var fd=new FormData();
    fd.append('azione','toggle_attivo'); fd.append('id',id); fd.append('attivo',val);
    fetch('/layout.php',{method:'POST',body:fd}).then(function(){ location.reload(); });
}

// ── Drag & drop riordina slide ──
var lista=document.getElementById('lista-slides');
if(lista){
    Sortable.create(lista,{
        handle:'.drag-handle', animation:150, ghostClass:'sortable-ghost',
        onEnd:function(){
            var ids=[...lista.querySelectorAll('[data-id]')].map(function(el){return el.dataset.id;});
            var fd=new FormData();
            fd.append('azione','riordina'); fd.append('ordine',JSON.stringify(ids));
            fetch('/layout.php',{method:'POST',body:fd});
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>