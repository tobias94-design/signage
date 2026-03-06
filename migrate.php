<?php
require_once 'includes/db.php';
$db = getDB();

try {
    $db->exec("ALTER TABLE dispositivi ADD COLUMN layout TEXT DEFAULT 'standard'");
    echo "✅ Colonna layout aggiunta!";
} catch (Exception $e) {
    echo "⚠️ " . $e->getMessage();
}
?>