<?php
require_once 'includes/auth.php';

// Se già loggato vai alla dashboard
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($user === AUTH_USER && $pass === AUTH_PASS) {
        $_SESSION['signage_logged_in'] = true;
        header('Location: /index.php');
        exit;
    } else {
        $errore = 'Username o password errati.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Login — Signage Manager</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #1a1a2e;
            color: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-box {
            background: #16213e;
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 380px;
            border-top: 3px solid #e94560;
        }
        .login-box h1 {
            font-size: 22px;
            color: #e94560;
            margin-bottom: 8px;
            text-align: center;
        }
        .login-box p {
            font-size: 13px;
            color: #aaa;
            text-align: center;
            margin-bottom: 30px;
        }
        label { font-size: 13px; color: #aaa; display: block; margin-bottom: 6px; }
        input[type=text], input[type=password] {
            width: 100%;
            padding: 12px;
            background: #0f3460;
            border: 1px solid #1a4a7a;
            border-radius: 6px;
            color: #eee;
            font-size: 15px;
            margin-bottom: 16px;
        }
        input:focus { outline: none; border-color: #e94560; }
        .btn {
            width: 100%;
            padding: 12px;
            background: #e94560;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn:hover { background: #c73652; }
        .errore {
            background: #4d1e1e;
            color: #e74c3c;
            border: 1px solid #e74c3c;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 16px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="login-box">
    <h1>📺 SIGNAGE</h1>
    <p>Accedi per gestire il sistema</p>

    <?php if ($errore): ?>
        <div class="errore">❌ <?php echo $errore; ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Username</label>
        <input type="text" name="username" placeholder="admin" required autofocus>
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>
        <button type="submit" class="btn">Accedi</button>
    </form>
</div>
</body>
</html>