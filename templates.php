<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/widget_catalog.php';

$page_title = 'Template Layout';
$db  = getDB();
$tid = TENANT_ID;
$success = $error = '';
$catalog    = getWidgetCatalog();
$tenantPlan = getTenantPlan($db, $tid);
$streaming_channels = getStreamingChannels($db, $tid);

// ── Embedding sicuro di dati PHP dentro <script> inline ─────────
// json_encode() da solo NON basta quando il testo arriva da input utente
// (es. testo del ticker incollato da Word/Google Docs): puo' contenere
// separatori Unicode invisibili (U+2028/U+2029) che JavaScript legge come
// fine riga, o la sequenza "</script" che chiude il tag prematuramente.
// Questo rompe l'INTERO script della pagina con un errore di sintassi,
// silenziosamente disattivando salvataggio, anteprima, tutto — da qui
// questa funzione, usata ovunque dati PHP entrano in un blocco <script>.
function jsonForScript($data): string {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $json = str_replace(
        ["\xE2\x80\xA8", "\xE2\x80\xA9", '</script', '<!--'],
        ['\u2028', '\u2029', '<\/script', '<\!--'],
        $json
    );
    return $json;
}

// ── Brand Kit helpers ────────────────────────────────────────
function getBrandKit(PDO $db, int $tid): array {
    $stmt = $db->prepare("SELECT chiave, valore FROM impostazioni WHERE tenant_id=? AND chiave LIKE 'brand_%'");
    $stmt->execute([$tid]);
    $kit = [];
    foreach ($stmt->fetchAll() as $row) $kit[$row['chiave']] = $row['valore'];
    return $kit;
}

// ── Canali IPTV salvati ────────────────────────────────────────
function getStreamingChannels(PDO $db, int $tid): array {
    $stmt = $db->prepare("SELECT valore FROM impostazioni WHERE tenant_id=? AND chiave='streaming_channels'");
    $stmt->execute([$tid]);
    $row = $stmt->fetch();
    if ($row) {
        $decoded = json_decode($row['valore'], true);
        if (is_array($decoded)) return $decoded;
    }
    // Default alla prima apertura: la lista fornita da Gymnasium (Cielo e Radio Capital
    // esclusi perche' l'URL era troncato nello screenshot originale)
    return [
        ['nome'=>'Deejay TV', 'url'=>'https://4c4b867c89244861ac216426883d1ad0.msvdn.net/live/S85984808/sMO0tz9Sr2Rk/playlist.m3u8'],
        ['nome'=>'M2O TV', 'url'=>'https://4c4b867c89244861ac216426883d1ad0.msvdn.net/live/S62628868/uhdWBlkC1AoO/playlist.m3u8'],
        ['nome'=>'Radio Kiss Kiss TV', 'url'=>'https://kk.fluid.stream/KKMulti/smil:KissKissTV.smil/playlist.m3u8'],
        ['nome'=>'Radio Zeta', 'url'=>'https://dd782ed59e2a4e86aabf6fc508674b59.msvdn.net/live/S9346184/XEx1LqlYbNic/playlist_video.m3u8'],
        ['nome'=>'Radiofreccia', 'url'=>'https://dd782ed59e2a4e86aabf6fc508674b59.msvdn.net/live/S3160845/0tuSetc8UFkF/playlist_video.m3u8'],
        ['nome'=>'RAI 1', 'url'=>'https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=2606803&output=16'],
        ['nome'=>'RAI 2', 'url'=>'https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=308718&output=16'],
        ['nome'=>'RAI 3', 'url'=>'https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=308709&output=16'],
        ['nome'=>'RAI 4', 'url'=>'https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=746966&output=16'],
        ['nome'=>'RAI 5', 'url'=>'https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=395276&output=16'],
        ['nome'=>'RAI Movie', 'url'=>'https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=747002&output=16'],
        ['nome'=>'RAI News 24', 'url'=>'https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=1&output=16'],
        ['nome'=>'RAI Premium', 'url'=>'https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=746992&output=16'],
        ['nome'=>'RAI Radio 2', 'url'=>'https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=5674080&output=16'],
        ['nome'=>'RAI Scuola', 'url'=>'https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=747011&output=16'],
        ['nome'=>'RAI Sport', 'url'=>'https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=358025&output=16'],
        ['nome'=>'RAI Storia', 'url'=>'https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=746990&output=16'],
    ];
}

