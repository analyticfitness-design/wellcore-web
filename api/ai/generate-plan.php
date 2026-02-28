<?php
declare(strict_types=1);
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
if (!in_array($planType, ['entrenamiento', 'nutricion', 'habitos'], true)) {
    respondError('plan_type debe ser: entrenamiento, nutricion o habitos', 422);
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

$ai = new WellCoreAI();
$profileText = build_client_profile_text($client);
$pipeline = [];

// ── ETAPA 1: Analisis del perfil (local, gratis) ─────────────
$stage1System = "Eres un analista de perfiles de clientes fitness. Analiza el perfil y devuelve un JSON con: nivel_real (principiante/intermedio/avanzado), prioridades (array de 3 prioridades), limitaciones (array), volumen_recomendado (bajo/medio/alto), frecuencia_recomendada (dias/semana como numero). Solo JSON, sin texto extra.";

$stage1Start = microtime(true);
try {
    $analysis = $ai->chatLocal($profileText, $stage1System);
    $analysisData = extract_json_from_response($analysis['content'] ?? '');
    $pipeline[] = [
        'stage'    => 'profile_analysis',
        'status'   => 'completed',
        'route'    => $analysis['route'] ?? 'local',
        'duration' => round(microtime(true) - $stage1Start, 2),
    ];
} catch (\Throwable $e) {
    // Si falla el analisis local, usar valores por defecto
    $analysisData = [
        'nivel_real'             => $client['nivel'] ?: 'intermedio',
        'prioridades'            => [$client['objetivo'] ?: 'recomposicion'],
        'limitaciones'           => [],
        'volumen_recomendado'    => 'medio',
        'frecuencia_recomendada' => count($client['dias_disponibles']) ?: 4,
    ];
    $pipeline[] = [
        'stage'  => 'profile_analysis',
        'status' => 'fallback_defaults',
        'error'  => $e->getMessage(),
    ];
}

// ── ETAPA 2: Generacion del plan (Router decide) ─────────────
$genId = ai_save_generation([
    'client_id' => $clientId,
    'type'      => $planType,
    'status'    => 'pending',
]);

// Construir system prompt segun tipo de plan
$systemPrompts = [
    'entrenamiento' => get_training_system_prompt(),
    'nutricion'     => get_nutrition_system_prompt(),
    'habitos'       => get_habits_system_prompt(),
];
$systemPrompt = $systemPrompts[$planType];

// Enriquecer user prompt con analisis de etapa 1
$enrichedPrompt  = "ANALISIS PREVIO DEL PERFIL:\n";
if ($analysisData) {
    $enrichedPrompt .= json_encode($analysisData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
$enrichedPrompt .= "\n\n" . $profileText;
$enrichedPrompt .= "\n\nGENERA EL PLAN DE " . strtoupper($planType) . " COMPLETO EN FORMATO JSON.";

// Obtener JSON schema para el tipo de plan
$enrichedPrompt .= "\n\nESTRUCTURA DE RESPUESTA:\n" . get_plan_schema($planType);

$stage2Start = microtime(true);
try {
    $result = $ai->chat($enrichedPrompt, $systemPrompt);
    $parsed = extract_json_from_response($result['content'] ?? '');

    $pipeline[] = [
        'stage'    => 'plan_generation',
        'status'   => 'completed',
        'route'    => $result['route'] ?? 'unknown',
        'model'    => $result['model'] ?? 'unknown',
        'duration' => round(microtime(true) - $stage2Start, 2),
    ];
} catch (\Throwable $e) {
    ai_update_generation($genId, 'failed', $e->getMessage());
    respondError('Error generando plan: ' . $e->getMessage(), 500);
}

// ── ETAPA 3: Validacion de calidad (local, gratis) ────────────
$stage3Start = microtime(true);
$validationScore = 100;
$validationNotes = [];

if ($parsed) {
    $validationPrompt = "Evalua este plan de $planType generado para un cliente. Responde SOLO con JSON: {\"score\": 0-100, \"issues\": [\"issue1\"], \"suggestions\": [\"sugerencia1\"]}. Score 80+ = aceptable. Verifica: completitud, coherencia con el perfil, seguridad.\n\nPLAN:\n" . json_encode($parsed, JSON_UNESCAPED_UNICODE);

    try {
        $validation = $ai->chatLocal($validationPrompt, "Eres un validador de calidad de planes fitness. Solo responde JSON.");
        $valData = extract_json_from_response($validation['content'] ?? '');
        if ($valData && isset($valData['score'])) {
            $validationScore = (int) $valData['score'];
            $validationNotes = $valData['issues'] ?? [];
        }
        $pipeline[] = [
            'stage'    => 'quality_validation',
            'status'   => 'completed',
            'score'    => $validationScore,
            'route'    => $validation['route'] ?? 'local',
            'duration' => round(microtime(true) - $stage3Start, 2),
        ];
    } catch (\Throwable $e) {
        $pipeline[] = [
            'stage'  => 'quality_validation',
            'status' => 'skipped',
            'error'  => $e->getMessage(),
        ];
    }
}

// ── Guardar resultados ────────────────────────────────────────
if ($parsed) {
    ai_save_plan($clientId, $planType, $parsed, $genId);
}

$pipelineJson = json_encode($pipeline, JSON_UNESCAPED_UNICODE);
ai_update_generation(
    $genId,
    $parsed ? 'completed' : 'failed',
    $result['content'] ?? '',
    $parsed ? json_encode($parsed, JSON_UNESCAPED_UNICODE) : null
);

// Intentar guardar pipeline_data en ai_generations
try {
    getDB()->prepare("UPDATE ai_generations SET pipeline_stage = 'completed', pipeline_data = ? WHERE id = ?")
        ->execute([$pipelineJson, $genId]);
} catch (\Throwable $e) {
    // Columnas pipeline_* pueden no existir aun
}

respond([
    'ok'              => true,
    'generation_id'   => $genId,
    'client'          => ['id' => $clientId, 'name' => $client['name']],
    'plan_type'       => $planType,
    'plan'            => $parsed,
    'quality_score'   => $validationScore,
    'quality_notes'   => $validationNotes,
    'pipeline'        => $pipeline,
    'status'          => 'pending_review',
    'message'         => "Plan de $planType generado con pipeline IA. Pendiente revision del coach.",
], 201);


// ── Helper functions para system prompts por tipo ────────────

function get_training_system_prompt(): string {
    $custom = get_ai_prompt('entrenamiento');
    if ($custom['system_prompt']) return $custom['system_prompt'];

    return <<<'PROMPT'
Eres un entrenador de alto rendimiento y cientifico del ejercicio con 15 anos de experiencia.
Trabajas para WellCore Fitness, coaching premium basado en ciencia.

PRINCIPIOS:
- Sobrecarga progresiva: incremento de 2-5% en carga o 1-2 reps cada semana
- Volumen semanal: 10-20 series efectivas por grupo muscular
- Gestion de fatiga con RIR: semana 1 RIR 3, semana 2 RIR 2, semana 3 RIR 1, semana 4 deload RIR 4
- Recuperacion: minimo 48h entre sesiones del mismo grupo muscular
- Tempo controlado en aislamiento: 3-0-1
- Adaptar TODO a lesiones y restricciones

PERIODIZACION 4 SEMANAS:
- Semana 1: Acumulacion ligera (RIR 3, RPE 7)
- Semana 2: Acumulacion moderada (RIR 2, RPE 8)
- Semana 3: Intensificacion (RIR 1, RPE 9)
- Semana 4: Deload activo (50% volumen, RIR 4)

FORMATO: JSON estricto. Sin texto fuera del JSON.
PROMPT;
}

function get_nutrition_system_prompt(): string {
    $custom = get_ai_prompt('nutricion');
    if ($custom['system_prompt']) return $custom['system_prompt'];

    return <<<'PROMPT'
Eres un nutricionista deportivo con 12 anos de experiencia en composicion corporal.
Trabajas para WellCore Fitness, coaching premium basado en ciencia.

PRINCIPIOS:
- TDEE con formula Mifflin-St Jeor + factor de actividad
- Proteina: 1.6-2.2g/kg para hipertrofia
- Grasas: minimo 0.8g/kg
- Carbohidratos: resto de calorias
- Deficit para perder grasa: 300-500 kcal
- Superavit para volumen: 200-300 kcal
- Timing: mayor ingesta de carbos pre y post entreno
- Respetar alergias y restricciones dieteticas

FORMATO: JSON estricto. Sin texto fuera del JSON.
PROMPT;
}

function get_habits_system_prompt(): string {
    $custom = get_ai_prompt('habitos');
    if ($custom['system_prompt']) return $custom['system_prompt'];

    return <<<'PROMPT'
Eres un coach de habitos y estilo de vida especializado en optimizacion del rendimiento.
Trabajas para WellCore Fitness, coaching premium basado en ciencia.

PRINCIPIOS:
- Sueno: 7-9 horas, higiene del sueno
- Hidratacion: 35ml/kg de peso corporal minimo
- Manejo del estres: tecnicas basadas en evidencia
- Habitos atomicos: empezar pequeno, incrementar gradualmente
- Adherencia sobre perfeccion
- Tracking semanal de bienestar

FORMATO: JSON estricto. Sin texto fuera del JSON.
PROMPT;
}

function get_plan_schema(string $type): string {
    $schemas = [
        'entrenamiento' => json_encode([
            'semanas'            => 4,
            'dias_por_semana'    => '3-5 segun disponibilidad',
            'objetivo_principal' => 'Adaptado al cliente',
            'principios_clave'   => ['principio 1', 'principio 2'],
            'dias' => [[
                'dia'           => 1,
                'nombre'        => 'Nombre del dia',
                'calentamiento' => 'Descripcion calentamiento',
                'ejercicios' => [[
                    'nombre'        => 'Nombre ejercicio',
                    'patron_motor'  => 'Empuje/Tiraje/etc',
                    'musculos_prim' => ['Musculo'],
                    'series'        => 4,
                    'reps'          => '8-10',
                    'descanso'      => '90s',
                    'rir_semana'    => [3, 2, 1, 4],
                    'notas'         => 'Notas tecnicas',
                ]],
            ]],
            'progresion_semanal' => 'Instrucciones',
            'notas_coach'        => 'Observaciones',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),

        'nutricion' => json_encode([
            'tdee_estimado'   => 2500,
            'objetivo_cal'    => 2200,
            'macros' => [
                'proteina_g'     => 160,
                'carbohidratos_g' => 220,
                'grasa_g'        => 73,
            ],
            'comidas_por_dia' => 4,
            'plan_semanal' => [[
                'dia'     => 'Lunes (Entreno)',
                'comidas' => [[
                    'nombre'    => 'Desayuno',
                    'alimentos' => ['Avena 80g', 'Whey 30g', 'Banana 1'],
                    'calorias'  => 450,
                    'proteina'  => 35,
                ]],
            ]],
            'suplementos_recomendados' => ['Creatina 5g/dia', 'Vitamina D 2000IU'],
            'notas_coach' => 'Observaciones',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),

        'habitos' => json_encode([
            'duracion_semanas' => 4,
            'pilares' => [
                [
                    'nombre'    => 'Sueno',
                    'meta'      => '7-9 horas por noche',
                    'acciones'  => ['Accion 1', 'Accion 2'],
                    'tracking'  => 'Como medir progreso',
                ],
            ],
            'rutina_manana'  => ['Paso 1', 'Paso 2'],
            'rutina_noche'   => ['Paso 1', 'Paso 2'],
            'checklist_diario' => ['Item 1', 'Item 2'],
            'notas_coach'     => 'Observaciones',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    ];

    return $schemas[$type] ?? $schemas['entrenamiento'];
}
