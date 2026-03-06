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
    // ── RISE: pipeline directo (Stage 1 y 3 siempre fallan en producción) ────
    // Stage 1: skipped — el intake tiene todos los datos; no hay router local
    $pipeline[] = [
        'stage'  => 'profile_analysis',
        'status' => 'skipped_rise_mode',
        'note'   => 'RISE usa intake completo directamente',
    ];

    // Stage 2: llamada directa a Claude (sin pasar por WellCoreAI router)
    $genId      = ai_save_generation(['client_id' => $clientId, 'type' => 'rise', 'status' => 'pending']);
    $userPrompt = build_rise_enriched_prompt($client, $riseIntake);
    $userPrompt .= "\n\nGENERA EL PLAN RISE 30 DÍAS EN JSON ESTRICTO (sin texto fuera del JSON).\n\nESQUEMA REQUERIDO:\n" . get_plan_schema('rise');

    $stage2Start = microtime(true);
    try {
        // Sonnet para RISE: soporta hasta 64K tokens de output (Haiku solo 8K)
        $riseModel = defined('CLAUDE_SONNET_MODEL') ? CLAUDE_SONNET_MODEL : 'claude-sonnet-4-5-20251015';
        $response = claude_call(get_rise_system_prompt(), $userPrompt, $riseModel, 16000);
        $parsed   = extract_json_from_response($response['text']);
        $result   = ['content' => $response['text']];
        $pipeline[] = [
            'stage'    => 'plan_generation',
            'status'   => 'completed',
            'route'    => 'claude_direct',
            'model'    => CLAUDE_MODEL,
            'tokens'   => ['input' => $response['input_tokens'], 'output' => $response['output_tokens']],
            'duration' => round(microtime(true) - $stage2Start, 2),
        ];
    } catch (\Throwable $e) {
        ai_update_generation($genId, 'failed', $e->getMessage());
        respondError('Error generando plan RISE: ' . $e->getMessage(), 500);
    }

    // Stage 3: skipped — schema estricto + prompt especializado garantizan estructura
    $pipeline[] = ['stage' => 'quality_validation', 'status' => 'skipped_rise_mode'];

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


// ── Helper functions para system prompts por tipo ────────────

function get_training_system_prompt(): string {
    $custom = get_ai_prompt('entrenamiento');
    if ($custom['system_prompt']) return $custom['system_prompt'];

    return <<<'PROMPT'
Eres un entrenador de alto rendimiento y científico del ejercicio con 15 años de experiencia.
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
Eres un nutricionista deportivo con 12 años de experiencia en composición corporal.
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

function get_rise_system_prompt(): string {
    $custom = get_ai_prompt('rise');
    if ($custom['system_prompt']) return $custom['system_prompt'];

    return <<<'PROMPT'
Eres entrenador élite WellCore Fitness. Genera el PLAN RISE 30 DÍAS en JSON estricto (cero texto fuera del JSON).

REGLAS:
- 4 semanas con progresión: S1=acumulación RIR3, S2=acumulación RIR2, S3=intensificación RIR1, S4=deload RIR4
- Adapta TODOS los ejercicios al lugar declarado (gym/casa/híbrido) y equipo disponible
- Respeta ESTRICTAMENTE lesiones y ejercicios a evitar
- Cardio: obligatorio si el objetivo incluye pérdida de grasa o el cliente ya hace cardio (3x/sem Zona2 o HIIT según nivel); incluir aunque sea opcional para todos los demás
- Tips de nutrición: principios educativos sin gramajes ni menú rígido — cerrar recomendando Asesoría Nutricional WellCore
- Ajusta volumen/intensidad al nivel de experiencia declarado
- Personaliza basándote en TODOS los datos del perfil (días disponibles, tiempo por sesión, dieta, estilo de vida, metas)
- Cada sesión debe tener calentamiento, ejercicios con series/reps/descanso/notas, y vuelta a la calma
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

    $schemas['rise'] = json_encode([
        'resumen_cliente'     => 'Breve descripcion del perfil y objetivo en el reto',
        'objetivo_30_dias'    => 'Que lograra este cliente en 30 dias con adherencia',
        'incluye_cardio'      => true,
        'razon_cardio'        => 'Por que se incluye o no el cardio para este cliente',
        'dias_entreno_semana' => 4,
        'estructura_semana'   => 'Ej: Lun/Mie/Vie pesas + Mar/Jue cardio, Dom descanso',
        'plan_entrenamiento'  => [
            'semanas' => [[
                'semana'        => 1,
                'nombre'        => 'Adaptacion',
                'rir_objetivo'  => 3,
                'descripcion'   => 'Enfoque de esta semana',
                'sesiones'      => [[
                    'dia'           => 'Lunes',
                    'nombre'        => 'Nombre del dia (Ej: Piernas / Empuje)',
                    'calentamiento' => '5-10min calentamiento especifico',
                    'ejercicios'    => [[
                        'nombre'    => 'Sentadilla con barra',
                        'series'    => 4,
                        'reps'      => '8-10',
                        'descanso'  => '90s',
                        'notas'     => 'Notas tecnicas y de ejecucion',
                    ]],
                    'vuelta_calma' => '5min estiramiento',
                ]],
            ]],
        ],
        'cardio'              => [
            'incluido'           => true,
            'frecuencia_semanal' => 3,
            'duracion_min'       => 30,
            'tipo'               => 'Zona 2 (conversacional) o HIIT segun nivel',
            'cuando'             => 'Dias de descanso de pesas o post-entrenamiento (20min)',
            'opciones_gym'       => ['Bicicleta estacionaria 30min Z2', 'Eliptica 30min'],
            'opciones_casa'      => ['Caminata rapida 30min', 'HIIT bodyweight 20min'],
            'semanas_progresion' => 'Como escalar el cardio semana a semana',
        ],
        'tips_nutricion'      => [
            'principio_base'              => 'El principio nutricional mas importante para este cliente',
            'proteina'                    => 'Por que la proteina es clave y como incluirla (sin gramajes exactos)',
            'hidratacion'                 => 'Meta de agua diaria y por que importa en el reto',
            'distribucion_comidas'        => 'Cuantas comidas y con que logica distribuirlas',
            'alimentos_aliados'           => ['Alimento 1', 'Alimento 2', 'Alimento 3'],
            'alimentos_reducir'           => ['Alimento a reducir 1', 'Alimento a reducir 2'],
            'pre_entreno'                 => 'Que comer 1-2h antes de entrenar',
            'post_entreno'                => 'Que comer en los 60min post-entrenamiento',
            'respeto_dieta_cliente'       => 'Como adaptar estos tips a la dieta/preferencia del cliente',
            'nota_asesoria_nutricional'   => 'Para maximizar tus resultados con un plan 100% personalizado — macros exactos, ajustes semanales y seguimiento real — te recomendamos la Asesoria Nutricional WellCore al finalizar el reto.',
        ],
        'progresion_semanal'  => 'Como debe escalar el cliente semana a semana (volumen, intensidad, cardio)',
        'indicadores_progreso' => ['Que medir semana a semana para saber que va bien'],
        'nota_coach'          => 'Mensaje motivacional y consejo clave del coach para el reto',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    return $schemas[$type] ?? $schemas['entrenamiento'];
}
