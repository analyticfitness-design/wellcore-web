<?php
/**
 * Community Reactions API
 * POST — Toggle a reaction (add or remove)
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
$postId = (int)($body['post_id'] ?? 0);
$emoji  = $body['emoji'] ?? '';

if (!$postId) respondError('post_id requerido', 400);

$allowed = ['fire', 'muscle', 'clap', 'heart'];
if (!in_array($emoji, $allowed, true)) {
    respondError('Emoji no permitido. Usa: ' . implode(', ', $allowed), 400);
}

// Verify post exists
$pStmt = $db->prepare("SELECT id FROM community_posts WHERE id = ?");
$pStmt->execute([$postId]);
if (!$pStmt->fetch()) {
    respondError('Post no encontrado', 404);
}

// Toggle: check if reaction exists
$checkStmt = $db->prepare("
    SELECT id FROM community_reactions WHERE post_id = ? AND client_id = ? AND emoji = ?
");
$checkStmt->execute([$postId, $cid, $emoji]);
$existing = $checkStmt->fetch();

if ($existing) {
    // Remove
    $db->prepare("DELETE FROM community_reactions WHERE id = ?")->execute([$existing['id']]);
    $action = 'removed';
} else {
    // Add
    $db->prepare("
        INSERT INTO community_reactions (post_id, client_id, emoji) VALUES (?, ?, ?)
    ")->execute([$postId, $cid, $emoji]);
    $action = 'added';
}

// Return updated counts for this post
$countStmt = $db->prepare("
    SELECT emoji, COUNT(*) AS cnt,
           MAX(CASE WHEN client_id = ? THEN 1 ELSE 0 END) AS user_reacted
    FROM community_reactions
    WHERE post_id = ?
    GROUP BY emoji
");
$countStmt->execute([$cid, $postId]);
$reactions = [];
foreach ($countStmt->fetchAll() as $r) {
    $reactions[] = [
        'emoji'        => $r['emoji'],
        'count'        => (int)$r['cnt'],
        'user_reacted' => (bool)$r['user_reacted'],
    ];
}

respond([
    'ok'        => true,
    'action'    => $action,
    'reactions' => $reactions,
]);
