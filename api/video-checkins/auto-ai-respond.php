<?php
/**
 * GET /api/video-checkins/auto-ai-respond
 * Cron job / webhook: procesa video check-ins sin respuesta de coach en 24h
 * usando Claude Haiku para generar retroalimentación de forma.
 *
 * CRON: 0 * * * * (cada hora)
 * ACCESO: secret en header X-Cron-Secret
 *
 * Usa file_get_contents + stream_context_create (no curl) para llamar a Claude API.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/response.php';

// Validar secret de cron
$secret = $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
$expected = env('CRON_SECRET', '');
if (!$expected || $secret !== $expected) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$db = getDB();

// Buscar check-ins pendientes de más de 24h sin respuesta de coach
$pending = $db->query("
    SELECT id, client_id, exercise_name, notes, media_type, media_url
    FROM video_checkins
    WHERE status = 'pending'
      AND ai_used = 0
      AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($pending)) {
    echo json_encode(['processed' => 0, 'message' => 'No hay check-ins pendientes']);
    exit;
}

$api_key = env('ANTHROPIC_API_KEY', '');
if (!$api_key) {
    echo json_encode(['error' => 'ANTHROPIC_API_KEY no configurada']);
    exit;
}

$processed = 0;
$errors    = [];

foreach ($pending as $vc) {
    $prompt = "Eres un coach de fitness experto. Un cliente envió un " .
        ($vc['media_type'] === 'video' ? 'video' : 'imagen') .
        " de su ejercicio: **{$vc['exercise_name']}**." .
        ($vc['notes'] ? " Notas del cliente: {$vc['notes']}." : '') .
        " Proporciona retroalimentación breve (máximo 3 párrafos) sobre:\n" .
        "1. Puntos positivos de técnica que probablemente muestra el clip.\n" .
        "2. Sugerencias de mejora comunes para este ejercicio.\n" .
        "3. Motivación y próximos pasos.\n" .
        "Sé directo, positivo y específico. Responde en español.";

    $body = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 500,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);

    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => implode("\r\n", [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
        ]),
        'content'       => $body,
        'timeout'       => 30,
        'ignore_errors' => true,
    ]]);

    $result = @file_get_contents('https://api.anthropic.com/v1/messages', false, $ctx);

    if ($result === false) {
        $errors[] = "vc#{$vc['id']}: connection failed";
        continue;
    }

    $data = json_decode($result, true);
    $ai_text = $data['content'][0]['text'] ?? null;

    if (!$ai_text) {
        $errors[] = "vc#{$vc['id']}: empty response";
        continue;
    }

    $db->prepare("
        UPDATE video_checkins
        SET ai_response = ?, ai_used = 1, status = 'ai_reviewed', responded_at = NOW()
        WHERE id = ?
    ")->execute([$ai_text, $vc['id']]);

    $processed++;
}

echo json_encode([
    'processed' => $processed,
    'errors'    => $errors,
    'total'     => count($pending),
]);
