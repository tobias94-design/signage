<?php
/**
 * api/claim.php
 * Gestisce il pairing dei dispositivi nuovi tramite codice a 6 cifre.
 *
 * Riscritto da zero: il file precedente era in sintassi SQLite (AUTOINCREMENT,
 * datetime('now'), INSERT OR REPLACE) e non funzionava affatto su MySQL — il
 * pairing di dispositivi nuovi era probabilmente rotto dalla migrazione in poi.
 * L'autenticazione controllava anche $_SESSION['admin'], che il sistema attuale
 * non imposta mai (usa $_SESSION['utente_id'] tramite includes/auth.php).
 *
 * Flusso (stesso modello di Yodeck — codice alfanumerico, inserimento manuale,
 * niente QR): 
 * 1. Il player, al primo avvio senza token, genera un codice a 8 caratteri e
 *    chiama action=register per registrarlo lato server (valido 10 minuti).
 * 2. Il player mostra il codice sullo schermo, poi fa polling su action=check
 *    per sapere quando è stato reclamato.
 * 3. L'admin, su dispositivi.php, inserisce il codice a mano — dispositivi.php
 *    gestisce direttamente la creazione del dispositivo con il tenant_id corretto.
 */

require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db     = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function ensure_pairing_table(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS pairing_pending (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(8) NOT NULL UNIQUE,
        machine VARCHAR(64) DEFAULT '',
        token VARCHAR(64) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires DATETIME NOT NULL,
        claimed TINYINT(1) DEFAULT 0
    )");
    // Se la tabella esisteva gia' da un test precedente con codici a 6 cifre,
    // allarga la colonna per ospitare gli 8 caratteri alfanumerici attuali
    try { $db->exec("ALTER TABLE pairing_pending MODIFY COLUMN code VARCHAR(8) NOT NULL"); } catch (Exception $e) {}
}
ensure_pairing_table($db);

// ── REGISTER: il player chiama questo all'avvio per registrare il codice ─
// Stesso modello di Yodeck: codice alfanumerico piu' lungo (8 caratteri) invece
// di un numero corto, inserito a mano nel pannello — niente QR, non serve.
// GET /api/claim.php?action=register&code=ABCD1234&machine=SALA-PESI-1
if ($action === 'register') {
    $code    = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['code'] ?? ''));
    $machine = substr(preg_replace('/[^a-zA-Z0-9\-]/', '', $_GET['machine'] ?? 'PC'), 0, 64);

    if (strlen($code) !== 8) {
        echo json_encode(['ok'=>false,'error'=>'Codice non valido, deve essere di 8 caratteri']);
        exit;
    }

    // Pulizia codici scaduti (manutenzione automatica, non serve un cron a parte)
    $db->exec("DELETE FROM pairing_pending WHERE expires < NOW()");

    $expires = date('Y-m-d H:i:s', time() + 600); // valido 10 minuti
    try {
        $db->prepare("
            INSERT INTO pairing_pending (tenant_id, code, machine, expires, claimed)
            VALUES (2, ?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE machine=VALUES(machine), expires=VALUES(expires), claimed=0, token=NULL
        ")->execute([$code, $machine, $expires]);

        echo json_encode(['ok'=>true, 'expires_in'=>600]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'Errore nel salvataggio del codice']);
    }
    exit;
}

// ── CHECK: il player fa polling per sapere se è stato associato ─────────
// GET /api/claim.php?action=check&code=ABCD1234
if ($action === 'check') {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['code'] ?? ''));

    $stmt = $db->prepare("SELECT * FROM pairing_pending WHERE code = ?");
    $stmt->execute([$code]);
    $row = $stmt->fetch();

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

echo json_encode(['ok'=>false,'error'=>'Azione non valida']);
