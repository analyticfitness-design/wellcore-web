<?php
/**
 * Chat Migration v2 — Add admin/coach support to community chat
 * Adds user_type and admin_id columns to community_chat and chat_reports
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireSetupAuth();

$db = getDB();
$results = [];

try {
    // 1. ALTER community_chat — add user_type and admin_id
    $db->exec("ALTER TABLE community_chat
        ADD COLUMN user_type ENUM('client','admin') NOT NULL DEFAULT 'client' AFTER client_id,
        ADD COLUMN admin_id INT UNSIGNED NULL AFTER user_type
    ");
    $results[] = 'community_chat: added user_type and admin_id columns';
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $results[] = 'community_chat: columns already exist (skipped)';
    } else {
        $results[] = 'community_chat ERROR: ' . $e->getMessage();
    }
}

try {
    // 2. Make client_id nullable for admin messages
    $db->exec("ALTER TABLE community_chat MODIFY COLUMN client_id INT UNSIGNED NULL");
    $results[] = 'community_chat: client_id now nullable';
} catch (PDOException $e) {
    $results[] = 'community_chat client_id nullable ERROR: ' . $e->getMessage();
}

try {
    // 3. ALTER chat_reports — add reporter_type and reporter_admin_id
    $db->exec("ALTER TABLE chat_reports
        ADD COLUMN reporter_type ENUM('client','admin') NOT NULL DEFAULT 'client' AFTER reporter_id,
        ADD COLUMN reporter_admin_id INT UNSIGNED NULL AFTER reporter_type
    ");
    $results[] = 'chat_reports: added reporter_type and reporter_admin_id columns';
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $results[] = 'chat_reports: columns already exist (skipped)';
    } else {
        $results[] = 'chat_reports ERROR: ' . $e->getMessage();
    }
}

try {
    // 4. Make reporter_id nullable for admin reporters
    $db->exec("ALTER TABLE chat_reports MODIFY COLUMN reporter_id INT UNSIGNED NULL");
    $results[] = 'chat_reports: reporter_id now nullable';
} catch (PDOException $e) {
    $results[] = 'chat_reports reporter_id nullable ERROR: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'results' => $results], JSON_PRETTY_PRINT);
