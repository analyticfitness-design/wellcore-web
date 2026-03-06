<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
/**
 * WellCore Fitness — AI Exercise Tips (Haiku)
 * ============================================================
 * POST /api/ai/exercise-tip.php
 *
 * Genera un tip personalizado para un ejercicio usando Claude Haiku.
 * Rapido y economico — pensado para uso en tiempo real.
 *
 * Body: { exercise: string, context?: string }
 * Auth: Bearer token de cliente
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rate-limit.php';

requireMethod('POST');
$client = authenticateClient();

// Rate limit: 20 tips por hora por cliente
if (!rate_limit_check('ai_tip_' . $client['id'], 20, 3600)) {
    respondError('Limite de tips alcanzado. Intenta en unos minutos.', 429);
}

$body     = getJsonBody();
$exercise = trim($body['exercise'] ?? '');
$context  = trim($body['context']  ?? '');

if (empty($exercise) || strlen($exercise) < 3) {
    respondError('Nombre del ejercicio requerido', 400);
}

// Obtener perfil del cliente para personalizar
$profile = '';
try {
    $cData = get_client_for_ai((int)$client['id']);
    $profile = "Cliente: " . ($cData['name'] ?: 'Usuario') . "\n";
    $profile .= "Nivel: " . ($cData['nivel'] ?: 'intermedio') . "\n";
    $profile .= "Objetivo: " . ($cData['objetivo'] ?: 'mejorar composicion corporal') . "\n";
    if ($cData['restricciones']) $profile .= "Restricciones: " . $cData['restricciones'] . "\n";
} catch (\Throwable $e) {
    $profile = "Nivel: intermedio\n";
}

$systemPrompt = <<<SYSTEM
Eres el coach virtual de WellCore Fitness. Responde en espanol.
Genera UN tip breve y accionable para el ejercicio indicado.
Maximo 2-3 oraciones. Se directo y tecnico pero amigable.
Incluye un cue de forma o un consejo de ejecucion practico.
Si el cliente tiene restricciones, adapta el consejo.
NO uses emojis. NO repitas el nombre del ejercicio.
SYSTEM;

$userPrompt = "Ejercicio: $exercise\n";
if ($context) $userPrompt .= "Contexto: $context\n";
$userPrompt .= $profile;
$userPrompt .= "\nDa un tip de ejecucion breve y personalizado.";

try {
    $result = claude_call(
        $systemPrompt,
        $userPrompt,
        'claude-haiku-4-5-20251001',
        256
    );

    respond([
        'ok'   => true,
        'tip'  => trim($result['text']),
        'tokens' => $result['input_tokens'] + $result['output_tokens'],
    ]);
} catch (\Throwable $e) {
    error_log('exercise-tip error: ' . $e->getMessage());
    respondError('No se pudo generar el tip. Intenta de nuevo.', 500);
}
