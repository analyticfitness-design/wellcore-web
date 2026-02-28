<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Generador de Programas de Entrenamiento con IA
 * ============================================================
 * POST /api/ai/generate
 *
 * Genera un programa de entrenamiento personalizado para un cliente
 * usando Claude AI. Guarda el resultado en assigned_plans con
 * estado 'pending_review' para aprobación del coach.
 *
 * Auth:  Bearer token de admin
 * Body:  { client_id: int, triggered_by?: string }
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$admin     = authenticateAdmin();
$body      = getJsonBody();
$clientId  = (int) ($body['client_id']    ?? 0);
$triggeredBy = trim($body['triggered_by'] ?? 'admin');

if (!$clientId) respondError('client_id requerido', 422);
if (!ai_check_rate_limit()) {
    respondError('Rate limit alcanzado. Máximo ' . AI_RATE_LIMIT_PER_HOUR . ' generaciones por hora.', 429);
}

// ── Cargar perfil del cliente ─────────────────────────────────
try {
    $client = get_client_for_ai($clientId);
} catch (\RuntimeException $e) {
    respondError($e->getMessage(), 404);
}

// ── Obtener prompt personalizado (o usar default) ─────────────
$customPrompt = get_ai_prompt('entrenamiento');

// ── SYSTEM PROMPT — Expertise del coach ──────────────────────
$systemPrompt = $customPrompt['system_prompt'] ?: <<<'SYSTEM'
Eres un entrenador de alto rendimiento y científico del ejercicio con 15 años de experiencia.
Trabajas para WellCore Fitness, coaching premium basado en ciencia.

PRINCIPIOS CIENTÍFICOS QUE DEBES APLICAR SIEMPRE:
- Sobrecarga progresiva: incremento de 2-5% en carga o 1-2 reps cada semana
- Volumen semanal basado en evidencia: 10-20 series efectivas por grupo muscular
- Gestión de fatiga con RIR (Reps In Reserve): semana 1 RIR 3, semana 2 RIR 2, semana 3 RIR 1, semana 4 deload RIR 4
- Especificidad: el entrenamiento debe coincidir exactamente con el objetivo del cliente
- Recuperación: mínimo 48h entre sesiones del mismo grupo muscular
- Tempo controlado en ejercicios de aislamiento: 3-0-1 (excéntrico-pausa-concéntrico)
- Adaptar TODO a lesiones y restricciones declaradas — nunca ignorarlas

PERIODIZACIÓN DEL BLOQUE DE 4 SEMANAS:
- Semana 1: Acumulación ligera (RIR 3, RPE 7) — aprender movimientos
- Semana 2: Acumulación moderada (RIR 2, RPE 8)
- Semana 3: Intensificación (RIR 1, RPE 9) — máximo esfuerzo
- Semana 4: Deload activo (50% volumen, RIR 4) — recuperación

CALIDAD DEL PROGRAMA:
- Ejercicios compuestos primero, aislamiento al final
- Incluir calentamiento específico recomendado por sesión
- Grupos musculares secundarios que activa cada ejercicio
- Notas técnicas claras para ejercicios complejos
- El programa debe poder ejecutarse sin coach presente

FORMATO: JSON estricto. Sin texto fuera del JSON.
SYSTEM;

// ── USER PROMPT — Datos específicos del cliente ───────────────
$profileText = build_client_profile_text($client);

$jsonSchema = json_encode([
    'semanas'           => 4,
    'dias_por_semana'   => 4,
    'objetivo_principal' => 'Descripción del objetivo adaptado al cliente',
    'principios_clave'  => ['principio 1', 'principio 2', 'principio 3'],
    'dias' => [
        [
            'dia'    => 1,
            'nombre' => 'Empuje — Pecho / Hombros / Tríceps',
            'calentamiento' => '5 min movilidad hombros + 2 series ligeras de press',
            'ejercicios' => [
                [
                    'nombre'         => 'Press de Banca con Barra',
                    'patron_motor'   => 'Empuje horizontal',
                    'musculos_prim'  => ['Pectoral mayor'],
                    'musculos_sec'   => ['Deltoides anterior', 'Tríceps'],
                    'series'         => 4,
                    'reps'           => '8-10',
                    'descanso'       => '90s',
                    'rir_semana'     => [3, 2, 1, 4],
                    'tempo'          => '3-0-1',
                    'notas'          => 'Bajar controlado 3s. Codos a 45°. No rebotar en el pecho.',
                ],
            ],
        ],
    ],
    'progresion_semanal' => 'Instrucciones de progresión semana a semana',
    'deload_semana4'     => 'Protocolo deload: reducir volumen 50%, misma frecuencia',
    'notas_coach'        => 'Observaciones importantes. Puntos de atención para el cliente.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$userPrompt  = "Genera un programa de entrenamiento de 4 semanas para el siguiente cliente.\n\n";
$userPrompt .= $profileText;
$userPrompt .= "\n\nESTRUCTURA EXACTA DE RESPUESTA (JSON):\n";
$userPrompt .= $jsonSchema;
$userPrompt .= "\n\nADAPTA el número de días y la selección de ejercicios al lugar de entrenamiento y equipamiento disponible del cliente. Si tiene lesiones, sustituye los ejercicios que las afecten por alternativas seguras y anótalo en notas_coach.";

// ── Registrar generación como pending ────────────────────────
$genId = ai_save_generation([
    'client_id' => $clientId,
    'type'      => 'entrenamiento',
    'status'    => 'pending',
]);

// ── Llamar a Claude API ───────────────────────────────────────
try {
    $result    = claude_call($systemPrompt, $userPrompt);
    $parsed    = extract_json_from_response($result['text']);
    $cost      = ai_calc_cost($result['input_tokens'], $result['output_tokens']);

    // Guardar plan en assigned_plans
    if ($parsed) {
        ai_save_plan($clientId, 'entrenamiento', $parsed, $genId);
    }

    // Actualizar registro de generación
    ai_update_generation(
        $genId,
        'completed',
        $result['text'],
        $parsed ? json_encode($parsed, JSON_UNESCAPED_UNICODE) : null
    );

    respond([
        'ok'            => true,
        'generation_id' => $genId,
        'client'        => ['id' => $clientId, 'name' => $client['name']],
        'programa'      => $parsed,
        'tokens'        => [
            'input'   => $result['input_tokens'],
            'output'  => $result['output_tokens'],
            'total'   => $result['input_tokens'] + $result['output_tokens'],
            'costo_usd' => $cost,
        ],
        'status'  => 'pending_review',
        'message' => 'Programa generado. Pendiente de revisión y aprobación del coach.',
    ], 201);

} catch (\Exception $e) {
    ai_update_generation($genId, 'failed', $e->getMessage());
    error_log('[WellCore AI] generate error: ' . $e->getMessage());
    respondError('Error generando programa. Intenta de nuevo.', 500);
}
