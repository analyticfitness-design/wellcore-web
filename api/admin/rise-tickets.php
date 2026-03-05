<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
// WellCore Admin — RISE Tickets
// GET  /api/admin/rise-tickets.php           listar todos
// POST /api/admin/rise-tickets.php           responder/actualizar status
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') { http_response_code(204); exit; }

requireRole(['admin', 'superadmin', 'jefe']);
$db = getDB();

// ─── GET: listar tickets RISE ─────────────────────────────────────────────────
if ($method === 'GET') {
    $status = $_GET['status'] ?? 'all';
    $where  = "source = 'rise'";
    if (in_array($status, ['open','in_progress','closed'], true)) {
        $where .= " AND status = " . $db->quote($status);
    }

    $stmt = $db->query("
        SELECT t.id, t.client_id, t.client_name, t.ticket_type, t.description,
               t.priority, t.status, t.response, t.deadline, t.created_at, t.updated_at,
               TIMESTAMPDIFF(HOUR, NOW(), t.deadline) AS hours_left
        FROM tickets t
        WHERE {$where}
        ORDER BY
            CASE t.status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END,
            t.priority DESC, t.created_at ASC
        LIMIT 100
    ");
    $tickets = $stmt->fetchAll();

    // Stats rápidas
    $stats = $db->query("
        SELECT
            SUM(source='rise' AND status='open')        AS open,
            SUM(source='rise' AND status='in_progress') AS in_progress,
            SUM(source='rise' AND status='closed')      AS closed,
            SUM(source='rise' AND status!='closed' AND deadline < NOW()) AS overdue
        FROM tickets
    ")->fetch();

    respond(['ok' => true, 'tickets' => $tickets, 'stats' => $stats]);
}

// ─── POST: responder / cambiar status ─────────────────────────────────────────
if ($method === 'POST') {
    $body     = getJsonBody();
    $ticketId = trim($body['ticket_id'] ?? '');
    $status   = trim($body['status']    ?? '');
    $response = trim($body['response']  ?? '');

    if (!$ticketId) respondError('ticket_id requerido');

    // Verificar que es ticket RISE
    $t = $db->prepare("SELECT id FROM tickets WHERE id = ? AND source = 'rise'");
    $t->execute([$ticketId]);
    if (!$t->fetch()) respondError('Ticket no encontrado', 404);

    $fields  = [];
    $params  = [];

    if ($response !== '') {
        $fields[] = 'response = ?';
        $params[]  = $response;
        // Al responder, cerrar automáticamente si no se especifica status
        if (!$status) $status = 'closed';
        $fields[] = 'resolved_at = NOW()';
    }

    if (in_array($status, ['open','in_progress','closed'], true)) {
        $fields[] = 'status = ?';
        $params[]  = $status;
        if ($status === 'closed') $fields[] = 'resolved_at = COALESCE(resolved_at, NOW())';
    }

    if (empty($fields)) respondError('Nada que actualizar');

    $params[] = $ticketId;
    $db->prepare("UPDATE tickets SET " . implode(', ', $fields) . " WHERE id = ?")
       ->execute($params);

    respond(['ok' => true, 'message' => 'Ticket actualizado']);
}

respondError('Método no permitido', 405);
