<?php
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
$db = getDB();

$messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione'])) {

    if ($_POST['azione'] === 'crea_profilo') {
        $nome = trim($_POST['nome']);
        if ($nome) {
            $db->prepare('INSERT INTO profili (nome, banner_attivo) VALUES (?, 1)')->execute([$nome]);
            $messaggio = 'ok|Profilo creato!';
        }
    }

    if ($_POST['azione'] === 'salva_base') {
        $profilo_id      = intval($_POST['profilo_id']);
        $playlist_base_id = $_POST['playlist_base_id'] ? intval($_POST['playlist_base_id']) : null;
        $intervallo      = intval($_POST['intervallo_minuti']) ?: 20;
        $db->prepare('UPDATE profili SET playlist_base_id=? WHERE id=?')->execute([$playlist_base_id, $profilo_id]);
        // aggiorna/crea regola base (tutti i giorni, nessun orario)
        $esiste = $db->query("SELECT id FROM profilo_regole WHERE profilo_id=$profilo_id AND tipo='base'")->fetch();
        if ($esiste) {
            $db->prepare("UPDATE profilo_regole SET playlist_id=?, intervallo_minuti=? WHERE id=?")
               ->execute([$playlist_base_id, $intervallo, $esiste['id']]);
        } else {
            $db->prepare("INSERT INTO profilo_regole (profilo_id, playlist_id, intervallo_minuti, giorni, tipo) VALUES (?,?,?,'1,2,3,4,5,6,7','base')")
               ->execute([$profilo_id, $playlist_base_id, $intervallo]);
        }
        $messaggio = 'ok|Playlist base salvata!';
    }

    if ($_POST['azione'] === 'aggiungi_evento') {
        $profilo_id  = intval($_POST['profilo_id']);
        $nome_ev     = trim($_POST['nome_evento']);
        $playlist_id = intval($_POST['playlist_id']);
        $giorni      = isset($_POST['giorni']) ? implode(',', array_map('intval', $_POST['giorni'])) : '';
        $ora_inizio  = $_POST['ora_inizio'] ?: null;
        $ora_fine    = $_POST['ora_fine']   ?: null;
        $data_inizio = $_POST['data_inizio'] ?: null;
        $data_fine   = $_POST['data_fine']   ?: null;
        $ripetizione = $_POST['ripetizione'] ?? 'settimanale';

        if ($playlist_id && $giorni) {
            $db->prepare('INSERT INTO profilo_eventi (profilo_id, nome, playlist_id, giorni, ora_inizio, ora_fine, data_inizio, data_fine, ripetizione) VALUES (?,?,?,?,?,?,?,?,?)')
               ->execute([$profilo_id, $nome_ev, $playlist_id, $giorni, $ora_inizio, $ora_fine, $data_inizio, $data_fine, $ripetizione]);
            $messaggio = 'ok|Evento aggiunto!';
        } else {
            $messaggio = 'errore|Seleziona playlist e almeno un giorno.';
        }
    }
}

if (isset($_GET['elimina_profilo'])) {
    $id = intval($_GET['elimina_profilo']);
    $db->exec("DELETE FROM profili WHERE id=$id");
    header('Location: /profili.php'); exit;
}

if (isset($_GET['elimina_regola'])) {
    $id = intval($_GET['elimina_regola']);
    $p  = intval($_GET['p'] ?? 0);
    $db->exec("DELETE FROM profilo_regole WHERE id=$id");
    header('Location: /profili.php' . ($p ? '?p='.$p : '')); exit;
}

if (isset($_GET['elimina_evento'])) {
    $id = intval($_GET['elimina_evento']);
    $p  = intval($_GET['p'] ?? 0);
    $db->exec("DELETE FROM profilo_eventi WHERE id=$id");
    header('Location: /profili.php' . ($p ? '?p='.$p : '')); exit;
}

