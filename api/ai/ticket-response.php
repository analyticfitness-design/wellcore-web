<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Auto-respuesta de Tickets con IA
 * ============================================================
 * POST /api/ai/ticket-response
 *
 * Se dispara automáticamente al crear un ticket nuevo.
 * Genera un borrador completo (ai_draft) y lo guarda en la DB.
 * El coach revisa y aprueba con 1 click — nunca envía solo.
 *
 * Auth:  Bearer token de admin (o llamada interna sin auth via internal_secret)
 * Body:  { ticket_id: string, client_id?: int }
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');

// Autenticación: admin Bearer O secret interno para llamadas automáticas
$isInternal = false;
$internalSecret = defined('AI_INTERNAL_SECRET') ? AI_INTERNAL_SECRET : 'wc-ai-internal-2026';

$body = getJsonBody();

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($authHeader, 'Internal ')) {
    $secret = substr($authHeader, 9);
    if ($secret !== $internalSecret) {
        respondError('Internal secret inválido', 401);
    }
    $isInternal = true;
} else {
    authenticateAdmin(); // valida Bearer
}

$ticketId = trim($body['ticket_id'] ?? '');
$clientId = (int) ($body['client_id'] ?? 0);

if (!$ticketId) respondError('ticket_id requerido', 422);

// ── Cargar ticket desde DB ────────────────────────────────────
$db   = getDB();
$stmt = $db->prepare("
    SELECT id, ticket_type, description, client_name, client_plan, priority, coach_id
    FROM tickets WHERE id = ? LIMIT 1
");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) respondError("Ticket $ticketId no encontrado", 404);

// ── Cargar perfil del cliente (si tenemos client_id) ─────────
$client     = null;
$profileText = '';

if ($clientId) {
    try {
        $client      = get_client_for_ai($clientId);
        $profileText = build_client_profile_text($client);
    } catch (\RuntimeException $e) {
        // Continuar sin perfil detallado
        $profileText = "- Nombre:  " . ($ticket['client_name'] ?? 'Cliente') . "\n"
                     . "- Plan:    " . strtoupper($ticket['client_plan'] ?? 'esencial') . "\n";
    }
} else {
    $profileText = "- Nombre:  " . ($ticket['client_name'] ?? 'Cliente') . "\n"
                 . "- Plan:    " . strtoupper($ticket['client_plan'] ?? 'esencial') . "\n";
}

// ── Seleccionar prompt según tipo de ticket ───────────────────
$typeMap = [
    'rutina_nueva'       => 'Generar un programa de entrenamiento completo de 4 semanas.',
    'cambio_rutina'      => 'Ajustar el programa de entrenamiento existente según los cambios solicitados.',
    'nutricion'          => 'Crear o ajustar el plan nutricional del cliente.',
    'habitos'            => 'Diseñar un plan de hábitos y rutinas diarias.',
    'invitacion_cliente' => 'Redactar un mensaje de bienvenida personalizado y cálido.',
    'otro'              => 'Responder la consulta del cliente con información precisa y útil.',
];

$instruccion = $typeMap[$ticket['ticket_type']] ?? $typeMap['otro'];

$systemPrompt = <<<'SYSTEM'
Eres el equipo de WellCore Fitness respondiendo a un ticket de cliente.
Tu respuesta será revisada por un coach humano antes de enviarse — nunca llega directamente al cliente.

VOZ DE MARCA WELLCORE:
- Directa y técnica. Datos antes que promesas.
- Sin jerga vacía. Sin "increíble", "espectacular", "definitivamente".
- Confianza basada en ciencia, no en motivación genérica.
- Empática con el contexto real del cliente.

ESTRUCTURA DE RESPUESTA:
1. Reconocimiento breve del punto del cliente (1-2 líneas)
2. Solución concreta y detallada
3. Próximos pasos claros
4. Cierre WellCore (breve, sin clichés)

Si el ticket requiere un programa completo, generarlo en formato estructurado.
Si es una consulta, responder con exactitud técnica.
SYSTEM;

$userPrompt  = "TICKET ID: {$ticket['id']}\n";
$userPrompt .= "TIPO: {$ticket['ticket_type']}\n";
$userPrompt .= "PRIORIDAD: {$ticket['priority']}\n";
$userPrompt .= "DESCRIPCIÓN DEL TICKET:\n{$ticket['description']}\n\n";
$userPrompt .= "CLIENTE:\n$profileText\n";
$userPrompt .= "INSTRUCCIÓN: $instruccion\n\n";
$userPrompt .= "Genera la respuesta completa al ticket. Si se requiere un programa, incluirlo completo en formato JSON dentro de la respuesta.";

// ── Registrar generación ──────────────────────────────────────
$genId = ai_save_generation([
    'client_id' => $clientId ?: null,
    'type'      => 'ticket_response',
    'ticket_id' => $ticketId,
    'status'    => 'pending',
]);

// ── Llamar a Claude ───────────────────────────────────────────
try {
    $result  = claude_call($systemPrompt, $userPrompt);
    $draftText = $result['text'];
    $cost    = ai_calc_cost($result['input_tokens'], $result['output_tokens']);

    // Guardar borrador en el ticket
    try {
        $db->prepare("
            UPDATE tickets
            SET ai_draft = ?, ai_status = 'ready', ai_generation_id = ?
            WHERE id = ?
        ")->execute([$draftText, $genId, $ticketId]);
    } catch (\Throwable $e) {
        // Columnas AI no existen aún (setup no ejecutado)
        error_log('[WellCore AI] tickets sin columnas AI. Ejecuta /api/ai/setup-tables.php');
    }

    ai_update_generation($genId, 'completed', $draftText, null);

    respond([
        'ok'            => true,
        'generation_id' => $genId,
        'ticket_id'     => $ticketId,
        'draft'         => $draftText,
        'tokens'        => [
            'input'     => $result['input_tokens'],
            'output'    => $result['output_tokens'],
            'costo_usd' => $cost,
        ],
        'status'  => 'ready',
        'message' => 'Borrador generado. Pendiente de aprobación del coach.',
    ], 201);

} catch (\Exception $e) {
    ai_update_generation($genId, 'failed', $e->getMessage());
    try {
        $db->prepare("UPDATE tickets SET ai_status = 'none' WHERE id = ?")->execute([$ticketId]);
    } catch (\Throwable $ignore) {}
    error_log('[WellCore AI] ticket-response error: ' . $e->getMessage());
    respondError('Error generando respuesta. Intenta de nuevo.', 500);
}
