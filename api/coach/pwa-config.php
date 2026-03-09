<?php
// GET  /api/coach/pwa-config  — config PWA del coach
// POST /api/coach/pwa-config  — guardar config {app_name, color, subdomain, icon_url}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

$coach = authenticateCoach();
$db    = getDB();
$cid   = $coach['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("SELECT * FROM coach_pwa_config WHERE coach_id = ?");
    $stmt->execute([$cid]);
    $cfg = $stmt->fetch() ?: [
        'app_name'  => 'Mi App Fitness',
        'color'     => '#E31E24',
        'icon_url'  => null,
        'subdomain' => null,
    ];
    respond(['config' => $cfg]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $appName   = substr(trim($body['app_name'] ?? 'Mi App Fitness'), 0, 60);
    $color     = preg_match('/^#[0-9a-fA-F]{6}$/', $body['color'] ?? '') ? $body['color'] : '#E31E24';
    $iconUrl   = substr(trim($body['icon_url'] ?? ''), 0, 255) ?: null;
    $subdomain = preg_match('/^[a-z0-9-]{3,40}$/', $body['subdomain'] ?? '') ? $body['subdomain'] : null;

    $db->prepare("
        INSERT INTO coach_pwa_config (coach_id, app_name, color, icon_url, subdomain)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          app_name  = VALUES(app_name),
          color     = VALUES(color),
          icon_url  = VALUES(icon_url),
          subdomain = VALUES(subdomain)
    ")->execute([$cid, $appName, $color, $iconUrl, $subdomain]);

    respond(['ok' => true]);
}

respondError('Método no permitido', 405);
