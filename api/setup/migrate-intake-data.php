<?php
/**
 * Migration: Add intake_data JSON column to client_profiles
 * Run once: php api/setup/migrate-intake-data.php
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

try {
    $db->exec("ALTER TABLE client_profiles ADD COLUMN intake_data JSON DEFAULT NULL AFTER macros");
    echo "Added column: intake_data\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column intake_data already exists, skipping.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\nMigration complete.\n";
