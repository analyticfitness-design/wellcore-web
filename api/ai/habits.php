<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Generador de Plan de Hábitos con IA
 * ============================================================
 * POST /api/ai/habits
 *
 * Auth:  Bearer token de admin
 * Body:  { client_id: int }
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

$customPrompt = get_ai_prompt('habitos');

$systemPrompt = $customPrompt['system_prompt'] ?: <<<'SYSTEM'
Eres un coach de bienestar integral especializado en cambio de comportamiento y psicología del hábito.
Trabajas para WellCore Fitness. Tu misión es diseñar planes de hábitos que los clientes REALMENTE puedan mantener.

PRINCIPIOS QUE GUÍAN TU TRABAJO:
1. Habit stacking: anclar nuevos hábitos a rutinas existentes del cliente
2. Mínimo viable primero: empezar con hábitos pequeños (2 minutos) que se amplían
3. Fricción reducida: eliminar barreras para los hábitos buenos
4. Señal → Rutina → Recompensa: diseñar el loop completo
5. Sueño como pilar: 7-9h es la base de todo lo demás
6. Recuperación activa: el descanso es parte del entrenamiento
7. Gestión del estrés: el cortisol crónico sabotea cualquier objetivo físico

CONTEXTO QUE DEFINES:
- Rutina de mañana: pre-entrenamiento, activación mental
- Rutina de noche: recuperación, sueño de calidad
- Hábitos de nutrición: más allá del plan de comidas (preparación, mindfulness)
- Bienestar mental: estrés, motivación, adherencia
- Métricas de seguimiento: qué medir y cómo (sin obsesionarse)

FORMATO: JSON estricto. Sin texto fuera del JSON.
SYSTEM;

$profileText = build_client_profile_text($client);

$jsonSchema = json_encode([
    'duracion_semanas' => 4,
    'objetivo_habitos' => 'Descripción del enfoque de hábitos para este cliente',
    'pilares' => [
        [
            'nombre'   => 'Sueño y Recuperación',
            'prioridad' => 1,
            'habitos'  => [
                [
                    'habito'       => 'Apagar pantallas 60 min antes de dormir',
                    'momento'      => 'Noche, 21:30',
                    'duracion'     => '5 min de configuración',
                    'ancla'        => 'Después de cenar',
                    'beneficio'    => 'Mejora calidad de sueño, recuperación muscular',
                    'como_hacerlo' => 'Activar modo No Molestar y dejar el teléfono en otra habitación',
                    'semana_inicio' => 1,
                ],
            ],
        ],
    ],
    'rutina_manana' => [
        'duracion_total' => '20 minutos',
        'pasos' => [
            ['hora' => '06:30', 'accion' => 'Vaso de agua (500ml) al despertar', 'duracion' => '1 min'],
        ],
    ],
    'rutina_noche' => [
        'duracion_total' => '15 minutos',
        'pasos' => [
            ['hora' => '21:30', 'accion' => 'Preparar ropa y mochila del día siguiente', 'duracion' => '5 min'],
        ],
    ],
    'seguimiento_semanal' => [
        'metricas' => ['Peso (misma hora, en ayunas)', 'Horas de sueño promedio', 'Nivel de energía 1-10'],
        'checkin'  => 'Cada domingo anotar 3 victorias de la semana y 1 área de mejora',
    ],
    'notas_coach' => 'Observaciones y prioridades para este cliente específico.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$userPrompt  = "Diseña un plan de hábitos de 4 semanas para el siguiente cliente.\n\n";
$userPrompt .= $profileText;
$userPrompt .= "\n\nESTRUCTURA EXACTA DE RESPUESTA (JSON):\n";
$userPrompt .= $jsonSchema;
$userPrompt .= "\n\nEl plan debe ser REALISTA para el contexto de vida del cliente. Considera su nivel de estrés, horario de trabajo y hábitos actuales. Introduce los hábitos de forma progresiva — semana 1 solo 2-3 hábitos clave, ampliando gradualmente.";

$genId = ai_save_generation([
    'client_id' => $clientId,
    'type'      => 'habitos',
    'status'    => 'pending',
]);

try {
    $result = claude_call($systemPrompt, $userPrompt);
    $parsed = extract_json_from_response($result['text']);
    $cost   = ai_calc_cost($result['input_tokens'], $result['output_tokens']);

    if ($parsed) {
        ai_save_plan($clientId, 'habitos', $parsed, $genId);
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
        'plan_habitos'  => $parsed,
        'tokens'        => [
            'input'     => $result['input_tokens'],
            'output'    => $result['output_tokens'],
            'costo_usd' => $cost,
        ],
        'status'  => 'pending_review',
        'message' => 'Plan de hábitos generado. Pendiente de revisión del coach.',
    ], 201);

} catch (\Exception $e) {
    ai_update_generation($genId, 'failed', $e->getMessage());
    error_log('[WellCore AI] habits error: ' . $e->getMessage());
    respondError('Error generando plan de hábitos. Intenta de nuevo.', 500);
}
