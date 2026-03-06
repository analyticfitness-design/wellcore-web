<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
/**
 * RISE — Enviar plan por email
 * POST /api/rise/send-plan-email.php
 * Body: { type: "training" | "nutrition" }
 * Auth: Bearer token de cliente
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rate-limit.php';
require_once __DIR__ . '/../includes/email.php';

requireMethod('POST');
$client = authenticateClient();
$db     = getDB();

// Rate limit: max 3 emails por hora por cliente
if (!rate_limit_check('send_plan_' . $client['id'], 3, 3600)) {
    respondError('Ya enviaste este plan recientemente. Intenta en 1 hora.', 429);
}

$body = getJsonBody();
$type = trim($body['type'] ?? '');

// Mapear tipo del frontend al tipo en DB
$typeMap = ['training' => 'rise', 'nutrition' => 'nutrition'];
$dbType  = $typeMap[$type] ?? '';
if (!$dbType) {
    respondError('Tipo de plan invalido. Usa "training" o "nutrition".', 400);
}

// Obtener el plan activo
$stmt = $db->prepare("
    SELECT content, plan_type, version, created_at
    FROM assigned_plans
    WHERE client_id = ? AND plan_type = ? AND active = 1
    ORDER BY version DESC LIMIT 1
");
$stmt->execute([$client['id'], $dbType]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan || empty($plan['content'])) {
    respondError('No tienes un plan activo de este tipo.', 404);
}

$clientName  = $client['name'] ?? 'Cliente';
$clientEmail = $client['email'] ?? '';
$firstName   = explode(' ', trim($clientName))[0];

if (!$clientEmail || !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
    respondError('No se encontro un email valido en tu cuenta.', 400);
}

$planLabel = $type === 'training' ? 'Entrenamiento' : 'Nutricion';
$planHtml  = $plan['content'];

// Limpiar botones de impresion/descarga del HTML del plan
$planHtml = preg_replace('/<button[^>]*class=["\']print-btn["\'][^>]*>.*?<\/button>/is', '', $planHtml);
$planHtml = preg_replace('/<script[^>]*html2pdf[^>]*><\/script>/i', '', $planHtml);
$planHtml = preg_replace('/function\s+downloadPDF\s*\(\)\s*\{[^}]*\}/s', '', $planHtml);

// Construir email wrapper
$dashboardUrl = 'https://wellcorefitness.com/rise-dashboard.html';
$year = date('Y');

$emailHtml = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tu Plan RISE - {$planLabel} | WellCore Fitness</title>
<!--[if mso]><style>table{border-collapse:collapse;}td{font-family:Arial,sans-serif;}</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:#050505;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%">

<!-- Preheader -->
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;overflow:hidden">
{$firstName}, aqui tienes tu plan de {$planLabel} RISE para acceder sin conexion.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#050505;padding:20px 10px">
<tr><td align="center">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#0a0a0a;border:1px solid #1a1a1a">

<!-- Red top bar -->
<tr><td style="background:#E31E24;padding:3px 0;font-size:0;line-height:0">&nbsp;</td></tr>

<!-- Logo -->
<tr><td style="padding:32px 40px 20px;text-align:center;background:#0a0a0a">
  <table role="presentation" cellpadding="0" cellspacing="0" align="center">
  <tr>
    <td style="font-family:Arial,Helvetica,sans-serif;font-size:28px;font-weight:700;color:#ffffff;letter-spacing:3px">WELL</td>
    <td style="font-family:Arial,Helvetica,sans-serif;font-size:28px;font-weight:700;color:#E31E24;letter-spacing:3px">[CORE]</td>
  </tr>
  </table>
  <div style="font-size:9px;color:#52525b;letter-spacing:3px;margin-top:4px;text-transform:uppercase">RETO RISE &middot; 30 DIAS</div>
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 40px"><div style="border-top:1px solid #1a1a1a"></div></td></tr>

<!-- Greeting -->
<tr><td style="padding:28px 40px 16px">
  <div style="font-size:11px;color:#C8102E;letter-spacing:3px;text-transform:uppercase;font-weight:700;margin-bottom:12px">// TU PLAN DE {$planLabel}</div>
  <div style="font-size:20px;font-weight:700;color:#ffffff;line-height:1.3;margin-bottom:16px">
    {$firstName}, aqui esta tu programa
  </div>
  <div style="font-size:14px;color:#a1a1aa;line-height:1.7;margin-bottom:8px">
    Guarda este correo para acceder a tu plan de <strong style="color:#fff">{$planLabel}</strong> en cualquier momento, incluso sin conexion a internet. Tu programa esta disenado especificamente para ti.
  </div>
</td></tr>

<!-- Tip box -->
<tr><td style="padding:0 40px 24px">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#111113;border-left:3px solid #C8102E">
  <tr><td style="padding:14px 20px">
    <div style="font-size:12px;color:#a1a1aa;line-height:1.5">
      <strong style="color:#C8102E">TIP:</strong> Marca este email como favorito o guardalo en una carpeta especial para encontrarlo rapidamente cuando lo necesites en el gym.
    </div>
  </td></tr>
  </table>
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 40px"><div style="border-top:1px solid #1a1a1a"></div></td></tr>

<!-- PLAN CONTENT -->
<tr><td style="padding:24px 20px">
{$planHtml}
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 40px"><div style="border-top:1px solid #1a1a1a"></div></td></tr>

<!-- CTA -->
<tr><td style="padding:28px 40px" align="center">
  <div style="font-size:13px;color:#a1a1aa;margin-bottom:16px">Tambien puedes ver tu plan completo en el dashboard:</div>
  <!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{$dashboardUrl}" style="height:46px;v-text-anchor:middle;width:280px" fill="true" stroke="false"><v:fill type="tile" color="#C8102E"/><center style="color:#ffffff;font-family:Arial;font-size:13px;font-weight:bold">IR A MI DASHBOARD</center></v:roundrect><![endif]-->
  <!--[if !mso]><!-->
  <a href="{$dashboardUrl}" target="_blank" style="display:inline-block;background:#C8102E;color:#ffffff;text-decoration:none;padding:14px 40px;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase">
    IR A MI DASHBOARD &rarr;
  </a>
  <!--<![endif]-->
</td></tr>

<!-- Footer -->
<tr><td style="padding:20px 40px 16px;text-align:center;border-top:1px solid #1a1a1a">
  <div style="font-size:11px;color:#3f3f46;line-height:1.8">
    <strong style="color:#52525b">WellCore Fitness</strong><br>
    <a href="https://wellcorefitness.com" style="color:#3f3f46;text-decoration:none">wellcorefitness.com</a> &nbsp;|&nbsp;
    <a href="mailto:info@wellcorefitness.com" style="color:#3f3f46;text-decoration:none">info@wellcorefitness.com</a><br>
    <a href="https://wa.me/573124904720" style="color:#3f3f46;text-decoration:none">WhatsApp: +57 312 490 4720</a>
  </div>
  <div style="font-size:10px;color:#27272a;margin-top:10px;letter-spacing:1px">
    &copy; {$year} WellCore Fitness. Todos los derechos reservados.
  </div>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

$subject = "Tu Plan RISE de {$planLabel} — WellCore Fitness";
$result  = sendEmail($clientEmail, $subject, $emailHtml);

if (!$result['ok']) {
    respondError('Error al enviar email: ' . ($result['error'] ?? 'desconocido'), 500);
}

respond([
    'ok'      => true,
    'message' => "Plan de {$planLabel} enviado a {$clientEmail}",
    'email'   => $clientEmail,
]);
