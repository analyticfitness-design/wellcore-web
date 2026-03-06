<?php
// GET /api/rise/get-enrollments.php
// Admin: retorna lista de inscritos al reto RISE
// Requiere token de admin válido

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$admin = authenticateAdmin();
$db = getDB();

// Traer inscritos RISE con datos del cliente
$stmt = $db->prepare("
    SELECT
        rp.id AS program_id,
        rp.client_id,
        c.name,
        c.email,
        rp.experience_level,
        rp.training_location,
        rp.gender,
        rp.start_date,
        rp.end_date,
        rp.status,
        rp.personalized_program IS NOT NULL AS has_intake,
        rp.created_at
    FROM rise_programs rp
    JOIN clients c ON c.id = rp.client_id
    ORDER BY rp.created_at DESC
");
$stmt->execute();
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total   = count($programs);
$active  = array_filter($programs, fn($p) => $p['status'] === 'active');
$pending = array_filter($programs, fn($p) => !$p['has_intake']);

respond([
    'total'          => $total,
    'active'         => count($active),
    'pending_intake' => count($pending),
    'revenue_usd'    => $total * 33,
    'programs'       => $programs
]);
?>
