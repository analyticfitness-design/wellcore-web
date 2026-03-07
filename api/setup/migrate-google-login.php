<?php
/**
 * Migration: Add google_id column to clients table
 * Run once: php api/setup/migrate-google-login.php
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

try {
    // Check if column already exists
    $cols = $db->query("SHOW COLUMNS FROM clients LIKE 'google_id'")->fetchAll();
    if (count($cols) > 0) {
        echo "Column google_id already exists, skipping.\n";
    } else {
        $db->exec("ALTER TABLE clients ADD COLUMN google_id VARCHAR(255) DEFAULT NULL AFTER email");
        echo "Added column: google_id\n";

        $db->exec("CREATE INDEX idx_google_id ON clients (google_id)");
        echo "Created index: idx_google_id\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nMigration complete.\n";
