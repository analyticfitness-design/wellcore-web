<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
/**
 * ============================================================
 * WELLCORE FITNESS — WEBHOOK DE CONFIRMACION WOMPI
 * ============================================================
 * POST /api/wompi/webhook.php
 *
 * Wompi llama a este endpoint cuando el estado de un pago
 * cambia (server-to-server). NO es el redirect al cliente.
 *
 * PAYLOAD JSON de Wompi (evento transaction.updated):
 * {
 *   "event":     "transaction.updated",
 *   "data": {
 *     "transaction": {
 *       "id":                    string,
 *       "reference":             string,  (nuestro referenceCode)
 *       "status":                string,  APPROVED|DECLINED|PENDING|VOIDED|ERROR
 *       "amount_in_cents":       int,
 *       "currency":              string,
 *       "payment_method_type":   string,
 *       "customer_email":        string,
 *       "customer_data": { "full_name", "phone_number" },
 *       ...
 *     }
 *   },
 *   "signature": {
 *     "checksum":    string,  SHA256 de verificacion
 *     "properties":  array
 *   },
 *   "timestamp": int,
 *   "sent_at":   string
 * }
 *
 * VERIFICACION DE FIRMA WOMPI:
 *   Concatenar los valores de signature.properties en orden
 *   + timestamp + eventsKey
 *   SHA256 del string resultante = checksum esperado
 *
 * ESTADOS:
 *   APPROVED — Pago aprobado
 *   DECLINED — Pago rechazado
 *   PENDING  — Pago pendiente (PSE, Efecty, etc.)
 *   VOIDED   — Pago anulado
 *   ERROR    — Error en la transaccion
 * ============================================================
 */

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// -------------------------------------------------------
// DEPENDENCIAS
// -------------------------------------------------------
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/transactions.php';

// -------------------------------------------------------
// LOGS
// -------------------------------------------------------
// Usar /tmp/ para logs transitorios (siempre writable en contenedores Docker)
$logDir     = sys_get_temp_dir() . '/wc_wompi_logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$errorLog   = $logDir . '/errors.log';
$webhookLog = $logDir . '/webhooks.log';

function wc_log(string $file, string $level, string $message, array $context = []): void {
    $entry = sprintf(
        "[%s] [%s] %s %s\n",
        date('Y-m-d H:i:s'),
        $level,
        $message,
        empty($context) ? '' : json_encode($context, JSON_UNESCAPED_UNICODE)
    );
    file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
}

// -------------------------------------------------------
// LEER BODY JSON
// -------------------------------------------------------
$rawBody = file_get_contents('php://input');
if (empty($rawBody)) {
    http_response_code(400);
    exit('Bad Request: empty body');
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    wc_log($errorLog, 'ERROR', 'Webhook body no es JSON valido');
    http_response_code(400);
    exit('Bad Request: invalid JSON');
}

wc_log($webhookLog, 'INFO', 'Webhook recibido', [
    'event'     => $payload['event'] ?? '',
    'timestamp' => $payload['timestamp'] ?? '',
]);

// -------------------------------------------------------
// VERIFICAR FIRMA WOMPI
// Formula: SHA256(prop1value + prop2value + ... + timestamp + eventsKey)
// Las propiedades estan en signature.properties en orden
// -------------------------------------------------------
$signature  = $payload['signature']  ?? [];
$checksum   = $signature['checksum'] ?? '';
$properties = $signature['properties'] ?? [];
$timestamp  = (string) ($payload['timestamp'] ?? '');

if (empty($checksum) || empty($properties) || empty($timestamp)) {
    wc_log($errorLog, 'WARNING', 'Webhook sin datos de firma', $signature);
    http_response_code(400);
    exit('Bad Request: missing signature data');
}

// Obtener los valores de las propiedades del objeto data.transaction
$transaction = $payload['data']['transaction'] ?? [];

$propValues = '';
foreach ($properties as $prop) {
    // Las propiedades son como "transaction.id", "transaction.status", etc.
    // Extraemos la parte despues del primer punto
    $parts = explode('.', $prop, 2);
    $key   = $parts[1] ?? $prop;
    $propValues .= (string) ($transaction[$key] ?? '');
}

$expectedChecksum = hash('sha256', $propValues . $timestamp . WOMPI_EVENTS_KEY);

