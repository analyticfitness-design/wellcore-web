<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
/**
 * WellCore Fitness — F4: Generador de Planes con Pipeline IA
 * ============================================================
 * POST /api/ai/generate-plan
 *
 * Pipeline multi-etapa que usa el Router IA:
 *   1. Analisis del perfil (local, gratis)
 *   2. Generacion del plan (Router decide local/cloud)
 *   3. Validacion de calidad (local, gratis)
 *
 * Auth:  Bearer token de admin
 * Body:  { client_id: int, plan_type: "entrenamiento"|"nutricion"|"habitos", triggered_by?: string }
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/prompts.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai-client.php';

requireMethod('POST');
$admin     = authenticateAdmin();
$body      = getJsonBody();
$clientId  = (int) ($body['client_id'] ?? 0);
$planType  = trim($body['plan_type'] ?? 'entrenamiento');
$triggeredBy = trim($body['triggered_by'] ?? 'admin');

if (!$clientId) respondError('client_id requerido', 422);
if (!in_array($planType, ['entrenamiento', 'nutricion', 'habitos', 'rise'], true)) {
    respondError('plan_type debe ser: entrenamiento, nutricion, habitos o rise', 422);
}
if (!ai_check_rate_limit()) {
    respondError('Rate limit alcanzado. Maximo ' . AI_RATE_LIMIT_PER_HOUR . ' generaciones por hora.', 429);
}

// ── Cargar perfil del cliente ─────────────────────────────────
try {
    $client = get_client_for_ai($clientId);
} catch (\RuntimeException $e) {
    respondError($e->getMessage(), 404);
}

// Para RISE: enriquecer perfil con datos de intake
$riseIntake = null;
if ($planType === 'rise') {
    try {
        $riseStmt = getDB()->prepare("
            SELECT personalized_program, experience_level, training_location, gender
            FROM rise_programs WHERE client_id = ? ORDER BY id DESC LIMIT 1
        ");
        $riseStmt->execute([$clientId]);
        $riseRow = $riseStmt->fetch(PDO::FETCH_ASSOC);
        if ($riseRow && $riseRow['personalized_program']) {
            $riseIntake = json_decode($riseRow['personalized_program'], true);
            // Sobrescribir datos de perfil con los del intake si están disponibles
            if ($riseRow['experience_level']) { $client['nivel'] = $riseRow['experience_level']; }
            if ($riseRow['training_location']) { $client['lugar_entreno'] = $riseRow['training_location']; }
            if ($riseRow['gender']) { $client['gender'] = $riseRow['gender']; }
        }
    } catch (\Throwable $ignored) {}
}

$pipeline        = [];
$result          = ['content' => ''];
$validationScore = 100;
$validationNotes = [];

if ($planType === 'rise') {
    // ── RISE: generacion SINCRONA (inline, sin worker) ────────────────
    set_time_limit(600); // 10 min max para llamada Claude

    // Auto-reset generaciones atascadas en "generating" por mas de 5 min
    getDB()->prepare("
        UPDATE ai_generations SET status = 'failed', raw_response = 'Worker timeout - auto-reset'
        WHERE client_id = ? AND type = 'rise' AND status = 'generating'
          AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ")->execute([$clientId]);

    $genId = ai_save_generation(['client_id' => $clientId, 'type' => 'rise', 'status' => 'generating']);

    $userPrompt = build_rise_enriched_prompt($client, $riseIntake);
    $userPrompt .= "\n\nGENERA EL PLAN RISE 30 DIAS EN JSON ESTRICTO (sin texto fuera del JSON).\n\nESQUEMA REQUERIDO:\n" . get_plan_schema('rise');

    try {
        $response = claude_call(get_rise_system_prompt(), $userPrompt, CLAUDE_MODEL, CLAUDE_MAX_TOKENS);
        $parsed   = extract_json_from_response($response['text']);

        if ($parsed) {
            ai_save_plan($clientId, 'rise', $parsed, $genId);
        }
        ai_update_generation(
            $genId,
            $parsed ? 'completed' : 'failed',
            $response['text'],
            $parsed ? json_encode($parsed, JSON_UNESCAPED_UNICODE) : null
        );

        // Guardar tokens
        getDB()->prepare("UPDATE ai_generations SET prompt_tokens = ?, completion_tokens = ? WHERE id = ?")
            ->execute([$response['input_tokens'], $response['output_tokens'], $genId]);

        respond([
            'ok'            => true,
            'generation_id' => $genId,
            'status'        => $parsed ? 'completed' : 'failed',
            'plan'          => $parsed,
            'message'       => $parsed
                ? 'Plan RISE generado. Aparecerá en "Planes por Validar".'
                : 'Error: no se pudo parsear respuesta de IA.',
        ], $parsed ? 201 : 500);

    } catch (\Throwable $e) {
        ai_update_generation($genId, 'failed', $e->getMessage());
        respondError('Error generando plan RISE: ' . $e->getMessage(), 500);
    }

} else {
    // ── Planes estándar: pipeline con WellCoreAI router ──────────────────────
    $ai          = new WellCoreAI();
    $profileText = build_client_profile_text($client);

    // Stage 1: análisis del perfil (router local — puede fallar en producción)
    $stage1Start = microtime(true);
    try {
        $analysis     = $ai->chatLocal($profileText, "Eres analista fitness. Devuelve SOLO JSON: {\"nivel_real\":\"intermedio\",\"prioridades\":[],\"limitaciones\":[],\"volumen_recomendado\":\"medio\",\"frecuencia_recomendada\":4}");
        $analysisData = extract_json_from_response($analysis['content'] ?? '');
        $pipeline[]   = ['stage' => 'profile_analysis', 'status' => 'completed', 'duration' => round(microtime(true) - $stage1Start, 2)];
    } catch (\Throwable $e) {
        $analysisData = [
            'nivel_real'             => $client['nivel'] ?: 'intermedio',
            'prioridades'            => [$client['objetivo'] ?: 'recomposicion'],
            'limitaciones'           => [],
            'volumen_recomendado'    => 'medio',
            'frecuencia_recomendada' => count($client['dias_disponibles']) ?: 4,
        ];
        $pipeline[] = ['stage' => 'profile_analysis', 'status' => 'fallback_defaults'];
    }

    // Stage 2: generación del plan
    $genId = ai_save_generation(['client_id' => $clientId, 'type' => $planType, 'status' => 'pending']);
    $systemPrompts = [
        'entrenamiento' => get_training_system_prompt(),
        'nutricion'     => get_nutrition_system_prompt(),
        'habitos'       => get_habits_system_prompt(),
    ];
    $systemPrompt    = $systemPrompts[$planType];
    $enrichedPrompt  = "ANÁLISIS PREVIO:\n" . ($analysisData ? json_encode($analysisData, JSON_UNESCAPED_UNICODE) : '') . "\n\n" . $profileText;
    $enrichedPrompt .= "\n\nGENERA EL PLAN DE " . strtoupper($planType) . " EN JSON.\n\nESQUEMA:\n" . get_plan_schema($planType);

    $stage2Start = microtime(true);
    try {
        $result     = $ai->chat($enrichedPrompt, $systemPrompt);
        $parsed     = extract_json_from_response($result['content'] ?? '');
        $pipeline[] = ['stage' => 'plan_generation', 'status' => 'completed', 'route' => $result['route'] ?? 'unknown', 'model' => $result['model'] ?? 'unknown', 'duration' => round(microtime(true) - $stage2Start, 2)];
    } catch (\Throwable $e) {
        ai_update_generation($genId, 'failed', $e->getMessage());
        respondError('Error generando plan: ' . $e->getMessage(), 500);
    }

    // Stage 3: validación (router local — puede fallar en producción)
    if ($parsed) {
        $stage3Start = microtime(true);
        try {
            $validation = $ai->chatLocal("Evalua este plan. SOLO JSON: {\"score\":85,\"issues\":[],\"suggestions\":[]}\n\nPLAN:\n" . json_encode($parsed, JSON_UNESCAPED_UNICODE), "Validador fitness. Solo JSON.");
            $valData    = extract_json_from_response($validation['content'] ?? '');
            if ($valData && isset($valData['score'])) { $validationScore = (int) $valData['score']; $validationNotes = $valData['issues'] ?? []; }
            $pipeline[] = ['stage' => 'quality_validation', 'status' => 'completed', 'score' => $validationScore, 'duration' => round(microtime(true) - $stage3Start, 2)];
        } catch (\Throwable $e) {
            $pipeline[] = ['stage' => 'quality_validation', 'status' => 'skipped'];
        }
    }
}

// ── Guardar resultados ────────────────────────────────────────────────────────
if ($parsed) {
    ai_save_plan($clientId, $planType, $parsed, $genId);
}

$pipelineJson = json_encode($pipeline, JSON_UNESCAPED_UNICODE);
ai_update_generation(
    $genId,
    $parsed ? 'completed' : 'failed',
    $result['content'],
    $parsed ? json_encode($parsed, JSON_UNESCAPED_UNICODE) : null
);

try {
    getDB()->prepare("UPDATE ai_generations SET pipeline_stage = 'completed', pipeline_data = ? WHERE id = ?")
        ->execute([$pipelineJson, $genId]);
} catch (\Throwable $ignored) {}

respond([
    'ok'            => true,
    'generation_id' => $genId,
    'client'        => ['id' => $clientId, 'name' => $client['name']],
    'plan_type'     => $planType,
    'plan'          => $parsed,
    'quality_score' => $validationScore,
    'quality_notes' => $validationNotes,
    'pipeline'      => $pipeline,
    'status'        => 'pending_review',
    'message'       => "Plan de $planType generado. Pendiente revisión del coach.",
], 201);


// Prompts y schemas movidos a prompts.php (shared con worker-rise.php)
