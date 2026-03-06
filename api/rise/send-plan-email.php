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
// RISE dashboard envía training/nutrition; cliente.html envía entrenamiento/nutricion/habitos
$typeMap = [
    'training'       => 'rise',
    'nutrition'      => 'nutrition',
    'entrenamiento'  => 'entrenamiento',
    'nutricion'      => 'nutricion',
    'habitos'        => 'habitos',
];
$dbType  = $typeMap[$type] ?? '';
if (!$dbType) {
    respondError('Tipo de plan invalido.', 400);
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

$labelMap = [
    'training' => 'Entrenamiento', 'nutrition' => 'Nutricion',
    'entrenamiento' => 'Entrenamiento', 'nutricion' => 'Nutricion', 'habitos' => 'Habitos',
];
$planLabel = $labelMap[$type] ?? 'Plan';

// ── Preparar el HTML adjunto (plan completo, idéntico a la plataforma) ──
$attachmentHtml = $plan['content'];
// Remover botón de PDF y scripts de descarga (no necesarios offline)
$attachmentHtml = preg_replace('/<button[^>]*class="print-btn"[^>]*>.*?<\/button>/is', '', $attachmentHtml);
// Remover script de iframe-detection (no aplica en archivo standalone)
$attachmentHtml = preg_replace('/<script>\s*if\s*\(\s*window\s*!==\s*window\.top\s*\).*?<\/script>/is', '', $attachmentHtml);

$attachFilename = "Plan-RISE-{$planLabel}-{$firstName}.html";

$dashboardUrl = 'https://wellcorefitness.com/rise-dashboard.html';
$year = date('Y');

$emailHtml = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tu Plan RISE - {$planLabel} | WellCore Fitness</title>
</head>
<body style="margin:0;padding:0;background:#0A0A0A;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%">

<!-- Preheader -->
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;overflow:hidden">
{$firstName}, tu plan de {$planLabel} RISE esta adjunto — abrelo en tu navegador para verlo completo.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;padding:20px 10px">
<tr><td align="center">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#111114;border:1px solid #2A2A2E">

<!-- Red top bar -->
<tr><td style="background:#C8102E;padding:4px 0;font-size:0;line-height:0">&nbsp;</td></tr>

<!-- Logo -->
<tr><td style="padding:28px 32px 16px;text-align:center;background:#111114">
  <table role="presentation" cellpadding="0" cellspacing="0" align="center">
  <tr>
    <td style="font-family:Arial,Helvetica,sans-serif;font-size:26px;font-weight:700;color:#FFFFFF;letter-spacing:3px">WELL</td>
    <td style="font-family:Arial,Helvetica,sans-serif;font-size:26px;font-weight:700;color:#C8102E;letter-spacing:3px">[CORE]</td>
  </tr>
  </table>
  <div style="font-size:9px;color:#71717A;letter-spacing:3px;margin-top:4px;text-transform:uppercase">RETO RISE &middot; 30 DIAS</div>
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 32px"><div style="border-top:1px solid #2A2A2E"></div></td></tr>

<!-- Greeting -->
<tr><td style="padding:24px 32px 16px;background:#111114">
  <div style="font-size:11px;color:#C8102E;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:10px">TU PLAN DE {$planLabel}</div>
  <div style="font-size:22px;font-weight:700;color:#FFFFFF;line-height:1.3;margin-bottom:16px">
    {$firstName}, tu programa esta listo
  </div>
  <div style="font-size:14px;color:#D4D4D8;line-height:1.7;margin-bottom:12px">
    Tu plan completo de <strong style="color:#FFFFFF">{$planLabel}</strong> va adjunto como archivo HTML. Abrelo en cualquier navegador para verlo exactamente como en la plataforma &mdash; <strong style="color:#FFFFFF">funciona sin conexion a internet.</strong>
  </div>
</td></tr>

<!-- Attachment instruction -->
<tr><td style="padding:0 32px 20px;background:#111114">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;border:1px solid #2A2A2E;border-top:3px solid #C8102E">
  <tr><td style="padding:20px 24px;text-align:center">
    <div style="font-size:28px;margin-bottom:10px">&#128206;</div>
    <div style="font-family:Arial,sans-serif;font-size:14px;font-weight:700;color:#FFFFFF;margin-bottom:6px">{$attachFilename}</div>
    <div style="font-size:12px;color:#71717A;line-height:1.5">
      Descarga el archivo adjunto y abrelo en Chrome, Safari o cualquier navegador
    </div>
  </td></tr>
  </table>
</td></tr>

<!-- Steps -->
<tr><td style="padding:0 32px 20px;background:#111114">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td style="padding:12px 16px;background:#0A0A0A;border-left:3px solid #C8102E">
      <div style="font-size:12px;color:#D4D4D8;line-height:1.8">
        <strong style="color:#C8102E">1.</strong> Descarga el archivo adjunto<br>
        <strong style="color:#C8102E">2.</strong> Abrelo en tu navegador (Chrome, Safari, etc.)<br>
        <strong style="color:#C8102E">3.</strong> Guardalo en favoritos para acceso rapido en el gym
      </div>
    </td>
  </tr>
  </table>
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 32px;background:#111114"><div style="border-top:1px solid #2A2A2E"></div></td></tr>

<!-- CTA -->
<tr><td style="padding:24px 32px;background:#111114" align="center">
  <div style="font-size:13px;color:#71717A;margin-bottom:14px">Tambien puedes ver tu plan en el dashboard:</div>
  <a href="{$dashboardUrl}" target="_blank" style="display:inline-block;background:#C8102E;color:#ffffff;text-decoration:none;padding:14px 36px;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase">
    IR A MI DASHBOARD &rarr;
  </a>
</td></tr>

<!-- Footer -->
<tr><td style="padding:18px 32px 14px;text-align:center;border-top:1px solid #2A2A2E;background:#0A0A0A">
  <div style="font-size:11px;color:#71717A;line-height:1.8">
    <strong style="color:#D4D4D8">WellCore Fitness</strong><br>
    <a href="https://wellcorefitness.com" style="color:#71717A;text-decoration:none">wellcorefitness.com</a> &nbsp;|&nbsp;
    <a href="mailto:info@wellcorefitness.com" style="color:#71717A;text-decoration:none">info@wellcorefitness.com</a><br>
    <a href="https://wa.me/573124904720" style="color:#71717A;text-decoration:none">WhatsApp: +57 312 490 4720</a>
  </div>
  <div style="font-size:10px;color:#52525B;margin-top:8px;letter-spacing:1px">
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
$result  = sendEmail($clientEmail, $subject, $emailHtml, '', [
    [
        'filename' => $attachFilename,
        'content'  => $attachmentHtml,
        'mime'     => 'text/html',
    ],
]);

if (!$result['ok']) {
    respondError('Error al enviar email: ' . ($result['error'] ?? 'desconocido'), 500);
}

respond([
    'ok'      => true,
    'message' => "Plan de {$planLabel} enviado a {$clientEmail}",
    'email'   => $clientEmail,
]);
