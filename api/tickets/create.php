<?php
/**
 * WellCore Fitness — Crear Ticket
 * POST /api/tickets/create.php
 *
 * Body JSON esperado:
 *   coach_id (required), coach_name, client_name (required), client_plan,
 *   ticket_type (required), description (required, min 20 chars),
 *   priority, token (required = WellCoreCoach2026)
 */

require_once __DIR__ . '/../includes/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ok(array $data): void  { respond(200, array_merge(['ok' => true],  $data)); }
function err(string $msg, int $code = 400): void { respond($code, ['ok' => false, 'error' => $msg]); }

// ─── Constants ────────────────────────────────────────────────────────────────
$COACH_TOKEN = getenv('COACH_TOKEN') ?: '';
const SLA_HOURS     = 48;
const VALID_TYPES   = ['rutina_nueva', 'cambio_rutina', 'nutricion', 'habitos', 'invitacion_cliente', 'otro'];
const VALID_PLANS   = ['esencial', 'metodo', 'elite'];
const VALID_PRIORITIES = ['normal', 'alta'];

// ─── Parse body ───────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    err('Cuerpo JSON inválido o vacío');
}

// ─── Validar token ────────────────────────────────────────────────────────────
if (!$COACH_TOKEN || ($body['token'] ?? '') !== $COACH_TOKEN) {
    err('Token inválido', 401);
}

// ─── Validar campos requeridos ────────────────────────────────────────────────
$coachId    = trim($body['coach_id']    ?? '');
$clientName = trim($body['client_name'] ?? '');
$ticketType = trim($body['ticket_type'] ?? '');
$description = trim($body['description'] ?? '');

if ($coachId === '') {
    err('El campo coach_id es requerido');
}
if ($clientName === '') {
    err('El campo client_name es requerido');
}
if (!in_array($ticketType, VALID_TYPES, true)) {
    err('ticket_type debe ser uno de: ' . implode(', ', VALID_TYPES));
}
if (strlen($description) < 20) {
    err('El campo description debe tener al menos 20 caracteres');
}

// ─── Campos opcionales con defaults ──────────────────────────────────────────
$coachName  = trim($body['coach_name']  ?? '');
$clientPlan = trim($body['client_plan'] ?? '');
if ($clientPlan !== '' && !in_array($clientPlan, VALID_PLANS, true)) {
    err('client_plan debe ser uno de: ' . implode(', ', VALID_PLANS));
}

$priority = trim($body['priority'] ?? 'normal');
if (!in_array($priority, VALID_PRIORITIES, true)) {
    $priority = 'normal';
}

// ─── Generar ID único ─────────────────────────────────────────────────────────
$timestampMs = (int)(microtime(true) * 1000);
$rand        = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
$ticketId    = "TKT-{$timestampMs}-{$rand}";

// ─── Calcular deadline (created_at + 48h) ─────────────────────────────────────
$createdAt = date('c');
$deadline  = date('c', time() + SLA_HOURS * 3600);

// ─── Construir ticket ─────────────────────────────────────────────────────────
$ticket = [
    'id'          => $ticketId,
    'status'      => 'open',
    'created_at'  => $createdAt,
    'deadline'    => $deadline,
    'sla_hours'   => SLA_HOURS,
    'assigned_to' => null,
    'coach_id'    => $coachId,
    'coach_name'  => $coachName,
    'client_name' => $clientName,
    'client_plan' => $clientPlan,
    'ticket_type' => $ticketType,
    'description' => $description,
    'priority'    => $priority,
    'response'    => null,
];

// ─── Guardar en MySQL (primario) + JSON (fallback) ────────────────────────────
$savedToDb = false;
try {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();
    $stmt = $db->prepare("
        INSERT IGNORE INTO tickets (id, coach_id, coach_name, client_name, client_plan, ticket_type, description, priority, deadline)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $ticket['id'],
        $ticket['coach_id'],
        $coachName ?: null,
        $ticket['client_name'],
        $clientPlan ?: null,
        $ticket['ticket_type'],
        $ticket['description'],
        $ticket['priority'],
        date('Y-m-d H:i:s', time() + SLA_HOURS * 3600),
    ]);
    $savedToDb = true;
} catch (\Exception $e) {
    error_log('[WellCore] tickets DB error: ' . $e->getMessage());
}

if (!$savedToDb) {
    $dataDir     = __DIR__ . '/../data';
    $ticketsFile = $dataDir . '/tickets.json';
    if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
    $tickets = file_exists($ticketsFile) ? (json_decode(file_get_contents($ticketsFile), true) ?: []) : [];
    $tickets[] = $ticket;
    file_put_contents($ticketsFile, json_encode($tickets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ─── Auto-respuesta IA (fire-and-forget, no bloquea la respuesta) ─────────────
if ($savedToDb) {
    try {
        require_once __DIR__ . '/../config/ai.php';
        require_once __DIR__ . '/../ai/helpers.php';

        if (AI_ENABLED) {
            // Encolar borrador IA para este ticket
            $clientIdForAi = (int) ($body['client_id'] ?? 0);
            ai_save_generation([
                'client_id' => $clientIdForAi ?: null,
                'type'      => 'ticket_response',
                'ticket_id' => $ticketId,
                'status'    => 'queued',
            ]);

            // Marcar ticket como pendiente de respuesta IA
            try {
                $db->prepare("UPDATE tickets SET ai_status = 'pending' WHERE id = ?")
                   ->execute([$ticketId]);
            } catch (\Throwable $ignore) {}
        }
    } catch (\Throwable $aiErr) {
        error_log('[WellCore AI] ticket hook error: ' . $aiErr->getMessage());
        // No bloquear la creación del ticket
    }
}

// ─── Respuesta ────────────────────────────────────────────────────────────────
ok([
    'ticket_id'  => $ticketId,
    'message'    => 'Ticket creado. El equipo WellCore responderá en 48 horas.',
    'deadline'   => $deadline,
    'ai_draft'   => AI_ENABLED ? 'Borrador IA generándose en segundo plano.' : null,
]);
