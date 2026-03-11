<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$db = getDB();

/* ══ MIGRATIONS ════════════════════════════════════════════ */
$db->exec("
    CREATE TABLE IF NOT EXISTS club_config (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        nome          TEXT    NOT NULL UNIQUE,
        num_tv_totali INTEGER DEFAULT 0,
        indirizzo     TEXT    DEFAULT '',
        note          TEXT    DEFAULT ''
    )
");
foreach (['club TEXT','layout TEXT DEFAULT \'standard\'','sheet_url TEXT','ultimo_ping TEXT'] as $col) {
    try { $db->exec("ALTER TABLE dispositivi ADD COLUMN $col"); } catch(Exception $e) {}
}

/* ══ ACTIONS ════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $back_club = $_POST['back_club'] ?? '';
    $redir     = 'club.php' . ($back_club ? '?open=' . urlencode($back_club) : '');

    if ($action === 'nuovo') {
        $nome = trim($_POST['nome'] ?? '');
        $club = trim($_POST['club'] ?? '');
        if ($nome) {
            $db->prepare("INSERT INTO dispositivi (nome,club,layout,sheet_url,token) VALUES (?,?,?,?,?)")
               ->execute([$nome,$club,$_POST['layout']??'standard',trim($_POST['sheet_url']??''),bin2hex(random_bytes(16))]);
            if ($club) $db->prepare("INSERT OR IGNORE INTO club_config (nome) VALUES (?)")->execute([$club]);
        }
        header("Location: $redir"); exit;
    }
    if ($action === 'aggiorna') {
        $db->prepare("UPDATE dispositivi SET nome=?,club=?,profilo_id=?,layout=?,sheet_url=? WHERE token=?")
           ->execute([trim($_POST['nome']??''),trim($_POST['club']??''),$_POST['profilo_id']?:null,
                      $_POST['layout']??'standard',trim($_POST['sheet_url']??''),$_POST['token']??'']);
        header("Location: $redir"); exit;
    }
    if ($action === 'elimina') {
        $db->prepare("DELETE FROM dispositivi WHERE token=?")->execute([$_POST['token']??'']);
        header("Location: club.php"); exit;
    }
    if ($action === 'layout_rapido') {
        $db->prepare("UPDATE dispositivi SET layout=? WHERE token=?")->execute([$_POST['layout']??'standard',$_POST['token']??'']);
        header("Location: $redir"); exit;
    }
    if ($action === 'salva_config_club') {
        $cn = trim($_POST['club_nome'] ?? '');
        if ($cn) {
            $db->prepare("
                INSERT INTO club_config (nome,num_tv_totali,indirizzo,note) VALUES (?,?,?,?)
                ON CONFLICT(nome) DO UPDATE SET
                    num_tv_totali=excluded.num_tv_totali,
                    indirizzo=excluded.indirizzo,
                    note=excluded.note
            ")->execute([$cn,(int)($_POST['num_tv_totali']??0),trim($_POST['indirizzo']??''),trim($_POST['note']??'')]);
        }
        header("Location: $redir"); exit;
    }
}

/* ══ DATI ═══════════════════════════════════════════════════ */
$dispositivi = $db->query("
    SELECT d.*, p.nome AS profilo_nome
    FROM dispositivi d LEFT JOIN profili p ON p.id=d.profilo_id
    ORDER BY d.club, d.nome
")->fetchAll(PDO::FETCH_ASSOC);
$profili = $db->query("SELECT * FROM profili ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$club_configs = [];
foreach ($db->query("SELECT * FROM club_config")->fetchAll(PDO::FETCH_ASSOC) as $r)
    $club_configs[$r['nome']] = $r;

/* Stato live per ogni dispositivo PixelBridge */
foreach ($dispositivi as &$d)
    $d['stato_live'] = (!empty($d['ultimo_ping']) && strtotime($d['ultimo_ping'])>time()-120) ? 'online' : 'offline';
unset($d);

/* Raggruppa per club — ogni club ha 1 PixelBridge */
$per_club = [];
foreach ($dispositivi as $d) {
    $c = !empty($d['club']) ? $d['club'] : 'Senza club';
    $per_club[$c][] = $d;
}

/* KPI */
$tot_dispositivi = count($dispositivi);
$pb_online       = count(array_filter($dispositivi, fn($d)=>$d['stato_live']==='online'));
$pb_offline      = $tot_dispositivi - $pb_online;
$n_clubs         = count($per_club);
$tot_tv          = array_sum(array_map(fn($c)=>$club_configs[$c]??(null), array_keys($per_club)) + []);
$tot_tv_cfg      = 0;
foreach ($club_configs as $cfg) $tot_tv_cfg += (int)$cfg['num_tv_totali'];

$open_club  = $_GET['open'] ?? null;
$edit_token = $_GET['modifica'] ?? null;

$titolo = 'Club';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">

<!-- ══ KPI BAR ══════════════════════════════════════════════ -->
<div class="club-kpi-bar" style="margin-bottom:24px;">
    <div class="club-kpi">
        <div class="club-kpi-val orange"><?php echo $n_clubs; ?></div>
        <div class="club-kpi-lbl">Club</div>
    </div>
    <div class="club-kpi">
        <div class="club-kpi-val green"><?php echo $pb_online; ?></div>
        <div class="club-kpi-lbl">PixelBridge online</div>
    </div>
    <div class="club-kpi">
        <div class="club-kpi-val <?php echo $pb_offline>0?'red':'green'; ?>"><?php echo $pb_offline; ?></div>
        <div class="club-kpi-lbl">PixelBridge offline</div>
    </div>
    <div class="club-kpi">
        <div class="club-kpi-val orange"><?php echo $tot_tv_cfg ?: '—'; ?></div>
        <div class="club-kpi-lbl">TV collegate totali</div>
    </div>
</div>

<!-- ══ ACCORDION CLUB ════════════════════════════════════════ -->
<div class="box" style="padding:0;overflow:hidden;">

    <?php if (empty($per_club)): ?>
    <div class="vuoto" style="padding:48px;">
        Nessun dispositivo. <a href="#form-nuovo">+ Aggiungi →</a>
    </div>
    <?php endif; ?>

    <?php foreach ($per_club as $club_nome => $club_disp):
        $cfg      = $club_configs[$club_nome] ?? null;
        $num_tv   = $cfg ? (int)$cfg['num_tv_totali'] : 0;
        // Prendi il primo (e idealmente unico) dispositivo PixelBridge del club
        $pb       = $club_disp[0];
        $is_online = $pb['stato_live'] === 'online';
        $ping_label = !empty($pb['ultimo_ping']) ? date('d/m H:i', strtotime($pb['ultimo_ping'])) : 'Mai';
        $slug     = 'club-' . md5($club_nome);
        $is_open  = ($open_club === $club_nome);
        $is_editing = ($edit_token === $pb['token']);
        $playerUrl = ($pb['layout']??'standard')==='corsi'
            ? 'player/corsi.php?token='.$pb['token']
            : 'player/?token='.$pb['token'];
    ?>

    <div class="acc-item <?php echo $is_open?'acc-open':''; ?>" id="<?php echo $slug; ?>">

        <!-- ── RIGA COMPATTA ──────────────────────────────── -->
        <div class="acc-row" onclick="toggleClub('<?php echo $slug; ?>','<?php echo addslashes($club_nome); ?>')">

            <div class="acc-dot <?php echo $is_online?'acc-dot-on':'acc-dot-off'; ?>"></div>

            <div class="acc-name"><?php echo htmlspecialchars($club_nome); ?></div>

            <!-- Stato PixelBridge -->
            <div class="acc-pb-pill <?php echo $is_online?'pb-on':'pb-off'; ?>">
                <span>PixelBridge</span>
                <strong><?php echo $is_online?'Online':'Offline'; ?></strong>
            </div>

            <!-- TV collegate -->
            <?php if ($num_tv > 0): ?>
            <div class="acc-stat">
                <span class="acc-stat-val"><?php echo $num_tv; ?></span>
                <span class="acc-stat-lbl">TV</span>
            </div>
            <?php endif; ?>

            <!-- Profilo -->
            <?php if (!empty($pb['profilo_nome'])): ?>
            <div class="acc-addr">
                <?php echo htmlspecialchars($pb['profilo_nome']); ?>
            </div>
            <?php endif; ?>

            <!-- Indirizzo -->
            <?php if (!empty($cfg['indirizzo'])): ?>
            <div class="acc-addr" style="color:rgba(245,245,247,0.22);">
                <?php echo htmlspecialchars($cfg['indirizzo']); ?>
            </div>
            <?php endif; ?>

            <!-- Ultimo ping -->
            <div style="margin-left:auto;font-size:11px;color:<?php echo $is_online?'var(--sg-green)':'var(--sg-muted)'; ?>;">
                <?php echo $is_online ? '● ' . $ping_label : '○ ' . $ping_label; ?>
            </div>

            <div class="acc-chevron">›</div>
        </div>

        <!-- ── PANNELLO ESPANSO ────────────────────────────── -->
        <div class="acc-panel">
            <div class="acc-panel-inner">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:1px solid rgba(255,255,255,0.05);">

                    <!-- Colonna sinistra: dispositivo PixelBridge -->
                    <div style="padding:20px;border-right:1px solid rgba(255,255,255,0.05);">

                        <div style="font-size:10px;font-weight:700;color:var(--sg-muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:14px;">
                            Dispositivo PixelBridge
                        </div>

                        <?php if ($is_editing): ?>
                        <!-- Form modifica -->
                        <form method="POST">
                            <input type="hidden" name="action" value="aggiorna">
                            <input type="hidden" name="token" value="<?php echo $pb['token']; ?>">
                            <input type="hidden" name="back_club" value="<?php echo htmlspecialchars($club_nome); ?>">
                            <label>Nome dispositivo</label>
                            <input type="text" name="nome" required value="<?php echo htmlspecialchars($pb['nome']); ?>">
                            <label>Club</label>
                            <input type="text" name="club" value="<?php echo htmlspecialchars($pb['club']??''); ?>">
                            <label>Profilo playlist</label>
                            <select name="profilo_id">
                                <option value="">— Nessuno —</option>
                                <?php foreach ($profili as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo ($pb['profilo_id']??'')==$p['id']?'selected':''; ?>>
                                    <?php echo htmlspecialchars($p['nome']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <label>Layout</label>
                            <select name="layout">
                                <option value="standard" <?php echo ($pb['layout']??'')==='standard'?'selected':''; ?>>Standard</option>
                                <option value="corsi"    <?php echo ($pb['layout']??'')==='corsi'?'selected':''; ?>>Corsi Fitness</option>
                            </select>
                            <label>URL Google Sheet</label>
                            <input type="text" name="sheet_url" value="<?php echo htmlspecialchars($pb['sheet_url']??''); ?>" placeholder="https://docs.google.com/...">
                            <div style="display:flex;gap:8px;margin-top:4px;">
                                <button type="submit" class="btn btn-sm">💾 Salva</button>
                                <a href="club.php?open=<?php echo urlencode($club_nome); ?>#<?php echo $slug; ?>" class="btn btn-secondary btn-sm">Annulla</a>
                            </div>
                        </form>

                        <?php else: ?>
                        <!-- Scheda dispositivo -->
                        <div class="pb-device-card <?php echo $is_online?'pb-device-on':'pb-device-off'; ?>">
                            <div class="pb-device-header">
                                <div class="pb-device-icon">
                                    <?php echo $is_online ? '🟢' : '🔴'; ?>
                                </div>
                                <div>
                                    <div style="font-size:14px;font-weight:700;color:var(--sg-white);">
                                        <?php echo htmlspecialchars($pb['nome']); ?>
                                    </div>
                                    <div style="font-size:11px;color:var(--sg-muted);">
                                        <?php echo $is_online ? 'Online · ping ' . $ping_label : 'Offline · ultimo ping ' . $ping_label; ?>
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex;flex-direction:column;gap:7px;margin-top:14px;">
                                <div class="club-card-row">
                                    <span>Profilo</span>
                                    <strong><?php echo htmlspecialchars($pb['profilo_nome']??'—'); ?></strong>
                                </div>
                                <div class="club-card-row">
                                    <span>Layout</span>
                                    <span class="badge badge-profilo" style="font-size:10px;"><?php echo $pb['layout']??'standard'; ?></span>
                                </div>
                                <div class="club-card-row">
                                    <span>Sheet</span>
                                    <?php if (!empty($pb['sheet_url'])): ?>
                                        <span class="badge badge-online" style="font-size:10px;">✓ Configurato</span>
                                    <?php else: ?>
                                        <span style="font-size:11px;color:var(--sg-muted);">—</span>
                                    <?php endif; ?>
                                </div>
                                <div class="club-card-row">
                                    <span>Token</span>
                                    <code style="font-size:10px;"><?php echo substr($pb['token'],0,14); ?>…</code>
                                </div>
                            </div>

                            <!-- Layout rapido -->
                            <form method="POST" style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.05);">
                                <input type="hidden" name="action" value="layout_rapido">
                                <input type="hidden" name="token" value="<?php echo $pb['token']; ?>">
                                <input type="hidden" name="back_club" value="<?php echo htmlspecialchars($club_nome); ?>">
                                <label style="margin-bottom:4px;font-size:10px;">Cambia layout</label>
                                <select name="layout" onchange="this.form.submit()" style="margin-bottom:0;">
                                    <option value="standard" <?php echo ($pb['layout']??'')==='standard'?'selected':''; ?>>Standard</option>
                                    <option value="corsi"    <?php echo ($pb['layout']??'')==='corsi'?'selected':''; ?>>Corsi Fitness</option>
                                </select>
                            </form>

                            <div style="display:flex;gap:8px;margin-top:12px;">
                                <a href="<?php echo $playerUrl; ?>" target="_blank" class="btn btn-success btn-sm" style="flex:1;text-align:center;">▶ Apri Player</a>
                                <a href="club.php?modifica=<?php echo urlencode($pb['token']); ?>&open=<?php echo urlencode($club_nome); ?>#<?php echo $slug; ?>"
                                   class="btn btn-secondary btn-sm" style="flex:1;text-align:center;">✎ Modifica</a>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Eliminare questo dispositivo?')">
                                    <input type="hidden" name="action" value="elimina">
                                    <input type="hidden" name="token" value="<?php echo $pb['token']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Se il club ha più di 1 dispositivo (caso anomalo) -->
                        <?php if (count($club_disp) > 1): ?>
                        <div style="margin-top:12px;font-size:11px;color:var(--sg-yellow);background:rgba(255,214,10,0.07);border:1px solid rgba(255,214,10,0.15);border-radius:8px;padding:8px 12px;">
                            ⚠ Questo club ha <?php echo count($club_disp); ?> dispositivi registrati.
                            Normalmente ogni club ha un solo PixelBridge.
                        </div>
                        <?php endif; ?>

                    </div><!-- /col sinistra -->

                    <!-- Colonna destra: info club + TV -->
                    <div style="padding:20px;">

                        <div style="font-size:10px;font-weight:700;color:var(--sg-muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:14px;">
                            Configurazione Club
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="salva_config_club">
                            <input type="hidden" name="club_nome" value="<?php echo htmlspecialchars($club_nome); ?>">
                            <input type="hidden" name="back_club" value="<?php echo htmlspecialchars($club_nome); ?>">

                            <!-- N° TV con visualizzazione prominente -->
                            <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:14px 16px;margin-bottom:14px;display:flex;align-items:center;gap:16px;">
                                <div style="font-size:36px;font-weight:800;color:var(--sg-white);letter-spacing:-1px;line-height:1;">
                                    <?php echo $num_tv ?: '?'; ?>
                                </div>
                                <div>
                                    <div style="font-size:12px;font-weight:700;color:var(--sg-white);">TV collegate</div>
                                    <div style="font-size:11px;color:var(--sg-muted);">via uscita HDMI del PixelBridge</div>
                                </div>
                                <div style="margin-left:auto;">
                                    <input type="number" name="num_tv_totali" min="0" max="99"
                                           value="<?php echo $num_tv ?: ''; ?>"
                                           placeholder="N°"
                                           style="width:64px;text-align:center;font-size:16px;font-weight:700;">
                                </div>
                            </div>

                            <label>Indirizzo / sede</label>
                            <input type="text" name="indirizzo"
                                   value="<?php echo htmlspecialchars($cfg['indirizzo']??''); ?>"
                                   placeholder="Es: Via Roma 1, Milano">

                            <label>Note interne</label>
                            <input type="text" name="note"
                                   value="<?php echo htmlspecialchars($cfg['note']??''); ?>"
                                   placeholder="Es: Referente Mario Rossi 333...">

                            <button type="submit" class="btn btn-sm" style="margin-top:4px;">💾 Salva configurazione</button>
                        </form>

                        <!-- Note info -->
                        <?php if (!empty($cfg['note'])): ?>
                        <div style="margin-top:14px;padding:10px 14px;background:rgba(255,255,255,0.03);border-radius:10px;border:1px solid rgba(255,255,255,0.06);font-size:12px;color:var(--sg-text);">
                            📝 <?php echo htmlspecialchars($cfg['note']); ?>
                        </div>
                        <?php endif; ?>

                    </div><!-- /col destra -->

                </div><!-- /grid 2col -->

            </div><!-- /acc-panel-inner -->
        </div><!-- /acc-panel -->

    </div><!-- /acc-item -->
    <?php endforeach; ?>

</div><!-- /box accordion -->

<!-- ══ AGGIUNGI NUOVO DISPOSITIVO ════════════════════════════ -->
<div class="box" id="form-nuovo" style="margin-top:20px;">
    <h2>+ Aggiungi PixelBridge</h2>
    <p style="font-size:13px;color:var(--sg-muted);margin-bottom:18px;margin-top:-8px;">
        Un dispositivo per club. Il sistema monitorerà la connessione del PixelBridge.
    </p>
    <form method="POST">
        <input type="hidden" name="action" value="nuovo">
        <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr;">
            <div>
                <label>Nome dispositivo *</label>
                <input type="text" name="nome" required placeholder="Es: PixelBridge Soave">
            </div>
            <div>
                <label>Club</label>
                <input type="text" name="club" list="lista-club-new" placeholder="Es: Gymnasium Soave">
                <datalist id="lista-club-new">
                    <?php foreach (array_keys($per_club) as $cn): ?>
                    <option value="<?php echo htmlspecialchars($cn); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div>
                <label>Layout</label>
                <select name="layout">
                    <option value="standard">Standard</option>
                    <option value="corsi">Corsi Fitness</option>
                </select>
            </div>
        </div>
        <label>URL Google Sheet Corsi (opzionale)</label>
        <input type="text" name="sheet_url" placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv">
        <p style="font-size:11px;color:var(--sg-muted);margin-top:-8px;margin-bottom:18px;">
            File → Pubblica sul web → CSV → copia link
        </p>
        <button type="submit" class="btn">+ Aggiungi dispositivo</button>
    </form>
</div>

</div><!-- /container -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
function toggleClub(slug, nome) {
    const item = document.getElementById(slug);
    const isOpen = item.classList.contains('acc-open');
    document.querySelectorAll('.acc-item.acc-open').forEach(el => {
        if (el.id !== slug) el.classList.remove('acc-open');
    });
    item.classList.toggle('acc-open', !isOpen);
    const open = JSON.parse(sessionStorage.getItem('clubs_open') || '[]');
    const idx  = open.indexOf(nome);
    if (!isOpen && idx === -1) open.push(nome);
    if (isOpen  && idx > -1)  open.splice(idx, 1);
    sessionStorage.setItem('clubs_open', JSON.stringify(open));
}

(function() {
    const openGet = <?php echo json_encode($open_club); ?>;
    if (openGet) {
        document.querySelectorAll('.acc-item').forEach(el => {
            const name = el.querySelector('.acc-name')?.textContent?.trim();
            if (name === openGet) {
                el.classList.add('acc-open');
                setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
            }
        });
    } else {
        const saved = JSON.parse(sessionStorage.getItem('clubs_open') || '[]');
        document.querySelectorAll('.acc-item').forEach(el => {
            const name = el.querySelector('.acc-name')?.textContent?.trim();
            if (name && saved.includes(name)) el.classList.add('acc-open');
        });
    }
})();
</script>