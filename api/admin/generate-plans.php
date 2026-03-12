<?php
/**
 * WellCore Fitness — Generate AI Plans for Any Client
 * ============================================================
 * POST /api/admin/generate-plans.php
 *
 * Generates training, nutrition, habits, and/or supplement plans
 * using Claude AI based on client intake data + coach notes.
 * No template required — works directly from client profile.
 *
 * Auth: authenticateAdmin() — superadmin or coach
 *
 * Body:
 * {
 *   "client_id": 5,
 *   "plan_types": ["entrenamiento", "nutricion"],
 *   "coach_notes": "Focus on glutes, protect right knee",
 *   "methodology": "Periodización Ondulante (DUP)",
 *   "training_config": {
 *     "weeks": 4,
 *     "sessions_per_week": 4,
 *     "split": "Upper/Lower"
 *   },
 *   "nutrition_config": {
 *     "approach": "Flexible Dieting (IIFYM)",
 *     "caloric_goal": "deficit"
 *   }
 * }
 *
 * Response: { ok, plans: { entrenamiento: {...}, nutricion: {...} }, cost }
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

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

// ── Validate input ──────────────────────────────────────────
$clientId   = (int) ($body['client_id'] ?? 0);
$planTypes  = $body['plan_types'] ?? [];
$coachNotes = trim($body['coach_notes'] ?? '');
$methodology = trim($body['methodology'] ?? '');
$trainingConfig = is_array($body['training_config'] ?? null) ? $body['training_config'] : [];
$nutritionConfig = is_array($body['nutrition_config'] ?? null) ? $body['nutrition_config'] : [];

if ($clientId <= 0) {
    respondError('client_id requerido', 400);
}

$validTypes = ['entrenamiento', 'nutricion', 'habitos', 'suplementacion'];
$planTypes = array_values(array_intersect($planTypes, $validTypes));
if (empty($planTypes)) {
    respondError('Selecciona al menos un tipo de plan', 400);
}

// Rate limit
if (!ai_check_rate_limit()) {
    respondError('Límite de generaciones por hora alcanzado. Espera unos minutos.', 429);
}

// ── Load client profile ─────────────────────────────────────
try {
    $clientProfile = get_client_for_ai($clientId);
} catch (\RuntimeException $e) {
    respondError($e->getMessage(), 404);
}

// Load intake data from client_profiles
$stmtIntake = $db->prepare("SELECT intake_data FROM client_profiles WHERE client_id = ?");
$stmtIntake->execute([$clientId]);
$intakeRow = $stmtIntake->fetch(PDO::FETCH_ASSOC);
$intake = $intakeRow ? json_decode($intakeRow['intake_data'] ?? 'null', true) : null;

// ── Build client context text ───────────────────────────────
$clientText = build_client_profile_text($clientProfile);

// Enrich with intake data
if ($intake && is_array($intake)) {
    $clientText .= "\nDATOS DEL FORMULARIO INICIAL (INTAKE):\n";

    $intakeFields = [
        'genero' => 'Género', 'edad' => 'Edad', 'peso' => 'Peso (kg)',
        'talla' => 'Talla (cm)', 'objetivo' => 'Objetivo principal',
        'experiencia' => 'Experiencia', 'dias_entrenamiento' => 'Días/semana',
        'equipamiento' => 'Equipamiento', 'inicio_semana' => 'Preferencia inicio semana',
        'horario' => 'Horario', 'duracion_sesion' => 'Duración sesión',
        'rutina_actual' => 'Rutina actual', 'tiene_lesion' => 'Tiene lesión',
        'lesion_detalle' => 'Detalle lesión', 'num_comidas' => 'Comidas/día',
        'dieta_actual' => 'Tipo de dieta', 'dia_tipico' => 'Día típico de comida',
        'agua' => 'Consumo de agua', 'alergias' => 'Alergias',
        'alimentos_no' => 'Alimentos que no come', 'suplementos_actuales' => 'Suplementos actuales',
        'come_fuera' => 'Come fuera', 'horario_trabajo' => 'Tipo de trabajo',
        'sueno' => 'Horas de sueño', 'estres' => 'Nivel de estrés',
        'expectativas' => 'Expectativas', 'notas' => 'Notas adicionales',
    ];

    foreach ($intakeFields as $key => $label) {
        $val = trim((string) ($intake[$key] ?? ''));
        if ($val !== '') {
            $clientText .= "- $label: $val\n";
        }
    }

    // RISE-format intake (nested objects)
    if (!empty($intake['measurements']) && is_array($intake['measurements'])) {
        $clientText .= "\nMEDIDAS CORPORALES:\n";
        foreach ($intake['measurements'] as $k => $v) {
            if ($v) $clientText .= "- $k: $v\n";
        }
    }
    if (!empty($intake['training']) && is_array($intake['training'])) {
        $clientText .= "\nENTRENAMIENTO (RISE intake):\n";
        foreach ($intake['training'] as $k => $v) {
            $clientText .= "- $k: " . (is_array($v) ? implode(', ', $v) : $v) . "\n";
        }
    }
    if (!empty($intake['nutrition']) && is_array($intake['nutrition'])) {
        $clientText .= "\nNUTRICIÓN (RISE intake):\n";
        foreach ($intake['nutrition'] as $k => $v) {
            $clientText .= "- $k: " . (is_array($v) ? implode(', ', $v) : $v) . "\n";
        }
    }
    if (!empty($intake['lifestyle']) && is_array($intake['lifestyle'])) {
        $clientText .= "\nESTILO DE VIDA (RISE intake):\n";
        foreach ($intake['lifestyle'] as $k => $v) {
            $clientText .= "- $k: $v\n";
        }
    }
}

// ── Generate each plan type ─────────────────────────────────
$results = [];
$totalInputTokens = 0;
$totalOutputTokens = 0;
$model = CLAUDE_MODEL; // Sonnet for quality

foreach ($planTypes as $type) {
    $systemPrompt = buildSystemPrompt($type);
    $userPrompt = buildUserPrompt($type, $clientText, $coachNotes, $methodology, $trainingConfig, $nutritionConfig);

    $aiGenerated = false;
    $plan = null;
    $aiError = null;

    try {
        $aiResult = claude_call($systemPrompt, $userPrompt, $model, 8000);
        $totalInputTokens += $aiResult['input_tokens'];
        $totalOutputTokens += $aiResult['output_tokens'];

        $parsed = extract_json_from_response($aiResult['text']);
        if (is_array($parsed) && !empty($parsed)) {
            $plan = $parsed;
            $aiGenerated = true;
        } else {
            $aiError = 'JSON inválido en respuesta de Claude';
            error_log("[WellCore] generate-plans: JSON parse failed for type=$type client=$clientId");
        }
    } catch (\RuntimeException $e) {
        $aiError = $e->getMessage();
        error_log("[WellCore] generate-plans: Claude error type=$type client=$clientId: $aiError");
    }

    if (!$aiGenerated || !$plan) {
        $results[$type] = [
            'generated' => false,
            'error' => $aiError ?? 'No se pudo generar el plan',
        ];
        continue;
    }

    // Save to ai_generations
    $genId = ai_save_generation([
        'client_id' => $clientId,
        'type' => $type,
        'prompt_tokens' => $aiResult['input_tokens'],
        'completion_tokens' => $aiResult['output_tokens'],
        'status' => 'completed',
        'raw_response' => $aiResult['text'],
        'parsed_json' => json_encode($plan, JSON_UNESCAPED_UNICODE),
    ]);

    // Save to assigned_plans (active=0 — pending review)
    $planId = ai_save_plan($clientId, $type, $plan, $genId);

    // Update ai_generation with coach_notes for record
    if ($coachNotes) {
        try {
            $db->prepare("UPDATE ai_generations SET coach_notes = ? WHERE id = ?")->execute([$coachNotes, $genId]);
        } catch (\Throwable $ignored) {}
    }

    $results[$type] = [
        'generated' => true,
        'plan_id' => $planId,
        'generation_id' => $genId,
        'preview' => $plan,
    ];
}

$totalCost = ai_calc_cost($totalInputTokens, $totalOutputTokens);

respond([
    'plans' => $results,
    'client_id' => $clientId,
    'client_name' => $clientProfile['name'] ?? '',
    'model' => $model,
    'input_tokens' => $totalInputTokens,
    'output_tokens' => $totalOutputTokens,
    'estimated_cost_usd' => $totalCost,
]);

// ══════════════════════════════════════════════════════════════
// PROMPT BUILDERS
// ══════════════════════════════════════════════════════════════

function buildSystemPrompt(string $type): string {
    $base = "Eres un coach de fitness y nutrición de élite con más de 15 años de experiencia. Trabajas para WellCore Fitness, una empresa de coaching personalizado basado en ciencia. ";

    return match($type) {
        'entrenamiento' => $base . "Tu especialidad es diseñar programas de entrenamiento periodizados, seguros y efectivos. Respetas las restricciones físicas del cliente y adaptas el volumen/intensidad a su nivel. Usas nomenclatura estándar de ejercicios en español.",

        'nutricion' => $base . "Tu especialidad es diseñar planes nutricionales personalizados basados en evidencia. Calculas macronutrientes según el objetivo del cliente, respetas sus alergias e intolerancias, y creas planes prácticos y sostenibles. Usas alimentos comunes en Latinoamérica.",

        'habitos' => $base . "Tu especialidad es diseñar protocolos de hábitos saludables basados en evidencia. Incluyes sueño, hidratación, pasos diarios, manejo de estrés, y hábitos de recuperación. Los hábitos deben ser progresivos y alcanzables.",

        'suplementacion' => $base . "Tu especialidad es recomendar protocolos de suplementación basados en evidencia científica (ISSN position stands). Solo recomiendas suplementos con evidencia fuerte (Tier A/B). Incluyes dosis, timing, y justificación científica.",

        default => $base,
    };
}

function buildUserPrompt(string $type, string $clientText, string $coachNotes, string $methodology, array $trainingConfig, array $nutritionConfig): string {
    $prompt = "$clientText\n";

    if ($coachNotes) {
        $prompt .= "\n⚠️ NOTAS DEL COACH (SEGUIR OBLIGATORIAMENTE):\n$coachNotes\n";
    }

    if ($methodology) {
        $prompt .= "\nMETODOLOGÍA SELECCIONADA: $methodology\n";
    }

    $prompt .= "\n---\n\n";

    return match($type) {
        'entrenamiento' => $prompt . buildTrainingPrompt($trainingConfig),
        'nutricion'     => $prompt . buildNutritionPrompt($nutritionConfig),
        'habitos'       => $prompt . buildHabitsPrompt(),
        'suplementacion'=> $prompt . buildSupplementPrompt(),
        default         => $prompt,
    };
}

function buildTrainingPrompt(array $config): string {
    $weeks = (int) ($config['weeks'] ?? 4);
    $sessions = (int) ($config['sessions_per_week'] ?? 4);
    $split = $config['split'] ?? '';

    $p  = "Genera un plan de ENTRENAMIENTO personalizado para este cliente.\n\n";
    $p .= "Configuración:\n";
    $p .= "- Semanas: $weeks\n";
    $p .= "- Sesiones por semana: $sessions\n";
    if ($split) $p .= "- Split preferido: $split\n";
    $p .= "\nPara cada ejercicio incluye: nombre, series, repeticiones (o tiempo), descanso, RPE/RIR, y notas técnicas.\n";
    $p .= "Incluye calentamiento y vuelta a la calma cuando sea apropiado.\n";
    $p .= "Aplica progresión de volumen/intensidad entre semanas (incluye deload si hay 4+ semanas).\n\n";
    $p .= "Retorna SOLO JSON válido con esta estructura (sin texto antes ni después):\n";
    $p .= <<<'JSON'
{
  "title": "Nombre del programa",
  "methodology": "Metodología aplicada",
  "weeks": N,
  "sessions_per_week": N,
  "plan": [
    {
      "week": 1,
      "phase": "Nombre de la fase",
      "notes": "Instrucciones de la semana",
      "days": [
        {
          "day": "Día 1",
          "focus": "Grupo muscular / enfoque",
          "warmup": "Descripción del calentamiento",
          "exercises": [
            {
              "name": "Nombre del ejercicio",
              "sets": 4,
              "reps": "8-10",
              "rest": "90s",
              "rpe": "7-8",
              "notes": "Notas técnicas"
            }
          ],
          "cooldown": "Vuelta a la calma"
        }
      ]
    }
  ],
  "progressions": "Cómo progresar semana a semana",
  "notes": "Notas generales del programa"
}
JSON;
    return $p;
}

function buildNutritionPrompt(array $config): string {
    $approach = $config['approach'] ?? '';
    $goal = $config['caloric_goal'] ?? '';

    $p  = "Genera un plan de NUTRICIÓN personalizado para este cliente.\n\n";
    if ($approach) $p .= "Enfoque nutricional: $approach\n";
    if ($goal) $p .= "Objetivo calórico: $goal\n";
    $p .= "\nCalcula TDEE estimado basado en los datos del cliente.\n";
    $p .= "Ajusta calorías según objetivo (deficit/superavit/mantenimiento).\n";
    $p .= "Distribuye macronutrientes (proteína mínimo 1.6g/kg para hipertrofia).\n";
    $p .= "Crea un menú diario con 2-3 variantes por comida.\n";
    $p .= "Usa alimentos comunes en Colombia/Latinoamérica.\n";
    $p .= "Respeta alergias, intolerancias y preferencias del cliente.\n\n";
    $p .= "Retorna SOLO JSON válido con esta estructura:\n";
    $p .= <<<'JSON'
{
  "title": "Plan Nutricional Personalizado",
  "approach": "Enfoque utilizado",
  "calories_target": 2200,
  "macros": {
    "protein_g": 180,
    "carbs_g": 220,
    "fat_g": 70,
    "fiber_g": 30
  },
  "tdee_estimated": 2500,
  "caloric_adjustment": "-300 (deficit moderado)",
  "meals": [
    {
      "meal": "Desayuno",
      "time": "7:00 AM",
      "options": [
        {
          "name": "Opción 1",
          "foods": [
            {"food": "Huevos revueltos", "portion": "3 unidades", "calories": 210, "protein": 18, "carbs": 2, "fat": 15}
          ],
          "total_calories": 450
        }
      ]
    }
  ],
  "hydration": "Protocolo de hidratación",
  "timing": "Recomendaciones de timing nutricional",
  "supplements_suggested": ["Creatina 5g/día", "Proteína whey post-entreno"],
  "weekly_adjustments": "Cómo ajustar según progreso",
  "notes": "Notas importantes"
}
JSON;
    return $p;
}

function buildHabitsPrompt(): string {
    $p  = "Genera un protocolo de HÁBITOS SALUDABLES personalizado para este cliente.\n\n";
    $p .= "Incluye hábitos diarios progresivos en estas categorías:\n";
    $p .= "- Sueño (higiene del sueño, horarios)\n";
    $p .= "- Hidratación (objetivo diario, estrategias)\n";
    $p .= "- Movimiento no-ejercicio (pasos, NEAT)\n";
    $p .= "- Manejo de estrés (técnicas prácticas)\n";
    $p .= "- Recuperación (stretching, foam rolling, etc.)\n";
    $p .= "- Hábitos nutricionales (meal prep, registro, etc.)\n\n";
    $p .= "Retorna SOLO JSON válido:\n";
    $p .= <<<'JSON'
{
  "title": "Protocolo de Hábitos",
  "habits": [
    {
      "category": "Sueño",
      "icon": "moon",
      "daily_target": "7-8 horas",
      "actions": [
        {"habit": "Apagar pantallas 30min antes de dormir", "frequency": "diario", "priority": "alta"},
        {"habit": "Horario fijo de sueño", "frequency": "diario", "priority": "alta"}
      ],
      "progression": "Semana 1: horario fijo. Semana 2: rutina pre-sueño. Semana 3: optimizar ambiente."
    }
  ],
  "weekly_checklist": ["Dormir 7h+", "Beber 2L+ agua", "8000+ pasos", "5min meditación"],
  "notes": "Notas del protocolo"
}
JSON;
    return $p;
}

function buildSupplementPrompt(): string {
    $p  = "Genera un protocolo de SUPLEMENTACIÓN personalizado basado en evidencia científica.\n\n";
    $p .= "REGLAS:\n";
    $p .= "- Solo incluye suplementos con evidencia Tier A o B (ISSN position stands).\n";
    $p .= "- Incluye dosis exacta, timing, y referencia científica breve.\n";
    $p .= "- Marca como 'esencial' o 'opcional'.\n";
    $p .= "- Respeta condiciones médicas y medicamentos del cliente.\n\n";
    $p .= "Retorna SOLO JSON válido:\n";
    $p .= <<<'JSON'
{
  "title": "Protocolo de Suplementación",
  "supplements": [
    {
      "name": "Creatina Monohidrato",
      "dose": "5g/día",
      "timing": "Cualquier momento del día, con comida",
      "evidence_tier": "A",
      "purpose": "Fuerza, potencia, masa muscular",
      "scientific_note": "ISSN 2017: mejora rendimiento en ejercicio de alta intensidad",
      "priority": "esencial"
    }
  ],
  "total_monthly_cost_estimate": "$30-50 USD",
  "warnings": "Contraindicaciones o precauciones",
  "notes": "Notas generales"
}
JSON;
    return $p;
}
