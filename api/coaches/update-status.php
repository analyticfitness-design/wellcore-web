<?php
/**
 * WellCore Fitness — Coach Application Status Update (Admin)
 * PUT /api/coaches/update-status.php
 * Body JSON: {"id": "CAP-xxx", "status": "approved|rejected|pending", "notes": "optional"}
 * Requires: Bearer admin token
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('PUT');
authenticateAdmin();

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) respondError('JSON inválido', 400);

$VALID_STATUSES = ['pending', 'approved', 'rejected'];
$id     = trim($body['id']     ?? '');
$status = trim($body['status'] ?? '');
$notes  = trim($body['notes']  ?? '');

if (!$id)                                      respondError('id es requerido', 400);
if (!in_array($status, $VALID_STATUSES, true)) respondError('status debe ser uno de: ' . implode(', ', $VALID_STATUSES), 400);

// ─── MySQL (primario) ─────────────────────────────────────────────────────────
try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM coach_applications WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) respondError("Aplicación '{$id}' no encontrada", 404);

    $stmt = $db->prepare("UPDATE coach_applications SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $notes ?: null, $id]);

    respond(['ok' => true, 'id' => $id, 'status' => $status]);

} catch (\Exception $e) {
    error_log('[WellCore] coaches/update-status DB error: ' . $e->getMessage());
}

// ─── Fallback JSON ────────────────────────────────────────────────────────────
$ROOT  = dirname(__DIR__, 2);
$file  = $ROOT . '/api/data/coach-applications.json';
$apps  = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
$idx   = -1;

foreach ($apps as $i => $app) {
    if ($app['id'] === $id) { $idx = $i; break; }
}

if ($idx === -1) respondError("Aplicación '{$id}' no encontrada", 404);

$apps[$idx]['status']     = $status;
$apps[$idx]['updated_at'] = date('c');
if ($notes !== '') $apps[$idx]['admin_notes'] = $notes;

file_put_contents($file, json_encode($apps, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
respond(['ok' => true, 'id' => $id, 'status' => $status]);
