<?php
/**
 * WellCore Fitness — Coach Application API
 * POST /api/coaches/apply.php
 * Stores coach applications in api/data/coach-applications.json
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

// ─── Paths ────────────────────────────────────────────────────────────────────
$ROOT            = dirname(__DIR__, 2);  // project root
$DATA_DIR        = $ROOT . '/api/data';
$APPLICATIONS_FILE = $DATA_DIR . '/coach-applications.json';
$RATE_LIMIT_FILE = $DATA_DIR . '/coach-rate-limit.json';

// ─── Helpers ──────────────────────────────────────────────────────────────────
function readJSON(string $file, $default = []) {
    if (!file_exists($file)) return $default;
    $raw = file_get_contents($file);
    $decoded = json_decode($raw, true);
    return ($decoded !== null) ? $decoded : $default;
}

function writeJSON(string $file, $data): void {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function respond(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function ok(array $data): void   { respond(200, array_merge(['ok' => true],  $data)); }
function fail(string $msg): void { respond(400, ['ok' => false, 'error' => $msg]); }

// ─── Parse body ───────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    fail('JSON inválido o Content-Type incorrecto');
}

// ─── Validación ───────────────────────────────────────────────────────────────
$VALID_EXPERIENCES     = ['1-2', '3-5', '5-10', '+10'];
$VALID_PLANS           = ['asociado', 'profesional', 'elite'];
$VALID_CURRENT_CLIENTS = ['No tengo', '1-5 clientes', '6-15', '+15'];

// Required strings
$required = ['name', 'email', 'whatsapp', 'city', 'bio'];
foreach ($required as $field) {
    if (empty($body[$field]) || !is_string($body[$field]) || trim($body[$field]) === '') {
        fail("El campo '{$field}' es requerido");
    }
}

$name      = trim($body['name']);
$email     = trim($body['email']);
$whatsapp  = trim($body['whatsapp']);
$city      = trim($body['city']);
$bio       = trim($body['bio']);

if (strlen($name) < 3)
    fail('El nombre debe tener al menos 3 caracteres');

if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    fail('El formato del email no es válido');

if (strlen($whatsapp) < 7)
    fail('El número de WhatsApp debe tener al menos 7 caracteres');

if (strlen($bio) < 100)
    fail('La bio debe tener al menos 100 caracteres');

// Experience
$experience = trim($body['experience'] ?? '');
if (!in_array($experience, $VALID_EXPERIENCES, true))
    fail('El campo experience debe ser uno de: ' . implode(', ', $VALID_EXPERIENCES));

// Specializations (optional array)
$specializations = [];
if (!empty($body['specializations'])) {
    if (!is_array($body['specializations']))
        fail('specializations debe ser un array');
    $specializations = array_values(array_filter(array_map('strval', $body['specializations'])));
}

// Plan
$plan = trim($body['plan'] ?? '');
if (!in_array($plan, $VALID_PLANS, true))
    fail('El campo plan debe ser uno de: ' . implode(', ', $VALID_PLANS));

// Current clients
$current_clients = trim($body['current_clients'] ?? '');
if (!in_array($current_clients, $VALID_CURRENT_CLIENTS, true))
    fail('El campo current_clients debe ser uno de: ' . implode(', ', $VALID_CURRENT_CLIENTS));

// Contract accepted
if (!isset($body['contract_accepted']) || $body['contract_accepted'] !== true)
    fail('Debes aceptar el contrato (contract_accepted: true)');

$referral = trim($body['referral'] ?? '');

// ─── Rate limiting (max 3 per IP per hour) ────────────────────────────────────
$clientIp  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipKey     = md5($clientIp);
$now       = time();
$window    = 3600; // 1 hour in seconds
$maxPerHr  = 3;

$rateData  = readJSON($RATE_LIMIT_FILE, []);

// Clean old entries
foreach ($rateData as $key => $entry) {
    if (($now - ($entry['first_request'] ?? 0)) > $window) {
        unset($rateData[$key]);
    }
}

if (isset($rateData[$ipKey])) {
    if ($rateData[$ipKey]['count'] >= $maxPerHr) {
        respond(429, ['ok' => false, 'error' => 'Límite de solicitudes alcanzado. Máximo 3 aplicaciones por hora por IP.']);
    }
    $rateData[$ipKey]['count']++;
    $rateData[$ipKey]['last_request'] = $now;
} else {
    $rateData[$ipKey] = [
        'count'         => 1,
        'first_request' => $now,
        'last_request'  => $now,
    ];
}

writeJSON($RATE_LIMIT_FILE, $rateData);

// ─── Build & save application ─────────────────────────────────────────────────
$timestamp = time();
$id        = 'CAP-' . $timestamp;

$application = [
    'id'               => $id,
    'status'           => 'pending',
    'created_at'       => date('c', $timestamp),  // ISO 8601
    'ip'               => $ipKey,                 // hashed for privacy
    'name'             => $name,
    'email'            => strtolower($email),
    'whatsapp'         => $whatsapp,
    'city'             => $city,
    'experience'       => $experience,
    'specializations'  => $specializations,
    'plan'             => $plan,
    'current_clients'  => $current_clients,
    'bio'              => $bio,
    'referral'         => $referral,
    'contract_accepted'=> true,
];

$savedToDb = false;
try {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();
    $stmt = $db->prepare("
        INSERT IGNORE INTO coach_applications
            (id, name, email, whatsapp, city, bio, experience, plan, current_clients, specializations, referral, ip_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $application['id'],
        $application['name'],
        strtolower($application['email']),
        $application['whatsapp'],
        $application['city'],
        $application['bio'],
        $application['experience'],
        $application['plan'],
        $application['current_clients'],
        json_encode($application['specializations'], JSON_UNESCAPED_UNICODE),
        $application['referral'] ?: null,
        $application['ip'],
    ]);
    $savedToDb = true;
} catch (\Exception $e) {
    error_log('[WellCore] coach_applications DB error: ' . $e->getMessage());
}

if (!$savedToDb) {
    $applications = readJSON($APPLICATIONS_FILE, []);
    $applications[] = $application;
    writeJSON($APPLICATIONS_FILE, $applications);
}

ok([
    'id'      => $id,
    'message' => 'Aplicación recibida. Te contactaremos en 5-7 días hábiles.',
]);
