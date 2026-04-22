<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
$db = getDB();

$tipi_display = [
    'tv' => ['label'=>'TV', 'icona'=>'📺', 'desc'=>'1920×1080', 'color'=>'#3b82f6'],
];
function tipoDisplayBadge($tipo, $tipi) {
    $t = $tipi[$tipo] ?? $tipi['tv'];
    return '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:'.$t['color'].'22;color:'.$t['color'].';">'.$t['icona'].' '.$t['label'].'</span>';
}
$view  = $_GET['view'] ?? 'lista';
$token = $_GET['token'] ?? '';

// Migrations
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN numero_tv INTEGER DEFAULT NULL"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN indirizzo TEXT DEFAULT ''"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN note TEXT DEFAULT ''"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN lat REAL DEFAULT NULL"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN tipo_display TEXT DEFAULT 'tv'"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN lon REAL DEFAULT NULL"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN reload_richiesto INTEGER DEFAULT 0"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN forza_adv INTEGER DEFAULT 0"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN loop_adv INTEGER DEFAULT 0"); } catch(Exception $e) {}
try { $db->exec("CREATE TABLE IF NOT EXISTS pairing_pending (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    machine TEXT DEFAULT '',
    token TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires DATETIME,
    claimed INTEGER DEFAULT 0
)"); } catch(Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'claim_pairing') {
        $code = preg_replace('/[^0-9]/', '', $_POST['code'] ?? '');
        $tok  = trim($_POST['token_dispositivo'] ?? '');
        if ($code && $tok) $db->prepare("UPDATE pairing_pending SET token=?, claimed=1 WHERE code=?")->execute([$tok, $code]);
        header('Location: dispositivi.php'); exit;
    }

    if ($action === 'nuovo') {
        $nome      = trim($_POST['nome'] ?? '');
        $club      = trim($_POST['club'] ?? '');
        $layout    = $_POST['layout'] ?? 'standard';
        $sheet_url  = trim($_POST['sheet_url'] ?? '');
        $stream_url = trim($_POST['stream_url'] ?? '');
        $numero_tv = (int)($_POST['numero_tv'] ?? 0) ?: null;
        $indirizzo = trim($_POST['indirizzo'] ?? '');
        $note      = trim($_POST['note'] ?? '');
        $lat       = $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
        $lon       = $_POST['lon'] !== '' ? (float)$_POST['lon'] : null;
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($club ?: $nome)));
        $tok  = trim($slug, '-');
        $tok  = substr($tok, 0, 20) . '-' . bin2hex(random_bytes(3));
        if ($nome) {
            $db->prepare("INSERT INTO dispositivi (nome, club, layout, sheet_url, stream_url, token, numero_tv, indirizzo, note, lat, lon, tipo_display) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$nome, $club, $layout, $sheet_url, $stream_url, $tok, $numero_tv, $indirizzo, $note, $lat, $lon, 'tv']);
        }
        header('Location: dispositivi.php'); exit;
    }

    if ($action === 'aggiorna') {
        $tok        = $_POST['token'] ?? '';
        $nome       = trim($_POST['nome'] ?? '');
        $club       = trim($_POST['club'] ?? '');
        $profilo_id = $_POST['profilo_id'] ?? null;
        $layout     = $_POST['layout'] ?? 'standard';
        $sheet_url  = trim($_POST['sheet_url'] ?? '');
        $stream_url = trim($_POST['stream_url'] ?? '');
        $numero_tv  = (int)($_POST['numero_tv'] ?? 0) ?: null;
        $indirizzo  = trim($_POST['indirizzo'] ?? '');
        $note       = trim($_POST['note'] ?? '');
        $lat        = $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
        $lon        = $_POST['lon'] !== '' ? (float)$_POST['lon'] : null;
        $loop_adv = isset($_POST['loop_adv']) ? 1 : 0;
        $db->prepare("UPDATE dispositivi SET nome=?,club=?,profilo_id=?,layout=?,sheet_url=?,stream_url=?,numero_tv=?,indirizzo=?,note=?,lat=?,lon=?,tipo_display='tv',loop_adv=? WHERE token=?")
           ->execute([$nome, $club, $profilo_id ?: null, $layout, $sheet_url, $stream_url, $numero_tv, $indirizzo, $note, $lat, $lon, $loop_adv, $tok]);
        header('Location: dispositivi.php'); exit;
    }

    if ($action === 'elimina') {
        $tok = $_POST['token'] ?? '';
        $db->prepare("DELETE FROM dispositivi WHERE token=?")->execute([$tok]);
        header('Location: dispositivi.php'); exit;
    }

    if ($action === 'layout_rapido') {
        $tok    = $_POST['token'] ?? '';
        $layout = $_POST['layout'] ?? 'standard';
        $db->prepare("UPDATE dispositivi SET layout=? WHERE token=?")->execute([$layout, $tok]);
        header('Location: dispositivi.php'); exit;
    }

    if ($action === 'reload_display') {
        $tok = $_POST['token'] ?? '';
        $db->prepare("UPDATE dispositivi SET reload_richiesto=1 WHERE token=?")->execute([$tok]);
        header('Location: dispositivi.php'); exit;
    }

    if ($action === 'forza_adv') {
        $tok = $_POST['token'] ?? '';
        $db->prepare("UPDATE dispositivi SET forza_adv=1 WHERE token=?")->execute([$tok]);
        header('Location: dispositivi.php'); exit;
    }
}

