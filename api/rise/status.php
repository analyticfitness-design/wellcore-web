<?php
declare(strict_types=1);
/**
 * RISE Plan Status — Vigencia y acceso
 * GET /api/rise/status
 *
 * Retorna: { active, start_date, end_date, days_elapsed, days_remaining, expired, client_name, coach }
 * - Si expired=true, el cliente debe renovar su plan
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';

requireMethod('GET');
$client = authenticateClient();
$db     = getDB();
$cid    = (int)$client['id'];

// Obtener fecha de inicio del plan desde el primer plan asignado activo
$stmt = $db->prepare("
    SELECT ap.valid_from, cp.rise_start_date, cp.rise_coach, cp.rise_gender, c.name, c.plan
    FROM clients c
    LEFT JOIN client_profiles cp ON cp.client_id = c.id
    LEFT JOIN assigned_plans ap ON ap.client_id = c.id AND ap.active = 1 AND ap.plan_type = 'entrenamiento'
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$cid]);
$row = $stmt->fetch();

if (!$row || $row['plan'] !== 'rise') {
    respondError('No eres un cliente del RETO RISE', 403);
}

// Fecha de inicio: usar rise_start_date o valid_from del plan, lo que aplique primero
$startDate = $row['rise_start_date'] ?? $row['valid_from'] ?? null;

if (!$startDate) {
    // Plan asignado pero sin fecha de inicio definida — plan activo pero sin vigencia calculada aún
    respond([
        'active'         => true,
        'pending_start'  => true,
        'message'        => 'Tu programa está siendo preparado por tu coach.',
        'start_date'     => null,
        'end_date'       => null,
        'days_elapsed'   => 0,
        'days_remaining' => 30,
        'expired'        => false,
        'client_name'    => $row['name'],
        'coach'          => $row['rise_coach'] ?? 'silvia',
    ]);
}

// Calcular vigencia: 30 días desde el inicio
$startTs        = strtotime($startDate);
$endTs          = strtotime('+30 days', $startTs);
$nowTs          = time();
$daysElapsed    = max(0, (int)(($nowTs - $startTs) / 86400));
$daysRemaining  = max(0, (int)(($endTs - $nowTs) / 86400));
$expired        = $nowTs > $endTs;

// Si expiró, enviar notificación al admin una sola vez
if ($expired) {
    try {
        $already = $db->prepare("SELECT id FROM email_logs WHERE to_email = 'info@wellcorefitness.com' AND template = 'rise_expiry' AND plan = ? LIMIT 1");
        $already->execute([$row['name']]);
        if (!$already->fetch()) {
            $clientEmail = $db->prepare("SELECT email FROM clients WHERE id = ?");
            $clientEmail->execute([$cid]);
            $cEmail = ($clientEmail->fetch())['email'] ?? '';

            $html = "<!DOCTYPE html><html><body style='font-family:Arial;background:#0a0a0a;color:#fff;padding:32px'>"
                . "<h2 style='color:#E31E24;'>RISE Expirado — " . htmlspecialchars($row['name']) . "</h2>"
                . "<p>El cliente <strong>" . htmlspecialchars($row['name']) . "</strong> ({$cEmail}) completó los 30 días del RETO RISE.</p>"
                . "<p>Inicio: <strong>" . date('d/m/Y', $startTs) . "</strong> &nbsp; Fin: <strong>" . date('d/m/Y', $endTs) . "</strong></p>"
                . "<p style='margin-top:24px;'><a href='https://wa.me/?text=Hola+" . urlencode($row['name']) . "' style='background:#E31E24;color:#fff;padding:12px 24px;text-decoration:none;font-weight:bold;'>Contactar por WhatsApp</a></p>"
                . "<p style='color:#666;font-size:12px;margin-top:32px;'>WellCore Fitness — Notificación automática</p>"
                . "</body></html>";

            sendEmail('info@wellcorefitness.com', '🏁 RISE Expirado — ' . $row['name'], $html);
            $db->prepare("INSERT INTO email_logs (sent_by, to_email, to_name, template, plan, sent_at) VALUES (0, 'info@wellcorefitness.com', ?, 'rise_expiry', ?, NOW())")
               ->execute([$row['name'], $row['name']]);
        }
    } catch (\Throwable $ignored) {}
}

respond([
    'active'         => !$expired,
    'start_date'     => $startDate,
    'end_date'       => date('Y-m-d', $endTs),
    'days_elapsed'   => $daysElapsed,
    'days_remaining' => $daysRemaining,
    'expired'        => $expired,
    'client_name'    => $row['name'],
    'coach'          => $row['rise_coach'] ?? 'silvia',
    'gender'         => $row['rise_gender'] ?? 'mujer',
    'message'        => $expired
        ? 'Tu RETO RISE ha expirado. ¡Renueva tu plan para seguir progresando!'
        : "Día {$daysElapsed} de 30 — ¡Vas muy bien!",
]);
