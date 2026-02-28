<?php
/**
 * WellCore Fitness — Setup de Tablas IA
 * ============================================================
 * Ejecutar UNA SOLA VEZ para crear las tablas de IA.
 *
 * ACCESO: Protegido por secret. Visitar:
 * https://wellcorefitness.com/api/ai/setup-tables.php?secret=WC_AI_SETUP_2026
 * ============================================================
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();
$db = getDB();

$results = [];

function run_ddl(PDO $db, string $label, string $sql): void {
    global $results;
    try {
        $db->query($sql);
        $results[] = ['ok' => true, 'label' => $label];
    } catch (\PDOException $e) {
        $results[] = ['ok' => false, 'label' => $label, 'error' => $e->getMessage()];
    }
}

// ── Tabla ai_generations ──────────────────────────────────────
run_ddl($db, 'CREATE TABLE ai_generations', "
    CREATE TABLE IF NOT EXISTS ai_generations (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        client_id           INT DEFAULT NULL,
        type                ENUM('entrenamiento','nutricion','habitos','analisis','ticket_response') NOT NULL,
        ticket_id           VARCHAR(60) DEFAULT NULL,
        prompt_tokens       INT DEFAULT 0,
        completion_tokens   INT DEFAULT 0,
        model               VARCHAR(60) DEFAULT 'claude-opus-4-6',
        status              ENUM('queued','pending','completed','failed','approved','rejected') DEFAULT 'pending',
        raw_response        LONGTEXT DEFAULT NULL,
        parsed_json         LONGTEXT DEFAULT NULL,
        coach_notes         TEXT DEFAULT NULL,
        approved_by         INT DEFAULT NULL,
        approved_at         TIMESTAMP NULL DEFAULT NULL,
        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client    (client_id),
        INDEX idx_type      (type),
        INDEX idx_status    (status),
        INDEX idx_created   (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── Tabla ai_prompts ──────────────────────────────────────────
run_ddl($db, 'CREATE TABLE ai_prompts', "
    CREATE TABLE IF NOT EXISTS ai_prompts (
        id                    INT AUTO_INCREMENT PRIMARY KEY,
        type                  VARCHAR(60) UNIQUE NOT NULL,
        display_name          VARCHAR(120) DEFAULT NULL,
        system_prompt         TEXT NOT NULL,
        user_prompt_template  TEXT DEFAULT NULL,
        updated_by            INT DEFAULT NULL,
        updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── ALTER tickets — columnas IA ───────────────────────────────
// ADD COLUMN IF NOT EXISTS no existe en MySQL <8.0; verificamos manualmente
function col_exists(PDO $db, string $table, string $col): bool {
    try {
        $r = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
        return $r && $r->rowCount() > 0;
    } catch (\Throwable $e) { return false; }
}

if (!col_exists($db, 'tickets', 'ai_draft')) {
    run_ddl($db, 'ALTER tickets: ai_draft', "ALTER TABLE tickets ADD COLUMN ai_draft TEXT DEFAULT NULL");
} else {
    global $results; $results[] = ['ok' => true, 'label' => 'tickets.ai_draft ya existe'];
}
if (!col_exists($db, 'tickets', 'ai_status')) {
    run_ddl($db, 'ALTER tickets: ai_status', "ALTER TABLE tickets ADD COLUMN ai_status ENUM('none','pending','ready','approved') DEFAULT 'none'");
} else {
    global $results; $results[] = ['ok' => true, 'label' => 'tickets.ai_status ya existe'];
}
if (!col_exists($db, 'tickets', 'ai_generation_id')) {
    run_ddl($db, 'ALTER tickets: ai_generation_id', "ALTER TABLE tickets ADD COLUMN ai_generation_id INT DEFAULT NULL");
} else {
    global $results; $results[] = ['ok' => true, 'label' => 'tickets.ai_generation_id ya existe'];
}

// ── ALTER assigned_plans — columna ai_generation_id ──────────
if (!col_exists($db, 'assigned_plans', 'ai_generation_id')) {
    run_ddl($db, 'ALTER assigned_plans: ai_generation_id', "ALTER TABLE assigned_plans ADD COLUMN ai_generation_id INT DEFAULT NULL");
} else {
    global $results; $results[] = ['ok' => true, 'label' => 'assigned_plans.ai_generation_id ya existe'];
}

// ── Insertar prompts default ──────────────────────────────────
$defaultPrompts = [
    ['entrenamiento',   'Entrenamiento',        'Eres un entrenador de alto rendimiento de WellCore Fitness. Genera programas basados en ciencia con periodización real. Devuelve JSON estricto.'],
    ['nutricion',       'Nutrición',            'Eres un nutricionista deportivo de WellCore Fitness. Calcula TDEE, distribuye macros por evidencia y crea planes realistas. Devuelve JSON estricto.'],
    ['habitos',         'Hábitos',              'Eres un coach de bienestar de WellCore Fitness. Diseña planes de hábitos progresivos y sostenibles. Devuelve JSON estricto.'],
    ['ticket_response', 'Respuesta de Ticket',  'Eres el equipo de WellCore Fitness. Redacta respuestas directas, técnicas y útiles a los tickets. El coach revisa antes de enviar.'],
    ['analisis',        'Análisis de Progreso', 'Eres un analista de rendimiento de WellCore Fitness. Interpreta métricas y genera informes accionables. Devuelve JSON estricto.'],
];

foreach ($defaultPrompts as [$type, $displayName, $sysPrompt]) {
    try {
        $stmt = $db->prepare("INSERT IGNORE INTO ai_prompts (type, display_name, system_prompt) VALUES (?, ?, ?)");
        $stmt->execute([$type, $displayName, $sysPrompt]);
        $results[] = ['ok' => true, 'label' => "Prompt default: $type"];
    } catch (\PDOException $e) {
        $results[] = ['ok' => false, 'label' => "Prompt default: $type", 'error' => $e->getMessage()];
    }
}

// ── Resultados ────────────────────────────────────────────────
$ok    = array_filter($results, fn($r) => $r['ok']);
$fails = array_filter($results, fn($r) => !$r['ok']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>WellCore AI — Setup</title>
<style>
  * { box-sizing: border-box; }
  body  { font-family: 'JetBrains Mono', monospace; background: #0a0a0a; color: #fff; padding: 40px; max-width: 900px; margin: 0 auto; }
  h1    { color: #E31E24; font-family: Arial, sans-serif; letter-spacing: 2px; margin-bottom: 32px; }
  .ok   { color: #22C55E; }
  .err  { color: #E31E24; }
  .item { padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.06); font-size: 13px; display: flex; gap: 12px; align-items: flex-start; }
  .item small { color: #E31E24; font-size: 11px; display: block; margin-top: 4px; }
  .summary  { font-size: 14px; margin-top: 32px; padding: 20px; border: 1px solid; }
  .box-ok   { border-color: #22C55E; color: #22C55E; }
  .box-warn { border-color: #F59E0B; color: #F59E0B; }
  .next     { margin-top: 32px; padding: 20px; border: 1px solid rgba(255,255,255,0.1); }
  .next h3  { color: #00D9FF; font-size: 12px; letter-spacing: 2px; text-transform: uppercase; margin: 0 0 12px; }
  .next li  { font-size: 12px; color: rgba(255,255,255,0.5); padding: 4px 0; }
  code      { background: #1a1a1a; padding: 2px 6px; color: #00D9FF; }
</style>
</head>
<body>
<h1>WELLCORE AI — SETUP DE TABLAS</h1>

<?php foreach ($results as $r): ?>
<div class="item">
  <span class="<?= $r['ok'] ? 'ok' : 'err' ?>" style="flex-shrink:0;"><?= $r['ok'] ? '✓' : '✗' ?></span>
  <div>
    <?= htmlspecialchars($r['label']) ?>
    <?php if (!$r['ok'] && isset($r['error'])): ?>
      <small><?= htmlspecialchars($r['error']) ?></small>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="summary <?= empty($fails) ? 'box-ok' : 'box-warn' ?>">
  <?php if (empty($fails)): ?>
    ✓ SETUP COMPLETADO — <?= count($ok) ?> operaciones exitosas. Tablas IA listas.
  <?php else: ?>
    ⚠ <?= count($ok) ?> OK, <?= count($fails) ?> fallidos.
    Los errores de "column already exists" son normales si el setup ya se ejecutó antes.
  <?php endif; ?>
</div>

<div class="next">
  <h3>// Próximos Pasos</h3>
  <ol>
    <li>Editar <code>api/config/ai.php</code> — agregar tu API key de <a href="https://console.anthropic.com" style="color:#00D9FF;" target="_blank">console.anthropic.com</a></li>
    <li>Test: POST <code>/api/ai/generate</code> con Bearer token de admin y <code>{"client_id": 1}</code></li>
    <li>Cron cada 5 min: <code>*/5 * * * * php /ruta/api/ai/auto-trigger.php action=process_queue</code></li>
    <li>Cron semanal (domingo 8am): <code>0 8 * * 0 php /ruta/api/ai/auto-trigger.php action=weekly_analysis</code></li>
    <li>Abrir <code>/admin-ia.html</code> para el panel de control completo</li>
  </ol>
</div>
</body>
</html>
