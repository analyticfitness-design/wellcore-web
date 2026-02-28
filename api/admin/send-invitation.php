<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Send invitation email
 * POST /api/admin/send-invitation
 * Body: { "invitation_id": int }
 *
 * Sends an email to the invitation's email_hint with the signup link.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';

requireMethod('POST');
$admin = authenticateAdmin();
$db    = getDB();

$body = getJsonBody();
$invId = (int)($body['invitation_id'] ?? 0);

if (!$invId) {
    respondError('invitation_id es requerido', 422);
}

// Fetch invitation
$stmt = $db->prepare("SELECT * FROM invitations WHERE id = ?");
$stmt->execute([$invId]);
$inv = $stmt->fetch();

if (!$inv) {
    respondError('Invitacion no encontrada', 404);
}

if ($inv['status'] !== 'pending') {
    respondError('Solo se pueden enviar invitaciones pendientes', 422);
}

$email = $inv['email_hint'] ?? '';
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondError('La invitacion no tiene un email valido. Agrega un email antes de enviar.', 422);
}

$code = $inv['code'];
$plan = ucfirst($inv['plan']);
$link = 'https://wellcorefitness.com/inscripcion.html?invite=' . $code;

// Build email HTML
$planColors = ['esencial' => '#60a5fa', 'metodo' => '#F5C842', 'elite' => '#E31E24'];
$planColor  = $planColors[strtolower($inv['plan'])] ?? '#E31E24';
$planFeatures = [
    'esencial' => 'Plan de entrenamiento personalizado &bull; Seguimiento semanal &bull; Acceso al panel de cliente',
    'metodo'   => 'Entrenamiento + Nutricion &bull; Check-ins semanales &bull; Soporte por chat &bull; Analisis de progreso',
    'elite'    => 'Coaching integral 1:1 &bull; Entrenamiento + Nutricion + Suplementacion &bull; Soporte prioritario &bull; Analisis IA',
];
$features = $planFeatures[strtolower($inv['plan'])] ?? '';

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invitacion WellCore Fitness</title>
<!--[if mso]><style>table{border-collapse:collapse;}td{font-family:Arial,sans-serif;}</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:#050505;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%">

<!-- Preheader (hidden text for email preview) -->
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;overflow:hidden">
Has sido invitado a WellCore Fitness con el plan {$plan}. Completa tu inscripcion ahora.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#050505;padding:20px 10px">
<tr><td align="center">

<!-- Outer container: max 600px, responsive -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#0a0a0a;border:1px solid #1a1a1a">

<!-- Red top bar -->
<tr><td style="background:#E31E24;padding:3px 0;font-size:0;line-height:0">&nbsp;</td></tr>

<!-- Logo Section -->
<tr><td style="padding:36px 40px 24px;text-align:center;background:#0a0a0a">
  <table role="presentation" cellpadding="0" cellspacing="0" align="center">
  <tr>
    <td style="font-family:Arial,Helvetica,sans-serif;font-size:32px;font-weight:700;color:#ffffff;letter-spacing:3px">WELL</td>
    <td style="font-family:Arial,Helvetica,sans-serif;font-size:32px;font-weight:700;color:#E31E24;letter-spacing:3px">[CORE]</td>
  </tr>
  </table>
  <div style="font-size:10px;color:#52525b;letter-spacing:4px;margin-top:6px;text-transform:uppercase">Entrenamiento basado en ciencia</div>
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 40px"><div style="border-top:1px solid #1a1a1a"></div></td></tr>

