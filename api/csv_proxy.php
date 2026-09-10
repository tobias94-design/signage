<?php
// Proxy CSV per i fogli Google Sheets pubblicati (Meteo/Corsi Live usano
// questo per aggirare il CORS: il browser non puo' chiamare docs.google.com
// direttamente da JS lato client, quindi passiamo dal nostro server).

header('Content-Type: text/csv; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$url = $_GET['url'] ?? '';

if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo '';
    exit;
}

// Sicurezza minima: accettiamo solo URL di Google Sheets, per evitare che
// questo endpoint diventi un proxy generico verso qualsiasi sito
$host = parse_url($url, PHP_URL_HOST);
if (!in_array($host, ['docs.google.com', 'spreadsheets.google.com'], true)) {
    http_response_code(403);
    echo '';
    exit;
}

$ctx = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'timeout' => 10,
        'header'  => "User-Agent: Mozilla/5.0 (PixelBridge CSV Proxy)\r\n",
        'follow_location' => 1,
    ],
    'https' => [
        'timeout' => 10,
    ],
]);

$csv = @file_get_contents($url, false, $ctx);

if ($csv === false) {
    http_response_code(502);
    echo '';
    exit;
}

echo $csv;
