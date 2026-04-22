<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/db.php';
$db = getDB();
$token = $_GET['token'] ?? '';
if (!$token) { echo json_encode(['reload' => false, 'forza_adv' => false]); exit; }

$reload    = false;
$forza_adv = false;
try {
    $row = $db->query("SELECT reload_richiesto, forza_adv FROM dispositivi WHERE token=" . $db->quote($token))->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $reload    = (bool)$row['reload_richiesto'];
        $forza_adv = (bool)$row['forza_adv'];
        if ($reload) {
            $db->prepare("UPDATE dispositivi SET reload_richiesto=0 WHERE token=?")->execute([$token]);
        }
        // NON resettare forza_adv qui — lo resetta stato.php quando il player fa il poll
    }
} catch(Exception $e) {}
echo json_encode(['reload' => $reload, 'forza_adv' => $forza_adv]);
?>