if (!hash_equals(strtolower($expectedChecksum), strtolower($checksum))) {
    wc_log($errorLog, 'ERROR', 'Firma invalida en webhook Wompi', [
        'expected'  => $expectedChecksum,
        'received'  => $checksum,
        'reference' => $transaction['reference'] ?? '',
    ]);
    http_response_code(401);
    exit('Unauthorized: invalid signature');
}

// -------------------------------------------------------
// VALIDAR EVENTO
// -------------------------------------------------------
$event = $payload['event'] ?? '';
if ($event !== 'transaction.updated') {
    // Otros eventos (ej: nequi_token.updated): ignorar con 200
    http_response_code(200);
    exit('OK');
}

// -------------------------------------------------------
// EXTRAER DATOS DE LA TRANSACCION
// -------------------------------------------------------
$wompiTxId     = trim($transaction['id']                             ?? '');
$referenceCode = trim($transaction['reference']                     ?? '');
$statusWompi   = strtoupper(trim($transaction['status']             ?? ''));
$amountCents   = (int) ($transaction['amount_in_cents']             ?? 0);
$currency      = trim($transaction['currency']                      ?? 'COP');
$paymentMethod = trim($transaction['payment_method_type']          ?? '');
$buyerEmail    = strtolower(trim($transaction['customer_email']     ?? ''));
$customerData  = $transaction['customer_data'] ?? [];
$buyerName     = trim($customerData['full_name']  ?? '');
$buyerPhone    = trim($customerData['phone_number'] ?? '');

// Extraer datos del método de pago tokenizable (tarjeta)
$pmObject    = $transaction['payment_method'] ?? [];
$pmToken     = trim($pmObject['token'] ?? '');
$pmExtra     = $pmObject['extra'] ?? [];
$pmLastFour  = trim($pmExtra['last_four'] ?? '');
$pmBrand     = trim($pmExtra['brand']     ?? '');
$pmHolder    = trim($pmExtra['name']      ?? '');
$pmClientId  = (int) ($transaction['metadata']['client_id'] ?? 0);

if (empty($referenceCode) || empty($wompiTxId)) {
    wc_log($errorLog, 'WARNING', 'Webhook sin referencia o ID de transaccion', $transaction);
    http_response_code(400);
    exit('Bad Request: missing transaction data');
}

// -------------------------------------------------------
// MAPEAR ESTADO WOMPI A ESTADO INTERNO
// -------------------------------------------------------
$statusMap = [
    'APPROVED' => 'approved',
    'DECLINED' => 'declined',
    'PENDING'  => 'pending',
    'VOIDED'   => 'voided',
    'ERROR'    => 'error',
];
$status = $statusMap[$statusWompi] ?? 'pending';

wc_log($webhookLog, 'INFO', 'Transaccion procesada', [
    'reference' => $referenceCode,
    'wompi_id'  => $wompiTxId,
    'status'    => $status,
    'method'    => $paymentMethod,
    'amount'    => $amountCents,
]);

// -------------------------------------------------------
// ACTUALIZAR O CREAR LA TRANSACCION EN EL LOG LOCAL
// -------------------------------------------------------
$updates = [
    'status'                => $status,
    'wompi_transaction_id'  => $wompiTxId,
    'wompi_payment_method'  => $paymentMethod,
    'buyer_email'           => $buyerEmail  ?: null,
    'buyer_name'            => $buyerName   ?: null,
    'date_updated'          => date('c'),
];

$existing = transactions_find_by_reference($referenceCode);

if ($existing) {
    transactions_update($referenceCode, $updates);
} else {
    // Crear registro nuevo si el webhook llega antes que create-order
    preg_match('/^WC-(esencial|metodo|elite)-/', $referenceCode, $m);
    $plan     = $m[1] ?? 'esencial';
    $planData = WELLCORE_PLANS[$plan] ?? WELLCORE_PLANS['esencial'];

    transactions_append(array_merge([
        'id'             => generate_uuid(),
        'reference_code' => $referenceCode,
        'plan'           => $plan,
        'amount_in_cents'=> $amountCents,
        'amount_cop'     => $amountCents / 100,
        'currency'       => $currency,
        'buyer_name'     => $buyerName,
        'buyer_email'    => $buyerEmail,
        'buyer_phone'    => $buyerPhone,
        'date_created'   => date('c'),
    ], $updates));
}

