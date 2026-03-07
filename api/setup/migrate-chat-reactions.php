<?php
/**
 * Chat Reactions Migration — Create chat_message_reactions table
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireSetupAuth();

$db = getDB();
$results = [];

try {
    $db->exec("CREATE TABLE IF NOT EXISTS chat_message_reactions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        chat_message_id INT UNSIGNED NOT NULL,
        user_type ENUM('client','admin') NOT NULL DEFAULT 'client',
        client_id INT UNSIGNED NULL,
        admin_id INT UNSIGNED NULL,
        emoji VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_reaction (chat_message_id, user_type, client_id, admin_id, emoji),
        KEY idx_msg (chat_message_id)
    )");
    $results[] = 'chat_message_reactions: table created';
} catch (PDOException $e) {
    $results[] = 'chat_message_reactions ERROR: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'results' => $results], JSON_PRETTY_PRINT);
