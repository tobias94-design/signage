<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
$db = getDB();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salva_banner'])) {
    $colore       = $_POST['banner_colore']       ?? '#000000';
    $testo_colore = $_POST['banner_testo_colore'] ?? '#ffffff';
    $posizione    = $_POST['banner_posizione']    ?? 'bottom';
    $altezza      = (int)($_POST['banner_altezza'] ?? 80);

    $logo_attuale = $_POST['logo_attuale'] ?? '';
    $logo         = $logo_attuale;

    if (!empty($_FILES['logo']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['png','jpg','jpeg','svg','webp'];
        if (in_array($ext, $allowed)) {
            $nome = 'logo_global.' . $ext;
            $dest = __DIR__ . '/assets/img/' . $nome;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                $logo = $nome;
            }
        }
    }

    if (isset($_POST['rimuovi_logo'])) $logo = '';

    $stmt = $db->prepare('UPDATE profili SET
        banner_colore=?, banner_testo_colore=?, banner_posizione=?,
        banner_altezza=?, logo=?');
    $stmt->execute([$colore, $testo_colore, $posizione, $altezza, $logo]);
    $msg = 'ok|Salvato su tutti i profili!';
}

$profilo = $db->query('SELECT * FROM profili LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?? [];

$titolo = 'Layout';
require_once 'includes/header.php';
?>

<div class="container">

    <?php if ($msg):
        [$tipo_msg, $testo_msg] = explode('|', $msg);
    ?>
    <div class="messaggio <?php echo $tipo_msg; ?>"><?php echo $testo_msg; ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:380px 1fr; gap:24px; align-items:start;">

        <!-- FORM -->
        <div>
            <div class="box">
                <h2>Impostazioni Banner</h2>

                <form method="POST" enctype="multipart/form-data" id="bannerForm">
                    <input type="hidden" name="salva_banner" value="1">
                    <input type="hidden" name="logo_attuale" id="logoAttuale"
                           value="<?= htmlspecialchars($profilo['logo'] ?? '') ?>">

                    <label>Posizione Banner</label>
                    <select name="banner_posizione" id="inp_posizione">
                        <option value="bottom" <?= ($profilo['banner_posizione']??'bottom')==='bottom'?'selected':'' ?>>Basso</option>
                        <option value="top"    <?= ($profilo['banner_posizione']??'')==='top'?'selected':'' ?>>Alto</option>
                    </select>

                    <label>Altezza Banner (px)</label>
                    <input type="number" name="banner_altezza" id="inp_altezza"
                           value="<?= (int)($profilo['banner_altezza'] ?? 80) ?>"
                           min="40" max="200">

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <label>Colore Sfondo</label>
                            <input type="color" name="banner_colore" id="inp_colore"
                                   value="<?= htmlspecialchars($profilo['banner_colore'] ?? '#000000') ?>"
                                   style="width:100%; height:44px; margin-bottom:12px;">
                        </div>
                        <div>
                            <label>Colore Testo</label>
                            <input type="color" name="banner_testo_colore" id="inp_testo_colore"
                                   value="<?= htmlspecialchars($profilo['banner_testo_colore'] ?? '#ffffff') ?>"
                                   style="width:100%; height:44px; margin-bottom:12px;">
                        </div>
                    </div>

                    <label>Logo</label>
                    <div id="logoUploadArea" style="
                        border: 2px dashed #1a4a7a;
                        border-radius: 6px;
                        padding: 20px;
                        text-align: center;
                        cursor: pointer;
                        position: relative;
                        background: #0f3460;
                        transition: border-color 0.2s;
                        margin-bottom: 12px;
                    ">
                        <input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                               style="position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; margin:0;">
                        <div style="font-size:24px; margin-bottom:6px;">🖼️</div>
                        <div style="font-size:13px; color:#aaa;">Clicca per caricare<br><small>PNG, SVG, JPG, WEBP</small></div>
                    </div>

                    <div id="logoCurrentWrap" style="
                        <?= empty($profilo['logo']) ? 'display:none;' : '' ?>
                        display:<?= empty($profilo['logo']) ? 'none' : 'flex' ?>;
                        align-items:center; gap:12px;
                        background: #0f3460;
                        border: 1px solid #1a4a7a;
                        border-radius: 6px;
                        padding: 10px 14px;
                        margin-bottom: 16px;
                    ">
                        <img id="logoCurrentImg"
                             src="<?= !empty($profilo['logo']) ? 'assets/img/'.htmlspecialchars($profilo['logo']) : '' ?>"
                             style="height:36px; object-fit:contain;" alt="logo">
                        <span id="logoCurrentNome" style="flex:1; font-size:13px; color:#aaa; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?= htmlspecialchars($profilo['logo'] ?? '') ?>
                        </span>
                        <button type="button" id="btnRimuoviLogo" class="btn btn-sm btn-danger">✕ Rimuovi</button>
                    </div>

                    <button type="submit" class="btn btn-full" style="margin-top:4px;">💾 Salva Impostazioni</button>
                </form>
            </div>
        </div>

        <!-- PREVIEW -->
        <div>
            <div class="box">
                <h2>Anteprima Live</h2>

                <div id="previewScreen" style="
                    position: relative;
                    width: 65%;
                    aspect-ratio: 16 / 9;
                    background: #0f3460;
                    border-radius: 8px;
                    overflow: hidden;
                    border: 1px solid #1a4a7a;
                    margin-bottom: 20px;
                ">
                    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); color:#1a3a6e; font-size:18px;">
                        📺 Segnale TV
                    </div>

                    <div id="previewBanner" style="position:absolute; left:0; right:0; display:flex; align-items:center; overflow:hidden;">
                        <div style="display:flex; align-items:center; flex-shrink:0;">
                            <img id="previewLogoImg"
                                 src="<?= !empty($profilo['logo']) ? 'assets/img/'.htmlspecialchars($profilo['logo']) : '' ?>"
                                 style="object-fit:contain; flex-shrink:0; <?= !empty($profilo['logo']) ? 'display:block' : 'display:none' ?>">
                        </div>
                        <div id="previewSep1" style="width:1px; background:rgba(255,255,255,0.3); align-self:stretch; margin:8px 0; flex-shrink:0;"></div>
                        <div id="previewData" style="flex:1; text-align:center; font-weight:500; letter-spacing:1px; white-space:nowrap; overflow:hidden;">
                            <?php
                                $giorni = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
                                $mesi   = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
                                echo $giorni[date('w')] . ' ' . date('j') . ' ' . $mesi[date('n')-1] . ' ' . date('Y');
                            ?>
                        </div>
                        <div id="previewSep2" style="width:1px; background:rgba(255,255,255,0.3); align-self:stretch; margin:8px 0; flex-shrink:0;"></div>
                        <div id="previewOra" style="font-weight:bold; flex-shrink:0; text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap;">
                            --:--:--
                        </div>
                    </div>
                </div>

                <div style="font-size:13px; color:#aaa; line-height:2;">
                    <strong style="color:#e94560;">Struttura banner:</strong>
                    &nbsp; Logo
                    <span style="display:inline-block; width:1px; height:12px; background:#444; vertical-align:middle; margin:0 8px;"></span>
                    Data (centro)
                    <span style="display:inline-block; width:1px; height:12px; background:#444; vertical-align:middle; margin:0 8px;"></span>
                    Ora HH:MM:SS
                    <br>
                    <span style="color:#666;">Il banner è identico per tutti i profili ed è sempre visibile, anche durante la pubblicità.</span>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const previewScreen = document.getElementById('previewScreen');

