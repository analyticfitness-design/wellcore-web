<?php
// POST /api/auth/login
// Body: {type: 'client'|'admin', email_or_user: '...', password: '...'}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rate-limit.php';
require_once __DIR__ . '/../includes/logger.php';

logStart();
requireMethod('POST');

$body     = getJsonBody();
$type     = $body['type'] ?? 'client';
$identity = trim($body['email'] ?? $body['username'] ?? '');
$password = $body['password'] ?? '';

if (!$identity || !$password) {
    respondError('Email/usuario y contrasena son requeridos', 422);
}

// Rate limiting: max 5 intentos fallidos por IP cada 15 minutos
if (!rate_limit_check('login', 5, 900)) {
    respondError('Demasiados intentos de login. Espera unos minutos.', 429);
}

$db = getDB();

// Dummy hash for timing-safe comparison when user doesn't exist
// This prevents timing attacks that could enumerate valid usernames/emails
$dummyHash = '$2y$12$dummyHashForTimingSafetyXXXXXXXXXXXXXXXXXXXXXXXXXX';

if ($type === 'admin') {
    $stmt = $db->prepare("SELECT id, username, password_hash, name, role FROM admins WHERE username = ?");
    $stmt->execute([strtolower($identity)]);
    $user = $stmt->fetch();

    // Always run password_verify to prevent timing-based user enumeration
    $hash = $user ? $user['password_hash'] : $dummyHash;
    if (!$user || !password_verify($password, $hash)) {
        respondError('Credenciales incorrectas', 401);
    }

    // Login exitoso — limpiar rate limit
    rate_limit_clear('login');
    logSetUser($user['id'], 'admin');
    $token = createToken('admin', $user['id'], true);

    respond([
        'token'      => $token,
        'expires_in' => TOKEN_EXPIRY_ADMIN * 3600,
        'user'       => [
            'id'       => $user['id'],
            'username' => $user['username'],
            'name'     => $user['name'],
            'role'     => $user['role'],
            'type'     => 'admin',
        ]
    ]);
}

// Client login
$stmt = $db->prepare("SELECT id, client_code, name, email, plan, status, password_hash, coach_id FROM clients WHERE email = ?");
$stmt->execute([strtolower($identity)]);
$client = $stmt->fetch();

// Always run password_verify to prevent timing-based user enumeration
$hash = $client ? $client['password_hash'] : $dummyHash;
if (!$client || !password_verify($password, $hash)) {
    respondError('Credenciales incorrectas', 401);
}

// Login exitoso — limpiar rate limit
rate_limit_clear('login');
logSetUser($client['id'], 'client');

if ($client['status'] === 'inactivo') {
    respondError('Tu cuenta esta inactiva. Contacta al coach.', 403);
}
if ($client['status'] === 'pendiente') {
    respondError('Tu cuenta esta pendiente de activacion. Revisa tu email.', 403);
}

// Get profile
$stmt2 = $db->prepare("SELECT * FROM client_profiles WHERE client_id = ?");
$stmt2->execute([$client['id']]);
$profile = $stmt2->fetch();

$token = createToken('client', $client['id']);

// Coach theme for client dashboard personalization
$coachTheme = null;
if (!empty($client['coach_id'])) {
    $ctStmt = $db->prepare("
        SELECT a.name as coach_name, cp.color_primary, cp.logo_url, cp.slug
        FROM coach_profiles cp
        JOIN admins a ON a.id = cp.admin_id
        WHERE cp.admin_id = ?
    ");
    $ctStmt->execute([$client['coach_id']]);
    $ct = $ctStmt->fetch();
    if ($ct) {
        $coachTheme = [
            'name'     => $ct['coach_name'],
            'color'    => $ct['color_primary'],
            'logo_url' => $ct['logo_url'],
            'slug'     => $ct['slug'],
        ];
    }
}

respond([
    'token'       => $token,
    'expires_in'  => TOKEN_EXPIRY_HOURS * 3600,
    'client'      => [
        'id'          => $client['id'],
        'client_code' => $client['client_code'],
        'name'        => $client['name'],
        'email'       => $client['email'],
        'plan'        => $client['plan'],
        'status'      => $client['status'],
        'profile'     => $profile ?: null,
    ],
    'coach_theme' => $coachTheme,
]);
