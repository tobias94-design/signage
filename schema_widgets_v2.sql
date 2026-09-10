-- ============================================================
-- PIXELBRIDGE — Schema Widget System
-- Sidebar Slides · Widget Themes · Logo Widget
-- Integra con layout_layers esistente
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- WIDGET THEMES
-- Varianti grafiche predefinite per ogni tipo di widget
-- es. corsi: 'dark' | 'glass' | 'minimal'
-- ============================================================
CREATE TABLE IF NOT EXISTS widget_themes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,       -- NULL = tema globale sistema
    widget_type     VARCHAR(50)  NOT NULL,        -- a quale widget si applica
    nome            VARCHAR(100) NOT NULL,        -- es. "Glass", "Dark", "Minimal"
    slug            VARCHAR(50)  NOT NULL,        -- es. "glass", "dark", "minimal"
    is_system       TINYINT(1)   DEFAULT 0,       -- 1 = tema di sistema, non modificabile
    -- Configurazione grafica
    config          JSON         NOT NULL,        -- colori, font, sfondo, border-radius ecc.
    -- Anteprima
    preview_css     TEXT,                         -- CSS inline per anteprima nell'editor
    creato_il       DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_widget_slug (tenant_id, widget_type, slug),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SIDEBAR SLIDES
-- Lista ordinata di widget dentro un layer di tipo 'sidebar'
-- Ogni slide ha il suo widget_type, durata, tema e config
-- ============================================================
CREATE TABLE IF NOT EXISTS sidebar_slides_v2 (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    layer_id        INT UNSIGNED NOT NULL,        -- FK al layout_layer di tipo 'sidebar'
    tenant_id       INT UNSIGNED NOT NULL,
    -- Widget
    widget_type     VARCHAR(50)  NOT NULL,        -- immagine | countdown | meteo | info |
                                                  -- video | corsi | qrcode
    titolo          VARCHAR(255) DEFAULT '',
    -- Tema grafico
    theme_slug      VARCHAR(50)  DEFAULT 'dark',  -- riferimento a widget_themes.slug
    theme_override  JSON         DEFAULT NULL,    -- override custom su colori/font del tema
    -- Contenuto specifico del widget
    config          JSON,
    -- Durata nella rotazione sidebar
    durata_sec      INT          DEFAULT 10,
    -- Fullscreen su dispositivi senza TV (es. Android)
    fullscreen_mode TINYINT(1)   DEFAULT 0,       -- se 1, questo widget può andare FS
    -- Ordinamento e stato
    ordine          INT          DEFAULT 0,
    attivo          TINYINT(1)   DEFAULT 1,
    creato_il       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (layer_id)  REFERENCES layout_layers(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AGGIORNAMENTO layout_layers
-- Aggiungi campo per widget ora (overlay ADV-safe)
-- e per sidebar fullscreen su Android
-- ============================================================
ALTER TABLE layout_layers
    ADD COLUMN adv_safe      TINYINT(1) DEFAULT 0,
    -- 1 = questo layer NON viene coperto dall'ADV fullscreen
    -- usato per widget ora overlay
    ADD COLUMN sidebar_fullscreen_on_mobile TINYINT(1) DEFAULT 0;
    -- 1 = su Android/dispositivi senza TV, la sidebar va fullscreen

-- ============================================================
-- TEMI DI SISTEMA — dati iniziali
-- ============================================================

-- Tema: Dark (default)
INSERT INTO widget_themes (tenant_id, widget_type, nome, slug, is_system, config) VALUES
(1, 'corsi',     'Dark',    'dark',    1, '{"bg":"#111111","text":"#ffffff","accent":"#D51317","font":"JetBrains Mono","border_radius":12,"badge_live":true}'),
(1, 'corsi',     'Glass',   'glass',   1, '{"bg":"rgba(255,255,255,0.08)","text":"#ffffff","accent":"#D51317","font":"JetBrains Mono","border_radius":16,"backdrop_blur":12}'),
(1, 'corsi',     'Minimal', 'minimal', 1, '{"bg":"#ffffff","text":"#111111","accent":"#D51317","font":"Inter","border_radius":8,"badge_live":false}'),

(1, 'meteo',     'Dark',    'dark',    1, '{"bg":"#111111","text":"#ffffff","accent":"#3b82f6","font":"Inter","border_radius":12}'),
(1, 'meteo',     'Glass',   'glass',   1, '{"bg":"rgba(255,255,255,0.08)","text":"#ffffff","accent":"#93c5fd","font":"Inter","border_radius":16,"backdrop_blur":12}'),

(1, 'countdown', 'Dark',    'dark',    1, '{"bg":"#111111","text":"#ffffff","accent":"#f59e0b","font":"JetBrains Mono","border_radius":12,"mostra_secondi":false}'),
(1, 'countdown', 'Glass',   'glass',   1, '{"bg":"rgba(255,255,255,0.08)","text":"#ffffff","accent":"#fcd34d","font":"JetBrains Mono","border_radius":16,"backdrop_blur":12}'),

(1, 'info',      'Dark',    'dark',    1, '{"bg":"#111111","text":"#ffffff","accent":"#10b981","font":"Inter","border_radius":12,"allineamento":"center"}'),
(1, 'info',      'Glass',   'glass',   1, '{"bg":"rgba(255,255,255,0.08)","text":"#ffffff","accent":"#6ee7b7","font":"Inter","border_radius":16,"backdrop_blur":12}'),
(1, 'info',      'Minimal', 'minimal', 1, '{"bg":"#ffffff","text":"#111111","accent":"#10b981","font":"Inter","border_radius":8}'),

(1, 'qrcode',    'Dark',    'dark',    1, '{"bg":"#111111","text":"#ffffff","qr_fg":"#ffffff","qr_bg":"#111111","font":"Inter","border_radius":12}'),
(1, 'qrcode',    'Light',   'light',   1, '{"bg":"#ffffff","text":"#111111","qr_fg":"#000000","qr_bg":"#ffffff","font":"Inter","border_radius":12}');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- CONFIG REFERENCE — sidebar_slides_v2.config per widget_type
-- ============================================================
/*
── corsi ─────────────────────────────────────────────────────
{
  "sheet_url": "https://...",
  "max_corsi": 4,
  "titolo": "In programma oggi"
}

── meteo ─────────────────────────────────────────────────────
{
  "citta": "Soave",
  "lat": 45.42,
  "lon": 11.22
}

── countdown ─────────────────────────────────────────────────
{
  "titolo": "Riapertura",
  "data_target": "2026-09-01T08:00:00"
}

── info ──────────────────────────────────────────────────────
{
  "testo": "Chiusi per ferie",
  "font_size": 28
}

── immagine ──────────────────────────────────────────────────
{
  "files": ["uploads/img1.jpg", "uploads/img2.jpg"],
  "durata_per_immagine": 5,
  "object_fit": "cover"
}

── video ─────────────────────────────────────────────────────
{
  "file": "uploads/video.mp4",
  "loop": true,
  "muted": true
}

── qrcode ────────────────────────────────────────────────────
{
  "url": "https://...",
  "titolo": "Scarica la nostra app"
}
*/
