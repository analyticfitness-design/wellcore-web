<?php
/**
 * WellCore Fitness — Migración v9 Coach Platform
 * ============================================================
 * 14 nuevas tablas: XP, gamificación, pods, video check-ins,
 * analytics, audio coaching, booking, referidos, PWA push.
 *
 * ACCESO: /api/setup/migrate-v9-coach-platform.php?secret=WC_MIGRATE_V9_2026
 * Ejecutar UNA SOLA VEZ en producción.
 * ============================================================
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();
$db = getDB();

$results = [];

function runDDL(PDO $db, string $label, string $sql): void {
    global $results;
    try {
        $db->query($sql);
        $results[] = ['ok' => true, 'label' => $label];
    } catch (\PDOException $e) {
        $results[] = ['ok' => false, 'label' => $label, 'error' => $e->getMessage()];
    }
}

// ── 1. client_xp ─────────────────────────────────────────────
runDDL($db, 'CREATE TABLE client_xp', "
    CREATE TABLE IF NOT EXISTS client_xp (
        id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id           VARCHAR(60) NOT NULL,
        xp_total            INT UNSIGNED NOT NULL DEFAULT 0,
        level               TINYINT UNSIGNED NOT NULL DEFAULT 1,
        streak_days         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        streak_last_date    DATE DEFAULT NULL,
        streak_protected    TINYINT(1) NOT NULL DEFAULT 0,
        updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_client (client_id),
        INDEX idx_level (level),
        INDEX idx_streak (streak_days)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 2. xp_events ─────────────────────────────────────────────
runDDL($db, 'CREATE TABLE xp_events', "
    CREATE TABLE IF NOT EXISTS xp_events (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id   VARCHAR(60) NOT NULL,
        event_type  ENUM('checkin','video_checkin','streak_7','streak_30','badge','challenge','referral','bonus') NOT NULL,
        xp_gained   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        description VARCHAR(255) NOT NULL DEFAULT '',
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client     (client_id),
        INDEX idx_type       (event_type),
        INDEX idx_created    (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 3. accountability_pods ────────────────────────────────────
runDDL($db, 'CREATE TABLE accountability_pods', "
    CREATE TABLE IF NOT EXISTS accountability_pods (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        coach_id    VARCHAR(60) NOT NULL,
        name        VARCHAR(100) NOT NULL,
        description VARCHAR(500) DEFAULT NULL,
        max_members TINYINT UNSIGNED NOT NULL DEFAULT 8,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_coach  (coach_id),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 4. pod_members ───────────────────────────────────────────
runDDL($db, 'CREATE TABLE pod_members', "
    CREATE TABLE IF NOT EXISTS pod_members (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pod_id      INT UNSIGNED NOT NULL,
        client_id   VARCHAR(60) NOT NULL,
        joined_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_client_pod (client_id),
        INDEX idx_pod (pod_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 5. pod_messages ──────────────────────────────────────────
runDDL($db, 'CREATE TABLE pod_messages', "
    CREATE TABLE IF NOT EXISTS pod_messages (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pod_id      INT UNSIGNED NOT NULL,
        client_id   VARCHAR(60) NOT NULL,
        message     TEXT NOT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pod_created (pod_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 6. coach_audio ───────────────────────────────────────────
runDDL($db, 'CREATE TABLE coach_audio', "
    CREATE TABLE IF NOT EXISTS coach_audio (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        coach_id    VARCHAR(60) NOT NULL,
        title       VARCHAR(200) NOT NULL,
        audio_url   VARCHAR(500) NOT NULL,
        duration_sec SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        category    VARCHAR(80) NOT NULL DEFAULT 'general',
        plan_access JSON DEFAULT NULL COMMENT 'null = todos los planes, array = planes permitidos',
        sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_coach  (coach_id),
        INDEX idx_active (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 7. video_checkins ────────────────────────────────────────
runDDL($db, 'CREATE TABLE video_checkins', "
    CREATE TABLE IF NOT EXISTS video_checkins (
        id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id             VARCHAR(60) NOT NULL,
        coach_id              VARCHAR(60) NOT NULL,
        media_type            ENUM('video','image') NOT NULL DEFAULT 'video',
        media_url             VARCHAR(500) NOT NULL,
        exercise_name         VARCHAR(200) NOT NULL DEFAULT '',
        notes                 TEXT DEFAULT NULL,
        coach_response        TEXT DEFAULT NULL,
        ai_response           TEXT DEFAULT NULL,
        ai_used               TINYINT(1) NOT NULL DEFAULT 0,
        status                ENUM('pending','coach_reviewed','ai_reviewed') NOT NULL DEFAULT 'pending',
        plan_uses_this_month  TINYINT UNSIGNED NOT NULL DEFAULT 0,
        responded_at          TIMESTAMP NULL DEFAULT NULL,
        created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client  (client_id),
        INDEX idx_coach   (coach_id),
        INDEX idx_status  (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 8. coach_analytics_snapshots ─────────────────────────────
runDDL($db, 'CREATE TABLE coach_analytics_snapshots', "
    CREATE TABLE IF NOT EXISTS coach_analytics_snapshots (
        id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        coach_id         VARCHAR(60) NOT NULL,
        snapshot_date    DATE NOT NULL,
        active_clients   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        churn_risk_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        checkins_week    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        revenue_month    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        avg_engagement   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_coach_date (coach_id, snapshot_date),
        INDEX idx_coach (coach_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 9. appointments ──────────────────────────────────────────
runDDL($db, 'CREATE TABLE appointments', "
    CREATE TABLE IF NOT EXISTS appointments (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        coach_id        VARCHAR(60) NOT NULL,
        client_id       VARCHAR(60) NOT NULL,
        scheduled_at    DATETIME NOT NULL,
        duration_min    TINYINT UNSIGNED NOT NULL DEFAULT 30,
        title           VARCHAR(200) NOT NULL DEFAULT 'Sesion 1:1',
        notes           TEXT DEFAULT NULL,
        meet_link       VARCHAR(500) DEFAULT NULL,
        status          ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_coach      (coach_id),
        INDEX idx_client     (client_id),
        INDEX idx_scheduled  (scheduled_at),
        INDEX idx_status     (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 10. coach_availability ───────────────────────────────────
runDDL($db, 'CREATE TABLE coach_availability', "
    CREATE TABLE IF NOT EXISTS coach_availability (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        coach_id    VARCHAR(60) NOT NULL,
        day_of_week TINYINT UNSIGNED NOT NULL COMMENT '0=Dom, 1=Lun, ..., 6=Sab',
        time_start  TIME NOT NULL,
        time_end    TIME NOT NULL,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        INDEX idx_coach     (coach_id),
        INDEX idx_day       (day_of_week),
        INDEX idx_active    (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 11. referral_trials ──────────────────────────────────────
runDDL($db, 'CREATE TABLE referral_trials', "
    CREATE TABLE IF NOT EXISTS referral_trials (
        id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        referral_code       VARCHAR(20) NOT NULL,
        referrer_client_id  VARCHAR(60) NOT NULL,
        referred_email      VARCHAR(255) NOT NULL,
        trial_days          TINYINT UNSIGNED NOT NULL DEFAULT 3,
        trial_starts_at     TIMESTAMP NULL DEFAULT NULL,
        trial_expires_at    TIMESTAMP NULL DEFAULT NULL,
        converted           TINYINT(1) NOT NULL DEFAULT 0,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_email (referred_email),
        INDEX idx_code      (referral_code),
        INDEX idx_referrer  (referrer_client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 12. coach_video_tips ─────────────────────────────────────
runDDL($db, 'CREATE TABLE coach_video_tips', "
    CREATE TABLE IF NOT EXISTS coach_video_tips (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        coach_id        VARCHAR(60) NOT NULL,
        title           VARCHAR(200) NOT NULL,
        video_url       VARCHAR(500) NOT NULL,
        thumbnail_url   VARCHAR(500) DEFAULT NULL,
        duration_sec    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        is_active       TINYINT(1) NOT NULL DEFAULT 1,
        sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_coach  (coach_id),
        INDEX idx_active (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 13. shared_achievements ──────────────────────────────────
runDDL($db, 'CREATE TABLE shared_achievements', "
    CREATE TABLE IF NOT EXISTS shared_achievements (
        id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id           VARCHAR(60) NOT NULL,
        achievement_type    VARCHAR(60) NOT NULL,
        achievement_data    JSON NOT NULL,
        share_token         VARCHAR(64) NOT NULL,
        views               INT UNSIGNED NOT NULL DEFAULT 0,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_token  (share_token),
        INDEX idx_client     (client_id),
        INDEX idx_type       (achievement_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 14. push_subscriptions ───────────────────────────────────
runDDL($db, 'CREATE TABLE push_subscriptions', "
    CREATE TABLE IF NOT EXISTS push_subscriptions (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id   VARCHAR(60) NOT NULL,
        endpoint    VARCHAR(1000) NOT NULL,
        p256dh      VARCHAR(200) NOT NULL,
        auth        VARCHAR(50) NOT NULL,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_endpoint (endpoint(500)),
        INDEX idx_client (client_id),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── Render results ────────────────────────────────────────────
$total = count($results);
$ok    = count(array_filter($results, fn($r) => $r['ok']));
$fail  = $total - $ok;

echo "<!DOCTYPE html><html><head><meta charset='utf-8'>";
echo "<title>WellCore v9 Migration</title>";
echo "<style>body{font-family:monospace;background:#111;color:#eee;padding:2rem}";
echo ".ok{color:#4ade80}.err{color:#f87171}h1{color:#ef4444}";
echo "table{border-collapse:collapse;width:100%}td,th{padding:.4rem 1rem;text-align:left}";
echo "tr:nth-child(even){background:#1a1a1a}</style></head><body>";
echo "<h1>WellCore v9 Coach Platform — Migración DB</h1>";
echo "<p>Tablas: <span class='ok'>{$ok} OK</span> / <span class='err'>{$fail} ERROR</span> / {$total} total</p>";
echo "<table><tr><th>#</th><th>Tabla</th><th>Resultado</th></tr>";
foreach ($results as $i => $r) {
    $cls = $r['ok'] ? 'ok' : 'err';
    $msg = $r['ok'] ? 'OK' : ('ERROR: ' . ($r['error'] ?? 'desconocido'));
    echo "<tr><td>" . ($i + 1) . "</td><td class='{$cls}'>{$r['label']}</td><td class='{$cls}'>{$msg}</td></tr>";
}
echo "</table>";
echo "<p style='margin-top:2rem;color:#888'>Ejecutado: " . date('Y-m-d H:i:s') . " UTC</p>";
echo "</body></html>";
