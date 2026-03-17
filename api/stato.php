<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';
$db = getDB();

$token = $_GET['token'] ?? '';
if (!$token) { echo json_encode(['errore' => 'Token mancante']); exit; }

$cache_file = __DIR__ . '/../cache/stato-' . preg_replace('/[^a-z0-9\-]/', '', $token) . '.json';

$stmt = $db->prepare('SELECT * FROM dispositivi WHERE token = ?');
$stmt->execute([$token]);
$dispositivo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dispositivo) { echo json_encode(['errore' => 'Dispositivo non trovato']); exit; }

$db->prepare('UPDATE dispositivi SET stato = ?, ultimo_ping = CURRENT_TIMESTAMP WHERE token = ?')
   ->execute(['online', $token]);

// ── LOG PASSAGGIO ADV (se il player segnala un contenuto in corso) ──
if (!empty($_GET['log_contenuto'])) {
    $cid    = (int)$_GET['log_contenuto'];
    $dur    = (int)($_GET['log_durata'] ?? 0);
    $club   = $dispositivo['club'] ?? '';
    try {
        $db->prepare('INSERT INTO log_adv (contenuto_id, dispositivo_token, club, durata_sec) VALUES (?,?,?,?)')
           ->execute([$cid, $token, $club, $dur]);
    } catch(Exception $e) {}
}

if (!$dispositivo['profilo_id']) {
    $risposta = ['modalita' => 'tv', 'banner' => getBanner($db), 'sidebar_slides' => []];
    salvaCache($cache_file, $risposta);
    echo json_encode($risposta); exit;
}

$profilo = $db->query("SELECT * FROM profili WHERE id = " . intval($dispositivo['profilo_id']))
              ->fetch(PDO::FETCH_ASSOC);

$oggi       = date('Y-m-d');
$oraOra     = date('H:i');
$giornoOggi = date('N');

