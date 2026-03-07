<?php
/**
 * Migration: Add body measurement columns to metrics table
 * Run once: php api/setup/migrate-metrics-body.php
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

$columns = [
    'pecho'   => 'DECIMAL(5,1) DEFAULT NULL AFTER porcentaje_grasa',
    'cintura' => 'DECIMAL(5,1) DEFAULT NULL AFTER pecho',
    'cadera'  => 'DECIMAL(5,1) DEFAULT NULL AFTER cintura',
    'muslo'   => 'DECIMAL(5,1) DEFAULT NULL AFTER cadera',
    'brazo'   => 'DECIMAL(5,1) DEFAULT NULL AFTER muslo',
];

foreach ($columns as $col => $def) {
    try {
        $db->exec("ALTER TABLE metrics ADD COLUMN {$col} {$def}");
        echo "Added column: {$col}\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "Column {$col} already exists, skipping.\n";
        } else {
            echo "Error adding {$col}: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nMigration complete.\n";