// -------------------------------------------------------
// SI APPROVED: crear/activar cliente en MySQL + email
// -------------------------------------------------------
if ($status === 'approved') {
    $txData = transactions_find_by_reference($referenceCode);
    $plan   = $txData['plan'] ?? 'esencial';

    wc_log($webhookLog, 'INFO', 'Pago APROBADO — activando cliente', [
        'reference' => $referenceCode,
        'email'     => $buyerEmail,
        'plan'      => $plan,
        'method'    => $paymentMethod,
    ]);

    // Confirmar uso de código de descuento si aplica
    try {
        require_once __DIR__ . '/../config/database.php';
        $dcDb = getDB();
        $dcUsage = $dcDb->prepare("SELECT discount_code_id FROM discount_code_usage WHERE reference_code = ? AND payment_status = 'pending'");
        $dcUsage->execute([$referenceCode]);
        $dcRow = $dcUsage->fetch(PDO::FETCH_ASSOC);
        if ($dcRow) {
            $dcDb->prepare("UPDATE discount_code_usage SET payment_status = 'approved' WHERE reference_code = ?")->execute([$referenceCode]);
            $dcDb->prepare("UPDATE discount_codes SET times_used = times_used + 1 WHERE id = ?")->execute([$dcRow['discount_code_id']]);
            wc_log($webhookLog, 'INFO', 'Descuento confirmado', ['discount_code_id' => $dcRow['discount_code_id'], 'reference' => $referenceCode]);
        }
    } catch (\Throwable $e) {
        wc_log($webhookLog, 'WARN', 'Error actualizando descuento: ' . $e->getMessage());
    }

    // Crear o activar cliente en MySQL
    $tempPassword = null;
    try {
        require_once __DIR__ . '/../config/database.php';
        $db = getDB();

        $stmt = $db->prepare("SELECT id, status FROM clients WHERE email = ?");
        $stmt->execute([$buyerEmail]);
        $clientExists = $stmt->fetch();

        if ($clientExists) {
            $stmt = $db->prepare("UPDATE clients SET status = 'activo', plan = ?, updated_at = NOW() WHERE email = ?");
            $stmt->execute([$plan, $buyerEmail]);
            wc_log($webhookLog, 'INFO', 'Cliente existente activado', ['email' => $buyerEmail, 'plan' => $plan]);
        } else {
            // Generar codigo de cliente unico (race-safe)
            $stmt    = $db->query("SELECT MAX(CAST(SUBSTRING(client_code, 5) AS UNSIGNED)) as max_num FROM clients WHERE client_code LIKE 'cli-%'");
            $row     = $stmt->fetch();
            $nextNum = ($row['max_num'] ?? 0) + 1;
            $clientCode = 'cli-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

            $tempPassword = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 8);
            $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT, ['cost' => 12]);

            $nameParts = explode(' ', trim($buyerName), 2);
            $firstName = $nameParts[0] ?? 'Cliente';

            $stmt = $db->prepare("
                INSERT INTO clients (client_code, name, email, password_hash, must_change_password, plan, status, fecha_inicio)
                VALUES (?, ?, ?, ?, 1, ?, 'activo', CURDATE())
            ");
            $stmt->execute([$clientCode, trim($buyerName) ?: $firstName, $buyerEmail, $passwordHash, $plan]);
            $clientId = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO client_profiles (client_id) VALUES (?)");
            $stmt->execute([$clientId]);

            wc_log($webhookLog, 'INFO', 'Nuevo cliente creado', [
                'client_code' => $clientCode,
                'email'       => $buyerEmail,
                'plan'        => $plan,
            ]);
        }
    } catch (\Exception $e) {
        wc_log($errorLog, 'ERROR', 'Error al crear/activar cliente en MySQL', [
            'email' => $buyerEmail,
            'error' => $e->getMessage(),
        ]);
    }

    // Si es RISE: crear auth_token (30 días) y guardarlo en la transacción
    if ($plan === 'rise') {
        try {
            require_once __DIR__ . '/../config/database.php';
            $dbRise = getDB();

            $riseStmt = $dbRise->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
            $riseStmt->execute([$buyerEmail]);
            $riseClientId = (int) $riseStmt->fetchColumn();

            if ($riseClientId) {
                $riseToken  = bin2hex(random_bytes(32));
                $riseExpiry = date('Y-m-d H:i:s', strtotime('+30 days'));

                // Revocar tokens anteriores del cliente
                $dbRise->prepare("DELETE FROM auth_tokens WHERE user_type = 'client' AND user_id = ?")
                       ->execute([$riseClientId]);

                $dbRise->prepare("
                    INSERT INTO auth_tokens (user_type, user_id, token, expires_at, created_at)
                    VALUES ('client', ?, ?, ?, NOW())
                ")->execute([$riseClientId, $riseToken, $riseExpiry]);

                // Guardar token en la transacción local para que rise-session.php lo entregue
                transactions_update($referenceCode, ['rise_token' => $riseToken]);

                wc_log($webhookLog, 'INFO', 'RISE auth_token creado', [
                    'client_id' => $riseClientId,
                    'email'     => $buyerEmail,
                    'expires'   => $riseExpiry,
                ]);

                // Email de bienvenida — pago confirmado
                try {
                    require_once __DIR__ . '/../includes/email.php';
                    require_once __DIR__ . '/../emails/templates.php';

                    // Detectar género desde rise_programs
                    $genderRow = $dbRise->prepare("SELECT gender FROM rise_programs WHERE client_id = ? ORDER BY id DESC LIMIT 1");
                    $genderRow->execute([$riseClientId]);
                    $gRow = $genderRow->fetch(PDO::FETCH_ASSOC);
                    $gender = ($gRow && in_array($gRow['gender'] ?? '', ['female', 'mujer', 'f'], true)) ? 'female' : 'male';

                    $emailHtml = email_rise_payment_confirmed($buyerName ?: $buyerEmail, $gender);
                    $subjPrefix = ($gender === 'female') ? 'Bienvenida' : 'Bienvenido';
                    sendEmail(
                        $buyerEmail,
                        "{$subjPrefix} al Reto RISE — Pago confirmado ✓",
                        $emailHtml
                    );
                } catch (\Throwable $emailErr) {
                    wc_log($errorLog, 'WARNING', 'Error enviando email bienvenida RISE', ['error' => $emailErr->getMessage()]);
                }
            }
        } catch (\Throwable $riseErr) {
            wc_log($errorLog, 'WARNING', 'Error creando auth_token RISE (no critico)', [
                'error' => $riseErr->getMessage(),
            ]);
        }
    }

    // Encolar generacion IA
    try {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../config/ai.php';
        require_once __DIR__ . '/../ai/helpers.php';

        $dbForAi = getDB();
        $aiStmt  = $dbForAi->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
        $aiStmt->execute([$buyerEmail]);
        $aiClientId = (int) $aiStmt->fetchColumn();

        if ($aiClientId && AI_ENABLED) {
            foreach (['entrenamiento', 'nutricion', 'habitos'] as $aiType) {
                ai_save_generation([
                    'client_id' => $aiClientId,
                    'type'      => $aiType,
                    'status'    => 'queued',
                ]);
            }
            wc_log($webhookLog, 'INFO', 'Generaciones IA encoladas', [
                'client_id' => $aiClientId,
                'email'     => $buyerEmail,
            ]);
        }
    } catch (\Throwable $aiErr) {
        wc_log($errorLog, 'WARNING', 'Error al encolar generaciones IA (no critico)', [
            'error' => $aiErr->getMessage(),
        ]);
    }

    // Enviar email de bienvenida
    $emailSent = send_welcome_email($buyerEmail, $buyerName, $plan, $referenceCode, $paymentMethod, $tempPassword);
    if (!$emailSent) {
        wc_log($errorLog, 'WARNING', 'No se pudo enviar email de bienvenida', [
            'email'     => $buyerEmail,
            'reference' => $referenceCode,
        ]);
    }

    // Insertar/actualizar pago en MySQL (tabla payments)
    try {
        require_once __DIR__ . '/../config/database.php';
        $dbPay = getDB();

        // Obtener client_id
        $payClientStmt = $dbPay->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
        $payClientStmt->execute([$buyerEmail]);
        $payClientId = $payClientStmt->fetchColumn() ?: null;

        $dbPay->prepare("
            INSERT INTO payments (client_id, email, wompi_reference, wompi_transaction_id,
                                  payment_method, plan, amount, currency, status,
                                  buyer_name, buyer_phone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                wompi_transaction_id = VALUES(wompi_transaction_id),
                payment_method = VALUES(payment_method),
                buyer_name = VALUES(buyer_name),
                buyer_phone = VALUES(buyer_phone),
                client_id = COALESCE(VALUES(client_id), client_id),
                updated_at = NOW()
        ")->execute([
            $payClientId,
            $buyerEmail,
            $referenceCode,
            $wompiTxId,
            $paymentMethod,
            $plan,
            $amountCents / 100,
            $currency,
            $status,
            $buyerName,
            $buyerPhone,
        ]);

        wc_log($webhookLog, 'INFO', 'Pago registrado en MySQL payments', [
            'reference' => $referenceCode,
            'amount'    => $amountCents / 100,
            'status'    => $status,
        ]);
    } catch (\Throwable $payErr) {
        wc_log($errorLog, 'WARNING', 'Error insertando en payments MySQL (no critico)', [
            'error' => $payErr->getMessage(),
        ]);
    }

    // Guardar token de pago para auto-renovacion (solo si el metodo es tokenizable)
    if (!empty($pmToken)) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $dbPm = getDB();

            // Resolver client_id: desde metadata o desde email
            $resolvedClientId = $pmClientId;
            if (!$resolvedClientId && !empty($buyerEmail)) {
                $pmCStmt = $dbPm->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
                $pmCStmt->execute([$buyerEmail]);
                $resolvedClientId = (int) $pmCStmt->fetchColumn();
            }

            if ($resolvedClientId) {
                $dbPm->prepare("
                    INSERT IGNORE INTO payment_methods
                        (client_id, token_id, last_four, card_brand, card_holder, is_active)
                    VALUES (?, ?, ?, ?, ?, 1)
                ")->execute([
                    $resolvedClientId,
                    $pmToken,
                    $pmLastFour ?: null,
                    $pmBrand    ?: null,
                    $pmHolder   ?: null,
                ]);

                wc_log($webhookLog, 'INFO', 'Token de pago guardado', [
                    'client_id' => $resolvedClientId,
                    'token'     => substr($pmToken, 0, 12) . '...',
                    'brand'     => $pmBrand,
                    'last_four' => $pmLastFour,
                ]);
            }
        } catch (\Throwable $pmErr) {
            wc_log($errorLog, 'WARNING', 'Error guardando token de pago (no critico)', [
                'error' => $pmErr->getMessage(),
            ]);
        }
    }

    // Notificar al admin del nuevo registro/pago (con datos contables)
    try {
        require_once __DIR__ . '/../includes/notify-admin.php';
        notifyAdminNewClient([
            'name' => $buyerName, 'email' => $buyerEmail, 'plan' => $plan,
            'code' => $referenceCode, 'phone' => $buyerPhone,
        ], 'wompi', [
            'amount'    => $amountCents,
            'currency'  => $currency,
            'method'    => $paymentMethod,
            'reference' => $referenceCode,
            'wompi_id'  => $wompiTxId,
        ]);
    } catch (\Throwable $notifyErr) {
        wc_log($errorLog, 'WARNING', 'Error notificacion admin (no critico)', ['error' => $notifyErr->getMessage()]);
    }
}

