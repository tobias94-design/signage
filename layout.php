<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();

$msg = '';

// ── SALVA SOLO LAYOUT TIPO (AJAX) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salva_layout_tipo'])) {
    try { $db->exec("ALTER TABLE profili ADD COLUMN layout_tipo TEXT DEFAULT 'solo_banner'"); } catch(Exception $e) {}
    $db->prepare('UPDATE profili SET layout_tipo=?')->execute([$_POST['layout_tipo'] ?? 'solo_banner']);
    echo 'ok'; exit;
}

// ── SALVA BANNER ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salva_banner'])) {
    $colore       = $_POST['banner_colore']       ?? '#000000';
    $testo_colore = $_POST['banner_testo_colore'] ?? '#ffffff';
    $posizione    = $_POST['banner_posizione']    ?? 'bottom';
    $altezza      = (int)($_POST['banner_altezza'] ?? 80);
    $layout_tipo  = $_POST['layout_tipo'] ?? 'solo_banner';

    $logo_attuale = $_POST['logo_attuale'] ?? '';
    $logo         = $logo_attuale;

    if (!empty($_FILES['logo']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['png','jpg','jpeg','svg','webp'];
        if (in_array($ext, $allowed)) {
            $nome = 'logo_global.' . $ext;
            $dest = __DIR__ . '/assets/img/' . $nome;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) $logo = $nome;
        }
    }
    if (isset($_POST['rimuovi_logo'])) $logo = '';

    $db->prepare('UPDATE profili SET banner_colore=?, banner_testo_colore=?, banner_posizione=?, banner_altezza=?, logo=?, layout_tipo=?')
       ->execute([$colore, $testo_colore, $posizione, $altezza, $logo, $layout_tipo]);
    $msg = 'ok|Layout salvato!';
}

// ── UPLOAD SFONDO SLIDE ────────────────────────────────────────
function uploadSfondo($file) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) return '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) return '';
    $nome = 'sidebar_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $nome)) return $nome;
    return '';
}

// ── AZIONI SLIDE ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione'])) {

    if ($_POST['azione'] === 'aggiungi_slide') {
        $profilo_id  = intval($_POST['profilo_id']);
        $tipo        = $_POST['tipo'] ?? 'info';
        $titolo      = trim($_POST['titolo'] ?? '');
        $durata      = max(3, intval($_POST['durata'] ?? 10));
        $colore_sf   = $_POST['colore_sfondo']  ?? '#111111';
        $colore_tx   = $_POST['colore_testo']   ?? '#ffffff';
        $preset      = $_POST['sfondo_preset']  ?? '';
        $sfondo      = uploadSfondo($_FILES['sfondo'] ?? null);

        $contenuto = [];
        switch ($tipo) {
            case 'countdown':
                $contenuto = ['data_target' => $_POST['ct_data_target'] ?? '', 'messaggio_post' => $_POST['ct_messaggio_post'] ?? 'Evento in corso!'];
                break;
            case 'meteo':
                $contenuto = ['citta' => trim($_POST['mt_citta'] ?? ''), 'lat' => trim($_POST['mt_lat'] ?? ''), 'lon' => trim($_POST['mt_lon'] ?? '')];
                break;
            case 'info':
                $contenuto = ['testo' => trim($_POST['info_testo'] ?? ''), 'icona' => trim($_POST['info_icona'] ?? 'ℹ️')];
                break;
        }

        $ordine = intval($db->query("SELECT COUNT(*) FROM sidebar_slides WHERE profilo_id=$profilo_id")->fetchColumn());
        $db->prepare('INSERT INTO sidebar_slides (profilo_id, tipo, titolo, contenuto, durata, ordine, sfondo, sfondo_preset, colore_sfondo, colore_testo, attivo) VALUES (?,?,?,?,?,?,?,?,?,?,1)')
           ->execute([$profilo_id, $tipo, $titolo, json_encode($contenuto), $durata, $ordine, $sfondo, $preset, $colore_sf, $colore_tx]);
        $msg = 'ok|Slide aggiunta!';
    }

    if ($_POST['azione'] === 'toggle_attivo') {
        $db->prepare('UPDATE sidebar_slides SET attivo=? WHERE id=?')->execute([intval($_POST['attivo']), intval($_POST['id'])]);
        echo 'ok'; exit;
    }

    if ($_POST['azione'] === 'riordina') {
        foreach (json_decode($_POST['ordine'], true) as $pos => $id)
            $db->prepare('UPDATE sidebar_slides SET ordine=? WHERE id=?')->execute([$pos, intval($id)]);
        echo 'ok'; exit;
    }
}

