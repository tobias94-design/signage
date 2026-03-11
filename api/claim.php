<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db     = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── MIGRATE ───────────────────────────────────────────────────
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN pairing_code TEXT DEFAULT NULL"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN pairing_expires DATETIME DEFAULT NULL"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE dispositivi ADD COLUMN paired INTEGER DEFAULT 0"); } catch(Exception $e) {}

// ── REGISTER: il PC chiama questo all'avvio per registrare il codice
// GET /api/claim.php?action=register&code=123456&machine=DESKTOP-ABC
if ($action === 'register') {
    $code    = preg_replace('/[^0-9]/', '', $_GET['code'] ?? '');
    $machine = substr(preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['machine'] ?? 'PC'), 0, 64);

    if (strlen($code) !== 6) { echo json_encode(['ok'=>false,'error'=>'Codice non valido']); exit; }

    // Salva il codice in una tabella temporanea (usa dispositivi con token=NULL come pending)
    // Prima pulisce codici scaduti
    $db->exec("DELETE FROM pairing_pending WHERE expires < datetime('now')");

    // Crea tabella se non esiste
    $db->exec("CREATE TABLE IF NOT EXISTS pairing_pending (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        machine TEXT DEFAULT '',
        token TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires DATETIME,
        claimed INTEGER DEFAULT 0
    )");

    $expires = date('Y-m-d H:i:s', time() + 600); // 10 minuti
    try {
        $db->prepare("INSERT OR REPLACE INTO pairing_pending (code, machine, expires, claimed) VALUES (?,?,?,0)")
           ->execute([$code, $machine, $expires]);
        echo json_encode(['ok'=>true,'expires_in'=>600]);
    } catch(Exception $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// ── CHECK: il PC fa polling per sapere se è stato associato
// GET /api/claim.php?action=check&code=123456
if ($action === 'check') {
    $code = preg_replace('/[^0-9]/', '', $_GET['code'] ?? '');

    $db->exec("CREATE TABLE IF NOT EXISTS pairing_pending (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        machine TEXT DEFAULT '',
        token TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires DATETIME,
        claimed INTEGER DEFAULT 0
    )");

    $row = $db->query("SELECT * FROM pairing_pending WHERE code=".$db->quote($code))->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['ok'=>false,'status'=>'not_found']);
        exit;
    }
    if (strtotime($row['expires']) < time()) {
        echo json_encode(['ok'=>false,'status'=>'expired']);
        exit;
    }
    if ($row['claimed'] && $row['token']) {
        echo json_encode(['ok'=>true,'status'=>'claimed','token'=>$row['token']]);
        exit;
    }
    echo json_encode(['ok'=>true,'status'=>'waiting']);
    exit;
}

// ── ASSIGN: l'admin dal CMS associa il codice a un dispositivo (chiamata interna)
// POST /api/claim.php  action=assign  code=123456  token=soave-xxx
if ($action === 'assign') {
    // Solo da sessione admin autenticata
    session_start();
    if (empty($_SESSION['admin'])) { echo json_encode(['ok'=>false,'error'=>'Non autorizzato']); exit; }

    $code  = preg_replace('/[^0-9]/', '', $_POST['code'] ?? '');
    $token = trim($_POST['token'] ?? '');

    $db->exec("CREATE TABLE IF NOT EXISTS pairing_pending (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        machine TEXT DEFAULT '',
        token TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires DATETIME,
        claimed INTEGER DEFAULT 0
    )");

    $row = $db->query("SELECT * FROM pairing_pending WHERE code=".$db->quote($code))->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['ok'=>false,'error'=>'Codice non trovato']); exit; }
    if (strtotime($row['expires']) < time()) { echo json_encode(['ok'=>false,'error'=>'Codice scaduto']); exit; }

    $db->prepare("UPDATE pairing_pending SET token=?, claimed=1 WHERE code=?")->execute([$token, $code]);
    echo json_encode(['ok'=>true]);
    exit;
}

// ── LIST: lista codici in attesa (per dispositivi.php)
// GET /api/claim.php?action=list  (solo admin)
if ($action === 'list') {
    session_start();
    if (empty($_SESSION['admin'])) { echo json_encode(['ok'=>false,'error'=>'Non autorizzato']); exit; }

    $db->exec("CREATE TABLE IF NOT EXISTS pairing_pending (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        machine TEXT DEFAULT '',
        token TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires DATETIME,
        claimed INTEGER DEFAULT 0
    )");

    $db->exec("DELETE FROM pairing_pending WHERE expires < datetime('now')");
    $pending = $db->query("SELECT * FROM pairing_pending WHERE claimed=0 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'pending'=>$pending]);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Azione non valida']);