<?php
/**
 * WellCore Fitness — M15: AI Plan Generation
 * ============================================================
 * POST /api/plans/ai-generate.php
 *
 * Genera un plan de entrenamiento personalizado usando Claude Haiku
 * basándose en una plantilla (plan_templates) y los datos del cliente.
 * Incluye fallback obligatorio al template_data base si Claude falla.
 *
 * Auth: authenticateAdmin() — solo coaches y admins
 *
 * Body:
 * {
 *   "template_id": 1,
 *   "client_id": 3,
 *   "overrides": {
 *     "weeks": 8,
 *     "equipment": "gimnasio completo",
 *     "restrictions": "lesión rodilla derecha"
 *   }
 * }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/ai.php';
require_once __DIR__ . '/../ai/helpers.php';

requireMethod('POST');

$admin = authenticateAdmin();
$body  = getJsonBody();
$db    = getDB();

// ──────────────────────────────────────────────────────────────
// Validar parámetros de entrada
// ──────────────────────────────────────────────────────────────
$templateId = (int) ($body['template_id'] ?? 0);
if ($templateId <= 0) {
    respondError('El campo template_id es requerido y debe ser un entero positivo', 400);
}

$clientId = (int) ($body['client_id'] ?? 0);
if ($clientId <= 0) {
    respondError('El campo client_id es requerido y debe ser un entero positivo', 400);
}

$overrides = is_array($body['overrides'] ?? null) ? $body['overrides'] : [];

// ──────────────────────────────────────────────────────────────
// 1. Cargar template desde plan_templates
// ──────────────────────────────────────────────────────────────
$stmtTpl = $db->prepare("
    SELECT id, coach_id, title, description, plan_type, methodology, template_data
    FROM plan_templates
    WHERE id = ? AND coach_id = ? AND is_active = 1
");
$stmtTpl->execute([$templateId, $admin['id']]);
$template = $stmtTpl->fetch();

if (!$template) {
    respondError('Template no encontrado', 404);
}

// Decodificar template_data
$templateData = json_decode($template['template_data'] ?? 'null', true);
if (!is_array($templateData)) {
    $templateData = [];
}

// ──────────────────────────────────────────────────────────────
// 2. Cargar datos del cliente
// ──────────────────────────────────────────────────────────────
try {
    $clientProfile = get_client_for_ai($clientId);
} catch (\RuntimeException $e) {
    respondError($e->getMessage(), 404);
}

// ──────────────────────────────────────────────────────────────
// 3. Construir contexto del plan (template + overrides)
// ──────────────────────────────────────────────────────────────
$weeksRequested    = (int) ($overrides['weeks']        ?? $templateData['weeks']            ?? 4);
$equipment         = trim($overrides['equipment']       ?? $templateData['equipment']        ?? 'gimnasio completo');
$restrictions      = trim($overrides['restrictions']    ?? $clientProfile['restricciones']   ?? 'ninguna');
$sessionsPerWeek   = (int) ($overrides['sessions_per_week'] ?? $templateData['sessions_per_week'] ?? 4);
$focus             = trim($overrides['focus']           ?? $templateData['focus']            ?? 'fuerza');

// Merge de overrides sobre template_data para el prompt
$mergedTemplateData = array_merge($templateData, array_filter($overrides, fn($v) => $v !== null && $v !== ''));

// ──────────────────────────────────────────────────────────────
// 4. Construir prompts para Claude
// ──────────────────────────────────────────────────────────────
$templateDataText    = json_encode($mergedTemplateData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$methodology         = $template['methodology'] ?: 'Periodización estándar, progresión gradual.';

$systemPrompt = "Eres un coach de fitness experto en periodización y diseño de planes de entrenamiento personalizados. Tu especialidad es crear programas seguros, efectivos y adaptados a las necesidades individuales de cada cliente. Siempre respetas las restricciones físicas y el nivel de experiencia del cliente.";

$userPrompt  = "Eres un coach de fitness experto. Basándote en esta plantilla de entrenamiento:\n\n";
$userPrompt .= "PLANTILLA: " . $template['title'] . "\n";
$userPrompt .= "Descripción: " . ($template['description'] ?: 'Sin descripción') . "\n";
$userPrompt .= "Tipo de plan: " . strtoupper($template['plan_type']) . "\n\n";
$userPrompt .= "METODOLOGÍA:\n$methodology\n\n";
$userPrompt .= "DATOS DE LA PLANTILLA (JSON):\n$templateDataText\n\n";
$userPrompt .= "---\n\n";
$userPrompt .= "Personaliza el plan para este cliente:\n";
$userPrompt .= "- Nombre: " . ($clientProfile['name'] ?: 'No especificado') . "\n";
$userPrompt .= "- Plan: " . strtoupper($clientProfile['plan'] ?: 'esencial') . "\n";
$userPrompt .= "- Edad: " . ($clientProfile['edad'] ?: 'No especificada') . " años\n";
$userPrompt .= "- Nivel de experiencia: " . ($clientProfile['nivel'] ?: 'Intermedio') . "\n";
$userPrompt .= "- Objetivo: " . ($clientProfile['objetivo'] ?: 'Mejorar composición corporal') . "\n";
$userPrompt .= "- Lugar de entrenamiento: " . ($clientProfile['lugar_entreno'] ?: 'Gimnasio completo') . "\n";
$userPrompt .= "- Semanas solicitadas: $weeksRequested\n";
$userPrompt .= "- Sesiones por semana: $sessionsPerWeek\n";
$userPrompt .= "- Equipamiento: $equipment\n";
$userPrompt .= "- Restricciones físicas: $restrictions\n";
$userPrompt .= "- Enfoque del plan: $focus\n";

if (!empty($overrides)) {
    $userPrompt .= "\nAJUSTES ADICIONALES DEL COACH:\n";
    $overrides = array_slice($overrides, 0, 10);
    foreach ($overrides as $key => $value) {
        if ($value !== null && $value !== '') {
            $safeVal = substr((string)$value, 0, 500);
            $userPrompt .= "- $key: $safeVal\n";
        }
    }
}

$userPrompt .= "\n---\n\n";
$userPrompt .= "Retorna SOLO un JSON válido con esta estructura exacta (sin texto antes ni después del JSON):\n";
$userPrompt .= "{\n";
$userPrompt .= "  \"weeks\": N,\n";
$userPrompt .= "  \"sessions_per_week\": N,\n";
$userPrompt .= "  \"plan\": [\n";
$userPrompt .= "    {\n";
$userPrompt .= "      \"week\": 1,\n";
$userPrompt .= "      \"days\": [\n";
$userPrompt .= "        {\"day\": \"Lunes\", \"focus\": \"...\", \"exercises\": [{\"name\": \"...\", \"sets\": N, \"reps\": \"...\", \"notes\": \"...\"}]}\n";
$userPrompt .= "      ]\n";
$userPrompt .= "    }\n";
$userPrompt .= "  ],\n";
$userPrompt .= "  \"notes\": \"...\",\n";
$userPrompt .= "  \"progressions\": \"...\"\n";
$userPrompt .= "}\n";

// ──────────────────────────────────────────────────────────────
// 5. Llamada a Claude con fallback obligatorio
// ──────────────────────────────────────────────────────────────
$planAiGenerated = false;
$generatedPlan   = null;
$aiError         = null;
$inputTokens     = 0;
$outputTokens    = 0;

// Modelo preferido para planes: Haiku (rápido y económico)
$haikuModel = 'claude-haiku-4-5-20251001';

try {
    $aiResult = claude_call(
        $systemPrompt,
        $userPrompt,
        $haikuModel,
        2000
    );

    $rawText     = $aiResult['text'];
    $inputTokens  = $aiResult['input_tokens'];
    $outputTokens = $aiResult['output_tokens'];

    // Extraer JSON de la respuesta de Claude
    $parsed = extract_json_from_response($rawText);

    if (is_array($parsed) && !empty($parsed['plan'])) {
        $generatedPlan   = $parsed;
        $planAiGenerated = true;
    } else {
        $aiError = 'Claude respondió pero el JSON no tiene la estructura esperada (falta clave "plan").';
        error_log("[WellCore M15] JSON inválido de Claude para template=$templateId client=$clientId: " . substr($rawText, 0, 300));
    }
} catch (\RuntimeException $e) {
    $aiError = $e->getMessage();
    error_log("[WellCore M15] Claude falló para template=$templateId client=$clientId: $aiError");
}

// ──────────────────────────────────────────────────────────────
// 6. Fallback: usar template_data base si Claude falló
// ──────────────────────────────────────────────────────────────
if (!$planAiGenerated) {
    // Construir un plan base estructurado desde el template_data
    $generatedPlan = buildFallbackPlan($templateData, $weeksRequested, $sessionsPerWeek);
}

// ──────────────────────────────────────────────────────────────
// 7. Respuesta
// ──────────────────────────────────────────────────────────────
$response = [
    'plan_ai_generated' => $planAiGenerated,
    'template_id'       => $templateId,
    'client_id'         => $clientId,
    'template_title'    => $template['title'],
    'plan_type'         => $template['plan_type'],
    'generated_plan'    => $generatedPlan,
    'overrides_applied' => $overrides,
];

if ($planAiGenerated) {
    $response['ai_model']       = $haikuModel;
    $response['input_tokens']   = $inputTokens;
    $response['output_tokens']  = $outputTokens;
    $response['estimated_cost_usd'] = ($inputTokens / 1_000_000 * 0.25) + ($outputTokens / 1_000_000 * 1.25);
} else {
    $response['fallback_reason'] = $aiError ?? 'Claude no disponible';
    $response['note']            = 'Se retornó el plan base de la plantilla. El plan NO fue generado por IA.';
}

respond($response);

// ──────────────────────────────────────────────────────────────
// Función auxiliar: construir plan de fallback desde template_data
// ──────────────────────────────────────────────────────────────

/**
 * Construye un plan base estructurado a partir del template_data.
 * Se usa cuando Claude no está disponible o falla.
 */
