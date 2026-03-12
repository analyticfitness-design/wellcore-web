<?php
/**
 * WellCore — Chat: Send Message
 * POST /api/chat/send.php
 * Auth: Bearer token (client or coach)
 * Body: { message: string, client_id?: int (coach only) }
 *
 * Limits per plan: Esencial 5/week, Metodo 15/week, Elite unlimited
 */
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/web-push.php';

requireMethod('POST');
$body = getJsonBody();

$message = trim($body['message'] ?? '');
if (!$message) respondError('message requerido', 422);
if (mb_strlen($message) > 2000) respondError('Mensaje demasiado largo (max 2000 caracteres)', 422);

$messageType = in_array($body['message_type'] ?? '', ['text', 'quick_reply'], true)
    ? $body['message_type']
    : 'text';

$db = getDB();

// Determine sender type
$userType = peekTokenUserType();

if ($userType === 'client') {
    $client = authenticateClient();
    $clientId   = (int)$client['id'];
    $senderType = 'client';
    $senderId   = $clientId;
    $plan       = $client['plan'] ?? 'esencial';

    // Check weekly message limit
    $limits = ['esencial' => 5, 'metodo' => 15, 'elite' => 9999];
    $maxWeek = $limits[$plan] ?? 5;

    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $stmt = $db->prepare("
        SELECT message_count FROM chat_weekly_limits
        WHERE client_id = ? AND week_start = ?
    ");
    $stmt->execute([$clientId, $weekStart]);
    $current = (int)($stmt->fetchColumn() ?: 0);

    if ($current >= $maxWeek) {
        respondError("Limite de mensajes semanal alcanzado ($maxWeek). Tu plan: $plan", 429, [
            'limit'   => $maxWeek,
            'used'    => $current,
            'plan'    => $plan
        ]);
    }

    // Upsert weekly count
    $db->prepare("
        INSERT INTO chat_weekly_limits (client_id, week_start, message_count)
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE message_count = message_count + 1
    ")->execute([$clientId, $weekStart]);

} elseif ($userType === 'admin') {
    $admin = authenticateAdmin();
    $clientId = (int)($body['client_id'] ?? 0);
    if (!$clientId) respondError('client_id requerido para coaches', 422);

    // Verify client exists
    $stmt = $db->prepare("SELECT id FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    if (!$stmt->fetchColumn()) respondError('Cliente no encontrado', 404);

    $senderType = 'coach';
    $senderId   = (int)$admin['id'];
} else {
    respondError('Authentication required', 401);
}

// Generate a session_id for this client-coach conversation
$sessionId = 'coach_chat_' . $clientId;

// Insert message
$stmt = $db->prepare("
    INSERT INTO chat_messages (client_id, session_id, role, content, sender_type, sender_id, message_type, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
");
$role = $senderType === 'client' ? 'user' : 'assistant';
$stmt->execute([$clientId, $sessionId, $role, $message, $senderType, $senderId, $messageType]);
$msgId = (int)$db->lastInsertId();

// Send push notification to the other party
if ($senderType === 'client') {
    // Notify coach — coaches are admins, we don't push to admins currently
    // But we could log for their dashboard polling
} else {
    // Coach sent message → push to client
    $coachName = $admin['name'] ?? 'Tu coach';
    $preview   = mb_strlen($message) > 80 ? mb_substr($message, 0, 80) . '...' : $message;
    webpush_send_to_client($db, $clientId, "Mensaje de $coachName", $preview, '/cliente.html#chat');
}

respond([
    'ok'         => true,
    'message_id' => $msgId,
    'sent_at'    => date('Y-m-d H:i:s'),
]);
