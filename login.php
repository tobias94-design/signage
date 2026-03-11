<?php
require_once 'includes/auth.php';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PixelBridge — Accedi</title>
    <link rel="icon" href="/assets/img/Favicon.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        :root {
            --orange:  #E85002;
            --orange-hi: #F16001;
            --green:   #30D158;
            --red:     #FF453A;
            --white:   #F5F5F7;
            --text:    rgba(245,245,247,0.62);
            --muted:   rgba(245,245,247,0.28);
            --base:    #0c0a09;
        }

        html, body {
            height: 100%;
            font-family: 'Figtree', sans-serif;
            background: var(--base);
            color: var(--white);
            overflow: hidden;
        }

        /* ── LAYOUT SPLIT ──────────────────────────────── */
        .auth-wrap {
            display: grid;
            grid-template-columns: 1fr 480px;
            height: 100vh;
        }

        /* ── FOTO SINISTRA ─────────────────────────────── */
        .auth-bg {
            position: relative;
            overflow: hidden;
        }
        .auth-bg-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            filter: brightness(0.55);
            display: block;
        }
        /* Overlay gradient verso destra */
        .auth-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to right,
                rgba(12,10,9,0) 50%,
                rgba(12,10,9,0.85) 85%,
                rgba(12,10,9,1) 100%
            );
        }
        /* Testo in basso a sinistra */
        .auth-bg-text {
            position: absolute;
            bottom: 48px;
            left: 52px;
            z-index: 2;
        }
        .auth-bg-text h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--white);
            line-height: 1.2;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
            text-shadow: 0 2px 20px rgba(0,0,0,0.6);
        }
        .auth-bg-text p {
            font-size: 14px;
            color: rgba(245,245,247,0.50);
            font-weight: 400;
        }

        /* ── PANNELLO DESTRA ───────────────────────────── */
        .auth-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 52px;
            background: rgba(12,10,9,0.96);
            border-left: 1px solid rgba(255,255,255,0.06);
        }

        /* Logo */
        .auth-logo {
            width: 200px;
            margin-bottom: 48px;
            opacity: 0.95;
        }

        /* Titolo form */
        .auth-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--white);
            letter-spacing: -0.3px;
            margin-bottom: 4px;
            align-self: flex-start;
        }
        .auth-sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 32px;
            align-self: flex-start;
        }

        /* Form */
        .auth-form { width: 100%; }

        label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
        }

        input[type=text],
        input[type=password] {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 12px;
            color: var(--white);
            font-size: 15px;
            font-family: 'Figtree', sans-serif;
            font-weight: 500;
            margin-bottom: 20px;
            transition: border-color 0.2s, background 0.2s;
            outline: none;
            -webkit-appearance: none;
        }
        input[type=text]:focus,
        input[type=password]:focus {
            border-color: rgba(232,80,2,0.55);
            background: rgba(232,80,2,0.06);
            box-shadow: 0 0 0 3px rgba(232,80,2,0.12);
        }
        input::placeholder { color: var(--muted); }

        /* Bottone */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--orange) 0%, var(--orange-hi) 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Figtree', sans-serif;
            cursor: pointer;
            letter-spacing: 0.2px;
            transition: opacity 0.15s, transform 0.1s;
            box-shadow: 0 4px 24px rgba(232,80,2,0.35);
            margin-top: 4px;
        }
        .btn-login:hover  { opacity: 0.92; }
        .btn-login:active { transform: scale(0.985); }

        /* Errore */
        .auth-error {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,69,58,0.10);
            border: 1px solid rgba(255,69,58,0.22);
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 13px;
            color: var(--red);
            margin-bottom: 20px;
            width: 100%;
        }

        /* Footer */
        .auth-footer {
            position: absolute;
            bottom: 28px;
            font-size: 11px;
            color: var(--muted);
            text-align: center;
        }

        /* Grain overlay */
        .auth-grain {
            pointer-events: none;
            position: fixed;
            inset: 0;
            z-index: 99;
            opacity: 0.025;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
        }

        /* Mobile */
        @media (max-width: 768px) {
            .auth-wrap { grid-template-columns: 1fr; }
            .auth-bg   { display: none; }
            .auth-panel { padding: 40px 28px; }
        }
    </style>
</head>
<body>

<div class="auth-grain"></div>

<div class="auth-wrap">

    <!-- ── SINISTRA: foto ─────────────────────────────── -->
    <div class="auth-bg">
        <img class="auth-bg-img" src="/assets/img/Sfondo-auth.webp" alt="">
        <div class="auth-bg-text">
            <h2>Digital Signage<br>per il tuo club.</h2>
            <p>Gestisci contenuti, playlist e dispositivi<br>da un'unica interfaccia.</p>
        </div>
    </div>

    <!-- ── DESTRA: form ───────────────────────────────── -->
    <div class="auth-panel">

        <img class="auth-logo" src="/assets/img/Logo_in_orizzontale.png" alt="PixelBridge">

        <div class="auth-title">Bentornato</div>
        <div class="auth-sub">Accedi per gestire il sistema</div>

        <?php if ($errore): ?>
        <div class="auth-error">
            <span>⚠</span> <?php echo htmlspecialchars($errore); ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <label>Username</label>
            <input type="text" name="username" placeholder="admin" required autofocus autocomplete="username">

            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">

            <button type="submit" class="btn-login">Accedi →</button>
        </form>

        <div class="auth-footer">
            PixelBridge Signage Manager · <?php echo date('Y'); ?>
        </div>

    </div>
</div>

</body>
</html>