<?php
$titolo = $titolo ?? 'Signage Manager';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titolo); ?> — Signage Manager</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
<?php
require_once __DIR__ . '/topbar.php';
require_once __DIR__ . '/nav.php';
?>