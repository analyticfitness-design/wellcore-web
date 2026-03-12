<?php
/**
 * WellCore — Chat Typing Indicator
 * POST /api/chat/typing.php  — signal typing (sets DB flag)
 * GET  /api/chat/typing.php?client_id=X — check if other party is typing
 *
 * Uses a lightweight DB approach: stores last_typing timestamp.
 * If < 4 seconds ago, considered "typing".
 */
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Ensure table exists
try {
    $db->query("SELECT 1 FROM chat_typing LIMIT 1");
} catch (\Throwable $e) {
    $db->query("CREATE TABLE IF NOT EXISTS chat_typing (
        user_type ENUM('client','coach') NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        target_id INT UNSIGNED NOT NULL,
        last_typing DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_type, user_id, target_id)
    ) ENGINE=InnoDB");
}

$userType = peekTokenUserType();

if ($method === 'POST') {
    requireMethod('POST');
    $body = getJsonBody();

    if ($userType === 'client') {
        $client = authenticateClient();
        $clientId = (int)$client['id'];
        $stmt = $db->prepare("SELECT coach_id FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $coachId = (int)$stmt->fetchColumn();
        if (!$coachId) respond(['ok' => true]);

        $db->prepare("
            INSERT INTO chat_typing (user_type, user_id, target_id, last_typing)
            VALUES ('client', ?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_typing = NOW()
        ")->execute([$clientId, $coachId]);

    } else {
        $admin = authenticateAdmin();
        $clientId = (int)($body['client_id'] ?? 0);
        if (!$clientId) respondError('client_id requerido', 422);

        $db->prepare("
            INSERT INTO chat_typing (user_type, user_id, target_id, last_typing)
            VALUES ('coach', ?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_typing = NOW()
        ")->execute([$admin['id'], $clientId]);
    }

    respond(['ok' => true]);

} else {
    requireMethod('GET');

    if ($userType === 'client') {
        $client = authenticateClient();
        $clientId = (int)$client['id'];
        $stmt = $db->prepare("
            SELECT 1 FROM chat_typing
            WHERE user_type = 'coach' AND target_id = ? AND last_typing > DATE_SUB(NOW(), INTERVAL 4 SECOND)
        ");
        $stmt->execute([$clientId]);
        respond(['ok' => true, 'typing' => (bool)$stmt->fetchColumn()]);

    } else {
        $admin = authenticateAdmin();
        $clientId = (int)($_GET['client_id'] ?? 0);
        if (!$clientId) respondError('client_id requerido', 422);

        $stmt = $db->prepare("
            SELECT 1 FROM chat_typing
            WHERE user_type = 'client' AND user_id = ? AND target_id = ? AND last_typing > DATE_SUB(NOW(), INTERVAL 4 SECOND)
        ");
        $stmt->execute([$clientId, $admin['id']]);
        respond(['ok' => true, 'typing' => (bool)$stmt->fetchColumn()]);
    }
}
