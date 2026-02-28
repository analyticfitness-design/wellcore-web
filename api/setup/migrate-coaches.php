<?php
/**
 * WellCore Fitness — Migracion Coach Personalization
 * ============================================================
 * Crea tablas para perfiles de coach, logros y referidos.
 * Altera clients e inscriptions para asociar coach/referral.
 * Auto-seed para coaches existentes.
 *
 * ACCESO: https://wellcorefitness.com/api/setup/migrate-coaches.php?secret=WC_COACH_MIGRATE_2026
 * ============================================================
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
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

function columnExists(PDO $db, string $table, string $column): bool {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

// ── 1. Tabla coach_profiles (no FK — avoids type mismatch) ───
runDDL($db, 'CREATE TABLE coach_profiles', "
    CREATE TABLE IF NOT EXISTS coach_profiles (
        id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id            INT NOT NULL UNIQUE,
        slug                VARCHAR(80) NOT NULL UNIQUE,
        bio                 TEXT,
        city                VARCHAR(100),
        experience          VARCHAR(20),
        specializations     JSON DEFAULT NULL,
        photo_url           VARCHAR(500),
        color_primary       VARCHAR(7) DEFAULT '#E31E24',
        logo_url            VARCHAR(500) DEFAULT NULL,
        whatsapp            VARCHAR(50),
        instagram           VARCHAR(100),
        referral_code       VARCHAR(30) UNIQUE,
        referral_commission DECIMAL(5,2) DEFAULT 5.00,
        public_visible      TINYINT(1) DEFAULT 1,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_admin (admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 2. Tabla coach_achievements ──────────────────────────────
runDDL($db, 'CREATE TABLE coach_achievements', "
    CREATE TABLE IF NOT EXISTS coach_achievements (
        id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id          INT NOT NULL,
        achievement_type  VARCHAR(50) NOT NULL,
        label             VARCHAR(100) NOT NULL,
        icon              VARCHAR(50) DEFAULT 'star',
        earned_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_achievement (admin_id, achievement_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 3. Tabla referral_stats ──────────────────────────────────
runDDL($db, 'CREATE TABLE referral_stats', "
    CREATE TABLE IF NOT EXISTS referral_stats (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        coach_id      INT NOT NULL,
        visitor_hash  VARCHAR(64),
        source_url    VARCHAR(500),
        converted     TINYINT(1) DEFAULT 0,
        conversion_id INT UNSIGNED DEFAULT NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_coach (coach_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── 4. ALTER clients: add coach_id ───────────────────────────
if (!columnExists($db, 'clients', 'coach_id')) {
    runDDL($db, 'ALTER clients: ADD coach_id', "
        ALTER TABLE clients ADD COLUMN coach_id INT DEFAULT NULL
    ");
} else {
    $results[] = ['ok' => true, 'label' => 'clients.coach_id ya existe -- sin cambios'];
}

// ── 5. ALTER inscriptions: add referral_code ─────────────────
try {
    if (!columnExists($db, 'inscriptions', 'referral_code')) {
        runDDL($db, 'ALTER inscriptions: ADD referral_code', "
            ALTER TABLE inscriptions
            ADD COLUMN referral_code VARCHAR(30) DEFAULT NULL
        ");
    } else {
        $results[] = ['ok' => true, 'label' => 'inscriptions.referral_code ya existe -- sin cambios'];
    }
} catch (\Throwable $e) {
    $results[] = ['ok' => false, 'label' => 'ALTER inscriptions (tabla puede no existir)', 'error' => $e->getMessage()];
}

// ── 6. Auto-seed coach_profiles para coaches existentes ──────
try {
    $coaches = $db->query("SELECT id, name, username FROM admins WHERE role = 'coach'")->fetchAll(PDO::FETCH_ASSOC);
    $seeded = 0;

    foreach ($coaches as $coach) {
        $base = $coach['name'] ?: $coach['username'];
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $base), '-'));
        if (!$slug) $slug = 'coach-' . $coach['id'];

        $exists = $db->prepare("SELECT COUNT(*) FROM coach_profiles WHERE admin_id = ?");
        $exists->execute([$coach['id']]);
        if ((int) $exists->fetchColumn() > 0) continue;

        $stmt = $db->prepare("
            INSERT INTO coach_profiles (admin_id, slug, referral_code)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$coach['id'], $slug, $slug]);
        $seeded++;
    }

    $results[] = ['ok' => true, 'label' => "Auto-seed coach_profiles: $seeded perfiles creados de " . count($coaches) . " coaches"];
} catch (\Throwable $e) {
    $results[] = ['ok' => false, 'label' => 'Auto-seed coach_profiles', 'error' => $e->getMessage()];
}

// ── 7. Crear directorio uploads/coaches/ ─────────────────────
$uploadsDir = __DIR__ . '/../../uploads/coaches';
if (!is_dir($uploadsDir)) {
    if (mkdir($uploadsDir, 0755, true)) {
        $results[] = ['ok' => true, 'label' => 'Directorio uploads/coaches/ creado'];
    } else {
        $results[] = ['ok' => false, 'label' => 'Directorio uploads/coaches/', 'error' => 'No se pudo crear el directorio'];
    }
} else {
    $results[] = ['ok' => true, 'label' => 'Directorio uploads/coaches/ ya existe -- sin cambios'];
}

// ── Resultados ────────────────────────────────────────────────
$ok    = array_filter($results, fn($r) =>  $r['ok']);
$fails = array_filter($results, fn($r) => !$r['ok']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>WellCore -- Migracion Coaches</title>
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
<h1>WELLCORE -- MIGRACION COACHES</h1>
<div class="sub">// COACH PERSONALIZATION: PROFILES + ACHIEVEMENTS + REFERRALS</div>

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
    MIGRACION COMPLETA -- <?= count($ok) ?> operaciones exitosas.
  <?php else: ?>
    ATENCION: <?= count($ok) ?> exitosas, <?= count($fails) ?> fallidas.
    Si el error dice "Table already exists" o "Duplicate key name", es normal.
  <?php endif; ?>
</div>

<div class="note">
  <strong style="color:#fff;">// Tablas creadas:</strong><br>
  <code>coach_profiles</code> -- Perfil publico del coach (slug, bio, color, logo, referral)<br>
  <code>coach_achievements</code> -- Logros y badges del coach<br>
  <code>referral_stats</code> -- Tracking de visitas y conversiones por referido<br><br>
  <strong style="color:#fff;">// Columnas agregadas:</strong><br>
  <code>clients.coach_id</code> -- FK al admin/coach asignado<br>
  <code>inscriptions.referral_code</code> -- Codigo de referido usado al inscribirse
</div>
</body>
</html>
