<?php
/**
 * WellCore Fitness — Cleanup Demo Data
 * GET /api/setup/cleanup-demo.php?secret=WELLCORE_SETUP_2026
 *
 * Removes all demo client data while keeping admins intact.
 * Also creates the invitations table if it doesn't exist.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Disable FK checks for truncation
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');

    $tables = [
        'progress_photos',
        'checkins',
        'training_logs',
        'metrics',
        'assigned_plans',
        'payments',
        'client_profiles',
        'clients',
    ];

    // Get list of existing tables
    $existingTables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existingTables[] = $row[0];
    }

    $results = [];
    foreach ($tables as $table) {
        if (in_array($table, $existingTables)) {
            $db->exec("TRUNCATE TABLE `$table`");
            $results[] = "$table truncated";
        } else {
            $results[] = "$table skipped (not found)";
        }
    }

    // Delete only client auth tokens (keep admin tokens)
    if (in_array('auth_tokens', $existingTables)) {
        $stmt = $db->prepare("DELETE FROM auth_tokens WHERE user_type = ?");
        $stmt->execute(['client']);
        $results[] = 'auth_tokens (client) cleared: ' . $stmt->rowCount() . ' rows';
    } else {
        $results[] = 'auth_tokens skipped (not found)';
    }

    // Re-enable FK checks
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Create invitations table if not exists
    $invSql = "
        CREATE TABLE IF NOT EXISTS invitations (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code        VARCHAR(32) UNIQUE NOT NULL,
            plan        ENUM('esencial','metodo','elite') NOT NULL,
            email_hint  VARCHAR(255),
            note        VARCHAR(500),
            status      ENUM('pending','used','expired') DEFAULT 'pending',
            created_by  INT UNSIGNED,
            used_by     INT UNSIGNED,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at  TIMESTAMP NULL,
            used_at     TIMESTAMP NULL,
            INDEX idx_code (code),
            INDEX idx_status (status),
            FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
            FOREIGN KEY (used_by) REFERENCES clients(id) ON DELETE SET NULL
        )
    ";
    $db->exec($invSql);
    $results[] = 'invitations table ensured';

    echo json_encode([
        'ok' => true,
        'message' => 'Demo data cleaned successfully',
        'results' => $results
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Cleanup failed: ' . $e->getMessage()
    ]);
}