if (isset($_GET['elimina_slide'])) {
    $id  = intval($_GET['elimina_slide']);
    $p   = intval($_GET['profilo'] ?? 0);
    $row = $db->query("SELECT sfondo FROM sidebar_slides WHERE id=$id")->fetch();
    if ($row && $row['sfondo']) @unlink(__DIR__ . '/uploads/' . $row['sfondo']);
    $db->exec("DELETE FROM sidebar_slides WHERE id=$id");
    header('Location: /layout.php?tab=sidebar&profilo='.$p); exit;
}

// ── DATI ─────────────────────────────────────────────────────
try { $db->exec("ALTER TABLE profili ADD COLUMN layout_tipo TEXT DEFAULT 'solo_banner'"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE sidebar_slides ADD COLUMN sfondo_preset TEXT DEFAULT ''"); } catch(Exception $e) {}

$profili    = $db->query('SELECT * FROM profili ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
$profilo_id = isset($_GET['profilo']) ? intval($_GET['profilo']) : ($profili[0]['id'] ?? 0);
$profilo    = $db->query("SELECT * FROM profili WHERE id=$profilo_id")->fetch(PDO::FETCH_ASSOC) ?? ($profili[0] ?? []);
$profilo_id = $profilo['id'] ?? 0;
$layout_attivo = $profilo['layout_tipo'] ?? 'solo_banner';
$tab = $_GET['tab'] ?? 'banner';

$slides = $profilo_id ? $db->query("SELECT * FROM sidebar_slides WHERE profilo_id=$profilo_id ORDER BY ordine")->fetchAll(PDO::FETCH_ASSOC) : [];

$tipi = [
    'corsi'     => ['label' => '📋 Corsi del giorno', 'colore' => '#e94560'],
    'countdown' => ['label' => '⏳ Countdown evento',  'colore' => '#f59e0b'],
    'meteo'     => ['label' => '🌤️ Meteo',             'colore' => '#3b82f6'],
    'info'      => ['label' => 'ℹ️ Info / Avviso',     'colore' => '#10b981'],
];

$presets = [
    ''         => ['label' => 'Nessuno (usa colore)',   'css' => ''],
    'dark_red' => ['label' => '🔴 Gradient scuro rosso','css' => 'background:linear-gradient(135deg,#000 0%,#1a0000 40%,#8b0000 100%)'],
    'midnight' => ['label' => '🔵 Midnight blue',        'css' => 'background:linear-gradient(135deg,#0a0a1a 0%,#0f3460 100%)'],
    'purple'   => ['label' => '🟣 Deep purple',          'css' => 'background:linear-gradient(135deg,#1a0030 0%,#4a0080 100%)'],
    'forest'   => ['label' => '🟢 Forest dark',          'css' => 'background:linear-gradient(135deg,#001a0a 0%,#004d20 100%)'],
    'gold'     => ['label' => '🟡 Dark gold',            'css' => 'background:linear-gradient(135deg,#1a1200 0%,#4d3800 100%)'],
    'carbon'   => ['label' => '⚫ Carbon',               'css' => 'background:linear-gradient(135deg,#111 0%,#2a2a2a 100%)'],
];

$titolo = 'Layout';
require_once 'includes/header.php';
?>

<div class="container">

<?php if ($msg): [$tm,$txt] = explode('|',$msg); ?>
<div class="messaggio <?php echo $tm; ?>"><?php echo $txt; ?></div>
<?php endif; ?>

<!-- ── SELETTORE PROFILO/CLUB ──────────────────────────────── -->
<?php if (count($profili) > 1): ?>
<div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
    <span style="font-size:13px; color:#aaa;">Club:</span>
    <?php foreach ($profili as $pr): ?>
    <a href="/layout.php?tab=<?php echo $tab; ?>&profilo=<?php echo $pr['id']; ?>"
       style="padding:8px 18px; border-radius:20px; text-decoration:none; font-size:13px; font-weight:bold;
              background:<?php echo $pr['id']==$profilo_id ? '#e94560' : '#0f3460'; ?>;
              color:#fff; border:1px solid <?php echo $pr['id']==$profilo_id ? '#e94560' : '#1a4a7a'; ?>;">
        🎛️ <?php echo htmlspecialchars($pr['nome']); ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── SELEZIONE LAYOUT ─────────────────────────────────── -->
<div class="box" style="margin-bottom:24px;">
    <h2>🖥️ Tipo di Layout</h2>
    <div style="display:flex; gap:16px; margin-top:8px;">
        <div onclick="selezionaLayout('solo_banner')" id="card-solo_banner"
             style="flex:1; padding:20px; border-radius:10px; cursor:pointer;
                    border:2px solid <?php echo $layout_attivo==='solo_banner' ? '#e94560' : '#1a4a7a'; ?>;
                    background:<?php echo $layout_attivo==='solo_banner' ? '#2a0a14' : '#0f3460'; ?>;
                    transition:all 0.2s; text-align:center;">
            <div style="font-size:36px; margin-bottom:8px;">📺</div>
            <div style="font-weight:bold; font-size:15px; color:#fff;">Solo Banner</div>
            <div style="font-size:12px; color:#aaa; margin-top:4px;">Contenuto full + barra info</div>
        </div>
        <div onclick="selezionaLayout('banner_sidebar')" id="card-banner_sidebar"
             style="flex:1; padding:20px; border-radius:10px; cursor:pointer;
                    border:2px solid <?php echo $layout_attivo==='banner_sidebar' ? '#e94560' : '#1a4a7a'; ?>;
                    background:<?php echo $layout_attivo==='banner_sidebar' ? '#2a0a14' : '#0f3460'; ?>;
                    transition:all 0.2s; text-align:center;">
            <div style="font-size:36px; margin-bottom:8px;">📺➕📋</div>
            <div style="font-weight:bold; font-size:15px; color:#fff;">Banner + Banda Laterale</div>
            <div style="font-size:12px; color:#aaa; margin-top:4px;">Contenuto + sidebar con widget</div>
        </div>
    </div>
</div>

<!-- ── TABS ────────────────────────────────────────────── -->
<!-- ── TABS ────────────────────────────────────────────── -->
<div style="display:flex; gap:4px; margin-bottom:20px;" id="tabs-bar">
    <a href="/layout.php?tab=banner&profilo=<?php echo $profilo_id; ?>"
       style="padding:10px 20px; border-radius:6px; text-decoration:none; font-size:14px; font-weight:bold;
              background:<?php echo $tab==='banner' ? '#e94560' : '#0f3460'; ?>; color:#fff;">🎨 Banner</a>
    <?php if ($layout_attivo === 'banner_sidebar'): ?>
    <a href="/layout.php?tab=sidebar&profilo=<?php echo $profilo_id; ?>"
       style="padding:10px 20px; border-radius:6px; text-decoration:none; font-size:14px; font-weight:bold;
              background:<?php echo $tab==='sidebar' ? '#e94560' : '#0f3460'; ?>; color:#fff;">📱 Sidebar Slides</a>
    <?php else: ?>
    <span style="padding:10px 20px; border-radius:6px; font-size:14px; font-weight:bold;
                 background:#1a1a2e; color:#333; cursor:not-allowed;"
          title="Seleziona 'Banner + Banda Laterale' per abilitare">📱 Sidebar Slides</span>
    <?php endif; ?>
</div>

<?php if ($tab === 'banner'): ?>
<!-- ── TAB BANNER ── -->
<div style="display:grid; grid-template-columns:380px 1fr; gap:24px; align-items:start;">

    <div class="box">
        <h2>Impostazioni Banner</h2>
        <form method="POST" enctype="multipart/form-data" id="bannerForm">
            <input type="hidden" name="salva_banner" value="1">
            <input type="hidden" name="logo_attuale" id="logoAttuale" value="<?php echo htmlspecialchars($profilo['logo'] ?? ''); ?>">
            <input type="hidden" name="layout_tipo" id="layout_tipo_hidden" value="<?php echo $layout_attivo; ?>">

            <label>Posizione Banner</label>
            <select name="banner_posizione" id="inp_posizione">
                <option value="bottom" <?php echo ($profilo['banner_posizione']??'bottom')==='bottom'?'selected':''; ?>>Basso</option>
                <option value="top"    <?php echo ($profilo['banner_posizione']??'')==='top'?'selected':''; ?>>Alto</option>
            </select>

            <label>Altezza Banner (px)</label>
            <input type="number" name="banner_altezza" id="inp_altezza"
                   value="<?php echo (int)($profilo['banner_altezza'] ?? 80); ?>" min="40" max="200">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div>
                    <label>Colore Sfondo</label>
                    <input type="color" name="banner_colore" id="inp_colore"
                           value="<?php echo htmlspecialchars($profilo['banner_colore'] ?? '#000000'); ?>"
                           style="width:100%; height:44px; margin-bottom:12px;">
                </div>
                <div>
                    <label>Colore Testo</label>
                    <input type="color" name="banner_testo_colore" id="inp_testo_colore"
                           value="<?php echo htmlspecialchars($profilo['banner_testo_colore'] ?? '#ffffff'); ?>"
                           style="width:100%; height:44px; margin-bottom:12px;">
                </div>
            </div>

            <label>Logo</label>
            <div style="font-size:11px; color:#666; margin-bottom:6px;">
                Dimensione consigliata: <strong style="color:#aaa;">400 × 120 px</strong> (orizzontale)
            </div>
            <input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                   style="background:#0f3460; border:1px solid #1a4a7a; border-radius:6px;
                          color:#eee; padding:8px; width:100%; cursor:pointer; margin-bottom:10px;">

            <div id="logoCurrentWrap" style="display:<?php echo empty($profilo['logo']) ? 'none' : 'flex'; ?>;
                 align-items:center; gap:12px; background:#0f3460; border:1px solid #1a4a7a;
                 border-radius:6px; padding:10px 14px; margin-bottom:16px;">
                <img id="logoCurrentImg" src="<?php echo !empty($profilo['logo']) ? 'assets/img/'.htmlspecialchars($profilo['logo']) : ''; ?>"
                     style="height:36px; object-fit:contain; max-width:120px;">
                <span id="logoCurrentNome" style="flex:1; font-size:13px; color:#aaa; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <?php echo htmlspecialchars($profilo['logo'] ?? ''); ?>
                </span>
                <button type="button" id="btnRimuoviLogo"
                        style="padding:6px 12px; background:transparent; border:1px solid #e94560;
                               border-radius:6px; color:#e94560; font-size:12px; cursor:pointer; flex-shrink:0;">
                    ✕ Rimuovi
                </button>
            </div>

            <button type="submit" class="btn btn-full" style="margin-top:4px;">💾 Salva Layout</button>
        </form>
    </div>

    <!-- PREVIEW -->
    <div class="box">
        <h2>Anteprima Live</h2>
        <div id="previewScreen" style="position:relative; width:100%; aspect-ratio:16/9;
             background:#0f3460; border-radius:8px; overflow:hidden; border:1px solid #1a4a7a; margin-bottom:16px;">
            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); color:#1a3a6e; font-size:14px;">
                📺 Segnale TV
            </div>
            <!-- Sidebar preview -->
            <div id="previewSidebar" style="position:absolute; top:0; right:0; bottom:0; width:20%;
                 background:#111; border-left:1px solid #222;
                 display:<?php echo $layout_attivo==='banner_sidebar' ? 'flex' : 'none'; ?>;
                 flex-direction:column; align-items:center; justify-content:center; gap:6px; overflow:hidden;">
                <div style="font-size:9px; color:#333; letter-spacing:2px; text-transform:uppercase;">Sidebar</div>
                <?php foreach (array_filter($slides, fn($s) => $s['attivo']) as $sl):
                    $ti = $tipi[$sl['tipo']] ?? ['colore'=>'#aaa', 'label'=>$sl['tipo']]; ?>
                <div style="width:80%; padding:3px 6px; border-radius:4px; font-size:8px; text-align:center;
                             background:<?php echo $ti['colore']; ?>22; color:<?php echo $ti['colore']; ?>;">
                    <?php echo $ti['label']; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Banner -->
            <div id="previewBanner" style="position:absolute; left:0; right:0; display:flex; align-items:center; overflow:hidden;">
                <div style="display:flex; align-items:center; flex-shrink:0;">
                    <img id="previewLogoImg"
                         src="<?php echo !empty($profilo['logo']) ? 'assets/img/'.htmlspecialchars($profilo['logo']) : ''; ?>"
                         style="object-fit:contain; flex-shrink:0; display:<?php echo !empty($profilo['logo']) ? 'block' : 'none'; ?>">
                </div>
                <div id="previewSep1" style="width:1px; background:rgba(255,255,255,0.3); align-self:stretch; margin:6px 0; flex-shrink:0;"></div>
                <div id="previewData" style="flex:1; text-align:center; font-weight:500; letter-spacing:1px; white-space:nowrap; overflow:hidden;">
                    <?php
                        $gg = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
                        $mm = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
                        echo $gg[date('w')] . ' ' . date('j') . ' ' . $mm[date('n')-1] . ' ' . date('Y');
                    ?>
                </div>
                <div id="previewSep2" style="width:1px; background:rgba(255,255,255,0.3); align-self:stretch; margin:6px 0; flex-shrink:0;"></div>
                <div id="previewOra" style="font-weight:bold; flex-shrink:0; text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap;">--:--:--</div>
            </div>
        </div>
        <div style="font-size:12px; color:#666;">Il banner è visibile su tutti i dispositivi, anche durante la pubblicità.</div>
    </div>
</div>

<?php else: ?>
<!-- ── TAB SIDEBAR ── -->
<div style="display:grid; grid-template-columns:320px 1fr; gap:24px; align-items:start;">

    <div class="box">
        <h2>+ Nuova Slide</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="azione" value="aggiungi_slide">
            <input type="hidden" name="profilo_id" value="<?php echo $profilo_id; ?>">

            <label>Tipo</label>
            <select name="tipo" id="tipoSel" onchange="aggiornaCampi()">
                <?php foreach ($tipi as $k => $t): ?>
                <option value="<?php echo $k; ?>"><?php echo $t['label']; ?></option>
                <?php endforeach; ?>
            </select>

            <label>Titolo slide</label>
            <input type="text" name="titolo" placeholder="Es. Oggi in palestra...">

            <label>Durata (secondi)</label>
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
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <div><label>Lat (opz.)</label><input type="text" name="mt_lat" placeholder="45.4384"></div>
                    <div><label>Lon (opz.)</label><input type="text" name="mt_lon" placeholder="10.9916"></div>
                </div>
            </div>

            <div id="campi-info" style="display:none;">
                <label>Icona emoji</label>
                <input type="text" name="info_icona" value="ℹ️" style="width:80px;">
                <label>Testo</label>
                <textarea name="info_testo" rows="3"
                    style="width:100%; padding:10px 12px; background:#0f3460; border:1px solid #1a4a7a;
                           border-radius:6px; color:#eee; font-size:14px; resize:vertical;"></textarea>
            </div>

            <div style="border-top:1px solid #1a4a7a; margin-top:14px; padding-top:14px;">
                <label style="font-size:11px; color:#aaa; letter-spacing:1px; text-transform:uppercase;">Aspetto Slide</label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">
                    <div>
                        <label>Colore sfondo</label>
                        <input type="color" name="colore_sfondo" value="#111111" style="width:100%; height:40px; padding:2px; cursor:pointer;">
                    </div>
                    <div>
                        <label>Colore testo</label>
                        <input type="color" name="colore_testo" value="#ffffff" style="width:100%; height:40px; padding:2px; cursor:pointer;">
                    </div>
                </div>

                <label style="margin-top:10px;">Sfondo preset</label>
                <select name="sfondo_preset" id="sfondoPreset">
                    <?php foreach ($presets as $k => $p): ?>
                    <option value="<?php echo $k; ?>"><?php echo $p['label']; ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="presetPreview" style="height:40px; border-radius:6px; margin-top:6px; margin-bottom:10px; background:#0f3460; transition:all 0.3s;"></div>

                <label>Immagine sfondo personalizzata</label>
                <div style="font-size:11px; color:#666; margin-bottom:6px;">
                    Dimensione consigliata: <strong style="color:#aaa;">380 × 1080 px</strong> (verticale)
                </div>
                <input type="file" name="sfondo" accept="image/*"
                       style="background:#0f3460; border:1px solid #1a4a7a; border-radius:6px;
                              color:#eee; padding:8px; width:100%; cursor:pointer;">
                <div id="sfondo-preview" style="display:none; margin-top:8px;">
                    <img id="sfondo-img" style="width:100%; border-radius:6px; max-height:80px; object-fit:cover;">
                </div>
            </div>

            <button type="submit" class="btn btn-full" style="margin-top:14px;">+ Aggiungi Slide</button>
        </form>
    </div>

    <div>
        <div class="box">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h2 style="margin:0;">Slide configurate (<?php echo count($slides); ?>)</h2>
                <span style="font-size:12px; color:#aaa;">Trascina ⠿ per riordinare</span>
            </div>
            <?php if (empty($slides)): ?>
                <div class="vuoto">Nessuna slide. Aggiungine una.</div>
            <?php else: ?>
            <div id="lista-slides">
            <?php foreach ($slides as $sl):
                $cfg = json_decode($sl['contenuto'], true) ?: [];
                $ti  = $tipi[$sl['tipo']] ?? ['label'=>$sl['tipo'], 'colore'=>'#aaa'];
                $hasPreset = !empty($sl['sfondo_preset']) && isset($presets[$sl['sfondo_preset']]) && $presets[$sl['sfondo_preset']]['css'];
                $hasSfondo = !empty($sl['sfondo']);
                $bgCss = $hasSfondo
                    ? "background-image:url('/uploads/{$sl['sfondo']}'); background-size:cover; background-position:center;"
                    : ($hasPreset ? $presets[$sl['sfondo_preset']]['css'] : "background:{$sl['colore_sfondo']};");
            ?>
            <div data-id="<?php echo $sl['id']; ?>"
                 style="display:flex; align-items:stretch; border-radius:10px; margin-bottom:10px;
                        overflow:hidden; border:1px solid #1a4a7a; opacity:<?php echo $sl['attivo'] ? '1' : '0.4'; ?>">
                <div style="width:70px; flex-shrink:0; position:relative; <?php echo $bgCss; ?>">
                    <div style="position:absolute; bottom:4px; left:0; right:0; text-align:center;
                                font-size:10px; color:#fff; text-shadow:0 1px 3px rgba(0,0,0,0.9); font-weight:bold;">
                        <?php echo $sl['durata']; ?>s
                    </div>
                </div>
                <div style="flex:1; padding:10px 14px; background:#0f3460; display:flex; flex-direction:column; justify-content:center; gap:4px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="drag-handle" style="color:#1a4a7a; cursor:grab; font-size:18px; user-select:none;">⠿</span>
                        <span style="font-size:11px; font-weight:bold; padding:2px 8px; border-radius:20px;
                                     background:<?php echo $ti['colore']; ?>22; color:<?php echo $ti['colore']; ?>;">
                            <?php echo $ti['label']; ?>
                        </span>
                        <?php if ($sl['titolo']): ?>
                        <span style="font-size:13px; font-weight:bold; color:#fff;"><?php echo htmlspecialchars($sl['titolo']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:11px; color:#aaa; padding-left:26px;">
                        <?php if ($sl['tipo']==='countdown' && !empty($cfg['data_target'])): ?>📅 <?php echo date('d/m/Y H:i', strtotime($cfg['data_target']));
                        elseif ($sl['tipo']==='meteo' && !empty($cfg['citta'])): ?>📍 <?php echo htmlspecialchars($cfg['citta']);
                        elseif ($sl['tipo']==='info' && !empty($cfg['testo'])): ?><?php echo htmlspecialchars($cfg['icona']??''); ?> <?php echo htmlspecialchars(mb_substr($cfg['testo'],0,50));
                        elseif ($sl['tipo']==='corsi'): ?>Corsi dal Google Sheet<?php endif; ?>
                        <?php if ($hasPreset): ?> · 🎨 <?php echo $presets[$sl['sfondo_preset']]['label']; ?><?php endif; ?>
                        <?php if ($hasSfondo): ?> · 🖼️ Immagine caricata<?php endif; ?>
                    </div>
                </div>
                <div style="display:flex; flex-direction:column; background:#081428; padding:6px; gap:4px; align-items:center; justify-content:center;">
                    <button onclick="toggleAttivo(<?php echo $sl['id']; ?>, <?php echo $sl['attivo']?0:1; ?>)"
                            class="btn btn-sm"
                            style="font-size:10px; padding:3px 7px; background:<?php echo $sl['attivo'] ? '#10b981' : '#374151'; ?>;">
                        <?php echo $sl['attivo'] ? '● ON' : '○ OFF'; ?>
                    </button>
                    <a href="/layout.php?elimina_slide=<?php echo $sl['id']; ?>&tab=sidebar"
                       class="btn btn-sm btn-danger" onclick="return confirm('Eliminare?')"
                       style="font-size:10px; padding:3px 7px;">✕</a>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Anteprima miniature -->
        <?php $slidesAttive = array_filter($slides, fn($s) => $s['attivo']); ?>
        <?php if (!empty($slidesAttive)): ?>
        <div class="box">
            <h2>👁️ Anteprima Carousel</h2>
            <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:8px;">
            <?php foreach ($slidesAttive as $sl):
                $cfg = json_decode($sl['contenuto'],true) ?: [];
                $ti  = $tipi[$sl['tipo']] ?? ['colore'=>'#aaa'];
                $hasPreset = !empty($sl['sfondo_preset']) && isset($presets[$sl['sfondo_preset']]) && $presets[$sl['sfondo_preset']]['css'];
                $hasSfondo = !empty($sl['sfondo']);
                $bgCss = $hasSfondo
                    ? "background-image:url('/uploads/{$sl['sfondo']}'); background-size:cover; background-position:center;"
                    : ($hasPreset ? $presets[$sl['sfondo_preset']]['css'] : "background:{$sl['colore_sfondo']};");
            ?>
            <div style="flex-shrink:0; width:100px; height:160px; border-radius:8px; overflow:hidden; position:relative;
                        border:1px solid #1a4a7a; <?php echo $bgCss; ?> color:<?php echo $sl['colore_testo']; ?>;">
                <div style="position:absolute; inset:0; background:rgba(0,0,0,0.4);"></div>
                <div style="position:relative; z-index:1; padding:8px; height:100%; display:flex; flex-direction:column; justify-content:space-between;">
                    <div style="font-size:8px; opacity:0.7; text-transform:uppercase; letter-spacing:1px;"><?php echo $ti['label']; ?></div>
                    <div>
                        <?php if ($sl['tipo']==='countdown'): ?><div style="font-size:18px; font-weight:900;">00:00</div>
                        <?php elseif ($sl['tipo']==='meteo'): ?><div style="font-size:24px;">⛅</div><div style="font-size:16px; font-weight:bold;">--°C</div>
                        <?php elseif ($sl['tipo']==='info'): ?><div style="font-size:18px;"><?php echo $cfg['icona']??'ℹ️'; ?></div>
                        <?php elseif ($sl['tipo']==='corsi'): ?><div style="font-size:8px; line-height:1.6; opacity:0.8;">09:00 Yoga<br>10:30 Pilates</div>
                        <?php endif; ?>
                        <?php if ($sl['titolo']): ?>
                        <div style="font-size:9px; font-weight:bold; margin-top:4px; line-height:1.2;"><?php echo htmlspecialchars($sl['titolo']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:9px; opacity:0.5; text-align:right;"><?php echo $sl['durata']; ?>s</div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

</div>

<style>
input[type=datetime-local] {
    width:100%; padding:10px 12px; background:#0f3460 !important;
    border:1px solid #1a4a7a !important; border-radius:6px;
    color:#eee !important; font-size:14px; margin-bottom:12px; cursor:pointer;
}
input[type=datetime-local]::-webkit-calendar-picker-indicator { filter:invert(0.7); cursor:pointer; }
.sortable-ghost { opacity:0.3; background:#1a4a7a !important; border-radius:10px; }
.drag-handle:hover { color:#e94560 !important; }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
// ── SELEZIONE LAYOUT ──────────────────────────────────────
function selezionaLayout(tipo) {
    document.getElementById('layout_tipo_hidden').value = tipo;
    ['solo_banner','banner_sidebar'].forEach(t => {
        const card = document.getElementById('card-' + t);
        card.style.borderColor = t === tipo ? '#e94560' : '#1a4a7a';
        card.style.background  = t === tipo ? '#2a0a14'  : '#0f3460';
    });
    const sidebar = document.getElementById('previewSidebar');
    if (sidebar) sidebar.style.display = tipo === 'banner_sidebar' ? 'flex' : 'none';

    // Aggiorna tab Sidebar Slides (abilita/disabilita)
    const tabsBar = document.getElementById('tabs-bar');
    if (tabsBar) {
        const sidebarTab = tabsBar.children[1];
        if (tipo === 'banner_sidebar') {
            sidebarTab.style.background = '#0f3460';
            sidebarTab.style.color = '#fff';
            sidebarTab.style.cursor = 'pointer';
            sidebarTab.style.opacity = '1';
            sidebarTab.onclick = null;
            sidebarTab.href = `/layout.php?tab=sidebar&profilo=<?php echo $profilo_id; ?>`;
            sidebarTab.tagName === 'SPAN' && (sidebarTab.outerHTML = sidebarTab.outerHTML.replace('<span','<a').replace('</span>','</a>'));
        } else {
            sidebarTab.style.background = '#1a1a2e';
            sidebarTab.style.color = '#333';
            sidebarTab.style.cursor = 'not-allowed';
            sidebarTab.onclick = e => e.preventDefault();
        }
    }

    // Salva subito via AJAX + feedback verde + reload per aggiornare tab
    const fd = new FormData();
    fd.append('salva_layout_tipo', '1');
    fd.append('layout_tipo', tipo);
    fetch('/layout.php', { method:'POST', body:fd }).then(r => r.text()).then(t => {
        if (t === 'ok') {
            // Flash verde rapido poi reload
            const box = document.querySelector('.box');
            if (box) box.style.outline = '2px solid #22c55e';
            setTimeout(() => window.location.href = `/layout.php?tab=banner&profilo=<?php echo $profilo_id; ?>`, 600);
        }
    });
}

function flashSaved(el) {
    if (!el) return;
    const prev = el.style.outline;
    el.style.transition = 'outline 0.1s';
    el.style.outline = '2px solid #22c55e';
    setTimeout(() => el.style.outline = prev || '', 1000);
}

// ── BANNER PREVIEW ────────────────────────────────────────
const previewScreen = document.getElementById('previewScreen');

function aggiornaPreview() {
    if (!previewScreen) return;
    const colore      = document.getElementById('inp_colore')?.value;
    const testoColore = document.getElementById('inp_testo_colore')?.value;
    const altezza     = parseInt(document.getElementById('inp_altezza')?.value) || 80;
    const posizione   = document.getElementById('inp_posizione')?.value;
    if (!colore) return;

    const scaleW = previewScreen.offsetWidth / 1920;
    const altPx  = Math.round(altezza * scaleW);
    const banner = document.getElementById('previewBanner');
    banner.style.backgroundColor = colore;
    banner.style.color   = testoColore;
    banner.style.height  = altPx + 'px';
    banner.style.padding = '0 ' + Math.round(altPx * 0.25) + 'px';
    banner.style.gap     = Math.round(altPx * 0.25) + 'px';
    banner.style.bottom  = posizione === 'bottom' ? '0' : 'auto';
    banner.style.top     = posizione === 'top'    ? '0' : 'auto';
    document.getElementById('previewOra').style.fontSize  = Math.round(altPx * 0.44) + 'px';
    document.getElementById('previewOra').style.color     = testoColore;
    document.getElementById('previewData').style.fontSize = Math.round(altPx * 0.28) + 'px';
    document.getElementById('previewData').style.color    = testoColore;
    const logo = document.getElementById('previewLogoImg');
    if (logo && logo.src && !logo.src.endsWith('/') && logo.style.display !== 'none') {
        logo.style.height = Math.round(altPx * 0.75) + 'px'; logo.style.width = 'auto';
    }
}

function tickOra() {
    const el = document.getElementById('previewOra');
    if (!el) return;
    const now = new Date();
    el.textContent = String(now.getHours()).padStart(2,'0') + ':' +
                     String(now.getMinutes()).padStart(2,'0') + ':' +
                     String(now.getSeconds()).padStart(2,'0');
}
setInterval(tickOra, 1000); tickOra();

const logoInput = document.getElementById('logoInput');
if (logoInput) {
    logoInput.addEventListener('change', function() {
        const file = this.files[0]; if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.getElementById('previewLogoImg');
            img.src = e.target.result; img.style.display = 'block';
            document.getElementById('logoCurrentImg').src = e.target.result;
            document.getElementById('logoCurrentNome').textContent = file.name;
            document.getElementById('logoCurrentWrap').style.display = 'flex';
            aggiornaPreview();
        };
        reader.readAsDataURL(file);
    });
}
const btnRimuovi = document.getElementById('btnRimuoviLogo');
if (btnRimuovi) {
    btnRimuovi.addEventListener('click', function() {
        document.getElementById('logoAttuale').value = '';
        logoInput.value = '';
        document.getElementById('logoCurrentWrap').style.display = 'none';
        const img = document.getElementById('previewLogoImg');
        img.style.display = 'none'; img.src = '';
        let h = document.getElementById('rimuoviLogoHidden');
        if (!h) { h = document.createElement('input'); h.type='hidden'; h.name='rimuovi_logo'; h.id='rimuoviLogoHidden'; document.getElementById('bannerForm').appendChild(h); }
        h.value = '1';
        aggiornaPreview();
    });
}
['inp_colore','inp_testo_colore','inp_altezza','inp_posizione'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', aggiornaPreview);
});
window.addEventListener('resize', aggiornaPreview);
aggiornaPreview();

// Flash verde su salva banner
const bannerForm = document.getElementById('bannerForm');
if (bannerForm) {
    bannerForm.addEventListener('submit', () => {
        setTimeout(() => flashSaved(bannerForm.closest('.box')), 300);
    });
}

// ── SIDEBAR FORM ─────────────────────────────────────────
function aggiornaCampi() {
    const tipo = document.getElementById('tipoSel')?.value;
    if (!tipo) return;
    ['countdown','meteo','info'].forEach(t => {
        const el = document.getElementById('campi-' + t);
        if (el) el.style.display = (t === tipo) ? 'block' : 'none';
    });
}
aggiornaCampi();

// Preset preview
const presetMap = <?php
    $pm = [];
    foreach ($presets as $k => $p) $pm[$k] = $p['css'];
    echo json_encode($pm);
?>;
const sfondoPreset = document.getElementById('sfondoPreset');
if (sfondoPreset) {
    sfondoPreset.addEventListener('change', function() {
        const prev = document.getElementById('presetPreview');
        const css  = presetMap[this.value] || '';
        prev.style.cssText = (css ? css : 'background:#0f3460') + '; border-radius:6px; height:40px;';
    });
}

// Sfondo file preview
const sfondoInput = document.querySelector('input[name=sfondo]');
if (sfondoInput) {
    sfondoInput.addEventListener('change', function() {
        const file = this.files[0]; if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('sfondo-img').src = e.target.result;
            document.getElementById('sfondo-preview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    });
}

// Toggle attivo
function toggleAttivo(id, val) {
    const fd = new FormData();
    fd.append('azione', 'toggle_attivo'); fd.append('id', id); fd.append('attivo', val);
    fetch('/layout.php', { method:'POST', body:fd }).then(() => location.reload());
}

// Drag & drop
const lista = document.getElementById('lista-slides');
if (lista) {
    Sortable.create(lista, {
        handle: '.drag-handle', animation: 150, ghostClass: 'sortable-ghost',
        onEnd: function() {
            const ids = [...lista.querySelectorAll('[data-id]')].map(el => el.dataset.id);
            const fd = new FormData();
            fd.append('azione', 'riordina'); fd.append('ordine', JSON.stringify(ids));
            fetch('/layout.php', { method:'POST', body:fd }).then(r => r.text()).then(t => {
                if (t === 'ok') { lista.style.outline = '2px solid #22c55e'; setTimeout(() => lista.style.outline='', 800); }
            });
        }
    });
}

document.querySelectorAll('input[type=datetime-local]').forEach(input => {
    input.addEventListener('click', function() { try { this.showPicker(); } catch(e) {} });
});
</script>

<?php require_once 'includes/footer.php'; ?>