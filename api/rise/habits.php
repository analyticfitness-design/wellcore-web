<?php
declare(strict_types=1);
/**
 * RISE Hábitos Diarios
 * GET  /api/rise/habits            → hábitos de hoy + historial 7 días
 * POST /api/rise/habits            → guardar estado de hábitos del día
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
        SELECT * FROM rise_habits_log
        WHERE client_id = ?
        ORDER BY log_date DESC
        LIMIT 30
    ");
    $stmt->execute([$cid]);
    respond(['habits' => $stmt->fetchAll()]);
}

// POST
$body     = getJsonBody();
$date     = $body['date']        ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$habits   = $body['habits_json'] ?? (object)[];
$completed = (int)($body['completed'] ?? 0);

$habitsJson = json_encode($habits);

$stmt = $db->prepare("
    INSERT INTO rise_habits_log (client_id, log_date, habits_json, completed)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE habits_json = VALUES(habits_json), completed = VALUES(completed), updated_at = NOW()
");
$stmt->execute([$cid, $date, $habitsJson, $completed]);

respond(['ok' => true, 'message' => 'Hábitos guardados']);
