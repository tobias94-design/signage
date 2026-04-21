<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/db.php';
$db = getDB();
$token = $_GET['token'] ?? '';
if (!$token) { echo json_encode(['reload' => false]); exit; }
$reload = false;
try {
    $reload = (bool)$db->query("SELECT reload_richiesto FROM dispositivi WHERE token=" . $db->quote($token))->fetchColumn();
    if ($reload) {
        $db->prepare("UPDATE dispositivi SET reload_richiesto=0 WHERE token=?")->execute([$token]);
    }
} catch(Exception $e) {}
echo json_encode(['reload' => $reload]);
?>