$dispositivi = $db->query("SELECT d.*, p.nome as profilo_nome, COALESCE(d.tipo_display,'tv') as tipo_display FROM dispositivi d LEFT JOIN profili p ON p.id = d.profilo_id ORDER BY d.club, d.nome")->fetchAll(PDO::FETCH_ASSOC);
$profili     = $db->query("SELECT * FROM profili ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$dev = null;
if ($view === 'modifica' && $token) {
    $s = $db->prepare("SELECT * FROM dispositivi WHERE token=?");
    $s->execute([$token]);
    $dev = $s->fetch(PDO::FETCH_ASSOC);
    if (!$dev) { header('Location: dispositivi.php'); exit; }
}

$db->exec("DELETE FROM pairing_pending WHERE expires < datetime('now')");
$pending = $db->query("SELECT * FROM pairing_pending WHERE claimed=0 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$titolo = 'Dispositivi';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">

<?php if ($view === 'lista'): ?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
    <input type="text" id="search-input" placeholder="🔍  Cerca dispositivo, club, token..."
           oninput="filtraDispositivi(this.value)"
           style="flex:1;min-width:200px;max-width:400px;padding:10px 16px;background:rgba(255,255,255,0.055);
                  border:1px solid rgba(255,255,255,0.10);border-radius:10px;color:var(--sg-white);font-size:14px;">
    <div style="display:flex;gap:8px;margin-left:auto;">
        <button onclick="setVista('table')" id="btn-table" class="btn btn-sm btn-secondary" title="Vista tabella">⊟ Tabella</button>
        <button onclick="setVista('card')"  id="btn-card"  class="btn btn-sm btn-secondary" title="Vista card">⊞ Card</button>
        <a href="dispositivi.php?view=nuovo" class="btn btn-sm">+ Nuovo</a>
    </div>
</div>

<?php if (!empty($pending)): ?>
<div class="box" style="margin-bottom:20px;border:1px solid rgba(232,80,2,0.3);background:rgba(232,80,2,0.04);">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
        <span style="font-size:16px;">🔗</span>
        <div>
            <div style="font-weight:700;color:var(--sg-white);">Pairing in attesa (<?= count($pending) ?>)</div>
            <div style="font-size:11px;color:var(--sg-muted);">Un PC ha mostrato un codice — associalo a un dispositivo</div>
        </div>
    </div>
    <?php foreach ($pending as $p):
        $mins_left = max(0, round((strtotime($p['expires']) - time()) / 60));
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;margin-bottom:8px;flex-wrap:wrap;">
        <div style="font-size:26px;font-weight:900;font-family:monospace;color:var(--sg-white);letter-spacing:4px;"><?= htmlspecialchars($p['code']) ?></div>
        <div style="flex:1;">
            <div style="font-size:13px;color:var(--sg-white);font-weight:600;">💻 <?= htmlspecialchars($p['machine'] ?: 'PC sconosciuto') ?></div>
            <div style="font-size:11px;color:var(--sg-muted);">Scade tra <?= $mins_left ?> min</div>
        </div>
        <form method="POST" style="display:flex;align-items:center;gap:8px;margin:0;">
            <input type="hidden" name="action" value="claim_pairing">
            <input type="hidden" name="code" value="<?= htmlspecialchars($p['code']) ?>">
            <select name="token_dispositivo" required style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);border-radius:8px;color:var(--sg-white);padding:7px 12px;font-size:12px;">
                <option value="">— Associa a dispositivo —</option>
                <?php foreach ($dispositivi as $d): ?>
                <option value="<?= htmlspecialchars($d['token']) ?>"><?= htmlspecialchars($d['club'] ?: $d['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm" style="background:rgba(232,80,2,0.8);">✓ Associa</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── VISTA TABELLA ── -->
<div id="vista-table" class="box" style="padding:0;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="border-bottom:1px solid rgba(255,255,255,0.07);">
                <th style="padding:12px 16px;text-align:left;font-size:11px;color:var(--sg-muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;white-space:nowrap;">Stato</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;color:var(--sg-muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;">Dispositivo</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;color:var(--sg-muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;">Club</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;color:var(--sg-muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;">Ultimo ping</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;color:var(--sg-muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;">Azioni</th>
            </tr>
        </thead>
        <tbody id="table-body">
        <?php foreach ($dispositivi as $d):
            $is_on = !empty($d['ultimo_ping']) && strtotime($d['ultimo_ping']) > time() - 120;
            $ping  = !empty($d['ultimo_ping']) ? date('d/m H:i', strtotime($d['ultimo_ping'])) : '—';
            $playerUrl = 'player/display.php?token='.$d['token'];
        ?>
        <tr class="dev-row" data-search="<?= strtolower(htmlspecialchars($d['nome'].' '.$d['club'].' '.$d['token'])) ?>"
            style="border-bottom:1px solid rgba(255,255,255,0.04);transition:background 0.1s;">
            <td style="padding:12px 16px;">
                <div style="width:8px;height:8px;border-radius:50%;background:<?= $is_on?'var(--sg-green)':'rgba(255,255,255,0.15)' ?>;"></div>
            </td>
            <td style="padding:12px 16px;">
                <div style="font-size:13px;font-weight:600;color:var(--sg-white);"><?= htmlspecialchars($d['nome']) ?></div>
                <div style="font-size:10px;color:var(--sg-muted);font-family:monospace;"><?= htmlspecialchars($d['token']) ?></div>
            </td>
            <td style="padding:12px 16px;">
                <div style="font-size:13px;color:var(--sg-white);"><?= htmlspecialchars($d['club'] ?? '—') ?></div>
                <?php if (!empty($d['indirizzo'])): ?>
                <div style="font-size:11px;color:var(--sg-muted);">📍 <?= htmlspecialchars($d['indirizzo']) ?></div>
                <?php endif; ?>
            </td>
            <td style="padding:12px 16px;font-size:12px;color:<?= $is_on?'var(--sg-green)':'var(--sg-muted)' ?>;"><?= $ping ?></td>
            <td style="padding:12px 16px;">
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                    <a href="<?= $playerUrl ?>" target="_blank" class="btn btn-sm btn-success" style="font-size:11px;padding:3px 8px;" title="Apri player">▶</a>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="forza_adv">
                        <input type="hidden" name="token" value="<?= $d['token'] ?>">
                        <button type="submit" class="btn btn-sm" style="font-size:11px;padding:3px 8px;background:rgba(232,80,2,0.7);" title="Forza ADV ora">⚡</button>
                    </form>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="reload_display">
                        <input type="hidden" name="token" value="<?= $d['token'] ?>">
                        <button type="submit" class="btn btn-sm btn-secondary" style="font-size:11px;padding:3px 8px;" title="Ricarica display">🔄</button>
                    </form>
                    <a href="dispositivi.php?view=modifica&token=<?= $d['token'] ?>" class="btn btn-sm btn-secondary" style="font-size:11px;padding:3px 8px;">✏️</a>
                    <form method="POST" onsubmit="return confirm('Eliminare?')" style="margin:0;">
                        <input type="hidden" name="action" value="elimina">
                        <input type="hidden" name="token" value="<?= $d['token'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" style="font-size:11px;padding:3px 8px;">✕</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($dispositivi)): ?>
        <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--sg-muted);">Nessun dispositivo. <a href="dispositivi.php?view=nuovo" style="color:var(--sg-orange);">Creane uno →</a></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <div id="no-results" style="display:none;padding:32px;text-align:center;color:var(--sg-muted);font-size:13px;">Nessun risultato trovato.</div>
</div>

<!-- ── VISTA CARD ── -->
<div id="vista-card" style="display:none;">
    <?php foreach ($dispositivi as $d):
        $is_on = !empty($d['ultimo_ping']) && strtotime($d['ultimo_ping']) > time() - 120;
        $ping  = !empty($d['ultimo_ping']) ? date('H:i', strtotime($d['ultimo_ping'])) : '—';
        $playerUrl = 'player/display.php?token='.$d['token'];
    ?>
    <div class="dev-row box" data-search="<?= strtolower(htmlspecialchars($d['nome'].' '.$d['club'].' '.$d['token'])) ?>"
         style="margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
                    <div style="width:8px;height:8px;border-radius:50%;background:<?= $is_on?'var(--sg-green)':'rgba(255,255,255,0.15)' ?>;flex-shrink:0;"></div>
                    <span style="font-size:16px;font-weight:700;color:var(--sg-white);"><?= htmlspecialchars($d['nome']) ?></span>
                    <?php if ($d['profilo_nome']): ?>
                    <span class="badge" style="background:#0f3460;color:#5dade2;"><?= htmlspecialchars($d['profilo_nome']) ?></span>
                    <?php endif; ?>
                </div>
                <div style="font-size:12px;color:var(--sg-muted);margin-bottom:3px;">Club: <span style="color:var(--sg-white);"><?= htmlspecialchars($d['club'] ?? '—') ?></span></div>
                <?php if (!empty($d['indirizzo'])): ?>
                <div style="font-size:12px;color:var(--sg-muted);margin-bottom:3px;">📍 <?= htmlspecialchars($d['indirizzo']) ?></div>
                <?php endif; ?>
                <?php if (!empty($d['numero_tv'])): ?>
                <div style="font-size:12px;color:var(--sg-muted);margin-bottom:3px;">📺 TV N° <?= htmlspecialchars($d['numero_tv']) ?></div>
                <?php endif; ?>
                <?php if (!empty($d['note'])): ?>
                <div style="font-size:12px;color:var(--sg-muted);margin-bottom:3px;">📝 <?= htmlspecialchars($d['note']) ?></div>
                <?php endif; ?>
                <div style="font-size:11px;margin-top:6px;">
                    <code style="color:var(--sg-orange);font-size:10px;"><?= htmlspecialchars($d['token']) ?></code>
                    · <span style="color:<?= $is_on?'var(--sg-green)':'var(--sg-muted)' ?>;"><?= $is_on?'Online':'Offline' ?> <?= $ping ?></span>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;border-top:1px solid rgba(255,255,255,0.05);padding-top:12px;">
            <a href="<?= $playerUrl ?>" target="_blank" class="btn btn-sm btn-success">▶ Apri Player</a>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="forza_adv">
                <input type="hidden" name="token" value="<?= $d['token'] ?>">
                <button type="submit" class="btn btn-sm" style="background:rgba(232,80,2,0.7);" title="Forza ADV ora">⚡ Forza ADV</button>
            </form>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="reload_display">
                <input type="hidden" name="token" value="<?= $d['token'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary" title="Ricarica display">🔄 Ricarica</button>
            </form>
            <a href="dispositivi.php?view=modifica&token=<?= $d['token'] ?>" class="btn btn-sm btn-secondary">✏️ Modifica</a>
            <form method="POST" onsubmit="return confirm('Eliminare questo dispositivo?')" style="margin:0;">
                <input type="hidden" name="action" value="elimina">
                <input type="hidden" name="token" value="<?= $d['token'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">🗑️ Elimina</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php elseif ($view === 'nuovo' || $view === 'modifica'): ?>

<div style="max-width:580px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h2 style="margin:0;"><?= $view==='nuovo'?'Nuovo dispositivo':'Modifica: '.htmlspecialchars($dev['nome']) ?></h2>
        <a href="dispositivi.php" class="btn btn-secondary btn-sm">← Torna</a>
    </div>
    <div class="box">
        <form method="POST" id="dev-form">
            <input type="hidden" name="action" value="<?= $view==='nuovo'?'nuovo':'aggiorna' ?>">
            <?php if ($view === 'modifica'): ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($dev['token']) ?>">
            <?php endif; ?>
            <input type="hidden" name="lat" id="f_lat" value="<?= htmlspecialchars($dev['lat'] ?? '') ?>">
            <input type="hidden" name="lon" id="f_lon" value="<?= htmlspecialchars($dev['lon'] ?? '') ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label>Nome *</label>
                    <input type="text" name="nome" required placeholder="Es: TV Sala Pesi" value="<?= htmlspecialchars($dev['nome'] ?? '') ?>">
                </div>
                <div>
                    <label>Club</label>
                    <input type="text" name="club" placeholder="Es: Gymnasium Soave" value="<?= htmlspecialchars($dev['club'] ?? '') ?>">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label>N° TV / Schermo</label>
                    <input type="number" name="numero_tv" placeholder="Es: 1" min="1" value="<?= htmlspecialchars($dev['numero_tv'] ?? '') ?>">
                </div>
                <div></div>
            </div>

            <?php if ($view === 'modifica'): ?>
            <label>Profilo playlist</label>
            <select name="profilo_id">
                <option value="">— Nessuno —</option>
                <?php foreach ($profili as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($dev['profilo_id']??'')==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>


            <label>URL Google Sheet Corsi</label>
            <input type="text" name="sheet_url" placeholder="https://docs.google.com/spreadsheets/..." value="<?= htmlspecialchars($dev['sheet_url'] ?? '') ?>">
            <div style="font-size:11px;color:var(--sg-muted);margin-top:-8px;margin-bottom:14px;">File → Pubblica sul web → CSV → copia link</div>

            <label>URL Stream TV <span style="font-size:10px;color:var(--sg-muted);">(HLS .m3u8)</span></label>
            <div style="display:flex;gap:8px;margin-bottom:6px;">
                <select id="stream_preset" onchange="applicaPresetStream(this.value)" style="flex:1;">
                    <option value="">— Scegli canale preset —</option>
                    <optgroup label="⚽ Sport">
                        <option value="https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=358025&output=7&forceUserAgent=rainet/4.0.5">📺 RAI Sport HD</option>
                        <option value="https://sportitaliaamd.akamaized.net/live/Sportitalia/hls/F59D8EB0332E783633CDDE8E265844975635D24F/index.m3u8">📺 Sportitalia</option>
                        <option value="https://sportsitalia-samsungitaly.amagi.tv/playlist.m3u8">📺 Sportitalia Plus</option>
                        <option value="https://di-g7ij0rwh.vo.lswcdn.net/sportitalia/sisolocalcio.smil/playlist.m3u8">📺 Sportitalia Solocalcio</option>
                        <option value="https://stream.prod-01.milano.nxmedge.net/argocdn/bikechannel/video.m3u8">📺 Bike Channel</option>
                    </optgroup>
                    <optgroup label="📺 Generalisti">
                        <option value="https://viamotionhsi.netplus.ch/live/eds/la7/browser-HLS8/la7.m3u8">📺 La7</option>
                        <option value="https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=2606803&output=16">📺 RAI 1</option>
                        <option value="https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=308718&output=16">📺 RAI 2</option>
                        <option value="https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=308709&output=16">📺 RAI 3</option>
                        <option value="https://mediapolis.rai.it/relinker/relinkerServlet.htm?cont=1&output=16">📺 RAI News 24</option>
                        <option value="https://hls-live-tv2000.akamaized.net/hls/live/2028510/tv2000/master.m3u8">📺 TV2000</option>
                        <option value="https://d31mw7o1gs0dap.cloudfront.net/v1/master/3722c60a815c199d9c0ef36c5b73da68a62b09d1/cc-y5pbi2sq9r609/NOVE_IT.m3u8">📺 Nove</option>
                    </optgroup>
                    <option value="custom">✏️ Inserisci manualmente...</option>
                </select>
            </div>
            <input type="text" name="stream_url" id="stream_url" placeholder="https://esempio.com/stream.m3u8" value="<?= htmlspecialchars($dev['stream_url'] ?? '') ?>">
            <div style="font-size:11px;color:var(--sg-muted);margin-top:-8px;margin-bottom:14px;">
                Lascia vuoto per segnale TV via cavo. RAI Sport e Sportitalia sono gratuiti.
            </div>

            <div style="border-top:1px solid rgba(255,255,255,0.06);padding-top:16px;margin-top:4px;">
                <label style="display:flex;align-items:center;gap:8px;">
                    Indirizzo club
                    <span id="geo-status" style="font-size:10px;color:var(--sg-muted);"></span>
                </label>
                <div style="display:flex;gap:8px;">
                    <input type="text" name="indirizzo" id="f_indirizzo" placeholder="Es: Via Roma 12, Soave VR"
                           value="<?= htmlspecialchars($dev['indirizzo'] ?? '') ?>" style="flex:1;">
                    <button type="button" onclick="geocodifica()" class="btn btn-sm btn-secondary" style="flex-shrink:0;">📍 Geocodifica</button>
                </div>
                <?php if (!empty($dev['lat'])): ?>
                <div style="font-size:11px;color:var(--sg-green);margin-top:4px;">✓ Coordinate salvate: <?= $dev['lat'] ?>, <?= $dev['lon'] ?></div>
                <?php endif; ?>

                <label style="margin-top:12px;">Note</label>
                <textarea name="note" rows="2" style="width:100%;padding:10px;background:rgba(255,255,255,0.055);border:1px solid rgba(255,255,255,0.10);border-radius:10px;color:var(--sg-white);font-size:13px;resize:vertical;"
                          placeholder="Es: TV principale sala corsi"><?= htmlspecialchars($dev['note'] ?? '') ?></textarea>
            </div>

            <div style="display:flex;gap:10px;margin-top:16px;">
                <button type="submit" class="btn"><?= $view==='nuovo'?'✅ Crea dispositivo':'💾 Salva modifiche' ?></button>
                <a href="dispositivi.php" class="btn btn-secondary">Annulla</a>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

</div>

<script>
function setVista(v) {
    document.getElementById('vista-table').style.display = v==='table' ? 'block' : 'none';
    document.getElementById('vista-card').style.display  = v==='card'  ? 'block' : 'none';
    document.getElementById('btn-table').classList.toggle('btn-secondary', v!=='table');
    document.getElementById('btn-card').classList.toggle('btn-secondary',  v!=='card');
    localStorage.setItem('disp_vista', v);
}
(function(){ setVista(localStorage.getItem('disp_vista') || 'table'); })();

function filtraDispositivi(q) {
    q = q.toLowerCase().trim();
    var rows = document.querySelectorAll('.dev-row');
    var found = 0;
    rows.forEach(function(r) {
        var match = !q || r.dataset.search.includes(q);
        r.style.display = match ? '' : 'none';
        if (match) found++;
    });
    document.getElementById('no-results').style.display = (found === 0 && q) ? 'block' : 'none';
}

function geocodifica() {
    var addr = document.getElementById('f_indirizzo').value.trim();
    if (!addr) return;
    var status = document.getElementById('geo-status');
    status.textContent = '⏳ Ricerca...';
    status.style.color = 'var(--sg-muted)';
    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(addr) + '&limit=1', {
        headers: { 'Accept-Language': 'it' }
    })
    .then(r => r.json())
    .then(function(data) {
        if (data && data.length > 0) {
            document.getElementById('f_lat').value = data[0].lat;
            document.getElementById('f_lon').value = data[0].lon;
            status.textContent = '✓ ' + data[0].display_name.split(',').slice(0,2).join(',');
            status.style.color = 'var(--sg-green)';
        } else {
            status.textContent = '❌ Non trovato';
            status.style.color = 'var(--sg-red)';
        }
    })
    .catch(function() {
        status.textContent = '❌ Errore rete';
        status.style.color = 'var(--sg-red)';
    });
}

function applicaPresetStream(val) {
    const input = document.getElementById('stream_url');
    if (!val || val === 'custom') { input.focus(); return; }
    input.value = val;
}

(function() {
    const input  = document.getElementById('stream_url');
    const select = document.getElementById('stream_preset');
    if (!input || !select || !input.value) return;
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value === input.value) { select.selectedIndex = i; break; }
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
