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
    "ALTER TABLE dispositivi ADD COLUMN layout_tipo TEXT DEFAULT 'solo_banner'",
    // playlist_items
    "ALTER TABLE playlist_items ADD COLUMN data_inizio DATE DEFAULT NULL",
    "ALTER TABLE playlist_items ADD COLUMN data_fine DATE DEFAULT NULL",
    // profilo_regole
    "ALTER TABLE profilo_regole ADD COLUMN tipo TEXT DEFAULT 'base'",
    // sidebar_slides
    "ALTER TABLE sidebar_slides ADD COLUMN sfondo_preset TEXT DEFAULT ''",
    "ALTER TABLE sidebar_slides ADD COLUMN dispositivo_token TEXT DEFAULT ''",
    // contenuti — inserzionista
    "ALTER TABLE contenuti ADD COLUMN inserzionista_id INTEGER DEFAULT NULL",
];

$tabelle = [
    'profilo_eventi' => "CREATE TABLE IF NOT EXISTS profilo_eventi (
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
    )",
    'sidebar_slides' => "CREATE TABLE IF NOT EXISTS sidebar_slides (
        id               INTEGER PRIMARY KEY AUTOINCREMENT,
        profilo_id       INTEGER DEFAULT NULL,
        dispositivo_token TEXT DEFAULT '',
        tipo             TEXT NOT NULL DEFAULT 'info',
        titolo           TEXT DEFAULT '',
        contenuto        TEXT DEFAULT '{}',
        durata           INTEGER DEFAULT 10,
        ordine           INTEGER DEFAULT 0,
        sfondo           TEXT DEFAULT '',
        sfondo_preset    TEXT DEFAULT '',
        colore_sfondo    TEXT DEFAULT '#111111',
        colore_testo     TEXT DEFAULT '#ffffff',
        attivo           INTEGER DEFAULT 1,
        creato_il        DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    'inserzionisti' => "CREATE TABLE IF NOT EXISTS inserzionisti (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        ragione_sociale TEXT NOT NULL DEFAULT '',
        referente       TEXT DEFAULT '',
        email           TEXT DEFAULT '',
        telefono        TEXT DEFAULT '',
        settore         TEXT DEFAULT '',
        note            TEXT DEFAULT '',
        attivo          INTEGER DEFAULT 1,
        creato_il       DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    'contratti' => "CREATE TABLE IF NOT EXISTS contratti (
        id                INTEGER PRIMARY KEY AUTOINCREMENT,
        inserzionista_id  INTEGER NOT NULL,
        nome              TEXT DEFAULT '',
        data_inizio       DATE NOT NULL,
        data_fine         DATE NOT NULL,
        importo           REAL DEFAULT 0,
        tipo_contenuto    TEXT DEFAULT 'entrambi',
        club_target       TEXT DEFAULT '',
        fascia_oraria     TEXT DEFAULT '',
        frequenza_min     INTEGER DEFAULT 30,
        stato             TEXT DEFAULT 'attivo',
        note              TEXT DEFAULT '',
        creato_il         DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    'log_adv' => "CREATE TABLE IF NOT EXISTS log_adv (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        contenuto_id    INTEGER NOT NULL,
        dispositivo_token TEXT NOT NULL,
        club            TEXT DEFAULT '',
        durata_sec      INTEGER DEFAULT 0,
        passato_il      DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
];

echo "<h2 style='font-family:sans-serif;'>Migrazione database PixelBridge</h2><ul style='font-family:monospace;'>";

foreach ($migrazioni as $sql) {
    try {
        $db->exec($sql);
        echo "<li style='color:green'>✓ " . htmlspecialchars($sql) . "</li>";
    } catch (Exception $e) {
        echo "<li style='color:gray'>– già presente</li>";
    }
}

foreach ($tabelle as $nome => $sql) {
    try {
        $db->exec($sql);
        echo "<li style='color:green'>✓ Tabella <strong>$nome</strong> OK</li>";
    } catch (Exception $e) {
        echo "<li style='color:red'>✗ Errore $nome: " . $e->getMessage() . "</li>";
    }
}

echo "</ul><p style='font-family:sans-serif;'><strong>✅ Fatto!</strong></p>";
?>