// ── Azioni POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_template') {
        $nome = trim($_POST['nome'] ?? '');
        $cw   = (int)($_POST['canvas_w'] ?? 1920);
        $ch   = (int)($_POST['canvas_h'] ?? 1080);
        if ($nome) {
            $db->prepare("INSERT INTO layout_templates (tenant_id,nome,canvas_w,canvas_h,orientamento,creato_il) VALUES (?,?,?,?,?,NOW())")
               ->execute([$tid,$nome,$cw,$ch,$cw>$ch?'landscape':'portrait']);
            $success = "Template \"$nome\" creato.";
        }
    }

    if ($action === 'delete_template') {
        $id = (int)($_POST['template_id'] ?? 0);
        $db->prepare("DELETE FROM layout_layers WHERE template_id=?")->execute([$id]);
        $db->prepare("DELETE FROM layout_templates WHERE id=? AND tenant_id=?")->execute([$id,$tid]);
        $success = 'Template eliminato.';
    }

    if ($action === 'rename_template') {
        $id   = (int)($_POST['template_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if ($id && $nome !== '') {
            $db->prepare("UPDATE layout_templates SET nome=? WHERE id=? AND tenant_id=?")
               ->execute([$nome, $id, $tid]);
            $success = 'Template rinominato.';
        }
    }

    if ($action === 'duplicate_template') {
        $src_id = (int)($_POST['template_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM layout_templates WHERE id=? AND tenant_id=?");
        $stmt->execute([$src_id, $tid]);
        $src = $stmt->fetch();
        if ($src) {
            $nuovo_nome = $src['nome'] . ' (copia)';
            $db->prepare("INSERT INTO layout_templates (tenant_id,nome,canvas_w,canvas_h,orientamento,creato_il) VALUES (?,?,?,?,?,NOW())")
               ->execute([$tid, $nuovo_nome, $src['canvas_w'], $src['canvas_h'], $src['orientamento']]);
            $new_id = $db->lastInsertId();

            $stmt = $db->prepare("SELECT * FROM layout_layers WHERE template_id=?");
            $stmt->execute([$src_id]);
            foreach ($stmt->fetchAll() as $l) {
                $db->prepare("
                    INSERT INTO layout_layers
                    (template_id,nome,widget_type,pos_x,pos_y,width,height,z_index,visible,config,hw_compatibile,ordine,adv_safe,sidebar_fullscreen_on_mobile)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ")->execute([
                    $new_id, $l['nome'], $l['widget_type'], $l['pos_x'], $l['pos_y'],
                    $l['width'], $l['height'], $l['z_index'], $l['visible'],
                    $l['config'], $l['hw_compatibile'], $l['ordine'],
                    $l['adv_safe'], $l['sidebar_fullscreen_on_mobile']
                ]);
            }
            $success = "Template duplicato come \"$nuovo_nome\".";
        }
    }

    if ($action === 'save_layers') {
        $id = (int)($_POST['template_id'] ?? 0);
        $layers = json_decode($_POST['layers'] ?? '[]', true);
        $stmt = $db->prepare("SELECT id FROM layout_templates WHERE id=? AND tenant_id=?");
        $stmt->execute([$id,$tid]);
        if ($stmt->fetch()) {
            $db->prepare("DELETE FROM layout_layers WHERE template_id=?")->execute([$id]);
            foreach ($layers as $i => $l) {
                $db->prepare("INSERT INTO layout_layers (template_id,nome,widget_type,pos_x,pos_y,width,height,z_index,visible,config,ordine,adv_safe) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$id,$l['nome']??'Layer '.($i+1),$l['widget_type']??'info',(int)($l['pos_x']??0),(int)($l['pos_y']??0),(int)($l['width']??400),(int)($l['height']??200),(int)($l['z_index']??$i+1),(int)($l['visible']??1),json_encode($l['config']??[]),$i,(int)($l['adv_safe']??0)]);
            }
            echo json_encode(['ok'=>true]); exit;
        }
        echo json_encode(['ok'=>false]); exit;
    }

    if ($action === 'assign') {
        $tid_t = (int)($_POST['template_id'] ?? 0);
        $did   = (int)($_POST['dispositivo_id'] ?? 0);
        $db->prepare("UPDATE dispositivi SET template_id=? WHERE id=? AND tenant_id=?")->execute([$tid_t,$did,$tid]);
        $success = 'Template assegnato.';
    }

    if ($action === 'save_channels') {
        $channels = json_decode($_POST['channels'] ?? '[]', true) ?: [];
        // Pulizia minima: tieni solo righe con nome e url non vuoti
        $clean = [];
        foreach ($channels as $ch) {
            $nome = trim($ch['nome'] ?? '');
            $url  = trim($ch['url'] ?? '');
            if ($nome !== '' && $url !== '') $clean[] = ['nome'=>$nome, 'url'=>$url];
        }
        $db->prepare("INSERT INTO impostazioni (tenant_id,chiave,valore) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)")
           ->execute([$tid, 'streaming_channels', json_encode($clean)]);
        echo json_encode(['ok'=>true, 'count'=>count($clean)]); exit;
    }

    if ($action === 'save_brand') {
        $keys = ['brand_logo','brand_colore_primario','brand_colore_secondario','brand_colore_accento','brand_font'];
        foreach ($keys as $key) {
            $val = trim($_POST[$key] ?? '');
            if ($val !== '') {
                $db->prepare("INSERT INTO impostazioni (tenant_id,chiave,valore) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)")
                   ->execute([$tid,$key,$val]);
            }
        }
        // Logo upload
        if (!empty($_FILES['brand_logo_file']['name'])) {
            $upload_dir = __DIR__ . '/uploads/brand/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['brand_logo_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg','svg','webp'])) {
                $fname = 'logo_' . $tid . '.' . $ext;
                move_uploaded_file($_FILES['brand_logo_file']['tmp_name'], $upload_dir . $fname);
                $db->prepare("INSERT INTO impostazioni (tenant_id,chiave,valore) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valore=VALUES(valore)")
                   ->execute([$tid,'brand_logo','brand/'.$fname]);
            }
        }
        $success = 'Brand Kit salvato.';
    }
}

// ── Template aperto ──────────────────────────────────────────
$open_tid = (int)($_GET['id'] ?? 0);
$open_template = null;
$open_layers = [];

if ($open_tid) {
    $stmt = $db->prepare("SELECT * FROM layout_templates WHERE id=? AND tenant_id=?");
    $stmt->execute([$open_tid,$tid]);
    $open_template = $stmt->fetch();
    if ($open_template) {
        $stmt = $db->prepare("SELECT * FROM layout_layers WHERE template_id=? ORDER BY z_index");
        $stmt->execute([$open_tid]);
        $open_layers = $stmt->fetchAll();
    }
}

// ── Lista template ───────────────────────────────────────────
$stmt = $db->prepare("SELECT t.*,COUNT(l.id) AS num_layers,COUNT(DISTINCT d.id) AS num_disp FROM layout_templates t LEFT JOIN layout_layers l ON l.template_id=t.id LEFT JOIN dispositivi d ON d.template_id=t.id AND d.tenant_id=t.tenant_id WHERE t.tenant_id=? GROUP BY t.id ORDER BY t.creato_il DESC");
$stmt->execute([$tid]);
$templates = $stmt->fetchAll();

// ── Dispositivi ──────────────────────────────────────────────
$stmt = $db->prepare("SELECT id,nome,club,hw_type,template_id FROM dispositivi WHERE tenant_id=? ORDER BY club,nome");
$stmt->execute([$tid]);
$dispositivi = $stmt->fetchAll();

// ── Contenuti libreria ───────────────────────────────────────
$stmt = $db->prepare("SELECT id,nome,tipo,file FROM contenuti WHERE tenant_id=? ORDER BY nome");
$stmt->execute([$tid]);
$libreria = $stmt->fetchAll();

// ── Brand Kit ────────────────────────────────────────────────
$brand = getBrandKit($db, $tid);
$brand_primario   = $brand['brand_colore_primario']   ?? '#F7192E';
$brand_secondario = $brand['brand_colore_secondario'] ?? '#111111';
$brand_accento    = $brand['brand_colore_accento']    ?? '#ffffff';
$brand_logo       = $brand['brand_logo']              ?? '';
$brand_font       = $brand['brand_font']              ?? 'Inter';

// ── Widget types (dal Catalogo condiviso) ─────────────────────
$widget_types = array_map(fn($w) => ['label'=>$w['label'],'color'=>$w['color'],'icon'=>$w['icon']], $catalog);
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<style>
/* ── Template list ─────────────────────────────────────────── */
.tpl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
.tpl-card{background:var(--surface);border:1px solid var(--outline-var);border-radius:14px;overflow:hidden;transition:all .18s}
.tpl-card:hover{box-shadow:var(--shadow-md);border-color:var(--blue)}
.tpl-preview{height:130px;background:var(--surface-mid);position:relative;display:flex;align-items:center;justify-content:center;overflow:hidden}
.tpl-info{padding:14px 16px;border-bottom:1px solid var(--outline-var)}
.tpl-name{font-size:14px;font-weight:600;color:var(--on-surface);margin-bottom:4px}
.tpl-meta{font-size:11px;color:var(--on-variant);display:flex;gap:8px}
.tpl-actions{padding:10px 16px;display:flex;gap:6px}

/* ── Editor layout ─────────────────────────────────────────── */
.editor-shell{display:grid;grid-template-columns:200px 1fr 280px;height:calc(100vh - 120px);min-height:600px;background:var(--surface);border:1px solid var(--outline-var);border-radius:14px;overflow:hidden}
.panel{display:flex;flex-direction:column;overflow:hidden}
.panel-head{padding:10px 14px;border-bottom:1px solid var(--outline-var);font-size:11px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.05em;display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.panel-body{flex:1;overflow-y:auto;padding:8px}

/* ── Widget palette ────────────────────────────────────────── */
.witem{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:7px;border:1px solid var(--outline-var);background:var(--surface-low);cursor:grab;transition:all .12s;margin-bottom:3px;user-select:none}
.witem:hover{border-color:var(--blue);background:var(--blue-bg)}
.witem.locked{opacity:.45;cursor:not-allowed}
.witem.locked:hover{border-color:var(--outline-var);background:var(--surface-low)}
.wdot{width:22px;height:22px;border-radius:6px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:11px}
.wlabel{font-size:12px;font-weight:500;color:var(--on-surface)}

/* ── Layer list ────────────────────────────────────────────── */
.litem{display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:7px;border:1px solid transparent;cursor:pointer;transition:all .12s;margin-bottom:2px}
.litem:hover{background:var(--surface-mid)}
.litem.active{background:var(--blue-bg);border-color:var(--blue)}
.litem .lname{font-size:12px;font-weight:500;color:var(--on-surface);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
.litem.active .lname{color:var(--blue-text)}
.litem.drag-over{border-top:2px solid var(--blue)}
.litem-eye{font-size:13px;opacity:.5;cursor:pointer;flex-shrink:0}
.litem-eye:hover{opacity:1}

/* ── Canvas area ───────────────────────────────────────────── */
.canvas-area{background:var(--bg);display:flex;align-items:center;justify-content:center;overflow:auto;position:relative;border-left:1px solid var(--outline-var);border-right:1px solid var(--outline-var)}
#canvas{position:relative;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.4)}
.canvas-grid{position:absolute;inset:0;pointer-events:none;background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);background-size:40px 40px;z-index:0}

/* ── Canvas layers ─────────────────────────────────────────── */
.clayer{position:absolute;border:2px solid transparent;cursor:move;box-sizing:border-box;transition:border-color .1s}
.clayer:hover{border-color:rgba(37,120,209,.5)}
.clayer.sel{border-color:#2578D1}
.clayer.sel .rh{display:block}
.clayer-inner{position:relative;width:100%;height:100%;display:flex;align-items:center;justify-content:center;overflow:hidden;pointer-events:none}
.clayer-label{position:absolute;top:-22px;left:-2px;font-size:10px;font-weight:600;color:#fff;background:#2578D1;padding:1px 7px;border-radius:3px 3px 0 0;white-space:nowrap;display:none;pointer-events:none}
.clayer.sel .clayer-label{display:block}

/* Resize handles */
.rh{display:none;position:absolute;width:8px;height:8px;background:#2578D1;border:2px solid #fff;border-radius:2px;z-index:999}
.rh.nw{top:-4px;left:-4px;cursor:nw-resize}
.rh.n {top:-4px;left:50%;transform:translateX(-50%);cursor:n-resize}
.rh.ne{top:-4px;right:-4px;cursor:ne-resize}
.rh.w {top:50%;left:-4px;transform:translateY(-50%);cursor:w-resize}
.rh.e {top:50%;right:-4px;transform:translateY(-50%);cursor:e-resize}
.rh.sw{bottom:-4px;left:-4px;cursor:sw-resize}
.rh.s {bottom:-4px;left:50%;transform:translateX(-50%);cursor:s-resize}
.rh.se{bottom:-4px;right:-4px;cursor:se-resize}

/* Snap guides */
.snap-line{position:absolute;pointer-events:none;z-index:9999;background:#2578D1}
.snap-line.h{height:1px;left:0;right:0}
.snap-line.v{width:1px;top:0;bottom:0}

/* ── Toolbar ───────────────────────────────────────────────── */
.toolbar{position:absolute;top:12px;left:50%;transform:translateX(-50%);display:flex;gap:4px;background:var(--surface);border:1px solid var(--outline-var);border-radius:10px;padding:5px;box-shadow:var(--shadow-md);z-index:20;white-space:nowrap}
.tb{display:flex;align-items:center;gap:4px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;border:none;cursor:pointer;background:none;color:var(--on-variant);transition:all .12s}
.tb:hover{background:var(--surface-mid);color:var(--on-surface)}
.tb.blue{background:var(--blue);color:#fff}
.tb.blue:hover{background:var(--blue-dim)}
.tb svg{width:13px;height:13px}
.tb-sep{width:1px;background:var(--outline-var);margin:0 2px}

/* Zoom */
.zoom-bar{position:absolute;bottom:12px;right:12px;display:flex;align-items:center;gap:4px;background:var(--surface);border:1px solid var(--outline-var);border-radius:8px;padding:3px 8px}
.zb{width:22px;height:22px;border:none;background:none;cursor:pointer;color:var(--on-variant);font-size:15px;border-radius:4px;display:flex;align-items:center;justify-content:center}
.zb:hover{background:var(--surface-mid);color:var(--on-surface)}
.zval{font-size:11px;font-weight:600;color:var(--on-surface);min-width:36px;text-align:center}

/* ── Props panel ───────────────────────────────────────────── */
.prop-section{padding:12px 14px;border-bottom:1px solid var(--outline-var)}
.prop-section-title{font-size:10px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px}
.prop-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px}
.prop-input{width:100%;background:var(--surface-low);border:1px solid var(--outline-var);border-radius:6px;padding:6px 10px;font-size:12px;font-family:'Inter',sans-serif;color:var(--on-surface);outline:none;transition:border-color .15s}
.prop-input:focus{border-color:var(--blue)}
.prop-label{font-size:10px;color:var(--on-variant);margin-bottom:3px;display:block}
.ratio-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:3px;padding:6px 4px;border-radius:6px;border:1px solid var(--outline-var);background:var(--surface-low);font-size:11px;font-weight:600;color:var(--on-variant);cursor:pointer;transition:all .12s}
.ratio-btn:hover{border-color:var(--blue)}
.ratio-btn.active{background:var(--blue-bg);color:var(--blue-text);border-color:var(--blue)}
.ratio-btn span{font-family:monospace;opacity:.7}

/* Preset swatches (galleria stili Orologio, riutilizzabile per altri widget) */
.preset-swatch{border:1px solid var(--outline-var);border-radius:8px;padding:8px;cursor:pointer;transition:all .12s;background:var(--surface-low);text-align:center}
.preset-swatch:hover{border-color:var(--blue)}
.preset-swatch.active{border-color:var(--blue);background:var(--blue-bg);box-shadow:0 0 0 1px var(--blue)}
.preset-mini{border-radius:6px;padding:8px 4px;font-family:'Inter',sans-serif;font-weight:700;font-variant-numeric:tabular-nums;margin-bottom:4px;background:#111}
.preset-swatch-label{font-size:10px;font-weight:600;color:var(--on-variant)}
.preset-swatch.active .preset-swatch-label{color:var(--blue-text)}
.color-row{display:flex;align-items:center;gap:8px;margin-bottom:8px}
.cswatch{width:28px;height:28px;border-radius:5px;border:1px solid var(--outline-var);overflow:hidden;flex-shrink:0;cursor:pointer;position:relative}
.cswatch input{position:absolute;inset:-4px;width:140%;height:140%;cursor:pointer;border:none;padding:0}
.clabel{font-size:12px;color:var(--on-surface);flex:1}
.chex{font-size:10px;color:var(--on-variant);font-family:monospace}

/* Gradient picker */
.grad-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px}
.grad-preview{height:32px;border-radius:6px;border:1px solid var(--outline-var);margin-bottom:8px}
.grad-dir{display:flex;gap:4px;margin-bottom:8px}
.grad-dir-btn{flex:1;padding:4px;border-radius:5px;border:1px solid var(--outline-var);background:var(--surface-low);font-size:10px;font-weight:600;color:var(--on-variant);cursor:pointer;text-align:center}
.grad-dir-btn.active{background:var(--blue-bg);color:var(--blue-text);border-color:var(--blue)}

/* Library picker */
.lib-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;max-height:200px;overflow-y:auto}
.lib-item{aspect-ratio:16/9;border-radius:5px;border:1px solid var(--outline-var);overflow:hidden;cursor:pointer;transition:all .12s;background:var(--surface-mid)}
.lib-item:hover{border-color:var(--blue)}
.lib-item.sel{border-color:var(--blue);box-shadow:0 0 0 2px rgba(37,120,209,.2)}
.lib-item img{width:100%;height:100%;object-fit:cover}
.lib-item-name{font-size:9px;color:var(--on-variant);text-align:center;padding:2px 3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* Brand kit section */
.brand-logo-preview{width:100%;height:50px;object-fit:contain;border-radius:5px;border:1px solid var(--outline-var);margin-bottom:8px;background:var(--surface-mid)}

@keyframes tickerScroll {
  0%   { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}

/* Ticker widget: entra da destra fuori schermo, scorre verso sinistra */
@keyframes tickerScrollRTL {
  0%   { left: 100%; }
  100% { left: -100%; }
}

@keyframes neonPulse {
  0%, 100% { filter: brightness(1); }
  50% { filter: brightness(1.3); }
}

@keyframes flapIn {
  0%   { transform: rotateX(-90deg); opacity: 0; }
  60%  { transform: rotateX(12deg);  opacity: 1; }
  100% { transform: rotateX(0deg);   opacity: 1; }
}

/* Tessere stile tabellone stazione (riuso visivo del flip clock) */
.flap-tile{display:inline-flex;align-items:center;justify-content:center;position:relative;border-radius:3px;font-family:'Space Mono',monospace;font-weight:700;text-transform:uppercase;transform-origin:center;animation:flapIn .4s ease backwards}
.flap-tile::after{content:'';position:absolute;left:0;right:0;top:50%;height:1px;background:rgba(0,0,0,.5)}

/* Preview overlay */
.preview-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:500;display:flex;align-items:center;justify-content:center;padding:24px}
.preview-canvas{position:relative;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.6)}
.preview-close{position:absolute;top:16px;right:16px;width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.15);border:none;color:#fff;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.preview-close:hover{background:rgba(255,255,255,.25)}
.preview-info{position:absolute;bottom:16px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.6);color:#fff;font-size:11px;padding:4px 12px;border-radius:20px;white-space:nowrap}

/* No selection */
.no-sel{text-align:center;padding:24px 14px;color:var(--on-variant)}
.no-sel svg{margin:0 auto 10px;opacity:.3}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Hanken+Grotesk:wght@400;600;700;800&family=Poppins:wght@400;600;700;800&family=Montserrat:wght@400;600;700;800&family=Oswald:wght@400;600;700&family=Bebas+Neue&family=Space+Mono:wght@400;700&display=swap">
</head>
<body>
<?php require_once __DIR__ . '/includes/topnav.php'; ?>
<div class="app">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <main class="main" style="<?php echo $open_template?'padding:12px':''; ?>">

  <?php if ($success): ?>
  <div style="background:var(--success-bg);border:1px solid rgba(34,197,94,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--success);margin-bottom:14px;display:flex;align-items:center;gap:8px">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
    <?php echo htmlspecialchars($success); ?>
  </div>
  <?php endif; ?>

  <?php if ($open_template): ?>
  <!-- ══════════ EDITOR ══════════ -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:10px">
    <div style="display:flex;align-items:center;gap:10px;min-width:0">
      <a href="/templates.php" class="btn-ghost" style="display:inline-flex;flex-shrink:0">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Indietro
      </a>
      <div style="font-family:'Hanken Grotesk',sans-serif;font-size:17px;font-weight:700;color:var(--on-surface);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars($open_template['nome']); ?></div>
      <span class="badge blue" style="flex-shrink:0"><?php echo $open_template['canvas_w'].'×'.$open_template['canvas_h']; ?></span>
    </div>
    <div style="display:flex;gap:8px;flex-shrink:0">
      <button class="btn-ghost" onclick="showPreview()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        Anteprima
      </button>
      <button class="btn-ghost" onclick="document.getElementById('assign-modal').style.display='flex'">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
        Assegna
      </button>
      <button class="tb blue" id="save-btn" onclick="saveLayers()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
        Salva
      </button>
    </div>
  </div>

  <div class="editor-shell">

    <!-- ── PANEL SX: widget + layer list ── -->
    <div class="panel" style="border-right:1px solid var(--outline-var)">
      <div class="panel-head">Widget</div>
      <div class="panel-body" style="padding:6px">
        <?php foreach ($widget_types as $type => $wt):
          $unlocked = isWidgetUnlocked($type, $catalog, $tenantPlan);
        ?>
        <div class="witem <?php echo $unlocked?'':'locked'; ?>" draggable="<?php echo $unlocked?'true':'false'; ?>" data-type="<?php echo $type; ?>" <?php echo $unlocked?'':'title="Richiede piano Plus o Professional — vedi Catalogo Widget"'; ?>>
          <div class="wdot" style="background:<?php echo $wt['color']; ?>22;color:<?php echo $wt['color']; ?>"><i class="fa-solid <?php echo $wt['icon']; ?>"></i></div>
          <span class="wlabel"><?php echo $wt['label']; ?></span>
          <?php if (!$unlocked): ?>
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:auto;flex-shrink:0;opacity:.6"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <a href="/widgets.php" class="btn-sm" style="width:100%;justify-content:center;margin-top:8px">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z"/></svg>
          Vedi Catalogo
        </a>

        <div style="border-top:1px solid var(--outline-var);margin:10px -8px 8px;padding-top:8px;padding-left:8px;font-size:10px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.06em">Layer</div>
        <div id="layer-list"></div>
      </div>
    </div>

    <!-- ── CANVAS ── -->
    <div class="canvas-area" id="canvas-area">
      <div class="toolbar">
        <button class="tb" onclick="deleteSelected()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
          Elimina
        </button>
        <button class="tb" onclick="duplicateSelected()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
          Duplica
        </button>
        <div class="tb-sep"></div>
        <button class="tb" onclick="bringFwd()">↑ Avanti</button>
        <button class="tb" onclick="sendBwd()">↓ Indietro</button>
        <div class="tb-sep"></div>
        <button class="tb" onclick="align('left')">⊢</button>
        <button class="tb" onclick="align('right')">⊣</button>
        <button class="tb" onclick="align('top')">⊤</button>
        <button class="tb" onclick="align('bottom')">⊥</button>
        <button class="tb" onclick="align('cx')">↔</button>
        <button class="tb" onclick="align('cy')">↕</button>
      </div>

      <div id="canvas" style="background:#111">
        <div class="canvas-grid"></div>
      </div>

      <div style="position:absolute;bottom:12px;left:12px;font-size:10px;color:var(--on-variant);background:var(--surface);border:1px solid var(--outline-var);border-radius:6px;padding:3px 8px;pointer-events:none">
        <span style="font-weight:600;color:var(--on-surface)">Shift</span> = proporzioni · <span style="font-weight:600;color:var(--on-surface)">Alt+click</span> = layer sotto · <span style="font-weight:600;color:var(--on-surface)">⌘S</span> = salva
      </div>
      <div class="zoom-bar">
        <button class="zb" onclick="zoomOut()">−</button>
        <div class="zval" id="zoom-val">50%</div>
        <button class="zb" onclick="zoomIn()">+</button>
        <button class="zb" onclick="zoomFit()" title="Fit">⊡</button>
      </div>
    </div>

    <!-- ── PANEL DX: props ── -->
    <div class="panel" id="props-panel">
      <div class="panel-head">
        <span id="props-title">Proprietà</span>
        <span id="props-widget-badge" style="font-size:10px;padding:2px 7px;border-radius:10px;background:var(--blue-bg);color:var(--blue-text);display:none"></span>
      </div>
      <div class="panel-body" id="props-body">
        <div class="no-sel">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
          <div style="font-size:12px;font-weight:600;margin-bottom:4px">Nessun layer</div>
          <div style="font-size:11px">Trascina un widget sul canvas</div>
        </div>
      </div>
    </div>

  </div>

  <!-- PREVIEW OVERLAY -->
  <div id="preview-overlay" class="preview-overlay" style="display:none">
    <button class="preview-close" onclick="hidePreview()">✕</button>
    <div id="preview-canvas" class="preview-canvas"></div>
    <div class="preview-info" id="preview-info">Caricamento anteprima…</div>
  </div>

  <?php else: ?>
  <!-- ══════════ LISTA TEMPLATE ══════════ -->
  <div class="page-head">
    <div>
      <div class="page-title">Template Layout</div>
      <div class="page-sub"><?php echo count($templates); ?> template · editor canvas drag&drop</div>
    </div>
    <div class="head-actions">
      <button class="btn-ghost" onclick="document.getElementById('brand-modal').style.display='flex'">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
        Brand Kit
      </button>
      <button class="btn-primary" onclick="document.getElementById('create-modal').style.display='flex'">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuovo template
      </button>
    </div>
  </div>

  <!-- Brand Kit summary -->
  <?php if ($brand_logo || $brand_primario): ?>
  <div class="card cp" style="margin-bottom:16px;display:flex;align-items:center;gap:16px">
    <?php if ($brand_logo): ?>
    <img src="/uploads/<?php echo htmlspecialchars($brand_logo); ?>" style="height:40px;object-fit:contain">
    <?php endif; ?>
    <div style="display:flex;gap:8px;align-items:center">
      <div style="width:24px;height:24px;border-radius:5px;background:<?php echo htmlspecialchars($brand_primario); ?>;border:1px solid var(--outline-var)" title="Primario"></div>
      <div style="width:24px;height:24px;border-radius:5px;background:<?php echo htmlspecialchars($brand_secondario); ?>;border:1px solid var(--outline-var)" title="Secondario"></div>
      <div style="width:24px;height:24px;border-radius:5px;background:<?php echo htmlspecialchars($brand_accento); ?>;border:1px solid var(--outline-var)" title="Accento"></div>
    </div>
    <div style="font-size:12px;color:var(--on-variant)">Brand Kit configurato</div>
    <button class="btn-sm" style="margin-left:auto" onclick="document.getElementById('brand-modal').style.display='flex'">Modifica</button>
  </div>
  <?php endif; ?>

  <?php if (empty($templates)): ?>
  <div class="card cp" style="text-align:center;padding:60px">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="margin:0 auto 16px;opacity:.3"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
    <div style="font-size:17px;font-weight:700;margin-bottom:8px">Nessun template</div>
    <div style="font-size:13px;color:var(--on-variant);margin-bottom:20px">Crea il primo layout per le tue TV</div>
    <button class="btn-primary" style="margin:0 auto" onclick="document.getElementById('create-modal').style.display='flex'">Crea template</button>
  </div>
  <?php else: ?>
  <div class="tpl-grid">
    <?php foreach ($templates as $tpl):
      $stmt2 = $db->prepare("SELECT * FROM layout_layers WHERE template_id=? ORDER BY z_index");
      $stmt2->execute([$tpl['id']]);
      $prev_layers = $stmt2->fetchAll();
      $pw = $tpl['canvas_w']; $ph = $tpl['canvas_h'];
      $scale = min(240/$pw, 130/$ph);
      $sw = round($pw*$scale); $sh = round($ph*$scale);
    ?>
    <div class="tpl-card">
      <div class="tpl-preview">
        <div style="width:<?php echo $sw; ?>px;height:<?php echo $sh; ?>px;background:#111;position:relative;overflow:hidden;border-radius:3px;box-shadow:0 2px 8px rgba(0,0,0,.3)">
          <?php foreach ($prev_layers as $ll):
            $wt2 = $widget_types[$ll['widget_type']] ?? ['color'=>'#444'];
            $cfg = json_decode($ll['config']??'{}', true);
            $bg = $cfg['bg_color'] ?? $wt2['color'];
            $lx=round($ll['pos_x']*$scale);$ly=round($ll['pos_y']*$scale);
            $lw=round($ll['width']*$scale);$lh=round($ll['height']*$scale);
          ?>
          <div style="position:absolute;left:<?php echo $lx; ?>px;top:<?php echo $ly; ?>px;width:<?php echo $lw; ?>px;height:<?php echo $lh; ?>px;background:<?php echo htmlspecialchars($bg); ?>;opacity:.8;border-radius:1px;display:flex;align-items:center;justify-content:center">
            <span style="font-size:<?php echo max(6,round(8*$scale)); ?>px;color:rgba(255,255,255,.8);font-weight:600"><?php echo $wt2['label']??''; ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,.6);color:#fff;font-size:9px;font-weight:600;padding:2px 7px;border-radius:4px"><?php echo $tpl['canvas_w'].'×'.$tpl['canvas_h']; ?></div>
      </div>
      <div class="tpl-info">
        <div class="tpl-name"><?php echo htmlspecialchars($tpl['nome']); ?></div>
        <div class="tpl-meta">
          <span><?php echo $tpl['num_layers']; ?> layer</span>
          <span>·</span><span><?php echo $tpl['num_disp']; ?> dispositivi</span>
          <span>·</span><span><?php echo strtoupper($tpl['orientamento']??'landscape'); ?></span>
        </div>
      </div>
      <div class="tpl-actions">
        <a href="/templates.php?id=<?php echo $tpl['id']; ?>" class="btn-sm" style="flex:1;justify-content:center">Modifica</a>
        <button class="btn-sm" title="Assegna a dispositivi" onclick="document.getElementById('assign-modal').style.display='flex';document.getElementById('assign-tpl-id').value=<?php echo $tpl['id']; ?>">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><line x1="9" y1="9" x2="9" y2="9.01"/><line x1="9" y1="12" x2="9" y2="12.01"/><line x1="9" y1="15" x2="9" y2="15.01"/></svg>
        </button>
        <button class="btn-sm" title="Rinomina" onclick='rinominaTemplate(<?php echo $tpl['id']; ?>, <?php echo htmlspecialchars(json_encode($tpl['nome']), ENT_QUOTES); ?>)'>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="duplicate_template">
          <input type="hidden" name="template_id" value="<?php echo $tpl['id']; ?>">
          <button type="submit" class="btn-sm" title="Duplica">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
          </button>
        </form>
        <form method="POST" onsubmit="return confirm('Eliminare?')" style="margin-left:auto">
          <input type="hidden" name="action" value="delete_template">
          <input type="hidden" name="template_id" value="<?php echo $tpl['id']; ?>">
          <button type="submit" class="btn-sm danger" title="Elimina">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  </main>
</div>

<script>
function rinominaTemplate(id, nomeAttuale) {
  const nuovo = prompt('Nuovo nome del template:', nomeAttuale);
  if (nuovo === null) return; // annullato
  const nuovoTrim = nuovo.trim();
  if (nuovoTrim === '' || nuovoTrim === nomeAttuale) return;

  const form = document.createElement('form');
  form.method = 'POST';
  form.innerHTML = `
    <input type="hidden" name="action" value="rename_template">
    <input type="hidden" name="template_id" value="${id}">
    <input type="hidden" name="nome" value="${nuovoTrim.replace(/"/g, '&quot;')}">
  `;
  document.body.appendChild(form);
  form.submit();
}
</script>


<!-- CREATE MODAL -->
<div id="create-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:420px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:20px;font-weight:700;margin-bottom:20px">Nuovo template</div>
    <form method="POST">
      <input type="hidden" name="action" value="create_template">
      <div class="form-group">
        <label class="form-label">Nome *</label>
        <input type="text" name="nome" class="form-input" required autofocus placeholder="Es. TV Gymnasium Standard">
      </div>
      <div class="form-group">
        <label class="form-label">Formato</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <label id="fmt-l" style="border:2px solid var(--blue);border-radius:10px;padding:12px;text-align:center;cursor:pointer;background:var(--blue-bg)">
            <input type="radio" name="fmt" value="landscape" checked style="display:none" onchange="setFmt('landscape')">
            <div style="font-size:22px">▬</div>
            <div style="font-size:12px;font-weight:600;color:var(--blue-text)">Landscape</div>
            <div style="font-size:10px;color:var(--on-variant)">1920 × 1080</div>
          </label>
          <label id="fmt-p" style="border:2px solid var(--outline-var);border-radius:10px;padding:12px;text-align:center;cursor:pointer">
            <input type="radio" name="fmt" value="portrait" style="display:none" onchange="setFmt('portrait')">
            <div style="font-size:22px">▮</div>
            <div style="font-size:12px;font-weight:600">Portrait</div>
            <div style="font-size:10px;color:var(--on-variant)">1080 × 1920</div>
          </label>
        </div>
        <input type="hidden" name="canvas_w" id="canvas_w" value="1920">
        <input type="hidden" name="canvas_h" id="canvas_h" value="1080">
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('create-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Crea</button>
      </div>
    </form>
  </div>
</div>

<!-- ASSIGN MODAL -->
<div id="assign-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:400px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:20px">Assegna template</div>
    <form method="POST">
      <input type="hidden" name="action" value="assign">
      <input type="hidden" name="template_id" id="assign-tpl-id" value="<?php echo $open_tid; ?>">
      <div class="form-group">
        <label class="form-label">Dispositivo</label>
        <select name="dispositivo_id" class="form-input form-select" required>
          <option value="">Seleziona…</option>
          <?php foreach ($dispositivi as $d): ?>
          <option value="<?php echo $d['id']; ?>" <?php echo $d['template_id']==$open_tid?'selected':''; ?>>
            <?php echo htmlspecialchars($d['nome'].' — '.$d['club']); ?><?php echo $d['template_id']?' ✓':''; ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('assign-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Assegna</button>
      </div>
    </form>
  </div>
</div>

<!-- BRAND KIT MODAL -->
<div id="brand-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:28px;width:100%;max-width:440px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:20px;font-weight:700;margin-bottom:6px">Brand Kit</div>
    <div style="font-size:13px;color:var(--on-variant);margin-bottom:20px">Logo e colori del tuo brand — usati automaticamente nei widget</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save_brand">
      <div class="form-group">
        <label class="form-label">Logo (PNG, SVG, JPG)</label>
        <?php if ($brand_logo): ?>
        <img src="/uploads/<?php echo htmlspecialchars($brand_logo); ?>" class="brand-logo-preview">
        <?php endif; ?>
        <input type="file" name="brand_logo_file" class="form-input" accept="image/*">
      </div>
      <div class="form-group">
        <label class="form-label">Colori brand</label>
        <div style="display:flex;flex-direction:column;gap:10px">
          <div style="display:flex;align-items:center;gap:10px">
            <input type="color" name="brand_colore_primario" value="<?php echo htmlspecialchars($brand_primario); ?>" style="width:40px;height:36px;border-radius:6px;border:1px solid var(--outline-var);cursor:pointer">
            <div>
              <div style="font-size:12px;font-weight:500;color:var(--on-surface)">Colore primario</div>
              <div style="font-size:11px;color:var(--on-variant)"><?php echo htmlspecialchars($brand_primario); ?></div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:10px">
            <input type="color" name="brand_colore_secondario" value="<?php echo htmlspecialchars($brand_secondario); ?>" style="width:40px;height:36px;border-radius:6px;border:1px solid var(--outline-var);cursor:pointer">
            <div>
              <div style="font-size:12px;font-weight:500;color:var(--on-surface)">Colore secondario</div>
              <div style="font-size:11px;color:var(--on-variant)"><?php echo htmlspecialchars($brand_secondario); ?></div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:10px">
            <input type="color" name="brand_colore_accento" value="<?php echo htmlspecialchars($brand_accento); ?>" style="width:40px;height:36px;border-radius:6px;border:1px solid var(--outline-var);cursor:pointer">
            <div>
              <div style="font-size:12px;font-weight:500;color:var(--on-surface)">Colore accento</div>
              <div style="font-size:11px;color:var(--on-variant)"><?php echo htmlspecialchars($brand_accento); ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Font brand</label>
        <select name="brand_font" class="form-input" style="font-family:'<?php echo htmlspecialchars($brand_font); ?>',sans-serif">
          <?php foreach (['Inter','Poppins','Montserrat','Oswald','Bebas Neue','Space Mono'] as $fontOpt): ?>
          <option value="<?php echo $fontOpt; ?>" style="font-family:'<?php echo $fontOpt; ?>',sans-serif" <?php echo $brand_font===$fontOpt?'selected':''; ?>><?php echo $fontOpt; ?></option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:10.5px;color:var(--on-variant);margin-top:4px">Applicato come font di base su tutti i widget del player, salvo dove un widget usa gia' un font specifico (es. Space Mono per gli stili tabellone).</div>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="document.getElementById('brand-modal').style.display='none'">Annulla</button>
        <button type="submit" class="btn-primary" style="flex:2;justify-content:center">Salva Brand Kit</button>
      </div>
    </form>
  </div>
</div>

<!-- CHANNEL MANAGER MODAL -->
<div id="channel-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:350;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)closeChannelManager()">
  <div style="background:var(--surface);border-radius:16px;padding:24px;width:100%;max-width:560px;max-height:85vh;display:flex;flex-direction:column">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:4px">Gestisci canali IPTV</div>
    <div style="font-size:12px;color:var(--on-variant);margin-bottom:16px">Aggiungi o rimuovi i canali salvati per questo tenant. Assicurati di avere i diritti d'uso per ogni URL.</div>

    <div id="channel-list" style="overflow-y:auto;flex:1;margin-bottom:14px;display:flex;flex-direction:column;gap:6px"></div>

    <div style="border-top:1px solid var(--outline-var);padding-top:14px">
      <div style="font-size:11px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Aggiungi canale</div>
      <div style="display:flex;gap:8px;margin-bottom:10px">
        <input type="text" id="new-channel-nome" class="prop-input" placeholder="Nome canale" style="flex:1">
        <input type="url" id="new-channel-url" class="prop-input" placeholder="URL stream (m3u8, ecc.)" style="flex:2">
        <button type="button" class="btn-sm" onclick="addChannelRow()" style="white-space:nowrap">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Aggiungi
        </button>
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" class="btn-ghost" style="flex:1" onclick="closeChannelManager()">Annulla</button>
        <button type="button" class="btn-primary" id="save-channels-btn" style="flex:2;justify-content:center" onclick="saveChannels()">Salva canali</button>
      </div>
    </div>
  </div>
</div>

<!-- LIBRARY PICKER MODAL -->
<div id="lib-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border-radius:16px;padding:24px;width:100%;max-width:520px">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:16px">Seleziona dalla libreria</div>
    <div class="search-box" style="height:36px;margin-bottom:14px">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" color="var(--on-variant)"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input type="text" id="lib-search" placeholder="Cerca contenuto..." oninput="filterLib(this.value)">
    </div>
    <div class="lib-grid" id="lib-grid">
      <?php foreach ($libreria as $item):
        $ext = strtolower(pathinfo($item['file'], PATHINFO_EXTENSION));
        $is_img = in_array($ext, ['jpg','jpeg','png','webp','gif','svg']);
      ?>
      <div class="lib-item" data-file="<?php echo htmlspecialchars($item['file']); ?>" data-name="<?php echo htmlspecialchars($item['nome']); ?>" onclick="selectLibItem(this)" title="<?php echo htmlspecialchars($item['nome']); ?>">
        <?php if ($is_img): ?>
        <img src="/uploads/<?php echo htmlspecialchars($item['file']); ?>" alt="">
        <?php else: ?>
        <div style="height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--violet-bg),var(--blue-bg))">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--violet)" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        </div>
        <?php endif; ?>
        <div class="lib-item-name"><?php echo htmlspecialchars($item['nome']); ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <!-- Brand Kit section -->
    <?php if ($brand_logo): ?>
    <div style="border-top:1px solid var(--outline-var);margin-top:14px;padding-top:14px">
      <div style="font-size:10px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Brand Kit</div>
      <div class="lib-item" data-file="<?php echo htmlspecialchars($brand_logo); ?>" data-name="Logo brand" onclick="selectLibItem(this)" style="width:80px">
        <img src="/uploads/<?php echo htmlspecialchars($brand_logo); ?>" alt="">
        <div class="lib-item-name">Logo brand</div>
      </div>
    </div>
    <?php endif; ?>
    <div style="display:flex;gap:10px;margin-top:16px">
      <button class="btn-ghost" style="flex:1" onclick="document.getElementById('lib-modal').style.display='none'">Annulla</button>
      <button class="btn-primary" style="flex:2;justify-content:center" onclick="confirmLibSelection()">Seleziona</button>
    </div>
  </div>
</div>

<!-- SLIDE EDITOR MODAL -->
<div id="slide-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:400;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)closeSlideModal()">
  <div style="background:var(--surface);border-radius:16px;padding:24px;width:100%;max-width:460px;max-height:90vh;overflow-y:auto">
    <div style="font-family:'Hanken Grotesk',sans-serif;font-size:18px;font-weight:700;margin-bottom:16px" id="slide-modal-title">Nuova slide</div>

    <div class="form-group">
      <label class="form-label">Tipo widget</label>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:4px" id="slide-type-grid">
        <?php foreach(['corsi'=>'Corsi Live','meteo'=>'Meteo','countdown'=>'Countdown','data'=>'Data','info'=>'Info/Testo','immagine'=>'Immagine','qrcode'=>'QR Code'] as $type=>$label): ?>
        <div class="slide-type-btn" data-type="<?php echo $type; ?>" onclick="selectSlideType('<?php echo $type; ?>')" style="border:1px solid var(--outline-var);border-radius:8px;padding:10px 6px;text-align:center;cursor:pointer;transition:all .12s;background:var(--surface-low)">
          <div style="font-size:11px;font-weight:600;color:var(--on-surface)"><?php echo $label; ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Titolo slide (opzionale)</label>
      <input type="text" id="slide-titolo" class="form-input" placeholder="Es. Meteo Soave">
    </div>

    <div class="form-group">
      <label class="form-label">Durata (secondi)</label>
      <input type="number" id="slide-durata" class="form-input" value="10" min="1" max="300">
    </div>

    <!-- Config dinamica per tipo -->
    <div id="slide-config-area"></div>

    <div style="display:flex;gap:8px;margin-top:16px">
      <button class="btn-ghost" style="flex:1" onclick="closeSlideModal()">Annulla</button>
      <button class="btn-primary" style="flex:2;justify-content:center" onclick="saveSlide()">Salva slide</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
<?php if ($open_template): ?>
// ════════════════════════════════════════════════════════════
// CANVAS ENGINE
// ════════════════════════════════════════════════════════════
const CW = <?php echo $open_template['canvas_w']; ?>;
const CH = <?php echo $open_template['canvas_h']; ?>;
const TID = <?php echo $open_tid; ?>;
const BRAND = {
  primario:   '<?php echo addslashes($brand_primario); ?>',
  secondario: '<?php echo addslashes($brand_secondario); ?>',
  accento:    '<?php echo addslashes($brand_accento); ?>',
  logo:       '<?php echo addslashes($brand_logo); ?>',
  font:       '<?php echo addslashes($brand_font); ?>'
};
const WIDGETS = <?php echo jsonForScript($widget_types); ?>;
const TIME_PRESETS = <?php echo jsonForScript($catalog['time']['template_grafici'] ?? []); ?>;
const CORSI_PRESETS = <?php echo jsonForScript($catalog['corsi']['template_grafici'] ?? []); ?>;
const METEO_PRESETS = <?php echo jsonForScript($catalog['meteo']['template_grafici'] ?? []); ?>;
const COUNTDOWN_PRESETS = <?php echo jsonForScript($catalog['countdown']['template_grafici'] ?? []); ?>;
const INFO_PRESETS = <?php echo jsonForScript($catalog['info']['template_grafici'] ?? []); ?>;
const DATA_PRESETS = <?php echo jsonForScript($catalog['data']['template_grafici'] ?? []); ?>;

// Canali salvati dal tenant — persistiti nel DB (impostazioni.streaming_channels),
// gestibili dal pannello "Gestisci canali" del widget Streaming/IPTV.
let STREAMING_CHANNELS = <?php echo jsonForScript($streaming_channels); ?>;

function refreshLayerPreview() {
  const layer = layers.find(l=>l.id==selId);
  if (!layer) return;
  const el = canvas.querySelector(`[data-id="${layer.id}"]`);
  if (!el) return;
  const inner = el.querySelector('.clayer-inner');
  if (inner) inner.innerHTML = layerPreview(layer);
}

function applyChannelPreset(nome) {
  const ch = STREAMING_CHANNELS.find(x=>x.nome===nome);
  const layer = layers.find(l=>l.id==selId);
  if (!ch || !layer) return;
  if (!layer.config) layer.config = {};
  layer.config.url = ch.url;
  layer.config.nome_canale = ch.nome;
  const urlInput = document.getElementById('streaming-url-input');
  const nomeInput = document.getElementById('streaming-nome-input');
  if (urlInput) urlInput.value = ch.url;
  if (nomeInput) nomeInput.value = ch.nome;
  refreshLayerPreview();
}

// ── Gestione canali salvati (aggiungi/elimina, persistito nel DB) ─
let channelDraft = [];

function openChannelManager() {
  channelDraft = STREAMING_CHANNELS.map(ch => ({...ch})); // copia di lavoro
  renderChannelManagerList();
  document.getElementById('channel-modal').style.display = 'flex';
}

function closeChannelManager() {
  document.getElementById('channel-modal').style.display = 'none';
}

function renderChannelManagerList() {
  const list = document.getElementById('channel-list');
  if (!channelDraft.length) {
    list.innerHTML = '<div style="text-align:center;padding:24px;font-size:12px;color:var(--on-variant)">Nessun canale salvato. Aggiungine uno qui sotto.</div>';
    return;
  }
  list.innerHTML = channelDraft.map((ch, i) => `
    <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--surface-low);border:1px solid var(--outline-var);border-radius:8px">
      <div style="flex:1;min-width:0">
        <div style="font-size:12px;font-weight:600;color:var(--on-surface)">${ch.nome}</div>
        <div style="font-size:10px;color:var(--on-variant);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${ch.url}</div>
      </div>
      <button type="button" onclick="deleteChannelRow(${i})" style="border:none;background:none;color:var(--error);cursor:pointer;padding:4px;flex-shrink:0">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
      </button>
    </div>
  `).join('');
}

function addChannelRow() {
  const nomeEl = document.getElementById('new-channel-nome');
  const urlEl = document.getElementById('new-channel-url');
  const nome = nomeEl.value.trim();
  const url = urlEl.value.trim();
  if (!nome || !url) { alert('Inserisci nome e URL del canale'); return; }
  channelDraft.push({ nome, url });
  nomeEl.value = ''; urlEl.value = '';
  renderChannelManagerList();
}

function deleteChannelRow(idx) {
  channelDraft.splice(idx, 1);
  renderChannelManagerList();
}

async function saveChannels() {
  const btn = document.getElementById('save-channels-btn');
  btn.textContent = 'Salvataggio…'; btn.disabled = true;
  const fd = new FormData();
  fd.append('action', 'save_channels');
  fd.append('channels', JSON.stringify(channelDraft));
  try {
    const res = await fetch(window.location.href, { method:'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      STREAMING_CHANNELS = channelDraft.map(ch => ({...ch}));
      // Aggiorna il menu a tendina nel pannello, se aperto sul widget streaming
      const select = document.getElementById('streaming-channel-select');
      if (select) {
        const current = select.value;
        select.innerHTML = '<option value="">Seleziona un canale...</option>' +
          STREAMING_CHANNELS.map(ch=>`<option value="${ch.nome}" ${current===ch.nome?'selected':''}>${ch.nome}</option>`).join('');
      }
      btn.textContent = '✓ Salvato';
      setTimeout(()=>{ closeChannelManager(); btn.textContent='Salva canali'; btn.disabled=false; }, 700);
    } else {
      btn.textContent = 'Errore'; btn.disabled = false;
    }
  } catch(e) {
    btn.textContent = 'Errore'; btn.disabled = false;
  }
}

const LIBRERIA = <?php echo jsonForScript($libreria); ?>;

// Sidebar widget types
const SIDEBAR_WIDGET_LABELS = {
  corsi:'Corsi Live', meteo:'Meteo', countdown:'Countdown', data:'Data',
  info:'Info/Testo', immagine:'Immagine', qrcode:'QR Code', video:'Video'
};
const SIDEBAR_WIDGET_COLORS = {
  corsi:'#e94560', meteo:'#3b82f6', countdown:'#f59e0b', data:'#a855f7',
  info:'#10b981', immagine:'#8b5cf6', qrcode:'#64748b', video:'#7126D1'
};

let zoom = 0.45;
let selId = null;
let nextId = Date.now();
let libCallback = null;
let layers = <?php echo jsonForScript(array_map(function($l){
    $l['config'] = json_decode($l['config'] ?? '{}', true) ?: [];
    $l['id'] = $l['id'];
    $l['visible'] = (bool)$l['visible'];
    $l['adv_safe'] = (bool)($l['adv_safe'] ?? false);
    return $l;
}, $open_layers)); ?>;

const canvas = document.getElementById('canvas');

// ── Default sizes per widget ─────────────────────────────────
const DEFAULTS = {
  logo:      {w:300,h:120},   time:{w:260,h:140},
  data:      {w:320,h:100},
  tv:{w:1540,h:1080},
  streaming: {w:1540,h:1080}, adv:{w:1920,h:1080},
  sidebar:   {w:380,h:1080},  corsi:{w:380,h:500},
  meteo:     {w:380,h:200},   countdown:{w:380,h:200},
  info:      {w:400,h:150},   immagine:{w:400,h:225},
  qrcode:    {w:200,h:200},   ticker:{w:1920,h:60},
};

// ── Init ─────────────────────────────────────────────────────
function initCanvas() {
  const area = document.getElementById('canvas-area');
  const aw = area.clientWidth - 80, ah = area.clientHeight - 120;
  zoom = Math.min(aw/CW, ah/CH, 0.9);
  zoom = Math.round(zoom * 20) / 20;
  applyZoom();
  // Font brand come default ereditato da tutti i widget del canvas — un widget puo'
  // comunque avere il suo font specifico (es. Space Mono negli stili tabellone) perche'
  // quello e' impostato direttamente sull'elemento e vince sull'ereditarieta'.
  canvas.style.fontFamily = `'${BRAND.font}', sans-serif`;
  renderAll();
}

function applyZoom() {
  canvas.style.width  = Math.round(CW*zoom)+'px';
  canvas.style.height = Math.round(CH*zoom)+'px';
  document.getElementById('zoom-val').textContent = Math.round(zoom*100)+'%';
  renderAll();
}

function zoomIn()  { zoom=Math.min(zoom+0.05,2);   applyZoom(); }
function zoomOut() { zoom=Math.max(zoom-0.05,0.1); applyZoom(); }
function zoomFit() { initCanvas(); }

// ── Render ───────────────────────────────────────────────────
function renderAll() {
  canvas.querySelectorAll('.clayer').forEach(e=>e.remove());
  const sorted = [...layers].sort((a,b)=>a.z_index-b.z_index);
  sorted.forEach(l => { if(l.visible!==false) canvas.appendChild(makeEl(l)); });
  renderLayerList();
}

function makeEl(layer) {
  const wt = WIDGETS[layer.widget_type] || {color:'#555',label:layer.widget_type};
  const cfg = layer.config || {};
  let bg = cfg.bg_color || wt.color;
  if (cfg.gradient_type && cfg.grad_a && cfg.grad_b) {
    const dir = cfg.gradient_dir || 'to right';
    bg = `linear-gradient(${dir}, ${cfg.grad_a}, ${cfg.grad_b})`;
  }
  const el = document.createElement('div');
  el.className = 'clayer' + (layer.id==selId?' sel':'');
  el.dataset.id = layer.id;
  el.style.cssText = `left:${r(layer.pos_x*zoom)}px;top:${r(layer.pos_y*zoom)}px;width:${r(layer.width*zoom)}px;height:${r(layer.height*zoom)}px;z-index:${layer.z_index}`;

  // Sfondo del layer SEPARATO dal contenuto: l'opacità qui non deve mai scurire il testo sopra
  const bgDiv = document.createElement('div');
  bgDiv.className = 'clayer-bg';
  bgDiv.style.cssText = `position:absolute;inset:0;background:${bg};opacity:${cfg.bg_opacity??0.9}`;
  el.appendChild(bgDiv);

  // Inner content — sempre a piena opacità, sopra lo sfondo
  const inner = document.createElement('div');
  inner.className = 'clayer-inner';
  inner.innerHTML = layerPreview(layer);
  el.appendChild(inner);

  // Label
  const lbl = document.createElement('div');
  lbl.className = 'clayer-label';
  lbl.textContent = layer.nome;
  el.appendChild(lbl);

  // Size hint when selected
  if (layer.id == selId) {
    const hint = document.createElement('div');
    hint.style.cssText = 'position:absolute;bottom:2px;right:4px;font-size:9px;color:rgba(255,255,255,.6);pointer-events:none;font-family:monospace';
    hint.textContent = `${layer.width}×${layer.height}`;
    el.appendChild(hint);
  }

  // Resize handles
  ['nw','n','ne','w','e','sw','s','se'].forEach(pos => {
    const rh = document.createElement('div');
    rh.className = `rh ${pos}`;
    rh.addEventListener('mousedown', e => { e.stopPropagation(); startResize(e,layer,pos); });
    el.appendChild(rh);
  });

  el.addEventListener('mousedown', e => {
    if (e.target.classList.contains('rh')) return;
    e.stopPropagation();
    if (e.altKey) { cycleLayerAt(e); return; }
    selectLayer(layer.id);
    startDrag(e, layer);
  });

  return el;
}

// ── Live preview engine ────────────────────────────────────────
// Renderizza i widget in modo realistico, sempre attivo, con orologio/ticker/sidebar live.

const GIORNI = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
const MESI = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];
const MESI_ESTESI = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

// ── Data: widget indipendente dall'Orologio, 2 stili ─────────────
function renderDataMarkup(cfg, s, demoDate) {
  const d = demoDate || new Date();
  const stile = cfg.stile || 'minimale';
  const formato = cfg.formato || 'esteso';
  const textColor = cfg.text_color || '#ffffff';
  const colNumero = cfg.colore_numero || '#F7192E';
  const mostraAnno = !!cfg.mostra_anno;

  if (stile === 'calendario') {
    const fsNum = r((cfg.font_size||44)*s*0.7);
    const fsLabel = r((cfg.font_size||44)*s*0.22);
    return `<div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center">
      <div style="font-size:${fsNum}px;font-weight:800;color:${colNumero};line-height:1;font-variant-numeric:tabular-nums">${d.getDate()}</div>
      <div style="font-size:${fsLabel}px;color:${textColor};text-transform:uppercase;letter-spacing:.05em;margin-top:${r(4*s)}px">${MESI_ESTESI[d.getMonth()]}${mostraAnno?' '+d.getFullYear():''}</div>
      <div style="font-size:${r(fsLabel*0.85)}px;color:${textColor};opacity:.6;margin-top:1px">${GIORNI[d.getDay()]}</div>
    </div>`;
  }

  // minimale (default)
  const fs = r((cfg.font_size||20)*s*0.7);
  const testo = formato === 'breve'
    ? `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}${mostraAnno?'/'+d.getFullYear():''}`
    : `${GIORNI[d.getDay()]} ${d.getDate()} ${MESI_ESTESI[d.getMonth()]}${mostraAnno?' '+d.getFullYear():''}`;
  return `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;text-align:center">
    <div style="font-size:${fs}px;font-weight:600;color:${textColor}">${testo}</div>
  </div>`;
}

function fmtClock(mostraSecondi) {
  const d = new Date();
  const hh = String(d.getHours()).padStart(2,'0');
  const mm = String(d.getMinutes()).padStart(2,'0');
  const ss = String(d.getSeconds()).padStart(2,'0');
  return mostraSecondi ? `${hh}:${mm}:${ss}` : `${hh}:${mm}`;
}
function fmtDate() {
  const d = new Date();
  return `${GIORNI[d.getDay()]} ${d.getDate()} ${MESI[d.getMonth()]}`;
}

// ── Render del widget Time (6 stili) ──────────────────────────
// Markup generato una volta; il tick globale (sotto) aggiorna testo/anelli/barre
// ogni secondo cercando gli elementi .time-widget per data-attributo, senza
// dover ricostruire tutto l'HTML (evita flicker e permette animazioni CSS pulite).
function timeStrokePerimeter(w, h) {
  // Perimetro di uno stadio (rettangolo con estremita semicircolari, rx = h/2)
  return 2*(w-h) + Math.PI*h;
}

function hexToRgba(hex, alpha) {
  if (!hex || hex[0] !== '#') return `rgba(255,255,255,${alpha})`;
  const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
  return `rgba(${r},${g},${b},${alpha})`;
}

// ── Meteo: icone SVG colorate per condizione (no emoji unicode, compatibili BrightSign) ─
// Mappa i codici WMO restituiti da Open-Meteo in icona + etichetta italiana.
function weatherLabel(code) {
  const map = {0:'Sereno',1:'Poco nuvoloso',2:'Parzialmente nuvoloso',3:'Nuvoloso',45:'Nebbia',48:'Nebbia',51:'Pioviggine',53:'Pioviggine',55:'Pioviggine',56:'Gelicidio',57:'Gelicidio',61:'Pioggia debole',63:'Pioggia',65:'Pioggia forte',66:'Pioggia gelata',67:'Pioggia gelata',71:'Neve debole',73:'Neve',75:'Neve forte',77:'Neve granulare',80:'Rovesci',81:'Rovesci',82:'Rovesci forti',85:'Rovesci di neve',86:'Rovesci di neve forti',95:'Temporale',96:'Temporale con grandine',99:'Temporale con grandine'};
  return map[code] || 'Variabile';
}

function weatherIconSvg(code, isDay, size) {
  const s = size;
  // Sereno
  if (code === 0) {
    return isDay
      ? `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5" fill="#FDB813"/><g stroke="#FDB813" stroke-width="1.8" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.2" y1="4.2" x2="5.6" y2="5.6"/><line x1="18.4" y1="18.4" x2="19.8" y2="19.8"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.2" y1="19.8" x2="5.6" y2="18.4"/><line x1="18.4" y1="5.6" x2="19.8" y2="4.2"/></g></svg>`
      : `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M20 14.5A8.5 8.5 0 019.5 4 8.5 8.5 0 1020 14.5z" fill="#B8C4E0"/></svg>`;
  }
  // Poco/parzialmente nuvoloso
  if (code === 1 || code === 2) {
    return isDay
      ? `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><circle cx="9" cy="9" r="4.5" fill="#FDB813"/><path d="M6 20a4.5 4.5 0 01.4-9 5.5 5.5 0 0110.5 1.8A4 4 0 0118 20H6z" fill="#CBD5E1"/></svg>`
      : `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M15 11a5 5 0 01-4.6-7 5 5 0 106.2 6.8A5 5 0 0115 11z" fill="#B8C4E0"/><path d="M6 20a4.5 4.5 0 01.4-9 5.5 5.5 0 0110.5 1.8A4 4 0 0118 20H6z" fill="#CBD5E1"/></svg>`;
  }
  // Nuvoloso
  if (code === 3) {
    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M6 20a4.5 4.5 0 01.4-9 5.5 5.5 0 0110.5 1.8A4 4 0 0118 20H6z" fill="#94A3B8"/></svg>`;
  }
  // Nebbia
  if (code === 45 || code === 48) {
    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><g stroke="#94A3B8" stroke-width="2" stroke-linecap="round"><line x1="3" y1="8" x2="21" y2="8"/><line x1="3" y1="13" x2="21" y2="13"/><line x1="3" y1="18" x2="17" y2="18"/></g></svg>`;
  }
  // Pioggia / pioviggine / rovesci
  if ([51,53,55,56,57,61,63,65,66,67,80,81,82].includes(code)) {
    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M6 15a4.5 4.5 0 01.3-9 5.5 5.5 0 0110.6 1.7A4 4 0 0118 15H6z" fill="#94A3B8"/><g stroke="#3B82F6" stroke-width="1.8" stroke-linecap="round"><line x1="8" y1="18" x2="7" y2="21"/><line x1="12" y1="18" x2="11" y2="21"/><line x1="16" y1="18" x2="15" y2="21"/></g></svg>`;
  }
  // Neve
  if ([71,73,75,77,85,86].includes(code)) {
    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M6 15a4.5 4.5 0 01.3-9 5.5 5.5 0 0110.6 1.7A4 4 0 0118 15H6z" fill="#94A3B8"/><g fill="#E0F2FE"><circle cx="8" cy="19" r="1.3"/><circle cx="12" cy="20.5" r="1.3"/><circle cx="16" cy="19" r="1.3"/></g></svg>`;
  }
  // Temporale
  if ([95,96,99].includes(code)) {
    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M6 14a4.5 4.5 0 01.3-9 5.5 5.5 0 0110.6 1.7A4 4 0 0118 14H6z" fill="#64748B"/><path d="M13 14l-3 5h2.5l-1.5 4 4-6h-2.5l1.5-3z" fill="#FBBF24"/></svg>`;
  }
  return `<svg width="${s}" height="${s}" viewBox="0 0 24 24"><path d="M6 20a4.5 4.5 0 01.4-9 5.5 5.5 0 0110.5 1.8A4 4 0 0118 20H6z" fill="#94A3B8"/></svg>`;
}

// ── Info/Testo: libreria icone (Font Awesome, compatibili BrightSign) ─
const INFO_ICON_LIBRARY = {
  info:'fa-circle-info', avviso:'fa-triangle-exclamation', check:'fa-circle-check', stella:'fa-star', megafono:'fa-bullhorn',
  campana:'fa-bell', calendario:'fa-calendar-days', orologio:'fa-clock', regalo:'fa-gift', percentuale:'fa-percent',
  fuoco:'fa-fire', trofeo:'fa-trophy', cuore:'fa-heart', utenti:'fa-users', pin:'fa-location-dot',
  telefono:'fa-phone', wifi:'fa-wifi', musica:'fa-music', fotocamera:'fa-camera', manubrio:'fa-dumbbell',
  medaglia:'fa-medal', bandiera:'fa-flag', lucchetto:'fa-lock', scudo:'fa-shield-halved', fulmine:'fa-bolt',
  sole:'fa-sun', luna:'fa-moon', ombrello:'fa-umbrella-beach', tazza:'fa-mug-hot', vietato:'fa-ban',
};
function infoIconClass(icona) {
  return INFO_ICON_LIBRARY[icona] || null;
}
const INFO_STYLE_DEFAULTS = {
  semplice: { titolo:15, corpo:10, icona:16 },
  banner:   { titolo:14, corpo:10, icona:28 },
  poster:   { titolo:26, corpo:12, icona:32 },
};

// ── Info/Testo: 3 stili grafici, con supporto sfondo a immagine ─
function renderInfoMarkup(cfg, s) {
  const stile = cfg.stile || 'semplice';
  const defaults = INFO_STYLE_DEFAULTS[stile] || INFO_STYLE_DEFAULTS.semplice;
  const titolo = cfg.titolo || '';
  const corpo = cfg.corpo || cfg.testo || (titolo ? '' : 'Testo informativo');
  const colTitolo = cfg.colore_titolo || '#ffffff';
  const colCorpo = cfg.colore_corpo || 'rgba(255,255,255,.75)';
  const colIcona = cfg.colore_icona || '#F7192E';
  // Dimensioni sempre regolabili a mano: se non impostate, usano il default dello stile scelto
  const fsTitolo = r((cfg.font_size_titolo||defaults.titolo)*s);
  const fsCorpo = r((cfg.font_size_corpo||defaults.corpo)*s);
  const fsIcona = r((cfg.font_size_icona||defaults.icona)*s);
  // L'emoji personalizzata ha priorita' sull'icona da libreria, se impostata
  const iconClass = infoIconClass(cfg.icona);
  const iconHtml = cfg.icon_emoji
    ? `<span style="font-size:${fsIcona}px;line-height:1;display:block">${cfg.icon_emoji}</span>`
    : (iconClass ? `<i class="fa-solid ${iconClass}" style="font-size:${fsIcona}px;color:${colIcona};display:block"></i>` : '');
  const bgOverlay = (cfg.usa_immagine_sfondo && cfg.bg_image) ? `
    <img src="/uploads/${cfg.bg_image}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
    <div style="position:absolute;inset:0;background:#000;opacity:${cfg.bg_image_oscura??0.5}"></div>
  ` : '';

  if (stile === 'banner') {
    return `<div style="position:relative;width:100%;height:100%;display:flex;align-items:center;padding:${r(12*s)}px;overflow:hidden">
      ${bgOverlay}
      <div style="position:relative;display:flex;align-items:center;gap:${r(12*s)}px;width:100%">
        ${iconHtml?`<div style="flex-shrink:0">${iconHtml}</div>`:''}
        <div style="border-left:3px solid ${colIcona};padding-left:${r(10*s)}px;flex:1">
          ${titolo?`<div style="font-size:${fsTitolo}px;font-weight:700;color:${colTitolo}">${titolo}</div>`:''}
          ${corpo?`<div style="font-size:${fsCorpo}px;color:${colCorpo};margin-top:2px">${corpo}</div>`:''}
        </div>
      </div>
    </div>`;
  }

  if (stile === 'poster') {
    return `<div style="position:relative;width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:${r(16*s)}px;overflow:hidden">
      ${bgOverlay}
      <div style="position:relative">
        ${iconHtml?`<div style="margin-bottom:${r(8*s)}px">${iconHtml}</div>`:''}
        ${titolo?`<div style="font-size:${fsTitolo}px;font-weight:800;color:${colTitolo};letter-spacing:.02em">${titolo}</div>`:''}
        ${corpo?`<div style="font-size:${fsCorpo}px;color:${colCorpo};margin-top:${r(6*s)}px">${corpo}</div>`:''}
      </div>
    </div>`;
  }

  // semplice (default)
  return `<div style="position:relative;width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:${r(10*s)}px;overflow:hidden">
    ${bgOverlay}
    <div style="position:relative">
      ${iconHtml?`<div style="margin-bottom:4px">${iconHtml}</div>`:''}
      ${titolo?`<div style="font-size:${fsTitolo}px;font-weight:700;color:${colTitolo}">${titolo}</div>`:''}
      ${corpo?`<div style="font-size:${fsCorpo}px;color:${colCorpo};margin-top:2px">${corpo}</div>`:''}
    </div>
  </div>`;
}

function renderTimeMarkup(cfg, s) {
  const stile = cfg.stile || 'ring_pill';
  const textColor = cfg.text_color || '#ffffff';
  const progressColor = cfg.colore_progresso || '#F7192E';
  const now = new Date();
  const hh = String(now.getHours()).padStart(2,'0');
  const mm = String(now.getMinutes()).padStart(2,'0');
  const ss = String(now.getSeconds()).padStart(2,'0');
  const dateStr = fmtDate();
  const showSeconds = cfg.mostra_secondi!==false && ['ring_pill','flip','bar_below','ring_seconds'].includes(stile) ? true : (cfg.mostra_secondi||false);
  const hms = showSeconds ? `${hh}:${mm}:${ss}` : `${hh}:${mm}`;
  const fsOra = Math.round((cfg.font_size_ora||44)*s*0.6);
  const fsData = Math.round((cfg.font_size_data||20)*s*0.6);
  const mostraData = cfg.mostra_data!==false;

  if (stile === 'ring_pill') {
    // Sfondo pieno DENTRO il bordo dell'anello (non sotto/sopra al bordo)
    const W = 200, H = 80;
    const strokeW = 5;
    const peri = timeStrokePerimeter(W-8, H-8);
    const fillInset = 4 + strokeW/2 + 3; // resta dentro il tracciato dell'anello
    const bgFill = (cfg.sfondo_ora && cfg.sfondo_ora!=='transparent') ? cfg.sfondo_ora : '#111111';
    const bgOpacity = cfg.sfondo_ora_opacity ?? 0.6;
    return `<div class="time-widget" data-stile="ring_pill" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center">
      <div style="position:relative;width:100%;height:100%;display:flex;align-items:center;justify-content:center">
        <svg viewBox="0 0 ${W} ${H}" style="position:absolute;inset:0;width:100%;height:100%">
          <rect x="${fillInset}" y="${fillInset}" width="${W-fillInset*2}" height="${H-fillInset*2}" rx="${(H-fillInset*2)/2}" fill="${bgFill}" opacity="${bgOpacity}"/>
          <rect x="4" y="4" width="${W-8}" height="${H-8}" rx="${(H-8)/2}" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="${strokeW}"/>
          <rect class="time-ring-progress" data-peri="${peri}" x="4" y="4" width="${W-8}" height="${H-8}" rx="${(H-8)/2}" fill="none" stroke="${progressColor}" stroke-width="${strokeW}" stroke-dasharray="${peri}" stroke-dashoffset="${peri}"/>
        </svg>
        <span class="time-hms" style="position:relative;font-size:${fsOra}px;font-weight:700;color:${textColor};font-variant-numeric:tabular-nums">${hms}</span>
      </div>
    </div>`;
  }

  if (stile === 'flip') {
    // sfondo_ora = colore delle card (una card per cifra)
    const chars = hms.split('');
    const bgFill = cfg.sfondo_ora || '#141414';
    const bgOpacity = cfg.sfondo_ora_opacity ?? 1;
    return `<div class="time-widget" data-stile="flip" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;gap:${Math.round(4*s)}px">
      ${chars.map(ch => ch===':'
        ? `<span style="font-size:${fsOra}px;font-weight:700;color:${textColor};opacity:.5">:</span>`
        : `<div class="time-flip-digit" style="position:relative;border-radius:6px;padding:${Math.round(4*s)}px ${Math.round(8*s)}px;transition:transform .15s ease">
             <div style="position:absolute;inset:0;background:${bgFill};opacity:${bgOpacity};border-radius:6px"></div>
             <span class="time-flip-text" style="position:relative;color:${textColor};font-size:${fsOra}px;font-weight:800;font-variant-numeric:tabular-nums">${ch}</span>
           </div>`
      ).join('')}
    </div>`;
  }

  if (stile === 'bar_below') {
    // sfondo_ora qui e' il bagliore (glow) esterno dei numeri, non un riquadro
    const colorA = cfg.colore_barra_a || '#22c55e';
    const colorB = cfg.colore_barra_b || '#2dd4bf';
    const glowColor = cfg.sfondo_ora || null;
    const glowOpacity = cfg.sfondo_ora_opacity ?? 0.7;
    const glowShadow = glowColor ? `text-shadow:0 0 ${Math.round(10*s)}px ${hexToRgba(glowColor,glowOpacity)},0 0 ${Math.round(22*s)}px ${hexToRgba(glowColor,glowOpacity*0.6)};` : '';
    return `<div class="time-widget" data-stile="bar_below" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center">
      <div style="text-align:center;width:80%">
        <div class="time-hms" style="font-size:${fsOra}px;font-weight:700;color:${textColor};font-variant-numeric:tabular-nums;${glowShadow}">${hms}</div>
        <div style="width:100%;height:${Math.round(6*s)}px;background:rgba(255,255,255,.15);border-radius:999px;margin-top:${Math.round(6*s)}px;overflow:hidden">
          <div class="time-bar-fill" style="height:100%;width:0%;background:linear-gradient(90deg,${colorA},${colorB});border-radius:999px"></div>
        </div>
      </div>
    </div>`;
  }

  if (stile === 'ring_seconds') {
    // Formato in riga unica: hh:mm: + secondi grandi dentro l'anello.
    // L'anello si dimensiona sul font dei secondi, non trabocca mai.
    const ringPx = Math.max(60, Math.round(fsOra*2.4));
    const fsHm = Math.round(fsOra*0.75);
    return `<div class="time-widget" data-stile="ring_seconds" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;gap:${Math.round(8*s)}px">
      <span class="time-hm" style="font-size:${fsHm}px;font-weight:600;color:${textColor};font-variant-numeric:tabular-nums">${hh}:${mm}:</span>
      <div style="position:relative;width:${ringPx}px;height:${ringPx}px;flex-shrink:0">
        <svg viewBox="0 0 100 100" style="width:100%;height:100%;transform:rotate(-90deg)">
          <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="8"/>
          <circle class="time-ring-progress" data-peri="${2*Math.PI*42}" cx="50" cy="50" r="42" fill="none" stroke="${progressColor}" stroke-width="8" stroke-linecap="round" stroke-dasharray="${2*Math.PI*42}" stroke-dashoffset="${2*Math.PI*42}"/>
        </svg>
        <span class="time-ss" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:${fsOra}px;font-weight:700;color:${textColor};font-variant-numeric:tabular-nums">${ss}</span>
      </div>
    </div>`;
  }

  if (stile === 'neon_gym') {
    return `<div class="time-widget" data-stile="neon_gym" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center">
      <div style="text-align:center;padding:${Math.round(10*s)}px ${Math.round(18*s)}px;border-radius:14px;background:${cfg.sfondo_ora||'#0a0a0a'}">
        <span class="time-hms" style="font-size:${fsOra}px;font-weight:800;color:${progressColor};text-shadow:0 0 6px ${progressColor},0 0 16px ${progressColor};letter-spacing:1px;font-variant-numeric:tabular-nums;animation:neonPulse 2s ease-in-out infinite">${hms}</span>
      </div>
    </div>`;
  }

  // minimal_apple (default) — sfondo sempre reale e visibile, non solo un placeholder d'anteprima
  const minimalBg = cfg.sfondo_ora || '#000000';
  const minimalOpacity = cfg.sfondo_ora_opacity ?? 0.5;
  return `<div class="time-widget" data-stile="minimal_apple" data-secondi="${showSeconds?1:0}" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center">
    <div style="position:relative;text-align:center;padding:${Math.round(14*s)}px ${Math.round(22*s)}px;border-radius:14px">
      <div style="position:absolute;inset:0;background:${minimalBg};opacity:${minimalOpacity};border-radius:14px"></div>
      <div style="position:relative">
        <div class="time-hms" style="font-size:${fsOra}px;font-weight:300;letter-spacing:2px;color:${textColor};font-variant-numeric:tabular-nums">${hms}</div>
        ${mostraData ? `<div class="time-date" style="font-size:${fsData}px;font-weight:400;color:${textColor};opacity:.55;margin-top:4px;letter-spacing:1px">${dateStr}</div>` : ''}
      </div>
    </div>
  </div>`;
}

// ── Render del widget Corsi Live (3 stili) ────────────────────
// ── Riga a tessere flip stile tabellone stazione ────────────────
// Converte ora e nome corso in singole tessere (come le cifre del widget Orologio),
// con un piccolo scatto d'ingresso quando i dati si aggiornano.
function makeFlapTiles(text, tileSize, extraStyle='') {
  return text.toUpperCase().split('').map((ch,i) => {
    if (ch === ' ') return `<span style="display:inline-block;width:${Math.round(tileSize*0.5)}px"></span>`;
    return `<div class="flap-tile" style="width:${Math.round(tileSize*1.3)}px;height:${Math.round(tileSize*1.6)}px;font-size:${tileSize}px;animation-delay:${i*30}ms;${extraStyle}">${ch}</div>`;
  }).join('');
}

function renderFlapRow(c, i, opts) {
  const { corsoColor, orarioColor, badgeColor, badgeTxt, showBadge, cols, tileSize, scaleFactor:s, colorTestoLive } = opts;
  const isLive = c.stato === 'attivo';
  const bg = isLive ? hexToRgba(badgeColor,0.7) : '#141414';
  const txtColor = isLive ? (colorTestoLive||'#000000') : corsoColor;
  const oraTiles = makeFlapTiles(c.ora, Math.round(tileSize*0.85), `background:${isLive?badgeColor:'#141414'};color:${isLive?(colorTestoLive||'#000000'):orarioColor};margin-right:2px`);
  const corsoTiles = makeFlapTiles(c.corso, tileSize, `background:${bg};color:${txtColor};margin-right:2px`);
  return `<div style="display:flex;align-items:center;gap:${Math.round(6*s)}px;padding:${Math.round(4*s)}px 0;margin-bottom:${Math.round(3*s)}px;${c.stato==='passato'?'opacity:.35;':''}">
    <div style="display:flex;flex-shrink:0;margin-right:${Math.round(14*s)}px">${oraTiles}</div>
    <div style="display:flex;flex-wrap:wrap;flex:1">${corsoTiles}</div>
    ${cols.includes('sala')?`<span style="font-size:${Math.round(tileSize*0.6)}px;color:rgba(255,255,255,.4);font-family:'Space Mono',monospace;flex-shrink:0;margin-left:${Math.round(8*s)}px">${c.sala}</span>`:''}
    ${isLive&&showBadge?`<span style="font-size:${Math.round(tileSize*0.55)}px;font-weight:700;color:${badgeColor};letter-spacing:1px;flex-shrink:0;margin-left:${Math.round(6*s)}px">${badgeTxt}</span>`:''}
  </div>`;
}

function renderCorsiMarkup(cfg, s) {
  const stile = cfg.stile || 'lista';
  const cols = cfg.colonne || ['ora','corso','istruttore'];
  const titoloColor = cfg.colore_titolo || '#ffffff';
  const corsoColor = cfg.colore_corso || '#ffffff';
  const orarioColor = cfg.colore_orario || '#ffffff';
  const badgeColor = cfg.colore_badge || '#F7192E';
  const fsCorso = Math.round((cfg.font_size_corso||18)*s*0.55);
  const badgeTxt = cfg.badge_live || 'LIVE';
  const showBadge = cfg.mostra_badge !== false;
  const maxCorsi = cfg.max_corsi || 4;

  // Dati demo con stato passato/attivo/prossimo, per mostrare tutte le varianti visive
  const demo = [
    {ora:'17:00',corso:'Pilates',istruttore:'Sara',sala:'Sala 2',stato:'passato'},
    {ora:'18:00',corso:'Spinning',istruttore:'Marco',sala:'Sala 1',stato:'attivo'},
    {ora:'19:00',corso:'Yoga',istruttore:'Giulia',sala:'Sala 2',stato:'prossimo'},
    {ora:'20:00',corso:'CrossFit',istruttore:'Luca',sala:'Sala 1',stato:'prossimo'},
  ].slice(0, maxCorsi);

  if (stile === 'lobby') {
    const tickerText = demo.map(c=>`${c.corso} ${c.ora}`).join('   ·   ');
    return `<div style="width:100%;height:100%;background:#000;display:flex;flex-direction:column;overflow:hidden">
      <div style="display:flex;justify-content:space-between;align-items:baseline;padding:${r(10*s)}px ${r(14*s)}px;border-bottom:2px solid ${badgeColor}">
        <span style="font-size:${r(15*s)}px;font-weight:800;color:${titoloColor};text-transform:uppercase;letter-spacing:${r(1*s)}px">${cfg.titolo||'In programma oggi'}</span>
        <span style="font-size:${r(9*s)}px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:1px">Lun 08 Lug</span>
      </div>
      <div style="flex:1;overflow:hidden;padding:${r(6*s)}px ${r(14*s)}px">
        ${demo.map(c=>`
        <div style="display:flex;align-items:center;gap:${r(10*s)}px;padding:${r(6*s)}px 0;${c.stato==='attivo'?`background:${hexToRgba(badgeColor,0.1)};border-left:3px solid ${badgeColor};padding-left:${r(8*s)}px;`:''}${c.stato==='passato'?'opacity:.35;':''}">
          <span style="font-size:${r(11*s)}px;font-weight:300;color:${c.stato==='attivo'?badgeColor:orarioColor};min-width:${r(34*s)}px;letter-spacing:1px">${c.ora}</span>
          <span style="font-size:${fsCorso}px;font-weight:800;color:${corsoColor};text-transform:uppercase;letter-spacing:${r(0.5*s)}px;flex:1">${c.corso}</span>
          ${c.stato==='attivo'&&showBadge?`<span style="display:flex;align-items:center;gap:4px;font-size:${r(8*s)}px;font-weight:700;color:${badgeColor};text-transform:uppercase;letter-spacing:1px"><span style="width:${r(5*s)}px;height:${r(5*s)}px;border-radius:50%;background:${badgeColor};animation:neonPulse 1.4s ease-in-out infinite"></span>${badgeTxt}</span>`:''}
        </div>`).join('')}
      </div>
      <div style="border-top:1px solid rgba(255,255,255,.1);padding:${r(4*s)}px 0;overflow:hidden;position:relative;height:${r(16*s)}px">
        <div style="position:absolute;left:0;white-space:nowrap;font-size:${r(9*s)}px;color:rgba(255,255,255,.5);animation:tickerScrollRTL 14s linear infinite">${tickerText}</div>
      </div>
    </div>`;
  }

  if (stile === 'stazione') {
    const tileSize = Math.max(9, Math.round(fsCorso*0.75));
    return `<div style="width:100%;height:100%;background:#000;padding:${r(8*s)}px;overflow:hidden">
      <div style="font-size:${r(11*s)}px;font-weight:700;color:${titoloColor};text-transform:uppercase;letter-spacing:${r(2*s)}px;margin-bottom:${r(8*s)}px;border-bottom:1px solid rgba(255,255,255,.2);padding-bottom:${r(4*s)}px;font-family:'Space Mono',monospace">${cfg.titolo||'In programma oggi'}</div>
      ${demo.map((c,i)=>renderFlapRow(c,i,{corsoColor,orarioColor,badgeColor,badgeTxt,showBadge,cols,tileSize,scaleFactor:s,colorTestoLive:cfg.colore_testo_live})).join('')}
    </div>`;
  }

  // lista (default) — stile attuale in produzione: bordo sinistro sul corso attivo,
  // passati sfumati, nomi corso in maiuscolo grassetto
  return `<div style="width:100%;height:100%;padding:${r(10*s)}px;overflow:hidden">
    <div style="font-size:${r(16*s*0.6)}px;font-weight:700;color:${titoloColor};margin-bottom:${r(6*s)}px">${cfg.titolo||'In programma oggi'}</div>
    ${demo.map(c=>`
    <div style="display:flex;align-items:center;gap:6px;padding:${r(5*s)}px ${r(4*s)}px;border-bottom:1px solid rgba(255,255,255,.1);${c.stato==='attivo'?`background:${hexToRgba(badgeColor,0.08)};border-left:3px solid ${badgeColor};padding-left:${r(6*s)}px;`:''}${c.stato==='passato'?'opacity:.35;':''}">
      ${cols.includes('ora')?`<span style="font-size:${r(11*s)}px;font-weight:300;color:${c.stato==='attivo'?badgeColor:orarioColor};min-width:${r(32*s)}px;letter-spacing:1px">${c.ora}</span>`:''}
      ${cols.includes('corso')?`<span style="font-size:${fsCorso}px;font-weight:700;color:${corsoColor};text-transform:uppercase;letter-spacing:${r(0.5*s)}px;flex:1">${c.corso}</span>`:''}
      ${c.stato==='attivo'&&showBadge?`<span style="font-size:${r(8*s)}px;font-weight:700;color:#fff;background:${badgeColor};padding:1px ${r(5*s)}px;border-radius:8px;letter-spacing:.5px">${badgeTxt}</span>`:''}
      ${cols.includes('istruttore')?`<span style="font-size:${r(10*s)}px;color:rgba(255,255,255,.5)">${c.istruttore}</span>`:''}
      ${cols.includes('sala')?`<span style="font-size:${r(9*s)}px;color:rgba(255,255,255,.4)">${c.sala}</span>`:''}
    </div>`).join('')}
  </div>`;
}

function layerPreview(layer) {
  const cfg = layer.config || {};
  const wt = WIDGETS[layer.widget_type] || {};
  const s = Math.max(0.3, zoom);
  const fs = Math.max(8, Math.round(12*s));

  switch(layer.widget_type) {

    case 'logo':
      return cfg.file||BRAND.logo
        ? `<img src="/uploads/${cfg.file||BRAND.logo}" style="max-width:${(cfg.logo_size||80)}%;max-height:${(cfg.logo_size||80)}%;object-fit:contain">`
        : `<div style="text-align:center;color:rgba(255,255,255,.4)"><svg width="${r(20*s)}" height="${r(20*s)}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 4px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg><div style="font-size:${fs}px">Nessun logo</div></div>`;

    case 'time': {
      return renderTimeMarkup(cfg, s);
    }

    case 'data': {
      return renderDataMarkup(cfg, s);
    }

    case 'streaming': case 'tv':
      return `<div style="text-align:center;color:rgba(255,255,255,.5)">
        <svg width="${r(26*s)}" height="${r(26*s)}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" style="margin:0 auto 6px"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/><polygon points="10 11 15 13.5 10 16" fill="currentColor" stroke="none"/></svg>
        <div style="font-size:${fs}px">${layer.widget_type==='streaming'?(cfg.nome_canale||'Streaming IPTV'):'Canale TV'}</div>
      </div>`;

    case 'immagine': {
      const imgFiles = (cfg.files&&cfg.files.length) ? cfg.files : (cfg.file?[cfg.file]:[]);
      if (!imgFiles.length) {
        return `<div style="text-align:center;color:rgba(255,255,255,.4)"><svg width="${r(20*s)}" height="${r(20*s)}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 4px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg><div style="font-size:${fs}px">Nessuna immagine</div></div>`;
      }
      return `<div style="position:relative;width:100%;height:100%">
        <img src="/uploads/${imgFiles[0]}" style="width:100%;height:100%;object-fit:${cfg.object_fit||'cover'}">
        ${imgFiles.length>1?`<span style="position:absolute;bottom:${r(4*s)}px;right:${r(4*s)}px;background:rgba(0,0,0,.7);color:#fff;font-size:${r(9*s)}px;font-weight:700;padding:1px ${r(5*s)}px;border-radius:8px">1/${imgFiles.length}</span>`:''}
      </div>`;
    }

    case 'corsi': {
      return renderCorsiMarkup(cfg, s);
    }

    case 'meteo': {
      // Anteprima statica demo (dati reali vivono solo nel player)
      const stile = cfg.stile || 'card';
      const textColor = cfg.text_color || '#ffffff';
      const fsTemp = r((cfg.font_size_temp||32)*s*0.6);
      const showDesc = cfg.mostra_descrizione !== false;
      // Icona e testo secondario proporzionali a fsTemp: crescono insieme alla temperatura
      const iconPxCard = r(fsTemp*1.15);
      const iconPxDett = r(fsTemp*1.25);
      const iconPxMin  = r(fsTemp*0.85);
      const descPx = r(fsTemp*0.35);
      const smallPx = r(fsTemp*0.31);

      if (stile === 'minimal') {
        return `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;gap:${r(8*s)}px">
          ${weatherIconSvg(2, true, iconPxMin)}
          <span style="font-size:${fsTemp}px;font-weight:700;color:${textColor}">22°</span>
        </div>`;
      }

      if (stile === 'dettagliato') {
        return `<div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:${textColor}">
          ${weatherIconSvg(2, true, iconPxDett)}
          <div style="font-size:${fsTemp}px;font-weight:700;margin-top:4px">22°C</div>
          ${showDesc?`<div style="font-size:${descPx}px;opacity:.7">Parzialmente nuvoloso</div>`:''}
          <div style="font-size:${smallPx}px;opacity:.5;margin-top:3px">↑26° ↓17°</div>
          <div style="font-size:${smallPx}px;opacity:.6;margin-top:2px">${cfg.citta||'Città'}</div>
        </div>`;
      }

      // card (default)
      return `<div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:${textColor}">
        ${weatherIconSvg(2, true, iconPxCard)}
        <div style="font-size:${fsTemp}px;font-weight:700;margin-top:2px">22°C</div>
        ${showDesc?`<div style="font-size:${descPx}px;opacity:.7">Parzialmente nuvoloso</div>`:''}
        <div style="font-size:${smallPx}px;opacity:.6;margin-top:2px">${cfg.citta||'Città'}</div>
      </div>`;
    }

    case 'countdown': {
      const numColor = cfg.colore_numeri||'#fff';
      const titleColor = cfg.colore_titolo||'rgba(255,255,255,.7)';
      const stile = cfg.stile || 'classico';
      const vals = ['12','04','23'];
      const labels = ['giorni','ore','min'];

      if (stile === 'flip') {
        const tileSize = r(22*s);
        return `<div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center">
          ${cfg.titolo?`<div style="font-size:${r(11*s)}px;color:${titleColor};margin-bottom:${r(6*s)}px">${cfg.titolo}</div>`:''}
          <div style="display:flex;gap:${r(10*s)}px">
            ${vals.map((v,i)=>`
            <div style="text-align:center">
              <div style="display:flex;gap:2px">${makeFlapTiles(v, tileSize, `background:#141414;color:${numColor}`)}</div>
              <div style="font-size:${r(8*s)}px;color:rgba(255,255,255,.5);margin-top:${r(4*s)}px;text-transform:uppercase;letter-spacing:.05em">${labels[i]}</div>
            </div>`).join('')}
          </div>
        </div>`;
      }

      // classico (default)
      return `<div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center">
        ${cfg.titolo?`<div style="font-size:${r(11*s)}px;color:${titleColor};margin-bottom:4px">${cfg.titolo}</div>`:''}
        <div style="display:flex;gap:${r(6*s)}px;justify-content:center">
          ${vals.map((n,i)=>`<div style="text-align:center"><div style="font-size:${r(20*s)}px;font-weight:700;color:${numColor};font-variant-numeric:tabular-nums">${n}</div><div style="font-size:${r(7*s)}px;color:rgba(255,255,255,.5)">${labels[i]}</div></div>`).join('')}
        </div>
      </div>`;
    }

    case 'info':
      return renderInfoMarkup(cfg, s);

    case 'ticker': {
      const testi = (cfg.testi||['Testo del ticker…']).filter(Boolean);
      const testoUnico = testi.join('   ·   ');
      const fsize = Math.max(9, r((cfg.font_size||20)*s*0.75));
      const speed = Math.max(1.5, 400/(cfg.velocita||60)); // durata animazione in sec, inversamente prop. alla velocità — tetto minimo abbassato per permettere velocità più alte
      return `<div style="width:100%;height:100%;background:${cfg.bg_ticker||'#F7192E'};overflow:hidden;position:relative;display:flex;align-items:center">
        <div class="live-ticker" style="font-size:${fsize}px;color:${cfg.text_color||'#fff'};font-weight:600;white-space:nowrap;position:absolute;left:0;animation:tickerScrollRTL ${speed}s linear infinite">${testoUnico}</div>
      </div>`;
    }

    case 'qrcode': {
      // Dimensione come percentuale del lato piu' piccolo del box, regolabile a mano
      const pct = (cfg.dimensione_qr || 70) / 100;
      const qrSize = r(Math.min(layer.width, layer.height) * s * pct);
      const colTitolo = cfg.colore_titolo || 'rgba(255,255,255,.7)';
      const fsTitolo = r((cfg.font_size_titolo || 16) * s * 0.6);
      return `<div style="text-align:center">
        ${cfg.url
          ? `<img src="https://api.qrserver.com/v1/create-qr-code/?size=${qrSize*2}x${qrSize*2}&data=${encodeURIComponent(cfg.url)}&color=${(cfg.colore_qr||'#000000').replace('#','')}&bgcolor=${(cfg.sfondo_qr||'#ffffff').replace('#','')}" style="width:${qrSize}px;height:${qrSize}px;border-radius:4px">`
          : `<div style="width:${qrSize}px;height:${qrSize}px;background:${cfg.sfondo_qr||'#fff'};border-radius:4px;display:flex;align-items:center;justify-content:center;margin:0 auto">
              <svg width="${r(qrSize*0.75)}" height="${r(qrSize*0.75)}" viewBox="0 0 24 24" fill="${cfg.colore_qr||'#000'}"><path d="M3 3h7v7H3zm1 1v5h5V4zm1 1h3v3H5zm9-2h7v7h-7zm1 1v5h5V4zm1 1h3v3h-3zM3 14h7v7H3zm1 1v5h5v-5zm1 1h3v3H5zm9 1h2v2h-2zm2 2h2v2h-2zm-2 2h2v2h-2zm4-4h2v2h-2zm-4-2h2v2h-2zm4 4h2v2h-2z"/></svg>
            </div>`}
        ${cfg.titolo?`<div style="font-size:${fsTitolo}px;color:${colTitolo};margin-top:4px">${cfg.titolo}</div>`:''}
      </div>`;
    }

    case 'adv':
      return `<div style="text-align:center;color:rgba(255,255,255,.6)">
        <svg width="${r(22*s)}" height="${r(22*s)}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 4px"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        <div style="font-size:${fs}px">ADV Player</div>
      </div>`;

    case 'sidebar': {
      const slides = cfg.slides || [];
      if (!slides.length) {
        return `<div style="text-align:center;color:rgba(255,255,255,.4)"><svg width="${r(20*s)}" height="${r(20*s)}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 6px"><rect x="3" y="3" width="7" height="18" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><div style="font-size:${fs}px">Sidebar vuota</div></div>`;
      }
      // Mostra la prima slide come anteprima statica + indicatori
      const first = slides[0];
      const label = SIDEBAR_WIDGET_LABELS[first.widget_type] || first.widget_type;
      return `<div style="width:100%;height:100%;display:flex;flex-direction:column">
        <div style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;color:rgba(255,255,255,.7)">
          <svg width="${r(18*s)}" height="${r(18*s)}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:6px"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
          <div style="font-size:${fs}px;font-weight:600">${label}</div>
          <div style="font-size:${r(9*s)}px;opacity:.6;margin-top:2px">${first.titolo||''}</div>
        </div>
        <div style="display:flex;gap:3px;justify-content:center;padding:${r(6*s)}px">
          ${slides.map((_,i)=>`<div style="width:${i===0?r(12*s):r(4*s)}px;height:3px;border-radius:2px;background:${i===0?'#fff':'rgba(255,255,255,.3)'}"></div>`).join('')}
        </div>
      </div>`;
    }

    default:
      return `<span style="font-size:${fs}px;color:rgba(255,255,255,.8);font-weight:600">${wt.label||layer.widget_type}</span>`;
  }
}

// ── Live update loop (orologio, ticker, widget Time) ────────────
let tickerOffset = 0;
setInterval(() => {
  const now = new Date();
  const hh = String(now.getHours()).padStart(2,'0');
  const mm = String(now.getMinutes()).padStart(2,'0');
  const ss = String(now.getSeconds()).padStart(2,'0');
  const secFrac = now.getSeconds()/60;

  // Aggiorna orologi (widget Logo+Ora legacy, se presenti)
  document.querySelectorAll('.live-clock').forEach(el => {
    el.textContent = fmtClock(el.dataset.secondi==='1');
  });
  document.querySelectorAll('.live-date').forEach(el => {
    el.textContent = fmtDate();
  });

  // Aggiorna il widget Time nei suoi 6 stili
  document.querySelectorAll('.time-widget').forEach(root => {
    const stile = root.dataset.stile;
    const hmsShown = root.querySelector('.time-hms');

    if (stile === 'flip') {
      // Ogni cifra si aggiorna singolarmente con un piccolo scatto se cambia
      const targetChars = `${hh}:${mm}:${ss}`.split('').filter(c=>c!==':');
      const digitEls = root.querySelectorAll('.time-flip-digit');
      digitEls.forEach((el, i) => {
        const textEl = el.querySelector('.time-flip-text');
        const newVal = targetChars[i];
        if (textEl && textEl.textContent !== newVal) {
          el.style.transform = 'scaleY(.8)';
          setTimeout(()=>{ textEl.textContent = newVal; el.style.transform='scaleY(1)'; }, 90);
        }
      });
    } else if (stile === 'bar_below') {
      const fill = root.querySelector('.time-bar-fill');
      if (fill) fill.style.width = (secFrac*100)+'%';
      if (hmsShown) hmsShown.textContent = `${hh}:${mm}:${ss}`;
    } else if (stile === 'ring_seconds') {
      const ring = root.querySelector('.time-ring-progress');
      const ssEl = root.querySelector('.time-ss');
      const hmEl = root.querySelector('.time-hm');
      if (ring) { const peri=+ring.dataset.peri; ring.style.strokeDashoffset = peri*(1-secFrac); }
      if (ssEl) ssEl.textContent = ss;
      if (hmEl) hmEl.textContent = `${hh}:${mm}:`;
    } else if (stile === 'ring_pill') {
      const ring = root.querySelector('.time-ring-progress');
      if (ring) { const peri=+ring.dataset.peri; ring.style.strokeDashoffset = peri*(1-secFrac); }
      if (hmsShown) hmsShown.textContent = `${hh}:${mm}:${ss}`;
    } else if (stile === 'neon_gym') {
      if (hmsShown) hmsShown.textContent = `${hh}:${mm}:${ss}`;
    } else if (stile === 'minimal_apple') {
      // Rispetta l'impostazione "Mostra secondi": prima il tick sovrascriveva
      // sempre con solo hh:mm, facendo sparire i secondi dopo il primo tick.
      if (hmsShown) hmsShown.textContent = root.dataset.secondi==='1' ? `${hh}:${mm}:${ss}` : `${hh}:${mm}`;
      const dateEl = root.querySelector('.time-date');
      if (dateEl) dateEl.textContent = fmtDate();
    }
  });
}, 1000);

// Il ticker ora usa una keyframe animation CSS pura (@keyframes tickerScroll),
// quindi non serve più calcolare scrollWidth via JS: parte da sola, sempre, anche nel popup anteprima.
function attachTickerAnimation() { /* no-op: animazione gestita interamente da CSS */ }

function r(n) { return Math.round(n); }

// ── Select ───────────────────────────────────────────────────
function selectLayer(id) {
  selId = id;
  renderAll();
  const layer = layers.find(l=>l.id==id);
  if (layer) renderProps(layer);
}

canvas.addEventListener('mousedown', e => {
  if (e.target===canvas || e.target.classList.contains('canvas-grid')) {
    selId=null; renderAll();
    document.getElementById('props-body').innerHTML=`<div class="no-sel"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg><div style="font-size:12px;font-weight:600;margin-bottom:4px">Nessun layer</div><div style="font-size:11px">Clicca un layer per modificarlo</div></div>`;
    document.getElementById('props-title').textContent='Proprietà';
    document.getElementById('props-widget-badge').style.display='none';
  }
});

// Alt+click → cicla layer sovrapposti
function cycleLayerAt(e) {
  const rect = canvas.getBoundingClientRect();
  const mx=(e.clientX-rect.left)/zoom, my=(e.clientY-rect.top)/zoom;
  const hits=layers.filter(l=>l.visible!==false&&mx>=l.pos_x&&mx<=l.pos_x+l.width&&my>=l.pos_y&&my<=l.pos_y+l.height).sort((a,b)=>b.z_index-a.z_index);
  if(!hits.length)return;
  const idx=hits.findIndex(l=>l.id==selId);
  selectLayer(hits[(idx+1)%hits.length].id);
}

// ── Snap guides ──────────────────────────────────────────────
const SNAP_THRESHOLD = 6; // px sul canvas scalato

function clearSnapGuides() {
  canvas.querySelectorAll('.snap-line,.snap-label').forEach(el=>el.remove());
}

function showSnapGuide(type, pos) {
  const line = document.createElement('div');
  line.className = `snap-line ${type}`;
  if (type==='h') line.style.top  = r(pos*zoom)+'px';
  else            line.style.left = r(pos*zoom)+'px';
  canvas.appendChild(line);

  // Label con coordinata pixel reale
  const lbl = document.createElement('div');
  lbl.className = 'snap-label';
  lbl.textContent = Math.round(pos)+'px';
  if (type==='h') {
    lbl.style.top  = (r(pos*zoom)-14)+'px';
    lbl.style.left = '4px';
  } else {
    lbl.style.left = (r(pos*zoom)+3)+'px';
    lbl.style.top  = '4px';
  }
  canvas.appendChild(lbl);
}

function calcSnap(layer, nx, ny) {
  const T = SNAP_THRESHOLD / zoom;
  let sx=nx, sy=ny;
  const snappedX = new Set(), snappedY = new Set();

  // Bordi e centri del layer in movimento
  const myX = [nx, nx+layer.width/2, nx+layer.width];
  const myY = [ny, ny+layer.height/2, ny+layer.height];

  // Sorgenti snap: altri layer + bordi/centro canvas
  const srcX = [0, CW/2, CW];
  const srcY = [0, CH/2, CH];

  layers.filter(l=>l.id!==layer.id&&l.visible!==false).forEach(other=>{
    srcX.push(other.pos_x, other.pos_x+other.width/2, other.pos_x+other.width);
    srcY.push(other.pos_y, other.pos_y+other.height/2, other.pos_y+other.height);
  });

  // Snap X
  let bestDX=T+1, bestSrcX=null, bestMyXIdx=0;
  srcX.forEach(sv=>{
    myX.forEach((mv,i)=>{
      const d=Math.abs(sv-mv);
      if(d<bestDX){bestDX=d;bestSrcX=sv;bestMyXIdx=i;}
    });
  });
  if(bestSrcX!==null&&bestDX<=T){
    sx=bestSrcX-[0,layer.width/2,layer.width][bestMyXIdx];
    snappedX.add(bestSrcX);
  }

  // Snap Y
  let bestDY=T+1, bestSrcY=null, bestMyYIdx=0;
  srcY.forEach(sv=>{
    myY.forEach((mv,i)=>{
      const d=Math.abs(sv-mv);
      if(d<bestDY){bestDY=d;bestSrcY=sv;bestMyYIdx=i;}
    });
  });
  if(bestSrcY!==null&&bestDY<=T){
    sy=bestSrcY-[0,layer.height/2,layer.height][bestMyYIdx];
    snappedY.add(bestSrcY);
  }

  return {sx, sy, snappedX, snappedY};
}

// ── Drag ─────────────────────────────────────────────────────
function startDrag(e, layer) {
  const sx=e.clientX, sy=e.clientY, ox=layer.pos_x, oy=layer.pos_y;

  function move(e) {
    clearSnapGuides();
    let nx=ox+r((e.clientX-sx)/zoom);
    let ny=oy+r((e.clientY-sy)/zoom);

    const {sx:snx, sy:sny, snappedX, snappedY} = calcSnap(layer, nx, ny);
    nx=Math.max(0,Math.min(CW-layer.width, snx));
    ny=Math.max(0,Math.min(CH-layer.height, sny));

    // Mostra guide visive
    snappedX.forEach(v=>showSnapGuide('v',v));
    snappedY.forEach(v=>showSnapGuide('h',v));

    layer.pos_x=nx; layer.pos_y=ny;
    updateEl(layer); updatePropsXY(layer);
  }

  function up(){
    clearSnapGuides();
    document.removeEventListener('mousemove',move);
    document.removeEventListener('mouseup',up);
  }
  document.addEventListener('mousemove',move);
  document.addEventListener('mouseup',up);
}

function updateEl(layer) {
  const el=canvas.querySelector(`[data-id="${layer.id}"]`);
  if(!el)return;
  el.style.left=r(layer.pos_x*zoom)+'px';
  el.style.top=r(layer.pos_y*zoom)+'px';
  el.style.width=r(layer.width*zoom)+'px';
  el.style.height=r(layer.height*zoom)+'px';
  const hint=el.querySelector('[style*="9px"]');
  if(hint)hint.textContent=`${layer.width}×${layer.height}`;
}

// ── Resize ───────────────────────────────────────────────────
function startResize(e, layer, handle) {
  e.preventDefault();
  const sx=e.clientX,sy=e.clientY,ox=layer.pos_x,oy=layer.pos_y,ow=layer.width,oh=layer.height;
  const origRatio = ow/oh;
  const lockedRatio = layer.aspect_lock || null; // es. 16/9, 9/16, o null

  function move(e) {
    clearSnapGuides();
    const dx=r((e.clientX-sx)/zoom), dy=r((e.clientY-sy)/zoom);
    const shiftConstrain = e.shiftKey;
    const ratio = lockedRatio || origRatio;
    const useRatio = lockedRatio || (shiftConstrain ? origRatio : null);

    if(handle.includes('e')) layer.width  = Math.max(40, ow+dx);
    if(handle.includes('s')) layer.height = Math.max(20, oh+dy);
    if(handle.includes('w')){ layer.pos_x=Math.max(0,ox+dx); layer.width=Math.max(40,ow-dx); }
    if(handle.includes('n')){ layer.pos_y=Math.max(0,oy+dy); layer.height=Math.max(20,oh-dy); }

    // Proporzioni bloccate (fisse dal widget) o Shift (mantieni proporzioni originali)
    if (useRatio) {
      if (handle==='se'||handle==='e'||handle==='s') {
        if (Math.abs(dx) >= Math.abs(dy)) layer.height = Math.max(20, r(layer.width/useRatio));
        else layer.width = Math.max(40, r(layer.height*useRatio));
      } else if (handle==='sw') {
        layer.height = Math.max(20, r(layer.width/useRatio));
        layer.pos_y  = oy + oh - layer.height;
      } else if (handle==='ne') {
        layer.height = Math.max(20, r(layer.width/useRatio));
        layer.pos_y  = oy + oh - layer.height;
      } else if (handle==='nw') {
        layer.height = Math.max(20, r(layer.width/useRatio));
        layer.pos_y  = oy + oh - layer.height;
      }
    }

    // Snap ai bordi/centri di altri layer e canvas durante il resize
    const {sx:snapX, sy:snapY, snappedX, snappedY} = calcSnapResize(layer, handle);
    if (snapX !== null) {
      if (handle.includes('e')) layer.width  = Math.max(40, snapX - layer.pos_x);
      if (handle.includes('w')) { layer.width = Math.max(40, layer.pos_x + layer.width - snapX); layer.pos_x = snapX; }
      snappedX.forEach(v=>showSnapGuide('v',v));
    }
    if (snapY !== null) {
      if (handle.includes('s')) layer.height = Math.max(20, snapY - layer.pos_y);
      if (handle.includes('n')) { layer.height = Math.max(20, layer.pos_y + layer.height - snapY); layer.pos_y = snapY; }
      snappedY.forEach(v=>showSnapGuide('h',v));
    }

    layer.width  = Math.min(layer.width,  CW-layer.pos_x);
    layer.height = Math.min(layer.height, CH-layer.pos_y);
    updateEl(layer); updatePropsXY(layer);
  }

  function up(){clearSnapGuides();document.removeEventListener('mousemove',move);document.removeEventListener('mouseup',up);}
  document.addEventListener('mousemove',move);
  document.addEventListener('mouseup',up);
}

// Snap durante il resize: controlla solo il bordo che si sta muovendo (non centro/altro lato)
function calcSnapResize(layer, handle) {
  const T = SNAP_THRESHOLD / zoom;
  let snapX=null, snapY=null;
  const snappedX=new Set(), snappedY=new Set();

  const edgeX = handle.includes('e') ? layer.pos_x+layer.width : (handle.includes('w') ? layer.pos_x : null);
  const edgeY = handle.includes('s') ? layer.pos_y+layer.height : (handle.includes('n') ? layer.pos_y : null);

  const srcX=[0,CW/2,CW], srcY=[0,CH/2,CH];
  layers.filter(l=>l.id!==layer.id&&l.visible!==false).forEach(o=>{
    srcX.push(o.pos_x,o.pos_x+o.width/2,o.pos_x+o.width);
    srcY.push(o.pos_y,o.pos_y+o.height/2,o.pos_y+o.height);
  });

  if (edgeX !== null) {
    let best=T+1, bestV=null;
    srcX.forEach(v=>{const d=Math.abs(v-edgeX); if(d<best){best=d;bestV=v;}});
    if (bestV!==null && best<=T) { snapX=bestV; snappedX.add(bestV); }
  }
  if (edgeY !== null) {
    let best=T+1, bestV=null;
    srcY.forEach(v=>{const d=Math.abs(v-edgeY); if(d<best){best=d;bestV=v;}});
    if (bestV!==null && best<=T) { snapY=bestV; snappedY.add(bestV); }
  }

  return {sx:snapX, sy:snapY, snappedX, snappedY};
}

function updatePropsXY(layer) {
  ['pos_x','pos_y','width','height'].forEach(k=>{
    const el=document.querySelector(`[data-prop="${k}"]`);
    if(el)el.value=layer[k];
  });
}

function setAspectLock(ratio, btn) {
  const layer = layers.find(l=>l.id==selId);
  if (!layer) return;
  layer.aspect_lock = ratio;
  document.querySelectorAll('.ratio-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  if (ratio) {
    layer.height = Math.max(20, r(layer.width/ratio));
    layer.height = Math.min(layer.height, CH-layer.pos_y);
    updateEl(layer); updatePropsXY(layer);
  }
}

function applyAspectFromW() {
  const layer = layers.find(l=>l.id==selId);
  if (!layer || !layer.aspect_lock) return;
  layer.height = Math.max(20, r(layer.width/layer.aspect_lock));
  updateEl(layer); updatePropsXY(layer);
}

function applyAspectFromH() {
  const layer = layers.find(l=>l.id==selId);
  if (!layer || !layer.aspect_lock) return;
  layer.width = Math.max(40, r(layer.height*layer.aspect_lock));
  updateEl(layer); updatePropsXY(layer);
}

// ── Drop da palette ──────────────────────────────────────────
let dragType=null;
document.querySelectorAll('.witem').forEach(item=>{
  item.addEventListener('dragstart',e=>{
    if(item.classList.contains('locked')){e.preventDefault();return;}
    dragType=item.dataset.type;e.dataTransfer.effectAllowed='copy';
  });
});
canvas.addEventListener('dragover',e=>e.preventDefault());
canvas.addEventListener('drop',e=>{
  e.preventDefault();
  if(!dragType)return;
  const rect=canvas.getBoundingClientRect();
  const def=DEFAULTS[dragType]||{w:400,h:200};
  addLayer(dragType, r((e.clientX-rect.left)/zoom-def.w/2), r((e.clientY-rect.top)/zoom-def.h/2));
  dragType=null;
});

function addLayer(type,x=100,y=100) {
  const def=DEFAULTS[type]||{w:400,h:200};
  const wt=WIDGETS[type]||{};
  const layer={
    id:++nextId,
    nome:(wt.label||type)+' '+(layers.filter(l=>l.widget_type===type).length+1),
    widget_type:type,
    pos_x:Math.max(0,Math.min(CW-def.w,x)),
    pos_y:Math.max(0,Math.min(CH-def.h,y)),
    width:def.w, height:def.h,
    z_index:layers.length+1,
    visible:true,
    adv_safe:false,
    config:{bg_color:wt.color||'#111',bg_opacity:0.9}
  };
  // Auto-fill brand colors
  if(type==='logo_ora'&&BRAND.logo)layer.config.logo_file=BRAND.logo;
  layers.push(layer);
  renderAll();
  selectLayer(layer.id);
}

// ── Layer list ───────────────────────────────────────────────
function renderLayerList() {
  const list=document.getElementById('layer-list');
  const sorted=[...layers].sort((a,b)=>b.z_index-a.z_index);
  list.innerHTML=sorted.map(l=>{
    const wt=WIDGETS[l.widget_type]||{color:'#555',icon:'fa-square'};
    return `<div class="litem ${l.id==selId?'active':''}" data-id="${l.id}" draggable="true" onclick="selectLayer(${l.id})">
      <div style="width:18px;height:18px;border-radius:5px;background:${wt.color}22;color:${wt.color};display:flex;align-items:center;justify-content:center;font-size:9px;flex-shrink:0"><i class="fa-solid ${wt.icon}"></i></div>
      <span class="lname">${l.nome}</span>
      ${l.adv_safe?`<i class="fa-solid fa-lock" style="font-size:9px;color:var(--violet)" title="Sempre visibile durante ADV"></i>`:''}
      <span class="litem-eye" onclick="event.stopPropagation();toggleVis(${l.id})">${l.visible!==false?'👁':'🚫'}</span>
    </div>`;
  }).join('');
  // Drag & drop z-index
  let dragId=null;
  list.querySelectorAll('.litem').forEach(item=>{
    item.addEventListener('dragstart',()=>{dragId=+item.dataset.id;});
    item.addEventListener('dragover',e=>{e.preventDefault();item.classList.add('drag-over');});
    item.addEventListener('dragleave',()=>item.classList.remove('drag-over'));
    item.addEventListener('drop',e=>{
      e.preventDefault();item.classList.remove('drag-over');
      const tid=+item.dataset.id;
      if(dragId===tid)return;
      const a=layers.find(l=>l.id===dragId),b=layers.find(l=>l.id===tid);
      if(a&&b){const tmp=a.z_index;a.z_index=b.z_index;b.z_index=tmp;renderAll();}
    });
  });
}

function toggleVis(id) {
  const l=layers.find(x=>x.id===id);
  if(l){l.visible=l.visible===false?true:false;renderAll();}
}

function toggleAdvSafe(id, checked) {
  const l=layers.find(x=>x.id===id);
  if(l){l.adv_safe=checked;renderProps(l);}
}

// ── Props panel ──────────────────────────────────────────────
function renderProps(layer) {
  const wt=WIDGETS[layer.widget_type]||{label:layer.widget_type,color:'#555',icon:'fa-square'};
  const badge=document.getElementById('props-widget-badge');
  badge.textContent=wt.label;
  badge.style.display='inline-block';
  document.getElementById('props-title').textContent=layer.nome;

  const body=document.getElementById('props-body');
  body.innerHTML=`
    <!-- Posizione -->
    <div class="prop-section">
      <div class="prop-section-title">Geometria</div>
      <div class="prop-row">
        <div><span class="prop-label">X (px)</span><input type="number" class="prop-input" data-prop="pos_x" value="${layer.pos_x}" oninput="setProp('pos_x',+this.value)"></div>
        <div><span class="prop-label">Y (px)</span><input type="number" class="prop-input" data-prop="pos_y" value="${layer.pos_y}" oninput="setProp('pos_y',+this.value)"></div>
      </div>
      <div class="prop-row">
        <div><span class="prop-label">W (px)</span><input type="number" class="prop-input" data-prop="width" value="${layer.width}" oninput="setProp('width',+this.value);applyAspectFromW()"></div>
        <div><span class="prop-label">H (px)</span><input type="number" class="prop-input" data-prop="height" value="${layer.height}" oninput="setProp('height',+this.value);applyAspectFromH()"></div>
      </div>
      <div style="font-size:10px;color:var(--on-variant);margin-top:2px;margin-bottom:8px">Grafica: <strong style="color:var(--on-surface)">${layer.width} × ${layer.height} px</strong></div>

      ${['tv','streaming','immagine'].includes(layer.widget_type) ? `
      <span class="prop-label">Blocca proporzioni</span>
      <div style="display:flex;gap:4px">
        <button class="ratio-btn ${layer.aspect_lock===16/9?'active':''}" onclick="setAspectLock(16/9,this)">
          <span>#</span> 16:9
        </button>
        <button class="ratio-btn ${layer.aspect_lock===9/16?'active':''}" onclick="setAspectLock(9/16,this)">
          <span>#</span> 9:16
        </button>
        <button class="ratio-btn ${!layer.aspect_lock?'active':''}" onclick="setAspectLock(null,this)">
          Libero
        </button>
      </div>
      ` : ''}
    </div>

    <!-- Nome e z-index -->
    <div class="prop-section">
      <div class="prop-section-title">Layer</div>
      <span class="prop-label">Nome</span>
      <input type="text" class="prop-input" value="${layer.nome}" oninput="setPropStr('nome',this.value)" style="width:100%;margin-bottom:8px">
      <div class="prop-row">
        <div><span class="prop-label">Z-index</span><input type="number" class="prop-input" value="${layer.z_index}" oninput="setProp('z_index',+this.value);renderAll()"></div>
        <div style="display:flex;align-items:flex-end">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;padding-bottom:1px">
            <input type="checkbox" ${layer.visible!==false?'checked':''} onchange="toggleVis(${layer.id})" style="width:14px;height:14px;accent-color:var(--blue)">
            Visibile
          </label>
        </div>
      </div>
    </div>

    <!-- Lucchetto ADV: forza il layer sempre visibile sopra all'ADV fullscreen -->
    <div class="prop-section">
      <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer">
        <input type="checkbox" ${layer.adv_safe?'checked':''} onchange="toggleAdvSafe(${layer.id},this.checked)" style="width:15px;height:15px;accent-color:var(--violet);margin-top:1px">
        <span>
          <span style="font-size:12px;font-weight:600;color:var(--on-surface);display:flex;align-items:center;gap:5px">
            <i class="fa-solid fa-lock" style="font-size:10px;color:${layer.adv_safe?'var(--violet)':'var(--on-variant)'}"></i>
            Sempre visibile durante ADV
          </span>
          <span style="font-size:10.5px;color:var(--on-variant);display:block;margin-top:2px">Quando l'ADV va a tutto schermo, questo layer resta comunque in sovrimpressione (es. l'orologio)</span>
        </span>
      </label>
    </div>

    <!-- Sfondo (nascosto per widget che gia' hanno un proprio sfondo a tutta area, es. Ticker) -->
    ${layer.widget_type !== 'ticker' ? `
    <div class="prop-section">
      <div class="prop-section-title">Sfondo layer</div>
      <div id="bg-solid-section">
        ${colorPickerHtml('Colore','bg_color',layer.config?.bg_color||wt.color)}
        <span class="prop-label">Opacità</span>
        <input type="range" min="0" max="1" step="0.05" value="${layer.config?.bg_opacity??0.9}" oninput="setCfg('bg_opacity',+this.value);updateElBg()" style="width:100%;margin-bottom:8px">
      </div>
      <div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <input type="checkbox" id="grad-toggle" ${layer.config?.gradient_type?'checked':''} onchange="toggleGradient(this.checked)" style="width:14px;height:14px;accent-color:var(--blue)">
          <label for="grad-toggle" style="font-size:12px;cursor:pointer">Usa gradiente</label>
        </div>
        <div id="grad-section" style="display:${layer.config?.gradient_type?'block':'none'}">
          <div class="grad-row">
            ${colorPickerHtml('Colore A','grad_a',layer.config?.grad_a||wt.color)}
            ${colorPickerHtml('Colore B','grad_b',layer.config?.grad_b||'#000000')}
          </div>
          <div class="grad-dir">
            ${['to right','to bottom','135deg','to top right'].map((d,i)=>
              `<div class="grad-dir-btn ${(layer.config?.gradient_dir||'to right')===d?'active':''}" onclick="setGradDir('${d}',this)">${['→','↓','↗','↘'][i]}</div>`
            ).join('')}
          </div>
          <div class="grad-preview" id="grad-preview" style="background:${layer.config?.grad_a?`linear-gradient(${layer.config.gradient_dir||'to right'},${layer.config.grad_a},${layer.config.grad_b})`:'#555'}"></div>
        </div>
      </div>
    </div>
    ` : ''}

    <!-- Widget config -->
    <div class="prop-section">
      <div class="prop-section-title">Configurazione widget</div>
      ${widgetConfig(layer)}
    </div>

    <div class="prop-section" style="border:none">
      <button class="btn-sm danger" style="width:100%;justify-content:center" onclick="deleteSelected()">Elimina layer</button>
    </div>
  `;
}

function colorPickerHtml(label, key, val, extraCallback='') {
  return `<div class="color-row">
    <div class="cswatch"><input type="color" value="${val||'#111111'}" oninput="setCfg('${key}',this.value);updateElBg();updateGradPreview();${extraCallback}"></div>
    <span class="clabel">${label}</span>
  </div>`;
}

function refreshTickerPreview() {
  const layer = layers.find(l=>l.id==selId);
  if (!layer) return;
  const el = canvas.querySelector(`[data-id="${layer.id}"]`);
  if (!el) return;
  const inner = el.querySelector('.clayer-inner');
  if (inner) inner.innerHTML = layerPreview(layer);
}

function updateElBg() {
  const layer=layers.find(l=>l.id==selId);
  if(!layer)return;
  const el=canvas.querySelector(`[data-id="${layer.id}"]`);
  if(!el)return;
  const bgDiv=el.querySelector('.clayer-bg');
  if(!bgDiv)return;
  const cfg=layer.config||{};
  let bg=cfg.bg_color||WIDGETS[layer.widget_type]?.color||'#111';
  if(cfg.gradient_type&&cfg.grad_a&&cfg.grad_b)bg=`linear-gradient(${cfg.gradient_dir||'to right'},${cfg.grad_a},${cfg.grad_b})`;
  bgDiv.style.background=bg;
  bgDiv.style.opacity=cfg.bg_opacity??0.9;
}

function updateGradPreview() {
  const layer=layers.find(l=>l.id==selId);
  if(!layer)return;
  const c=layer.config||{};
  const p=document.getElementById('grad-preview');
  if(p&&c.grad_a&&c.grad_b)p.style.background=`linear-gradient(${c.gradient_dir||'to right'},${c.grad_a},${c.grad_b})`;
}

function toggleGradient(on) {
  const layer=layers.find(l=>l.id==selId);
  if(!layer)return;
  if(!layer.config)layer.config={};
  layer.config.gradient_type=on?'linear':'';
  document.getElementById('grad-section').style.display=on?'block':'none';
  updateElBg();
}

function setGradDir(dir, btn) {
  document.querySelectorAll('.grad-dir-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  setCfg('gradient_dir',dir);
  updateElBg();updateGradPreview();
}

function widgetConfig(layer) {
  const c=layer.config||{};
  const libBtn=(key,label)=>`<button class="btn-sm" style="width:100%;justify-content:center;margin-bottom:8px" onclick="openLib('${key}')">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
    ${label}
  </button>`;
  const filePreview=(file)=>file?`<img src="/uploads/${file}" style="width:100%;height:50px;object-fit:contain;border-radius:5px;border:1px solid var(--outline-var);margin-bottom:8px;background:var(--surface-mid)">`:'' ;

  const configs = {
    logo: `
      ${filePreview(c.file||BRAND.logo)}
      ${libBtn('file','Scegli logo dalla libreria')}
      <span class="prop-label">Dimensione (%)</span>
      <input type="range" min="20" max="100" value="${c.logo_size||80}" oninput="setCfg('logo_size',+this.value)" style="width:100%;margin-bottom:8px">
      <span class="prop-label">Allineamento</span>
      <select class="prop-input" onchange="setCfg('align',this.value)" style="width:100%">
        <option value="left" ${c.align==='left'?'selected':''}>Sinistra</option>
        <option value="center" ${(c.align||'center')==='center'?'selected':''}>Centro</option>
        <option value="right" ${c.align==='right'?'selected':''}>Destra</option>
      </select>`,

    time: `
      <span class="prop-label">Template grafico</span>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;margin-bottom:12px">
        ${TIME_PRESETS.map(p=>`
        <div class="preset-swatch ${c._preset===p.id?'active':''}" onclick="applyTimePreset('${p.id}')" title="${p.descrizione}">
          <div class="preset-mini" style="${presetMiniStyle(p)}">${p.nome}</div>
          <div class="preset-swatch-label">${p.nome}</div>
        </div>`).join('')}
      </div>
      <span class="prop-label">Stile layout</span>
      <select class="prop-input" onchange="setCfg('stile',this.value);refreshTimePreview()" style="width:100%;margin-bottom:10px">
        <option value="ring_pill" ${(c.stile||'ring_pill')==='ring_pill'?'selected':''}>Anello pillola</option>
        <option value="flip" ${c.stile==='flip'?'selected':''}>Flip clock stazione</option>
        <option value="bar_below" ${c.stile==='bar_below'?'selected':''}>Barra sotto</option>
        <option value="ring_seconds" ${c.stile==='ring_seconds'?'selected':''}>Anello sui secondi</option>
        <option value="minimal_apple" ${c.stile==='minimal_apple'?'selected':''}>Minimal</option>
        <option value="neon_gym" ${c.stile==='neon_gym'?'selected':''}>Neon Gym</option>
      </select>
      <div style="border-top:1px solid var(--outline-var);margin:4px 0 10px"></div>
      ${colorPickerHtml('Colore testo','text_color',c.text_color||'#ffffff')}
      ${['ring_pill','ring_seconds','neon_gym'].includes(c.stile||'ring_pill') ? colorPickerHtml('Colore progresso','colore_progresso',c.colore_progresso||'#F7192E') : ''}
      ${(c.stile||'ring_pill')==='bar_below' ? `
      <div class="grad-row">
        ${colorPickerHtml('Barra inizio','colore_barra_a',c.colore_barra_a||'#22c55e')}
        ${colorPickerHtml('Barra fine','colore_barra_b',c.colore_barra_b||'#2dd4bf')}
      </div>` : ''}
      ${(() => {
        const stile = c.stile||'ring_pill';
        const labels = {
          ring_pill: ['Sfondo dentro anello', 'Opacità sfondo'],
          flip: ['Colore card', 'Opacità card'],
          bar_below: ['Colore bagliore numeri', 'Intensità bagliore'],
          minimal_apple: ['Sfondo', 'Opacità sfondo'],
          neon_gym: ['Sfondo box', 'Opacità sfondo'],
          ring_seconds: null, // non usa sfondo_ora
        };
        if (stile === 'ring_seconds') return '';
        const [lbl, lblOp] = labels[stile] || ['Sfondo','Opacità sfondo'];
        const defaultColor = stile==='minimal_apple' ? '#000000' : (stile==='flip' ? '#141414' : (stile==='ring_pill' ? '#111111' : '#0a0a0a'));
        return `${colorPickerHtml(lbl,'sfondo_ora',c.sfondo_ora||defaultColor)}
      <span class="prop-label">${lblOp}</span>
      <input type="range" min="0" max="1" step="0.05" value="${c.sfondo_ora_opacity??0.6}" oninput="setCfg('sfondo_ora_opacity',+this.value);refreshTimePreview()" style="width:100%;margin-bottom:10px">`;
      })()}
      <span class="prop-label">Font size ora</span>
      <input type="number" class="prop-input" value="${c.font_size_ora||44}" oninput="setCfg('font_size_ora',+this.value);refreshTimePreview()" style="width:100%;margin-bottom:8px">
      <span class="prop-label">Font size data</span>
      <input type="number" class="prop-input" value="${c.font_size_data||20}" oninput="setCfg('font_size_data',+this.value);refreshTimePreview()" style="width:100%;margin-bottom:8px">
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px">
        <input type="checkbox" ${c.mostra_secondi?'checked':''} onchange="setCfg('mostra_secondi',this.checked);refreshTimePreview()" style="width:14px;height:14px;accent-color:var(--blue)"> Mostra secondi
      </label>
      <div style="font-size:10px;color:var(--on-variant);margin:2px 0 6px">Ignorato da Anello pillola/Flip/Barra/Anello secondi: mostrano sempre i secondi per via del progresso</div>
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;margin-top:4px">
        <input type="checkbox" ${c.mostra_data!==false?'checked':''} onchange="setCfg('mostra_data',this.checked);refreshTimePreview()" style="width:14px;height:14px;accent-color:var(--blue)"> Mostra data
      </label>
      <div style="font-size:10px;color:var(--on-variant);margin-top:2px">Usata solo da Minimal</div>`,

    data: `
      <span class="prop-label">Template grafico</span>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;margin-bottom:12px">
        ${DATA_PRESETS.map(p=>`
        <div class="preset-swatch ${c._preset===p.id?'active':''}" onclick="applyDataPreset('${p.id}')" title="${p.descrizione}">
          <div class="preset-mini" style="background:#0d1420;color:#fff;font-size:9px;border-radius:6px;border:1px solid rgba(255,255,255,.15)">${p.nome}</div>
          <div class="preset-swatch-label">${p.nome}</div>
        </div>`).join('')}
      </div>
      <div style="border-top:1px solid var(--outline-var);margin:2px 0 10px"></div>
      ${(c.stile||'minimale')==='minimale' ? `
      <span class="prop-label">Formato</span>
      <select class="prop-input" onchange="setCfg('formato',this.value);refreshLayerPreview()" style="width:100%;margin-bottom:8px">
        <option value="esteso" ${(c.formato||'esteso')==='esteso'?'selected':''}>Esteso (Lunedì 15 Agosto)</option>
        <option value="breve" ${c.formato==='breve'?'selected':''}>Breve (15/08/2026)</option>
      </select>
      ` : ''}
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;margin-bottom:8px">
        <input type="checkbox" ${c.mostra_anno?'checked':''} onchange="setCfg('mostra_anno',this.checked);refreshLayerPreview()" style="width:14px;height:14px;accent-color:var(--blue)"> Mostra anno
      </label>
      ${colorPickerHtml('Colore testo','text_color',c.text_color||'#ffffff','refreshLayerPreview()')}
      ${(c.stile==='calendario') ? colorPickerHtml('Colore numero','colore_numero',c.colore_numero||'#F7192E','refreshLayerPreview()') : ''}
      <span class="prop-label">Dimensione testo</span>
      <input type="number" class="prop-input" value="${c.font_size||(c.stile==='calendario'?44:20)}" oninput="setCfg('font_size',+this.value);refreshLayerPreview()" style="width:100%">`,

    tv: `<div style="font-size:11px;color:var(--on-variant)">Digitale terrestre. Solo Pi e PC con tuner DVB.</div>`,

    streaming: `
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
        <span class="prop-label" style="margin:0">Canali salvati</span>
        <button type="button" onclick="openChannelManager()" style="border:none;background:none;color:var(--blue);font-size:11px;font-weight:600;cursor:pointer;padding:0">
          <i class="fa-solid fa-gear"></i> Gestisci
        </button>
      </div>
      <select class="prop-input" id="streaming-channel-select" onchange="applyChannelPreset(this.value)" style="width:100%;margin-bottom:10px">
        <option value="">Seleziona un canale...</option>
        ${STREAMING_CHANNELS.map(ch=>`<option value="${ch.nome}" ${c.nome_canale===ch.nome?'selected':''}>${ch.nome}</option>`).join('')}
      </select>
      <div style="border-top:1px solid var(--outline-var);margin:2px 0 10px"></div>
      <span class="prop-label">URL stream (HLS/M3U8/RTSP)</span>
      <input type="url" class="prop-input" id="streaming-url-input" value="${c.url||''}" oninput="setCfg('url',this.value)" placeholder="https://...m3u8" style="width:100%;margin-bottom:8px">
      <span class="prop-label">Nome canale (opzionale)</span>
      <input type="text" class="prop-input" id="streaming-nome-input" value="${c.nome_canale||''}" oninput="setCfg('nome_canale',this.value)" style="width:100%">
      <div style="font-size:10px;color:var(--on-variant);margin-top:8px">I canali salvati sono quelli del tuo pannello di distribuzione — verifica sempre di avere i diritti per usarli.</div>`,

    adv: `
      <div style="font-size:12px;color:var(--on-variant);line-height:1.5">
        Questo widget non ha impostazioni proprie: playlist, giorni, fasce orarie e modalità si gestiscono da
        <a href="/adv.php" style="color:var(--blue-text);font-weight:600">ADV & Scheduling</a>.
      </div>
      <div style="font-size:11px;color:var(--on-variant);margin-top:10px;padding-top:10px;border-top:1px solid var(--outline-var)">
        Per tenere un widget (es. l'Orologio) sempre visibile anche quando l'ADV va a tutto schermo, attiva il lucchetto
        <i class="fa-solid fa-lock" style="color:var(--violet)"></i> "Sempre visibile durante ADV" nel <strong>suo</strong> pannello, non in questo.
      </div>`,

    sidebar: `
      <div style="margin-bottom:12px">
        <span class="prop-label">Durata default slide (sec)</span>
        <input type="number" class="prop-input" value="${c.durata_default||10}" oninput="setCfg('durata_default',+this.value)" style="width:100%;margin-bottom:12px">
        <span class="prop-label">Modalità fullscreen su Android</span>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;margin-bottom:12px">
          <input type="checkbox" ${c.fullscreen_mobile?'checked':''} onchange="setCfg('fullscreen_mobile',this.checked)" style="width:14px;height:14px;accent-color:var(--blue)">
          Le slide vanno fullscreen su dispositivi senza TV
        </label>
      </div>

      <!-- SLIDE LIST -->
      <div style="font-size:10px;font-weight:600;color:var(--on-variant);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Slide (${(c.slides||[]).length})</div>
      <div id="slide-list" style="display:flex;flex-direction:column;gap:4px;margin-bottom:10px">
        ${(c.slides||[]).map((slide,i)=>`
        <div class="slide-item" data-idx="${i}" draggable="true">
          <div class="slide-dot" style="background:${SIDEBAR_WIDGET_COLORS[slide.widget_type]||'#555'}"></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:12px;font-weight:500;color:var(--on-surface);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${SIDEBAR_WIDGET_LABELS[slide.widget_type]||slide.widget_type}</div>
            <div style="font-size:10px;color:var(--on-variant)">${slide.durata||10}s · ${slide.titolo||'Nessun titolo'}</div>
          </div>
          <button onclick="editSlide(${i})" style="border:none;background:none;cursor:pointer;color:var(--on-variant);font-size:11px;padding:2px 6px;border-radius:4px;transition:all .12s" onmouseover="this.style.background='var(--surface-mid)'" onmouseout="this.style.background='none'">✎</button>
          <button onclick="deleteSlide(${i})" style="border:none;background:none;cursor:pointer;color:var(--error);font-size:13px;padding:2px 6px;border-radius:4px" title="Elimina">×</button>
        </div>`).join('')}
        ${(c.slides||[]).length===0?'<div style="font-size:11px;color:var(--on-variant);text-align:center;padding:12px">Nessuna slide. Aggiungine una.</div>':''}
      </div>

      <button class="btn-sm" style="width:100%;justify-content:center;margin-bottom:4px" onclick="addSlide()">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Aggiungi slide
      </button>
    `,

    corsi: `
      <span class="prop-label">Template grafico</span>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:12px">
        ${CORSI_PRESETS.map(p=>`
        <div class="preset-swatch ${c._preset===p.id?'active':''}" onclick="applyCorsiPreset('${p.id}')" title="${p.descrizione}">
          <div class="preset-mini" style="${corsiPresetMiniStyle(p)}">${p.nome}</div>
          <div class="preset-swatch-label">${p.nome}</div>
        </div>`).join('')}
      </div>
      <div style="border-top:1px solid var(--outline-var);margin:4px 0 10px"></div>
      <span class="prop-label">Google Sheet URL</span>
      <input type="url" class="prop-input" value="${c.sheet_url||''}" oninput="setCfg('sheet_url',this.value)" placeholder="Vuoto = usa il foglio della sede" style="width:100%;margin-bottom:8px">
      <span class="prop-label">Max corsi</span>
      <input type="number" class="prop-input" value="${c.max_corsi||4}" oninput="setCfg('max_corsi',+this.value);refreshCorsiPreview()" style="width:100%;margin-bottom:8px">
      <span class="prop-label">Titolo widget</span>
      <input type="text" class="prop-input" value="${c.titolo||'In programma oggi'}" oninput="setCfg('titolo',this.value);refreshCorsiPreview()" style="width:100%;margin-bottom:8px">
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;margin-bottom:8px">
        <input type="checkbox" ${c.mostra_badge!==false?'checked':''} onchange="setCfg('mostra_badge',this.checked);refreshCorsiPreview()" style="width:14px;height:14px;accent-color:var(--blue)"> Mostra badge LIVE
      </label>
      <span class="prop-label">Testo badge LIVE</span>
      <input type="text" class="prop-input" value="${c.badge_live||'LIVE'}" oninput="setCfg('badge_live',this.value);refreshCorsiPreview()" style="width:100%;margin-bottom:8px">
      ${colorPickerHtml('Colore titolo','colore_titolo',c.colore_titolo||'#ffffff','refreshCorsiPreview()')}
      ${colorPickerHtml('Colore orario','colore_orario',c.colore_orario||'#ffffff','refreshCorsiPreview()')}
      ${colorPickerHtml('Colore corso','colore_corso',c.colore_corso||'#ffffff','refreshCorsiPreview()')}
      ${colorPickerHtml('Colore badge','colore_badge',c.colore_badge||'#F7192E','refreshCorsiPreview()')}
      ${(c.stile||'lista')==='stazione' ? colorPickerHtml('Colore testo (corso LIVE)','colore_testo_live',c.colore_testo_live||'#000000','refreshCorsiPreview()') : ''}
      <span class="prop-label">Font size corso</span>
      <input type="number" class="prop-input" value="${c.font_size_corso||18}" oninput="setCfg('font_size_corso',+this.value);refreshCorsiPreview()" style="width:100%;margin-bottom:8px">
      <span class="prop-label">Colonne visibili</span>
      <div style="display:flex;flex-direction:column;gap:4px;margin-top:4px">
        ${['ora','corso','istruttore','sala'].map(col=>`
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px">
          <input type="checkbox" ${(c.colonne||['ora','corso','istruttore']).includes(col)?'checked':''} onchange="toggleCol('${col}',this.checked);refreshCorsiPreview()" style="width:14px;height:14px;accent-color:var(--blue)">
          ${col.charAt(0).toUpperCase()+col.slice(1)}
        </label>`).join('')}
      </div>`,

    meteo: `
      <span class="prop-label">Template grafico</span>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:12px">
        ${METEO_PRESETS.map(p=>`
        <div class="preset-swatch ${c._preset===p.id?'active':''}" onclick="applyMeteoPreset('${p.id}')" title="${p.descrizione}">
          <div class="preset-mini" style="background:#0d1420;color:#fff;font-size:9px;border-radius:6px;border:1px solid rgba(255,255,255,.15)">${p.nome}</div>
          <div class="preset-swatch-label">${p.nome}</div>
        </div>`).join('')}
      </div>
      <div style="border-top:1px solid var(--outline-var);margin:4px 0 10px"></div>
      <span class="prop-label">Città</span>
      <input type="text" class="prop-input" value="${c.citta||''}" oninput="setCfg('citta',this.value);refreshMeteoPreview()" placeholder="Es. Soave" style="width:100%;margin-bottom:8px">
      <div class="prop-row">
        <div><span class="prop-label">Lat</span><input type="number" step="0.001" class="prop-input" placeholder="Vuoto = sede" value="${c.lat||''}" oninput="setCfg('lat',+this.value)"></div>
        <div><span class="prop-label">Lon</span><input type="number" step="0.001" class="prop-input" placeholder="Vuoto = sede" value="${c.lon||''}" oninput="setCfg('lon',+this.value)"></div>
      </div>
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;margin:8px 0">
        <input type="checkbox" ${c.mostra_descrizione!==false?'checked':''} onchange="setCfg('mostra_descrizione',this.checked);refreshMeteoPreview()" style="width:14px;height:14px;accent-color:var(--blue)"> Mostra descrizione condizione
      </label>
      <span class="prop-label">Font size temperatura</span>
      <input type="number" class="prop-input" value="${c.font_size_temp||32}" oninput="setCfg('font_size_temp',+this.value);refreshMeteoPreview()" style="width:100%;margin-bottom:8px">
      ${colorPickerHtml('Colore testo','text_color',c.text_color||'#ffffff','refreshMeteoPreview()')}`,

    countdown: `
      <span class="prop-label">Template grafico</span>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;margin-bottom:12px">
        ${COUNTDOWN_PRESETS.map(p=>`
        <div class="preset-swatch ${c._preset===p.id?'active':''}" onclick="applyCountdownPreset('${p.id}')" title="${p.descrizione}">
          <div class="preset-mini" style="background:#0d1420;color:#fff;font-size:9px;border-radius:6px;border:1px solid rgba(255,255,255,.15);font-family:${p.id==='flip'?"'Space Mono',monospace":'inherit'}">${p.nome}</div>
          <div class="preset-swatch-label">${p.nome}</div>
        </div>`).join('')}
      </div>
      <div style="border-top:1px solid var(--outline-var);margin:2px 0 10px"></div>
      <span class="prop-label">Titolo</span>
      <input type="text" class="prop-input" value="${c.titolo||''}" oninput="setCfg('titolo',this.value);refreshLayerPreview()" style="width:100%;margin-bottom:8px">
      <span class="prop-label">Data target</span>
      <input type="datetime-local" class="prop-input" value="${c.data_target||''}" oninput="setCfg('data_target',this.value)" style="width:100%;margin-bottom:8px">
      ${colorPickerHtml('Colore titolo','colore_titolo',c.colore_titolo||'rgba(255,255,255,.7)','refreshLayerPreview()')}
      ${colorPickerHtml('Colore numeri','colore_numeri',c.colore_numeri||'#ffffff','refreshLayerPreview()')}
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;margin:4px 0 10px">
        <input type="checkbox" ${c.mostra_secondi?'checked':''} onchange="setCfg('mostra_secondi',this.checked);refreshLayerPreview()" style="width:14px;height:14px;accent-color:var(--blue)"> Mostra secondi
      </label>
      <div style="border-top:1px solid var(--outline-var);margin:2px 0 10px"></div>
      <span class="prop-label">Alla scadenza</span>
      <select class="prop-input" onchange="setCfg('on_expiry',this.value);renderProps(layer)" style="width:100%;margin-bottom:8px">
        <option value="zero" ${(c.on_expiry||'zero')==='zero'?'selected':''}>Resta a zero</option>
        <option value="testo" ${c.on_expiry==='testo'?'selected':''}>Mostra un testo</option>
        <option value="nascondi" ${c.on_expiry==='nascondi'?'selected':''}>Nascondi il widget</option>
      </select>
      ${c.on_expiry==='testo' ? `
      <span class="prop-label">Testo alla scadenza</span>
      <input type="text" class="prop-input" value="${c.testo_scadenza||''}" oninput="setCfg('testo_scadenza',this.value)" placeholder="Es. Offerta terminata" style="width:100%">
      ` : ''}`,

    info: `
      <span class="prop-label">Template grafico</span>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:12px">
        ${INFO_PRESETS.map(p=>`
        <div class="preset-swatch ${c._preset===p.id?'active':''}" onclick="applyInfoPreset('${p.id}')" title="${p.descrizione}">
          <div class="preset-mini" style="background:#0d1420;color:#fff;font-size:9px;border-radius:6px;border:1px solid rgba(255,255,255,.15)">${p.nome}</div>
          <div class="preset-swatch-label">${p.nome}</div>
        </div>`).join('')}
      </div>
      <div style="border-top:1px solid var(--outline-var);margin:2px 0 10px"></div>
      <span class="prop-label">Titolo</span>
      <input type="text" class="prop-input" value="${c.titolo||''}" oninput="setCfg('titolo',this.value);refreshLayerPreview()" style="width:100%;margin-bottom:8px">
      <span class="prop-label">Corpo</span>
      <textarea class="prop-input" rows="3" oninput="setCfg('corpo',this.value);refreshLayerPreview()" style="width:100%;resize:vertical;margin-bottom:8px">${c.corpo||c.testo||''}</textarea>

      <div style="border-top:1px solid var(--outline-var);margin:10px 0"></div>
      <span class="prop-label">Icona (libreria)</span>
      <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:5px;margin-bottom:8px">
        ${Object.entries(INFO_ICON_LIBRARY).map(([key,cls])=>`
        <div onclick="setCfg('icona','${key}');setCfg('icon_emoji','');renderProps(layer);refreshLayerPreview()" title="${key}"
          style="aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:6px;cursor:pointer;font-size:13px;background:${(c.icona===key&&!c.icon_emoji)?'var(--blue)':'var(--surface-low)'};color:${(c.icona===key&&!c.icon_emoji)?'#fff':'var(--on-variant)'};border:1px solid var(--outline-var)">
          <i class="fa-solid ${cls}"></i>
        </div>`).join('')}
        <div onclick="setCfg('icona','');setCfg('icon_emoji','');renderProps(layer);refreshLayerPreview()" title="Nessuna"
          style="aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:6px;cursor:pointer;font-size:11px;background:${(!c.icona&&!c.icon_emoji)?'var(--blue)':'var(--surface-low)'};color:${(!c.icona&&!c.icon_emoji)?'#fff':'var(--on-variant)'};border:1px solid var(--outline-var)">
          <i class="fa-solid fa-ban"></i>
        </div>
      </div>
      <span class="prop-label">Oppure emoji personalizzata</span>
      <input type="text" class="prop-input" value="${c.icon_emoji||''}" oninput="setCfg('icon_emoji',this.value);refreshLayerPreview()" placeholder="Es. 🎉 (ha priorita' sulla libreria)" style="width:100%;margin-bottom:4px">
      <div style="font-size:10px;color:var(--on-variant);margin-bottom:10px">Attenzione: le emoji potrebbero non essere visualizzate correttamente su schermi BrightSign. Per la massima compatibilita usa la libreria icone sopra.</div>

      ${colorPickerHtml('Colore titolo','colore_titolo',c.colore_titolo||'#ffffff','refreshLayerPreview()')}
      ${colorPickerHtml('Colore corpo','colore_corpo',c.colore_corpo||'rgba(255,255,255,.75)','refreshLayerPreview()')}
      ${!c.icon_emoji ? colorPickerHtml('Colore icona','colore_icona',c.colore_icona||'#F7192E','refreshLayerPreview()') : ''}

      <div style="border-top:1px solid var(--outline-var);margin:10px 0"></div>
      <span class="prop-label">Dimensioni (lascia vuoto per il default dello stile)</span>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:4px;margin-bottom:10px">
        <div><span style="font-size:10px;color:var(--on-variant)">Titolo</span><input type="number" class="prop-input" value="${c.font_size_titolo||''}" oninput="setCfg('font_size_titolo',this.value?+this.value:null);refreshLayerPreview()" style="width:100%"></div>
        <div><span style="font-size:10px;color:var(--on-variant)">Corpo</span><input type="number" class="prop-input" value="${c.font_size_corpo||''}" oninput="setCfg('font_size_corpo',this.value?+this.value:null);refreshLayerPreview()" style="width:100%"></div>
        <div><span style="font-size:10px;color:var(--on-variant)">Icona</span><input type="number" class="prop-input" value="${c.font_size_icona||''}" oninput="setCfg('font_size_icona',this.value?+this.value:null);refreshLayerPreview()" style="width:100%"></div>
      </div>

      <div style="border-top:1px solid var(--outline-var);margin:10px 0"></div>
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;margin-bottom:8px">
        <input type="checkbox" ${c.usa_immagine_sfondo?'checked':''} onchange="setCfg('usa_immagine_sfondo',this.checked);renderProps(layer);refreshLayerPreview()" style="width:14px;height:14px;accent-color:var(--blue)"> Usa immagine di sfondo
      </label>
      ${c.usa_immagine_sfondo ? `
      ${filePreview(c.bg_image)}
      ${libBtn('bg_image','Scegli immagine dalla libreria')}
      <span class="prop-label">Oscura sfondo</span>
      <input type="range" min="0" max="1" step="0.05" value="${c.bg_image_oscura??0.5}" oninput="setCfg('bg_image_oscura',+this.value);refreshLayerPreview()" style="width:100%;margin-bottom:4px">
      <div style="font-size:10px;color:var(--on-variant);margin-bottom:8px">Piu alto = testo piu leggibile, immagine piu scura</div>
      ` : ''}`,

    immagine: `
      <span class="prop-label">Immagini (in rotazione se pi\u00f9 di una)</span>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:8px">
        ${(c.files&&c.files.length?c.files:(c.file?[c.file]:[])).map((f,i)=>`
        <div style="position:relative;aspect-ratio:1;border-radius:6px;overflow:hidden;border:1px solid var(--outline-var);background:var(--surface-mid)">
          <img src="/uploads/${f}" style="width:100%;height:100%;object-fit:cover">
          <button type="button" onclick="removeImmagineFile(${i})" title="Rimuovi" style="position:absolute;top:2px;right:2px;width:16px;height:16px;border-radius:50%;background:rgba(0,0,0,.75);color:#fff;border:none;font-size:10px;cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center">×</button>
        </div>`).join('')}
      </div>
      <button type="button" class="btn-sm" style="width:100%;justify-content:center;margin-bottom:10px" onclick="openLib('immagine_files_add')">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Aggiungi immagine
      </button>
      ${(c.files&&c.files.length>1) ? `
      <span class="prop-label">Durata per immagine (sec)</span>
      <input type="number" class="prop-input" value="${c.durata_slide||5}" oninput="setCfg('durata_slide',+this.value)" style="width:100%;margin-bottom:8px">
      ` : ''}
      <span class="prop-label">Adattamento</span>
      <select class="prop-input" onchange="setCfg('object_fit',this.value)" style="width:100%;margin-bottom:8px">
        <option value="cover" ${(c.object_fit||'cover')==='cover'?'selected':''}>Cover</option>
        <option value="contain" ${c.object_fit==='contain'?'selected':''}>Contain</option>
        <option value="fill" ${c.object_fit==='fill'?'selected':''}>Fill</option>
      </select>`,

    qrcode: `
      <span class="prop-label">URL</span>
      <input type="url" class="prop-input" value="${c.url||''}" oninput="setCfg('url',this.value);refreshLayerPreview()" placeholder="https://..." style="width:100%;margin-bottom:8px">
      <span class="prop-label">Titolo</span>
      <input type="text" class="prop-input" value="${c.titolo||''}" oninput="setCfg('titolo',this.value);refreshLayerPreview()" style="width:100%;margin-bottom:8px">
      <span class="prop-label">Dimensione titolo</span>
      <input type="number" class="prop-input" value="${c.font_size_titolo||16}" oninput="setCfg('font_size_titolo',+this.value);refreshLayerPreview()" style="width:100%;margin-bottom:8px">
      <span class="prop-label">Dimensione QR: ${c.dimensione_qr||70}% del box</span>
      <input type="range" min="30" max="100" step="5" value="${c.dimensione_qr||70}" oninput="setCfg('dimensione_qr',+this.value);refreshLayerPreview()" onchange="renderProps(layer)" style="width:100%;margin-bottom:8px">
      ${colorPickerHtml('Colore QR','colore_qr',c.colore_qr||'#000000','refreshLayerPreview()')}
      ${colorPickerHtml('Colore sfondo QR','sfondo_qr',c.sfondo_qr||'#ffffff','refreshLayerPreview()')}
      ${colorPickerHtml('Colore titolo','colore_titolo',c.colore_titolo||'rgba(255,255,255,.7)','refreshLayerPreview()')}
      <div style="font-size:10px;color:var(--on-variant);margin-top:4px">Per una lettura affidabile, mantieni un forte contrasto tra i due colori del QR.</div>`,

    ticker: `
      <span class="prop-label">Testi (uno per riga)</span>
      <textarea class="prop-input" rows="3" oninput="setCfg('testi',this.value.split('\\n'));refreshLayerPreview()" style="width:100%;resize:vertical;margin-bottom:8px">${(c.testi||[]).join('\n')}</textarea>
      ${colorPickerHtml('Colore testo','text_color',c.text_color||'#ffffff','refreshLayerPreview()')}
      ${colorPickerHtml('Colore sfondo','bg_ticker',c.bg_ticker||'#F7192E','refreshLayerPreview()')}
      <span class="prop-label">Velocità (px/sec)</span>
      <input type="number" class="prop-input" value="${c.velocita||60}" oninput="setCfg('velocita',+this.value);refreshLayerPreview()" style="width:100%;margin-bottom:8px">
      <span class="prop-label">Font size</span>
      <input type="number" class="prop-input" value="${c.font_size||20}" oninput="setCfg('font_size',+this.value);refreshLayerPreview()" style="width:100%">`,
  };
  return configs[layer.widget_type] || `<div style="font-size:11px;color:var(--on-variant)">Nessuna configurazione disponibile per questo widget.</div>`;
}

function setProp(key,val){const l=layers.find(x=>x.id==selId);if(l){l[key]=val;updateEl(l);}}
function setPropStr(key,val){const l=layers.find(x=>x.id==selId);if(l){l[key]=val;renderLayerList();document.getElementById('props-title').textContent=val;}}
function setCfg(key,val){const l=layers.find(x=>x.id==selId);if(l){if(!l.config)l.config={};l.config[key]=val;}}

// ── Preset grafici Orologio ────────────────────────────────────
function presetMiniStyle(preset) {
  const cfg = preset.config;
  const textColor = cfg.text_color || '#fff';
  const progressColor = cfg.colore_progresso || '#F7192E';
  switch (cfg.stile) {
    case 'ring_pill':
      return `background:#111;color:${textColor};font-size:11px;border-radius:999px;border:3px solid ${progressColor}`;
    case 'flip':
      return `background:${cfg.sfondo_ora||'#141414'};color:${textColor};font-size:11px;border-radius:6px;border:1px solid rgba(255,255,255,.15)`;
    case 'bar_below':
      return `background:#111;color:${textColor};font-size:11px;border-radius:6px;border-bottom:4px solid ${cfg.colore_barra_a||'#22c55e'}`;
    case 'ring_seconds':
      return `background:#111;color:${textColor};font-size:11px;border-radius:6px;border:2px solid ${progressColor}`;
    case 'neon_gym':
      return `background:${cfg.sfondo_ora||'#0a0a0a'};color:${progressColor};font-size:11px;border-radius:8px;text-shadow:0 0 6px ${progressColor}`;
    default: // minimal_apple
      return `background:transparent;color:${textColor};font-size:11px;border-radius:0;border:1px dashed rgba(255,255,255,.15)`;
  }
}

function applyTimePreset(presetId) {
  const preset = TIME_PRESETS.find(p=>p.id===presetId);
  const layer = layers.find(l=>l.id==selId);
  if (!preset || !layer) return;
  layer.config = { ...layer.config, ...preset.config, _preset: presetId };
  renderProps(layer);
  refreshTimePreview();
}

function refreshTimePreview() {
  const layer = layers.find(l=>l.id==selId);
  if (!layer) return;
  const el = canvas.querySelector(`[data-id="${layer.id}"]`);
  if (!el) return;
  const inner = el.querySelector('.clayer-inner');
  if (inner) inner.innerHTML = layerPreview(layer);
  updateElBg();
}
function toggleCol(col,on){const l=layers.find(x=>x.id==selId);if(!l)return;if(!l.config)l.config={};const c=l.config.colonne||['ora','corso','istruttore'];if(on&&!c.includes(col))c.push(col);if(!on){const i=c.indexOf(col);if(i>-1)c.splice(i,1);}l.config.colonne=c;}

// ── Preset grafici Corsi Live ──────────────────────────────────
function corsiPresetMiniStyle(preset) {
  const cfg = preset.config;
  switch (cfg.stile) {
    case 'lobby':
      return `background:#000;color:${cfg.colore_corso};font-size:9px;border-radius:4px;border-left:3px solid ${cfg.colore_badge}`;
    case 'stazione':
      return `background:#000;color:${cfg.colore_corso};font-size:8px;font-family:monospace;border-radius:2px;border:1px solid rgba(255,255,255,.15)`;
    default: // lista
      return `background:#111;color:${cfg.colore_corso};font-size:9px;border-radius:4px;border-left:3px solid ${cfg.colore_badge}`;
  }
}

function applyCorsiPreset(presetId) {
  const preset = CORSI_PRESETS.find(p=>p.id===presetId);
  const layer = layers.find(l=>l.id==selId);
  if (!preset || !layer) return;
  layer.config = { ...layer.config, ...preset.config, _preset: presetId };
  renderProps(layer);
  refreshCorsiPreview();
}

function refreshCorsiPreview() {
  const layer = layers.find(l=>l.id==selId);
  if (!layer) return;
  const el = canvas.querySelector(`[data-id="${layer.id}"]`);
  if (!el) return;
  const inner = el.querySelector('.clayer-inner');
  if (inner) inner.innerHTML = layerPreview(layer);
}

// ── Preset grafici Meteo ────────────────────────────────────────
function applyMeteoPreset(presetId) {
  const preset = METEO_PRESETS.find(p=>p.id===presetId);
  const layer = layers.find(l=>l.id==selId);
  if (!preset || !layer) return;
  layer.config = { ...layer.config, ...preset.config, _preset: presetId };
  renderProps(layer);
  refreshMeteoPreview();
}

function applyCountdownPreset(presetId) {
  const preset = COUNTDOWN_PRESETS.find(p=>p.id===presetId);
  const layer = layers.find(l=>l.id==selId);
  if (!preset || !layer) return;
  layer.config = { ...layer.config, ...preset.config, _preset: presetId };
  renderProps(layer);
  refreshLayerPreview();
}

function applyInfoPreset(presetId) {
  const preset = INFO_PRESETS.find(p=>p.id===presetId);
  const layer = layers.find(l=>l.id==selId);
  if (!preset || !layer) return;
  layer.config = { ...layer.config, ...preset.config, _preset: presetId };
  renderProps(layer);
  refreshLayerPreview();
}

function applyDataPreset(presetId) {
  const preset = DATA_PRESETS.find(p=>p.id===presetId);
  const layer = layers.find(l=>l.id==selId);
  if (!preset || !layer) return;
  layer.config = { ...layer.config, ...preset.config, _preset: presetId };
  renderProps(layer);
  refreshLayerPreview();
}

function refreshMeteoPreview() {
  const layer = layers.find(l=>l.id==selId);
  if (!layer) return;
  const el = canvas.querySelector(`[data-id="${layer.id}"]`);
  if (!el) return;
  const inner = el.querySelector('.clayer-inner');
  if (inner) inner.innerHTML = layerPreview(layer);
}

// ── Library modal ────────────────────────────────────────────
let libKey=null;
function openLib(key){libKey=key;document.getElementById('lib-modal').style.display='flex';}
function filterLib(q){
  document.querySelectorAll('#lib-grid .lib-item').forEach(item=>{
    item.style.display=item.dataset.name.toLowerCase().includes(q.toLowerCase())?'':'none';
  });
}
function selectLibItem(el){
  document.querySelectorAll('.lib-item').forEach(i=>i.classList.remove('sel'));
  el.classList.add('sel');
}
function confirmLibSelection(){
  const sel=document.querySelector('.lib-item.sel');
  if(!sel||!libKey)return;
  if (libKey === 'immagine_files_add') {
    const layer = layers.find(l=>l.id==selId);
    if (layer) {
      if (!layer.config) layer.config = {};
      let files = (layer.config.files && layer.config.files.length) ? [...layer.config.files] : (layer.config.file ? [layer.config.file] : []);
      files.push(sel.dataset.file);
      layer.config.files = files;
      renderProps(layer);
      refreshLayerPreview();
    }
  } else {
    setCfg(libKey,sel.dataset.file);
    renderProps(layers.find(l=>l.id==selId));
  }
  document.getElementById('lib-modal').style.display='none';
}

function removeImmagineFile(idx) {
  const layer = layers.find(l=>l.id==selId);
  if (!layer || !layer.config) return;
  let files = (layer.config.files && layer.config.files.length) ? [...layer.config.files] : (layer.config.file ? [layer.config.file] : []);
  files.splice(idx, 1);
  layer.config.files = files;
  renderProps(layer);
  refreshLayerPreview();
}

// ── Toolbar actions ──────────────────────────────────────────
function deleteSelected(){if(!selId)return;if(!confirm('Eliminare?'))return;layers=layers.filter(l=>l.id!==selId);selId=null;renderAll();document.getElementById('props-body').innerHTML='<div class="no-sel"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg><div style="font-size:12px;font-weight:600">Layer eliminato</div></div>';}
function duplicateSelected(){const l=layers.find(x=>x.id==selId);if(!l)return;const d={...l,id:++nextId,nome:l.nome+' (copia)',pos_x:l.pos_x+20,pos_y:l.pos_y+20,z_index:layers.length+1,config:{...l.config}};layers.push(d);renderAll();selectLayer(d.id);}
function bringFwd(){const l=layers.find(x=>x.id==selId);if(l){l.z_index++;renderAll();}}
function sendBwd(){const l=layers.find(x=>x.id==selId);if(l){l.z_index=Math.max(1,l.z_index-1);renderAll();}}
function align(dir){
  const l=layers.find(x=>x.id==selId);if(!l)return;
  if(dir==='left')l.pos_x=0;
  if(dir==='right')l.pos_x=CW-l.width;
  if(dir==='top')l.pos_y=0;
  if(dir==='bottom')l.pos_y=CH-l.height;
  if(dir==='cx')l.pos_x=Math.round((CW-l.width)/2);
  if(dir==='cy')l.pos_y=Math.round((CH-l.height)/2);
  updateEl(l);updatePropsXY(l);
}

// ── Keyboard ─────────────────────────────────────────────────
document.addEventListener('keydown',e=>{
  if(e.target.tagName==='INPUT'||e.target.tagName==='TEXTAREA'||e.target.tagName==='SELECT')return;
  const l=layers.find(x=>x.id==selId);if(!l)return;
  const step=e.shiftKey?10:1;
  if(e.key==='ArrowLeft'){l.pos_x=Math.max(0,l.pos_x-step);updateEl(l);updatePropsXY(l);e.preventDefault();}
  if(e.key==='ArrowRight'){l.pos_x=Math.min(CW-l.width,l.pos_x+step);updateEl(l);updatePropsXY(l);e.preventDefault();}
  if(e.key==='ArrowUp'){l.pos_y=Math.max(0,l.pos_y-step);updateEl(l);updatePropsXY(l);e.preventDefault();}
  if(e.key==='ArrowDown'){l.pos_y=Math.min(CH-l.height,l.pos_y+step);updateEl(l);updatePropsXY(l);e.preventDefault();}
  if(e.key==='Delete'||e.key==='Backspace')deleteSelected();
  if((e.ctrlKey||e.metaKey)&&e.key==='s'){e.preventDefault();saveLayers();}
  if((e.ctrlKey||e.metaKey)&&e.key==='d'){e.preventDefault();duplicateSelected();}
});

// ── Preview ──────────────────────────────────────────────────
let previewSlideTimers = [];
let previewZoomSaved = zoom;

// ── Anteprima: carica il PLAYER REALE in un iframe ──────────────
// Prima ricostruivamo una versione "finta" dei widget solo per l'anteprima,
// che inevitabilmente andava fuori sincrono con la logica vera del player
// (bug su ticker, IPTV che non partiva, ecc). Ora l'anteprima è letteralmente
// lo stesso player che gira sui dispositivi, con gli stessi identici dati:
// zero possibilità di disallineamento.
let previewIframe = null;

function showPreview(){
  const overlay=document.getElementById('preview-overlay');
  const pc=document.getElementById('preview-canvas');
  const maxW=window.innerWidth-48,maxH=window.innerHeight-80;
  const scale=Math.min(maxW/CW,maxH/CH,1);
  const pw=Math.round(CW*scale),ph=Math.round(CH*scale);

  pc.style.cssText=`width:${pw}px;height:${ph}px;background:#111;position:relative;overflow:hidden`;
  pc.innerHTML='';

  previewIframe = document.createElement('iframe');
  previewIframe.id = 'preview-iframe';
  previewIframe.style.cssText = `width:${CW}px;height:${CH}px;border:none;transform:scale(${scale});transform-origin:top left`;
  previewIframe.src = '/player/display_v2.php?preview=1';

  previewIframe.onload = () => {
    // Passa lo stato attuale (non salvato) dell'editor al player dentro l'iframe
    const payload = {
      type: 'pixelbridge-preview',
      template: { canvas_w: CW, canvas_h: CH },
      layers: layers,
      brand: BRAND,
    };
    previewIframe.contentWindow.postMessage(payload, window.location.origin);
  };

  pc.appendChild(previewIframe);
  document.getElementById('preview-info').textContent=`${CW} × ${CH} px · ${Math.round(scale*100)}% scala · anteprima live (player reale)`;
  overlay.style.display='flex';
}

function hidePreview(){
  document.getElementById('preview-overlay').style.display='none';
  // Azzera src per fermare davvero video/audio/timer in corso dentro l'iframe
  if (previewIframe) { previewIframe.src = 'about:blank'; previewIframe = null; }
}
document.getElementById('preview-overlay').addEventListener('keydown',e=>{if(e.key==='Escape')hidePreview();});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&document.getElementById('preview-overlay').style.display==='flex')hidePreview();});

// ── Save ─────────────────────────────────────────────────────
async function saveLayers(){
  const btn=document.getElementById('save-btn');
  btn.textContent='…';btn.disabled=true;
  const fd=new FormData();
  fd.append('action','save_layers');fd.append('template_id',TID);fd.append('layers',JSON.stringify(layers));
  try{
    const res=await fetch(window.location.href,{method:'POST',body:fd});
    const d=await res.json();
    if(d.ok){btn.textContent='✓ Salvato';btn.style.background='var(--success)';setTimeout(()=>{btn.textContent='Salva';btn.style.background='';btn.disabled=false;},2000);}
    else{btn.textContent='Errore';btn.disabled=false;}
  }catch{btn.textContent='Errore';btn.disabled=false;}
}

window.addEventListener('load',initCanvas);

// ── Sidebar slide management ──────────────────────────────────
let editingSlideIdx = null;
let currentSlideType = null;

function getLayer() { return layers.find(l=>l.id==selId); }
function getSlides() { const l=getLayer(); return l?.config?.slides||[]; }

function addSlide() {
  editingSlideIdx = null;
  currentSlideType = null;
  document.getElementById('slide-modal-title').textContent='Nuova slide';
  document.getElementById('slide-titolo').value='';
  document.getElementById('slide-durata').value='10';
  document.getElementById('slide-config-area').innerHTML='';
  document.querySelectorAll('.slide-type-btn').forEach(b=>b.style.cssText=b.style.cssText.replace('border:2px solid var(--blue)','border:1px solid var(--outline-var)').replace('background:var(--blue-bg)','background:var(--surface-low)'));
  document.getElementById('slide-modal').style.display='flex';
}

function editSlide(idx) {
  const slides = getSlides();
  const slide = slides[idx];
  if (!slide) return;
  editingSlideIdx = idx;
  currentSlideType = slide.widget_type;
  document.getElementById('slide-modal-title').textContent='Modifica slide';
  document.getElementById('slide-titolo').value = slide.titolo||'';
  document.getElementById('slide-durata').value = slide.durata||10;
  selectSlideType(slide.widget_type, slide.config||{});
  document.getElementById('slide-modal').style.display='flex';
}

function deleteSlide(idx) {
  if (!confirm('Eliminare questa slide?')) return;
  const layer = getLayer();
  if (!layer.config) layer.config={};
  if (!layer.config.slides) layer.config.slides=[];
  layer.config.slides.splice(idx,1);
  renderProps(layer);
}

function selectSlideType(type, existingConfig={}) {
  currentSlideType = type;
  // Highlight selected
  document.querySelectorAll('.slide-type-btn').forEach(b=>{
    const sel = b.dataset.type===type;
    b.style.border = sel ? '2px solid var(--blue)' : '1px solid var(--outline-var)';
    b.style.background = sel ? 'var(--blue-bg)' : 'var(--surface-low)';
  });
  // Render config fields
  const c = existingConfig;
  const area = document.getElementById('slide-config-area');
  const configs = {
    corsi: `
      <div class="form-group"><label class="form-label">Google Sheet URL</label>
      <input type="url" class="form-input" id="sc-sheet_url" value="${c.sheet_url||''}" placeholder="Vuoto = usa il foglio della sede"></div>
      <div class="form-group"><label class="form-label">Max corsi</label>
      <input type="number" class="form-input" id="sc-max_corsi" value="${c.max_corsi||4}"></div>
      <div class="form-group"><label class="form-label">Titolo widget</label>
      <input type="text" class="form-input" id="sc-titolo_widget" value="${c.titolo_widget||'In programma oggi'}"></div>
      <div class="form-group"><label class="form-label">Template grafico</label>
      <select class="form-input" id="sc-stile" onchange="const nc=collectCurrentSlideConfig();nc.stile=this.value;selectSlideType('corsi',nc)">
        <option value="lista" ${(c.stile||'lista')==='lista'?'selected':''}>Lista Classica</option>
        <option value="lobby" ${c.stile==='lobby'?'selected':''}>Lobby Android</option>
        <option value="stazione" ${c.stile==='stazione'?'selected':''}>Tabellone stazione</option>
      </select></div>
      <div class="form-group" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" id="sc-mostra_badge" ${c.mostra_badge!==false?'checked':''} style="width:15px;height:15px;accent-color:var(--blue)">
        <label class="form-label" style="margin:0" for="sc-mostra_badge">Mostra badge LIVE</label>
      </div>
      <div class="form-group"><label class="form-label">Testo badge LIVE</label>
      <input type="text" class="form-input" id="sc-badge_live" value="${c.badge_live||'LIVE'}"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <div class="form-group"><label class="form-label">Colore titolo</label><input type="color" class="form-input" id="sc-colore_titolo" value="${c.colore_titolo||'#ffffff'}" style="height:38px;cursor:pointer"></div>
        <div class="form-group"><label class="form-label">Colore orario</label><input type="color" class="form-input" id="sc-colore_orario" value="${c.colore_orario||'#ffffff'}" style="height:38px;cursor:pointer"></div>
        <div class="form-group"><label class="form-label">Colore corso</label><input type="color" class="form-input" id="sc-colore_corso" value="${c.colore_corso||'#ffffff'}" style="height:38px;cursor:pointer"></div>
        <div class="form-group"><label class="form-label">Colore badge</label><input type="color" class="form-input" id="sc-colore_badge" value="${c.colore_badge||'#F7192E'}" style="height:38px;cursor:pointer"></div>
      </div>
      ${(c.stile||'lista')==='stazione' ? `<div class="form-group"><label class="form-label">Colore testo (corso LIVE)</label><input type="color" class="form-input" id="sc-colore_testo_live" value="${c.colore_testo_live||'#000000'}" style="height:38px;cursor:pointer"></div>` : ''}
      <div class="form-group"><label class="form-label">Font size corso</label>
      <input type="number" class="form-input" id="sc-font_size_corso" value="${c.font_size_corso||18}"></div>`,
    meteo: `
      <div class="form-group"><label class="form-label">Città</label>
      <input type="text" class="form-input" id="sc-citta" value="${c.citta||''}" placeholder="Es. Soave"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <div class="form-group"><label class="form-label">Lat</label><input type="number" class="form-input" id="sc-lat" value="${c.lat||''}" step="0.001"></div>
        <div class="form-group"><label class="form-label">Lon</label><input type="number" class="form-input" id="sc-lon" value="${c.lon||''}" step="0.001"></div>
      </div>
      <div class="form-group"><label class="form-label">Template grafico</label>
      <select class="form-input" id="sc-stile">
        <option value="card" ${(c.stile||'card')==='card'?'selected':''}>Card Moderna</option>
        <option value="minimal" ${c.stile==='minimal'?'selected':''}>Minimal</option>
        <option value="dettagliato" ${c.stile==='dettagliato'?'selected':''}>Dettagliato</option>
      </select></div>
      <div class="form-group" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" id="sc-mostra_descrizione" ${c.mostra_descrizione!==false?'checked':''} style="width:15px;height:15px;accent-color:var(--blue)">
        <label class="form-label" style="margin:0" for="sc-mostra_descrizione">Mostra descrizione condizione</label>
      </div>`,
    countdown: `
      <div class="form-group"><label class="form-label">Titolo countdown</label>
      <input type="text" class="form-input" id="sc-titolo_countdown" value="${c.titolo_countdown||''}"></div>
      <div class="form-group"><label class="form-label">Data target</label>
      <input type="datetime-local" class="form-input" id="sc-data_target" value="${c.data_target||''}"></div>`,
    info: `
      <div class="form-group"><label class="form-label">Titolo</label>
      <input type="text" class="form-input" id="sc-titolo" value="${c.titolo||''}"></div>
      <div class="form-group"><label class="form-label">Corpo</label>
      <textarea class="form-input" id="sc-corpo" rows="3" style="resize:vertical">${c.corpo||c.testo||''}</textarea></div>
      <div class="form-group"><label class="form-label">Icona</label>
      <select class="form-input" id="sc-icona">
        <option value="" ${!c.icona?'selected':''}>Nessuna</option>
        <option value="info" ${c.icona==='info'?'selected':''}>Info</option>
        <option value="avviso" ${c.icona==='avviso'?'selected':''}>Avviso</option>
        <option value="check" ${c.icona==='check'?'selected':''}>Check</option>
        <option value="stella" ${c.icona==='stella'?'selected':''}>Stella</option>
        <option value="megafono" ${c.icona==='megafono'?'selected':''}>Megafono</option>
      </select></div>
      <div class="form-group"><label class="form-label">Stile</label>
      <select class="form-input" id="sc-stile">
        <option value="semplice" ${(c.stile||'semplice')==='semplice'?'selected':''}>Semplice</option>
        <option value="banner" ${c.stile==='banner'?'selected':''}>Banner</option>
        <option value="poster" ${c.stile==='poster'?'selected':''}>Poster</option>
      </select></div>`,
    immagine: `
      <div class="form-group"><label class="form-label">File immagine</label>
      <select class="form-input" id="sc-file">
        <option value="">Seleziona dalla libreria…</option>
        ${LIBRERIA.filter(l=>['jpg','jpeg','png','webp','gif'].includes(l.file.split('.').pop()?.toLowerCase())).map(l=>`<option value="${l.file}" ${c.file===l.file?'selected':''}>${l.nome}</option>`).join('')}
      </select></div>`,
    qrcode: `
      <div class="form-group"><label class="form-label">URL</label>
      <input type="url" class="form-input" id="sc-url" value="${c.url||''}"></div>
      <div class="form-group"><label class="form-label">Testo sotto il QR</label>
      <input type="text" class="form-input" id="sc-qr_titolo" value="${c.qr_titolo||''}"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <div class="form-group"><label class="form-label">Colore QR</label><input type="color" class="form-input" id="sc-colore_qr" value="${c.colore_qr||'#000000'}" style="height:38px;cursor:pointer"></div>
        <div class="form-group"><label class="form-label">Colore sfondo</label><input type="color" class="form-input" id="sc-sfondo_qr" value="${c.sfondo_qr||'#ffffff'}" style="height:38px;cursor:pointer"></div>
      </div>`,
  };
  area.innerHTML = configs[type] || '';
}

function collectCurrentSlideConfig() {
  const config={};
  document.querySelectorAll('#slide-config-area [id^="sc-"]').forEach(el=>{
    const key=el.id.replace('sc-','');
    if (el.type==='checkbox') config[key]=el.checked;
    else if (el.type==='number') config[key]=+el.value;
    else config[key]=el.value;
  });
  return config;
}

function saveSlide() {
  if (!currentSlideType) { alert('Seleziona un tipo di widget'); return; }
  const layer = getLayer(); if (!layer) return;
  if (!layer.config) layer.config={};
  if (!layer.config.slides) layer.config.slides=[];

  const config = collectCurrentSlideConfig();

  const slide = {
    widget_type: currentSlideType,
    titolo: document.getElementById('slide-titolo').value,
    durata: +document.getElementById('slide-durata').value || 10,
    config
  };

  if (editingSlideIdx !== null) {
    layer.config.slides[editingSlideIdx] = slide;
  } else {
    layer.config.slides.push(slide);
  }

  closeSlideModal();
  renderProps(layer);
}

function closeSlideModal() {
  document.getElementById('slide-modal').style.display='none';
  editingSlideIdx=null; currentSlideType=null;
}

// Drag & drop slide list
document.addEventListener('click', e=>{
  // Re-init drag after renderProps rebuilds slide-list
  const list=document.getElementById('slide-list');
  if(!list)return;
  let dragIdx=null;
  list.querySelectorAll('.slide-item').forEach(item=>{
    item.addEventListener('dragstart',()=>{dragIdx=+item.dataset.idx;});
    item.addEventListener('dragover',e=>{e.preventDefault();item.classList.add('drag-over');});
    item.addEventListener('dragleave',()=>item.classList.remove('drag-over'));
    item.addEventListener('drop',e=>{
      e.preventDefault();item.classList.remove('drag-over');
      const toIdx=+item.dataset.idx;
      if(dragIdx===toIdx||dragIdx===null)return;
      const layer=getLayer();if(!layer)return;
      const slides=layer.config.slides||[];
      const [moved]=slides.splice(dragIdx,1);
      slides.splice(toIdx,0,moved);
      layer.config.slides=slides;
      renderProps(layer);
      dragIdx=null;
    });
  });
});
<?php endif; ?>

// ── Formato modal ────────────────────────────────────────────
function setFmt(fmt){
  const l=fmt==='landscape';
  document.getElementById('canvas_w').value=l?1920:1080;
  document.getElementById('canvas_h').value=l?1080:1920;
  document.getElementById('fmt-l').style.cssText=l?'border:2px solid var(--blue);border-radius:10px;padding:12px;text-align:center;cursor:pointer;background:var(--blue-bg)':'border:2px solid var(--outline-var);border-radius:10px;padding:12px;text-align:center;cursor:pointer';
  document.getElementById('fmt-p').style.cssText=!l?'border:2px solid var(--blue);border-radius:10px;padding:12px;text-align:center;cursor:pointer;background:var(--blue-bg)':'border:2px solid var(--outline-var);border-radius:10px;padding:12px;text-align:center;cursor:pointer';
}
</script>
