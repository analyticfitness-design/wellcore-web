<?php
declare(strict_types=1);
/**
 * WellCore Fitness — F3: Webhook Recordatorio de Entreno
 * ============================================================
 * GET /api/webhooks/training-reminder?secret=WC_WEBHOOK_2026
 *
 * Devuelve clientes que deben entrenar hoy segun su plan,
 * para que n8n envie recordatorios por WhatsApp.
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

// Dia de la semana en espanol
$dayMap = [
    1 => 'lunes', 2 => 'martes', 3 => 'miercoles',
    4 => 'jueves', 5 => 'viernes', 6 => 'sabado', 0 => 'domingo',
];
$today = $dayMap[(int) date('w')] ?? 'lunes';

// Clientes activos con dias de entrenamiento que incluyen hoy
$stmt = $db->prepare("
    SELECT c.id, c.client_code, c.name, c.email, c.plan,
           p.whatsapp, p.dias_disponibles, p.objetivo, p.nivel
    FROM clients c
    JOIN client_profiles p ON p.client_id = c.id
    WHERE c.status = 'activo'
    AND p.dias_disponibles IS NOT NULL
");
$stmt->execute();
$allClients = $stmt->fetchAll();

$trainingToday = [];
foreach ($allClients as $client) {
    $dias = json_decode($client['dias_disponibles'] ?? '[]', true);
    if (!is_array($dias)) continue;

    // Normalizar dias
    $diasNorm = array_map(fn($d) => mb_strtolower(trim($d)), $dias);
    if (in_array($today, $diasNorm, true)) {
        // Verificar si ya entreno hoy (tiene log de entrenamiento)
        $logStmt = $db->prepare("
            SELECT COUNT(*) FROM training_logs
            WHERE client_id = ? AND DATE(created_at) = CURDATE()
        ");
        $logStmt->execute([$client['id']]);
        $trainedToday = (int) $logStmt->fetchColumn();

        $client['already_trained'] = $trainedToday > 0;
        $client['day'] = $today;
        unset($client['dias_disponibles']); // No enviar raw JSON
        $trainingToday[] = $client;
    }
}

// Separar: pendientes vs ya entrenaron
$pending = array_values(array_filter($trainingToday, fn($c) => !$c['already_trained']));
$done    = array_values(array_filter($trainingToday, fn($c) => $c['already_trained']));

$logStmt = $db->prepare("INSERT INTO webhook_logs (webhook_type, payload, status, created_at) VALUES ('training_reminder', ?, 'success', NOW())");
$logStmt->execute([json_encode(['day' => $today, 'pending' => count($pending), 'done' => count($done)])]);

respond([
    'ok'      => true,
    'day'     => $today,
    'pending' => $pending,
    'done'    => $done,
    'webhook' => 'training-reminder',
]);
