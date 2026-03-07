<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

$migrazioni = [
    "ALTER TABLE dispositivi ADD COLUMN layout TEXT DEFAULT 'standard'",
    "ALTER TABLE dispositivi ADD COLUMN sheet_url TEXT DEFAULT ''",
];

foreach ($migrazioni as $sql) {
    try {
        $db->exec($sql);
        echo "✅ OK: $sql<br>";
    } catch (Exception $e) {
        echo "ℹ️ Già presente: $sql<br>";
    }
}

echo "<br><strong>Migrazione completata!</strong>";