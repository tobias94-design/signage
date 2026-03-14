<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$v = trim(file_get_contents(__DIR__ . '/../version.txt') ?: '1.0.0');
echo json_encode(['version' => $v, 'download' => 'https://github.com/tobias94-design/signage/releases/latest/download/PixelBridge.exe']);
