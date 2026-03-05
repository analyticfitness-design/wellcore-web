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
    'rise'          => get_rise_system_prompt(),
];
$systemPrompt = $systemPrompts[$planType];

// Enriquecer user prompt con analisis de etapa 1
$enrichedPrompt  = "ANALISIS PREVIO DEL PERFIL:\n";
if ($analysisData) {
    $enrichedPrompt .= json_encode($analysisData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
$enrichedPrompt .= "\n\n" . $profileText;

// Para RISE: agregar datos completos del intake
if ($planType === 'rise' && $riseIntake) {
    $enrichedPrompt .= "\n\nDATOS DETALLADOS DEL INTAKE RISE:\n";
    $enrichedPrompt .= json_encode($riseIntake, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    // Determinar si incluir cardio basado en objetivos declarados
    $goals = $riseIntake['motivation']['motivation'] ?? [];
    $trainingTypes = $riseIntake['training']['trainingType'] ?? [];
    $wantsCardio = in_array('weight_loss', $goals) || in_array('cardio', $trainingTypes) || in_array('health', $goals);
    $enrichedPrompt .= "\n\nINDICACIÓN DE CARDIO: " . ($wantsCardio ? "INCLUIR (objetivo o preferencia del cliente lo requiere)" : "EVALUAR según contexto del cliente");
}

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
Eres un entrenador personal de élite que trabaja para WellCore Fitness.
Tu tarea: crear el PLAN COMPLETO DEL RETO RISE 30 DÍAS, personalizado para el cliente.

EL PLAN RISE CONTIENE:
1. PLAN DE ENTRENAMIENTO (4 semanas con progresión de sobrecarga)
2. CARDIO: incluirlo si el cliente busca perder grasa, ya hace cardio, o si complementa sus metas.
   Adaptarlo al lugar de entrenamiento (gym o casa).
3. TIPS DE NUTRICIÓN: NO una dieta específica con gramajes ni menú diario.
   Son principios educativos que:
   - Le dan resultados reales en 30 días con adherencia básica
   - Le enseñan sobre macronutrientes y alimentación funcional
   - Lo preparan para tomar mejores decisiones sin depender de un plan rígido
   - Al final, mencionan que para resultados máximos se recomienda la Asesoría Nutricional WellCore

FILOSOFÍA RISE:
- 30 días transformadores, accesibles y progresivos
- Resultados visibles = adherencia + entrenamiento bien estructurado + nutrición básica sólida
- Los tips de nutrición deben motivar a aprender más, no resolver todo

REGLAS DE CARDIO:
- Si objetivo incluye pérdida de grasa: obligatorio (3x/semana Zona 2 o HIIT según nivel)
- Si cliente ya hace cardio: estructurarlo mejor dentro del plan
- Si entrena en casa: opciones bodyweight (burpees, salto de cuerda, HIIT con peso corporal)
- Si solo hace pesas: agregar 2-3 sesiones/semana de Zona 2 (caminar rápido, bici estacionaria)
- Cardio en zona 2 preserva músculo durante el reto — prioridad para no perder masa

PERSONALIZACIÓN OBLIGATORIA:
- Adaptar ejercicios a lugar (gym/casa/híbrido) y equipo disponible
- Respetar lesiones, ejercicios a evitar y restricciones
- Ajustar volumen/intensidad al nivel de experiencia
- Considerar días disponibles para organizar la semana
- Si el cliente menciona dieta específica (vegetariana, keto, etc.) respetar esos lineamientos en los tips

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
