<?php
/**
 * Migration: Add must_change_password column to clients table
 * Run: php api/setup/migrate-must-change-password.php
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

// Check if column exists
$check = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'must_change_password'");
if ((int) $check->fetchColumn() > 0) {
    echo "Column must_change_password already exists. Skipping.\n";
} else {
    $db->exec("ALTER TABLE clients ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash");
    echo "Added must_change_password column to clients table.\n";

    // Set flag for all active clients so they update on next login
    $db->exec("UPDATE clients SET must_change_password = 1 WHERE password_hash IS NOT NULL AND status = 'activo'");
    echo "Set must_change_password=1 for all active clients.\n";
}

echo "Migration complete.\n";
