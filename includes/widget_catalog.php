<?php
/**
 * CATALOGO WIDGET — definizione unica, condivisa da:
 *  - /widgets.php (pagina catalogo visibile al cliente)
 *  - /templates.php (editor canvas — palette + gating piano)
 *
 * tier: 'base' (sempre incluso) | 'premium' (pool selezionabile Plus/Professional)
 * contesto: 'canvas' | 'sidebar' | 'ibrido' (funziona in entrambi)
 */

function getWidgetCatalog(): array {
    return [
        'logo' => [
            'label' => 'Logo',
            'color' => '#2578D1',
            'icon'  => 'fa-copyright',
            'tier'  => 'base',
            'contesto' => 'ibrido', // canvas libero o dentro slide sidebar (branding)
            'descrizione' => 'Mostra il logo del club o del brand. Selezionabile dalla libreria contenuti o dal Brand Kit.',
            'default' => ['w'=>300,'h'=>120], 'min' => ['w'=>80,'h'=>40], 'max' => ['w'=>1920,'h'=>1080],
            'funzionalita' => [
                'Selezione immagine da libreria contenuti o dal Brand Kit del tenant',
                'Ridimensionamento proporzionale (20-100% dell\'area del layer)',
                'Allineamento orizzontale (sinistra/centro/destra) quando il layer e piu largo dell\'immagine',
                'Sfondo del layer personalizzabile (colore o gradiente) indipendente dal logo',
            ],
            'impostazioni' => [
                ['chiave'=>'file', 'label'=>'Immagine', 'tipo'=>'libreria', 'descrizione'=>'File scelto dalla libreria contenuti o dal Brand Kit'],
                ['chiave'=>'logo_size', 'label'=>'Dimensione', 'tipo'=>'slider 20-100%', 'descrizione'=>'Percentuale di riempimento dell\'area del layer'],
                ['chiave'=>'align', 'label'=>'Allineamento', 'tipo'=>'select', 'descrizione'=>'left / center / right'],
                ['chiave'=>'bg_color', 'label'=>'Sfondo layer', 'tipo'=>'color/gradiente', 'descrizione'=>'Colore o gradiente dietro al logo (es. banner bianco)'],
            ],
            'varianti' => [
                [
                    'id' => 'standalone',
                    'label' => 'Libero sul canvas',
                    'contesto' => 'canvas',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Logo posizionato liberamente, dimensione e posizione a piacere. Nessuna differenza strutturale tra landscape e portrait: le proporzioni restano quelle originali dell\'immagine.',
                ],
                [
                    'id' => 'sidebar_slide',
                    'label' => 'Slide branding in sidebar',
                    'contesto' => 'sidebar',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Il logo occupa l\'intera slide della sidebar per qualche secondo, utile come slide brand tra una slide informativa e l\'altra. Stessa configurazione (file, dimensione, allineamento) del contesto canvas.',
                ],
            ],
        ],
        'time' => [
            'label' => 'Orologio',
            'color' => '#1558a8',
            'icon'  => 'fa-clock',
            'tier'  => 'base',
            'contesto' => 'ibrido', // canvas libero o slide dedicata in sidebar
            'descrizione' => 'Ora, minuti e secondi in tempo reale, con 6 stili grafici selezionabili (anelli di progresso, flip clock, barra, minimal, neon).',
            'default' => ['w'=>260,'h'=>140], 'min' => ['w'=>100,'h'=>60], 'max' => ['w'=>1920,'h'=>500],
            'funzionalita' => [
                'Ora aggiornata ogni secondo lato client, nessuna chiamata al server',
                'Anelli e barre di progresso legati ai secondi reali (un giro completo ogni 60 secondi)',
                'Data del giorno in formato esteso italiano (es. Lun 08 Lug)',
                'Sfondo box indipendente dal testo, sempre leggibile su qualsiasi sfondo layer',
                '6 stili grafici pronti, ognuno con colori e font size personalizzabili',
            ],
            'impostazioni' => [
                ['chiave'=>'stile', 'label'=>'Stile grafico', 'tipo'=>'select', 'descrizione'=>'ring_pill / flip / bar_below / ring_seconds / minimal_apple / neon_gym, vedi Template Grafici'],
                ['chiave'=>'text_color', 'label'=>'Colore testo', 'tipo'=>'color', 'descrizione'=>'Colore di ora e data in tutti gli stili'],
                ['chiave'=>'sfondo_ora', 'label'=>'Sfondo box orologio', 'tipo'=>'color', 'descrizione'=>'Colore dietro al box orologio (usato da flip, card, neon), indipendente dallo sfondo del layer'],
                ['chiave'=>'sfondo_ora_opacity', 'label'=>'Opacita box orologio', 'tipo'=>'slider 0-1', 'descrizione'=>'Trasparenza del box sfondo orologio'],
                ['chiave'=>'colore_progresso', 'label'=>'Colore progresso', 'tipo'=>'color', 'descrizione'=>'Colore dell anello o barra che avanza con i secondi (stili ring_pill, ring_seconds, neon_gym)'],
                ['chiave'=>'colore_barra_a', 'label'=>'Colore barra (inizio)', 'tipo'=>'color', 'descrizione'=>'Primo colore del gradiente della barra (stile bar_below)'],
                ['chiave'=>'colore_barra_b', 'label'=>'Colore barra (fine)', 'tipo'=>'color', 'descrizione'=>'Secondo colore del gradiente della barra (stile bar_below)'],
                ['chiave'=>'font_size_ora', 'label'=>'Font size ora', 'tipo'=>'number', 'descrizione'=>'Dimensione in px dell orario (default 44)'],
                ['chiave'=>'font_size_data', 'label'=>'Font size data', 'tipo'=>'number', 'descrizione'=>'Dimensione in px della data (default 20)'],
                ['chiave'=>'mostra_secondi', 'label'=>'Mostra secondi', 'tipo'=>'checkbox', 'descrizione'=>'Alcuni stili (ring_pill, flip, bar_below, ring_seconds) mostrano sempre i secondi per via del progresso; minimal_apple e neon_gym rispettano questa scelta'],
                ['chiave'=>'mostra_data', 'label'=>'Mostra data', 'tipo'=>'checkbox', 'descrizione'=>'On di default: nasconde la riga data se disattivato'],
            ],
            'varianti' => [
                [
                    'id' => 'standalone',
                    'label' => 'Libero sul canvas',
                    'contesto' => 'canvas',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Orologio posizionato liberamente, tipicamente in un angolo dello schermo sopra ad altri contenuti (es. TV o ADV).',
                ],
                [
                    'id' => 'sidebar_slide',
                    'label' => 'Slide orologio in sidebar',
                    'contesto' => 'sidebar',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Slide dedicata solo a ora e data, utile come slide di transizione leggera tra contenuti piu densi (corsi, meteo).',
                ],
            ],
            'template_grafici' => [
                [
                    'id' => 'ring_pill',
                    'nome' => 'Anello pillola',
                    'descrizione' => 'Ora, minuti e secondi al centro di una pillola il cui bordo si riempie come barra di progresso: un giro completo ogni 60 secondi.',
                    'config' => ['stile'=>'ring_pill','text_color'=>'#ffffff','colore_progresso'=>'#F7192E','font_size_ora'=>32,'mostra_secondi'=>true,'mostra_data'=>false],
                ],
                [
                    'id' => 'flip',
                    'nome' => 'Flip clock stazione',
                    'descrizione' => 'Cifre su caselle in stile tabellone ferroviario, con un piccolo scatto ad ogni cambio numero.',
                    'config' => ['stile'=>'flip','text_color'=>'#f3ede0','sfondo_ora'=>'#141414','sfondo_ora_opacity'=>1,'font_size_ora'=>40,'mostra_secondi'=>true,'mostra_data'=>false],
                ],
                [
                    'id' => 'bar_below',
                    'nome' => 'Barra sotto',
                    'descrizione' => 'Ora, minuti e secondi in alto, sotto una barra a gradiente che avanza con i secondi.',
                    'config' => ['stile'=>'bar_below','text_color'=>'#ffffff','colore_barra_a'=>'#22c55e','colore_barra_b'=>'#2dd4bf','font_size_ora'=>36,'mostra_secondi'=>true,'mostra_data'=>false],
                ],
                [
                    'id' => 'ring_seconds',
                    'nome' => 'Anello sui secondi',
                    'descrizione' => 'Ora e minuti normali, con i secondi racchiusi in un piccolo anello che si completa ogni 60 secondi.',
                    'config' => ['stile'=>'ring_seconds','text_color'=>'#ffffff','colore_progresso'=>'#2578D1','font_size_ora'=>34,'mostra_secondi'=>true,'mostra_data'=>false],
                ],
                [
                    'id' => 'minimal_apple',
                    'nome' => 'Minimal',
                    'descrizione' => 'Ora e minuti, font sottile, nessuno sfondo. Il piu discreto, in stile widget di sistema.',
                    'config' => ['stile'=>'minimal_apple','text_color'=>'#ffffff','font_size_ora'=>48,'font_size_data'=>16,'mostra_secondi'=>false,'mostra_data'=>true],
                ],
                [
                    'id' => 'neon_gym',
                    'nome' => 'Neon Gym',
                    'descrizione' => 'Cifre bold con bagliore al neon pulsante nel colore brand. Energico, adatto a sale corsi e aree cardio.',
                    'config' => ['stile'=>'neon_gym','colore_progresso'=>'#F7192E','sfondo_ora'=>'#0a0a0a','sfondo_ora_opacity'=>0.5,'font_size_ora'=>40,'mostra_secondi'=>true,'mostra_data'=>false],
                ],
            ],
        ],
        'data' => [
            'label' => 'Data',
            'color' => '#a855f7',
            'icon'  => 'fa-calendar-days',
            'tier'  => 'base',
            'contesto' => 'ibrido',
            'descrizione' => 'Data del giorno indipendente dall orologio, per quando serve mostrarla da sola invece che insieme all ora.',
            'default' => ['w'=>320,'h'=>100], 'min' => ['w'=>100,'h'=>50], 'max' => ['w'=>1920,'h'=>400],
            'funzionalita' => [
                'Data in italiano, aggiornata automaticamente ogni giorno',
                '2 stili: testo su riga singola, o card calendario con numero grande',
                'Formato esteso (Lunedi 15 Agosto) o breve (15/08/2026), a scelta',
                'Anno opzionale',
            ],
            'impostazioni' => [
                ['chiave'=>'stile', 'label'=>'Stile grafico', 'tipo'=>'select', 'descrizione'=>'minimale / calendario, vedi Template Grafici'],
                ['chiave'=>'formato', 'label'=>'Formato', 'tipo'=>'select', 'descrizione'=>'esteso (Lunedi 15 Agosto) / breve (15/08/2026) - solo stile minimale'],
                ['chiave'=>'mostra_anno', 'label'=>'Mostra anno', 'tipo'=>'checkbox', 'descrizione'=>'Aggiunge lanno alla data mostrata'],
                ['chiave'=>'text_color', 'label'=>'Colore testo', 'tipo'=>'color', 'descrizione'=>'Colore principale del testo'],
                ['chiave'=>'colore_numero', 'label'=>'Colore numero', 'tipo'=>'color', 'descrizione'=>'Solo stile calendario: colore del numero grande del giorno'],
                ['chiave'=>'font_size', 'label'=>'Dimensione testo', 'tipo'=>'number', 'descrizione'=>'Dimensione base, default secondo lo stile'],
            ],
            'template_grafici' => [
                [
                    'id' => 'minimale',
                    'nome' => 'Minimale',
                    'descrizione' => 'Data su riga singola, pulita. Buona accanto ad altri widget senza prendere spazio.',
                    'config' => ['stile'=>'minimale','formato'=>'esteso','text_color'=>'#ffffff'],
                ],
                [
                    'id' => 'calendario',
                    'nome' => 'Calendario',
                    'descrizione' => 'Numero del giorno grande, mese e giorno della settimana sotto — come una pagina di calendario da parete.',
                    'config' => ['stile'=>'calendario','text_color'=>'#ffffff','colore_numero'=>'#F7192E'],
                ],
            ],
            'varianti' => [
                [
                    'id' => 'standalone',
                    'label' => 'Libero sul canvas',
                    'contesto' => 'canvas',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Es. accanto al Logo, o vicino a Corsi Live quando lorologio non serve.',
                ],
                [
                    'id' => 'sidebar_slide',
                    'label' => 'Slide data in sidebar',
                    'contesto' => 'sidebar',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Slide dedicata che ruota tra le altre informazioni della sidebar.',
                ],
            ],
        ],
        'tv' => [
            'label' => 'Canale TV',
            'color' => '#1a1a2e',
            'icon'  => 'fa-tv',
            'tier'  => 'base',
            'contesto' => 'canvas',
            'descrizione' => 'Segnale digitale terrestre via tuner fisico. Richiede hardware Raspberry Pi o PC con tuner DVB.',
            'default' => ['w'=>1540,'h'=>1080], 'min' => ['w'=>320,'h'=>180], 'max' => ['w'=>1920,'h'=>1080],
            'aspect_consigliato' => '16:9',
        ],
        'adv' => [
            'label' => 'ADV Player',
            'color' => '#F7192E',
            'icon'  => 'fa-bullhorn',
            'tier'  => 'base',
            'contesto' => 'canvas',
            'descrizione' => 'Riproduce playlist pubblicitarie assegnate a inserzionisti, con regole di scheduling per fascia oraria.',
            'default' => ['w'=>1920,'h'=>1080], 'min' => ['w'=>320,'h'=>180], 'max' => ['w'=>1920,'h'=>1080],
        ],
        'immagine' => [
            'label' => 'Immagine',
            'color' => '#8b5cf6',
            'icon'  => 'fa-image',
            'tier'  => 'base',
            'contesto' => 'ibrido',
            'descrizione' => 'Mostra un\'immagine dalla libreria contenuti. Utilizzabile sia a schermo intero sia dentro la sidebar.',
            'default' => ['w'=>400,'h'=>225], 'min' => ['w'=>100,'h'=>60], 'max' => ['w'=>1920,'h'=>1080],
            'aspect_consigliato' => '16:9 o 9:16',
        ],
        'info' => [
            'label' => 'Info / Testo',
            'color' => '#10b981',
            'icon'  => 'fa-circle-info',
            'tier'  => 'base',
            'contesto' => 'ibrido',
            'descrizione' => 'Titolo e corpo del testo con icona opzionale — avvisi, orari, comunicazioni, annunci a tutto schermo.',
            'default' => ['w'=>400,'h'=>150], 'min' => ['w'=>150,'h'=>60], 'max' => ['w'=>1920,'h'=>600],
            'funzionalita' => [
                'Titolo e corpo separati, con pesi visivi diversi',
                'Icona opzionale (info/avviso/check/stella/megafono) per comunicare il tipo di messaggio a colpo docchio',
                'Sfondo a colore pieno oppure immagine, con oscuramento regolabile per leggibilita del testo',
                'Colori indipendenti per titolo, corpo e icona',
            ],
            'impostazioni' => [
                ['chiave'=>'titolo', 'label'=>'Titolo', 'tipo'=>'text', 'descrizione'=>'Testo principale, in evidenza'],
                ['chiave'=>'corpo', 'label'=>'Corpo', 'tipo'=>'textarea', 'descrizione'=>'Testo secondario sotto il titolo'],
                ['chiave'=>'icona', 'label'=>'Icona', 'tipo'=>'select', 'descrizione'=>'Libreria di 29 icone Font Awesome (compatibili anche su BrightSign)'],
                ['chiave'=>'icon_emoji', 'label'=>'Emoji personalizzata', 'tipo'=>'text', 'descrizione'=>'Alternativa allicona da libreria, ha priorita se impostata. Attenzione: le emoji potrebbero non renderizzare su BrightSign'],
                ['chiave'=>'colore_titolo', 'label'=>'Colore titolo', 'tipo'=>'color', 'descrizione'=>'Colore del testo principale'],
                ['chiave'=>'colore_corpo', 'label'=>'Colore corpo', 'tipo'=>'color', 'descrizione'=>'Colore del testo secondario'],
                ['chiave'=>'colore_icona', 'label'=>'Colore icona', 'tipo'=>'color', 'descrizione'=>'Colore dell icona da libreria (non si applica alle emoji)'],
                ['chiave'=>'font_size_titolo', 'label'=>'Dimensione titolo', 'tipo'=>'number', 'descrizione'=>'Se vuoto usa il default dello stile scelto'],
                ['chiave'=>'font_size_corpo', 'label'=>'Dimensione corpo', 'tipo'=>'number', 'descrizione'=>'Se vuoto usa il default dello stile scelto'],
                ['chiave'=>'font_size_icona', 'label'=>'Dimensione icona', 'tipo'=>'number', 'descrizione'=>'Se vuoto usa il default dello stile scelto'],
                ['chiave'=>'stile', 'label'=>'Stile grafico', 'tipo'=>'select', 'descrizione'=>'semplice / banner / poster, vedi Template Grafici'],
                ['chiave'=>'usa_immagine_sfondo', 'label'=>'Sfondo a immagine', 'tipo'=>'checkbox', 'descrizione'=>'Se attivo, usa unimmagine invece del colore di sfondo'],
                ['chiave'=>'bg_image', 'label'=>'Immagine di sfondo', 'tipo'=>'file', 'descrizione'=>'Scelta dalla libreria contenuti'],
                ['chiave'=>'bg_image_oscura', 'label'=>'Oscura sfondo', 'tipo'=>'range 0-1', 'descrizione'=>'Livello di oscuramento sopra limmagine, per leggibilita del testo'],
            ],
            'template_grafici' => [
                [
                    'id' => 'semplice',
                    'nome' => 'Semplice',
                    'descrizione' => 'Testo minimale, icona piccola opzionale. Per avvisi rapidi che non devono dominare lo schermo.',
                    'config' => ['stile'=>'semplice','colore_titolo'=>'#ffffff','colore_corpo'=>'rgba(255,255,255,.75)','colore_icona'=>'#F7192E'],
                ],
                [
                    'id' => 'banner',
                    'nome' => 'Banner',
                    'descrizione' => 'Icona grande a sinistra, bordo colorato, testo a destra. Stile notifica/avviso, buono per comunicazioni importanti.',
                    'config' => ['stile'=>'banner','colore_titolo'=>'#ffffff','colore_corpo'=>'rgba(255,255,255,.75)','colore_icona'=>'#F7192E','icona'=>'avviso'],
                ],
                [
                    'id' => 'poster',
                    'nome' => 'Poster',
                    'descrizione' => 'Titolo enorme centrato, il messaggio e il protagonista del template. Per annunci a piena pagina.',
                    'config' => ['stile'=>'poster','colore_titolo'=>'#ffffff','colore_corpo'=>'rgba(255,255,255,.8)','colore_icona'=>'#F7192E'],
                ],
            ],
            'varianti' => [
                [
                    'id' => 'standalone',
                    'label' => 'Libero sul canvas',
                    'contesto' => 'canvas',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Da un piccolo avviso in un angolo fino a un template intero dedicato a un annuncio (stile Poster).',
                ],
                [
                    'id' => 'sidebar_slide',
                    'label' => 'Slide info in sidebar',
                    'contesto' => 'sidebar',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Slide che ruota tra le altre informazioni della sidebar.',
                ],
            ],
        ],

        // ── POOL PREMIUM (Plus: scelta di 3 · Professional: tutti) ──
        'sidebar' => [
            'label' => 'Sidebar',
            'color' => '#7126D1',
            'icon'  => 'fa-table-columns',
            'tier'  => 'premium',
            'contesto' => 'canvas',
            'descrizione' => 'Contenitore multi-slide: cicla automaticamente più widget (meteo, corsi, countdown...) con durata personalizzata per ciascuno.',
            'default' => ['w'=>380,'h'=>1080], 'min' => ['w'=>200,'h'=>300], 'max' => ['w'=>800,'h'=>1080],
        ],
        'meteo' => [
            'label' => 'Meteo',
            'color' => '#3b82f6',
            'icon'  => 'fa-cloud-sun',
            'tier'  => 'premium',
            'contesto' => 'ibrido',
            'descrizione' => 'Meteo in tempo reale per la citta della sede, con icone colorate precise per condizione (sole/pioggia/neve/temporale, giorno/notte) e temperatura sempre aggiornata.',
            'default' => ['w'=>380,'h'=>200], 'min' => ['w'=>150,'h'=>100], 'max' => ['w'=>800,'h'=>500],
            'funzionalita' => [
                'Dati reali da Open-Meteo (gratis, nessuna API key richiesta)',
                'Icone SVG colorate dedicate per condizione: sereno, nuvoloso, pioggia, neve, temporale, nebbia',
                'Varianti giorno/notte automatiche (sole di giorno, luna di notte)',
                'Aggiornamento automatico ogni 10 minuti: la temperatura mostrata non resta mai vecchia',
                'Stile Dettagliato include anche minima e massima della giornata',
            ],
            'impostazioni' => [
                ['chiave'=>'citta', 'label'=>'Citta', 'tipo'=>'text', 'descrizione'=>'Nome mostrato sul widget (non usato per la ricerca meteo)'],
                ['chiave'=>'lat', 'label'=>'Latitudine', 'tipo'=>'number', 'descrizione'=>'Coordinata della sede, per il meteo esatto'],
                ['chiave'=>'lon', 'label'=>'Longitudine', 'tipo'=>'number', 'descrizione'=>'Coordinata della sede, per il meteo esatto'],
                ['chiave'=>'text_color', 'label'=>'Colore testo', 'tipo'=>'color', 'descrizione'=>'Colore di temperatura e testo'],
                ['chiave'=>'stile', 'label'=>'Stile grafico', 'tipo'=>'select', 'descrizione'=>'card / minimal / dettagliato, vedi Template Grafici'],
                ['chiave'=>'mostra_descrizione', 'label'=>'Mostra descrizione', 'tipo'=>'checkbox', 'descrizione'=>'Testo condizione (es. Sereno, Pioggia) sotto la temperatura'],
                ['chiave'=>'font_size_temp', 'label'=>'Font size temperatura', 'tipo'=>'number', 'descrizione'=>'Dimensione in px della temperatura (default 32)'],
            ],
            'varianti' => [
                [
                    'id' => 'standalone',
                    'label' => 'Libero sul canvas',
                    'contesto' => 'canvas',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Widget meteo posizionato liberamente, tipicamente in un angolo dello schermo.',
                ],
                [
                    'id' => 'sidebar_slide',
                    'label' => 'Slide meteo in sidebar',
                    'contesto' => 'sidebar',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Slide dedicata al meteo dentro la sidebar multi-widget.',
                ],
            ],
            'template_grafici' => [
                [
                    'id' => 'card',
                    'nome' => 'Card Moderna',
                    'descrizione' => 'Icona grande colorata, temperatura in evidenza, citta e descrizione condizione sotto. Stile app meteo moderna.',
                    'config' => ['stile'=>'card','text_color'=>'#ffffff','font_size_temp'=>32,'mostra_descrizione'=>true],
                ],
                [
                    'id' => 'minimal',
                    'nome' => 'Minimal',
                    'descrizione' => 'Solo icona e temperatura affiancate, ingombro minimo. Adatto ad angoli piccoli o sidebar strette.',
                    'config' => ['stile'=>'minimal','text_color'=>'#ffffff','font_size_temp'=>24,'mostra_descrizione'=>false],
                ],
                [
                    'id' => 'dettagliato',
                    'nome' => 'Dettagliato',
                    'descrizione' => 'Icona, temperatura, descrizione condizione e minima/massima della giornata. Il piu completo, stile app meteo professionale.',
                    'config' => ['stile'=>'dettagliato','text_color'=>'#ffffff','font_size_temp'=>36,'mostra_descrizione'=>true],
                ],
            ],
        ],
        'countdown' => [
            'label' => 'Countdown',
            'color' => '#f59e0b',
            'icon'  => 'fa-hourglass-half',
            'tier'  => 'premium',
            'contesto' => 'ibrido', // canvas libero (es. template dedicato a una promo) o slide in sidebar
            'descrizione' => 'Conto alla rovescia verso una data — eventi, aperture, scadenze promozioni.',
            'default' => ['w'=>380,'h'=>200], 'min' => ['w'=>150,'h'=>100], 'max' => ['w'=>800,'h'=>500],
            'funzionalita' => [
                'Conto alla rovescia aggiornato ogni secondo, calcolato lato client',
                'Giorni, ore, minuti (e opzionalmente secondi) verso una data e ora precise',
                'Titolo descrittivo opzionale sopra i numeri',
                'Colori indipendenti per titolo e numeri',
            ],
            'impostazioni' => [
                ['chiave'=>'titolo', 'label'=>'Titolo', 'tipo'=>'text', 'descrizione'=>'Testo sopra il countdown, es. Riapriamo tra'],
                ['chiave'=>'data_target', 'label'=>'Data target', 'tipo'=>'datetime-local', 'descrizione'=>'Data e ora verso cui conta alla rovescia'],
                ['chiave'=>'colore_titolo', 'label'=>'Colore titolo', 'tipo'=>'color', 'descrizione'=>'Colore del testo sopra i numeri'],
                ['chiave'=>'colore_numeri', 'label'=>'Colore numeri', 'tipo'=>'color', 'descrizione'=>'Colore dei numeri del countdown'],
                ['chiave'=>'mostra_secondi', 'label'=>'Mostra secondi', 'tipo'=>'checkbox', 'descrizione'=>'Off di default: mostra solo giorni/ore/minuti'],
                ['chiave'=>'stile', 'label'=>'Stile grafico', 'tipo'=>'select', 'descrizione'=>'classico / flip, vedi Template Grafici'],
                ['chiave'=>'on_expiry', 'label'=>'Alla scadenza', 'tipo'=>'select', 'descrizione'=>'zero (resta a 00 00 00) / testo (messaggio personalizzato) / nascondi (il widget sparisce)'],
                ['chiave'=>'testo_scadenza', 'label'=>'Testo alla scadenza', 'tipo'=>'text', 'descrizione'=>'Mostrato al posto dei numeri se "Alla scadenza" e impostato su testo'],
            ],
            'template_grafici' => [
                [
                    'id' => 'classico',
                    'nome' => 'Classico',
                    'descrizione' => 'Numeri puliti con etichetta sotto (giorni/ore/min/sec). Stile digitale essenziale.',
                    'config' => ['stile'=>'classico','colore_numeri'=>'#ffffff','colore_titolo'=>'rgba(255,255,255,.7)'],
                ],
                [
                    'id' => 'flip',
                    'nome' => 'Flip Card',
                    'descrizione' => 'Ogni cifra su una tessera che scatta al cambio, stesso linguaggio visivo di Orologio e Tabellone Corsi. Piu impatto per promozioni ed eventi.',
                    'config' => ['stile'=>'flip','colore_numeri'=>'#ffffff','colore_titolo'=>'rgba(255,255,255,.7)'],
                ],
            ],
            'varianti' => [
                [
                    'id' => 'standalone',
                    'label' => 'Elemento principale su un template dedicato',
                    'contesto' => 'canvas',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Per campagne a tempo (promo, apertura nuova sede): un template snello con countdown grande come protagonista, senza TV/ADV.',
                ],
                [
                    'id' => 'sidebar_slide',
                    'label' => 'Slide countdown in sidebar',
                    'contesto' => 'sidebar',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Slide che ruota tra le altre informazioni della sidebar (corsi, meteo).',
                ],
            ],
        ],
        'qrcode' => [
            'label' => 'QR Code',
            'color' => '#64748b',
            'icon'  => 'fa-qrcode',
            'tier'  => 'premium',
            'contesto' => 'ibrido',
            'descrizione' => 'QR code verso un URL (iscrizioni, social, form, menu) con titolo opzionale sotto, generato in tempo reale.',
            'default' => ['w'=>200,'h'=>200], 'min' => ['w'=>100,'h'=>100], 'max' => ['w'=>600,'h'=>600],
            'funzionalita' => [
                'Generato in tempo reale da un URL, nessun immagine da caricare a mano',
                'Colori QR e sfondo personalizzabili, mantenendo un contrasto leggibile',
                'Titolo opzionale sotto il codice (es. Iscriviti ora)',
                'Anteprima live nelleditor: vedi il vero QR mentre lo costruisci, non un segnaposto',
            ],
            'impostazioni' => [
                ['chiave'=>'url', 'label'=>'URL', 'tipo'=>'url', 'descrizione'=>'Indirizzo a cui punta il QR code una volta scansionato'],
                ['chiave'=>'titolo', 'label'=>'Titolo', 'tipo'=>'text', 'descrizione'=>'Testo opzionale sotto il codice'],
                ['chiave'=>'colore_qr', 'label'=>'Colore QR', 'tipo'=>'color', 'descrizione'=>'Colore dei moduli del codice'],
                ['chiave'=>'sfondo_qr', 'label'=>'Colore sfondo QR', 'tipo'=>'color', 'descrizione'=>'Colore di sfondo dietro al codice'],
                ['chiave'=>'colore_titolo', 'label'=>'Colore titolo', 'tipo'=>'color', 'descrizione'=>'Colore del testo sotto il QR'],
                ['chiave'=>'dimensione_qr', 'label'=>'Dimensione QR', 'tipo'=>'range 30-100', 'descrizione'=>'Percentuale del lato piu corto del box occupata dal codice, default 70%'],
            ],
            'varianti' => [
                [
                    'id' => 'standalone',
                    'label' => 'Libero sul canvas',
                    'contesto' => 'canvas',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Angolo dello schermo vicino alla reception, o accanto a una promozione per link diretto alliscrizione.',
                ],
                [
                    'id' => 'sidebar_slide',
                    'label' => 'Slide QR in sidebar',
                    'contesto' => 'sidebar',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Slide dedicata che ruota tra le altre informazioni della sidebar.',
                ],
            ],
        ],
        'ticker' => [
            'label' => 'Ticker',
            'color' => '#0ea5e9',
            'icon'  => 'fa-scroll',
            'tier'  => 'premium',
            'contesto' => 'canvas', // barra orizzontale a tutta larghezza, non adatta alla sidebar stretta
            'descrizione' => 'Striscia di testo scorrevole in loop, sempre in movimento — annunci, promozioni, avvisi rapidi.',
            'default' => ['w'=>1920,'h'=>60], 'min' => ['w'=>400,'h'=>30], 'max' => ['w'=>1920,'h'=>150],
            'funzionalita' => [
                'Scorrimento continuo via animazione CSS pura, nessun calcolo JS pesante',
                'Piu righe di testo concatenate con separatore a stella',
                'Velocita di scorrimento regolabile in px/sec',
                'Colore testo e sfondo indipendenti, entrambi personalizzabili',
            ],
            'impostazioni' => [
                ['chiave'=>'testi', 'label'=>'Testi', 'tipo'=>'textarea (uno per riga)', 'descrizione'=>'Ogni riga diventa un messaggio, concatenati nello scorrimento con un separatore a stella'],
                ['chiave'=>'text_color', 'label'=>'Colore testo', 'tipo'=>'color', 'descrizione'=>'Colore del testo scorrevole'],
                ['chiave'=>'bg_ticker', 'label'=>'Colore sfondo', 'tipo'=>'color', 'descrizione'=>'Colore di sfondo della barra'],
                ['chiave'=>'velocita', 'label'=>'Velocita', 'tipo'=>'number (px/sec)', 'descrizione'=>'Velocita di scorrimento, default 60'],
                ['chiave'=>'font_size', 'label'=>'Font size', 'tipo'=>'number', 'descrizione'=>'Dimensione in px del testo, default 20'],
            ],
            'varianti' => [
                [
                    'id' => 'standalone',
                    'label' => 'Barra a tutta larghezza',
                    'contesto' => 'canvas',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Tipicamente ancorata in alto o in basso allo schermo, larga quanto il canvas. Non adatta alla sidebar per via della larghezza necessaria a un buon scorrimento.',
                ],
            ],
        ],
        'streaming' => [
            'label' => 'Streaming/IPTV',
            'color' => '#0f4c81',
            'icon'  => 'fa-satellite-dish',
            'tier'  => 'premium',
            'contesto' => 'canvas',
            'descrizione' => 'Streaming via URL HLS/M3U8/RTSP — sostituisce il tuner fisico, funziona su tutti i dispositivi (Pi, Android, BrightSign).',
            'default' => ['w'=>1540,'h'=>1080], 'min' => ['w'=>320,'h'=>180], 'max' => ['w'=>1920,'h'=>1080],
            'aspect_consigliato' => '16:9',
        ],
        'corsi' => [
            'label' => 'Corsi Live',
            'color' => '#e94560',
            'icon'  => 'fa-dumbbell',
            'tier'  => 'premium',
            'contesto' => 'ibrido', // canvas libero, sidebar, o dentro ADV
            'descrizione' => 'Palinsesto corsi in tempo reale da Google Sheets, con badge LIVE e colonne personalizzabili. Il widget-firma di PixelBridge, pensato specificamente per palestre: nessun concorrente italiano ha un equivalente diretto.',
            'default' => ['w'=>380,'h'=>500], 'min' => ['w'=>250,'h'=>250], 'max' => ['w'=>1920,'h'=>1080],
            'funzionalita' => [
                'Legge in tempo reale un Google Sheet pubblico (via proxy CSV, nessuna API key richiesta)',
                'Badge LIVE automatico sui corsi in corso, basato sull orario reale',
                'Colonne mostrate configurabili: ora, corso, istruttore, sala',
                'Ricarica il palinsesto ogni 5 minuti senza bisogno di riavviare il player',
                'Limite massimo corsi visibili configurabile, utile per sidebar strette',
            ],
            'impostazioni' => [
                ['chiave'=>'sheet_url', 'label'=>'Google Sheet URL', 'tipo'=>'url', 'descrizione'=>'Link al foglio Google pubblicato come CSV, con colonne Giorno/Orario/Corso/Club/Durata/Studio'],
                ['chiave'=>'max_corsi', 'label'=>'Max corsi', 'tipo'=>'number', 'descrizione'=>'Numero massimo di righe mostrate (default 4)'],
                ['chiave'=>'titolo', 'label'=>'Titolo widget', 'tipo'=>'text', 'descrizione'=>'Es. In programma oggi'],
                ['chiave'=>'mostra_badge', 'label'=>'Mostra badge LIVE', 'tipo'=>'checkbox', 'descrizione'=>'On di default: nasconde il badge se disattivato'],
                ['chiave'=>'badge_live', 'label'=>'Testo badge LIVE', 'tipo'=>'text', 'descrizione'=>'Etichetta mostrata sul corso in corso'],
                ['chiave'=>'colore_titolo', 'label'=>'Colore titolo', 'tipo'=>'color', 'descrizione'=>'Colore del titolo del widget'],
                ['chiave'=>'colore_orario', 'label'=>'Colore orario', 'tipo'=>'color', 'descrizione'=>'Colore della colonna ora'],
                ['chiave'=>'colore_testo_live', 'label'=>'Colore testo corso LIVE', 'tipo'=>'color', 'descrizione'=>'Solo stile Tabellone stazione: colore del testo sulle tessere del corso in corso (sfondo pieno colore badge)'],
                ['chiave'=>'colore_corso', 'label'=>'Colore corso', 'tipo'=>'color', 'descrizione'=>'Colore del nome corso in ogni riga'],
                ['chiave'=>'colore_badge', 'label'=>'Colore badge', 'tipo'=>'color', 'descrizione'=>'Colore di sfondo del badge LIVE'],
                ['chiave'=>'font_size_corso', 'label'=>'Font size corso', 'tipo'=>'number', 'descrizione'=>'Dimensione in px del nome corso (default 18)'],
                ['chiave'=>'colonne', 'label'=>'Colonne visibili', 'tipo'=>'checkbox multipli', 'descrizione'=>'ora / corso / istruttore / sala, mostrate/nascoste indipendentemente'],
                ['chiave'=>'stile', 'label'=>'Stile grafico', 'tipo'=>'select', 'descrizione'=>'lista / lobby / stazione, vedi Template Grafici'],
            ],
            'template_grafici' => [
                [
                    'id' => 'lista',
                    'nome' => 'Lista Classica',
                    'descrizione' => 'Lista verticale con corso LIVE evidenziato da bordo rosso e sfondo scurito, corsi passati sfumati. Lo stile attualmente in produzione su PixelBridge.',
                    'config' => ['stile'=>'lista','colore_titolo'=>'#ffffff','colore_corso'=>'#ffffff','colore_orario'=>'#ffffff','colore_badge'=>'#F7192E','font_size_corso'=>18],
                ],
                [
                    'id' => 'lobby',
                    'nome' => 'Lobby Android',
                    'descrizione' => 'Fullscreen dark-tech con intestazione club+data, riga LIVE con pallino pulsante, ticker scorrevole in basso. Lo stile usato nell app Android in kiosk.',
                    'config' => ['stile'=>'lobby','colore_titolo'=>'#ffffff','colore_corso'=>'#ffffff','colore_orario'=>'#ffffff','colore_badge'=>'#F7192E','font_size_corso'=>22],
                ],
                [
                    'id' => 'stazione',
                    'nome' => 'Tabellone stazione',
                    'descrizione' => 'Righe monospace in stile tabellone partenze treni/aeroporto, con il corso LIVE evidenziato da una barra laterale.',
                    'config' => ['stile'=>'stazione','colore_titolo'=>'#ffffff','colore_corso'=>'#ffffff','colore_orario'=>'#ffffff','colore_badge'=>'#F7192E','font_size_corso'=>18],
                ],
            ],
            'varianti' => [
                [
                    'id' => 'standalone',
                    'label' => 'Libero sul canvas',
                    'contesto' => 'canvas',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Palinsesto a schermo pieno o in un angolo dello schermo, tipicamente vicino alla reception o all ingresso della sala corsi.',
                ],
                [
                    'id' => 'sidebar_slide',
                    'label' => 'Slide corsi in sidebar',
                    'contesto' => 'sidebar',
                    'formato' => 'landscape+portrait',
                    'descrizione' => 'Slide dedicata al palinsesto dentro la sidebar multi-widget, con max_corsi ridotto per stare nello spazio stretto.',
                ],
            ],
        ],
    ];
}

