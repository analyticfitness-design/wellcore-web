<?php
declare(strict_types=1);
/**
 * WellCore Fitness — F3: Webhook Check de Inactividad
 * ============================================================
 * GET /api/webhooks/inactivity-check?secret=WC_WEBHOOK_2026
 *
 * Devuelve clientes inactivos (sin login en X dias)
 * para que n8n envie mensajes de re-engagement.
 * ============================================================
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('GET');

$secret = $_GET['secret'] ?? '';
$expectedSecret = getenv('WEBHOOK_SECRET') ?: '';
if (!$expectedSecret || $secret !== $expectedSecret) {
    respondError('Unauthorized', 401);
}

$db = getDB();
$inactiveDays = (int) ($_GET['days'] ?? 7);
if ($inactiveDays < 3) $inactiveDays = 3;

// Clientes activos que no tienen token reciente (proxy de login)
$stmt = $db->prepare("
    SELECT c.id, c.client_code, c.name, c.email, c.plan,
           p.whatsapp, p.objetivo,
           MAX(t.created_at) as last_login,
           DATEDIFF(NOW(), COALESCE(MAX(t.created_at), c.fecha_inicio)) as days_inactive
    FROM clients c
    LEFT JOIN client_profiles p ON p.client_id = c.id
    LEFT JOIN auth_tokens t ON t.user_id = c.id AND t.user_type = 'client'
    WHERE c.status = 'activo'
    GROUP BY c.id
    HAVING days_inactive >= ?
    ORDER BY days_inactive DESC
");
$stmt->execute([$inactiveDays]);
$inactive = $stmt->fetchAll();

// Categorizar por urgencia
$categories = ['warning' => [], 'critical' => [], 'churn_risk' => []];
foreach ($inactive as $client) {
    $d = (int) $client['days_inactive'];
    if ($d >= 30) {
        $categories['churn_risk'][] = $client;
    } elseif ($d >= 14) {
        $categories['critical'][] = $client;
    } else {
        $categories['warning'][] = $client;
    }
}

$logStmt = $db->prepare("INSERT INTO webhook_logs (webhook_type, payload, status, created_at) VALUES ('inactivity', ?, 'success', NOW())");
$logStmt->execute([json_encode(['days' => $inactiveDays, 'total' => count($inactive)])]);

respond([
    'ok'         => true,
    'total'      => count($inactive),
    'categories' => $categories,
    'webhook'    => 'inactivity-check',
]);
