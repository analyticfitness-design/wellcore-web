<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Auto-Trigger de IA
 * ============================================================
 * Ejecutado por cron job o por webhooks internos.
 * NO exponer al público — protegido por secret.
 *
 * USOS:
 *   1. Procesar cola de generaciones pendientes (queued)
 *   2. Generar programa inicial al pagar (invocado por webhook Wompi)
 *   3. Análisis semanal automático de métricas
 *   4. Detección de estancamiento y ajuste
 *
 * LLAMADA DIRECTA (cron):
 *   php /var/www/html/api/ai/auto-trigger.php action=process_queue
 *   php /var/www/html/api/ai/auto-trigger.php action=weekly_analysis
 *
 * LLAMADA HTTP (Wompi webhook interna):
 *   POST /api/ai/auto-trigger.php
 *   Header: X-Internal-Secret: wc-ai-internal-2026
 *   Body: { action: "new_client", client_id: 123, plan: "metodo" }
 * ============================================================
 */

// Detectar si se ejecuta desde CLI o HTTP
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');

    // Verificar secret interno
    $secret = $_SERVER['HTTP_X_INTERNAL_SECRET'] ?? '';
    define('AI_INTERNAL_SECRET', 'wc-ai-internal-2026'); // Cambiar en producción
    if ($secret !== AI_INTERNAL_SECRET) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

require_once __DIR__ . '/helpers.php';

// Configurar tiempo máximo para cron jobs
if ($isCli) {
    set_time_limit(0);
    ini_set('memory_limit', '256M');
}

// ── Obtener acción ────────────────────────────────────────────
if ($isCli) {
    parse_str(implode('&', array_slice($argv ?? [], 1)), $cliArgs);
    $action   = $cliArgs['action']    ?? 'process_queue';
    $clientId = (int) ($cliArgs['client_id'] ?? 0);
    $plan     = $cliArgs['plan']      ?? '';
} else {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $action   = $body['action']    ?? 'process_queue';
    $clientId = (int) ($body['client_id'] ?? 0);
    $plan     = $body['plan']      ?? '';
}

$db = getDB();

// ────────────────────────────────────────────────────────────
// ACCIÓN: new_client — Generar programa inicial tras pago
// Invocado por webhook.php de Wompi cuando pago es APPROVED
// ────────────────────────────────────────────────────────────
if ($action === 'new_client') {
    if (!$clientId) {
        ai_respond_or_log('error', 'client_id requerido para new_client', $isCli);
        exit;
    }

    ai_log("Nuevo cliente ID $clientId (plan: $plan) — encolando generación inicial");

    // Encolar: entrenamiento + nutricion + habitos
    $types = ['entrenamiento', 'nutricion', 'habitos'];
    $queued = [];

    foreach ($types as $type) {
        try {
            $genId = ai_save_generation([
                'client_id' => $clientId,
                'type'      => $type,
                'status'    => 'queued',
            ]);
            $queued[] = ['type' => $type, 'gen_id' => $genId];
            ai_log("Encolado: $type (gen_id: $genId) para cliente $clientId");
        } catch (\Throwable $e) {
            ai_log("Error encolando $type: " . $e->getMessage(), 'ERROR');
        }
    }

    ai_respond_or_log('ok', "Encoladas " . count($queued) . " generaciones para cliente $clientId", $isCli, [
        'queued' => $queued,
    ]);
    exit;
}

