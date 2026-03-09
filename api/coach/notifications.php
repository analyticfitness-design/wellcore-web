<?php
// GET /api/coach/notifications
// Auth: Bearer token (rol coach)
// Response: { notifications: [...], unread_count: N }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$coach = authenticateCoach();
$db = getDB();

$stmt = $db->prepare("
    SELECT id, type, title, body, link, read_at, created_at
    FROM notifications
    WHERE user_type = 'admin' AND user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$coach['id']]);
$notifications = $stmt->fetchAll();

$unread = $db->prepare("
    SELECT COUNT(*) FROM notifications
    WHERE user_type = 'admin' AND user_id = ? AND read_at IS NULL
");
$unread->execute([$coach['id']]);
$unreadCount = (int) $unread->fetchColumn();

respond([
    'notifications' => $notifications,
    'unread_count'  => $unreadCount,
]);
