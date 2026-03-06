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

            $clientNameSafe = htmlspecialchars($row['name']);
            $startFmt = date('d/m/Y', $startTs);
            $endFmt   = date('d/m/Y', $endTs);
            $waLink   = 'https://wa.me/?text=Hola+' . urlencode($row['name']);
            $adminUrl = 'https://wellcorefitness.com/admin.html';
            $yearExp  = date('Y');

            $html = <<<EXPHTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>RISE Expirado</title></head>
<body style="margin:0;padding:0;background:#0A0A0A;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;padding:20px 10px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#111114;border:1px solid #2A2A2E">

<tr><td style="background:#E31E24;padding:3px 0;font-size:0;line-height:0">&nbsp;</td></tr>

<tr><td style="padding:28px 32px 16px;text-align:center;background:#111114">
  <table role="presentation" cellpadding="0" cellspacing="0" align="center">
  <tr>
    <td style="font-size:24px;font-weight:700;color:#FFFFFF;letter-spacing:3px">WELL</td>
    <td style="font-size:24px;font-weight:700;color:#E31E24;letter-spacing:3px">[CORE]</td>
  </tr>
  </table>
  <div style="font-size:9px;color:#71717A;letter-spacing:3px;margin-top:4px;text-transform:uppercase">NOTIFICACION AUTOMATICA</div>
</td></tr>

<tr><td style="padding:0 32px"><div style="border-top:1px solid #2A2A2E"></div></td></tr>

<tr><td style="padding:24px 32px 16px;background:#111114">
  <div style="font-size:11px;color:#F59E0B;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:12px">&#9888; RETO RISE EXPIRADO</div>
  <div style="font-size:20px;font-weight:700;color:#FFFFFF;line-height:1.3;margin-bottom:16px">{$clientNameSafe}</div>
  <div style="font-size:14px;color:#A1A1AA;line-height:1.7">
    Este cliente completo los 30 dias del Reto RISE. Su acceso ha expirado y debe renovar para continuar.
  </div>
</td></tr>

<tr><td style="padding:0 32px 20px;background:#111114">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;border:1px solid #2A2A2E;border-top:3px solid #F59E0B">
  <tr><td style="padding:10px 16px;border-bottom:1px solid #2A2A2E">
    <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Cliente</div>
    <div style="font-size:14px;color:#D4D4D8;font-weight:700;margin-top:2px">{$clientNameSafe}</div>
  </td></tr>
  <tr><td style="padding:10px 16px;border-bottom:1px solid #2A2A2E">
    <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Email</div>
    <div style="font-size:14px;color:#D4D4D8;margin-top:2px">{$cEmail}</div>
  </td></tr>
  <tr><td style="padding:10px 16px;border-bottom:1px solid #2A2A2E">
    <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Inicio</div>
    <div style="font-size:14px;color:#D4D4D8;margin-top:2px">{$startFmt}</div>
  </td></tr>
  <tr><td style="padding:10px 16px">
    <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Fin</div>
    <div style="font-size:14px;color:#E31E24;font-weight:700;margin-top:2px">{$endFmt}</div>
  </td></tr>
  </table>
</td></tr>

<tr><td style="padding:0 32px 24px;background:#111114" align="center">
  <a href="{$waLink}" target="_blank" style="display:inline-block;background:#25D366;color:#ffffff;text-decoration:none;padding:14px 36px;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;margin-right:8px">
    CONTACTAR POR WHATSAPP
  </a>
  <br><br>
  <a href="{$adminUrl}" target="_blank" style="display:inline-block;background:#E31E24;color:#ffffff;text-decoration:none;padding:14px 36px;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase">
    VER EN PANEL ADMIN &rarr;
  </a>
</td></tr>

<tr><td style="padding:16px 32px;text-align:center;border-top:1px solid #2A2A2E;background:#0A0A0A">
  <div style="font-size:10px;color:#52525B;letter-spacing:1px">
    &copy; {$yearExp} WellCore Fitness &middot; Notificacion automatica
  </div>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
EXPHTML;

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