function aggiornaPreview() {
    const colore      = document.getElementById('inp_colore').value;
    const testoColore = document.getElementById('inp_testo_colore').value;
    const altezza     = parseInt(document.getElementById('inp_altezza').value) || 80;
    const posizione   = document.getElementById('inp_posizione').value;

    const scaleW = previewScreen.offsetWidth / 1920;
    const altPx  = Math.round(altezza * scaleW);

    const banner = document.getElementById('previewBanner');
    banner.style.backgroundColor = colore;
    banner.style.color            = testoColore;
    banner.style.height           = altPx + 'px';
    banner.style.padding          = '0 ' + Math.round(altPx * 0.25) + 'px';
    banner.style.gap              = Math.round(altPx * 0.25) + 'px';
    banner.style.bottom = posizione === 'bottom' ? '0' : 'auto';
    banner.style.top    = posizione === 'top'    ? '0' : 'auto';

    document.getElementById('previewOra').style.fontSize  = Math.round(altPx * 0.44) + 'px';
    document.getElementById('previewOra').style.color     = testoColore;
    document.getElementById('previewData').style.fontSize = Math.round(altPx * 0.28) + 'px';
    document.getElementById('previewData').style.color    = testoColore;

    const logo = document.getElementById('previewLogoImg');
    if (logo.src && !logo.src.endsWith('/') && logo.style.display !== 'none') {
        logo.style.height = Math.round(altPx * 0.75) + 'px';
        logo.style.width  = 'auto';
    }
}

function tickOra() {
    const now = new Date();
    document.getElementById('previewOra').textContent =
        String(now.getHours()).padStart(2,'0') + ':' +
        String(now.getMinutes()).padStart(2,'0') + ':' +
        String(now.getSeconds()).padStart(2,'0');
}
setInterval(tickOra, 1000);
tickOra();

document.getElementById('logoInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById('previewLogoImg');
        img.src = e.target.result;
        img.style.display = 'block';
        document.getElementById('logoCurrentImg').src = e.target.result;
        document.getElementById('logoCurrentNome').textContent = file.name;
        document.getElementById('logoCurrentWrap').style.display = 'flex';
        aggiornaPreview();
    };
    reader.readAsDataURL(file);
});

document.getElementById('btnRimuoviLogo').addEventListener('click', function() {
    document.getElementById('logoAttuale').value = '';
    document.getElementById('logoInput').value   = '';
    document.getElementById('logoCurrentWrap').style.display = 'none';
    document.getElementById('previewLogoImg').style.display  = 'none';
    document.getElementById('previewLogoImg').src = '';
    let h = document.getElementById('rimuoviLogoHidden');
    if (!h) {
        h = document.createElement('input');
        h.type = 'hidden'; h.name = 'rimuovi_logo'; h.id = 'rimuoviLogoHidden';
        document.getElementById('bannerForm').appendChild(h);
    }
    h.value = '1';
    aggiornaPreview();
});

['inp_colore','inp_testo_colore','inp_altezza','inp_posizione'].forEach(id => {
    document.getElementById(id).addEventListener('input', aggiornaPreview);
});
window.addEventListener('resize', aggiornaPreview);
aggiornaPreview();
</script>

<?php require_once 'includes/footer.php'; ?>