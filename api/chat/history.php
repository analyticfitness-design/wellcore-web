<?php
/**
 * WellCore — Chat: Conversation History
 * GET /api/chat/history.php?client_id=X&page=1&limit=30
 * Auth: Bearer token (client or coach)
 */
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');

$db = getDB();
$userType = peekTokenUserType();

if ($userType === 'client') {
    $client   = authenticateClient();
    $clientId = (int)$client['id'];
} elseif ($userType === 'admin') {
    $admin    = authenticateAdmin();
    $clientId = (int)($_GET['client_id'] ?? 0);
    if (!$clientId) respondError('client_id requerido', 422);
} else {
    respondError('Authentication required', 401);
}

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(10, (int)($_GET['limit'] ?? 30)));
$offset = ($page - 1) * $limit;

$sessionId = 'coach_chat_' . $clientId;

// Get messages (newest first for pagination, then reverse for display)
$stmt = $db->prepare("
    SELECT id, role, content, sender_type, sender_id, message_type, read_at,
           created_at
    FROM chat_messages
    WHERE client_id = ? AND session_id = ?
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$clientId, $sessionId, $limit, $offset]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Reverse to chronological order
$messages = array_reverse($messages);

// Total count for pagination
$countStmt = $db->prepare("
    SELECT COUNT(*) FROM chat_messages
    WHERE client_id = ? AND session_id = ?
");
$countStmt->execute([$clientId, $sessionId]);
$total = (int)$countStmt->fetchColumn();

respond([
    'ok'       => true,
    'messages' => $messages,
    'page'     => $page,
    'limit'    => $limit,
    'total'    => $total,
    'pages'    => ceil($total / $limit),
]);