<!-- Main Content -->
<tr><td style="padding:32px 40px 24px">
  <div style="font-size:12px;color:#E31E24;letter-spacing:3px;text-transform:uppercase;font-weight:700;margin-bottom:16px">// INVITACION</div>
  <div style="font-size:22px;font-weight:700;color:#ffffff;line-height:1.3;margin-bottom:20px">
    Has sido seleccionado para unirte a WellCore Fitness
  </div>
  <div style="font-size:14px;color:#a1a1aa;line-height:1.7;margin-bottom:28px">
    Tienes acceso exclusivo al programa de entrenamiento mas avanzado de Latinoamerica. Tu plaza esta reservada con el siguiente plan:
  </div>

  <!-- Plan Card -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#111113;border-left:3px solid {$planColor};margin-bottom:28px">
  <tr><td style="padding:20px 24px">
    <div style="font-size:10px;color:#52525b;letter-spacing:2px;text-transform:uppercase;margin-bottom:8px">TU PLAN</div>
    <div style="font-size:20px;font-weight:700;color:{$planColor};letter-spacing:1px;text-transform:uppercase;margin-bottom:10px">{$plan}</div>
    <div style="font-size:12px;color:#71717a;line-height:1.6">{$features}</div>
  </td></tr>
  </table>

  <div style="font-size:14px;color:#a1a1aa;line-height:1.6;margin-bottom:28px">
    Completa tu inscripcion para activar tu cuenta. El enlace es valido por <strong style="color:#ffffff">30 dias</strong>.
  </div>
</td></tr>

<!-- CTA Button -->
<tr><td style="padding:0 40px 32px" align="center">
  <!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{$link}" style="height:50px;v-text-anchor:middle;width:320px" fill="true" stroke="false"><v:fill type="tile" color="#E31E24"/><center style="color:#ffffff;font-family:Arial;font-size:14px;font-weight:bold">COMPLETAR INSCRIPCION</center></v:roundrect><![endif]-->
  <!--[if !mso]><!-->
  <a href="{$link}" target="_blank" style="display:inline-block;background:#E31E24;color:#ffffff;text-decoration:none;padding:16px 48px;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;mso-padding-alt:0;text-underline-color:#E31E24">
    COMPLETAR INSCRIPCION &rarr;
  </a>
  <!--<![endif]-->
</td></tr>

<!-- Fallback Link -->
<tr><td style="padding:0 40px 32px">
  <div style="font-size:11px;color:#3f3f46;line-height:1.5;margin-bottom:8px">Si el boton no funciona, copia este enlace:</div>
  <div style="font-size:11px;color:#00D9FF;word-break:break-all;background:#111113;padding:14px 16px;border:1px solid #1a1a1a;font-family:'Courier New',monospace;line-height:1.5">
    {$link}
  </div>
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 40px"><div style="border-top:1px solid #1a1a1a"></div></td></tr>

<!-- Footer -->
<tr><td style="padding:24px 40px 20px;text-align:center">
  <div style="font-size:11px;color:#3f3f46;line-height:1.8">
    <strong style="color:#52525b">WellCore Fitness</strong><br>
    <a href="https://wellcorefitness.com" style="color:#3f3f46;text-decoration:none">wellcorefitness.com</a> &nbsp;|&nbsp;
    <a href="mailto:info@wellcorefitness.com" style="color:#3f3f46;text-decoration:none">info@wellcorefitness.com</a><br>
    <a href="https://wa.me/573124904720" style="color:#3f3f46;text-decoration:none">WhatsApp: +57 312 490 4720</a>
  </div>
  <div style="font-size:10px;color:#27272a;margin-top:12px;letter-spacing:1px">
    &copy; 2026 WellCore Fitness. Todos los derechos reservados.
  </div>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

$subject = "Invitacion WellCore Fitness — Plan {$plan}";
$result  = sendEmail($email, $subject, $html);

if (!$result['ok']) {
    respondError('Error al enviar email: ' . ($result['error'] ?? 'desconocido'), 500);
}

// Update invitation: mark that email was sent
$db->prepare("
    UPDATE invitations SET note = CONCAT(COALESCE(note,''), ' [Email enviado ' , NOW(), ']') WHERE id = ?
")->execute([$invId]);

respond([
    'ok'      => true,
    'message' => 'Email enviado a ' . $email,
]);
