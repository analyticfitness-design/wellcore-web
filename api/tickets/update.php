<?php
/**
 * WellCore Fitness — Actualizar Ticket (Admin)
 * PUT /api/tickets/update.php
 * Body JSON: {"ticket_id":"TKT-xxx","status":"closed|in_progress|open","response":"...","assigned_to":"..."}
 * Requires: Bearer admin token
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('PUT');
authenticateAdmin();

function ok(array $data): void              { respond(array_merge(['ok' => true], $data)); }
function err(string $msg, int $c = 400): void { respondError($msg, $c); }

$VALID_STATUSES = ['open', 'closed', 'in_progress'];

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) err('Cuerpo JSON inválido o vacío');

$ticketId   = trim($body['ticket_id']   ?? '');
$newStatus  = trim($body['status']      ?? '');
$response   = isset($body['response'])    ? trim($body['response'])    : null;
$assignedTo = isset($body['assigned_to']) ? trim($body['assigned_to']) : null;

if ($ticketId === '')                              err('El campo ticket_id es requerido');
if (!in_array($newStatus, $VALID_STATUSES, true))   err('status debe ser uno de: ' . implode(', ', $VALID_STATUSES));

// ─── MySQL (primario) ─────────────────────────────────────────────────────────
try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    if (!$stmt->fetch()) err("Ticket '{$ticketId}' no encontrado", 404);

    $resolvedAt = ($newStatus === 'closed') ? date('Y-m-d H:i:s') : null;

    $stmt = $db->prepare("
        UPDATE tickets
        SET status = ?,
            response = COALESCE(?, response),
            assigned_to = COALESCE(?, assigned_to),
            resolved_at = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $newStatus,
        ($response   !== '' ? $response   : null),
        ($assignedTo !== '' ? $assignedTo : null),
        $resolvedAt,
        $ticketId,
    ]);

    ok([
        'ticket_id'  => $ticketId,
        'status'     => $newStatus,
        'updated_at' => date('c'),
        'message'    => "Ticket actualizado a '{$newStatus}'",
    ]);

} catch (\Exception $e) {
    error_log('[WellCore] tickets/update DB error: ' . $e->getMessage());
}

// ─── Fallback JSON ────────────────────────────────────────────────────────────
$file    = __DIR__ . '/../data/tickets.json';
if (!file_exists($file)) err('No se encontró el archivo de tickets', 404);

$tickets = json_decode(file_get_contents($file), true) ?: [];
$idx     = null;
foreach ($tickets as $i => $t) {
    if (($t['id'] ?? '') === $ticketId) { $idx = $i; break; }
}
if ($idx === null) err("Ticket '{$ticketId}' no encontrado", 404);

$tickets[$idx]['status']     = $newStatus;
$tickets[$idx]['updated_at'] = date('c');
if ($response !== null && $response !== '')   $tickets[$idx]['response']    = $response;
if ($assignedTo !== null && $assignedTo !== '') $tickets[$idx]['assigned_to'] = $assignedTo;
if ($newStatus === 'closed')                  $tickets[$idx]['resolved_at'] = date('c');

file_put_contents($file, json_encode($tickets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
ok(['ticket_id' => $ticketId, 'status' => $newStatus, 'updated_at' => $tickets[$idx]['updated_at'], 'message' => "Ticket actualizado a '{$newStatus}'"]);
