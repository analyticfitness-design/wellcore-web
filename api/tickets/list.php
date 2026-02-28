<?php
/**
 * WellCore Fitness — Listar Tickets
 * GET /api/tickets/list.php[?status=open|closed|in_progress][&coach_id=XXX]
 * Requires: Bearer admin token
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$admin = authenticateAdmin();

function ok(array $data): void              { respond(array_merge(['ok' => true], $data)); }
function err(string $msg, int $c = 400): void { respondError($msg, $c); }

$VALID_STATUSES = ['open', 'closed', 'in_progress'];

$coachId = trim($_GET['coach_id'] ?? '');
$status  = trim($_GET['status']   ?? '');

if ($status !== '' && !in_array($status, $VALID_STATUSES, true)) err('status debe ser uno de: ' . implode(', ', $VALID_STATUSES));

// ─── MySQL (primario) ─────────────────────────────────────────────────────────
try {
    $db = getDB();

    $conditions = [];
    $params     = [];

    if ($coachId !== '') {
        $conditions[] = "coach_id = ?";
        $params[]     = $coachId;
    }
    if ($status !== '') {
        $conditions[] = "status = ?";
        $params[]     = $status;
    }

    $sql  = "SELECT * FROM tickets";
    if ($conditions) $sql .= " WHERE " . implode(' AND ', $conditions);
    $sql .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    ok(['count' => count($tickets), 'data' => $tickets]);

} catch (\Exception $e) {
    error_log('[WellCore] tickets/list DB error: ' . $e->getMessage());
}

// ─── Fallback JSON ────────────────────────────────────────────────────────────
$file    = __DIR__ . '/../data/tickets.json';
$tickets = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

if ($coachId !== '') $tickets = array_values(array_filter($tickets, fn($t) => ($t['coach_id'] ?? '') === $coachId));
if ($status !== '') $tickets = array_values(array_filter($tickets, fn($t) => ($t['status']   ?? '') === $status));

usort($tickets, fn($a, $b) => strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'));
ok(['count' => count($tickets), 'data' => $tickets]);