function buildFallbackPlan(array $templateData, int $weeks, int $sessionsPerWeek): array
{
    $focus   = $templateData['focus']   ?? 'fuerza';
    $phases  = $templateData['phases']  ?? [];

    // Días de semana por defecto según sessionsPerWeek
    $allDays = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    $days    = array_slice($allDays, 0, min($sessionsPerWeek, 7));

    // Ejercicios base por tipo de enfoque
    $exercisesByFocus = [
        'fuerza'    => [
            ['name' => 'Sentadilla con barra', 'sets' => 4, 'reps' => '5',    'notes' => 'RPE 8'],
            ['name' => 'Press banca',          'sets' => 4, 'reps' => '5',    'notes' => 'RPE 8'],
            ['name' => 'Peso muerto',          'sets' => 3, 'reps' => '5',    'notes' => 'RPE 8'],
            ['name' => 'Press militar',        'sets' => 3, 'reps' => '6',    'notes' => 'RPE 7'],
            ['name' => 'Remo con barra',       'sets' => 3, 'reps' => '6',    'notes' => 'RPE 7'],
        ],
        'hipertrofia' => [
            ['name' => 'Sentadilla',           'sets' => 4, 'reps' => '8-12', 'notes' => 'Control excéntrico'],
            ['name' => 'Press banca',          'sets' => 4, 'reps' => '8-12', 'notes' => 'Contracción completa'],
            ['name' => 'Jalón al pecho',       'sets' => 3, 'reps' => '10-15','notes' => 'Apertura escapular'],
            ['name' => 'Curl de bíceps',       'sets' => 3, 'reps' => '12-15','notes' => 'Sin impulso'],
            ['name' => 'Extensión tríceps',    'sets' => 3, 'reps' => '12-15','notes' => 'Codo fijo'],
        ],
        'resistencia' => [
            ['name' => 'Sentadilla goblet',    'sets' => 3, 'reps' => '15-20','notes' => 'Tempo controlado'],
            ['name' => 'Zancadas alternadas',  'sets' => 3, 'reps' => '12/p', 'notes' => 'Paso largo'],
            ['name' => 'Flexiones',            'sets' => 3, 'reps' => '15-20','notes' => 'Core activo'],
            ['name' => 'Remo TRX',             'sets' => 3, 'reps' => '15',   'notes' => 'Escápulas juntas'],
            ['name' => 'Plancha',              'sets' => 3, 'reps' => '45seg', 'notes' => 'Alineación neutra'],
        ],
        'funcional'  => [
            ['name' => 'Kettlebell swing',     'sets' => 4, 'reps' => '15',   'notes' => 'Explosivo'],
            ['name' => 'Turkish get-up',       'sets' => 3, 'reps' => '5/l',  'notes' => 'Lento y controlado'],
            ['name' => 'Box jump',             'sets' => 3, 'reps' => '8',    'notes' => 'Aterrizaje suave'],
            ['name' => 'Farmer carry',         'sets' => 3, 'reps' => '30m',  'notes' => 'Core braced'],
            ['name' => 'Battle ropes',         'sets' => 3, 'reps' => '30seg','notes' => 'Máxima intensidad'],
        ],
    ];

    $exercises = $exercisesByFocus[$focus] ?? $exercisesByFocus['fuerza'];

    // Construir semanas
    $planWeeks = [];
    for ($w = 1; $w <= $weeks; $w++) {
        // Determinar fase si el template tiene fases definidas
        $phaseIndex = (int) floor(($w - 1) / max(1, ceil($weeks / max(1, count($phases)))));
        $currentPhase = $phases[$phaseIndex] ?? $focus;

        $weekDays = [];
        foreach ($days as $i => $dayName) {
            // Rotar foco por día si hay varias sesiones
            $dayFocus = match ($i % 4) {
                0       => 'Tren inferior — ' . $currentPhase,
                1       => 'Tren superior — empuje',
                2       => 'Full body / cardio',
                default => 'Tren superior — jalón',
            };

            // Ajustar volumen según semana (deload en última semana si hay 4+)
            $volumeFactor = ($w === $weeks && $weeks >= 4) ? 0.6 : 1.0;
            $dayExercises = array_map(function ($ex) use ($volumeFactor) {
                $ex['sets'] = max(1, (int) round($ex['sets'] * $volumeFactor));
                return $ex;
            }, array_slice($exercises, 0, 4));

            $weekDays[] = [
                'day'       => $dayName,
                'focus'     => $dayFocus,
                'exercises' => $dayExercises,
            ];
        }

        $planWeeks[] = [
            'week'  => $w,
            'phase' => $currentPhase,
            'days'  => $weekDays,
        ];
    }

    return [
        'weeks'            => $weeks,
        'sessions_per_week'=> $sessionsPerWeek,
        'plan'             => $planWeeks,
        'notes'            => 'Plan base generado desde la plantilla. Ajusta pesos y volumen según el nivel del cliente.',
        'progressions'     => 'Aumenta el peso 2.5-5kg cuando completes todas las series con buena técnica por 2 sesiones consecutivas.',
    ];
}
