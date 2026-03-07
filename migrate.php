<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

$colonne = [
    "ALTER TABLE profili ADD COLUMN banner_colore TEXT DEFAULT '#000000'",
    "ALTER TABLE profili ADD COLUMN banner_testo_colore TEXT DEFAULT '#ffffff'",
    "ALTER TABLE profili ADD COLUMN banner_posizione TEXT DEFAULT 'bottom'",
    "ALTER TABLE profili ADD COLUMN banner_altezza INTEGER DEFAULT 80",
    "ALTER TABLE profili ADD COLUMN banner_testo TEXT DEFAULT ''",
    "ALTER TABLE profili ADD COLUMN logo TEXT DEFAULT ''",
    "ALTER TABLE dispositivi ADD COLUMN sheet_url TEXT DEFAULT ''",
];

foreach ($colonne as $sql) {
    try {
        $db->exec($sql);
        echo "✅ OK: $sql<br>";
    } catch (Exception $e) {
        echo "⚠️ Già esistente (skip): " . $e->getMessage() . "<br>";
    }
}

echo "<br><strong>Migrazione completata.</strong>";