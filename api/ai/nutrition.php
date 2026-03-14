<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Generador de Plan Nutricional con IA
 * ============================================================
 * POST /api/ai/nutrition
 *
 * Auth:  Bearer token de admin
 * Body:  { client_id: int, objetivo_calorico?: int }
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$admin    = authenticateAdmin();
$body     = getJsonBody();
$clientId = (int) ($body['client_id'] ?? 0);

if (!$clientId) respondError('client_id requerido', 422);
if (!ai_check_rate_limit()) respondError('Rate limit alcanzado.', 429);

try {
    $client = get_client_for_ai($clientId);
} catch (\RuntimeException $e) {
    respondError($e->getMessage(), 404);
}

$customPrompt = get_ai_prompt('nutricion');

$systemPrompt = $customPrompt['system_prompt'] ?: <<<'SYSTEM'
Eres un nutricionista deportivo certificado con especialización en composición corporal y rendimiento atlético.
Trabajas para WellCore Fitness, plataforma de coaching premium basada en ciencia.

METODOLOGÍA OBLIGATORIA:
1. Calcular TDEE usando Mifflin-St Jeor + factor de actividad según días de entrenamiento
2. Ajuste calórico según objetivo: déficit -300 a -500 kcal (pérdida grasa) | superávit +200 a +300 kcal (volumen)
3. Distribución de macros basada en evidencia:
   - Proteína: 1.6-2.2g/kg de peso corporal (prioridad absoluta)
   - Grasas: 0.8-1.2g/kg (mínimo 20% de calorías totales)
   - Carbohidratos: resto de calorías
4. Distribución en 4-5 comidas según preferencias del cliente
5. Timing nutricional: mayor CHO pre y post entrenamiento
6. Considerar SIEMPRE alergias, restricciones dietéticas y alimentos que no come el cliente
7. Alimentos concretos con gramos exactos — no "una porción de pollo"

CALIDAD:
- Comidas realistas que alguien pueda preparar en 20-30 minutos
- Alternativas de cada alimento principal (para variedad)
- Suplementación básica recomendada si aplica (creatina, proteína, vitamina D)
- Hidratación: mínimo 35ml/kg de peso

FORMATO: JSON estricto. Sin texto fuera del JSON.
SYSTEM;

$profileText = build_client_profile_text($client);

// Calcular TDEE base para incluirlo en el prompt
$peso   = (float) ($client['peso']   ?: 75);
$altura = (float) ($client['altura'] ?: 170);
$edad   = (int)   ($client['edad']   ?: 30);
$diasEnt = count($client['dias_disponibles']) ?: 3;

// Mifflin-St Jeor aproximado (asumiendo masculino si no hay datos)
$bmr     = 10 * $peso + 6.25 * $altura - 5 * $edad + 5;
$factors = [2 => 1.375, 3 => 1.55, 4 => 1.725, 5 => 1.725, 6 => 1.9];
$factor  = $factors[min($diasEnt, 6)] ?? 1.55;
$tdee    = round($bmr * $factor);

$jsonSchema = json_encode([
    'tdee_estimado'    => $tdee,
    'calorias_objetivo'=> $tdee - 350,
    'proteina_g'       => round($peso * 2.0),
    'carbohidratos_g'  => 220,
    'grasas_g'         => 65,
    'hidratacion_ml'   => round($peso * 35),
    'comidas' => [
        [
            'numero'  => 1,
            'nombre'  => 'Desayuno 07:00',
            'momento' => 'Antes del entrenamiento si entrena de mañana',
            'alimentos' => [
                [
                    'nombre'    => 'Avena en hojuelas',
                    'gramos'    => 80,
                    'alternativa' => 'Pan integral (90g)',
                    'proteina'  => 10,
                    'carbs'     => 54,
                    'grasas'    => 4,
                    'calorias'  => 296,
                ],
            ],
            'totales' => ['calorias' => 480, 'proteina' => 38, 'carbs' => 48, 'grasas' => 12],
        ],
    ],
    'suplementacion' => [
        ['suplemento' => 'Creatina monohidrato', 'dosis' => '5g/día', 'momento' => 'Cualquier hora, consistencia diaria'],
    ],
    'notas_coach' => 'Observaciones importantes para el cliente y el coach.',
    'ajustes_progreso' => 'Si en 2 semanas el peso no cambia ±0.5kg/semana, ajustar calorías ±150kcal.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$userPrompt  = "Crea un plan nutricional completo y detallado para el siguiente cliente.\n\n";
$userPrompt .= $profileText;
$userPrompt .= "\nTDEE estimado por Mifflin-St Jeor: aproximadamente $tdee kcal/día\n";
$userPrompt .= "\n\nESTRUCTURA EXACTA DE RESPUESTA (JSON):\n";
$userPrompt .= $jsonSchema;
$userPrompt .= "\n\nGenera todas las comidas del día con alimentos reales, gramos exactos y macros por comida. Verifica que los totales cuadren con los macros objetivo. Respeta TODAS las restricciones alimentarias del cliente.";

$genId = ai_save_generation([
    'client_id' => $clientId,
    'type'      => 'nutricion',
    'status'    => 'pending',
]);

try {
    $result = claude_call($systemPrompt, $userPrompt);
    $parsed = extract_json_from_response($result['text']);
    $cost   = ai_calc_cost($result['input_tokens'], $result['output_tokens']);

    if ($parsed) {
        ai_save_plan($clientId, 'nutricion', $parsed, $genId);
    }

    ai_update_generation(
        $genId, 'completed',
        $result['text'],
        $parsed ? json_encode($parsed, JSON_UNESCAPED_UNICODE) : null
    );

    respond([
        'ok'            => true,
        'generation_id' => $genId,
        'client'        => ['id' => $clientId, 'name' => $client['name']],
        'plan_nutricional' => $parsed,
        'tokens'        => [
            'input'     => $result['input_tokens'],
            'output'    => $result['output_tokens'],
            'costo_usd' => $cost,
        ],
        'status'  => 'pending_review',
        'message' => 'Plan nutricional generado. Pendiente de revisión del coach.',
    ], 201);

} catch (\Exception $e) {
    ai_update_generation($genId, 'failed', $e->getMessage());
    error_log('[WellCore AI] nutrition error: ' . $e->getMessage());
    respondError('Error generando plan nutricional. Intenta de nuevo.', 500);
}
