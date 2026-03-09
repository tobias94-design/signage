<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';
$db = getDB();

$token = $_GET['token'] ?? '';

if (!$token) {
    echo json_encode(['errore' => 'Token mancante']);
    exit;
}

$cache_file = __DIR__ . '/../cache/stato-' . preg_replace('/[^a-z0-9\-]/', '', $token) . '.json';

// Trova dispositivo
$stmt = $db->prepare('SELECT * FROM dispositivi WHERE token = ?');
$stmt->execute([$token]);
$dispositivo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dispositivo) {
    echo json_encode(['errore' => 'Dispositivo non trovato']);
    exit;
}

// Aggiorna ping
$db->prepare('UPDATE dispositivi SET stato = ?, ultimo_ping = CURRENT_TIMESTAMP WHERE token = ?')
   ->execute(['online', $token]);

// Nessun profilo assegnato
if (!$dispositivo['profilo_id']) {
    $risposta = ['modalita' => 'tv', 'banner' => getBanner($db)];
    salvaCache($cache_file, $risposta);
    echo json_encode($risposta);
    exit;
}

// Carica profilo
$profilo = $db->query("SELECT * FROM profili WHERE id = " . intval($dispositivo['profilo_id']))
              ->fetch(PDO::FETCH_ASSOC);

$giornoOggi = date('N');
$oggi       = date('Y-m-d');

$regole = $db->query("
    SELECT pr.*, p.nome as playlist_nome
    FROM profilo_regole pr
    JOIN playlist p ON p.id = pr.playlist_id
    WHERE pr.profilo_id = " . intval($profilo['id'])
)->fetchAll(PDO::FETCH_ASSOC);

$regola_attiva = null;
foreach ($regole as $r) {
    $giorni = explode(',', $r['giorni']);
    if (in_array($giornoOggi, $giorni)) {
        $regola_attiva = $r;
        break;
    }
}

if (!$regola_attiva) {
    $risposta = [
        'modalita' => 'tv',
        'banner'   => getBanner($db),
        'profilo'  => $profilo['nome']
    ];
    salvaCache($cache_file, $risposta);
    echo json_encode($risposta);
    exit;
}

// Carica contenuti attivi (non scaduti, già iniziati)
$contenuti_raw = $db->query("
    SELECT c.*, pi.ordine, pi.data_inizio, pi.data_fine
    FROM playlist_items pi
    JOIN contenuti c ON c.id = pi.contenuto_id
    WHERE pi.playlist_id = " . intval($regola_attiva['playlist_id']) . "
    ORDER BY pi.ordine
")->fetchAll(PDO::FETCH_ASSOC);

// Filtra contenuti scaduti o non ancora attivi
$contenuti = array_values(array_filter($contenuti_raw, function($c) use ($oggi) {
    if (!empty($c['data_fine'])   && $c['data_fine']   < $oggi) return false;
    if (!empty($c['data_inizio']) && $c['data_inizio'] > $oggi) return false;
    return true;
}));

// Per i video (durata=0) usa fallback 30s solo per il calcolo scheduling
$DURATA_VIDEO_DEFAULT = 30;
$getDurata = function($c) use ($DURATA_VIDEO_DEFAULT) {
    return $c['tipo'] === 'video' ? $DURATA_VIDEO_DEFAULT : (int)$c['durata'];
};

$durata_playlist = array_sum(array_map($getDurata, $contenuti));
if ($durata_playlist === 0) $durata_playlist = 60;

$secondi_giorno  = (int)date('H') * 3600 + (int)date('i') * 60 + (int)date('s');
$intervallo_sec  = $regola_attiva['intervallo_minuti'] * 60;
$ciclo_totale    = $intervallo_sec + $durata_playlist;
$posizione_ciclo = $secondi_giorno % $ciclo_totale;

if ($posizione_ciclo < $intervallo_sec) {
    $secondi_alla_adv = $intervallo_sec - $posizione_ciclo;
    $risposta = [
        'modalita'         => 'tv',
        'secondi_alla_adv' => $secondi_alla_adv,
        'banner'           => getBanner($db),
        'profilo'          => $profilo['nome'],
        'debug'            => "TV per altri {$secondi_alla_adv}s"
    ];
} else {
    $pos_in_adv      = $posizione_ciclo - $intervallo_sec;
    $secondi_alla_tv = $durata_playlist - $pos_in_adv;

    $contenuto_attivo = null;
    $elapsed = 0;
    foreach ($contenuti as $c) {
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
        'playlist_id'     => $regola_attiva['playlist_id'],
        'playlist_nome'   => $regola_attiva['playlist_nome'],
        'contenuti'       => $contenuti,
        'contenuto_ora'   => $contenuto_attivo,
        'pos_in_adv'      => $pos_in_adv,
        'secondi_alla_tv' => $secondi_alla_tv,
        'banner'          => getBanner($db),
        'profilo'         => $profilo['nome'],
        'debug'           => "ADV per altri {$secondi_alla_tv}s"
    ];
}

salvaCache($cache_file, $risposta);
echo json_encode($risposta);

// ─── FUNZIONI ────────────────────────────────────────────────
function getBanner($db) {
    $profilo = $db->query('SELECT * FROM profili LIMIT 1')->fetch(PDO::FETCH_ASSOC);
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