// -------------------------------------------------------
// RESPONDER 200 OK A WOMPI
// Wompi reintenta si no recibe 200 en 30 segundos
// -------------------------------------------------------
http_response_code(200);
echo 'OK';

// ============================================================
// FUNCION: ENVIAR EMAIL DE BIENVENIDA
// ============================================================
function send_welcome_email(
    string  $email,
    string  $name,
    string  $plan,
    string  $reference,
    string  $paymentMethod = '',
    ?string $tempPassword  = null
): bool {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

    $plans       = WELLCORE_PLANS;
    $planData    = $plans[$plan] ?? $plans['esencial'];
    $planDisplay = $planData['display'];
    $amount      = '$' . number_format($planData['amount_cop'], 0, '.', '.') . ' COP/mes';

    $methodLabels = [
        'CARD'                     => 'Tarjeta de credito/debito',
        'PSE'                      => 'PSE',
        'BANCOLOMBIA_TRANSFER'     => 'Transferencia Bancolombia',
        'NEQUI'                    => 'Nequi',
        'BANCOLOMBIA_COLLECT'      => 'Bancolombia',
        'EFECTY'                   => 'Efecty',
    ];
    $methodLabel = $methodLabels[strtoupper($paymentMethod)] ?? $paymentMethod;

    require_once __DIR__ . '/../includes/email.php';

    $subject  = 'Bienvenido a WellCore Fitness — Tu pago fue confirmado';
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $firstName = explode(' ', trim($safeName))[0];
    $loginUrl  = 'https://wellcorefitness.com/login.html';
    $year      = date('Y');

    $planColors = ['esencial' => '#60a5fa', 'metodo' => '#F5C842', 'elite' => '#E31E24', 'rise' => '#E31E24'];
    $planColor  = $planColors[$plan] ?? '#E31E24';

    // Credentials section (only for new accounts)
    $credentialsHtml = '';
    if ($tempPassword !== null) {
        $credentialsHtml = <<<CRED
<tr><td style="padding:0 32px 20px;background:#111114">
  <div style="font-size:11px;color:#E31E24;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:12px">TUS CREDENCIALES</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;border:1px solid #2A2A2E;border-top:3px solid #E31E24">
  <tr><td style="padding:10px 16px;border-bottom:1px solid #2A2A2E">
    <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Email</div>
    <div style="font-size:14px;color:#00D9FF;font-family:monospace;margin-top:2px">{$email}</div>
  </td></tr>
  <tr><td style="padding:10px 16px">
    <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Contrasena temporal</div>
    <div style="font-size:16px;color:#00D9FF;font-family:monospace;font-weight:700;letter-spacing:2px;margin-top:2px">{$tempPassword}</div>
  </td></tr>
  </table>
  <div style="margin-top:10px;padding:10px 14px;background:rgba(227,30,36,.08);border-left:2px solid #E31E24">
    <div style="font-size:11px;color:#a1a1aa;line-height:1.5"><strong style="color:#E31E24;">IMPORTANTE:</strong> Al ingresar por primera vez, el sistema te pedira crear una nueva contrasena personal. La contrasena temporal dejara de funcionar.</div>
  </div>
</td></tr>
CRED;
    } else {
        $credentialsHtml = <<<CRED
<tr><td style="padding:0 32px 20px;background:#111114">
  <div style="font-size:14px;color:#D4D4D8;line-height:1.7;padding:16px 20px;background:#0A0A0A;border:1px solid #2A2A2E;border-left:3px solid {$planColor}">
    Tu cuenta ya estaba activa. Ingresa con tus credenciales habituales en <a href="{$loginUrl}" style="color:#00D9FF;text-decoration:underline">wellcorefitness.com</a>
  </div>
</td></tr>
CRED;
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pago Confirmado | WellCore Fitness</title>
</head>
<body style="margin:0;padding:0;background:#0A0A0A;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%">

<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;overflow:hidden">
{$firstName}, tu pago fue confirmado. Plan {$planDisplay} activado.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;padding:20px 10px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#111114;border:1px solid #2A2A2E">

<!-- Red top bar -->
<tr><td style="background:#E31E24;padding:3px 0;font-size:0;line-height:0">&nbsp;</td></tr>

<!-- Logo -->
<tr><td style="padding:32px 32px 20px;text-align:center;background:#111114">
  <table role="presentation" cellpadding="0" cellspacing="0" align="center">
  <tr>
    <td style="font-size:28px;font-weight:700;color:#FFFFFF;letter-spacing:3px">WELL</td>
    <td style="font-size:28px;font-weight:700;color:#E31E24;letter-spacing:3px">[CORE]</td>
  </tr>
  </table>
  <div style="font-size:9px;color:#71717A;letter-spacing:3px;margin-top:4px;text-transform:uppercase">PAGO CONFIRMADO</div>
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 32px"><div style="border-top:1px solid #2A2A2E"></div></td></tr>

<!-- Welcome -->
<tr><td style="padding:28px 32px 20px;background:#111114">
  <div style="font-size:11px;color:#22C55E;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:12px">&#10003; PAGO EXITOSO</div>
  <div style="font-size:22px;font-weight:700;color:#FFFFFF;line-height:1.3;margin-bottom:16px">
    Bienvenido, {$firstName}
  </div>
  <div style="font-size:14px;color:#A1A1AA;line-height:1.7">
    Tu pago ha sido confirmado exitosamente. Ya tienes acceso completo a tu plan.
  </div>
</td></tr>

<!-- Payment details -->
<tr><td style="padding:0 32px 20px;background:#111114">
  <div style="font-size:11px;color:{$planColor};letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:12px">DETALLES DE TU SUSCRIPCION</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;border:1px solid #2A2A2E;border-top:3px solid {$planColor}">
  <tr><td style="padding:10px 16px;border-bottom:1px solid #2A2A2E">
    <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Plan</div>
    <div style="font-size:16px;color:{$planColor};font-weight:700;margin-top:2px">{$planDisplay}</div>
  </td></tr>
  <tr><td style="padding:10px 16px;border-bottom:1px solid #2A2A2E">
    <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Monto</div>
    <div style="font-size:14px;color:#D4D4D8;margin-top:2px">{$amount}</div>
  </td></tr>
  <tr><td style="padding:10px 16px;border-bottom:1px solid #2A2A2E">
    <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Metodo de pago</div>
    <div style="font-size:14px;color:#D4D4D8;margin-top:2px">{$methodLabel}</div>
  </td></tr>
  <tr><td style="padding:10px 16px">
    <div style="font-size:10px;color:#71717A;text-transform:uppercase;letter-spacing:1px">Referencia</div>
    <div style="font-size:14px;color:#D4D4D8;font-family:monospace;margin-top:2px">{$reference}</div>
  </td></tr>
  </table>
</td></tr>

<!-- Credentials -->
{$credentialsHtml}

<!-- CTA -->
<tr><td style="padding:0 32px 24px;background:#111114" align="center">
  <a href="{$loginUrl}" target="_blank" style="display:inline-block;background:#E31E24;color:#ffffff;text-decoration:none;padding:16px 48px;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase">
    INGRESAR A MI CUENTA &rarr;
  </a>
</td></tr>

<!-- Steps -->
<tr><td style="padding:0 32px 24px;background:#111114">
  <div style="font-size:10px;color:#52525B;letter-spacing:2px;text-transform:uppercase;margin-bottom:12px">PROXIMOS PASOS</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
  <tr><td style="padding:6px 0;font-size:13px;color:#A1A1AA;line-height:1.5">
    <span style="color:#E31E24;font-weight:700;margin-right:6px">01</span> Ingresa con tus credenciales
  </td></tr>
  <tr><td style="padding:6px 0;font-size:13px;color:#A1A1AA;line-height:1.5">
    <span style="color:#E31E24;font-weight:700;margin-right:6px">02</span> Completa tu perfil (peso, objetivo, disponibilidad)
  </td></tr>
  <tr><td style="padding:6px 0;font-size:13px;color:#A1A1AA;line-height:1.5">
    <span style="color:#E31E24;font-weight:700;margin-right:6px">03</span> Tu coach personalizara tu programa en 24-48h
  </td></tr>
  </table>
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 32px"><div style="border-top:1px solid #2A2A2E"></div></td></tr>

<!-- Footer -->
<tr><td style="padding:20px 32px;text-align:center;background:#0A0A0A">
  <div style="font-size:11px;color:#3F3F46;line-height:1.8">
    <strong style="color:#52525B">WellCore Fitness</strong><br>
    <a href="https://wellcorefitness.com" style="color:#3F3F46;text-decoration:none">wellcorefitness.com</a> &nbsp;|&nbsp;
    <a href="mailto:info@wellcorefitness.com" style="color:#3F3F46;text-decoration:none">info@wellcorefitness.com</a><br>
    <a href="https://wa.me/573124904720" style="color:#3F3F46;text-decoration:none">WhatsApp: +57 312 490 4720</a>
  </div>
  <div style="font-size:10px;color:#27272A;margin-top:10px;letter-spacing:1px">
    &copy; {$year} WellCore Fitness. Todos los derechos reservados.
  </div>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

    $result = sendEmail($email, $subject, $html);
    return $result['ok'] ?? false;
}
