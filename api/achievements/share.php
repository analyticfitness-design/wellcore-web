<?php
/**
 * POST /api/achievements/share    — Crea token de logro compartible
 * GET  /api/achievements/share?token= — Ve logro por token (público)
 *
 * Body POST: { achievement_type, achievement_data{} }
 * Auth POST: cliente
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/cors.php';

requireMethod('GET', 'POST');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $client    = authenticateClient();
    $client_id = $client['id'];
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];

    $type = trim($body['achievement_type'] ?? '');
    $data = $body['achievement_data'] ?? [];

    $allowed_types = ['streak_7', 'streak_30', 'level_up', 'checkin_week1', 'checkin_month1', 'challenge_won', 'xp_milestone'];
    if (!$type || !in_array($type, $allowed_types, true)) {
        respondError('achievement_type inválido', 400);
    }

    // Enriquecer data con info del cliente
    $cr = $db->prepare("SELECT name, plan FROM clients WHERE id = ?");
    $cr->execute([$client_id]);
    $c = $cr->fetch(PDO::FETCH_ASSOC);

    $data['client_name'] = $c['name'] ?? 'Cliente WellCore';
    $data['plan']        = $c['plan'] ?? '';
    $data['date']        = date('Y-m-d');

    $token = bin2hex(random_bytes(16));

    $db->prepare("
        INSERT INTO shared_achievements (client_id, achievement_type, achievement_data, share_token)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            achievement_data = VALUES(achievement_data),
            share_token = VALUES(share_token)
    ")->execute([$client_id, $type, json_encode($data), $token]);

    $share_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/logro.html?t=' . $token;

    respond([
        'token'     => $token,
        'share_url' => $share_url,
        'type'      => $type,
    ]);

} elseif ($method === 'GET') {
    $token = trim($_GET['token'] ?? '');
    if (!$token) respondError('token requerido', 400);

    $row = $db->prepare("SELECT achievement_type, achievement_data, views, created_at FROM shared_achievements WHERE share_token = ?");
    $row->execute([$token]);
    $a = $row->fetch(PDO::FETCH_ASSOC);

    if (!$a) respondError('Logro no encontrado', 404);

    // Incrementar views
    $db->prepare("UPDATE shared_achievements SET views = views + 1 WHERE share_token = ?")->execute([$token]);

    respond([
        'type'             => $a['achievement_type'],
        'data'             => json_decode($a['achievement_data'], true),
        'views'            => (int)$a['views'] + 1,
        'created_at'       => $a['created_at'],
    ]);

} else {
    respondError('Método no permitido', 405);
}
