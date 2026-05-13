<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../includes/db.php';
$db = getDB();

$token = $_GET['token'] ?? '';
if (!$token) { echo json_encode(['ok' => false, 'error' => 'Token mancante']); exit; }

$stmt = $db->prepare('SELECT * FROM dispositivi WHERE token = ?');
$stmt->execute([$token]);
$dispositivo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dispositivo) { echo json_encode(['ok' => false, 'error' => 'Dispositivo non trovato']); exit; }
if (!$dispositivo['profilo_id']) { echo json_encode(['ok' => true, 'files' => []]); exit; }

$oggi = date('Y-m-d');

$regola = null;
try {
    $regola = $db->query("
        SELECT pr.playlist_id
        FROM profilo_regole pr
        WHERE pr.profilo_id = " . intval($dispositivo['profilo_id']) . "
        AND pr.tipo = 'base'
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

if (!$regola) { echo json_encode(['ok' => true, 'files' => []]); exit; }

$rows = $db->query("
    SELECT c.file, c.tipo, c.durata, c.nome
    FROM playlist_items pi
    JOIN contenuti c ON c.id = pi.contenuto_id
    WHERE pi.playlist_id = " . intval($regola['playlist_id']) . "
    AND (pi.data_fine IS NULL OR pi.data_fine >= '$oggi')
    AND (pi.data_inizio IS NULL OR pi.data_inizio <= '$oggi')
    ORDER BY pi.ordine
")->fetchAll(PDO::FETCH_ASSOC);

$files = array_map(function($r) {
    return [
        'file'   => $r['file'],
        'nome'   => $r['nome'],
        'tipo'   => $r['tipo'],
        'durata' => $r['durata'],
        'url'    => '/uploads/' . $r['file'],
    ];
}, $rows);

echo json_encode(['ok' => true, 'files' => $files]);
?>