// ────────────────────────────────────────────────────────────
// ACCIÓN: process_queue — Procesar generaciones encoladas
// Ejecutar con cron cada 5 minutos
// ────────────────────────────────────────────────────────────
if ($action === 'process_queue') {
    ai_log("Iniciando proceso de cola de generaciones");

    // Obtener generaciones en cola (máx 5 por ejecución para no saturar)
    $stmt = $db->prepare("
        SELECT id, client_id, type, ticket_id
        FROM ai_generations
        WHERE status = 'queued'
        ORDER BY created_at ASC
        LIMIT 5
    ");
    $stmt->execute();
    $queued = $stmt->fetchAll();

    if (empty($queued)) {
        ai_log("Cola vacía — sin generaciones pendientes");
        ai_respond_or_log('ok', 'Cola vacía', $isCli);
        exit;
    }

    ai_log("Procesando " . count($queued) . " generaciones encoladas");
    $results = [];

    foreach ($queued as $gen) {
        $genId    = (int) $gen['id'];
        $gClientId = (int) $gen['client_id'];
        $type     = $gen['type'];

        // Marcar como pending (procesando)
        ai_update_generation($genId, 'pending');

        try {
            $client      = get_client_for_ai($gClientId);
            $profileText = build_client_profile_text($client);

            switch ($type) {
                case 'entrenamiento':
                    $result = ai_generate_entrenamiento($client, $profileText, $genId);
                    break;
                case 'nutricion':
                    $result = ai_generate_nutricion($client, $profileText, $genId);
                    break;
                case 'habitos':
                    $result = ai_generate_habitos($client, $profileText, $genId);
                    break;
                case 'ticket_response':
                    // Delegar a ticket-response.php via llamada interna
                    if ($gen['ticket_id']) {
                        $result = ai_trigger_ticket_response($gen['ticket_id'], $gClientId);
                    } else {
                        $result = ['status' => 'skip', 'reason' => 'Sin ticket_id'];
                    }
                    break;
                default:
                    $result = ['status' => 'skip', 'reason' => "Tipo desconocido: $type"];
            }

            $results[] = array_merge(['gen_id' => $genId, 'type' => $type], $result);
            ai_log("Completado gen_id=$genId tipo=$type status=" . ($result['status'] ?? '?'));

        } catch (\Throwable $e) {
            ai_update_generation($genId, 'failed', $e->getMessage());
            $results[] = ['gen_id' => $genId, 'type' => $type, 'status' => 'error', 'error' => $e->getMessage()];
            ai_log("Error gen_id=$genId: " . $e->getMessage(), 'ERROR');
        }
    }

    ai_respond_or_log('ok', 'Cola procesada', $isCli, ['results' => $results]);
    exit;
}

// ────────────────────────────────────────────────────────────
// ACCIÓN: weekly_analysis — Análisis semanal de todos los clientes
// Ejecutar con cron cada domingo
// ────────────────────────────────────────────────────────────
if ($action === 'weekly_analysis') {
    ai_log("Iniciando análisis semanal de progreso");

    // Obtener clientes activos con métricas recientes
    $stmt = $db->prepare("
        SELECT DISTINCT c.id, c.name
        FROM clients c
        JOIN client_metrics cm ON cm.client_id = c.id
        WHERE c.status = 'activo'
          AND cm.fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        LIMIT 20
    ");
    $stmt->execute();
    $clients = $stmt->fetchAll();

    if (empty($clients)) {
        ai_log("Sin clientes con métricas recientes para analizar");
        ai_respond_or_log('ok', 'Sin clientes para analizar', $isCli);
        exit;
    }

    $queued = 0;
    foreach ($clients as $c) {
        // Verificar si ya se analizó esta semana
        $check = $db->prepare("
            SELECT id FROM ai_generations
            WHERE client_id = ? AND type = 'analisis'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            LIMIT 1
        ");
        $check->execute([$c['id']]);
        if ($check->fetchColumn()) continue; // ya analizado

        ai_save_generation([
            'client_id' => $c['id'],
            'type'      => 'analisis',
            'status'    => 'queued',
        ]);
        $queued++;
    }

    ai_log("Encolados $queued análisis de progreso semanal");
    ai_respond_or_log('ok', "Encolados $queued análisis", $isCli, ['total_clientes' => count($clients), 'encolados' => $queued]);
    exit;
}

// ────────────────────────────────────────────────────────────
// ACCIÓN: check_stagnation — Detectar estancamiento
// ────────────────────────────────────────────────────────────
if ($action === 'check_stagnation') {
    ai_log("Verificando clientes estancados");

    // Clientes con 3+ semanas sin cambio de peso significativo (±0.3kg)
    $stmt = $db->prepare("
        SELECT c.id, c.name,
               MAX(m.peso) - MIN(m.peso) as variacion,
               COUNT(m.id) as registros
        FROM clients c
        JOIN client_metrics m ON m.client_id = c.id
        WHERE c.status = 'activo'
          AND m.fecha >= DATE_SUB(NOW(), INTERVAL 21 DAY)
        GROUP BY c.id, c.name
        HAVING registros >= 3 AND ABS(variacion) < 0.5
    ");
    $stmt->execute();
    $stagnados = $stmt->fetchAll();

    foreach ($stagnados as $s) {
        ai_log("Estancamiento detectado: cliente ID {$s['id']} ({$s['name']}) — variación {$s['variacion']}kg en 3 semanas");

        // Encolar análisis de urgencia si no hay uno reciente
        $check = $db->prepare("
            SELECT id FROM ai_generations
            WHERE client_id = ? AND type = 'analisis'
              AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
            LIMIT 1
        ");
        $check->execute([$s['id']]);
        if (!$check->fetchColumn()) {
            ai_save_generation([
                'client_id' => $s['id'],
                'type'      => 'analisis',
                'status'    => 'queued',
            ]);
        }
    }

    ai_respond_or_log('ok', count($stagnados) . ' clientes estancados detectados', $isCli, [
        'estancados' => array_map(fn($s) => ['id' => $s['id'], 'nombre' => $s['name']], $stagnados),
    ]);
    exit;
}

// ────────────────────────────────────────────────────────────
// FUNCIONES INTERNAS DE GENERACIÓN
// ────────────────────────────────────────────────────────────

function ai_generate_entrenamiento(array $client, string $profileText, int $genId): array {
    $sysPrompt = "Eres un entrenador de alto rendimiento de WellCore Fitness. Genera un programa de entrenamiento de 4 semanas basado en ciencia. Devuelve JSON estricto.";

    $userPrompt  = "Genera un programa de entrenamiento de 4 semanas.\n\n";
    $userPrompt .= $profileText;
    $userPrompt .= "\n\nDevuelve JSON con: semanas, dias_por_semana, objetivo_principal, dias (array con ejercicios), progresion_semanal, notas_coach.";

    $result = claude_call($sysPrompt, $userPrompt);
    $parsed = extract_json_from_response($result['text']);

    if ($parsed) {
        ai_save_plan($client['id'], 'entrenamiento', $parsed, $genId);
    }

    ai_update_generation($genId, 'completed', $result['text'], $parsed ? json_encode($parsed) : null);

    return [
        'status' => 'completed',
        'tokens' => $result['input_tokens'] + $result['output_tokens'],
        'costo'  => ai_calc_cost($result['input_tokens'], $result['output_tokens']),
    ];
}

function ai_generate_nutricion(array $client, string $profileText, int $genId): array {
    $sysPrompt = "Eres un nutricionista deportivo de WellCore Fitness. Genera un plan nutricional completo con macros y comidas detalladas. Devuelve JSON estricto.";

    $peso = (float) ($client['peso'] ?: 75);
    $proteina = round($peso * 2.0);

    $userPrompt  = "Genera un plan nutricional completo.\n\n";
    $userPrompt .= $profileText;
    $userPrompt .= "\n\nProteína mínima: {$proteina}g/día. Devuelve JSON con: calorias_objetivo, macros, comidas (array con alimentos y gramos), suplementacion, notas_coach.";

    $result = claude_call($sysPrompt, $userPrompt);
    $parsed = extract_json_from_response($result['text']);

    if ($parsed) {
        ai_save_plan($client['id'], 'nutricion', $parsed, $genId);
    }

    ai_update_generation($genId, 'completed', $result['text'], $parsed ? json_encode($parsed) : null);

    return [
        'status' => 'completed',
        'tokens' => $result['input_tokens'] + $result['output_tokens'],
        'costo'  => ai_calc_cost($result['input_tokens'], $result['output_tokens']),
    ];
}

function ai_generate_habitos(array $client, string $profileText, int $genId): array {
    $sysPrompt = "Eres un coach de bienestar de WellCore Fitness. Genera un plan de hábitos progresivo de 4 semanas. Devuelve JSON estricto.";

    $userPrompt  = "Genera un plan de hábitos de 4 semanas.\n\n";
    $userPrompt .= $profileText;
    $userPrompt .= "\n\nDevuelve JSON con: pilares (array de hábitos por área), rutina_manana, rutina_noche, seguimiento_semanal, notas_coach.";

    $result = claude_call($sysPrompt, $userPrompt);
    $parsed = extract_json_from_response($result['text']);

    if ($parsed) {
        ai_save_plan($client['id'], 'habitos', $parsed, $genId);
    }

    ai_update_generation($genId, 'completed', $result['text'], $parsed ? json_encode($parsed) : null);

    return [
        'status' => 'completed',
        'tokens' => $result['input_tokens'] + $result['output_tokens'],
        'costo'  => ai_calc_cost($result['input_tokens'], $result['output_tokens']),
    ];
}

function ai_trigger_ticket_response(string $ticketId, int $clientId): array {
    // Llamar a ticket-response.php de forma interna via require
    // Se hace así para mantener el contexto de DB ya conectado
    if (!defined('AI_INTERNAL_SECRET')) define('AI_INTERNAL_SECRET', 'wc-ai-internal-2026');

    $db   = getDB();
    $stmt = $db->prepare("SELECT id, ticket_type, description, client_name FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        return ['status' => 'error', 'reason' => "Ticket $ticketId no encontrado"];
    }

    $profileText = '';
    if ($clientId) {
        try {
            $client      = get_client_for_ai($clientId);
            $profileText = build_client_profile_text($client);
        } catch (\Throwable $e) {
            $profileText = "Cliente: " . ($ticket['client_name'] ?? 'N/A') . "\n";
        }
    }

    $sysPrompt = "Eres el equipo de WellCore Fitness. Redacta una respuesta completa al ticket. Directa, técnica y útil. El coach revisará antes de enviar.";
    $userPrompt = "Ticket: {$ticket['ticket_type']}\n\n{$profileText}\n\nDescripción:\n{$ticket['description']}\n\nGenera la respuesta completa.";

    $result = claude_call($sysPrompt, $userPrompt);

    try {
        $db->prepare("UPDATE tickets SET ai_draft = ?, ai_status = 'ready' WHERE id = ?"
        )->execute([$result['text'], $ticketId]);
    } catch (\Throwable $e) {}

    return [
        'status' => 'completed',
        'tokens' => $result['input_tokens'] + $result['output_tokens'],
    ];
}

// ────────────────────────────────────────────────────────────
// UTILIDADES DE LOG Y RESPUESTA
// ────────────────────────────────────────────────────────────

function ai_log(string $msg, string $level = 'INFO'): void {
    $logFile = __DIR__ . '/../../api/wompi/logs/ai-trigger.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents(
        $logFile,
        sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $msg),
        FILE_APPEND | LOCK_EX
    );
    if (php_sapi_name() === 'cli') {
        echo "[$level] $msg\n";
    }
}

function ai_respond_or_log(string $status, string $message, bool $isCli, array $extra = []): void {
    if ($isCli) {
        ai_log("$status: $message");
        if (!empty($extra)) ai_log(json_encode($extra, JSON_UNESCAPED_UNICODE));
    } else {
        $code = $status === 'ok' ? 200 : 400;
        http_response_code($code);
        echo json_encode(array_merge(
            ['ok' => $status === 'ok', 'message' => $message],
            $extra
        ), JSON_UNESCAPED_UNICODE);
    }
}

// Acción desconocida
ai_respond_or_log('error', "Acción desconocida: $action. Usar: new_client, process_queue, weekly_analysis, check_stagnation", $isCli);
