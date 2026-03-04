<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();

$messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campi = ['banner_colore', 'banner_testo_colore', 'banner_posizione', 'banner_altezza', 'banner_testo'];
    foreach ($campi as $campo) {
        if (isset($_POST[$campo])) {
            $stmt = $db->prepare('INSERT OR REPLACE INTO impostazioni (chiave, valore) VALUES (?, ?)');
            $stmt->execute([$campo, $_POST[$campo]]);
        }
    }

    if (!empty($_FILES['logo']['name'])) {
        $file       = $_FILES['logo'];
        $estensione = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($estensione, ['jpg', 'jpeg', 'png', 'svg', 'webp'])) {
            $filename     = 'logo.' . $estensione;
            $destinazione = __DIR__ . '/assets/img/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $destinazione)) {
                $stmt = $db->prepare('INSERT OR REPLACE INTO impostazioni (chiave, valore) VALUES (?, ?)');
                $stmt->execute(['logo', $filename]);
                $messaggio = 'ok|Impostazioni salvate!';
            }
        } else {
            $messaggio = 'errore|Formato logo non supportato. Usa JPG, PNG o SVG.';
        }
    } else {
        $messaggio = 'ok|Impostazioni salvate!';
    }
}

$impostazioni = [];
$rows = $db->query('SELECT chiave, valore FROM impostazioni')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    $impostazioni[$row['chiave']] = $row['valore'];
}

$logo                = $impostazioni['logo'] ?? '';
$banner_colore       = $impostazioni['banner_colore'] ?? '#000000';
$banner_testo_colore = $impostazioni['banner_testo_colore'] ?? '#ffffff';
$banner_posizione    = $impostazioni['banner_posizione'] ?? 'bottom';
$banner_altezza      = $impostazioni['banner_altezza'] ?? '60';
$banner_testo        = $impostazioni['banner_testo'] ?? '';

$titolo = 'Layout Banner';
require_once 'includes/header.php';
?>

