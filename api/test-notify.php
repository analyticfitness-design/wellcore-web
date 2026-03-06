<?php
// Script temporal para probar la notificacion de nuevo cliente
// Eliminar despues de probar
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

header('Content-Type: application/json');

require_once __DIR__ . '/config/email.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/notify-admin.php';

$testClient = [
    'name'  => 'Carlos Demo',
    'email' => 'carlos@ejemplo.com',
    'plan'  => 'rise',
    'code'  => 'RISE-TEST1234',
];

$results = [];

// Enviar a los correos de prueba
$testEmails = ['analyticfitness@gmail.com', 'silviagomezroa4@gmail.com'];

foreach ($testEmails as $testEmail) {
    $name  = $testClient['name'];
    $plan  = strtoupper($testClient['plan']);
    $subject = "Nuevo cliente: {$name} — Plan {$plan}";

    // Generar el HTML (misma logica que notify-admin.php)
    $year = date('Y');
    $date = date('d/m/Y H:i');
    $html = buildNotifyHtml($testClient, 'rise_enroll', $date, $year);

    $result = sendEmail($testEmail, $subject, $html);
    $results[] = ['to' => $testEmail, 'ok' => $result['ok'], 'error' => $result['error'] ?? null];
}

echo json_encode(['results' => $results], JSON_PRETTY_PRINT);

function buildNotifyHtml($client, $source, $date, $year) {
    $name = $client['name'];
    $email = $client['email'];
    $plan = strtoupper($client['plan']);
    $code = $client['code'];
    $sourceLabels = [
        'rise_enroll' => 'Inscripcion publica RISE',
        'invitation'  => 'Codigo de invitacion',
        'wompi'       => 'Pago Wompi aprobado',
    ];
    $sourceLabel = $sourceLabels[$source] ?? $source;
    $dashboardUrl = 'https://wellcorefitness.com/admin.html';

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Nuevo Cliente</title></head>
<body style="margin:0;padding:0;background:#0A0A0A;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;padding:20px 10px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#111114;border:1px solid #2A2A2E">
<tr><td style="background:#C8102E;padding:4px 0;font-size:0;line-height:0">&nbsp;</td></tr>
<tr><td style="padding:24px 28px 14px;text-align:center;background:#111114">
  <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>
    <td style="font-size:22px;font-weight:700;color:#FFFFFF;letter-spacing:3px">WELL</td>
    <td style="font-size:22px;font-weight:700;color:#C8102E;letter-spacing:3px">[CORE]</td>
  </tr></table>
  <div style="font-size:9px;color:#71717A;letter-spacing:2px;margin-top:4px;text-transform:uppercase">NUEVO REGISTRO</div>
</td></tr>
<tr><td style="padding:0 28px"><div style="border-top:1px solid #2A2A2E"></div></td></tr>
<tr><td style="padding:20px 28px;background:#111114">
  <div style="font-size:11px;color:#C8102E;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:12px">CLIENTE NUEVO</div>
  <div style="font-size:18px;font-weight:700;color:#FFFFFF;line-height:1.3;margin-bottom:16px">Se ha registrado un nuevo cliente</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;border:1px solid #2A2A2E;border-radius:6px">
    <tr><td style="padding:12px 16px;border-bottom:1px solid #2A2A2E"><div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Nombre</div><div style="font-size:14px;color:#FFFFFF;font-weight:600;margin-top:2px">{$name}</div></td></tr>
    <tr><td style="padding:12px 16px;border-bottom:1px solid #2A2A2E"><div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Email</div><div style="font-size:14px;color:#FFFFFF;margin-top:2px">{$email}</div></td></tr>
    <tr><td style="padding:12px 16px;border-bottom:1px solid #2A2A2E"><div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Plan</div><div style="font-size:14px;color:#C8102E;font-weight:700;margin-top:2px">{$plan}</div></td></tr>
    <tr><td style="padding:12px 16px;border-bottom:1px solid #2A2A2E"><div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Codigo</div><div style="font-size:14px;color:#D4D4D8;font-family:monospace;margin-top:2px">{$code}</div></td></tr>
    <tr><td style="padding:12px 16px;border-bottom:1px solid #2A2A2E"><div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Origen</div><div style="font-size:14px;color:#D4D4D8;margin-top:2px">{$sourceLabel}</div></td></tr>
    <tr><td style="padding:12px 16px"><div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Fecha</div><div style="font-size:14px;color:#D4D4D8;margin-top:2px">{$date}</div></td></tr>
  </table>
</td></tr>
<tr><td style="padding:8px 28px 20px;background:#111114" align="center">
  <a href="{$dashboardUrl}" target="_blank" style="display:inline-block;background:#C8102E;color:#ffffff;text-decoration:none;padding:12px 32px;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;margin-top:8px">VER EN PANEL &rarr;</a>
</td></tr>
<tr><td style="padding:14px 28px;text-align:center;border-top:1px solid #2A2A2E;background:#0A0A0A">
  <div style="font-size:10px;color:#52525B;letter-spacing:1px">&copy; {$year} WellCore Fitness &middot; Notificacion automatica</div>
</td></tr>
</table>
</td></tr></table>
</body></html>
HTML;
}
