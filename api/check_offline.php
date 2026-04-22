<?php
/**
 * PixelBridge — Check dispositivi offline
 * Eseguito ogni 5 minuti dal cron job
 */

require_once __DIR__ . '/../includes/db.php';

$SOGLIA_MINUTI   = 10;
$SENDGRID_KEY = trim(file_get_contents('/var/www/html/config/sendgrid.key'));
$EMAIL_TO        = 'tobiasola@gymnasiumclub.net';
$EMAIL_FROM      = 'noreply@pixelbridge.it';
$EMAIL_FROM_NAME = 'PixelBridge';

$db = getDB();

// Migrazione tabella notifiche
try {
    $db->exec("CREATE TABLE IF NOT EXISTS notifiche_offline (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dispositivo_token TEXT NOT NULL,
        inviata_il DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch(Exception $e) {}

// Trova dispositivi offline da più di X minuti
$dispositivi_offline = $db->query("
    SELECT token, nome, club, ultimo_ping
    FROM dispositivi
    WHERE ultimo_ping IS NOT NULL
    AND ultimo_ping < datetime('now', '-{$SOGLIA_MINUTI} minutes')
    AND stato = 'online'
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($dispositivi_offline as $d) {
    // Controlla se abbiamo già inviato una notifica nelle ultime 2 ore
    $già_notificato = $db->query("
        SELECT COUNT(*) FROM notifiche_offline
        WHERE dispositivo_token = " . $db->quote($d['token']) . "
        AND inviata_il > datetime('now', '-2 hours')
    ")->fetchColumn();

    if ($già_notificato) continue;

    // Segna il dispositivo come offline
    $db->prepare("UPDATE dispositivi SET stato='offline' WHERE token=?")->execute([$d['token']]);

    // Invia email
    $minuti_off = round((time() - strtotime($d['ultimo_ping'])) / 60);
    $nome_disp  = $d['nome'] . ($d['club'] ? ' (' . $d['club'] . ')' : '');
    $ultimo     = $d['ultimo_ping'] ? date('d/m/Y H:i', strtotime($d['ultimo_ping'])) : '—';

    $subject = "⚠️ PixelBridge — {$nome_disp} offline da {$minuti_off} minuti";
    $body    = "
<h2 style='color:#e85002;'>⚠️ Dispositivo offline</h2>
<p>Il dispositivo <strong>{$nome_disp}</strong> non risponde da <strong>{$minuti_off} minuti</strong>.</p>
<table style='border-collapse:collapse;margin:16px 0;'>
    <tr><td style='padding:6px 16px 6px 0;color:#666;'>Dispositivo:</td><td><strong>{$d['nome']}</strong></td></tr>
    <tr><td style='padding:6px 16px 6px 0;color:#666;'>Club:</td><td>{$d['club']}</td></tr>
    <tr><td style='padding:6px 16px 6px 0;color:#666;'>Ultimo ping:</td><td>{$ultimo}</td></tr>
    <tr><td style='padding:6px 16px 6px 0;color:#666;'>Token:</td><td><code>{$d['token']}</code></td></tr>
</table>
<p><a href='https://pixelbridge.it/dispositivi.php' style='background:#e85002;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;'>Vai al pannello →</a></p>
<hr style='margin:24px 0;border:none;border-top:1px solid #eee;'>
<p style='color:#999;font-size:12px;'>PixelBridge Digital Signage — notifica automatica</p>
";

    $payload = json_encode([
        'personalizations' => [['to' => [['email' => $EMAIL_TO]]]],
        'from'    => ['email' => $EMAIL_FROM, 'name' => $EMAIL_FROM_NAME],
        'subject' => $subject,
        'content' => [['type' => 'text/html', 'value' => $body]]
    ]);

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $SENDGRID_KEY,
            'Content-Type: application/json'
        ]
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 202) {
        // Salva notifica inviata
        $db->prepare("INSERT INTO notifiche_offline (dispositivo_token) VALUES (?)")->execute([$d['token']]);
        echo "[OK] Notifica inviata per {$nome_disp}\n";
    } else {
        echo "[ERRORE] Invio fallito per {$nome_disp} — HTTP {$http_code}: {$response}\n";
    }
}

if (empty($dispositivi_offline)) {
    echo "[OK] Nessun dispositivo offline\n";
}
?>
