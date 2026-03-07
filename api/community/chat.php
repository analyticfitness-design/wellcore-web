<?php
/**
 * Community Chat API
 * GET  — Fetch messages (polling, load older, or initial)
 * POST — Send a new chat message
 * Supports both client and admin/coach tokens
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat-filter.php';

requireMethod('GET', 'POST');

// Try client auth first, then admin auth
$userType = 'client';
$userId = 0;
$userName = '';
$userPlan = '';

$token = getTokenFromHeader();
if (!$token) {
    respondError('Authentication required', 401);
}

$db = getDB();

// Try client token
$stmt = $db->prepare("
    SELECT t.user_id, c.id, c.name, c.plan, c.status
    FROM auth_tokens t
    JOIN clients c ON c.id = t.user_id
    WHERE t.token = ? AND t.user_type = 'client' AND t.expires_at > NOW()
");
$stmt->execute([$token]);
$client = $stmt->fetch();

if ($client) {
    if ($client['status'] !== 'activo') {
        respondError('Account is not active', 403);
    }
    $userType = 'client';
    $userId = (int)$client['id'];
    $userName = $client['name'];
    $userPlan = $client['plan'];
} else {
    // Try admin token
    $stmt2 = $db->prepare("
        SELECT t.user_id, a.id, a.name, a.role
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
    $userName = $admin['name'];
    $userPlan = $admin['role']; // coach, admin, superadmin
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = 50;

    // Build base query with LEFT JOINs for both user types
    $baseSelect = "
        SELECT m.id, m.client_id, m.user_type, m.admin_id, m.message, m.hidden, m.created_at,
               COALESCE(c.name, a.name) AS author_name,
               CASE WHEN m.user_type = 'admin' THEN a.role ELSE c.plan END AS author_plan
        FROM community_chat m
        LEFT JOIN clients c ON m.user_type = 'client' AND c.id = m.client_id
        LEFT JOIN admins a ON m.user_type = 'admin' AND a.id = m.admin_id
    ";

    if (isset($_GET['after_id'])) {
        $afterId = (int)$_GET['after_id'];
        $stmt = $db->prepare($baseSelect . "
            WHERE m.id > ? AND m.hidden = 0
            ORDER BY m.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$afterId, $limit]);
        $rows = $stmt->fetchAll();

    } elseif (isset($_GET['before_id'])) {
        $beforeId = (int)$_GET['before_id'];
        $stmt = $db->prepare($baseSelect . "
            WHERE m.id < ? AND m.hidden = 0
            ORDER BY m.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$beforeId, $limit]);
        $rows = array_reverse($stmt->fetchAll());

    } else {
        $stmt = $db->prepare($baseSelect . "
            WHERE m.hidden = 0
            ORDER BY m.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $rows = array_reverse($stmt->fetchAll());
    }

    // Map rows to response format
    $messages = array_map(function ($row) use ($db, $userType, $userId) {
        $isMine = ($row['user_type'] === $userType)
            && (($userType === 'client' && (int)$row['client_id'] === $userId)
                || ($userType === 'admin' && (int)$row['admin_id'] === $userId));

        return [
            'id'             => (int)$row['id'],
            'client_id'      => $row['client_id'] ? (int)$row['client_id'] : null,
            'user_type'      => $row['user_type'],
            'message'        => $row['message'],
            'author_name'    => $row['author_name'],
            'author_initial' => mb_strtoupper(mb_substr($row['author_name'] ?? '?', 0, 1)),
            'author_plan'    => $row['author_plan'],
            'is_mine'        => $isMine,
            'created_at'     => $row['created_at'],
            'reactions'      => getChatMsgReactions($db, (int)$row['id'], $userType, $userId),
        ];
    }, $rows);

    respond([
        'ok'       => true,
        'messages' => $messages,
    ]);
}

// POST — Send message

// 1. Check ban (only for clients — admins are never banned)
if ($userType === 'client') {
    $banStmt = $db->prepare("
        SELECT banned_until FROM chat_bans
        WHERE client_id = ? AND banned_until > NOW()
        ORDER BY banned_until DESC
        LIMIT 1
    ");
    $banStmt->execute([$userId]);
    $ban = $banStmt->fetch();
    if ($ban) {
        respondError('Estas suspendido del chat hasta ' . $ban['banned_until'], 403);
    }
}

// 2. Rate limit — 2 seconds between messages
$rateWhere = $userType === 'client'
    ? "client_id = ? AND user_type = 'client'"
    : "admin_id = ? AND user_type = 'admin'";
$rateStmt = $db->prepare("
    SELECT created_at FROM community_chat
    WHERE {$rateWhere}
    ORDER BY created_at DESC
    LIMIT 1
");
$rateStmt->execute([$userId]);
$lastMsg = $rateStmt->fetch();
if ($lastMsg) {
    $lastTime = strtotime($lastMsg['created_at']);
    if (time() - $lastTime < 2) {
        respondError('Espera un momento antes de enviar otro mensaje', 429);
    }
}

// 3. Validate message
$body = getJsonBody();
$message = trim(strip_tags($body['message'] ?? ''));
if (!$message || mb_strlen($message) > 500) {
    respondError('El mensaje debe tener entre 1 y 500 caracteres', 400);
}

// 4. Apply chat filter
$filtered = filterChatMessage($message);
$cleanMessage = $filtered['clean'];

// 5. Insert
if ($userType === 'client') {
    $stmt = $db->prepare("
        INSERT INTO community_chat (client_id, user_type, message)
        VALUES (?, 'client', ?)
    ");
    $stmt->execute([$userId, $cleanMessage]);
} else {
    $stmt = $db->prepare("
        INSERT INTO community_chat (client_id, user_type, admin_id, message)
        VALUES (NULL, 'admin', ?, ?)
    ");
    $stmt->execute([$userId, $cleanMessage]);
}
$newId = (int)$db->lastInsertId();

respond([
    'ok'       => true,
    'message'  => [
        'id'             => $newId,
        'client_id'      => $userType === 'client' ? $userId : null,
        'user_type'      => $userType,
        'message'        => $cleanMessage,
        'author_name'    => $userName,
        'author_initial' => mb_strtoupper(mb_substr($userName, 0, 1)),
        'author_plan'    => $userPlan,
        'is_mine'        => true,
        'created_at'     => date('Y-m-d H:i:s'),
        'reactions'      => [],
    ],
    'filtered' => $filtered['flagged'],
], 201);

function getChatMsgReactions($db, $msgId, $userType, $userId) {
    try {
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
    } catch (PDOException $e) {
        return [];
    }
}
