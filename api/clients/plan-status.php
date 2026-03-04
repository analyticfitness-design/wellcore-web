<?php
declare(strict_types=1);
/**
 * WellCore — Plan Status (vigencia para TODOS los tipos de clientes)
 * GET /api/clients/plan-status
 *
 * Verifica si el plan del cliente está activo o ha expirado.
 * Aplica a: rise (30 días), esencial/metodo/elite (30 días por pago)
 *
 * Response:
 * {
 *   active: bool,       — el plan está activo
 *   expired: bool,      — el plan ha expirado
 *   start_date: string,
 *   end_date: string,
 *   days_elapsed: int,
 *   days_remaining: int,
 *   plan_duration_days: int,  — 30 para RISE, 30 para mensuales
 *   plan: string,
 *   message: string
 * }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$client = authenticateClient();
$db     = getDB();
$cid    = (int)$client['id'];

// Obtener datos del cliente
$stmt = $db->prepare("
    SELECT c.plan, c.fecha_inicio, cp.rise_start_date
    FROM clients c
    LEFT JOIN client_profiles cp ON cp.client_id = c.id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$cid]);
$row = $stmt->fetch();

if (!$row) {
    respondError('Cliente no encontrado', 404);
}

// Determinar fecha de inicio y duración según tipo de plan
$plan          = $row['plan'];
$planDuration  = 30; // Todos los planes actuales son de 30 días

if ($plan === 'rise') {
    $startDate = $row['rise_start_date'] ?? $row['fecha_inicio'] ?? null;
} else {
    // Planes mensuales: usar fecha_inicio del cliente
    // O la fecha del último pago aprobado si hay uno
    $lastPayment = $db->prepare("
        SELECT created_at FROM payments
        WHERE client_id = ? AND status = 'approved'
        ORDER BY created_at DESC LIMIT 1
    ");
    $lastPayment->execute([$cid]);
    $paymentDate = $lastPayment->fetchColumn();

    $startDate = $paymentDate
        ? date('Y-m-d', strtotime($paymentDate))
        : ($row['fecha_inicio'] ?? null);
}

if (!$startDate) {
    // Sin fecha de inicio — plan activo pero pendiente de configuración
    respond([
        'active'            => true,
        'expired'           => false,
        'pending_setup'     => true,
        'plan'              => $plan,
        'plan_duration_days'=> $planDuration,
        'start_date'        => null,
        'end_date'          => null,
        'days_elapsed'      => 0,
        'days_remaining'    => $planDuration,
        'message'           => 'Tu plan está siendo configurado por tu coach.',
    ]);
}

// Calcular vigencia
$startTs       = strtotime($startDate);
$endTs         = strtotime("+{$planDuration} days", $startTs);
$nowTs         = time();
$daysElapsed   = max(0, (int)(($nowTs - $startTs) / 86400));
$daysRemaining = max(0, (int)(($endTs - $nowTs) / 86400));
$expired       = $nowTs > $endTs;

respond([
    'active'            => !$expired,
    'expired'           => $expired,
    'plan'              => $plan,
    'plan_duration_days'=> $planDuration,
    'start_date'        => $startDate,
    'end_date'          => date('Y-m-d', $endTs),
    'days_elapsed'      => $daysElapsed,
    'days_remaining'    => $daysRemaining,
    'message'           => $expired
        ? 'Tu plan ha expirado. ¡Renueva para seguir progresando!'
        : "Día {$daysElapsed} de {$planDuration} activo",
]);
