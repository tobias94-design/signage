<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
echo json_encode([
    'version'  => '1.1.0',
    'download' => '',   // URL download exe quando disponibile
]);
?>
