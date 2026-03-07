<?php
/**
 * Community Chat API
 * GET  — Fetch messages (polling, load older, or initial)
 * POST — Send a new chat message
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat-filter.php';

requireMethod('GET', 'POST');
$client = authenticateClient();
$db = getDB();
$cid = (int)$client['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = 50;

    if (isset($_GET['after_id'])) {
        // Polling — fetch newer messages
        $afterId = (int)$_GET['after_id'];
        $stmt = $db->prepare("
            SELECT m.id, m.client_id, m.message, m.hidden, m.created_at,
                   c.name AS author_name, c.plan AS author_plan
            FROM community_chat m
            JOIN clients c ON c.id = m.client_id
            WHERE m.id > ? AND m.hidden = 0
            ORDER BY m.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$afterId, $limit]);
        $rows = $stmt->fetchAll();

    } elseif (isset($_GET['before_id'])) {
        // Load older messages
        $beforeId = (int)$_GET['before_id'];
        $stmt = $db->prepare("
            SELECT m.id, m.client_id, m.message, m.hidden, m.created_at,
                   c.name AS author_name, c.plan AS author_plan
            FROM community_chat m
            JOIN clients c ON c.id = m.client_id
            WHERE m.id < ? AND m.hidden = 0
            ORDER BY m.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$beforeId, $limit]);
        $rows = array_reverse($stmt->fetchAll());

    } else {
        // Initial load — most recent messages
        $stmt = $db->prepare("
            SELECT m.id, m.client_id, m.message, m.hidden, m.created_at,
                   c.name AS author_name, c.plan AS author_plan
            FROM community_chat m
            JOIN clients c ON c.id = m.client_id
            WHERE m.hidden = 0
            ORDER BY m.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $rows = array_reverse($stmt->fetchAll());
    }

    // Map rows to response format
    $messages = array_map(function ($row) use ($cid) {
        return [
            'id'             => (int)$row['id'],
            'client_id'      => (int)$row['client_id'],
            'message'        => $row['message'],
            'author_name'    => $row['author_name'],
            'author_initial' => mb_strtoupper(mb_substr($row['author_name'], 0, 1)),
            'author_plan'    => $row['author_plan'],
            'is_mine'        => (int)$row['client_id'] === $cid,
            'created_at'     => $row['created_at'],
        ];
    }, $rows);

    respond([
        'ok'       => true,
        'messages' => $messages,
    ]);
}

// POST — Send message
// 1. Check ban
$banStmt = $db->prepare("
    SELECT banned_until FROM chat_bans
    WHERE client_id = ? AND banned_until > NOW()
    ORDER BY banned_until DESC
    LIMIT 1
");
$banStmt->execute([$cid]);
$ban = $banStmt->fetch();
if ($ban) {
    respondError('Estás suspendido del chat hasta ' . $ban['banned_until'], 403);
}

// 2. Rate limit — 2 seconds between messages
$rateStmt = $db->prepare("
    SELECT created_at FROM community_chat
    WHERE client_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$rateStmt->execute([$cid]);
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
$stmt = $db->prepare("
    INSERT INTO community_chat (client_id, message)
    VALUES (?, ?)
");
$stmt->execute([$cid, $cleanMessage]);
$newId = (int)$db->lastInsertId();

respond([
    'ok'       => true,
    'message'  => [
        'id'             => $newId,
        'client_id'      => $cid,
        'message'        => $cleanMessage,
        'author_name'    => $client['name'],
        'author_initial' => mb_strtoupper(mb_substr($client['name'], 0, 1)),
        'author_plan'    => $client['plan'],
        'is_mine'        => true,
        'created_at'     => date('Y-m-d H:i:s'),
    ],
    'filtered' => $filtered['flagged'],
], 201);
