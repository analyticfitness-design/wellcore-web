<?php
// GET /api/pwa/manifest?coach={subdomain|coach_id}
// Returns dynamic manifest.json for coach's PWA

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';

$db   = getDB();
$slug = trim($_GET['coach'] ?? '');

if (!$slug) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'coach param requerido']);
    exit;
}

// Look up by subdomain or numeric coach_id
if (is_numeric($slug)) {
    $stmt = $db->prepare("SELECT * FROM coach_pwa_config WHERE coach_id = ?");
    $stmt->execute([(int)$slug]);
} else {
    $stmt = $db->prepare("SELECT * FROM coach_pwa_config WHERE subdomain = ?");
    $stmt->execute([$slug]);
}
$cfg = $stmt->fetch();

$appName  = $cfg ? $cfg['app_name']  : 'WellCore Fitness';
$color    = $cfg ? $cfg['color']     : '#E31E24';
$iconUrl  = $cfg && $cfg['icon_url'] ? $cfg['icon_url'] : '/images/icon-192.png';

$manifest = [
    'name'             => $appName,
    'short_name'       => substr($appName, 0, 12),
    'start_url'        => '/cliente.html',
    'display'          => 'standalone',
    'background_color' => '#0a0a0a',
    'theme_color'      => $color,
    'icons'            => [
        ['src' => $iconUrl, 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => $iconUrl, 'sizes' => '512x512', 'type' => 'image/png'],
    ],
];

header('Content-Type: application/manifest+json');
header('Cache-Control: public, max-age=3600');
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
