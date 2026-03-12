<?php
/**
 * WellCore — Chat: Send Photo
 * POST /api/chat/send-photo.php (multipart/form-data)
 * Auth: Bearer token (client or coach)
 */
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');

$db = getDB();
$userType = peekTokenUserType();

if ($userType === 'client') {
    $user = authenticateClient();
    $clientId = (int)$user['id'];
    $senderType = 'client';
    $senderId = $clientId;
} elseif ($userType === 'admin') {
    $user = authenticateAdmin();
    $clientId = (int)($_POST['client_id'] ?? 0);
    if (!$clientId) respondError('client_id requerido', 422);
    $senderType = 'coach';
    $senderId = (int)$user['id'];
} else {
    respondError('Authentication required', 401);
}

// Validate file
if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    respondError('Foto requerida', 422);
}

$file = $_FILES['photo'];
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    respondError('Foto demasiado grande (max 5MB)', 422);
}

$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed, true)) {
    respondError('Formato no soportado. Usa JPG, PNG, WebP o GIF.', 422);
}

// Save file
$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$extension = $ext[$mime] ?? 'jpg';
$filename = 'chat_' . $clientId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$uploadDir = realpath(__DIR__ . '/../../uploads/chat') ?: __DIR__ . '/../../uploads/chat';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destPath = $uploadDir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    respondError('Error guardando foto', 500);
}

$photoUrl = '/uploads/chat/' . $filename;
$sessionId = 'coach_chat_' . $clientId;
$content = '[photo:' . $photoUrl . ']';

// Save message
$stmt = $db->prepare("
    INSERT INTO chat_messages (client_id, session_id, role, content, sender_type, sender_id, message_type, created_at)
    VALUES (?, ?, ?, ?, ?, ?, 'photo', NOW())
");
$role = $senderType === 'client' ? 'user' : 'assistant';
$stmt->execute([$clientId, $sessionId, $role, $content, $senderType, $senderId]);

// Push notification to client if coach sent
if ($senderType === 'coach') {
    require_once __DIR__ . '/../includes/web-push.php';
    $coachName = $user['name'] ?? 'Tu Coach';
    webpush_send_to_client($db, $clientId, "Foto de $coachName", 'Tu coach te envio una foto', '/cliente.html#chat');
}

respond([
    'ok' => true,
    'photo_url' => $photoUrl,
    'sent_at' => date('Y-m-d H:i:s')
]);
