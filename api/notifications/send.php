<?php
/**
 * POST /api/notifications/send — Sends a push notification to a client
 *
 * Auth: admin Bearer token OR X-Cron-Secret header
 * Body: { client_id, title, body, url? }
 * Response: { sent: N, errors: M }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/web-push.php';

respondJson();
requireMethod('POST');

// ── Authentication: admin token OR cron secret header ─────────────────────
$cronSecret    = getenv('CRON_SECRET') ?: 'WC_CRON_RISE2026_PROD_Secure!';
$providedSecret = $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
$isAuthorized  = false;

if ($providedSecret === $cronSecret) {
    $isAuthorized = true;
} else {
    // Fall back to admin Bearer token auth
    try {
        authenticateAdmin();
        $isAuthorized = true;
    } catch (\Throwable $e) {
        // respondError already called inside authenticateAdmin on failure
        // (it calls exit), so we only reach here if it throws instead
        respondError('Authentication required', 401);
    }
}

// ── Parse body ────────────────────────────────────────────────────────────
$body     = getJsonBody();
$clientId = isset($body['client_id']) ? (int)$body['client_id'] : 0;
$title    = trim($body['title'] ?? '');
$msgBody  = trim($body['body']  ?? '');
$url      = trim($body['url']   ?? '/cliente.html');

if (!$clientId || !$title || !$msgBody) {
    respondError('client_id, title y body son requeridos', 400);
}

// ── Load subscriptions and send ───────────────────────────────────────────
$db   = getDB();
$stmt = $db->prepare("
    SELECT endpoint, p256dh, auth
    FROM push_subscriptions
    WHERE client_id = ? AND is_active = 1
");
$stmt->execute([$clientId]);
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sent   = 0;
$errors = 0;
$data   = ['title' => $title, 'body' => $msgBody, 'url' => $url];

foreach ($subscriptions as $sub) {
    $ok = webpush_send($sub['endpoint'], $sub['p256dh'], $sub['auth'], $data);
    if ($ok) {
        $sent++;
    } else {
        $errors++;
        // Optionally deactivate dead subscriptions (410 Gone handled by webpush_send logging)
    }
}

respond(['sent' => $sent, 'errors' => $errors]);
