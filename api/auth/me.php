<?php
// GET /api/auth/me
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');

$token = getTokenFromHeader();
if (!$token) respondError('Not authenticated', 401);

$db = getDB();

// Resolve token type
$stmt = $db->prepare("
    SELECT t.user_type, t.user_id
    FROM auth_tokens t
    WHERE t.token = ? AND t.expires_at > NOW()
");
$stmt->execute([$token]);
$tok = $stmt->fetch();

if (!$tok) respondError('Invalid or expired token', 401);

if ($tok['user_type'] === 'client') {
    $client = authenticateClient();
    $stmt2  = $db->prepare("SELECT * FROM client_profiles WHERE client_id = ?");
    $stmt2->execute([$client['id']]);
    $profile = $stmt2->fetch();
    respond(['type' => 'client', 'client' => $client, 'profile' => $profile ?: null]);
} else {
    $admin = authenticateAdmin();
    respond(['type' => 'admin', 'admin' => $admin]);
}
