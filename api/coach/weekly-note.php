<?php
/**
 * WellCore — Coach: Add Note to Weekly Summary
 * POST /api/coach/weekly-note.php
 * Body: { client_id: int, note: string, week_start?: string }
 */
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$admin = authenticateAdmin();
$body  = getJsonBody();

$clientId  = (int)($body['client_id'] ?? 0);
$note      = trim($body['note'] ?? '');
$weekStart = $body['week_start'] ?? date('Y-m-d', strtotime('monday this week'));

if (!$clientId) respondError('client_id requerido', 422);
if (!$note) respondError('note requerido', 422);
if (mb_strlen($note) > 1000) respondError('Nota demasiado larga (max 1000 chars)', 422);

$db = getDB();

// Verify client exists and belongs to this coach
$stmt = $db->prepare("SELECT id FROM clients WHERE id = ? AND coach_id = ?");
$stmt->execute([$clientId, $admin['id']]);
if (!$stmt->fetchColumn()) {
    // Allow superadmin/admin to write notes for any client
    if (!in_array($admin['role'], ['admin', 'superadmin'], true)) {
        respondError('Cliente no asignado a ti', 403);
    }
}

// Upsert note
$stmt = $db->prepare("
    UPDATE weekly_summaries SET coach_note = ? WHERE client_id = ? AND week_start = ?
");
$stmt->execute([$note, $clientId, $weekStart]);

if ($stmt->rowCount() === 0) {
    // No summary exists yet — create one with empty data
    $db->prepare("
        INSERT INTO weekly_summaries (client_id, week_start, data_json, coach_note, created_at)
        VALUES (?, ?, '{}', ?, NOW())
        ON DUPLICATE KEY UPDATE coach_note = ?
    ")->execute([$clientId, $weekStart, $note, $note]);
}

respond(['ok' => true, 'message' => 'Nota guardada']);
