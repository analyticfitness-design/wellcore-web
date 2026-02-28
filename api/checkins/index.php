<?php
// GET /api/checkins?limit=8  → history
// POST /api/checkins          → submit check-in

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST');
$client = authenticateClient();
requirePlan($client, 'elite');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = min((int)($_GET['limit'] ?? 8), 52);
    $stmt = $db->prepare("
        SELECT id, week_label, checkin_date, bienestar, dias_entrenados,
               nutricion, comentario, coach_reply, replied_at, created_at
        FROM checkins WHERE client_id = ?
        ORDER BY checkin_date DESC LIMIT ?
    ");
    $stmt->execute([$client['id'], $limit]);
    respond(['checkins' => $stmt->fetchAll()]);
}

// POST
$body = getJsonBody();
$week = $body['week']      ?? (date('o') . '-W' . date('W'));
$date = $body['date']      ?? date('Y-m-d');
$bien = (int)($body['bienestar'] ?? 5);
$dias = (int)($body['dias']      ?? 0);
$nutr = $body['nutricion'] ?? 'Parcial';
$com  = $body['comentario'] ?? '';

if ($bien < 1 || $bien > 10) {
    respondError('Bienestar debe ser 1-10', 422);
}
if (!in_array($nutr, ['Si', 'No', 'Parcial'])) {
    respondError('Nutrición inválida', 422);
}

$stmt = $db->prepare("
    INSERT INTO checkins (client_id, week_label, checkin_date, bienestar, dias_entrenados, nutricion, comentario)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        bienestar       = VALUES(bienestar),
        dias_entrenados = VALUES(dias_entrenados),
        nutricion       = VALUES(nutricion),
        comentario      = VALUES(comentario)
");
$stmt->execute([$client['id'], $week, $date, $bien, $dias, $nutr, $com]);

$checkinId = $db->lastInsertId();
notifyN8nCheckin($client['id'], (int) $checkinId);

respond(['message' => 'Check-in enviado al coach'], 201);

function notifyN8nCheckin(int $clientId, int $checkinId): void {
    $h = curl_init('http://localhost:5678/webhook/checkin');
    curl_setopt($h, CURLOPT_POST, true);
    curl_setopt($h, CURLOPT_POSTFIELDS, json_encode(['client_id' => $clientId, 'checkin_id' => $checkinId]));
    curl_setopt($h, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($h, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($h, CURLOPT_TIMEOUT_MS, 500);
    @curl_exec($h);
    curl_close($h);
}
