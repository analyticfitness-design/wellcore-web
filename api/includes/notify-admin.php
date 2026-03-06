<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Notificacion de nuevo registro al admin
 * Envia email a info@wellcorefitness.com cuando se registra un cliente.
 * Incluye datos personales + datos de pago para contabilidad.
 */

require_once __DIR__ . '/email.php';

/**
 * @param array $client  Datos del cliente:
 *   - name, email, plan, code (obligatorios)
 *   - phone (opcional)
 *   - gender, experience_level, training_location (opcional, RISE)
 * @param string $source  Origen: rise_enroll, invitation, wompi, web
 * @param array $payment  Datos de pago (opcional, solo Wompi):
 *   - amount (en centavos), currency, method, reference, wompi_id
 */
function notifyAdminNewClient(array $client, string $source = 'web', array $payment = []): void {
    $adminEmail = 'info@wellcorefitness.com';
    $name       = $client['name']  ?? 'Sin nombre';
    $email      = $client['email'] ?? 'Sin email';
    $plan       = strtoupper($client['plan'] ?? 'N/A');
    $code       = $client['code']  ?? '-';
    $phone      = $client['phone'] ?? '';
    $gender     = $client['gender'] ?? '';
    $level      = $client['experience_level'] ?? '';
    $location   = $client['training_location'] ?? '';
    $date       = date('d/m/Y H:i');

    $sourceLabels = [
        'rise_enroll'  => 'Inscripcion publica RISE',
        'invitation'   => 'Codigo de invitacion',
        'wompi'        => 'Pago Wompi aprobado',
        'web'          => 'Registro web',
    ];
    $sourceLabel = $sourceLabels[$source] ?? $source;

    $genderLabels   = ['male' => 'Masculino', 'female' => 'Femenino', 'other' => 'Otro', 'hombre' => 'Masculino', 'mujer' => 'Femenino'];
    $locationLabels = ['gym' => 'Gimnasio', 'home' => 'Casa', 'hybrid' => 'Mixto', 'ambos' => 'Mixto'];
    $levelLabels    = ['principiante' => 'Principiante', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'];

    $year = date('Y');
    $dashboardUrl = 'https://wellcorefitness.com/admin.html';

    // Construir filas de la tabla
    $rows = '';
    $rows .= _notifyRow('Nombre', $name, true);
    $rows .= _notifyRow('Email', $email);
    if ($phone)   $rows .= _notifyRow('Telefono / WhatsApp', $phone);
    if ($gender)  $rows .= _notifyRow('Genero', $genderLabels[$gender] ?? $gender);
    $rows .= _notifyRow('Plan', $plan, false, '#C8102E');
    $rows .= _notifyRow('Codigo cliente', $code, false, '', true);
    if ($level)    $rows .= _notifyRow('Nivel', $levelLabels[$level] ?? $level);
    if ($location) $rows .= _notifyRow('Lugar entrenamiento', $locationLabels[$location] ?? $location);
    $rows .= _notifyRow('Origen del registro', $sourceLabel);
    $rows .= _notifyRow('Fecha y hora', $date);

    // Seccion de pago (solo si hay datos)
    $paymentSection = '';
    if (!empty($payment)) {
        $amountCents = (int)($payment['amount'] ?? 0);
        $currency    = $payment['currency'] ?? 'COP';
        $method      = $payment['method'] ?? '-';
        $reference   = $payment['reference'] ?? '-';
        $wompiId     = $payment['wompi_id'] ?? '-';

        $methodLabels = [
            'CARD'            => 'Tarjeta de credito/debito',
            'NEQUI'           => 'Nequi',
            'BANCOLOMBIA_TRANSFER' => 'Transferencia Bancolombia',
            'PSE'             => 'PSE',
            'BANCOLOMBIA_COLLECT'  => 'Corresponsal Bancolombia',
        ];
        $methodLabel = $methodLabels[$method] ?? $method;

        if ($currency === 'COP') {
            $amountFormatted = '$' . number_format($amountCents / 100, 0, ',', '.') . ' COP';
        } else {
            $amountFormatted = '$' . number_format($amountCents / 100, 2, '.', ',') . ' ' . $currency;
        }

        $paymentSection = <<<PAYMENT
<tr><td style="padding:16px 28px 4px;background:#111114">
  <div style="font-size:11px;color:#22C55E;letter-spacing:2px;text-transform:uppercase;font-weight:700">DATOS DE PAGO</div>
</td></tr>
<tr><td style="padding:8px 28px 16px;background:#111114">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;border:1px solid #2A2A2E;border-top:3px solid #22C55E;border-radius:6px">
PAYMENT;
        $paymentSection .= _notifyRow('Monto pagado', $amountFormatted, true, '#22C55E');
        $paymentSection .= _notifyRow('Moneda', $currency);
        $paymentSection .= _notifyRow('Metodo de pago', $methodLabel);
        $paymentSection .= _notifyRow('Referencia', $reference, false, '', true);
        $paymentSection .= _notifyRow('ID transaccion Wompi', $wompiId, false, '', true, true);
        $paymentSection .= '</table></td></tr>';
    }

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
  <div style="font-size:9px;color:#71717A;letter-spacing:2px;margin-top:4px;text-transform:uppercase">NUEVO REGISTRO &middot; NOTIFICACION CONTABLE</div>
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 28px"><div style="border-top:1px solid #2A2A2E"></div></td></tr>

<!-- Client info -->
<tr><td style="padding:20px 28px 4px;background:#111114">
  <div style="font-size:11px;color:#C8102E;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:12px">DATOS DEL CLIENTE</div>
</td></tr>
<tr><td style="padding:0 28px 16px;background:#111114">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;border:1px solid #2A2A2E;border-top:3px solid #C8102E;border-radius:6px">
    {$rows}
  </table>
</td></tr>

<!-- Payment info (conditional) -->
{$paymentSection}

<!-- CTA -->
<tr><td style="padding:8px 28px 20px;background:#111114" align="center">
  <a href="{$dashboardUrl}" target="_blank" style="display:inline-block;background:#C8102E;color:#ffffff;text-decoration:none;padding:12px 32px;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;margin-top:8px">
    VER EN PANEL ADMIN &rarr;
  </a>
</td></tr>

<!-- Footer -->
<tr><td style="padding:14px 28px;text-align:center;border-top:1px solid #2A2A2E;background:#0A0A0A">
  <div style="font-size:10px;color:#52525B;letter-spacing:1px">
    &copy; {$year} WellCore Fitness &middot; Notificacion automatica para contabilidad
  </div>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

    $subject = "Nuevo cliente: {$name} — Plan {$plan}";
    if (!empty($payment)) {
        $amountCents = (int)($payment['amount'] ?? 0);
        $cur = $payment['currency'] ?? 'COP';
        if ($cur === 'COP') {
            $amt = '$' . number_format($amountCents / 100, 0, ',', '.') . ' COP';
        } else {
            $amt = '$' . number_format($amountCents / 100, 2, '.', ',') . ' ' . $cur;
        }
        $subject = "Nuevo pago: {$name} — {$plan} — {$amt}";
    }

    @sendEmail($adminEmail, $subject, $html);
}

/** Helper para generar una fila de la tabla */
function _notifyRow(string $label, string $value, bool $bold = false, string $color = '', bool $mono = false, bool $isLast = false): string {
    $border = $isLast ? '' : 'border-bottom:1px solid #2A2A2E;';
    $style  = 'font-size:14px;margin-top:2px;';
    $style .= $color ? "color:{$color};" : 'color:#D4D4D8;';
    $style .= $bold ? 'font-weight:700;' : '';
    $style .= $mono ? 'font-family:monospace;' : '';
    return "<tr><td style=\"padding:10px 16px;{$border}\"><div style=\"font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px\">{$label}</div><div style=\"{$style}\">{$value}</div></td></tr>";
}
