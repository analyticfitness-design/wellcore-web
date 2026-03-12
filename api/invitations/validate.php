<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
/**
 * WellCore Fitness — Validate Invitation Code (Public)
 * GET /api/invitations/validate.php?code=XXXXXX
 *
 * Returns {valid: true, plan: "metodo"} or {valid: false}
 */

header('Content-Type: application/json; charset=utf-8');
$allowedOrigins = ['https://wellcorefitness.com', 'https://www.wellcorefitness.com', 'http://172.17.216.45:8082', 'http://localhost:8082'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://wellcorefitness.com');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// --- Rate limiting (10 req/hour per IP) ---
$rateLimitFile = sys_get_temp_dir() . '/wc_invite_validate_rate.json';
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipHash = hash('sha256', $clientIp);
$now = time();
$window = 3600;
$maxReq = 10;

$rateData = [];
if (file_exists($rateLimitFile)) {
    $rateData = json_decode(@file_get_contents($rateLimitFile), true) ?: [];
}

foreach ($rateData as $hash => $entry) {
    if ($now - $entry['first_request'] > $window) {
        unset($rateData[$hash]);
    }
}

if (isset($rateData[$ipHash]) && $rateData[$ipHash]['count'] >= $maxReq) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Rate limit exceeded']);
    exit;
}

if (isset($rateData[$ipHash])) {
    $rateData[$ipHash]['count']++;
} else {
    $rateData[$ipHash] = ['count' => 1, 'first_request' => $now];
}

@file_put_contents($rateLimitFile, json_encode($rateData, JSON_PRETTY_PRINT), LOCK_EX);

// --- Validate code ---
$code = trim($_GET['code'] ?? '');
if (!$code || !preg_match('/^[a-f0-9]{32}$/i', $code)) {
    echo json_encode(['ok' => true, 'valid' => false]);
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();

    $stmt = $db->prepare("
        SELECT code, plan, status, expires_at
        FROM invitations
        WHERE code = ? AND status = 'pending'
    ");
    $stmt->execute([$code]);
    $inv = $stmt->fetch();

    if (!$inv) {
        echo json_encode(['ok' => true, 'valid' => false]);
        exit;
    }

    // Check expiration
    if ($inv['expires_at'] && strtotime($inv['expires_at']) < $now) {
        // Auto-expire
        $db->prepare("UPDATE invitations SET status = 'expired' WHERE code = ?")->execute([$code]);
        echo json_encode(['ok' => true, 'valid' => false]);
        exit;
    }

    echo json_encode([
        'ok'    => true,
        'valid' => true,
        'plan'  => $inv['plan'],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