<div class="container" style="display:grid; grid-template-columns:380px 1fr; gap:24px;">

    <!-- Colonna sinistra -->
    <div>
        <?php if ($messaggio):
            [$tipo_msg, $testo_msg] = explode('|', $messaggio);
        ?>
        <div class="messaggio <?php echo $tipo_msg; ?>"><?php echo $testo_msg; ?></div>
        <?php endif; ?>

        <div class="box">
            <h2>Impostazioni Banner</h2>
            <form method="POST" enctype="multipart/form-data">

                <label>Logo (JPG, PNG, SVG)</label>
                <?php if ($logo): ?>
                    <div style="margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                        <img src="/assets/img/<?php echo $logo; ?>"
                             style="height:36px; background:#0f3460; padding:4px; border-radius:4px; object-fit:contain;">
                        <span style="font-size:12px; color:#aaa;">Logo attuale</span>
                    </div>
                <?php endif; ?>
                <input type="file" name="logo" accept="image/*">

                <label>Colore sfondo banner</label>
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                    <input type="color" name="banner_colore" id="banner_colore"
                           value="<?php echo $banner_colore; ?>"
                           oninput="aggiornaAnteprima()">
                    <span style="font-size:13px; color:#aaa;">Colore di sfondo</span>
                </div>

                <label>Colore testo</label>
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                    <input type="color" name="banner_testo_colore" id="banner_testo_colore"
                           value="<?php echo $banner_testo_colore; ?>"
                           oninput="aggiornaAnteprima()">
                    <span style="font-size:13px; color:#aaa;">Colore ora e data</span>
                </div>

                <label>Posizione banner</label>
                <select name="banner_posizione" id="banner_posizione" onchange="aggiornaAnteprima()">
                    <option value="bottom" <?php echo $banner_posizione === 'bottom' ? 'selected' : ''; ?>>In basso</option>
                    <option value="top" <?php echo $banner_posizione === 'top' ? 'selected' : ''; ?>>In alto</option>
                </select>

                <div style="margin-bottom:16px;">
                    <label>
                        Altezza banner su TV 1920×1080 —
                        <span style="color:#e94560; font-weight:bold;" id="altezza_label"><?php echo $banner_altezza; ?>px</span>
                    </label>
                    <input type="range" name="banner_altezza" id="banner_altezza"
                           value="<?php echo $banner_altezza; ?>"
                           min="40" max="200"
                           oninput="aggiornaAnteprima()">
                    <div style="display:flex; justify-content:space-between; font-size:11px; color:#555;">
                        <span>40px</span><span>200px</span>
                    </div>
                </div>

                <label>Testo extra (opzionale)</label>
                <input type="text" name="banner_testo" id="banner_testo"
                       value="<?php echo htmlspecialchars($banner_testo); ?>"
                       placeholder="Es. Benvenuti in palestra!"
                       oninput="aggiornaAnteprima()">

                <button type="submit" class="btn btn-full">💾 Salva impostazioni</button>
            </form>
        </div>
    </div>

    <!-- Colonna destra: anteprima -->
    <div>
        <div class="box">
            <h2>Anteprima in tempo reale</h2>
            <p style="font-size:13px; color:#aaa; margin-bottom:12px;">Simulazione fedele su TV 1920×1080</p>
            <div style="width:100%; aspect-ratio:16/9; position:relative;">
                <div id="tvPreview" style="width:100%; height:100%; background:#000; border-radius:10px;
                          position:relative; overflow:hidden; border:3px solid #0f3460;
                          box-shadow:0 0 40px rgba(0,0,0,0.5);">
                    <div style="position:absolute; inset:0; display:flex; align-items:center;
                                justify-content:center; background:linear-gradient(135deg,#1a1a2e,#0f3460);">
                        <span style="color:#555; font-size:14px;">[ Segnale TV / Pubblicità ]</span>
                    </div>
                    <div id="bannerPreview" style="position:absolute; left:0; right:0;
                              display:flex; align-items:center; overflow:hidden; transition:height 0.15s;">
                        <?php if ($logo): ?>
                            <img src="/assets/img/<?php echo $logo; ?>"
                                 id="logoPreview"
                                 style="object-fit:contain; width:auto; flex-shrink:0;">
                        <?php else: ?>
                            <span id="logoPlaceholder"
                                  style="color:rgba(255,255,255,0.4); border:1px dashed rgba(255,255,255,0.3);
                                         padding:4px 10px; border-radius:4px; white-space:nowrap;">LOGO</span>
                        <?php endif; ?>
                        <span id="testoExtra" style="opacity:0.75;"></span>
                        <div style="flex:1;"></div>
                        <div id="datetimePreview" style="text-align:right; line-height:1.3; flex-shrink:0;">
                            <div id="oraPreview" style="font-weight:bold; letter-spacing:2px;">--:--:--</div>
                            <div id="dataPreview" style="opacity:0.8;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="font-size:12px; color:#555; margin-top:10px; text-align:center;">
                📐 L'anteprima scala proporzionalmente a 1920×1080px
            </div>
        </div>
    </div>

</div>

<script>
const GIORNI = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
const MESI   = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
const TV_H   = 1080;

function aggiornaOrologio() {
    const now  = new Date();
    const ora  = String(now.getHours()).padStart(2,'0') + ':' +
                 String(now.getMinutes()).padStart(2,'0') + ':' +
                 String(now.getSeconds()).padStart(2,'0');
    const data = GIORNI[now.getDay()] + ' ' + now.getDate() + ' ' +
                 MESI[now.getMonth()] + ' ' + now.getFullYear();
    document.getElementById('oraPreview').textContent  = ora;
    document.getElementById('dataPreview').textContent = data;
}

function aggiornaAnteprima() {
    const colore       = document.getElementById('banner_colore').value;
    const testoColore  = document.getElementById('banner_testo_colore').value;
    const posizione    = document.getElementById('banner_posizione').value;
    const altezzaReale = parseInt(document.getElementById('banner_altezza').value);
    const testo        = document.getElementById('banner_testo').value;

    document.getElementById('altezza_label').textContent = altezzaReale + 'px';

    const tvEl  = document.getElementById('tvPreview');
    const scala = tvEl.offsetHeight / TV_H;
    const alt   = Math.round(altezzaReale * scala);

    const banner = document.getElementById('bannerPreview');
    banner.style.backgroundColor = colore;
    banner.style.color           = testoColore;
    banner.style.height          = alt + 'px';
    banner.style.padding         = '0 ' + Math.round(alt * 0.3) + 'px';
    banner.style.gap             = Math.round(alt * 0.2) + 'px';

    if (posizione === 'bottom') {
        banner.style.bottom = '0';
        banner.style.top    = 'auto';
    } else {
        banner.style.top    = '0';
        banner.style.bottom = 'auto';
    }

    const testoEl = document.getElementById('testoExtra');
    testoEl.textContent    = testo;
    testoEl.style.fontSize = Math.round(alt * 0.22) + 'px';

    document.getElementById('oraPreview').style.fontSize  = Math.round(alt * 0.42) + 'px';
    document.getElementById('dataPreview').style.fontSize = Math.round(alt * 0.20) + 'px';
    document.getElementById('datetimePreview').style.color = testoColore;

    const logo = document.getElementById('logoPreview');
    if (logo) {
        logo.style.height  = Math.round(alt * 0.78) + 'px';
        logo.style.width   = 'auto';
        logo.style.maxWidth = 'none';
    }

    const placeholder = document.getElementById('logoPlaceholder');
    if (placeholder) {
        placeholder.style.fontSize = Math.round(alt * 0.30) + 'px';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    aggiornaAnteprima();
    aggiornaOrologio();
    setInterval(aggiornaOrologio, 1000);
});

window.addEventListener('resize', aggiornaAnteprima);
</script>

<?php require_once 'includes/footer.php'; ?>