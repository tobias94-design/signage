<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/csv');
$url = $_GET['url'] ?? '';
if (!$url || !str_contains($url, 'docs.google.com')) {
    http_response_code(400);
    exit;
}
$ctx = stream_context_create(['http' => ['follow_location' => 1, 'max_redirects' => 5]]);
echo file_get_contents($url, false, $ctx);
