<?php
/**
 * WellCore Fitness — Migracion v3 (Tablas IA)
 * ============================================================
 * Crea tablas para: F1 nutrition tracking, F2 chatbot, F3 webhooks, F4 plan pipeline.
 *
 * ACCESO: /api/setup/migrate-v3-ai.php?secret=WC_AI_V3_2026
 * ============================================================
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();
$db = getDB();

$results = [];

function runDDL(PDO $db, string $label, string $sql): void {
    global $results;
    try {
        $db->exec($sql);
        $results[] = ['ok' => true, 'label' => $label];
    } catch (\PDOException $e) {
        $results[] = ['ok' => false, 'label' => $label, 'error' => $e->getMessage()];
    }
}

// ── F1: Nutrition Photo Tracking ─────────────────────────────

runDDL($db, 'CREATE TABLE nutrition_logs', "
    CREATE TABLE IF NOT EXISTS nutrition_logs (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id      INT UNSIGNED NOT NULL,
        image_path     VARCHAR(500) DEFAULT NULL,
        calories       SMALLINT UNSIGNED DEFAULT NULL,
        protein        DECIMAL(5,1) DEFAULT NULL,
        carbs          DECIMAL(5,1) DEFAULT NULL,
        fat            DECIMAL(5,1) DEFAULT NULL,
        foods          JSON DEFAULT NULL,
        meal_type      ENUM('desayuno','almuerzo','cena','snack','pre_entreno','post_entreno') DEFAULT NULL,
        confidence     ENUM('alta','media','baja') DEFAULT 'media',
        ai_raw         TEXT DEFAULT NULL,
        coach_comment  VARCHAR(500) DEFAULT NULL,
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client_date (client_id, created_at),
        INDEX idx_meal (client_id, meal_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

runDDL($db, 'CREATE TABLE nutrition_goals', "
    CREATE TABLE IF NOT EXISTS nutrition_goals (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id      INT UNSIGNED NOT NULL UNIQUE,
        calories       SMALLINT UNSIGNED DEFAULT 2200,
        protein        DECIMAL(5,1) DEFAULT 150,
        carbs          DECIMAL(5,1) DEFAULT 250,
        fat            DECIMAL(5,1) DEFAULT 65,
        updated_by     VARCHAR(100) DEFAULT 'system',
        updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── F2: Chatbot Messages ─────────────────────────────────────

runDDL($db, 'CREATE TABLE chat_messages', "
    CREATE TABLE IF NOT EXISTS chat_messages (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id      INT UNSIGNED DEFAULT NULL,
        session_id     VARCHAR(64) NOT NULL,
        role           ENUM('user','assistant','system') NOT NULL,
        content        TEXT NOT NULL,
        route          VARCHAR(30) DEFAULT NULL,
        model          VARCHAR(80) DEFAULT NULL,
        confidence     DECIMAL(3,2) DEFAULT NULL,
        escalated      TINYINT(1) DEFAULT 0,
        tokens_used    INT UNSIGNED DEFAULT 0,
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client  (client_id, created_at),
        INDEX idx_session (session_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── F3: Automation Webhooks Log ──────────────────────────────

runDDL($db, 'CREATE TABLE webhook_logs', "
    CREATE TABLE IF NOT EXISTS webhook_logs (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        webhook_type   VARCHAR(50) NOT NULL,
        payload        JSON DEFAULT NULL,
        result         JSON DEFAULT NULL,
        status         ENUM('success','error','pending') DEFAULT 'pending',
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_type_date (webhook_type, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── F4: AI Plan Pipeline ─────────────────────────────────────

$cols = $db->query("SHOW COLUMNS FROM ai_generations LIKE 'pipeline_stage'")->fetchAll();
if (empty($cols)) {
    runDDL($db, 'ALTER ai_generations ADD pipeline_stage', "
        ALTER TABLE ai_generations
        ADD COLUMN pipeline_stage VARCHAR(30) DEFAULT NULL AFTER status,
        ADD COLUMN pipeline_data JSON DEFAULT NULL AFTER pipeline_stage
    ");
}

// ── Output ───────────────────────────────────────────────────

$ok = count(array_filter($results, fn($r) => $r['ok']));
$total = count($results);

echo "<!DOCTYPE html><html><head><title>WellCore AI v3 Migration</title></head>";
echo "<body style='background:#0a0a0a;color:#fff;font-family:monospace;padding:40px'>";
echo "<h1 style='color:#E31E24'>WELLCORE AI v3 Migration</h1><hr style='border-color:#252528'>";
foreach ($results as $r) {
    $icon = $r['ok'] ? '<span style="color:#22C55E">OK</span>' : '<span style="color:#E31E24">FAIL</span>';
    $extra = isset($r['error']) ? " &mdash; <small style='color:#71717A'>" . htmlspecialchars($r['error']) . "</small>" : '';
    echo "<p>[{$icon}] " . htmlspecialchars($r['label']) . "{$extra}</p>";
}
echo "<hr style='border-color:#252528'><p style='color:#00D9FF'>Migracion: {$ok}/{$total} exitosas</p>";
echo "</body></html>";
