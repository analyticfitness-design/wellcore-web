<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Notificacion de nuevo registro al admin
 * Envia email a info@wellcorefitness.com cuando se registra un cliente.
 */

require_once __DIR__ . '/email.php';

function notifyAdminNewClient(array $client, string $source = 'web'): void {
    $adminEmail = 'info@wellcorefitness.com';
    $name       = $client['name']  ?? 'Sin nombre';
    $email      = $client['email'] ?? 'Sin email';
    $plan       = strtoupper($client['plan'] ?? 'N/A');
    $code       = $client['code']  ?? '-';
    $date       = date('d/m/Y H:i');

    $sourceLabels = [
        'rise_enroll'  => 'Inscripcion publica RISE',
        'invitation'   => 'Codigo de invitacion',
        'wompi'        => 'Pago Wompi aprobado',
        'web'          => 'Registro web',
    ];
    $sourceLabel = $sourceLabels[$source] ?? $source;

    $year = date('Y');
    $dashboardUrl = 'https://wellcorefitness.com/admin.html';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nuevo Cliente Registrado | WellCore Fitness</title>
</head>
<body style="margin:0;padding:0;background:#0A0A0A;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;padding:20px 10px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#111114;border:1px solid #2A2A2E">

<!-- Red top bar -->
<tr><td style="background:#C8102E;padding:4px 0;font-size:0;line-height:0">&nbsp;</td></tr>

<!-- Logo -->
<tr><td style="padding:24px 28px 14px;text-align:center;background:#111114">
  <table role="presentation" cellpadding="0" cellspacing="0" align="center">
  <tr>
    <td style="font-size:22px;font-weight:700;color:#FFFFFF;letter-spacing:3px">WELL</td>
    <td style="font-size:22px;font-weight:700;color:#C8102E;letter-spacing:3px">[CORE]</td>
  </tr>
  </table>
  <div style="font-size:9px;color:#71717A;letter-spacing:2px;margin-top:4px;text-transform:uppercase">NUEVO REGISTRO</div>
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 28px"><div style="border-top:1px solid #2A2A2E"></div></td></tr>

<!-- Content -->
<tr><td style="padding:20px 28px;background:#111114">
  <div style="font-size:11px;color:#C8102E;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:12px">CLIENTE NUEVO</div>
  <div style="font-size:18px;font-weight:700;color:#FFFFFF;line-height:1.3;margin-bottom:16px">
    Se ha registrado un nuevo cliente
  </div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;border:1px solid #2A2A2E;border-radius:6px">
    <tr>
      <td style="padding:12px 16px;border-bottom:1px solid #2A2A2E">
        <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Nombre</div>
        <div style="font-size:14px;color:#FFFFFF;font-weight:600;margin-top:2px">{$name}</div>
      </td>
    </tr>
    <tr>
      <td style="padding:12px 16px;border-bottom:1px solid #2A2A2E">
        <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Email</div>
        <div style="font-size:14px;color:#FFFFFF;margin-top:2px">{$email}</div>
      </td>
    </tr>
    <tr>
      <td style="padding:12px 16px;border-bottom:1px solid #2A2A2E">
        <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Plan</div>
        <div style="font-size:14px;color:#C8102E;font-weight:700;margin-top:2px">{$plan}</div>
      </td>
    </tr>
    <tr>
      <td style="padding:12px 16px;border-bottom:1px solid #2A2A2E">
        <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Codigo</div>
        <div style="font-size:14px;color:#D4D4D8;font-family:monospace;margin-top:2px">{$code}</div>
      </td>
    </tr>
    <tr>
      <td style="padding:12px 16px;border-bottom:1px solid #2A2A2E">
        <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Origen</div>
        <div style="font-size:14px;color:#D4D4D8;margin-top:2px">{$sourceLabel}</div>
      </td>
    </tr>
    <tr>
      <td style="padding:12px 16px">
        <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Fecha</div>
        <div style="font-size:14px;color:#D4D4D8;margin-top:2px">{$date}</div>
      </td>
    </tr>
  </table>
</td></tr>

<!-- CTA -->
<tr><td style="padding:8px 28px 20px;background:#111114" align="center">
  <a href="{$dashboardUrl}" target="_blank" style="display:inline-block;background:#C8102E;color:#ffffff;text-decoration:none;padding:12px 32px;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;margin-top:8px">
    VER EN PANEL &rarr;
  </a>
</td></tr>

<!-- Footer -->
<tr><td style="padding:14px 28px;text-align:center;border-top:1px solid #2A2A2E;background:#0A0A0A">
  <div style="font-size:10px;color:#52525B;letter-spacing:1px">
    &copy; {$year} WellCore Fitness &middot; Notificacion automatica
  </div>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

    $subject = "Nuevo cliente: {$name} — Plan {$plan}";

    // Fire and forget — no bloqueamos si falla
    @sendEmail($adminEmail, $subject, $html);
}
