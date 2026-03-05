<?php
/**
 * WellCore Fitness — Migración RISE
 * Agrega soporte para el RETO RISE 30 días en la base de datos.
 * CLI: php api/setup/migrate-rise.php
 * HTTP: requiere admin auth
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

// 1. Agregar 'rise' al ENUM plan en clients
runDDL($db, "ALTER clients.plan: agregar 'rise'", "
    ALTER TABLE clients
    MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise') NOT NULL
");

// 2. rise_start_date en client_profiles
$exists = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_profiles' AND COLUMN_NAME = 'rise_start_date'")->fetchColumn();
if (!$exists) {
    runDDL($db, 'ALTER client_profiles: agregar rise_start_date', "ALTER TABLE client_profiles ADD COLUMN rise_start_date DATE DEFAULT NULL");
} else {
    $results[] = ['ok' => true, 'label' => 'rise_start_date ya existe'];
}

// 3. rise_gender en client_profiles
$exists = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_profiles' AND COLUMN_NAME = 'rise_gender'")->fetchColumn();
if (!$exists) {
    runDDL($db, 'ALTER client_profiles: agregar rise_gender', "ALTER TABLE client_profiles ADD COLUMN rise_gender ENUM('mujer','hombre') DEFAULT NULL");
} else {
    $results[] = ['ok' => true, 'label' => 'rise_gender ya existe'];
}

// 4. rise_coach en client_profiles
$exists = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_profiles' AND COLUMN_NAME = 'rise_coach'")->fetchColumn();
if (!$exists) {
    runDDL($db, 'ALTER client_profiles: agregar rise_coach', "ALTER TABLE client_profiles ADD COLUMN rise_coach VARCHAR(100) DEFAULT NULL");
} else {
    $results[] = ['ok' => true, 'label' => 'rise_coach ya existe'];
}

// 5. Tabla rise_tracking
runDDL($db, 'CREATE TABLE rise_tracking', "
    CREATE TABLE IF NOT EXISTS rise_tracking (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        client_id       INT NOT NULL,
        log_date        DATE NOT NULL,
        training_done   TINYINT(1) DEFAULT 0,
        nutrition_done  TINYINT(1) DEFAULT 0,
        water_liters    DECIMAL(4,2) DEFAULT 0,
        sleep_hours     DECIMAL(4,2) DEFAULT 0,
        note            VARCHAR(500) DEFAULT NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_day (client_id, log_date),
        INDEX idx_client (client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 6. Tabla rise_measurements
runDDL($db, 'CREATE TABLE rise_measurements', "
    CREATE TABLE IF NOT EXISTS rise_measurements (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        client_id   INT NOT NULL,
        log_date    DATE NOT NULL,
        weight_kg   DECIMAL(5,2) DEFAULT NULL,
        chest_cm    DECIMAL(5,2) DEFAULT NULL,
        waist_cm    DECIMAL(5,2) DEFAULT NULL,
        hips_cm     DECIMAL(5,2) DEFAULT NULL,
        thigh_cm    DECIMAL(5,2) DEFAULT NULL,
        arm_cm      DECIMAL(5,2) DEFAULT NULL,
        notes       VARCHAR(500) DEFAULT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client (client_id),
        INDEX idx_date   (client_id, log_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 7. Tabla rise_habits_log
runDDL($db, 'CREATE TABLE rise_habits_log', "
    CREATE TABLE IF NOT EXISTS rise_habits_log (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        client_id   INT NOT NULL,
        log_date    DATE NOT NULL,
        habits_json JSON NOT NULL,
        completed   TINYINT UNSIGNED DEFAULT 0,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_day (client_id, log_date),
        INDEX idx_client (client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 8. Tabla progress_photos (si no se creó con schema.sql principal)
runDDL($db, 'CREATE TABLE progress_photos', "
    CREATE TABLE IF NOT EXISTS progress_photos (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id   INT UNSIGNED NOT NULL,
        photo_date  DATE NOT NULL,
        tipo        ENUM('frente','perfil','espalda') NOT NULL,
        filename    VARCHAR(255) NOT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client (client_id, photo_date),
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$ok    = array_filter($results, fn($r) =>  $r['ok']);
$fails = array_filter($results, fn($r) => !$r['ok']);
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Migración RISE</title>
<style>body{font-family:monospace;background:#0a0a0a;color:#fff;padding:40px;max-width:860px;margin:0 auto}h1{color:#E31E24}.item{padding:10px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:13px;display:flex;gap:14px}.ok{color:#22C55E}.err{color:#E31E24}small{color:#E31E24;font-size:11px;display:block;margin-top:4px}.box{margin-top:28px;padding:18px;border:1px solid;font-size:13px}.box-ok{border-color:#22C55E;color:#22C55E}.box-warn{border-color:#F59E0B;color:#F59E0B}</style>
</head><body>
<h1>WELLCORE — MIGRACIÓN RISE</h1>
<?php foreach ($results as $r): ?>
<div class="item"><span class="<?=$r['ok']?'ok':'err'?>"><?=$r['ok']?'✓':'✗'?></span><div><?=htmlspecialchars($r['label'])?><?php if(!$r['ok']&&isset($r['error'])): ?><small><?=htmlspecialchars($r['error'])?></small><?php endif; ?></div></div>
<?php endforeach; ?>
<div class="box <?=empty($fails)?'box-ok':'box-warn'?>">
<?php if(empty($fails)): ?>COMPLETA — <?=count($ok)?> operaciones. Siguiente: POST /api/admin/rise-onboarding
<?php else: ?>ATENCIÓN: <?=count($ok)?> OK / <?=count($fails)?> fallidas. Errores "already exists" son normales.<?php endif; ?>
</div>
</body></html>