$profilo_attivo = isset($_GET['p']) ? intval($_GET['p']) : null;
$profili        = $db->query('SELECT * FROM profili ORDER BY creato_il DESC')->fetchAll(PDO::FETCH_ASSOC);
$playlists      = $db->query('SELECT * FROM playlist ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);

$regola_base = null;
$eventi      = [];
if ($profilo_attivo) {
    // Controlla se esiste colonna tipo in profilo_regole, altrimenti usa query semplice
    try {
        $regola_base = $db->query("SELECT pr.*, p.nome as playlist_nome FROM profilo_regole pr JOIN playlist p ON p.id=pr.playlist_id WHERE pr.profilo_id=$profilo_attivo AND pr.tipo='base' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $regola_base = null; }

    $eventi = $db->query("
        SELECT pe.*, p.nome as playlist_nome
        FROM profilo_eventi pe
        JOIN playlist p ON p.id = pe.playlist_id
        WHERE pe.profilo_id = $profilo_attivo
        ORDER BY pe.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$giorni_nomi = [1=>'Lun',2=>'Mar',3=>'Mer',4=>'Gio',5=>'Ven',6=>'Sab',7=>'Dom'];
$titolo = 'Profili';
require_once 'includes/header.php';
?>

<div class="container" style="display:grid; grid-template-columns:300px 1fr; gap:24px;">

    <!-- Colonna sinistra -->
    <div>
        <?php if ($messaggio):
            [$tipo_msg, $testo_msg] = explode('|', $messaggio);
        ?>
        <div class="messaggio <?php echo $tipo_msg; ?>"><?php echo $testo_msg; ?></div>
        <?php endif; ?>

        <div class="box">
            <h2>Nuovo Profilo</h2>
            <form method="POST">
                <input type="hidden" name="azione" value="crea_profilo">
                <label>Nome profilo</label>
                <input type="text" name="nome" placeholder="Es. Gymnasium Soave" required>
                <button type="submit" class="btn btn-full">+ Crea Profilo</button>
            </form>
        </div>

        <div class="box">
            <h2>Profili salvati</h2>
            <?php if (empty($profili)): ?>
                <div class="vuoto">Nessun profilo ancora.</div>
            <?php else: ?>
                <?php foreach ($profili as $pr): ?>
                <div style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
                    <a href="/profili.php?p=<?php echo $pr['id']; ?>"
                       style="flex:1; display:flex; align-items:center; padding:10px 14px;
                              background:#0f3460; border-radius:6px; font-size:14px;
                              text-decoration:none; color:#eee;
                              border-left:3px solid <?php echo $profilo_attivo == $pr['id'] ? '#e94560' : 'transparent'; ?>;">
                        🎛️ <?php echo htmlspecialchars($pr['nome']); ?>
                    </a>
                    <a href="/profili.php?elimina_profilo=<?php echo $pr['id']; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Eliminare questo profilo?')">✕</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Colonna destra -->
    <div>
        <?php if ($profilo_attivo):
            $pr_corrente = array_values(array_filter($profili, fn($p) => $p['id'] == $profilo_attivo))[0];
        ?>

        <!-- ── PLAYLIST BASE ── -->
        <div class="box">
            <h2>📺 Playlist Base</h2>
            <p style="font-size:13px; color:#aaa; margin-bottom:16px;">
                Gira H24 tutti i giorni. Gli eventi si accodano a questa quando attivi.
            </p>
            <form method="POST">
                <input type="hidden" name="azione" value="salva_base">
                <input type="hidden" name="profilo_id" value="<?php echo $profilo_attivo; ?>">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label>Playlist principale</label>
                        <select name="playlist_base_id">
                            <option value="">— Nessuna —</option>
                            <?php foreach ($playlists as $pl): ?>
                            <option value="<?php echo $pl['id']; ?>"
                                <?php echo ($regola_base && $regola_base['playlist_id'] == $pl['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pl['nome']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Va in onda ogni (minuti)</label>
                        <input type="number" name="intervallo_minuti"
                               value="<?php echo $regola_base ? $regola_base['intervallo_minuti'] : 20; ?>"
                               min="1" max="480">
                    </div>
                </div>
                <button type="submit" class="btn" style="margin-top:4px;">💾 Salva Playlist Base</button>
            </form>

            <?php if ($regola_base): ?>
            <div style="margin-top:16px; padding:12px 16px; background:#0f3460; border-radius:8px; font-size:13px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <span style="color:#aaa;">Attiva: </span>
                    <strong style="color:#fff;">📋 <?php echo htmlspecialchars($regola_base['playlist_nome']); ?></strong>
                    <span style="color:#aaa; margin-left:10px;">ogni <?php echo $regola_base['intervallo_minuti']; ?> min — tutti i giorni</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── EVENTI PROGRAMMATI ── -->
        <div class="box">
            <h2>📅 Eventi Programmati</h2>
            <p style="font-size:13px; color:#aaa; margin-bottom:16px;">
                Quando un evento è attivo, la sua playlist viene accodata alla base nel ciclo ADV.
            </p>

            <form method="POST" id="formEvento">
                <input type="hidden" name="azione" value="aggiungi_evento">
                <input type="hidden" name="profilo_id" value="<?php echo $profilo_attivo; ?>">

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label>Nome evento</label>
                        <input type="text" name="nome_evento" placeholder="Es. Buongiorno, Weekend...">
                    </div>
                    <div>
                        <label>Playlist da accodare</label>
                        <select name="playlist_id">
                            <option value="">— Seleziona —</option>
                            <?php foreach ($playlists as $pl): ?>
                            <option value="<?php echo $pl['id']; ?>"><?php echo htmlspecialchars($pl['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div>
                        <label>Dalle</label>
                        <input type="time" name="ora_inizio">
                    </div>
                    <div>
                        <label>Alle</label>
                        <input type="time" name="ora_fine">
                    </div>
                    <div>
                        <label>Ripetizione</label>
                        <select name="ripetizione">
                            <option value="settimanale">Settimanale</option>
                            <option value="sempre">Sempre</option>
                            <option value="una_volta">Una volta</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label>Dal (opzionale)</label>
                        <input type="date" name="data_inizio">
                    </div>
                    <div>
                        <label>Al (opzionale)</label>
                        <input type="date" name="data_fine">
                    </div>
                </div>

                <label>Giorni</label>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
                    <?php foreach ($giorni_nomi as $num => $nome): ?>
                    <div>
                        <input type="checkbox" name="giorni[]" value="<?php echo $num; ?>"
                               id="eg<?php echo $num; ?>" style="display:none;" checked>
                        <label for="eg<?php echo $num; ?>" id="label-eg<?php echo $num; ?>"
                               style="padding:8px 16px; background:#e94560; border:2px solid #e94560;
                                      border-radius:20px; font-size:13px; font-weight:600; cursor:pointer;
                                      user-select:none; display:inline-block; color:#fff; letter-spacing:0.5px;">
                            <?php echo $nome; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn">+ Aggiungi Evento</button>
            </form>
        </div>

        <!-- ── LISTA EVENTI ── -->
        <?php if (!empty($eventi)): ?>
        <div class="box">
            <h2>Eventi configurati (<?php echo count($eventi); ?>)</h2>
            <?php
            $oggi = date('Y-m-d');
            $oraOra = date('H:i');
            foreach ($eventi as $ev):
                $giorni_attivi = explode(',', $ev['giorni']);
                $scaduto = !empty($ev['data_fine']) && $ev['data_fine'] < $oggi;
                $nonAncoraAttivo = !empty($ev['data_inizio']) && $ev['data_inizio'] > $oggi;
                $opacita = ($scaduto || $nonAncoraAttivo) ? '0.4' : '1';

                // Controlla se attivo adesso
                $giornoOggi = date('N');
                $attivoOra = false;
                if (in_array($giornoOggi, $giorni_attivi)) {
                    if (!$ev['ora_inizio'] && !$ev['ora_fine']) $attivoOra = true;
                    elseif ($oraOra >= $ev['ora_inizio'] && $oraOra <= $ev['ora_fine']) $attivoOra = true;
                }
            ?>
            <div style="background:#0f3460; border-radius:8px; padding:16px; margin-bottom:12px; opacity:<?php echo $opacita; ?>; position:relative;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                    <div>
                        <div style="font-size:15px; font-weight:bold; display:flex; align-items:center; gap:8px;">
                            📅 <?php echo htmlspecialchars($ev['nome'] ?: $ev['playlist_nome']); ?>
                            <?php if ($attivoOra): ?>
                                <span class="badge badge-online">● ATTIVO ORA</span>
                            <?php elseif ($scaduto): ?>
                                <span class="badge" style="background:#7f1d1d; color:#fca5a5;">SCADUTO</span>
                            <?php elseif ($nonAncoraAttivo): ?>
                                <span class="badge" style="background:#1c3a6e; color:#93c5fd;">IN ATTESA</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:13px; color:#aaa; margin-top:4px;">
                            📋 <?php echo htmlspecialchars($ev['playlist_nome']); ?>
                        </div>
                    </div>
                    <a href="/profili.php?elimina_evento=<?php echo $ev['id']; ?>&p=<?php echo $profilo_attivo; ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Eliminare questo evento?')" style="flex-shrink:0;">✕</a>
                </div>

                <div style="display:flex; gap:16px; flex-wrap:wrap; font-size:13px; color:#aaa;">
                    <?php if ($ev['ora_inizio'] || $ev['ora_fine']): ?>
                    <span>🕐 <?php echo $ev['ora_inizio'] ?: '--'; ?> → <?php echo $ev['ora_fine'] ?: '--'; ?></span>
                    <?php endif; ?>
                    <?php if ($ev['data_inizio'] || $ev['data_fine']): ?>
                    <span>📆 <?php echo $ev['data_inizio'] ? date('d/m/Y', strtotime($ev['data_inizio'])) : '∞'; ?>
                          → <?php echo $ev['data_fine'] ? date('d/m/Y', strtotime($ev['data_fine'])) : '∞'; ?></span>
                    <?php endif; ?>
                    <span>🔁 <?php echo $ev['ripetizione']; ?></span>
                </div>

                <div style="display:flex; gap:4px; flex-wrap:wrap; margin-top:10px;">
                    <?php foreach ($giorni_nomi as $num => $nome): ?>
                        <?php if (in_array($num, $giorni_attivi)): ?>
                        <span style="padding:3px 8px; border-radius:20px; font-size:11px; font-weight:bold;
                                     background:<?php echo $num >= 6 ? '#3d1a5c' : '#1a3a5c'; ?>;
                                     color:<?php echo $num >= 6 ? '#c39bd3' : '#5dade2'; ?>;">
                            <?php echo $nome; ?>
                        </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="box">
            <div class="vuoto">👈 Seleziona un profilo per configurarlo</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
input[type=date], input[type=time] {
    width: 100%;
    padding: 10px 12px;
    background: #0f3460 !important;
    border: 1px solid #1a4a7a !important;
    border-radius: 6px;
    color: #eee !important;
    font-size: 14px;
    margin-bottom: 12px;
    cursor: pointer;
}
input[type=date]::-webkit-calendar-picker-indicator,
input[type=time]::-webkit-calendar-picker-indicator {
    filter: invert(0.7);
    cursor: pointer;
    width: 18px;
    height: 18px;
}
</style>

<script>
// Toggle giorni form evento
document.querySelectorAll('[id^="label-eg"]').forEach(label => {
    label.addEventListener('click', function(e) {
        e.preventDefault();
        const input = document.getElementById(this.getAttribute('for'));
        input.checked = !input.checked;
        if (input.checked) {
            this.style.background  = '#e94560';
            this.style.borderColor = '#e94560';
            this.style.color       = '#ffffff';
        } else {
            this.style.background  = 'transparent';
            this.style.borderColor = '#1a4a7a';
            this.style.color       = '#aaa';
        }
    });
});

// Apri calendario/orario cliccando ovunque sul campo
document.querySelectorAll('input[type=date], input[type=time]').forEach(input => {
    input.addEventListener('click', function() {
        try { this.showPicker(); } catch(e) {}
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>