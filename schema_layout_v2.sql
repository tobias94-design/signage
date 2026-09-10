-- ============================================================
-- PIXELBRIDGE — Schema Layout System
-- Templates · Layers · Widget Config · ADV Scheduler
-- ============================================================
-- Aggiunge le tabelle al database pixelbridge_dev esistente

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- LAYOUT TEMPLATES
-- Canvas riutilizzabili, assegnabili a più dispositivi
-- ============================================================
CREATE TABLE IF NOT EXISTS layout_templates (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    nome            VARCHAR(255) NOT NULL,
    descrizione     TEXT,
    -- Canvas dimensions
    canvas_w        INT NOT NULL DEFAULT 1920,   -- 1920 landscape | 1080 portrait
    canvas_h        INT NOT NULL DEFAULT 1080,   -- 1080 landscape | 1920 portrait
    orientamento    ENUM('landscape','portrait') DEFAULT 'landscape',
    -- Meta
    is_default      TINYINT(1) DEFAULT 0,        -- template di default del tenant
    creato_il       DATETIME DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LAYOUT LAYERS
-- Ogni layer è posizionato in pixel sul canvas del template
-- Contiene un widget con la sua configurazione JSON
-- ============================================================
CREATE TABLE IF NOT EXISTS layout_layers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id     INT UNSIGNED NOT NULL,
    -- Identificazione
    nome            VARCHAR(100) NOT NULL,        -- es. "Sidebar destra", "Banner top"
    widget_type     VARCHAR(50)  NOT NULL,        -- tv | adv | logo_ora | corsi | meteo |
                                                  -- countdown | info | immagine | qrcode | ticker
    -- Posizione e dimensioni (pixel assoluti su canvas 1920x1080)
    pos_x           INT NOT NULL DEFAULT 0,
    pos_y           INT NOT NULL DEFAULT 0,
    width           INT NOT NULL DEFAULT 400,
    height          INT NOT NULL DEFAULT 1080,
    -- Stacking
    z_index         INT NOT NULL DEFAULT 1,
    -- Visibilità
    visible         TINYINT(1) DEFAULT 1,
    -- Configurazione specifica del widget (JSON)
    -- Vedi sezione "Widget Config Reference" sotto
    config          JSON,
    -- Compatibilità hardware
    -- NULL = compatibile con tutti | oppure lista: ["pi","pc","brightsign"]
    hw_compatibile  JSON DEFAULT NULL,
    -- Ordinamento nell'editor
    ordine          INT DEFAULT 0,
    creato_il       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES layout_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DISPOSITIVI — campi aggiuntivi (ALTER della tabella esistente)
-- ============================================================
-- Aggiungi colonne a dispositivi (esegui solo se non esistono già)
ALTER TABLE dispositivi ADD COLUMN template_id     INT UNSIGNED DEFAULT NULL;
ALTER TABLE dispositivi ADD COLUMN hw_type         ENUM('pi','android','brightsign','pc','altro') DEFAULT 'altro';
ALTER TABLE dispositivi ADD COLUMN layer_overrides JSON DEFAULT NULL;
ALTER TABLE dispositivi ADD CONSTRAINT fk_dispositivi_template
    FOREIGN KEY (template_id) REFERENCES layout_templates(id) ON DELETE SET NULL;

-- ============================================================
-- ADV SCHEDULER — sistema separato dal layout
-- Il layer ADV "ascolta" lo scheduler, non lo gestisce
-- ============================================================

-- Playlist ADV (separata dalla playlist contenuti generici)
CREATE TABLE IF NOT EXISTS adv_playlists (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    nome            VARCHAR(255) NOT NULL,
    descrizione     TEXT,
    -- Modalità loop
    loop_mode       ENUM('loop','alternata','smart') DEFAULT 'loop',
    -- loop     = solo ADV in loop continuo (no TV)
    -- alternata = ADV si inserisce ogni N minuti nel segnale TV/signage
    -- smart     = il sistema decide in base a regole orarie
    creato_il       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items della playlist ADV
CREATE TABLE IF NOT EXISTS adv_playlist_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    playlist_id     INT UNSIGNED NOT NULL,
    contenuto_id    INT UNSIGNED NOT NULL,
    ordine          INT DEFAULT 0,
    durata_sec      INT DEFAULT 15,              -- durata override (se NULL usa quella del contenuto)
    data_inizio     DATE DEFAULT NULL,           -- validità temporale
    data_fine       DATE DEFAULT NULL,
    attivo          TINYINT(1) DEFAULT 1,
    FOREIGN KEY (playlist_id)  REFERENCES adv_playlists(id) ON DELETE CASCADE,
    FOREIGN KEY (contenuto_id) REFERENCES contenuti(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regole scheduler ADV
-- Definisce QUANDO e COME l'ADV prende il controllo dello schermo
CREATE TABLE IF NOT EXISTS adv_regole (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    playlist_id     INT UNSIGNED NOT NULL,
    nome            VARCHAR(255) DEFAULT '',
    -- Target: a quale dispositivo/gruppo si applica
    -- NULL = tutti i dispositivi del tenant
    dispositivo_token VARCHAR(100) DEFAULT NULL,
    club_target     VARCHAR(255) DEFAULT NULL,   -- applica a tutti i device di un club
    -- Quando
    giorni          VARCHAR(20)  DEFAULT '1,2,3,4,5,6,7', -- 1=lun, 7=dom
    ora_inizio      TIME         DEFAULT NULL,
    ora_fine        TIME         DEFAULT NULL,
    -- Frequenza (per loop_mode = 'alternata')
    intervallo_min  INT DEFAULT 20,              -- ogni quanti minuti inserisce ADV
    durata_adv_sec  INT DEFAULT 30,              -- per quanti secondi va l'ADV
    -- Fullscreen override
    fullscreen      TINYINT(1) DEFAULT 0,        -- 1 = ADV va sopra tutti i layer
    -- Priorità (più alto = prevale su altre regole)
    priorita        INT DEFAULT 1,
    attivo          TINYINT(1) DEFAULT 1,
    creato_il       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id)   REFERENCES tenants(id)      ON DELETE CASCADE,
    FOREIGN KEY (playlist_id) REFERENCES adv_playlists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- WIDGET CONFIG REFERENCE
-- Documentazione dei campi JSON per ogni widget_type
-- ============================================================

/*
── WIDGET: logo_ora ──────────────────────────────────────────
{
  "logo": "uploads/logo.png",       // path logo
  "mostra_data": true,
  "mostra_ora": true,
  "sfondo_ora": "#000000",          // sfondo dietro ora (opaco per leggibilità)
  "sfondo_ora_opacity": 0.6,
  "font_size_ora": 44,
  "font_size_data": 28,
  "colore_testo": "#ffffff",
  "logo_size": 75                   // % altezza del layer
}

── WIDGET: tv ────────────────────────────────────────────────
{
  "stream_url": "",                 // URL stream HLS/RTSP opzionale
  "mantieni_proporzioni": true,     // forza 16:9 sempre
  "hw_required": ["pi","pc"]        // solo questi HW possono usarlo
}

── WIDGET: adv ───────────────────────────────────────────────
{
  "playlist_id": 1,                 // quale adv_playlist usare
  "fullscreen_enabled": true,       // può andare fullscreen
  "mostra_overlay_ora": false       // mostra widget ora durante ADV
}

── WIDGET: corsi ─────────────────────────────────────────────
{
  "sheet_url": "https://...",       // Google Sheets CSV
  "max_corsi": 4,                   // quanti corsi mostrare
  "mostra_badge_live": true,
  "titolo": "In programma oggi"
}

── WIDGET: meteo ─────────────────────────────────────────────
{
  "citta": "Soave",
  "lat": 45.42,
  "lon": 11.22,
  "unita": "celsius"
}

── WIDGET: countdown ─────────────────────────────────────────
{
  "titolo": "Prossima riapertura",
  "data_target": "2026-09-01T08:00:00",
  "mostra_secondi": false
}

── WIDGET: info ──────────────────────────────────────────────
{
  "testo": "Chiusi per ferie dal 10 al 20 agosto",
  "font_size": 28,
  "colore_sfondo": "#111111",
  "colore_testo": "#ffffff",
  "allineamento": "center"
}

── WIDGET: immagine ──────────────────────────────────────────
{
  "file": "uploads/sponsor.jpg",
  "object_fit": "cover",            // cover | contain | fill
  "rotazione": false,               // se true, usa files[] per slideshow
  "files": [],
  "durata_sec": 10
}

── WIDGET: qrcode ────────────────────────────────────────────
{
  "url": "https://gymnasiumclub.it/app",
  "titolo": "Scarica la nostra app",
  "colore_qr": "#000000",
  "colore_sfondo": "#ffffff"
}

── WIDGET: ticker ────────────────────────────────────────────
{
  "testi": ["Benvenuti al Gymnasium Club", "Orari: Lun-Ven 7:00-22:00"],
  "velocita": 60,                   // px/sec
  "colore_sfondo": "#D51317",
  "colore_testo": "#ffffff",
  "font_size": 20
}
*/
