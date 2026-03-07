<?php
/**
 * Chat Reactions API
 * POST — Toggle a reaction emoji on a chat message
 * Returns updated reaction counts for that message
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');

$token = getTokenFromHeader();
if (!$token) {
    respondError('Authentication required', 401);
}

$db = getDB();
$userType = 'client';
$userId = 0;

// Try client token
$stmt = $db->prepare("
    SELECT t.user_id, c.id
    FROM auth_tokens t
    JOIN clients c ON c.id = t.user_id
    WHERE t.token = ? AND t.user_type = 'client' AND t.expires_at > NOW()
");
$stmt->execute([$token]);
$client = $stmt->fetch();

if ($client) {
    $userType = 'client';
    $userId = (int)$client['id'];
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
    $userType = 'admin';
    $userId = (int)$admin['id'];
}

$body = getJsonBody();
$msgId = (int)($body['chat_message_id'] ?? 0);
$emoji = trim($body['emoji'] ?? '');

if ($msgId <= 0) {
    respondError('chat_message_id requerido', 400);
}
$allowed = ['fire', 'muscle', 'heart'];
if (!in_array($emoji, $allowed)) {
    respondError('Emoji no valido', 400);
}

// Verify message exists
$stmt = $db->prepare("SELECT id FROM community_chat WHERE id = ? AND hidden = 0");
$stmt->execute([$msgId]);
if (!$stmt->fetch()) {
    respondError('Mensaje no encontrado', 404);
}

// Check existing reaction
if ($userType === 'client') {
    $stmt = $db->prepare("SELECT id FROM chat_message_reactions WHERE chat_message_id = ? AND user_type = 'client' AND client_id = ? AND emoji = ?");
    $stmt->execute([$msgId, $userId, $emoji]);
} else {
    $stmt = $db->prepare("SELECT id FROM chat_message_reactions WHERE chat_message_id = ? AND user_type = 'admin' AND admin_id = ? AND emoji = ?");
    $stmt->execute([$msgId, $userId, $emoji]);
}
$existing = $stmt->fetch();

if ($existing) {
    // Remove reaction
    $stmt = $db->prepare("DELETE FROM chat_message_reactions WHERE id = ?");
    $stmt->execute([$existing['id']]);
} else {
    // Add reaction
    if ($userType === 'client') {
        $stmt = $db->prepare("INSERT INTO chat_message_reactions (chat_message_id, user_type, client_id, emoji) VALUES (?, 'client', ?, ?)");
        $stmt->execute([$msgId, $userId, $emoji]);
    } else {
        $stmt = $db->prepare("INSERT INTO chat_message_reactions (chat_message_id, user_type, admin_id, emoji) VALUES (?, 'admin', ?, ?)");
        $stmt->execute([$msgId, $userId, $emoji]);
    }
}

// Get updated counts
$reactions = getChatReactions($db, $msgId, $userType, $userId);

respond(['ok' => true, 'reactions' => $reactions]);

function getChatReactions($db, $msgId, $userType, $userId) {
    $stmt = $db->prepare("
        SELECT emoji, COUNT(*) as count
        FROM chat_message_reactions
        WHERE chat_message_id = ?
        GROUP BY emoji
    ");
    $stmt->execute([$msgId]);
    $counts = $stmt->fetchAll();

    $reactions = [];
    foreach ($counts as $row) {
        // Check if current user reacted
        if ($userType === 'client') {
            $chk = $db->prepare("SELECT id FROM chat_message_reactions WHERE chat_message_id = ? AND emoji = ? AND user_type = 'client' AND client_id = ?");
            $chk->execute([$msgId, $row['emoji'], $userId]);
        } else {
            $chk = $db->prepare("SELECT id FROM chat_message_reactions WHERE chat_message_id = ? AND emoji = ? AND user_type = 'admin' AND admin_id = ?");
            $chk->execute([$msgId, $row['emoji'], $userId]);
        }
        $reactions[] = [
            'emoji' => $row['emoji'],
            'count' => (int)$row['count'],
            'user_reacted' => (bool)$chk->fetch(),
        ];
    }
    return $reactions;
}
