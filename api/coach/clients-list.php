<?php
// GET /api/coach/clients-list — lista simple id/name/plan para mensajes
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$coach = authenticateCoach();
$db    = getDB();

$stmt = $db->prepare(
    "SELECT id, name, plan, status FROM clients WHERE coach_id = ? ORDER BY name ASC"
);
$stmt->execute([$coach['id']]);
respond(['clients' => $stmt->fetchAll()]);
