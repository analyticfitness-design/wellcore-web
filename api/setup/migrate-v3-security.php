<?php
/**
 * WellCore Fitness — Security Migration v3
 * ============================================================
 * Adds fingerprint and ip_address columns to auth_tokens.
 * Safe to run multiple times (uses SHOW COLUMNS check).
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db = getDB();
$results = [];

// Add fingerprint column if not exists
$stmt = $db->query("SHOW COLUMNS FROM auth_tokens LIKE 'fingerprint'");
if ($stmt->rowCount() === 0) {
    $db->exec("ALTER TABLE auth_tokens ADD COLUMN fingerprint CHAR(64) DEFAULT NULL AFTER token");
    $results[] = 'Added fingerprint column to auth_tokens';
} else {
    $results[] = 'fingerprint column already exists';
}

// Add ip_address column if not exists
$stmt = $db->query("SHOW COLUMNS FROM auth_tokens LIKE 'ip_address'");
if ($stmt->rowCount() === 0) {
    $db->exec("ALTER TABLE auth_tokens ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER fingerprint");
    $results[] = 'Added ip_address column to auth_tokens';
} else {
    $results[] = 'ip_address column already exists';
}

// Expand admins.role ENUM to support new roles
try {
    $db->exec("ALTER TABLE admins MODIFY COLUMN role ENUM('coach','admin','jefe','superadmin','coaches','clientes','coach_manager') DEFAULT 'coach'");
    $results[] = 'Expanded admins.role ENUM';
} catch (PDOException $e) {
    $results[] = 'admins.role ENUM: ' . $e->getMessage();
}

// Cleanup expired tokens
$stmt2 = $db->prepare("DELETE FROM auth_tokens WHERE expires_at < NOW()");
$stmt2->execute();
$deleted = $stmt2->rowCount();
$results[] = "Cleaned up $deleted expired tokens";

echo json_encode(['ok' => true, 'results' => $results], JSON_PRETTY_PRINT);
