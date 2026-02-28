<?php
/**
 * WellCore Fitness — Migración v2
 * ============================================================
 * Agrega las tablas faltantes y corrige constraints.
 * Ejecutar UNA SOLA VEZ en producción.
 *
 * ACCESO: https://wellcorefitness.com/api/setup/migrate-v2.php?secret=WC_MIGRATE_V2_2026
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
        $db->query($sql);
        $results[] = ['ok' => true, 'label' => $label];
    } catch (\PDOException $e) {
        $results[] = ['ok' => false, 'label' => $label, 'error' => $e->getMessage()];
    }
}

// ── 1. Tabla weight_logs ──────────────────────────────────────
runDDL($db, 'CREATE TABLE weight_logs', "
    CREATE TABLE IF NOT EXISTS weight_logs (
        id           VARCHAR(20) PRIMARY KEY,
        client_id    VARCHAR(60) NOT NULL,
        exercise     VARCHAR(255) NOT NULL,
        weight_kg    DECIMAL(6,2) NOT NULL,
        `sets`       TINYINT UNSIGNED NOT NULL,
        reps         SMALLINT UNSIGNED NOT NULL,
        rpe          DECIMAL(3,1) DEFAULT NULL,
        notes        VARCHAR(500) DEFAULT NULL,
        week_number  TINYINT UNSIGNED NOT NULL,
        `year`       SMALLINT UNSIGNED NOT NULL,
        `date`       DATETIME NOT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client   (client_id),
        INDEX idx_exercise (client_id, exercise),
        INDEX idx_week     (client_id, `year`, week_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 2. Tabla tickets ─────────────────────────────────────────
runDDL($db, 'CREATE TABLE tickets', "
    CREATE TABLE IF NOT EXISTS tickets (
        id               VARCHAR(60) PRIMARY KEY,
        coach_id         VARCHAR(60) NOT NULL,
        coach_name       VARCHAR(255) DEFAULT NULL,
        client_name      VARCHAR(255) NOT NULL,
        client_plan      VARCHAR(20) DEFAULT NULL,
        ticket_type      ENUM('rutina_nueva','cambio_rutina','nutricion','habitos','invitacion_cliente','otro') NOT NULL,
        description      TEXT NOT NULL,
        priority         ENUM('normal','alta') DEFAULT 'normal',
        status           ENUM('open','in_progress','closed') DEFAULT 'open',
        response         TEXT DEFAULT NULL,
        assigned_to      VARCHAR(100) DEFAULT NULL,
        deadline         DATETIME NOT NULL,
        resolved_at      DATETIME DEFAULT NULL,
        ai_draft         TEXT DEFAULT NULL,
        ai_status        ENUM('none','pending','ready','approved') DEFAULT 'none',
        ai_generation_id INT DEFAULT NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_coach  (coach_id),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 3. Tabla inscriptions ─────────────────────────────────────
runDDL($db, 'CREATE TABLE inscriptions', "
    CREATE TABLE IF NOT EXISTS inscriptions (
        id               VARCHAR(60) PRIMARY KEY,
        status           VARCHAR(50) DEFAULT 'pending_contact',
        plan             ENUM('esencial','metodo','elite') NOT NULL,
        nombre           VARCHAR(255) NOT NULL,
        apellido         VARCHAR(255) DEFAULT NULL,
        email            VARCHAR(255) NOT NULL,
        whatsapp         VARCHAR(50) NOT NULL,
        ciudad           VARCHAR(100) DEFAULT NULL,
        pais             VARCHAR(100) DEFAULT NULL,
        edad             TINYINT UNSIGNED DEFAULT NULL,
        objetivo         TEXT DEFAULT NULL,
        experiencia      VARCHAR(100) DEFAULT NULL,
        lesion           VARCHAR(100) DEFAULT NULL,
        detalle_lesion   TEXT DEFAULT NULL,
        dias_disponibles VARCHAR(100) DEFAULT NULL,
        horario          VARCHAR(100) DEFAULT NULL,
        como_conocio     VARCHAR(100) DEFAULT NULL,
        ip_hash          VARCHAR(64) DEFAULT NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email  (email),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 4. Tabla coach_applications ───────────────────────────────
runDDL($db, 'CREATE TABLE coach_applications', "
    CREATE TABLE IF NOT EXISTS coach_applications (
        id               VARCHAR(60) PRIMARY KEY,
        status           VARCHAR(50) DEFAULT 'pending',
        name             VARCHAR(255) NOT NULL,
        email            VARCHAR(255) NOT NULL,
        whatsapp         VARCHAR(50) NOT NULL,
        city             VARCHAR(100) DEFAULT NULL,
        bio              TEXT NOT NULL,
        experience       VARCHAR(20) NOT NULL,
        plan             VARCHAR(50) DEFAULT NULL,
        current_clients  VARCHAR(50) DEFAULT NULL,
        specializations  JSON DEFAULT NULL,
        referral         VARCHAR(255) DEFAULT NULL,
        ip_hash          VARCHAR(64) DEFAULT NULL,
        created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email  (email),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 5. UNIQUE KEY en metrics ──────────────────────────────────
// Verificar si ya existe antes de intentar agregarlo
$keyExists = false;
try {
    $check = $db->query("SHOW INDEX FROM metrics WHERE Key_name = 'unique_metric'");
    $keyExists = ($check && $check->rowCount() > 0);
} catch (\Throwable $e) {}

if (!$keyExists) {
    runDDL($db, 'ALTER metrics: UNIQUE KEY (client_id, log_date)', "
        ALTER TABLE metrics ADD UNIQUE KEY unique_metric (client_id, log_date)
    ");
} else {
    $results[] = ['ok' => true, 'label' => 'UNIQUE KEY unique_metric ya existe — sin cambios'];
}

// ── Resultados ────────────────────────────────────────────────
$ok    = array_filter($results, fn($r) =>  $r['ok']);
$fails = array_filter($results, fn($r) => !$r['ok']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>WellCore — Migración v2</title>
<style>
  * { box-sizing: border-box; }
  body  { font-family: 'Courier New', monospace; background: #0a0a0a; color: #fff; padding: 40px; max-width: 860px; margin: 0 auto; }
  h1    { color: #E31E24; letter-spacing: 3px; margin-bottom: 8px; }
  .sub  { color: #52525B; font-size: 12px; letter-spacing: 2px; margin-bottom: 32px; }
  .item { padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.06); font-size: 13px; display: flex; gap: 14px; }
  .ok   { color: #22C55E; flex-shrink: 0; }
  .err  { color: #E31E24; flex-shrink: 0; }
  small { color: #E31E24; font-size: 11px; display: block; margin-top: 4px; }
  .box  { margin-top: 28px; padding: 18px 22px; border: 1px solid; font-size: 13px; }
  .box-ok   { border-color: #22C55E; color: #22C55E; }
  .box-warn { border-color: #F59E0B; color: #F59E0B; }
  .note { margin-top: 24px; padding: 18px; border: 1px solid rgba(255,255,255,0.1); font-size: 12px; color: #71717A; line-height: 1.7; }
  code  { background: #1a1a1a; padding: 2px 6px; color: #00D9FF; }
</style>
</head>
<body>
<h1>WELLCORE — MIGRACIÓN v2</h1>
<div class="sub">// TABLAS FALTANTES + CONSTRAINTS</div>

<?php foreach ($results as $r): ?>
<div class="item">
  <span class="<?= $r['ok'] ? 'ok' : 'err' ?>"><?= $r['ok'] ? '&#10003;' : '&#10007;' ?></span>
  <div>
    <?= htmlspecialchars($r['label']) ?>
    <?php if (!$r['ok'] && isset($r['error'])): ?>
      <small><?= htmlspecialchars($r['error']) ?></small>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="box <?= empty($fails) ? 'box-ok' : 'box-warn' ?>">
  <?php if (empty($fails)): ?>
    MIGRACION COMPLETA — <?= count($ok) ?> operaciones exitosas.
  <?php else: ?>
    ATENCION: <?= count($ok) ?> exitosas, <?= count($fails) ?> fallidas.
    Si el error dice "Table already exists" o "Duplicate key name", es normal — ya estaba creada.
  <?php endif; ?>
</div>

<div class="note">
  <strong style="color:#fff;">// Si el UNIQUE KEY de metrics fallo:</strong><br>
  Significa que hay registros duplicados en esa tabla (mismo cliente, misma fecha).
  Eliminalos con este SQL en EasyPanel y luego vuelve a visitar esta URL:<br><br>
  <code>DELETE m1 FROM metrics m1 INNER JOIN metrics m2 ON m1.client_id = m2.client_id AND m1.log_date = m2.log_date WHERE m1.id > m2.id;</code>
</div>
</body>
</html>
