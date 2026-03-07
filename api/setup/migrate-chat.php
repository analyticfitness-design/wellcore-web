<?php
/**
 * Migration: Chat tables
 * Creates community_chat, chat_reports, chat_bans
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireSetupAuth();
$db = getDB();

// community_chat
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS community_chat (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id   INT UNSIGNED NOT NULL,
            message     VARCHAR(500) NOT NULL,
            hidden      TINYINT(1) DEFAULT 0,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            INDEX idx_created (created_at DESC),
            INDEX idx_visible (hidden, created_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: community_chat\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "Table community_chat already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// chat_reports
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS chat_reports (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            chat_message_id INT UNSIGNED NOT NULL,
            reporter_id     INT UNSIGNED NOT NULL,
            reason          VARCHAR(100) DEFAULT 'inappropriate',
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_report (chat_message_id, reporter_id),
            FOREIGN KEY (chat_message_id) REFERENCES community_chat(id) ON DELETE CASCADE,
            FOREIGN KEY (reporter_id) REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: chat_reports\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "Table chat_reports already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// chat_bans
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS chat_bans (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id   INT UNSIGNED NOT NULL,
            reason      VARCHAR(255),
            banned_until TIMESTAMP NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            INDEX idx_client_until (client_id, banned_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created table: chat_bans\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "Table chat_bans already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\nChat migration complete.\n";
