<?php
/**
 * WellCore v3 — Migracion: tabla rate_limits
 * ACCESO: Solo admin autenticado o CLI
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cors.php';

requireSetupAuth();

header('Content-Type: application/json');

$db = getDB();
$results = [];

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
    $results[] = 'Table rate_limits created/verified';
} catch (\Throwable $e) {
    $results[] = 'Error: ' . $e->getMessage();
}

echo json_encode(['ok' => true, 'results' => $results]);
