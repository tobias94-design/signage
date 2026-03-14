<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: /'); exit; }

$errore = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $utente = login($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($utente) {
        // Se password temporanea → forza cambio
        if ($utente['temp_password']) {
            header('Location: /profilo.php?force=1');
        } else {
            header('Location: /');
        }
        exit;
    }
    $errore = 'Username o password non corretti.';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — PixelBridge</title>
    <link rel="icon" href="/assets/img/Favicon.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style-glass.css">
</head>
<body>
<div class="sg-blob sg-b1"></div>
<div class="sg-blob sg-b2"></div>
<div class="sg-blob sg-b3"></div>
<div class="sg-grain"></div>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;">
    <div style="width:100%;max-width:380px;">
        <div style="text-align:center;margin-bottom:32px;">
            <img src="/assets/img/Logo_in_orizzontale.png" alt="PixelBridge" style="height:32px;object-fit:contain;opacity:0.9;">
        </div>
        <div class="box">
            <h2 style="margin-bottom:24px;text-align:center;">Accedi</h2>
            <?php if ($errore): ?>
            <div style="padding:12px 16px;background:rgba(233,69,96,0.12);border:1px solid rgba(233,69,96,0.25);border-radius:10px;color:#e94560;font-size:13px;margin-bottom:16px;">
                ⚠️ <?= htmlspecialchars($errore) ?>
            </div>
            <?php endif; ?>
            <form method="POST">
                <label>Username</label>
                <input type="text" name="username" required autofocus autocomplete="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                <label>Password</label>
                <input type="password" name="password" required autocomplete="current-password">
                <button type="submit" class="btn" style="width:100%;margin-top:8px;">Accedi →</button>
            </form>
        </div>
        <div style="text-align:center;margin-top:20px;font-size:12px;color:rgba(255,255,255,0.2);">
            PixelBridge Digital Signage
        </div>
    </div>
</div>
</body>
</html>