/**
 * Restituisce piano + widget premium selezionati per un tenant.
 * Letti dalla tabella impostazioni (chiavi: piano, widget_premium).
 */
function getTenantPlan(PDO $db, int $tenantId): array {
    // Il piano vero vive in tenants.piano (trial/starter/pro/enterprise) — la
    // vecchia versione leggeva un 'piano' salvato per conto suo dentro
    // impostazioni, completamente scollegato dalla tabella tenants. Risultato:
    // cambiare il piano di un cliente in un posto non aveva nessun effetto
    // sull'altro. Ora tenants e' l'unica fonte di verita', mappata al
    // vocabolario usato per sbloccare i widget (base/plus/professional).
    $stmt = $db->prepare("SELECT piano FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
    $piano_tenant = $stmt->fetchColumn() ?: 'trial';

    $mappa_piano = [
        'trial'      => 'base',
        'starter'    => 'base',
        'pro'        => 'plus',
        'enterprise' => 'professional',
    ];
    $piano = $mappa_piano[$piano_tenant] ?? 'base';

    $stmt = $db->prepare("SELECT valore FROM impostazioni WHERE tenant_id=? AND chiave='widget_premium'");
    $stmt->execute([$tenantId]);
    $premium = json_decode($stmt->fetchColumn() ?: '[]', true) ?: [];

    return ['piano' => $piano, 'premium' => $premium];
}

/**
 * Determina se un widget_type è sbloccato per il piano/selezione del tenant.
 */
function isWidgetUnlocked(string $widgetType, array $catalog, array $tenantPlan): bool {
    // Il Super Admin (Tobia) non e' mai limitato dal piano del tenant: deve poter
    // costruire e testare qualsiasi template a prescindere da cosa ha comprato il cliente.
    if (function_exists('isSuperAdmin') && isSuperAdmin()) return true;

    $wt = $catalog[$widgetType] ?? null;
    if (!$wt) return false;
    if ($wt['tier'] === 'base') return true;
    if ($tenantPlan['piano'] === 'professional') return true;
    if ($tenantPlan['piano'] === 'plus') return in_array($widgetType, $tenantPlan['premium']);
    return false; // piano 'base' non ha accesso al pool premium
}
