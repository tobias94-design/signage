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

    // Gestione logo — applicato a tutti i profili
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

    // Salva su TUTTI i profili
    $stmt = $db->prepare('UPDATE profili SET
        banner_colore=?, banner_testo_colore=?, banner_posizione=?,
        banner_altezza=?, logo=?');
    $stmt->execute([$colore, $testo_colore, $posizione, $altezza, $logo]);
    $msg = 'Salvato su tutti i profili!';
}

// Leggi impostazioni dal primo profilo (sono uguali per tutti)
$profilo = $db->query('SELECT * FROM profili LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?? [];

require_once 'includes/header.php';
?>

<style>
.layout-wrap {
    display: flex;
    gap: 40px;
    align-items: flex-start;
    max-width: 1200px;
}

.layout-form {
    background: #1a1a1a;
    border-radius: 12px;
    padding: 28px;
    width: 380px;
    flex-shrink: 0;
    border: 1px solid #2a2a2a;
}

.layout-form h2 {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 24px;
    letter-spacing: 1px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 12px;
    color: #888;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.form-group input[type=text],
.form-group input[type=number],
.form-group select {
    width: 100%;
    background: #111;
    border: 1px solid #333;
    border-radius: 6px;
    color: #fff;
    padding: 10px 14px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus { border-color: #e94560; }

.form-group input[type=color] {
    width: 100%;
    height: 44px;
    background: #111;
    border: 1px solid #333;
    border-radius: 6px;
    cursor: pointer;
    padding: 2px;
}

.color-row { display: flex; gap: 12px; }
.color-row .form-group { flex: 1; }

/* Logo upload area */
.logo-upload-area {
    border: 2px dashed #333;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s;
    position: relative;
}
.logo-upload-area:hover { border-color: #e94560; }
.logo-upload-area input[type=file] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
}
.logo-upload-area .upload-icon { font-size: 28px; margin-bottom: 6px; }
.logo-upload-area .upload-txt  { font-size: 13px; color: #888; }

.logo-current {
    margin-top: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    background: #111;
    border-radius: 8px;
    padding: 10px 14px;
    border: 1px solid #2a2a2a;
}
.logo-current img {
    height: 36px;
    object-fit: contain;
}
.logo-current .logo-nome {
    flex: 1;
    font-size: 13px;
    color: #aaa;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.btn-remove-logo {
    background: transparent;
    border: 1px solid #444;
    color: #aaa;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.2s;
}
.btn-remove-logo:hover { background: #e94560; border-color: #e94560; color: #fff; }

.btn-salva {
    width: 100%;
    padding: 13px;
    background: #e94560;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    letter-spacing: 1px;
    margin-top: 4px;
    transition: background 0.2s;
}
.btn-salva:hover { background: #c73652; }

.msg-ok {
    background: #0a3a1a;
    border: 1px solid #1a6a3a;
    color: #4ade80;
    padding: 10px 16px;
    border-radius: 6px;
    margin-bottom: 18px;
    font-size: 14px;
}

/* Preview */
.preview-wrap { flex: 1; }
.preview-wrap h3 {
    font-size: 13px;
    color: #888;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 14px;
}

.preview-screen {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    background: #111;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #2a2a2a;
}

.preview-tv-content {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2a2a2a;
    font-size: 16px;
}

.preview-banner {
    position: absolute;
    left: 0; right: 0;
    display: flex;
    align-items: center;
    overflow: hidden;
}

.preview-sep {
    width: 1px;
    background: rgba(255,255,255,0.3);
    align-self: stretch;
    margin: 8px 0;
    flex-shrink: 0;
}

.preview-logo-img {
    object-fit: contain;
    display: none;
    flex-shrink: 0;
}

.preview-data {
    flex: 1;
    text-align: center;
    font-weight: 500;
    letter-spacing: 1px;
    white-space: nowrap;
    overflow: hidden;
}

.preview-ora {
    font-weight: bold;
    flex-shrink: 0;
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.info-struttura {
    margin-top: 16px;
    background: #1a1a1a;
    border-radius: 8px;
    padding: 16px;
    border: 1px solid #2a2a2a;
    font-size: 13px;
    color: #888;
    line-height: 1.8;
}
.info-struttura strong { color: #fff; }
.sep-demo {
    display: inline-block;
    width: 1px;
    height: 12px;
    background: #444;
    vertical-align: middle;
    margin: 0 8px;
}
</style>

<div class="container-fluid py-4">
    <h1 class="mb-4" style="font-size:24px;font-weight:700;color:#fff;">Layout Banner</h1>

    <div class="layout-wrap">
        <!-- FORM -->
        <div class="layout-form">
            <?php if($msg): ?>
                <div class="msg-ok">✓ <?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <h2>Impostazioni Banner</h2>

            <form method="POST" enctype="multipart/form-data" id="bannerForm">
                <input type="hidden" name="salva_banner" value="1">
                <input type="hidden" name="logo_attuale" id="logoAttuale"
                       value="<?= htmlspecialchars($profilo['logo'] ?? '') ?>">

                <div class="form-group">
                    <label>Posizione Banner</label>
                    <select name="banner_posizione" id="inp_posizione">
                        <option value="bottom" <?= ($profilo['banner_posizione']??'bottom')==='bottom'?'selected':'' ?>>Basso</option>
                        <option value="top"    <?= ($profilo['banner_posizione']??'')==='top'?'selected':'' ?>>Alto</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Altezza Banner (px)</label>
                    <input type="number" name="banner_altezza" id="inp_altezza"
                           value="<?= (int)($profilo['banner_altezza'] ?? 80) ?>"
                           min="40" max="200">
                </div>

                <div class="color-row">
                    <div class="form-group">
                        <label>Sfondo</label>
                        <input type="color" name="banner_colore" id="inp_colore"
                               value="<?= htmlspecialchars($profilo['banner_colore'] ?? '#000000') ?>">
                    </div>
                    <div class="form-group">
                        <label>Testo</label>
                        <input type="color" name="banner_testo_colore" id="inp_testo_colore"
                               value="<?= htmlspecialchars($profilo['banner_testo_colore'] ?? '#ffffff') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Logo</label>

                    <!-- Area upload -->
                    <div class="logo-upload-area" id="logoUploadArea">
                        <input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg,image/svg+xml,image/webp">
                        <div class="upload-icon">🖼️</div>
                        <div class="upload-txt">Clicca per caricare<br><small>PNG, SVG, JPG, WEBP</small></div>
                    </div>

                    <!-- Logo attuale -->
                    <div class="logo-current" id="logoCurrentWrap"
                         style="<?= empty($profilo['logo']) ? 'display:none' : '' ?>">
                        <img id="logoCurrentImg"
                             src="<?= !empty($profilo['logo']) ? 'assets/img/'.htmlspecialchars($profilo['logo']) : '' ?>"
                             alt="logo">
                        <span class="logo-nome" id="logoCurrentNome">
                            <?= htmlspecialchars($profilo['logo'] ?? '') ?>
                        </span>
                        <button type="button" class="btn-remove-logo" id="btnRimuoviLogo">
                            ✕ Rimuovi
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-salva">💾 Salva Impostazioni</button>
            </form>
        </div>

        <!-- PREVIEW -->
        <div class="preview-wrap">
            <h3>Anteprima Live</h3>
            <div class="preview-screen" id="previewScreen">
                <div class="preview-tv-content">📺 Segnale TV</div>
                <div class="preview-banner" id="previewBanner">
                    <div style="display:flex;align-items:center;flex-shrink:0;" id="previewLogoWrap">
                        <img class="preview-logo-img" id="previewLogoImg"
                             src="<?= !empty($profilo['logo']) ? 'assets/img/'.htmlspecialchars($profilo['logo']) : '' ?>"
                             style="<?= !empty($profilo['logo']) ? 'display:block' : 'display:none' ?>">
                    </div>
                    <div class="preview-sep" id="previewSep1"></div>
                    <div class="preview-data" id="previewData">Sabato 7 Marzo 2026</div>
                    <div class="preview-sep" id="previewSep2"></div>
                    <div class="preview-ora" id="previewOra">--:--:--</div>
                </div>
            </div>

            <div class="info-struttura">
                <strong>Struttura banner:</strong><br>
                <span style="color:#e94560;">Logo</span>
                <span class="sep-demo"></span>
                Data (centro)
                <span class="sep-demo"></span>
                Ora HH:MM:SS
                <br><br>
                <span style="color:#aaa;">Il banner è identico per tutti i profili ed è sempre visibile, anche durante la pubblicità.</span>
            </div>
        </div>
    </div>
</div>

<script>
const screen = document.getElementById('previewScreen');

// ── AGGIORNA PREVIEW ──
function aggiornaPreview() {
    const colore      = document.getElementById('inp_colore').value;
    const testoColore = document.getElementById('inp_testo_colore').value;
    const altezza     = parseInt(document.getElementById('inp_altezza').value) || 80;
    const posizione   = document.getElementById('inp_posizione').value;

    const scaleW = screen.offsetWidth / 1920;
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

// ── OROLOGIO PREVIEW ──
function tickOra() {
    const now = new Date();
    document.getElementById('previewOra').textContent =
        String(now.getHours()).padStart(2,'0') + ':' +
        String(now.getMinutes()).padStart(2,'0') + ':' +
        String(now.getSeconds()).padStart(2,'0');
}
setInterval(tickOra, 1000);
tickOra();

// ── LOGO UPLOAD: preview immediata ──
document.getElementById('logoInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        // Aggiorna preview banner
        const img = document.getElementById('previewLogoImg');
        img.src           = e.target.result;
        img.style.display = 'block';

        // Aggiorna logo attuale nel form
        document.getElementById('logoCurrentImg').src   = e.target.result;
        document.getElementById('logoCurrentNome').textContent = file.name;
        document.getElementById('logoCurrentWrap').style.display = 'flex';

        aggiornaPreview();
    };
    reader.readAsDataURL(file);
});

// ── RIMUOVI LOGO ──
document.getElementById('btnRimuoviLogo').addEventListener('click', function() {
    document.getElementById('logoAttuale').value         = '';
    document.getElementById('logoInput').value           = '';
    document.getElementById('logoCurrentWrap').style.display = 'none';
    document.getElementById('previewLogoImg').style.display  = 'none';
    document.getElementById('previewLogoImg').src             = '';

    // Aggiungi campo hidden per segnalare rimozione
    let h = document.getElementById('rimuoviLogoHidden');
    if (!h) {
        h = document.createElement('input');
        h.type = 'hidden'; h.name = 'rimuovi_logo'; h.id = 'rimuoviLogoHidden';
        document.getElementById('bannerForm').appendChild(h);
    }
    h.value = '1';
    aggiornaPreview();
});

// ── LISTENERS ──
['inp_colore','inp_testo_colore','inp_altezza','inp_posizione'].forEach(id => {
    document.getElementById(id).addEventListener('input', aggiornaPreview);
});
window.addEventListener('resize', aggiornaPreview);
aggiornaPreview();
</script>

<?php require_once 'includes/footer.php'; ?>