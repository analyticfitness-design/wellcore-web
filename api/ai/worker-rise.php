#!/usr/bin/env php
<?php
/**
 * WellCore Fitness — RISE Plan Background Worker
 * ================================================
 * Ejecutado como proceso CLI independiente.
 * No depende de PHP-FPM — sobrevive restarts de workers.
 *
 * Usage: php worker-rise.php <generation_id> <client_id>
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
set_time_limit(300);

$genId    = (int) ($argv[1] ?? 0);
$clientId = (int) ($argv[2] ?? 0);

if (!$genId || !$clientId) {
    file_put_contents('php://stderr', "Usage: php worker-rise.php <gen_id> <client_id>\n");
    exit(1);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/prompts.php';

$logPrefix = "[RISE Worker gen=$genId client=$clientId]";

try {
    $client = get_client_for_ai($clientId);
} catch (\Throwable $e) {
    ai_update_generation($genId, 'failed', "Cliente no encontrado: " . $e->getMessage());
    error_log("$logPrefix Cliente no encontrado: " . $e->getMessage());
    exit(1);
}

// Cargar intake RISE
$riseIntake = null;
try {
    $riseStmt = getDB()->prepare("
        SELECT personalized_program, experience_level, training_location, gender
        FROM rise_programs WHERE client_id = ? ORDER BY id DESC LIMIT 1
    ");
    $riseStmt->execute([$clientId]);
    $riseRow = $riseStmt->fetch(PDO::FETCH_ASSOC);
    if ($riseRow && $riseRow['personalized_program']) {
        $riseIntake = json_decode($riseRow['personalized_program'], true);
        if ($riseRow['experience_level']) $client['nivel'] = $riseRow['experience_level'];
        if ($riseRow['training_location']) $client['lugar_entreno'] = $riseRow['training_location'];
        if ($riseRow['gender']) $client['gender'] = $riseRow['gender'];
    }
} catch (\Throwable $ignored) {}

// Construir prompt
$userPrompt = build_rise_enriched_prompt($client, $riseIntake);
$userPrompt .= "\n\nGENERA EL PLAN RISE 30 DIAS EN JSON ESTRICTO (sin texto fuera del JSON).\n\nESQUEMA REQUERIDO:\n" . get_plan_schema('rise');

error_log("$logPrefix Iniciando llamada a Claude (" . CLAUDE_MODEL . ")...");

try {
    $response = claude_call(get_rise_system_prompt(), $userPrompt, CLAUDE_MODEL, CLAUDE_MAX_TOKENS);
    $parsed   = extract_json_from_response($response['text']);

    error_log("$logPrefix Claude respondio. Tokens: in={$response['input_tokens']} out={$response['output_tokens']}. Parsed=" . ($parsed ? 'OK' : 'FAIL'));

    if ($parsed) {
        ai_save_plan($clientId, 'rise', $parsed, $genId);
    }
    ai_update_generation(
        $genId,
        $parsed ? 'completed' : 'failed',
        $response['text'],
        $parsed ? json_encode($parsed, JSON_UNESCAPED_UNICODE) : null
    );

    // Actualizar tokens en ai_generations
    getDB()->prepare("UPDATE ai_generations SET prompt_tokens = ?, completion_tokens = ? WHERE id = ?")
        ->execute([$response['input_tokens'], $response['output_tokens'], $genId]);

    error_log("$logPrefix " . ($parsed ? 'COMPLETADO' : 'FALLIDO (no se pudo parsear JSON)'));
} catch (\Throwable $e) {
    ai_update_generation($genId, 'failed', $e->getMessage());
    error_log("$logPrefix ERROR: " . $e->getMessage());
    exit(1);
}
