<?php
// ============================================================
// PixelBridge — includes/auth.php
// Middleware autenticazione multi-tenant
// Da includere in CIMA a ogni pagina admin protetta
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Controlla sessione valida ────────────────────────────────
if (empty($_SESSION['tenant_id']) || empty($_SESSION['utente_id'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/dashboard.php');
    header('Location: /login.php?redirect=' . $redirect);
    exit;
}

// ── Costanti di sessione usabili in ogni pagina ──────────────
define('TENANT_ID',    (int)    $_SESSION['tenant_id']);
define('UTENTE_ID',    (int)    $_SESSION['utente_id']);
define('UTENTE_NOME',           $_SESSION['nome']         ?? '');
define('UTENTE_RUOLO',          $_SESSION['ruolo']        ?? 'operatore');
define('TENANT_NOME',           $_SESSION['tenant_nome']  ?? '');
define('TENANT_SLUG',           $_SESSION['tenant_slug']  ?? '');
define('TENANT_PIANO',          $_SESSION['tenant_piano'] ?? 'trial');

// ── Helper ruoli ─────────────────────────────────────────────
function isAdmin(): bool {
    return in_array(UTENTE_RUOLO, ['admin', 'superadmin']);
}

function isSuperAdmin(): bool {
    return UTENTE_RUOLO === 'superadmin';
}

// Blocca accesso se non hai il ruolo richiesto
function requireRole(string $ruolo_minimo): void {
    $gerarchia = ['operatore' => 1, 'admin' => 2, 'superadmin' => 3];
    $richiesto  = $gerarchia[$ruolo_minimo]   ?? 1;
    $attuale    = $gerarchia[UTENTE_RUOLO]    ?? 1;

    if ($attuale < $richiesto) {
        http_response_code(403);
        die('
            <div style="font-family:sans-serif;text-align:center;padding:80px;color:#fff;background:#0a0a0a;min-height:100vh;">
                <div style="font-size:48px;margin-bottom:16px;">🔒</div>
                <div style="font-size:20px;font-weight:600;margin-bottom:8px;">Accesso non autorizzato</div>
                <div style="color:rgba(255,255,255,0.5);margin-bottom:24px;">Non hai i permessi per accedere a questa pagina.</div>
                <a href="/dashboard.php" style="color:#D51317;text-decoration:none;">← Torna alla dashboard</a>
            </div>
        ');
    }
}

// ── Verifica periodica sessione nel DB (ogni 5 min) ──────────
// Evita che utenti disattivati rimangano loggati
$ora = time();
if (empty($_SESSION['last_db_check']) || ($ora - $_SESSION['last_db_check']) > 300) {
    require_once __DIR__ . '/db.php';
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT u.attivo, t.attivo AS tenant_attivo
        FROM utenti u
        JOIN tenants t ON t.id = u.tenant_id
        WHERE u.id = ? AND u.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([UTENTE_ID, TENANT_ID]);
    $check = $stmt->fetch();

    if (!$check || !$check['attivo'] || !$check['tenant_attivo']) {
        // Account o tenant disattivato — logout forzato
        session_destroy();
        header('Location: /login.php?msg=sessione_scaduta');
        exit;
    }

    $_SESSION['last_db_check'] = $ora;
}
