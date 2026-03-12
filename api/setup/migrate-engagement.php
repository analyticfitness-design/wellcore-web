<?php
/**
 * WellCore — Engagement & Retention Migration
 * Creates tables for: chat, daily missions, onboarding, weekly summaries, celebrations, coach presence
 */

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    require_once __DIR__ . '/../includes/response.php';
    require_once __DIR__ . '/../includes/auth.php';
    requireSetupAuth();
}

require_once __DIR__ . '/../config/database.php';
$db = getDB();
$results = [];

function runDDL(PDO $db, string $label, string $sql): void {
    global $results;
    try {
        $db->exec($sql);
        $results[] = ['ok' => true, 'label' => $label];
        echo "  [OK] $label\n";
    } catch (\PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate column') || str_contains($msg, 'Duplicate key')) {
            $results[] = ['ok' => true, 'label' => "$label (already exists)"];
            echo "  [SKIP] $label (already exists)\n";
        } else {
            $results[] = ['ok' => false, 'label' => $label, 'error' => $msg];
            echo "  [FAIL] $label: $msg\n";
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Engagement migration started\n\n";

// ── ALTER chat_messages: add read_at, message_type, sender_type, sender_id ──
runDDL($db, 'ALTER chat_messages ADD read_at', "
    ALTER TABLE chat_messages ADD COLUMN read_at DATETIME DEFAULT NULL
");

runDDL($db, 'ALTER chat_messages ADD message_type', "
    ALTER TABLE chat_messages ADD COLUMN message_type ENUM('text','quick_reply','system') DEFAULT 'text'
");

runDDL($db, 'ALTER chat_messages ADD sender_type', "
    ALTER TABLE chat_messages ADD COLUMN sender_type ENUM('client','coach','ai','system') DEFAULT 'client'
");

runDDL($db, 'ALTER chat_messages ADD sender_id', "
    ALTER TABLE chat_messages ADD COLUMN sender_id INT DEFAULT NULL
");

runDDL($db, 'INDEX chat_messages client+sender', "
    ALTER TABLE chat_messages ADD INDEX idx_chat_client_sender (client_id, sender_type, created_at)
");

// ── Coach presence ──────────────────────────────────────────────
runDDL($db, 'CREATE TABLE coach_presence', "
    CREATE TABLE IF NOT EXISTS coach_presence (
        admin_id INT NOT NULL PRIMARY KEY,
        last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status ENUM('online','away','offline') DEFAULT 'offline',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── Daily missions ──────────────────────────────────────────────
runDDL($db, 'CREATE TABLE daily_missions', "
    CREATE TABLE IF NOT EXISTS daily_missions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        mission_date DATE NOT NULL,
        missions JSON NOT NULL,
        completed INT DEFAULT 0,
        total INT DEFAULT 3,
        xp_awarded TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_client_date (client_id, mission_date),
        INDEX idx_date (mission_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── Onboarding steps ────────────────────────────────────────────
runDDL($db, 'CREATE TABLE onboarding_steps', "
    CREATE TABLE IF NOT EXISTS onboarding_steps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        step_key VARCHAR(50) NOT NULL,
        completed_at DATETIME DEFAULT NULL,
        skipped TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_client_step (client_id, step_key),
        INDEX idx_client (client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── Weekly summaries ────────────────────────────────────────────
runDDL($db, 'CREATE TABLE weekly_summaries', "
    CREATE TABLE IF NOT EXISTS weekly_summaries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        week_start DATE NOT NULL,
        data_json JSON NOT NULL,
        coach_note TEXT DEFAULT NULL,
        sent_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_client_week (client_id, week_start),
        INDEX idx_week (week_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── Celebrations ────────────────────────────────────────────────
runDDL($db, 'CREATE TABLE celebrations', "
    CREATE TABLE IF NOT EXISTS celebrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        event_type VARCHAR(50) NOT NULL,
        shown_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_client_event (client_id, event_type),
        INDEX idx_client (client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── Chat message limits tracking ────────────────────────────────
runDDL($db, 'CREATE TABLE chat_weekly_limits', "
    CREATE TABLE IF NOT EXISTS chat_weekly_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        week_start DATE NOT NULL,
        message_count INT DEFAULT 0,
        UNIQUE KEY uk_client_week (client_id, week_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$ok    = count(array_filter($results, fn($r) => $r['ok']));
$fail  = count(array_filter($results, fn($r) => !$r['ok']));
$total = count($results);

echo "\n[" . date('Y-m-d H:i:s') . "] Migration done. OK: $ok, FAIL: $fail, Total: $total\n";

if (php_sapi_name() !== 'cli') {
    respond(['ok' => $fail === 0, 'results' => $results, 'summary' => "OK: $ok, FAIL: $fail"]);
}
