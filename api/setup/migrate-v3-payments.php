<?php
/**
 * Migration: Add Wompi columns to payments table
 * Run once: php api/setup/migrate-v3-payments.php
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

$migrations = [
    "ALTER TABLE payments ADD COLUMN wompi_reference VARCHAR(100) AFTER payu_response",
    "ALTER TABLE payments ADD COLUMN wompi_transaction_id VARCHAR(100) AFTER wompi_reference",
    "ALTER TABLE payments ADD COLUMN payment_method VARCHAR(50) AFTER wompi_transaction_id",
    "ALTER TABLE payments MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise') NOT NULL",
    "ALTER TABLE payments MODIFY COLUMN currency VARCHAR(10) DEFAULT 'COP'",
    "ALTER TABLE payments MODIFY COLUMN amount DECIMAL(12,2) NOT NULL",
    "ALTER TABLE payments MODIFY COLUMN status ENUM('pending','approved','rejected','cancelled','declined','voided','error') DEFAULT 'pending'",
];

$indexes = [
    "ALTER TABLE payments ADD UNIQUE INDEX idx_wompi_ref (wompi_reference)",
];

echo "=== WellCore Payments Migration ===\n\n";

foreach ($migrations as $sql) {
    try {
        $db->query($sql);
        echo "[OK] " . substr($sql, 0, 80) . "...\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "[SKIP] Column already exists: " . substr($sql, 0, 60) . "\n";
        } else {
            echo "[ERR] " . $e->getMessage() . "\n";
        }
    }
}

foreach ($indexes as $sql) {
    try {
        $db->query($sql);
        echo "[OK] Index created\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "[SKIP] Index already exists\n";
        } else {
            echo "[ERR] " . $e->getMessage() . "\n";
        }
    }
}

echo "\nDone.\n";
