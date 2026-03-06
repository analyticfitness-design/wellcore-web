<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
// WellCore RISE — Tickets de cliente
// POST /api/rise/ticket.php        crear ticket
// GET  /api/rise/ticket.php        listar tickets propios
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') { http_response_code(204); exit; }

$client = authenticateClient();
$db     = getDB();

// ─── GET: listar tickets propios ─────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $db->prepare("
        SELECT id, ticket_type, description, priority, status, response,
               deadline, created_at, updated_at
        FROM tickets
        WHERE client_id = ? AND source = 'rise'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$client['id']]);
    respond(['ok' => true, 'tickets' => $stmt->fetchAll()]);
}

if ($method !== 'POST') { respondError('Método no permitido', 405); }

// ─── POST: crear ticket ───────────────────────────────────────────────────────
$body = getJsonBody();

$ticketType  = trim($body['ticket_type']  ?? '');
$description = htmlspecialchars(trim($body['description']  ?? ''), ENT_QUOTES, 'UTF-8');
$priority    = trim($body['priority']     ?? 'normal');

$validTypes = ['ajuste_entrenamiento','consulta_nutricion','problema_acceso','solicitud_especial','otro'];
if (!in_array($ticketType, $validTypes, true)) {
    respondError('Tipo inválido. Opciones: ' . implode(', ', $validTypes));
}
if (strlen($description) < 20) {
    respondError('Descripción demasiada corta (mínimo 20 caracteres)');
}
if (!in_array($priority, ['normal','alta'], true)) $priority = 'normal';

// Rate limit: max 3 tickets abiertos al mismo tiempo
$countStmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE client_id = ? AND source = 'rise' AND status != 'closed'");
$countStmt->execute([$client['id']]);
$openCount = (int) $countStmt->fetchColumn();
if ($openCount >= 3) {
    respondError('Tienes 3 tickets abiertos. Espera respuesta antes de enviar otro.', 429);
}

$rand     = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
$ticketId = 'RISE-' . (int)(microtime(true) * 1000) . '-' . $rand;
$deadline = date('Y-m-d H:i:s', time() + 72 * 3600);

$stmt = $db->prepare("
    INSERT INTO tickets (id, source, client_id, coach_id, client_name, client_plan,
                         ticket_type, description, priority, status, deadline, created_at)
    VALUES (?, 'rise', ?, NULL, ?, 'rise', ?, ?, ?, 'open', ?, NOW())
");
$stmt->execute([
    $ticketId,
    $client['id'],
    $client['name'] ?? ($client['client_name'] ?? 'Cliente RISE'),
    $ticketType,
    $description,
    $priority,
    $deadline,
]);

respond([
    'ok'        => true,
    'ticket_id' => $ticketId,
    'message'   => 'Ticket enviado. Tu coach responderá en máximo 72 horas.',
    'deadline'  => $deadline,
]);
