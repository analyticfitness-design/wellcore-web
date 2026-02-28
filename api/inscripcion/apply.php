<?php
/**
 * WellCore Fitness — Inscripción de Clientes
 * POST /api/inscripcion/apply.php
 *
 * Body JSON esperado:
 *   plan, nombre, email, whatsapp (required)
 *   apellido, ciudad, pais, edad, objetivo, experiencia,
 *   lesion, detalle_lesion, dias_disponibles, horario, como_conocio (optional)
 */

header('Content-Type: application/json; charset=utf-8');
$allowedOrigins = ['https://wellcorefitness.com', 'https://www.wellcorefitness.com', 'http://172.17.216.45:8082', 'http://localhost:8082'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://wellcorefitness.com');
}
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

// ─── Helpers ──────────────────────────────────────────────────────────────────
function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ok(array $data): void  { respond(200, array_merge(['ok' => true],  $data)); }
function err(string $msg, int $code = 400): void { respond($code, ['ok' => false, 'error' => $msg]); }

// ─── Parse body ───────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    err('Cuerpo JSON inválido o vacío');
}

// ─── Validaciones requeridas ──────────────────────────────────────────────────
$validPlans = ['esencial', 'metodo', 'elite'];

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

$plan     = trim($body['plan']     ?? '');
$nombre   = sanitize($body['nombre']   ?? '');
$email    = trim($body['email']    ?? '');
$whatsapp = sanitize($body['whatsapp'] ?? '');

if (!in_array($plan, $validPlans, true)) {
    err('El campo plan debe ser: esencial, metodo o elite');
}
if ($nombre === '') {
    err('El campo nombre es requerido');
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    err('El campo email es requerido y debe tener formato válido');
}
if ($whatsapp === '') {
    err('El campo whatsapp es requerido');
}

// ─── Rate limiting — max 5 por IP por hora ────────────────────────────────────
$dataDir    = __DIR__ . '/../data';
$rateLimitFile = $dataDir . '/inscripcion-rate-limit.json';

$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR']
          ?? $_SERVER['REMOTE_ADDR']
          ?? '0.0.0.0';
$ipHash = hash('sha256', $clientIp);
$now    = time();
$window = 3600; // 1 hora
$maxReq = 5;

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$rateData = [];
if (file_exists($rateLimitFile)) {
    $rateData = json_decode(file_get_contents($rateLimitFile), true) ?: [];
}

// Limpiar ventanas expiradas
foreach ($rateData as $hash => $entry) {
    if ($now - $entry['first_request'] > $window) {
        unset($rateData[$hash]);
    }
}

if (isset($rateData[$ipHash])) {
    if ($rateData[$ipHash]['count'] >= $maxReq) {
        err('Límite de solicitudes alcanzado. Máximo 5 inscripciones por hora por IP.', 429);
    }
    $rateData[$ipHash]['count']++;
} else {
    $rateData[$ipHash] = ['count' => 1, 'first_request' => $now];
}

file_put_contents($rateLimitFile, json_encode($rateData, JSON_PRETTY_PRINT), LOCK_EX);

// ─── Construir registro ───────────────────────────────────────────────────────
$timestamp = (int)(microtime(true) * 1000);
$id        = 'INS-' . $timestamp;

$inscripcion = [
    'id'             => $id,
    'status'         => 'pending_contact',
    'created_at'     => date('c'),
    'plan'           => $plan,
    'nombre'         => $nombre,
    'apellido'       => sanitize($body['apellido']       ?? ''),
    'email'          => strtolower($email),
    'whatsapp'       => $whatsapp,
    'ciudad'         => sanitize($body['ciudad']         ?? ''),
    'pais'           => sanitize($body['pais']           ?? ''),
    'edad'           => isset($body['edad']) ? (int)$body['edad'] : null,
    'objetivo'       => sanitize($body['objetivo']       ?? ''),
    'experiencia'    => sanitize($body['experiencia']    ?? ''),
    'lesion'         => sanitize($body['lesion']         ?? ''),
    'detalle_lesion' => sanitize($body['detalle_lesion'] ?? ''),
    'dias_disponibles' => sanitize($body['dias_disponibles'] ?? ''),
    'horario'        => sanitize($body['horario']        ?? ''),
    'como_conocio'   => sanitize($body['como_conocio']   ?? ''),
    'ip_hash'        => $ipHash,
];

// ─── Guardar en MySQL (primario) + JSON (fallback) ───────────────────────────
$savedToDb = false;
try {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();
    $stmt = $db->prepare("
        INSERT IGNORE INTO inscriptions
            (id, plan, nombre, apellido, email, whatsapp, ciudad, pais, edad, objetivo,
             experiencia, lesion, detalle_lesion, dias_disponibles, horario, como_conocio, ip_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $inscripcion['id'],
        $inscripcion['plan'],
        $inscripcion['nombre'],
        $inscripcion['apellido'],
        strtolower($inscripcion['email']),
        $inscripcion['whatsapp'],
        $inscripcion['ciudad'],
        $inscripcion['pais'],
        $inscripcion['edad'],
        $inscripcion['objetivo'],
        $inscripcion['experiencia'],
        $inscripcion['lesion'],
        $inscripcion['detalle_lesion'] ?: null,
        $inscripcion['dias_disponibles'],
        $inscripcion['horario'],
        $inscripcion['como_conocio'],
        $inscripcion['ip_hash'],
    ]);
    $savedToDb = true;
} catch (\Exception $e) {
    // Si MySQL falla, caemos al JSON
    error_log('[WellCore] inscripcion DB error: ' . $e->getMessage());
}

// Fallback JSON si MySQL no está disponible
if (!$savedToDb) {
    $inscripcionesFile = $dataDir . '/inscripciones.json';
    $inscripciones = [];
    if (file_exists($inscripcionesFile)) {
        $inscripciones = json_decode(file_get_contents($inscripcionesFile), true) ?: [];
    }
    $inscripciones[] = $inscripcion;
    file_put_contents($inscripcionesFile, json_encode($inscripciones, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ─── Respuesta ────────────────────────────────────────────────────────────────
ok([
    'id'      => $id,
    'message' => 'Solicitud recibida. Te contactaremos en menos de 24 horas.',
]);
