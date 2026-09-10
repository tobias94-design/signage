<?php
/**
 * api/adv_status.php
 * Calcola in tempo reale, per un dispositivo, se in questo momento deve
 * essere mostrata la TV o la pubblicità (ADV), e quale contenuto esatto.
 *
 * Legge le regole da adv_regole (giorni, fascia oraria, intervallo, durata,
 * fullscreen) — la stessa tabella già gestita dalla pagina /adv.php.
 *
 * Uso: GET /api/adv_status.php?token=IL_TOKEN_DISPOSITIVO
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Token mancante']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT id, tenant_id FROM dispositivi WHERE token = ?");
$stmt->execute([$token]);
$dispositivo = $stmt->fetch();

if (!$dispositivo) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'Dispositivo non trovato']);
    exit;
}

$tid = (int)$dispositivo['tenant_id'];

// ── Trova la regola ADV attiva adesso (giorno + fascia oraria) ──
$oggi_num = (int)date('N'); // 1=Lun ... 7=Dom
$ora_attuale = date('H:i:s');

$stmt = $db->prepare("
    SELECT * FROM adv_regole
    WHERE tenant_id = ? AND dispositivo_token = ? AND attivo = 1
    ORDER BY id
");
$stmt->execute([$tid, $token]);
$tutte_regole = $stmt->fetchAll();

if (empty($tutte_regole)) {
    echo json_encode(['ok'=>true, 'modalita'=>'tv', 'messaggio'=>'Nessuna regola ADV configurata']);
    exit;
}

// La PRIMA regola creata (id piu' basso) e' sempre la "base": detta il ritmo
// (modalita' alternata/loop/smart, ogni quanti minuti) indipendentemente dai
// suoi stessi giorni/orari. Le regole successive non hanno un ritmo proprio:
// servono solo a sostituire QUALE playlist va in onda in giorni/fasce
// specifiche, mantenendo lo stesso ritmo della base. Se nessuna sostituzione
// combacia con adesso, si torna semplicemente alla playlist della base.
$regola_base = $tutte_regole[0];

$regola_attiva = $regola_base;
foreach (array_slice($tutte_regole, 1) as $r) {
    $giorni = explode(',', $r['giorni'] ?? '');
    if (!in_array((string)$oggi_num, $giorni)) continue;

    if ($r['ora_inizio'] && $r['ora_fine']) {
        if ($ora_attuale < $r['ora_inizio'] || $ora_attuale > $r['ora_fine']) continue;
    }
    $regola_attiva = $r; // sostituzione trovata: prende il posto della base solo per la playlist
    break;
}

// ── Loop mode: SEMPRE quello della playlist della regola base, mai della
// sostituzione — il ritmo lo decide solo la prima regola.
$loop_mode = 'alternata';
$stmt = $db->prepare("SELECT loop_mode FROM adv_playlists WHERE id = ? AND tenant_id = ?");
$stmt->execute([$regola_base['playlist_id'], $tid]);
$lm = $stmt->fetchColumn();
if ($lm) $loop_mode = $lm;

// ── Contenuti della playlist ADV, in ordine ──────────────────────
// Un contenuto legato a un inserzionista esce automaticamente dalla rotazione
// quando il suo contratto scade — nessuna data da sincronizzare a mano altrove,
// il contratto e' l'unica fonte di verita' su "questo deve ancora andare in onda".
$stmt = $db->prepare("
    SELECT c.id, c.nome, c.tipo, c.file, COALESCE(c.durata, 10) AS durata, pi.ordine
    FROM playlist_items pi
    JOIN contenuti c ON c.id = pi.contenuto_id
    WHERE pi.playlist_id = ?
      AND (
        c.inserzionista_id IS NULL
        OR EXISTS (
            SELECT 1 FROM contratti ct
            WHERE ct.inserzionista_id = c.inserzionista_id
              AND ct.stato = 'attivo'
              AND CURDATE() BETWEEN ct.data_inizio AND ct.data_fine
        )
      )
    ORDER BY pi.ordine
");
$stmt->execute([$regola_attiva['playlist_id']]);
$contenuti = $stmt->fetchAll();

if (empty($contenuti)) {
    echo json_encode(['ok'=>true, 'modalita'=>'tv', 'messaggio'=>'Playlist ADV vuota']);
    exit;
}

$durata_playlist = array_sum(array_column($contenuti, 'durata'));
// L'intervallo (ogni quanti minuti scatta l'ADV) viene sempre dalla base,
// mai dalla regola di sostituzione — stesso motivo del loop_mode sopra.
$intervallo_sec = (int)($regola_base['intervallo_min'] ?? 20) * 60;
$secondi_giorno = (int)date('H') * 3600 + (int)date('i') * 60 + (int)date('s');

// modalita 'loop' o 'smart' = sempre ADV quando la regola è attiva (no alternanza con TV)
if ($loop_mode === 'loop' || $loop_mode === 'smart') {
    $pos_in_adv = $secondi_giorno % $durata_playlist;
    $modalita = 'adv';
} else {
    // 'alternata' (default): cicla [TV per intervallo_sec] -> [ADV per durata_playlist] -> ripeti
    $ciclo_totale = $intervallo_sec + $durata_playlist;
    $posizione_ciclo = $secondi_giorno % $ciclo_totale;

    if ($posizione_ciclo < $intervallo_sec) {
        echo json_encode([
            'ok' => true,
            'modalita' => 'tv',
            'secondi_alla_adv' => $intervallo_sec - $posizione_ciclo,
            'fullscreen' => (bool)$regola_attiva['fullscreen'],
        ]);
        exit;
    }
    $pos_in_adv = $posizione_ciclo - $intervallo_sec;
    $modalita = 'adv';
}

// ── Trova quale contenuto tocca adesso all'interno della playlist ─
$contenuto_attivo = null;
$elapsed = 0;
foreach ($contenuti as $c) {
    if ($pos_in_adv >= $elapsed && $pos_in_adv < $elapsed + $c['durata']) {
        $contenuto_attivo = $c;
        $contenuto_attivo['secondi_rimanenti'] = ($elapsed + $c['durata']) - $pos_in_adv;
        break;
    }
    $elapsed += $c['durata'];
}
if (!$contenuto_attivo) {
    $contenuto_attivo = $contenuti[0];
    $contenuto_attivo['secondi_rimanenti'] = $contenuto_attivo['durata'];
}

echo json_encode([
    'ok' => true,
    'modalita' => 'adv',
    'fullscreen' => (bool)$regola_attiva['fullscreen'],
    'contenuto' => $contenuto_attivo,
    'playlist_id' => $regola_attiva['playlist_id'],
], JSON_UNESCAPED_UNICODE);
