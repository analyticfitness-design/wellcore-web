<?php
declare(strict_types=1);
/**
 * RISE Tracking Diario
 * GET  /api/rise/tracking         → historial del cliente (últimos 30 días)
 * POST /api/rise/tracking         → guardar registro del día
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST');
$client = authenticateClient();
$db     = getDB();
$cid    = (int)$client['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT * FROM rise_tracking
        WHERE client_id = ?
        ORDER BY log_date DESC
        LIMIT 30
    ");
    $stmt->execute([$cid]);
    respond(['tracking' => $stmt->fetchAll()]);
}

// POST — guardar
$body = getJsonBody();
$date          = $body['date']           ?? date('Y-m-d');
$trainingDone  = (int)(bool)($body['training_done']  ?? false);
$nutritionDone = (int)(bool)($body['nutrition_done'] ?? false);
$water         = (float)($body['water_liters'] ?? 0);
$sleep         = (float)($body['sleep_hours']  ?? 0);
$note          = substr(trim($body['note'] ?? ''), 0, 500);

$stmt = $db->prepare("
    INSERT INTO rise_tracking (client_id, log_date, training_done, nutrition_done, water_liters, sleep_hours, note)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        training_done  = VALUES(training_done),
        nutrition_done = VALUES(nutrition_done),
        water_liters   = VALUES(water_liters),
        sleep_hours    = VALUES(sleep_hours),
        note           = VALUES(note),
        updated_at     = NOW()
");
$stmt->execute([$cid, $date, $trainingDone, $nutritionDone, $water, $sleep, $note]);

respond(['ok' => true, 'message' => 'Registro guardado']);
