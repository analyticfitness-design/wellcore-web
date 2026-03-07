<?php
/**
 * Report Chat Message API
 * POST — Report a chat message for moderation
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$client = authenticateClient();
$db = getDB();
$cid = (int)$client['id'];

$body = getJsonBody();
$msgId = (int)($body['chat_message_id'] ?? 0);
$reason = mb_substr(trim($body['reason'] ?? 'inappropriate'), 0, 100);

if ($msgId <= 0) {
    respondError('chat_message_id requerido', 400);
}

// Verify message exists
$stmt = $db->prepare("SELECT id, client_id FROM community_chat WHERE id = ?");
$stmt->execute([$msgId]);
$msg = $stmt->fetch();

if (!$msg) {
    respondError('Mensaje no encontrado', 404);
}

// Block self-report
if ((int)$msg['client_id'] === $cid) {
    respondError('No puedes reportar tu propio mensaje', 400);
}

// Check duplicate report
$stmt = $db->prepare("SELECT id FROM chat_reports WHERE chat_message_id = ? AND reporter_id = ?");
$stmt->execute([$msgId, $cid]);
if ($stmt->fetch()) {
    respondError('Ya reportaste este mensaje', 409);
}

// Insert report
$stmt = $db->prepare("INSERT INTO chat_reports (chat_message_id, reporter_id, reason) VALUES (?, ?, ?)");
$stmt->execute([$msgId, $cid, $reason]);

// Count total reports for this message
$stmt = $db->prepare("SELECT COUNT(*) FROM chat_reports WHERE chat_message_id = ?");
$stmt->execute([$msgId]);
$reportCount = (int)$stmt->fetchColumn();

if ($reportCount >= 3) {
    // Hide message
    $stmt = $db->prepare("UPDATE community_chat SET hidden = 1 WHERE id = ?");
    $stmt->execute([$msgId]);

    // Check if sender already banned
    $senderId = (int)$msg['client_id'];
    $stmt = $db->prepare("SELECT id FROM chat_bans WHERE client_id = ? AND banned_until > NOW()");
    $stmt->execute([$senderId]);

    if (!$stmt->fetch()) {
        // Ban sender for 24 hours
        $stmt = $db->prepare("INSERT INTO chat_bans (client_id, reason, banned_until) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
        $stmt->execute([$senderId, 'Mensaje reportado por multiples usuarios (msg #' . $msgId . ')']);
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
