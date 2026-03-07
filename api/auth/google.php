<?php
/**
 * Google Social Login — POST /api/auth/google.php
 *
 * Receives a Google credential JWT, verifies it, and either:
 * - Logs in an existing client (matched by google_id or email)
 * - Auto-creates a new client (plan=esencial, status=activo)
 *
 * Returns: { ok, token, user: {name, email, plan}, redirect }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError('Method not allowed', 405);
}

$body = json_decode(file_get_contents('php://input'), true);
$credential = $body['credential'] ?? '';

if (!$credential) {
    respondError('Missing Google credential', 400);
}

// ── Verify Google token ──────────────────────────────────
$tokenInfo = verifyGoogleToken($credential);
if (!$tokenInfo) {
    respondError('Token de Google invalido', 401);
}

$googleId = $tokenInfo['sub'];
$email    = strtolower($tokenInfo['email']);
$name     = $tokenInfo['name'] ?? '';

if (!$email) {
    respondError('Email no disponible en la cuenta de Google', 400);
}

// ── Find or create client ────────────────────────────────
$db = getDB();

// 1. Try by google_id
$stmt = $db->prepare("SELECT id, name, email, plan, status FROM clients WHERE google_id = ? LIMIT 1");
$stmt->execute([$googleId]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Try by email
if (!$client) {
    $stmt = $db->prepare("SELECT id, name, email, plan, status, google_id FROM clients WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    // Link google_id to existing account
    if ($client && !$client['google_id']) {
        $db->prepare("UPDATE clients SET google_id = ? WHERE id = ?")
           ->execute([$googleId, $client['id']]);
    }
}

// 3. Auto-create new client
if (!$client) {
    $clientCode = 'cli-' . str_pad((string)rand(100, 99999), 5, '0', STR_PAD_LEFT);
    $tempHash   = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

    $ins = $db->prepare("
        INSERT INTO clients (client_code, name, email, password_hash, google_id, plan, status, fecha_inicio)
        VALUES (?, ?, ?, ?, ?, 'esencial', 'activo', CURDATE())
    ");
    $ins->execute([$clientCode, $name, $email, $tempHash, $googleId]);

    $client = [
        'id'     => (int)$db->lastInsertId(),
        'name'   => $name,
        'email'  => $email,
        'plan'   => 'esencial',
        'status' => 'activo',
    ];
}

// Check if client is active
if (($client['status'] ?? '') === 'inactivo') {
    respondError('Tu cuenta esta inactiva. Contacta a soporte.', 403);
}

// ── Generate auth token ──────────────────────────────────
$cid   = (int)$client['id'];
$plan  = $client['plan'] ?? 'esencial';
$token = bin2hex(random_bytes(32));
$hours = ($plan === 'rise') ? 720 : 24; // RISE: 30 days, others: 24h

$db->prepare("
    INSERT INTO auth_tokens (user_type, user_id, token, expires_at)
    VALUES ('client', ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))
")->execute([$cid, $token, $hours]);

// ── Determine redirect ───────────────────────────────────
$redirect = ($plan === 'rise') ? 'rise-dashboard.html' : 'cliente.html';

respond([
    'ok'       => true,
    'token'    => $token,
    'user'     => [
        'id'    => $cid,
        'name'  => $client['name'],
        'email' => $client['email'],
        'plan'  => $plan,
    ],
    'redirect' => $redirect,
]);


// ── Helper: verify Google JWT ────────────────────────────
function verifyGoogleToken(string $credential): ?array {
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['sub']) || !isset($data['email'])) {
        return null;
    }

    // Verify email is verified
    if (($data['email_verified'] ?? 'false') !== 'true') {
        return null;
    }

    return $data;
}
