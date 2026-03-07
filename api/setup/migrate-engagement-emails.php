<?php
/**
 * Migration: Create engagement_emails tracking table
 * Run once: php api/setup/migrate-engagement-emails.php
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS engagement_emails (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id   INT UNSIGNED NOT NULL,
            email_type  ENUM('day10_motivation', 'day20_value') NOT NULL,
            cycle_month DATE NOT NULL,
            sent_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_email (client_id, email_type, cycle_month),
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Created table: engagement_emails\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "Table engagement_emails already exists, skipping.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\nMigration complete.\n";
