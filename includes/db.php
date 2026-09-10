<?php
// ============================================================
// PixelBridge — db.php
// Database PDO MySQL + Multi-Tenant
// ============================================================

// ── Configurazione ───────────────────────────────────────────
// Le credenziali vere vivono in config.local.php, che NON e' su git
// (vedi config.example.php per sapere cosa deve contenere).
if (!file_exists(__DIR__ . '/config.local.php')) {
    die('Manca includes/config.local.php — copialo da config.example.php e inserisci le credenziali reali.');
}
require_once __DIR__ . '/config.local.php';

// ── Connessione PDO (singleton) ──────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // In produzione non esporre dettagli dell'errore
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['errore' => 'Errore di connessione al database']));
        }
    }
    return $pdo;
}

// ── Tenant ID dalla sessione ─────────────────────────────────
// Restituisce il tenant_id dell'utente loggato.
// Le API pubbliche (stato.php, claim.php) ricavano il tenant
// dal token del dispositivo — vedi getTenantFromToken().
function getTenantId(): int {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['tenant_id'])) {
        // Non loggato — redirect al login se siamo in una pagina admin
        if (!defined('API_MODE')) {
            header('Location: /login.php');
            exit;
        }
        return 0;
    }
    return (int) $_SESSION['tenant_id'];
}

// ── Tenant ID dal token dispositivo (per le API pubbliche) ───
// Usato da stato.php, claim.php, reload_check.php, ecc.
// Il token è UNIQUE nella tabella dispositivi → ricaviamo il tenant.
function getTenantFromToken(string $token): int {
    $db = getDB();
    $stmt = $db->prepare('SELECT tenant_id FROM dispositivi WHERE token = ? LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ? (int) $row['tenant_id'] : 0;
}

// ── Helper: query con tenant_id automatico ───────────────────
// Esegue una SELECT aggiungendo sempre WHERE tenant_id = ?
// Uso: dbQuery('SELECT * FROM dispositivi WHERE stato = ?', ['online'])
function dbQuery(string $sql, array $params = [], int $tenant_id = 0): array {
    $db = getDB();
    // Inietta tenant_id come primo parametro se la query usa :tenant_id
    if (str_contains($sql, ':tenant_id')) {
        $params = array_merge([':tenant_id' => $tenant_id ?: getTenantId()], $params);
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ── Helper: singola riga ─────────────────────────────────────
function dbQueryOne(string $sql, array $params = [], int $tenant_id = 0): ?array {
    $results = dbQuery($sql, $params, $tenant_id);
    return $results[0] ?? null;
}

// ── Helper: execute (INSERT/UPDATE/DELETE) ───────────────────
function dbExecute(string $sql, array $params = []): int {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $db->lastInsertId() ?: $stmt->rowCount();
}

// ── Helper: ultimo insert ID ─────────────────────────────────
function dbLastId(): int {
    return (int) getDB()->lastInsertId();
}
