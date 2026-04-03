<?php
header('Content-Type: application/json');

$latest_version = '1.0.4';
$download_url = 'https://github.com/tobias94-design/signage/releases/download/v1.0.4/PixelBridge-v1.0.4.zip';

echo json_encode([
    'version' => $latest_version,
    'download' => $download_url,
    'changelog' => [
        'Agent stabile basato su v1.0.1',
        'Fix BASE_DIR per config.json',
        'Pairing automatico',
        'Auto-start Windows',
        'Taskbar nascosta'
    ]
]);
