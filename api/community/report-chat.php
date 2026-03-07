<?php
/**
 * Report Chat Message API
 * POST — Report a chat message for moderation
 * Supports both client and admin/coach tokens
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');

// Authenticate — try client first, then admin
$token = getTokenFromHeader();
if (!$token) {
    respondError('Authentication required', 401);
}

$db = getDB();
$reporterType = 'client';
$reporterId = 0;

$stmt = $db->prepare("
    SELECT t.user_id, c.id
    FROM auth_tokens t
    JOIN clients c ON c.id = t.user_id
    WHERE t.token = ? AND t.user_type = 'client' AND t.expires_at > NOW()
");
$stmt->execute([$token]);
$client = $stmt->fetch();

if ($client) {
    $reporterType = 'client';
    $reporterId = (int)$client['id'];
} else {
    $stmt2 = $db->prepare("
        SELECT t.user_id, a.id
        FROM auth_tokens t
        JOIN admins a ON a.id = t.user_id
        WHERE t.token = ? AND t.user_type = 'admin' AND t.expires_at > NOW()
    ");
    $stmt2->execute([$token]);
    $admin = $stmt2->fetch();
    if (!$admin) {
        respondError('Invalid or expired token', 401);
    }
    $reporterType = 'admin';
    $reporterId = (int)$admin['id'];
}

$body = getJsonBody();
$msgId = (int)($body['chat_message_id'] ?? 0);
$reason = mb_substr(trim($body['reason'] ?? 'inappropriate'), 0, 100);

if ($msgId <= 0) {
    respondError('chat_message_id requerido', 400);
}

// Verify message exists
$stmt = $db->prepare("SELECT id, client_id, user_type, admin_id FROM community_chat WHERE id = ?");
$stmt->execute([$msgId]);
$msg = $stmt->fetch();

if (!$msg) {
    respondError('Mensaje no encontrado', 404);
}

// Block self-report
$isSelf = ($msg['user_type'] === $reporterType)
    && (($reporterType === 'client' && (int)$msg['client_id'] === $reporterId)
        || ($reporterType === 'admin' && (int)$msg['admin_id'] === $reporterId));
if ($isSelf) {
    respondError('No puedes reportar tu propio mensaje', 400);
}

// Check duplicate report
if ($reporterType === 'client') {
    $stmt = $db->prepare("SELECT id FROM chat_reports WHERE chat_message_id = ? AND reporter_id = ? AND reporter_type = 'client'");
    $stmt->execute([$msgId, $reporterId]);
} else {
    $stmt = $db->prepare("SELECT id FROM chat_reports WHERE chat_message_id = ? AND reporter_admin_id = ? AND reporter_type = 'admin'");
    $stmt->execute([$msgId, $reporterId]);
}
if ($stmt->fetch()) {
    respondError('Ya reportaste este mensaje', 409);
}

// Insert report
if ($reporterType === 'client') {
    $stmt = $db->prepare("INSERT INTO chat_reports (chat_message_id, reporter_id, reporter_type, reason) VALUES (?, ?, 'client', ?)");
    $stmt->execute([$msgId, $reporterId, $reason]);
} else {
    $stmt = $db->prepare("INSERT INTO chat_reports (chat_message_id, reporter_id, reporter_type, reporter_admin_id, reason) VALUES (?, NULL, 'admin', ?, ?)");
    $stmt->execute([$msgId, $reporterId, $reason]);
}

// Count total reports for this message
$stmt = $db->prepare("SELECT COUNT(*) FROM chat_reports WHERE chat_message_id = ?");
$stmt->execute([$msgId]);
$reportCount = (int)$stmt->fetchColumn();

if ($reportCount >= 3) {
    // Hide message
    $stmt = $db->prepare("UPDATE community_chat SET hidden = 1 WHERE id = ?");
    $stmt->execute([$msgId]);

    // Ban sender (only if sender is a client — admins don't get banned)
    if ($msg['user_type'] === 'client' && $msg['client_id']) {
        $senderId = (int)$msg['client_id'];
        $stmt = $db->prepare("SELECT id FROM chat_bans WHERE client_id = ? AND banned_until > NOW()");
        $stmt->execute([$senderId]);

        if (!$stmt->fetch()) {
            $stmt = $db->prepare("INSERT INTO chat_bans (client_id, reason, banned_until) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $stmt->execute([$senderId, 'Mensaje reportado por multiples usuarios (msg #' . $msgId . ')']);
        }
    }

    $action = 'hidden_and_banned';
} else {
    $action = 'reported';
}

respond([
    'ok'           => true,
    'action'       => $action,
    'report_count' => $reportCount,
]);
