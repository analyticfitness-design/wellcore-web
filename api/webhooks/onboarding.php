<?php
declare(strict_types=1);
/**
 * WellCore Fitness — F3: Webhook Onboarding Automatico
 * ============================================================
 * GET /api/webhooks/onboarding?secret=WC_WEBHOOK_2026
 *
 * Devuelve nuevos clientes de las ultimas 24h para que n8n
 * dispare: email bienvenida, asignacion coach, notificacion.
 * ============================================================
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('GET');

$secret = $_GET['secret'] ?? '';
if ($secret !== 'WC_WEBHOOK_2026') {
    respondError('Unauthorized', 401);
}

$db = getDB();
$hours = (int) ($_GET['hours'] ?? 24);
if ($hours < 1 || $hours > 168) $hours = 24;

$stmt = $db->prepare("
    SELECT c.id, c.client_code, c.name, c.email, c.plan, c.status, c.fecha_inicio,
           p.whatsapp, p.ciudad, p.objetivo, p.nivel, p.dias_disponibles
    FROM clients c
    LEFT JOIN client_profiles p ON p.client_id = c.id
    WHERE c.fecha_inicio >= DATE_SUB(NOW(), INTERVAL ? HOUR)
    AND c.status = 'activo'
    ORDER BY c.fecha_inicio DESC
");
$stmt->execute([$hours]);
$newClients = $stmt->fetchAll();

// Log webhook call
$logStmt = $db->prepare("INSERT INTO webhook_logs (webhook_type, payload, status, created_at) VALUES ('onboarding', ?, 'success', NOW())");
$logStmt->execute([json_encode(['hours' => $hours, 'count' => count($newClients)])]);

respond([
    'ok'      => true,
    'count'   => count($newClients),
    'clients' => $newClients,
    'webhook' => 'onboarding',
]);
