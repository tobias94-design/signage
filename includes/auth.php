<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';

// ── MIGRAZIONE TABELLA UTENTI ─────────────────────────────────
function migraUtenti() {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS utenti (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL DEFAULT '',
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        ruolo TEXT NOT NULL DEFAULT 'operatore',
        attivo INTEGER NOT NULL DEFAULT 1,
        temp_password INTEGER DEFAULT 0,
        creato_il DATETIME DEFAULT CURRENT_TIMESTAMP,
        ultimo_accesso DATETIME DEFAULT NULL
    )");
    // Crea admin di default se non esiste nessun utente
    $count = $db->query("SELECT COUNT(*) FROM utenti")->fetchColumn();
    if ($count == 0) {
        $db->prepare("INSERT INTO utenti (nome, username, password_hash, ruolo) VALUES (?,?,?,?)")
           ->execute(['Amministratore', 'admin', password_hash('admin', PASSWORD_DEFAULT), 'admin']);
    }
}
try { migraUtenti(); } catch(Exception $e) {}

// ── FUNZIONI AUTH ─────────────────────────────────────────────
function isLoggedIn() {
    return isset($_SESSION['utente_id']) && !empty($_SESSION['utente_id']);
}

function getUtenteCorrente() {
    if (!isLoggedIn()) return null;
    return [
        'id'       => $_SESSION['utente_id'],
        'nome'     => $_SESSION['utente_nome'] ?? '',
        'username' => $_SESSION['utente_username'] ?? '',
        'ruolo'    => $_SESSION['utente_ruolo'] ?? 'operatore',
    ];
}

function isAdmin() {
    return isset($_SESSION['utente_ruolo']) && $_SESSION['utente_ruolo'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;color:#e94560;background:#0a0a1a;min-height:100vh;">
            <h2>⛔ Accesso negato</h2><p>Non hai i permessi per questa sezione.</p>
            <a href="/" style="color:#e94560;">← Dashboard</a></div>');
    }
}

function login($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM utenti WHERE username=? AND attivo=1");
    $stmt->execute([trim($username)]);
    $utente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$utente || !password_verify($password, $utente['password_hash'])) return false;
    $db->prepare("UPDATE utenti SET ultimo_accesso=CURRENT_TIMESTAMP WHERE id=?")->execute([$utente['id']]);
    $_SESSION['utente_id']       = $utente['id'];
    $_SESSION['utente_nome']     = $utente['nome'];
    $_SESSION['utente_username'] = $utente['username'];
    $_SESSION['utente_ruolo']    = $utente['ruolo'];
    $_SESSION['temp_password']   = $utente['temp_password'];
    return $utente;
}

function logout() {
    session_destroy();
    header('Location: /login.php');
    exit;
}
