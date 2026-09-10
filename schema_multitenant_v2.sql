-- ============================================================
-- PIXELBRIDGE — Schema MySQL Multi-Tenant
-- ============================================================
-- Esegui con:
-- /Applications/MAMP/Library/bin/mysql -u pb_dev -ppbdev2026 pixelbridge_dev < schema_multitenant.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- TABELLA MASTER: tenants
-- Ogni cliente è un tenant isolato
-- ============================================================
CREATE TABLE IF NOT EXISTS tenants (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(255) NOT NULL,
    slug            VARCHAR(100) NOT NULL UNIQUE,        -- es. "gymnasium", "cinema-odeon"
    email           VARCHAR(255) NOT NULL UNIQUE,        -- email admin principale
    piano           ENUM('trial','starter','pro','enterprise') DEFAULT 'trial',
    trial_scade_il  DATETIME DEFAULT NULL,
    attivo          TINYINT(1) DEFAULT 1,
    creato_il       DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- Impostazioni generali del tenant
    timezone        VARCHAR(100) DEFAULT 'Europe/Rome',
    lingua          VARCHAR(10)  DEFAULT 'it',
    logo            VARCHAR(255) DEFAULT '',
    colore_primario VARCHAR(7)   DEFAULT '#D51317'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- UTENTI (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS utenti (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    nome            VARCHAR(255) NOT NULL DEFAULT '',
    username        VARCHAR(100) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    ruolo           ENUM('superadmin','admin','operatore') DEFAULT 'operatore',
    attivo          TINYINT(1) DEFAULT 1,
    temp_password   TINYINT(1) DEFAULT 0,
    creato_il       DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_accesso  DATETIME DEFAULT NULL,
    UNIQUE KEY uq_tenant_username (tenant_id, username),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CLUB CONFIG (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS club_config (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    nome            VARCHAR(255) NOT NULL,
    num_tv_totali   INT DEFAULT 0,
    indirizzo       VARCHAR(255) DEFAULT '',
    note            TEXT,
    UNIQUE KEY uq_tenant_club (tenant_id, nome),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONTENUTI (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS contenuti (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT UNSIGNED NOT NULL,
    nome                VARCHAR(255) NOT NULL,
    tipo                ENUM('video','immagine') NOT NULL,
    file                VARCHAR(255) NOT NULL,
    durata              INT DEFAULT 10,
    inserzionista_id    INT UNSIGNED DEFAULT NULL,
    creato_il           DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PLAYLIST (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS playlist (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED NOT NULL,
    nome        VARCHAR(255) NOT NULL,
    creato_il   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PLAYLIST ITEMS
-- ============================================================
CREATE TABLE IF NOT EXISTS playlist_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    playlist_id     INT UNSIGNED NOT NULL,
    contenuto_id    INT UNSIGNED NOT NULL,
    ordine          INT DEFAULT 0,
    data_inizio     DATE DEFAULT NULL,
    data_fine       DATE DEFAULT NULL,
    FOREIGN KEY (playlist_id)  REFERENCES playlist(id)  ON DELETE CASCADE,
    FOREIGN KEY (contenuto_id) REFERENCES contenuti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PROFILI (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS profili (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT UNSIGNED NOT NULL,
    nome                VARCHAR(255) NOT NULL,
    banner_attivo       TINYINT(1) DEFAULT 1,
    banner_colore       VARCHAR(7)  DEFAULT '#000000',
    banner_testo_colore VARCHAR(7)  DEFAULT '#ffffff',
    banner_posizione    VARCHAR(20) DEFAULT 'bottom',
    banner_altezza      INT         DEFAULT 80,
    banner_testo        TEXT,
    logo                VARCHAR(255) DEFAULT '',
    playlist_base_id    INT UNSIGNED DEFAULT NULL,
    layout_tipo         VARCHAR(50)  DEFAULT 'solo_banner',
    logo_size           INT DEFAULT 75,
    data_size           INT DEFAULT 28,
    ora_size            INT DEFAULT 44,
    creato_il           DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PROFILO REGOLE
-- ============================================================
CREATE TABLE IF NOT EXISTS profilo_regole (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profilo_id          INT UNSIGNED NOT NULL,
    playlist_id         INT UNSIGNED NOT NULL,
    intervallo_minuti   INT DEFAULT 20,
    giorni              VARCHAR(50) DEFAULT '1,2,3,4,5,6,7',
    tipo                VARCHAR(20) DEFAULT 'base',
    FOREIGN KEY (profilo_id) REFERENCES profili(id)  ON DELETE CASCADE,
    FOREIGN KEY (playlist_id) REFERENCES playlist(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PROFILO EVENTI
-- ============================================================
CREATE TABLE IF NOT EXISTS profilo_eventi (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profilo_id      INT UNSIGNED NOT NULL,
    nome            VARCHAR(255) DEFAULT '',
    playlist_id     INT UNSIGNED NOT NULL,
    giorni          VARCHAR(50)  DEFAULT '',
    ora_inizio      TIME         DEFAULT NULL,
    ora_fine        TIME         DEFAULT NULL,
    data_inizio     DATE         DEFAULT NULL,
    data_fine       DATE         DEFAULT NULL,
    ripetizione     VARCHAR(20)  DEFAULT 'settimanale',
    creato_il       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profilo_id)  REFERENCES profili(id)  ON DELETE CASCADE,
    FOREIGN KEY (playlist_id) REFERENCES playlist(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DISPOSITIVI (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS dispositivi (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id               INT UNSIGNED NOT NULL,
    nome                    VARCHAR(255) NOT NULL,
    club                    VARCHAR(255) NOT NULL,
    token                   VARCHAR(100) NOT NULL UNIQUE,
    profilo_id              INT UNSIGNED DEFAULT NULL,
    stato                   VARCHAR(20)  DEFAULT 'offline',
    ultimo_ping             DATETIME     DEFAULT NULL,
    layout                  VARCHAR(50)  DEFAULT 'standard',
    sheet_url               TEXT,
    layout_tipo             VARCHAR(50)  DEFAULT 'solo_banner',
    pairing_code            VARCHAR(10)  DEFAULT NULL,
    pairing_expires         DATETIME     DEFAULT NULL,
    paired                  TINYINT(1)   DEFAULT 0,
    numero_tv               INT          DEFAULT NULL,
    indirizzo               VARCHAR(255) DEFAULT '',
    note                    TEXT,
    lat                     DECIMAL(10,7) DEFAULT NULL,
    lon                     DECIMAL(10,7) DEFAULT NULL,
    tipo_display            VARCHAR(20)  DEFAULT 'tv',
    stream_url              TEXT,
    reload_richiesto        TINYINT(1)   DEFAULT 0,
    forza_adv               TINYINT(1)   DEFAULT 0,
    loop_adv                TINYINT(1)   DEFAULT 0,
    notifica_offline_inviata TINYINT(1)  DEFAULT 0,
    lobby_citta             VARCHAR(100) DEFAULT '',
    lobby_sheet_url         TEXT,
    lobby_corsi_url         TEXT,
    creato_il               DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (profilo_id) REFERENCES profili(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAIRING PENDING (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS pairing_pending (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED NOT NULL,
    code        VARCHAR(10)  NOT NULL UNIQUE,
    machine     VARCHAR(255) DEFAULT '',
    token       VARCHAR(100) DEFAULT NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    expires     DATETIME     DEFAULT NULL,
    claimed     TINYINT(1)   DEFAULT 0,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SIDEBAR SLIDES (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS sidebar_slides (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT UNSIGNED NOT NULL,
    dispositivo_token   VARCHAR(100) DEFAULT '',
    profilo_id          INT UNSIGNED DEFAULT NULL,
    tipo                VARCHAR(30)  DEFAULT 'info',
    titolo              VARCHAR(255) DEFAULT '',
    contenuto           TEXT,
    durata              INT          DEFAULT 10,
    ordine              INT          DEFAULT 0,
    sfondo              VARCHAR(255) DEFAULT '',
    sfondo_preset       VARCHAR(100) DEFAULT '',
    colore_sfondo       VARCHAR(7)   DEFAULT '#111',
    colore_testo        VARCHAR(7)   DEFAULT '#fff',
    attivo              TINYINT(1)   DEFAULT 1,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SCHEDULE (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS schedule (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED NOT NULL,
    ora_inizio  VARCHAR(5)   NOT NULL,
    ora_fine    VARCHAR(5)   NOT NULL,
    layer       TINYINT      NOT NULL CHECK (layer IN (1,2,3)),
    tipo        VARCHAR(20)  NOT NULL,
    playlist_id INT UNSIGNED DEFAULT NULL,
    attivo      TINYINT(1)   DEFAULT 1,
    FOREIGN KEY (tenant_id)  REFERENCES tenants(id)   ON DELETE CASCADE,
    FOREIGN KEY (playlist_id) REFERENCES playlist(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- IMPOSTAZIONI (per tenant)
-- ============================================================
CREATE TABLE IF NOT EXISTS impostazioni (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED NOT NULL,
    chiave      VARCHAR(100) NOT NULL,
    valore      TEXT,
    UNIQUE KEY uq_tenant_chiave (tenant_id, chiave),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INSERZIONISTI (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS inserzionisti (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    ragione_sociale VARCHAR(255) DEFAULT '',
    referente       VARCHAR(255) DEFAULT '',
    email           VARCHAR(255) DEFAULT '',
    telefono        VARCHAR(50)  DEFAULT '',
    settore         VARCHAR(100) DEFAULT '',
    note            TEXT,
    attivo          TINYINT(1)   DEFAULT 1,
    creato_il       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONTRATTI (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS contratti (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT UNSIGNED NOT NULL,
    inserzionista_id    INT UNSIGNED NOT NULL,
    nome                VARCHAR(255) DEFAULT '',
    data_inizio         DATE         NOT NULL,
    data_fine           DATE         NOT NULL,
    importo             DECIMAL(10,2) DEFAULT 0,
    tipo_contenuto      VARCHAR(20)  DEFAULT 'entrambi',
    club_target         VARCHAR(255) DEFAULT '',
    fascia_oraria       VARCHAR(50)  DEFAULT '',
    frequenza_min       INT          DEFAULT 30,
    stato               VARCHAR(20)  DEFAULT 'attivo',
    note                TEXT,
    creato_il           DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id)        REFERENCES tenants(id)       ON DELETE CASCADE,
    FOREIGN KEY (inserzionista_id) REFERENCES inserzionisti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LOG ADV (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS log_adv (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT UNSIGNED NOT NULL,
    contenuto_id        INT UNSIGNED NOT NULL,
    dispositivo_token   VARCHAR(100) NOT NULL,
    club                VARCHAR(255) DEFAULT '',
    durata_sec          INT          DEFAULT 0,
    passato_il          DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICHE OFFLINE (con tenant_id)
-- ============================================================
CREATE TABLE IF NOT EXISTS notifiche_offline (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT UNSIGNED NOT NULL,
    dispositivo_token   VARCHAR(100) NOT NULL,
    inviata_il          DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATI INIZIALI: Gymnasium come tenant_id = 1
-- ============================================================
INSERT INTO tenants (id, nome, slug, email, piano, attivo)
VALUES (1, 'Gymnasium Club', 'gymnasium', 'admin@gymnasiumclub.it', 'pro', 1);

INSERT INTO utenti (tenant_id, nome, username, password_hash, ruolo)
VALUES (1, 'Admin', 'admin', '$2y$10$placeholder_da_sostituire', 'admin');

SET FOREIGN_KEY_CHECKS = 1;
