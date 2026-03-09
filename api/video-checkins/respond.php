<?php
/**
 * POST /api/video-checkins/respond
 * Coach responde a un video check-in manualmente.
 * Si el coach no responde en 24h, se activa la IA (ver auto-ai-respond.php).
 *
 * Auth: coach
 * Body: { checkin_id, response }
 * Responde: { success, checkin_id }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();

$coach    = authenticateCoach();
$db       = getDB();
$coach_id = $coach['id'];

$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$checkin_id  = (int)($body['checkin_id'] ?? 0);
$response_text = trim($body['response'] ?? '');

if (!$checkin_id || $response_text === '') {
    respondError('checkin_id y response son requeridos', 400);
}

$row = $db->prepare("SELECT id, client_id, status FROM video_checkins WHERE id = ? AND coach_id = ?");
$row->execute([$checkin_id, $coach_id]);
$vc = $row->fetch(PDO::FETCH_ASSOC);

if (!$vc) {
    respondError('Check-in no encontrado', 404);
}

$db->prepare("
    UPDATE video_checkins
    SET coach_response = ?, status = 'coach_reviewed', responded_at = NOW()
    WHERE id = ?
")->execute([$response_text, $checkin_id]);

respond(['success' => true, 'checkin_id' => $checkin_id]);
