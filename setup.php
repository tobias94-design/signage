<?php
require_once 'includes/db.php';
$db = getDB();

$schema = "
CREATE TABLE IF NOT EXISTS contenuti (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    tipo TEXT NOT NULL CHECK(tipo IN ('video','immagine')),
    file TEXT NOT NULL,
    durata INTEGER DEFAULT 10,
    creato_il DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS playlist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    creato_il DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS playlist_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    playlist_id INTEGER NOT NULL,
    contenuto_id INTEGER NOT NULL,
    ordine INTEGER DEFAULT 0,
    FOREIGN KEY(playlist_id) REFERENCES playlist(id) ON DELETE CASCADE,
    FOREIGN KEY(contenuto_id) REFERENCES contenuti(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS profili (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    banner_attivo INTEGER DEFAULT 1,
    creato_il DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS profilo_regole (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    profilo_id INTEGER NOT NULL,
    playlist_id INTEGER NOT NULL,
    intervallo_minuti INTEGER DEFAULT 20,
    giorni TEXT DEFAULT '1,2,3,4,5,6,7',
    FOREIGN KEY(profilo_id) REFERENCES profili(id) ON DELETE CASCADE,
    FOREIGN KEY(playlist_id) REFERENCES playlist(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dispositivi (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    club TEXT NOT NULL,
    token TEXT UNIQUE NOT NULL,
    profilo_id INTEGER,
    stato TEXT DEFAULT 'offline',
    ultimo_ping DATETIME,
    creato_il DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(profilo_id) REFERENCES profili(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS impostazioni (
    chiave TEXT PRIMARY KEY,
    valore TEXT
);

INSERT OR IGNORE INTO impostazioni (chiave, valore) VALUES
    ('logo', ''),
    ('banner_colore', '#000000'),
    ('banner_testo_colore', '#ffffff'),
    ('banner_posizione', 'bottom'),
    ('banner_altezza', '60');
";

$statements = array_filter(array_map('trim', explode(';', $schema)));
foreach ($statements as $statement) {
    if (!empty($statement)) {
        $db->exec($statement);
    }
}

echo "✅ Database aggiornato correttamente!";
?>
```

Salva e vai su:
```
http://localhost:8888/setup.php
```

Devi vedere:
```
✅ Database aggiornato correttamente!