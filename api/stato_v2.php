<?php
/**
 * api/stato_v2.php
 * Restituisce, per un dispositivo (token), il template assegnato con tutti i suoi
 * layer (posizione, dimensione, widget_type, config) in JSON.
 *
 * Sostituisce la vecchia struttura fissa "profilo" con il sistema libero
 * a layer costruito nell'editor Template Layout.
 *
 * Uso: GET /api/stato_v2.php?token=IL_TOKEN_DISPOSITIVO
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Token mancante']);
    exit;
}

$db = getDB();

// ── Trova il dispositivo dal token ────────────────────────────
$stmt = $db->prepare("SELECT * FROM dispositivi WHERE token = ?");
$stmt->execute([$token]);
$dispositivo = $stmt->fetch();

if (!$dispositivo) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'Dispositivo non trovato']);
    exit;
}

$tenantId = (int)$dispositivo['tenant_id'];

// Aggiorna ultimo ping (heartbeat)
$db->prepare("UPDATE dispositivi SET ultimo_ping = NOW() WHERE id = ?")->execute([$dispositivo['id']]);

// ── Trova il template assegnato ───────────────────────────────
if (empty($dispositivo['template_id'])) {
    echo json_encode([
        'ok' => true,
        'dispositivo' => [
            'nome' => $dispositivo['nome'],
            'club' => $dispositivo['club'],
            'hw_type' => $dispositivo['hw_type'],
        ],
        'template' => null,
        'layers' => [],
        'messaggio' => 'Nessun template assegnato a questo dispositivo.',
    ]);
    exit;
}

$stmt = $db->prepare("SELECT * FROM layout_templates WHERE id = ? AND tenant_id = ?");
$stmt->execute([$dispositivo['template_id'], $tenantId]);
$template = $stmt->fetch();

if (!$template) {
    echo json_encode([
        'ok' => true,
        'dispositivo' => ['nome'=>$dispositivo['nome'],'club'=>$dispositivo['club'],'hw_type'=>$dispositivo['hw_type']],
        'template' => null,
        'layers' => [],
        'messaggio' => 'Template assegnato non trovato (potrebbe essere stato eliminato).',
    ]);
    exit;
}

// ── Layer del template ─────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM layout_layers WHERE template_id = ? ORDER BY z_index ASC");
$stmt->execute([$template['id']]);
$layersRaw = $stmt->fetchAll();

$layers = array_map(function($l) {
    return [
        'id'          => (int)$l['id'],
        'nome'        => $l['nome'],
        'widget_type' => $l['widget_type'],
        'pos_x'       => (int)$l['pos_x'],
        'pos_y'       => (int)$l['pos_y'],
        'width'       => (int)$l['width'],
        'height'      => (int)$l['height'],
        'z_index'     => (int)$l['z_index'],
        'visible'     => (bool)$l['visible'],
        'adv_safe'    => (bool)($l['adv_safe'] ?? false),
        'config'      => json_decode($l['config'] ?? '{}', true) ?: [],
    ];
}, $layersRaw);

// ── Brand Kit del tenant ────────────────────────────────────────
$stmt = $db->prepare("SELECT chiave, valore FROM impostazioni WHERE tenant_id = ? AND chiave LIKE 'brand_%'");
$stmt->execute([$tenantId]);
$brand = [];
foreach ($stmt->fetchAll() as $row) $brand[$row['chiave']] = $row['valore'];

// ── Dati della sede (Club) a cui appartiene questo dispositivo — usati dai
// widget Meteo e Corsi Live come fallback quando non configurati a mano nel
// singolo widget, cosi' lo stesso template duplicato su piu' sedi mostra
// automaticamente i dati giusti per ciascuna.
$stmt = $db->prepare("SELECT lat, lon, sheet_url_corsi FROM club_config WHERE tenant_id = ? AND nome = ?");
$stmt->execute([$tenantId, $dispositivo['club']]);
$club_data = $stmt->fetch();

// ── Risposta ─────────────────────────────────────────────────
echo json_encode([
    'ok' => true,
    'dispositivo' => [
        'nome' => $dispositivo['nome'],
        'club' => $dispositivo['club'],
        'hw_type' => $dispositivo['hw_type'],
        'capture_device' => $dispositivo['capture_device'] ?? '',
    ],
    'club_data' => [
        'lat' => $club_data['lat'] ?? null,
        'lon' => $club_data['lon'] ?? null,
        'sheet_url_corsi' => $club_data['sheet_url_corsi'] ?? '',
    ],
    'template' => [
        'id' => (int)$template['id'],
        'nome' => $template['nome'],
        'canvas_w' => (int)$template['canvas_w'],
        'canvas_h' => (int)$template['canvas_h'],
        'orientamento' => $template['orientamento'],
    ],
    'layers' => $layers,
    'brand' => [
        'logo' => $brand['brand_logo'] ?? '',
        'primario' => $brand['brand_colore_primario'] ?? '#F7192E',
        'secondario' => $brand['brand_colore_secondario'] ?? '#111111',
        'accento' => $brand['brand_colore_accento'] ?? '#ffffff',
        'font' => $brand['brand_font'] ?? 'Inter',
    ],
], JSON_UNESCAPED_UNICODE);
