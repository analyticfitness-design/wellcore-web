<?php
/**
 * GET /api/coach/clients.php
 * Returns the authenticated coach's assigned clients with activity stats.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');

$coach = authenticateCoach();
$coachId = (int) $coach['id'];

$db = getDB();

$stmt = $db->prepare("
    SELECT
        c.id,
        c.client_code,
        c.name,
        c.email,
        c.plan,
        c.status,
        c.fecha_inicio,
        cp.edad,
        cp.peso,
        cp.altura,
        cp.objetivo,
        cp.ciudad,
        cp.whatsapp,
        cp.nivel,
        cp.lugar_entreno,
        (SELECT MAX(created_at) FROM training_logs WHERE client_id = c.id) AS last_activity,
        (SELECT COUNT(*) FROM training_logs WHERE client_id = c.id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS sessions_last_7d
    FROM clients c
    LEFT JOIN client_profiles cp ON cp.client_id = c.id
    WHERE c.coach_id = ?
    ORDER BY c.status ASC, c.name ASC
");
$stmt->execute([$coachId]);
$clients = $stmt->fetchAll();

// Cast numeric fields
foreach ($clients as &$cl) {
    $cl['id'] = (int) $cl['id'];
    $cl['sessions_last_7d'] = (int) $cl['sessions_last_7d'];
    if ($cl['edad'] !== null) $cl['edad'] = (int) $cl['edad'];
    if ($cl['peso'] !== null) $cl['peso'] = (float) $cl['peso'];
    if ($cl['altura'] !== null) $cl['altura'] = (float) $cl['altura'];
}
unset($cl);

respond([
    'ok'      => true,
    'count'   => count($clients),
    'clients' => $clients
]);
