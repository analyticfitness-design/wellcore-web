<?php
/**
 * ============================================================
 * WELLCORE FITNESS - AUTO-RENEWAL CRON (M16)
 * ============================================================
 * Cobra automaticamente a clientes con tarjeta guardada
 * cuya suscripcion vence en los proximos 3 dias.
 *
 * Ejecutar diariamente (recomendado: 7am):
 *   0 7 * * * php /code/api/cron/auto-renewal.php
 *
 * Logica de elegibilidad:
 *   - plan activo (no rise - esos son 30 dias fijos)
 *   - fecha_vencimiento entre HOY y HOY+3
 *   - tiene payment_method activo (is_active=1)
 *   - NO tiene auto_charge_log exitoso en los ultimos 7 dias
 * ============================================================
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../wompi/config.php';

// -------------------------------------------------------
// LOGGING
// -------------------------------------------------------
$logDir = sys_get_temp_dir() . '/wc_wompi_logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$cronLog = $logDir . '/auto-renewal.log';

function ar_log(string $level, string $message, array $ctx = []): void {
    global $cronLog;
    $line = sprintf(
        "[%s] [%s] %s %s\n",
        date('Y-m-d H:i:s'),
        $level,
        $message,
        empty($ctx) ? '' : json_encode($ctx, JSON_UNESCAPED_UNICODE)
    );
    file_put_contents($cronLog, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

ar_log('INFO', 'Auto-renewal cron started');

// -------------------------------------------------------
// HELPER: COBRAR CON TOKEN VIA WOMPI API
// Retorna ['ok' => bool, 'status' => string, 'wompi_tx_id' => string|null, 'error' => string|null]
// -------------------------------------------------------
function do_charge(PDO $db, int $clientId, int $pmId, string $pmToken, string $clientEmail, string $clientName, string $plan, int $amountCents, string $reference): array
{
    $apiUrl = wompi_api_url() . '/transactions';

    $wompiBody = json_encode([
        'amount_in_cents' => $amountCents,
        'currency'        => 'COP',
        'customer_email'  => $clientEmail,
        'reference'       => $reference,
        'payment_method'  => [
            'type'         => 'CARD',
            'token'        => $pmToken,
            'installments' => 1,
        ],
        'customer_data'   => [
            'full_name'    => $clientName,
            'phone_number' => '',
        ],
        'metadata'        => [
            'client_id'   => (string) $clientId,
            'auto_charge' => 'true',
            'plan'        => $plan,
        ],
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $wompiBody);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     [
        'Content-Type: application/json',
        'Authorization: Bearer ' . WOMPI_PRIVATE_KEY,
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $rawResponse = curl_exec($ch);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr     = curl_error($ch);
    curl_close($ch);

    $logStatus    = 'failed';
    $wompiTxId    = null;
    $errorMessage = null;

    if ($curlErr) {
        $errorMessage = 'cURL error: ' . $curlErr;
    } else {
        $responseData = json_decode($rawResponse, true);
        $wompiTx      = $responseData['data'] ?? null;
        $wompiTxId    = $wompiTx['id'] ?? null;
        $wompiStatus  = strtoupper($wompiTx['status'] ?? '');

        if ($httpCode >= 200 && $httpCode < 300 && in_array($wompiStatus, ['APPROVED', 'PENDING'], true)) {
            $logStatus = ($wompiStatus === 'APPROVED') ? 'success' : 'pending';
        } else {
            $errorReason  = $wompiTx['error']['reason'] ?? '';
            $errorMessage = 'Wompi HTTP ' . $httpCode . ($errorReason ? ': ' . $errorReason : '');
        }
    }

    // Registrar en auto_charge_log
    try {
        $db->prepare("
            INSERT INTO auto_charge_log
                (client_id, payment_method_id, amount_cents, status, wompi_transaction_id, error_message)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $clientId,
            $pmId,
            $amountCents,
            $logStatus,
            $wompiTxId,
            $errorMessage,
        ]);
    } catch (\Throwable $logErr) {
        ar_log('WARNING', 'Error insertando auto_charge_log', ['error' => $logErr->getMessage()]);
    }

    return [
        'ok'          => ($logStatus === 'success'),
        'status'      => $logStatus,
        'wompi_tx_id' => $wompiTxId,
        'error'       => $errorMessage,
    ];
}

// -------------------------------------------------------
// HELPER: EMAIL DE RENOVACION EXITOSA
// -------------------------------------------------------
function send_renewal_success_email(string $email, string $name, string $plan, int $amountCents, string $reference): void
{
    $planLabels = ['esencial' => 'ESENCIAL', 'metodo' => 'METODO', 'elite' => 'ELITE'];
    $planLabel  = $planLabels[$plan] ?? strtoupper($plan);
    $amountCop  = '$' . number_format($amountCents / 100, 0, '.', '.') . ' COP';
    $firstName  = htmlspecialchars(explode(' ', trim($name))[0], ENT_QUOTES, 'UTF-8');
    $dashUrl    = 'https://wellcorefitness.com/cliente.html';
    $year       = date('Y');
    $safePlan   = htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8');
    $safeAmount = htmlspecialchars($amountCop, ENT_QUOTES, 'UTF-8');
    $safeRef    = htmlspecialchars($reference, ENT_QUOTES, 'UTF-8');

    $html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#0A0A0A;font-family:Arial,Helvetica,sans-serif">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;padding:20px 10px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#111114;border:1px solid #2A2A2E">'
        . '<tr><td style="background:#E31E24;padding:3px 0;font-size:0">&nbsp;</td></tr>'
        . '<tr><td style="padding:32px;text-align:center;background:#111114">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>'
        . '<td style="font-size:28px;font-weight:700;color:#FFFFFF;letter-spacing:3px">WELL</td>'
        . '<td style="font-size:28px;font-weight:700;color:#E31E24;letter-spacing:3px">[CORE]</td>'
        . '</tr></table>'
        . '<div style="font-size:9px;color:#71717A;letter-spacing:3px;margin-top:4px;text-transform:uppercase">RENOVACION AUTOMATICA</div></td></tr>'
        . '<tr><td style="padding:28px 32px 20px;background:#111114">'
        . '<div style="font-size:11px;color:#22C55E;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:12px">&#10003; RENOVACION EXITOSA</div>'
        . '<div style="font-size:22px;font-weight:700;color:#FFFFFF;line-height:1.3;margin-bottom:16px">Tu plan fue renovado, ' . $firstName . '</div>'
        . '<div style="font-size:14px;color:#A1A1AA;line-height:1.7">Tu suscripcion WellCore se ha renovado automaticamente. Sigues en marcha por otro mes.</div>'
        . '</td></tr>'
        . '<tr><td style="padding:0 32px 20px;background:#111114">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;border:1px solid #2A2A2E;border-top:3px solid #E31E24">'
        . '<tr><td style="padding:10px 16px;border-bottom:1px solid #2A2A2E"><div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Plan</div>'
        . '<div style="font-size:16px;color:#E31E24;font-weight:700;margin-top:2px">' . $safePlan . '</div></td></tr>'
        . '<tr><td style="padding:10px 16px;border-bottom:1px solid #2A2A2E"><div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Monto cobrado</div>'
        . '<div style="font-size:14px;color:#D4D4D8;margin-top:2px">' . $safeAmount . '</div></td></tr>'
        . '<tr><td style="padding:10px 16px"><div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Referencia</div>'
        . '<div style="font-size:14px;color:#D4D4D8;font-family:monospace;margin-top:2px">' . $safeRef . '</div></td></tr>'
        . '</table></td></tr>'
        . '<tr><td style="padding:0 32px 24px;background:#111114" align="center">'
        . '<a href="' . $dashUrl . '" target="_blank" style="display:inline-block;background:#E31E24;color:#ffffff;text-decoration:none;padding:16px 48px;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase">VER MI PANEL &rarr;</a>'
        . '</td></tr>'
        . '<tr><td style="padding:20px 32px;text-align:center;background:#0A0A0A">'
        . '<div style="font-size:11px;color:#3F3F46;line-height:1.8"><strong style="color:#52525B">WellCore Fitness</strong><br>'
        . '<a href="https://wellcorefitness.com" style="color:#3F3F46;text-decoration:none">wellcorefitness.com</a>'
        . '</div><div style="font-size:10px;color:#27272A;margin-top:10px">&copy; ' . $year . ' WellCore Fitness. Para cancelar tu suscripcion contactanos por WhatsApp.</div>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';

    sendEmail($email, 'Tu plan WellCore fue renovado - ' . $planLabel, $html);
}

// -------------------------------------------------------
// HELPER: EMAIL DE COBRO FALLIDO
// -------------------------------------------------------
function send_renewal_failed_email(string $email, string $name, string $plan, string $errorReason): void
{
    $firstName = htmlspecialchars(explode(' ', trim($name))[0], ENT_QUOTES, 'UTF-8');
    $planLabel = strtoupper($plan);
    $safePlan  = htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8');
    $updateUrl = 'https://wellcorefitness.com/cliente.html';
    $year      = date('Y');

    $html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#0A0A0A;font-family:Arial,Helvetica,sans-serif">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;padding:20px 10px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#111114;border:1px solid #2A2A2E">'
        . '<tr><td style="background:#E31E24;padding:3px 0;font-size:0">&nbsp;</td></tr>'
        . '<tr><td style="padding:32px;text-align:center;background:#111114">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>'
        . '<td style="font-size:28px;font-weight:700;color:#FFFFFF;letter-spacing:3px">WELL</td>'
        . '<td style="font-size:28px;font-weight:700;color:#E31E24;letter-spacing:3px">[CORE]</td>'
        . '</tr></table>'
        . '<div style="font-size:9px;color:#71717A;letter-spacing:3px;margin-top:4px;text-transform:uppercase">AVISO DE PAGO</div></td></tr>'
        . '<tr><td style="padding:28px 32px 20px;background:#111114">'
        . '<div style="font-size:11px;color:#E31E24;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:12px">&#9888; PROBLEMA CON TU PAGO</div>'
        . '<div style="font-size:22px;font-weight:700;color:#FFFFFF;line-height:1.3;margin-bottom:16px">No pudimos renovar tu plan, ' . $firstName . '</div>'
        . '<div style="font-size:14px;color:#A1A1AA;line-height:1.7">Intentamos renovar automaticamente tu plan <strong style="color:#fff">' . $safePlan . '</strong>,'
        . ' pero el cobro no pudo procesarse. Para continuar sin interrupciones, actualiza tu metodo de pago o realiza el pago manualmente.</div>'
        . '</td></tr>'
        . '<tr><td style="padding:0 32px 24px;background:#111114" align="center">'
        . '<a href="' . $updateUrl . '" target="_blank" style="display:inline-block;background:#E31E24;color:#ffffff;text-decoration:none;padding:16px 48px;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase">ACTUALIZAR PAGO &rarr;</a>'
        . '</td></tr>'
        . '<tr><td style="padding:0 32px 24px;background:#111114">'
        . '<div style="font-size:13px;color:#71717A;line-height:1.6;padding:12px 16px;background:#0A0A0A;border:1px solid #2A2A2E;border-left:3px solid #E31E24">'
        . 'Si necesitas ayuda, escribenos por WhatsApp: <a href="https://wa.me/573124904720" style="color:#00D9FF;text-decoration:none">+57 312 490 4720</a>'
        . '</div></td></tr>'
        . '<tr><td style="padding:20px 32px;text-align:center;background:#0A0A0A">'
        . '<div style="font-size:11px;color:#3F3F46;line-height:1.8"><strong style="color:#52525B">WellCore Fitness</strong><br>'
        . '<a href="https://wellcorefitness.com" style="color:#3F3F46;text-decoration:none">wellcorefitness.com</a>'
        . '</div><div style="font-size:10px;color:#27272A;margin-top:10px">&copy; ' . $year . ' WellCore Fitness.</div>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';

    sendEmail($email, 'Problema con la renovacion de tu plan WellCore - Accion requerida', $html);
}

// -------------------------------------------------------
// BUSCAR CLIENTES ELEGIBLES PARA AUTO-RENOVACION
// -------------------------------------------------------
$db = getDB();

$eligibleClients = $db->query("
    SELECT
        c.id,
        c.name,
        c.email,
        c.plan,
        DATE_ADD(COALESCE(c.fecha_inicio, c.created_at), INTERVAL 30 DAY) AS subscription_end,
        pm.id        AS pm_id,
        pm.token_id  AS pm_token,
        pm.card_brand,
        pm.last_four
    FROM clients c
    JOIN payment_methods pm ON pm.client_id = c.id AND pm.is_active = 1
    WHERE c.status = 'activo'
      AND c.plan NOT IN ('rise')
      AND DATE_ADD(COALESCE(c.fecha_inicio, c.created_at), INTERVAL 30 DAY)
          <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
      AND DATE_ADD(COALESCE(c.fecha_inicio, c.created_at), INTERVAL 30 DAY)
          >= DATE_ADD(CURDATE(), INTERVAL -7 DAY)
      AND NOT EXISTS (
          SELECT 1 FROM auto_charge_log acl
          WHERE acl.client_id = c.id
            AND acl.status IN ('success', 'pending')
            AND acl.attempt_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      )
    ORDER BY pm.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Deduplicar: un cliente con multiples payment_methods activos solo se procesa una vez
// (el JOIN puede devolver multiples filas por cliente, ORDER BY created_at DESC ya prioriza el mas reciente)
$seen = [];
$eligibleClients = array_values(array_filter($eligibleClients, function ($c) use (&$seen) {
    if (isset($seen[$c['id']])) return false;
    $seen[$c['id']] = true;
    return true;
}));

ar_log('INFO', 'Clientes elegibles encontrados', ['count' => count($eligibleClients)]);

// -------------------------------------------------------
// PLANES - MONTOS EN CENTAVOS COP
// -------------------------------------------------------
$planAmounts = [
    'esencial' => 39900000,
    'metodo'   => 50400000,
    'elite'    => 63000000,
];

$charged = 0;
$failed  = 0;

foreach ($eligibleClients as $client) {
    $cid         = (int) $client['id'];
    $plan        = $client['plan'];
    $amountCents = $planAmounts[$plan] ?? 39900000;
    $reference   = 'wc-renewal-' . $cid . '-' . date('Ymd');

    ar_log('INFO', 'Procesando cliente', [
        'client_id' => $cid,
        'email'     => $client['email'],
        'plan'      => $plan,
        'expires'   => $client['subscription_end'],
        'brand'     => $client['card_brand'],
        'last_four' => $client['last_four'],
    ]);

    $result = do_charge(
        $db,
        $cid,
        (int) $client['pm_id'],
        $client['pm_token'],
        $client['email'],
        $client['name'],
        $plan,
        $amountCents,
        $reference
    );

    if ($result['ok']) {
        // Renovar periodo: actualizar fecha_inicio a hoy
        try {
            $db->prepare('UPDATE clients SET fecha_inicio = CURDATE() WHERE id = ?')
               ->execute([$cid]);
        } catch (\Throwable $updateErr) {
            ar_log('WARNING', 'Error actualizando fecha_inicio', [
                'client_id' => $cid,
                'error'     => $updateErr->getMessage(),
            ]);
        }

        // Email de confirmacion al cliente
        try {
            send_renewal_success_email(
                $client['email'],
                $client['name'],
                $plan,
                $amountCents,
                $reference
            );
        } catch (\Throwable $emailErr) {
            ar_log('WARNING', 'Error enviando email de renovacion exitosa', ['error' => $emailErr->getMessage()]);
        }

        $charged++;
        ar_log('INFO', 'Renovacion EXITOSA', [
            'client_id'    => $cid,
            'wompi_tx_id'  => $result['wompi_tx_id'],
            'amount_cents' => $amountCents,
        ]);
    } else {
        // Email de fallo al cliente
        try {
            send_renewal_failed_email(
                $client['email'],
                $client['name'],
                $plan,
                $result['error'] ?? 'Error desconocido'
            );
        } catch (\Throwable $emailErr) {
            ar_log('WARNING', 'Error enviando email de fallo', ['error' => $emailErr->getMessage()]);
        }

        $failed++;
        ar_log('WARNING', 'Renovacion FALLIDA', [
            'client_id' => $cid,
            'error'     => $result['error'],
        ]);
    }
}

ar_log('INFO', 'Auto-renewal cron finalizado', [
    'charged' => $charged,
    'failed'  => $failed,
    'total'   => count($eligibleClients),
]);
