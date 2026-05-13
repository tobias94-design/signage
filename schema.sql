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

CREATE TABLE IF NOT EXISTS schedule (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ora_inizio TEXT NOT NULL,
    ora_fine TEXT NOT NULL,
    layer INTEGER NOT NULL CHECK(layer IN (1,2,3)),
    tipo TEXT NOT NULL CHECK(tipo IN ('tv','playlist','banner')),
    playlist_id INTEGER,
    attivo INTEGER DEFAULT 1,
    FOREIGN KEY(playlist_id) REFERENCES playlist(id) ON DELETE SET NULL
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