<?php
// GET /api/metrics?limit=8  → history
// POST /api/metrics          → save new entry

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST');
$client = authenticateClient();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = min((int)($_GET['limit'] ?? 10), 50);
    $stmt = $db->prepare("
        SELECT id, log_date, peso, porcentaje_musculo, porcentaje_grasa, notas, created_at
        FROM metrics WHERE client_id = ?
        ORDER BY log_date DESC LIMIT ?
    ");
    $stmt->execute([$client['id'], $limit]);
    respond(['metrics' => $stmt->fetchAll()]);
}

// POST
$body    = getJsonBody();
$date    = $body['date']    ?? date('Y-m-d');
$peso    = isset($body['peso'])    ? (float)$body['peso']    : null;
$musculo = isset($body['musculo']) ? (float)$body['musculo'] : null;
$grasa   = isset($body['grasa'])   ? (float)$body['grasa']   : null;
$notas   = $body['notas'] ?? null;

if (!$peso && !$musculo && !$grasa) {
    respondError('Proporciona al menos un valor (peso, musculo o grasa)', 422);
}

$stmt = $db->prepare("
    INSERT INTO metrics (client_id, log_date, peso, porcentaje_musculo, porcentaje_grasa, notas)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        peso               = COALESCE(VALUES(peso), peso),
        porcentaje_musculo = COALESCE(VALUES(porcentaje_musculo), porcentaje_musculo),
        porcentaje_grasa   = COALESCE(VALUES(porcentaje_grasa), porcentaje_grasa),
        notas              = COALESCE(VALUES(notas), notas)
");
$stmt->execute([$client['id'], $date, $peso, $musculo, $grasa, $notas]);

respond(['message' => 'Métricas guardadas', 'date' => $date], 201);
