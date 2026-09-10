<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

// Test connessione
echo "Connessione OK<br>";

// Test query utente
$stmt = $db->prepare("SELECT id, username, password_hash, attivo, tenant_id FROM utenti LIMIT 5");
$stmt->execute();
$rows = $stmt->fetchAll();
echo "<pre>";
print_r($rows);
echo "</pre>";

// Test password verify
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uQAfS/oViy';
$ok = password_verify('password', $hash);
echo "Password verify: " . ($ok ? 'OK' : 'FAIL') . "<br>";

// Test query login completa
$stmt2 = $db->prepare("
    SELECT u.*, t.nome AS tenant_nome, t.slug AS tenant_slug, t.piano AS tenant_piano
    FROM utenti u
    JOIN tenants t ON t.id = u.tenant_id
    WHERE u.username = ?
      AND u.attivo = 1
      AND t.attivo = 1
    LIMIT 1
");
$stmt2->execute(['admin']);
$utente = $stmt2->fetch();
echo "Query login: ";
print_r($utente);
