<?php
/**
 * WellCore — Chat: Mark Messages as Read
 * POST /api/chat/mark-read.php
 * Auth: Bearer token (client or coach)
 * Body: { client_id?: int (coach only) }
 */
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$body = getJsonBody();

$db = getDB();
$userType = peekTokenUserType();

if ($userType === 'client') {
    $client     = authenticateClient();
    $clientId   = (int)$client['id'];
    // Mark coach messages as read by client
    $senderFilter = 'coach';
} elseif ($userType === 'admin') {
    $admin    = authenticateAdmin();
    $clientId = (int)($body['client_id'] ?? 0);
    if (!$clientId) respondError('client_id requerido', 422);
    // Mark client messages as read by coach
    $senderFilter = 'client';
} else {
    respondError('Authentication required', 401);
}

$sessionId = 'coach_chat_' . $clientId;

$stmt = $db->prepare("
    UPDATE chat_messages
    SET read_at = NOW()
    WHERE client_id = ? AND session_id = ? AND sender_type = ? AND read_at IS NULL
");
$stmt->execute([$clientId, $sessionId, $senderFilter]);
$marked = $stmt->rowCount();

respond([
    'ok'     => true,
    'marked' => $marked,
]);