// PLAYLIST BASE
$regola_base = null;
try {
    $regola_base = $db->query("
        SELECT pr.*, p.nome as playlist_nome
        FROM profilo_regole pr
        JOIN playlist p ON p.id = pr.playlist_id
        WHERE pr.profilo_id = " . intval($profilo['id']) . "
        AND pr.tipo = 'base'
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $regole = $db->query("
        SELECT pr.*, p.nome as playlist_nome
        FROM profilo_regole pr
        JOIN playlist p ON p.id = pr.playlist_id
        WHERE pr.profilo_id = " . intval($profilo['id'])
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($regole as $r) {
        $giorni = explode(',', $r['giorni']);
        if (in_array($giornoOggi, $giorni)) { $regola_base = $r; break; }
    }
}

if (!$regola_base) {
    $risposta = ['modalita' => 'tv', 'banner' => getBanner($db), 'profilo' => $profilo['nome'],
                 'sidebar_slides' => getSidebarSlides($db, $profilo['id'], $token)];
    salvaCache($cache_file, $risposta);
    echo json_encode($risposta); exit;
}

// EVENTO ATTIVO ORA
$evento_attivo = null;
try {
    $eventi = $db->query("
        SELECT pe.*, p.nome as playlist_nome
        FROM profilo_eventi pe
        JOIN playlist p ON p.id = pe.playlist_id
        WHERE pe.profilo_id = " . intval($profilo['id'])
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($eventi as $ev) {
        if (!empty($ev['data_fine'])   && $ev['data_fine']   < $oggi) continue;
        if (!empty($ev['data_inizio']) && $ev['data_inizio'] > $oggi) continue;
        $giorni = explode(',', $ev['giorni']);
        if (!in_array($giornoOggi, $giorni)) continue;
        if ($ev['ora_inizio'] && $ev['ora_fine']) {
            if ($oraOra < $ev['ora_inizio'] || $oraOra > $ev['ora_fine']) continue;
        }
        $evento_attivo = $ev;
        break;
    }
} catch(Exception $e) { $evento_attivo = null; }

// CARICA E CONCATENA CONTENUTI
$contenuti_base   = getContenutiPlaylist($db, $regola_base['playlist_id'], $oggi);
$contenuti_evento = $evento_attivo ? getContenutiPlaylist($db, $evento_attivo['playlist_id'], $oggi) : [];
$contenuti_tutti  = array_values(array_merge($contenuti_base, $contenuti_evento));

if (empty($contenuti_tutti)) {
    $risposta = ['modalita' => 'tv', 'banner' => getBanner($db), 'profilo' => $profilo['nome'],
                 'sidebar_slides' => getSidebarSlides($db, $profilo['id'], $token)];
    salvaCache($cache_file, $risposta);
    echo json_encode($risposta); exit;
}

// SCHEDULING
$DURATA_VIDEO_DEFAULT = 30;
$getDurata = function($c) use ($DURATA_VIDEO_DEFAULT) {
    return $c['tipo'] === 'video' ? $DURATA_VIDEO_DEFAULT : (int)$c['durata'];
};

$durata_playlist = array_sum(array_map($getDurata, $contenuti_tutti));
if ($durata_playlist === 0) $durata_playlist = 60;

$intervallo_sec  = $regola_base['intervallo_minuti'] * 60;
$secondi_giorno  = (int)date('H') * 3600 + (int)date('i') * 60 + (int)date('s');
$ciclo_totale    = $intervallo_sec + $durata_playlist;
$posizione_ciclo = $secondi_giorno % $ciclo_totale;

if ($posizione_ciclo < $intervallo_sec) {
    $secondi_alla_adv = $intervallo_sec - $posizione_ciclo;
    $risposta = [
        'modalita'         => 'tv',
        'secondi_alla_adv' => $secondi_alla_adv,
        'banner'           => getBanner($db, $profilo),
        'profilo'          => $profilo['nome'],
        'evento_attivo'    => $evento_attivo ? $evento_attivo['nome'] : null,
        'sidebar_slides'   => getSidebarSlides($db, $profilo['id'], $token),
        'debug'            => "TV per altri {$secondi_alla_adv}s"
    ];
} else {
    $pos_in_adv      = $posizione_ciclo - $intervallo_sec;
    $secondi_alla_tv = $durata_playlist - $pos_in_adv;

    $contenuto_attivo = null;
    $elapsed = 0;
    foreach ($contenuti_tutti as $c) {
        $dur = $getDurata($c);
        if ($pos_in_adv >= $elapsed && $pos_in_adv < $elapsed + $dur) {
            $contenuto_attivo = $c;
            $contenuto_attivo['secondi_rimanenti'] = ($elapsed + $dur) - $pos_in_adv;
            break;
        }
        $elapsed += $dur;
    }

    $risposta = [
        'modalita'        => 'adv',
        'playlist_nome'   => $regola_base['playlist_nome'] . ($evento_attivo ? ' + ' . $evento_attivo['nome'] : ''),
        'contenuti'       => $contenuti_tutti,
        'contenuto_ora'   => $contenuto_attivo,
        'pos_in_adv'      => $pos_in_adv,
        'secondi_alla_tv' => $secondi_alla_tv,
        'banner'          => getBanner($db, $profilo),
        'profilo'         => $profilo['nome'],
        'evento_attivo'   => $evento_attivo ? $evento_attivo['nome'] : null,
        'sidebar_slides'  => getSidebarSlides($db, $profilo['id'], $token),
        'debug'           => "ADV per altri {$secondi_alla_tv}s" . ($evento_attivo ? " [evento: {$evento_attivo['nome']}]" : '')
    ];
}

salvaCache($cache_file, $risposta);
echo json_encode($risposta);

function getSidebarSlides($db, $profilo_id, $token = '') {
    try {
        // Prima cerca per dispositivo_token (nuovo sistema)
        if ($token) {
            $slides = $db->query("
                SELECT * FROM sidebar_slides
                WHERE dispositivo_token = " . $db->quote($token) . "
                AND attivo = 1
                ORDER BY ordine
            ")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($slides)) return $slides;
        }
        // Fallback: vecchio sistema per profilo_id
        if ($profilo_id) {
            return $db->query("
                SELECT * FROM sidebar_slides
                WHERE profilo_id = " . intval($profilo_id) . "
                AND attivo = 1
                ORDER BY ordine
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    } catch(Exception $e) { return []; }
}

function getContenutiPlaylist($db, $playlist_id, $oggi) {
    $rows = $db->query("
        SELECT c.*, pi.ordine, pi.data_inizio, pi.data_fine
        FROM playlist_items pi
        JOIN contenuti c ON c.id = pi.contenuto_id
        WHERE pi.playlist_id = " . intval($playlist_id) . "
        ORDER BY pi.ordine
    ")->fetchAll(PDO::FETCH_ASSOC);

    return array_values(array_filter($rows, function($c) use ($oggi) {
        if (!empty($c['data_fine'])   && $c['data_fine']   < $oggi) return false;
        if (!empty($c['data_inizio']) && $c['data_inizio'] > $oggi) return false;
        return true;
    }));
}

function getBanner($db, $profilo = null) {
    if (!$profilo) $profilo = $db->query('SELECT * FROM profili LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$profilo) return [];
    return [
        'banner_colore'       => $profilo['banner_colore']       ?? '#000000',
        'banner_testo_colore' => $profilo['banner_testo_colore'] ?? '#ffffff',
        'banner_posizione'    => $profilo['banner_posizione']    ?? 'bottom',
        'banner_altezza'      => $profilo['banner_altezza']      ?? 80,
        'logo'                => $profilo['logo']                ?? '',
    ];
}

function salvaCache($file, $dati) {
    $dati['cache_salvata_il'] = date('Y-m-d H:i:s');
    file_put_contents($file, json_encode($dati));
}
?>
