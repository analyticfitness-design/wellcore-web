<?php
/**
 * Migration: Add subscription tracking columns to clients table
 * Run once: php api/setup/migrate-renewal.php
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

$cols = [
    'subscription_start'     => "DATE DEFAULT NULL AFTER plan",
    'subscription_end'       => "DATE DEFAULT NULL AFTER subscription_start",
    'renewal_reminder_sent'  => "TINYINT(1) DEFAULT 0 AFTER subscription_end",
];

foreach ($cols as $col => $def) {
    $exists = $db->query("SHOW COLUMNS FROM clients LIKE '{$col}'")->fetchAll();
    if (count($exists) > 0) {
        echo "Column {$col} already exists, skipping.\n";
    } else {
        $db->exec("ALTER TABLE clients ADD COLUMN {$col} {$def}");
        echo "Added column: {$col}\n";
    }
}

// Backfill: set subscription_start = fecha_inicio, subscription_end = +30 days
$db->exec("
    UPDATE clients
    SET subscription_start = fecha_inicio,
        subscription_end   = DATE_ADD(fecha_inicio, INTERVAL 30 DAY)
    WHERE fecha_inicio IS NOT NULL
      AND subscription_start IS NULL
");
echo "Backfilled subscription dates from fecha_inicio.\n";

echo "\nMigration complete.\n";
