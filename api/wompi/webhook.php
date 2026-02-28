<?php
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
$logDir     = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
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
                INSERT INTO clients (client_code, name, email, password_hash, plan, status, fecha_inicio)
                VALUES (?, ?, ?, ?, ?, 'activo', CURDATE())
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

    $html  = "<h2>Hola {$safeName},</h2>";
    $html .= "<p>Tu pago ha sido confirmado exitosamente a traves de Wompi.</p>";
    $html .= "<h3>DETALLES DE TU SUSCRIPCION</h3>";
    $html .= "<ul>";
    $html .= "<li>Plan: {$planDisplay}</li>";
    $html .= "<li>Monto: {$amount}</li>";
    $html .= "<li>Metodo de pago: {$methodLabel}</li>";
    $html .= "<li>Referencia: {$reference}</li>";
    $html .= "</ul>";

    if ($tempPassword !== null) {
        $html .= "<h3>TUS CREDENCIALES DE ACCESO</h3>";
        $html .= "<ul>";
        $html .= "<li>URL: https://wellcorefitness.com/login.html</li>";
        $html .= "<li>Email: {$email}</li>";
        $html .= "<li>Contrasena temporal: {$tempPassword}</li>";
        $html .= "</ul>";
        $html .= "<p><strong>IMPORTANTE:</strong> Cambia tu contrasena al ingresar por primera vez.</p>";
    } else {
        $html .= "<p>Tu cuenta ya estaba activa. Ingresa con tus credenciales habituales en <a href='https://wellcorefitness.com/login.html'>wellcorefitness.com</a>.</p>";
    }

    $html .= "<h3>Proximos pasos</h3>";
    $html .= "<ol>";
    $html .= "<li>Ingresa al portal con las credenciales de arriba</li>";
    $html .= "<li>Completa tu perfil (peso, objetivo, disponibilidad)</li>";
    $html .= "<li>Tu coach personalizara tu programa en 24-48 horas</li>";
    $html .= "</ol>";
    $html .= "<p>Cualquier duda: info@wellcorefitness.com | WhatsApp: +57 312 4904720</p>";
    $html .= "<p>Equipo WellCore Fitness<br>https://wellcorefitness.com</p>";

    $result = sendEmail($email, $subject, $html);
    return $result['ok'] ?? false;
}
