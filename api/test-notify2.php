<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
header('Content-Type: application/json');

require_once __DIR__ . '/config/email.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/notify-admin.php';

// Temporalmente override la funcion para enviar a correos de prueba
$testClient = [
    'name'              => 'Maria Lopez Garcia',
    'email'             => 'maria.lopez@ejemplo.com',
    'plan'              => 'elite',
    'code'              => 'cli-0042',
    'phone'             => '+57 312 456 7890',
    'gender'            => 'female',
    'experience_level'  => 'intermedio',
    'training_location' => 'gym',
];

$testPayment = [
    'amount'    => 15000000,  // $150,000 COP en centavos
    'currency'  => 'COP',
    'method'    => 'CARD',
    'reference' => 'WC-elite-abc123def456',
    'wompi_id'  => '28734-1709737200-98234',
];

// Generar el HTML con la funcion real
ob_start();

// Enviamos directo con sendEmail a los correos de prueba
$name = $testClient['name'];
$plan = strtoupper($testClient['plan']);
$amt  = '$' . number_format($testPayment['amount'] / 100, 0, ',', '.') . ' COP';
$subject = "Nuevo pago: {$name} — {$plan} — {$amt}";

// Hack: temporalmente cambiar adminEmail
// Llamamos la funcion interna para generar el HTML
$date = date('d/m/Y H:i');
$year = date('Y');

$sourceLabel = 'Pago Wompi aprobado';
$dashboardUrl = 'https://wellcorefitness.com/admin.html';

// Build rows
$rows = '';
$rows .= _notifyRow('Nombre', $testClient['name'], true);
$rows .= _notifyRow('Email', $testClient['email']);
$rows .= _notifyRow('Telefono / WhatsApp', $testClient['phone']);
$rows .= _notifyRow('Genero', 'Femenino');
$rows .= _notifyRow('Plan', $plan, false, '#C8102E');
$rows .= _notifyRow('Codigo cliente', $testClient['code'], false, '', true);
$rows .= _notifyRow('Nivel', 'Intermedio');
$rows .= _notifyRow('Lugar entrenamiento', 'Gimnasio');
$rows .= _notifyRow('Origen del registro', $sourceLabel);
$rows .= _notifyRow('Fecha y hora', $date, false, '', false, true);

$paymentSection = '<tr><td style="padding:16px 28px 4px;background:#111114"><div style="font-size:11px;color:#22C55E;letter-spacing:2px;text-transform:uppercase;font-weight:700">DATOS DE PAGO</div></td></tr>';
$paymentSection .= '<tr><td style="padding:8px 28px 16px;background:#111114"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;border:1px solid #2A2A2E;border-top:3px solid #22C55E;border-radius:6px">';
$paymentSection .= _notifyRow('Monto pagado', $amt, true, '#22C55E');
$paymentSection .= _notifyRow('Moneda', 'COP');
$paymentSection .= _notifyRow('Metodo de pago', 'Tarjeta de credito/debito');
$paymentSection .= _notifyRow('Referencia', $testPayment['reference'], false, '', true);
$paymentSection .= _notifyRow('ID transaccion Wompi', $testPayment['wompi_id'], false, '', true, true);
$paymentSection .= '</table></td></tr>';

$html = <<<HTML
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Nuevo Cliente</title></head>
<body style="margin:0;padding:0;background:#0A0A0A;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;padding:20px 10px"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#111114;border:1px solid #2A2A2E">
<tr><td style="background:#C8102E;padding:4px 0;font-size:0;line-height:0">&nbsp;</td></tr>
<tr><td style="padding:24px 28px 14px;text-align:center;background:#111114">
  <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>
    <td style="font-size:22px;font-weight:700;color:#FFFFFF;letter-spacing:3px">WELL</td>
    <td style="font-size:22px;font-weight:700;color:#C8102E;letter-spacing:3px">[CORE]</td>
  </tr></table>
  <div style="font-size:9px;color:#71717A;letter-spacing:2px;margin-top:4px;text-transform:uppercase">NUEVO REGISTRO &middot; NOTIFICACION CONTABLE</div>
</td></tr>
<tr><td style="padding:0 28px"><div style="border-top:1px solid #2A2A2E"></div></td></tr>
<tr><td style="padding:20px 28px 4px;background:#111114"><div style="font-size:11px;color:#C8102E;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:12px">DATOS DEL CLIENTE</div></td></tr>
<tr><td style="padding:0 28px 16px;background:#111114"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;border:1px solid #2A2A2E;border-top:3px solid #C8102E;border-radius:6px">{$rows}</table></td></tr>
{$paymentSection}
<tr><td style="padding:8px 28px 20px;background:#111114" align="center">
  <a href="{$dashboardUrl}" target="_blank" style="display:inline-block;background:#C8102E;color:#ffffff;text-decoration:none;padding:12px 32px;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;margin-top:8px">VER EN PANEL ADMIN &rarr;</a>
</td></tr>
<tr><td style="padding:14px 28px;text-align:center;border-top:1px solid #2A2A2E;background:#0A0A0A">
  <div style="font-size:10px;color:#52525B;letter-spacing:1px">&copy; {$year} WellCore Fitness &middot; Notificacion automatica para contabilidad</div>
</td></tr>
</table></td></tr></table></body></html>
HTML;

$results = [];
foreach (['analyticfitness@gmail.com', 'silviagomezroa4@gmail.com'] as $to) {
    $r = sendEmail($to, $subject, $html);
    $results[] = ['to' => $to, 'ok' => $r['ok'], 'error' => $r['error'] ?? null];
}

echo json_encode(['results' => $results], JSON_PRETTY_PRINT);
