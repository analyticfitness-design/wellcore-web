<?php
/**
 * WellCore v3 — Migracion: tablas api_logs + rate_limits
 * ACCESO: Solo admin autenticado o CLI
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cors.php';

requireSetupAuth();

header('Content-Type: application/json');

$db = getDB();
$results = [];

// api_logs
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS api_logs (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            endpoint    VARCHAR(255) NOT NULL,
            method      VARCHAR(10) NOT NULL,
            ip          VARCHAR(45) NOT NULL,
            user_id     INT UNSIGNED DEFAULT NULL,
            user_type   ENUM('client','admin') DEFAULT NULL,
            status_code SMALLINT UNSIGNED NOT NULL DEFAULT 200,
            duration_ms INT UNSIGNED DEFAULT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_endpoint (endpoint),
            INDEX idx_created (created_at),
            INDEX idx_user (user_type, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = 'api_logs OK';
} catch (\Throwable $e) {
    $results[] = 'api_logs error: ' . $e->getMessage();
}

// rate_limits
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            ip_hash      VARCHAR(64) NOT NULL,
            action       VARCHAR(50) NOT NULL,
            hit_count    INT UNSIGNED NOT NULL DEFAULT 1,
            window_start DATETIME NOT NULL,
            PRIMARY KEY (ip_hash, action),
            INDEX idx_window (window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = 'rate_limits OK';
} catch (\Throwable $e) {
    $results[] = 'rate_limits error: ' . $e->getMessage();
}

echo json_encode(['ok' => true, 'results' => $results]);
