<?php
/**
 * WellCore — Chat: Unread Count
 * GET /api/chat/unread.php?client_id=X (coach) or no param (client)
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
    $client     = authenticateClient();
    $clientId   = (int)$client['id'];
    // Client sees unread from coach
    $senderFilter = 'coach';
} elseif ($userType === 'admin') {
    $admin = authenticateAdmin();
    $clientId = (int)($_GET['client_id'] ?? 0);

    if ($clientId) {
        // Unread for a specific client conversation
        $senderFilter = 'client';
    } else {
        // Total unread across all clients for this coach
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM chat_messages cm
            JOIN clients c ON c.id = cm.client_id
            WHERE cm.sender_type = 'client'
              AND cm.session_id LIKE 'coach_chat_%'
              AND cm.read_at IS NULL
              AND c.coach_id = ?
        ");
        $stmt->execute([$admin['id']]);
        respond(['ok' => true, 'unread' => (int)$stmt->fetchColumn()]);
    }
} else {
    respondError('Authentication required', 401);
}

$sessionId = 'coach_chat_' . $clientId;

$stmt = $db->prepare("
    SELECT COUNT(*) FROM chat_messages
    WHERE client_id = ? AND session_id = ? AND sender_type = ? AND read_at IS NULL
");
$stmt->execute([$clientId, $sessionId, $senderFilter]);

respond([
    'ok'     => true,
    'unread' => (int)$stmt->fetchColumn(),
]);
