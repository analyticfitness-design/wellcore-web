<?php
declare(strict_types=1);
/**
 * RISE Mediciones Corporales
 * GET  /api/rise/measurements     → historial de mediciones
 * POST /api/rise/measurements     → guardar medición
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
    $stmt = $db->prepare("SELECT * FROM rise_measurements WHERE client_id = ? ORDER BY log_date DESC LIMIT 20");
    $stmt->execute([$cid]);
    respond(['measurements' => $stmt->fetchAll()]);
}

// POST
$body     = getJsonBody();
$date     = $body['date']      ?? date('Y-m-d');
$weight   = isset($body['weight_kg']) ? (float)$body['weight_kg'] : null;
$chest    = isset($body['chest_cm'])  ? (float)$body['chest_cm']  : null;
$waist    = isset($body['waist_cm'])  ? (float)$body['waist_cm']  : null;
$hips     = isset($body['hips_cm'])   ? (float)$body['hips_cm']   : null;
$thigh    = isset($body['thigh_cm'])  ? (float)$body['thigh_cm']  : null;
$arm      = isset($body['arm_cm'])    ? (float)$body['arm_cm']    : null;
$notes    = substr(trim($body['notes'] ?? ''), 0, 500);

$stmt = $db->prepare("
    INSERT INTO rise_measurements (client_id, log_date, weight_kg, chest_cm, waist_cm, hips_cm, thigh_cm, arm_cm, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([$cid, $date, $weight, $chest, $waist, $hips, $thigh, $arm, $notes]);

respond(['ok' => true, 'id' => (int)$db->lastInsertId(), 'message' => 'Medición guardada']);
