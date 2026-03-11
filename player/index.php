<?php
$token = trim($_GET['token'] ?? '');
if (!$token) { http_response_code(403); die('Token mancante.'); }
header('Location: /player/corsi.php?token=' . urlencode($token));
exit;
