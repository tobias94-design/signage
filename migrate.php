<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

$migrazioni = [
    // profili
    "ALTER TABLE profili ADD COLUMN banner_colore TEXT DEFAULT '#000000'",
    "ALTER TABLE profili ADD COLUMN banner_testo_colore TEXT DEFAULT '#ffffff'",
    "ALTER TABLE profili ADD COLUMN banner_posizione TEXT DEFAULT 'bottom'",
    "ALTER TABLE profili ADD COLUMN banner_altezza INTEGER DEFAULT 80",
    "ALTER TABLE profili ADD COLUMN banner_testo TEXT DEFAULT ''",
    "ALTER TABLE profili ADD COLUMN logo TEXT DEFAULT ''",
    "ALTER TABLE profili ADD COLUMN playlist_base_id INTEGER DEFAULT NULL",
    // dispositivi
    "ALTER TABLE dispositivi ADD COLUMN sheet_url TEXT DEFAULT ''",
    "ALTER TABLE dispositivi ADD COLUMN club TEXT DEFAULT ''",
    // playlist_items — scadenza contenuto
    "ALTER TABLE playlist_items ADD COLUMN data_inizio DATE DEFAULT NULL",
    "ALTER TABLE playlist_items ADD COLUMN data_fine DATE DEFAULT NULL",
    "ALTER TABLE profilo_regole ADD COLUMN tipo TEXT DEFAULT 'base'"
];

$crea_eventi = "CREATE TABLE IF NOT EXISTS profilo_eventi (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    profilo_id    INTEGER NOT NULL,
    nome          TEXT NOT NULL DEFAULT '',
    playlist_id   INTEGER NOT NULL,
    giorni        TEXT NOT NULL DEFAULT '',
    ora_inizio    TIME DEFAULT NULL,
    ora_fine      TIME DEFAULT NULL,
    data_inizio   DATE DEFAULT NULL,
    data_fine     DATE DEFAULT NULL,
    ripetizione   TEXT DEFAULT 'settimanale',
    creato_il     DATETIME DEFAULT CURRENT_TIMESTAMP
)";

echo "<h2>Migrazione database</h2><ul>";
foreach ($migrazioni as $sql) {
    try {
        $db->exec($sql);
        echo "<li style='color:green'>✓ " . htmlspecialchars($sql) . "</li>";
    } catch (Exception $e) {
        echo "<li style='color:gray'>– già presente: " . htmlspecialchars($sql) . "</li>";
    }
}
try {
    $db->exec($crea_eventi);
    echo "<li style='color:green'>✓ Tabella profilo_eventi creata (o già presente)</li>";
} catch (Exception $e) {
    echo "<li style='color:red'>✗ Errore: " . $e->getMessage() . "</li>";
}
echo "</ul><p><strong>Fatto!</strong></p>";
?>