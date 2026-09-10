<?php
// ============================================================
// PixelBridge — login.php
// Autenticazione multi-tenant
// ============================================================
session_start();

// Se già loggato vai alla dashboard
if (isset($_SESSION['tenant_id']) && isset($_SESSION['utente_id'])) {
    header('Location: /dashboard.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';

$errore  = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $errore = 'Inserisci username e password.';
    } else {
        $db = getDB();

        // Cerca utente attivo — join con tenant per verificare che sia attivo
        $stmt = $db->prepare("
            SELECT u.*, t.nome AS tenant_nome, t.slug AS tenant_slug, t.piano AS tenant_piano
            FROM utenti u
            JOIN tenants t ON t.id = u.tenant_id
            WHERE u.username = ?
              AND u.attivo = 1
              AND t.attivo = 1
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $utente = $stmt->fetch();

        if (!$utente || !password_verify($password, $utente['password_hash'])) {
            // Messaggio generico — non rivelare se username esiste
            $errore = 'Credenziali non valide.';
        } else {
            // Login OK — scrivi sessione
            session_regenerate_id(true);

            $_SESSION['utente_id']    = $utente['id'];
            $_SESSION['tenant_id']    = $utente['tenant_id'];
            $_SESSION['tenant_nome']  = $utente['tenant_nome'];
            $_SESSION['tenant_slug']  = $utente['tenant_slug'];
            $_SESSION['tenant_piano'] = $utente['tenant_piano'];
            $_SESSION['ruolo']        = $utente['ruolo'];
            $_SESSION['nome']         = $utente['nome'];
            $_SESSION['username']     = $utente['username'];

            // Aggiorna ultimo accesso
            $db->prepare("UPDATE utenti SET ultimo_accesso = NOW() WHERE id = ?")
               ->execute([$utente['id']]);

            // Password temporanea — forza cambio
            if ($utente['temp_password']) {
                header('Location: /profilo.php?cambio_password=1');
                exit;
            }

            header('Location: /dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PixelBridge — Login</title>
    <link rel="stylesheet" href="/assets/css/style-glass.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0a0a;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        /* Sfondo animato */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 20% 30%, rgba(213,19,23,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 80% 70%, rgba(213,19,23,0.08) 0%, transparent 60%);
            pointer-events: none;
        }

        .login-wrap {
            width: 100%;
            max-width: 420px;
            padding: 24px;
            position: relative;
            z-index: 1;
        }

        /* Logo */
        .logo {
            text-align: center;
            margin-bottom: 40px;
        }
        .logo-text {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #ffffff;
        }
        .logo-text span { color: #D51317; }
        .logo-sub {
            font-size: 13px;
            color: rgba(255,255,255,0.4);
            margin-top: 4px;
            letter-spacing: 0.3px;
        }

        /* Card */
        .card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 36px 32px;
            backdrop-filter: blur(20px);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 24px;
        }

        /* Form */
        .field {
            margin-bottom: 18px;
        }
        .field label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .field input {
            width: 100%;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 13px 16px;
            font-size: 15px;
            color: #ffffff;
            outline: none;
            transition: border-color 0.2s;
            font-family: inherit;
        }
        .field input:focus {
            border-color: rgba(213,19,23,0.6);
            background: rgba(255,255,255,0.08);
        }
        .field input::placeholder {
            color: rgba(255,255,255,0.2);
        }

        /* Errore */
        .alert-error {
            background: rgba(213,19,23,0.12);
            border: 1px solid rgba(213,19,23,0.3);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: #ff6b6b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Button */
        .btn-login {
            width: 100%;
            background: #D51317;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            font-family: inherit;
            margin-top: 8px;
        }
        .btn-login:hover  { background: #b8000f; }
        .btn-login:active { transform: scale(0.99); }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 28px;
            font-size: 12px;
            color: rgba(255,255,255,0.2);
        }
        .login-footer a {
            color: rgba(255,255,255,0.4);
            text-decoration: none;
        }
        .login-footer a:hover { color: rgba(255,255,255,0.7); }
    </style>
</head>
<body>

<div class="login-wrap">

    <div class="logo">
        <div class="logo-text">PIXEL<span>BRIDGE</span></div>
        <div class="logo-sub">Digital Signage Platform</div>
    </div>

    <div class="card">
        <div class="card-title">Accedi al pannello</div>

        <?php if ($errore): ?>
        <div class="alert-error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?php echo htmlspecialchars($errore); ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <div class="field">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Il tuo username"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn-login">Accedi</button>
        </form>
    </div>

    <div class="login-footer">
        &copy; <?php echo date('Y'); ?> PixelBridge &nbsp;·&nbsp;
        <a href="mailto:support@pixelbridge.it">Supporto</a>
    </div>

</div>

</body>
</html>
