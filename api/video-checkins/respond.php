<?php
/**
 * POST /api/video-checkins/respond
 * Coach responde a un video check-in manualmente o via IA.
 *
 * Auth: coach
 * Body: { checkin_id, response } — manual
 *       { checkin_id, use_ai: true } — IA (Claude Haiku)
 * Responde: { success, checkin_id, ai_response? }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/cors.php';

requireMethod('POST');

$coach    = authenticateCoach();
$db       = getDB();
$coach_id = $coach['id'];

$body          = json_decode(file_get_contents('php://input'), true) ?? [];
$checkin_id    = (int)($body['checkin_id'] ?? 0);
$response_text = trim($body['response'] ?? '');
$use_ai        = !empty($body['use_ai']);

if (!$checkin_id) {
    respondError('checkin_id es requerido', 400);
}
if (!$use_ai && $response_text === '') {
    respondError('response es requerido para respuesta manual', 400);
}

$row = $db->prepare("SELECT id, client_id, exercise_name, notes, media_type, status FROM video_checkins WHERE id = ? AND coach_id = ?");
$row->execute([$checkin_id, $coach_id]);
$vc = $row->fetch(PDO::FETCH_ASSOC);

if (!$vc) {
    respondError('Check-in no encontrado', 404);
}

if ($use_ai) {
    $api_key = env('ANTHROPIC_API_KEY', '');
    if (!$api_key) {
        respondError('IA no configurada en este servidor', 503);
    }

    $prompt = "Eres un coach de fitness experto. Un cliente envió un " .
        ($vc['media_type'] === 'video' ? 'video' : 'imagen') .
        " de su ejercicio: **{$vc['exercise_name']}**." .
        ($vc['notes'] ? " Notas del cliente: {$vc['notes']}." : '') .
        " Proporciona retroalimentación breve (máximo 3 párrafos) sobre:\n" .
        "1. Puntos positivos de técnica.\n" .
        "2. Sugerencias de mejora.\n" .
        "3. Motivación y próximos pasos.\n" .
        "Sé directo, positivo y específico. Responde en español.";

    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => implode("\r\n", [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
        ]),
        'content'       => json_encode([
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 500,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]),
        'timeout'       => 30,
        'ignore_errors' => true,
    ]]);

    $result = @file_get_contents('https://api.anthropic.com/v1/messages', false, $ctx);
    $data   = $result ? json_decode($result, true) : null;
    $ai_text = $data['content'][0]['text'] ?? null;

    if (!$ai_text) {
        respondError('La IA no pudo generar una respuesta. Intenta manualmente.', 503);
    }

    $db->prepare("
        UPDATE video_checkins
        SET ai_response = ?, ai_used = 1, status = 'ai_reviewed', responded_at = NOW()
        WHERE id = ?
    ")->execute([$ai_text, $checkin_id]);

    respond(['success' => true, 'checkin_id' => $checkin_id, 'ai_response' => $ai_text]);

} else {
    $db->prepare("
        UPDATE video_checkins
        SET coach_response = ?, status = 'coach_reviewed', responded_at = NOW()
        WHERE id = ?
    ")->execute([$response_text, $checkin_id]);

    respond(['success' => true, 'checkin_id' => $checkin_id]);
}
