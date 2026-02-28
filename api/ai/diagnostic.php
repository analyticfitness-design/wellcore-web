<?php
/**
 * WellCore Fitness — Diagnóstico del Sistema IA
 * ============================================================
 * Ejecutar para verificar que todo el sistema IA funciona.
 *
 * ACCESO (protegido):
 * https://wellcorefitness.com/api/ai/diagnostic.php?secret=WC_DIAG_2026
 *
 * NO subir a producción sin protección. Eliminar cuando todo esté OK.
 * ============================================================
 */

header('Content-Type: text/html; charset=utf-8');

$secret = $_GET['secret'] ?? '';
if ($secret !== 'WC_DIAG_2026') {
    http_response_code(403);
    die('<h1>403 Forbidden</h1>');
}

$checks = [];

function check(string $label, callable $fn): array {
    $start = microtime(true);
    try {
        $result = $fn();
        $ms = round((microtime(true) - $start) * 1000);
        return ['ok' => true,  'label' => $label, 'detail' => $result, 'ms' => $ms];
    } catch (\Throwable $e) {
        $ms = round((microtime(true) - $start) * 1000);
        return ['ok' => false, 'label' => $label, 'detail' => $e->getMessage(), 'ms' => $ms];
    }
}

// ── 1. Config IA cargada ──────────────────────────────────────
$checks[] = check('Archivo api/config/ai.php cargable', function() {
    require_once __DIR__ . '/../config/ai.php';
    if (!defined('CLAUDE_API_KEY')) throw new \Exception('CLAUDE_API_KEY no definida');
    if (!defined('CLAUDE_MODEL'))   throw new \Exception('CLAUDE_MODEL no definida');
    if (!defined('AI_ENABLED'))     throw new \Exception('AI_ENABLED no definida');
    return 'Modelo: ' . CLAUDE_MODEL . ' | AI_ENABLED: ' . (AI_ENABLED ? 'true' : 'false');
});

// ── 2. API key configurada ────────────────────────────────────
$checks[] = check('API key de Claude configurada (no placeholder)', function() {
    if (!defined('CLAUDE_API_KEY')) require_once __DIR__ . '/../config/ai.php';
    if (CLAUDE_API_KEY === 'sk-ant-REPLACE_WITH_YOUR_KEY') {
        throw new \Exception('La API key es todavía el placeholder. Edita api/config/ai.php línea 10.');
    }
    return "Key configurada: Si";
});

// ── 3. Conexión a MySQL ───────────────────────────────────────
$checks[] = check('Conexión MySQL (wellcore_fitness)', function() {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();
    $v  = $db->query('SELECT VERSION() as v')->fetchColumn();
    return "MySQL $v conectado";
});

// ── 4. Tablas core ────────────────────────────────────────────
$checks[] = check('Tabla clients existe', function() {
    $db    = getDB();
    $count = $db->query('SELECT COUNT(*) FROM clients')->fetchColumn();
    return "$count clientes en DB";
});

$checks[] = check('Tabla ai_generations existe', function() {
    $db    = getDB();
    $count = $db->query('SELECT COUNT(*) FROM ai_generations')->fetchColumn();
    return "$count generaciones en DB";
});

$checks[] = check('Tabla ai_prompts existe', function() {
    $db    = getDB();
    $count = $db->query('SELECT COUNT(*) FROM ai_prompts')->fetchColumn();
    return "$count prompts configurados";
});

$checks[] = check('Tabla assigned_plans existe', function() {
    $db = getDB();
    $db->query('SELECT id FROM assigned_plans LIMIT 1');
    return 'OK';
});

// ── 5. Columnas IA en tickets ─────────────────────────────────
$checks[] = check('tickets.ai_status columna existe', function() {
    $db   = getDB();
    $cols = $db->query("SHOW COLUMNS FROM tickets LIKE 'ai_status'")->fetchAll();
    if (empty($cols)) throw new \Exception('Columna tickets.ai_status no existe. Ejecuta setup-tables.php');
    return 'Columna ai_status OK';
});

// ── 6. Conectividad a api.anthropic.com ──────────────────────
$checks[] = check('Conectividad a api.anthropic.com (DNS + SSL)', function() {
    $context = stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $raw  = @file_get_contents('https://api.anthropic.com', false, $context);
    $meta = $http_response_header ?? [];
    $code = 0;
    foreach ($meta as $h) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) $code = (int)$m[1];
    }
    // Anthropic devuelve 404 en la raíz — eso significa que llegamos
    if ($code === 0 && $raw === false) {
        throw new \Exception('No se puede alcanzar api.anthropic.com. Verificar firewall del servidor.');
    }
    return "Servidor alcanzable (HTTP $code desde raíz — normal)";
});

// ── 7. Test real de la API Claude (llamada mínima) ────────────
$checks[] = check('Llamada real a Claude API (test mínimo)', function() {
    if (!defined('CLAUDE_API_KEY')) require_once __DIR__ . '/../config/ai.php';
    if (CLAUDE_API_KEY === 'sk-ant-REPLACE_WITH_YOUR_KEY') {
        throw new \Exception('API key no configurada — no se puede probar. Ver check #2.');
    }

    require_once __DIR__ . '/helpers.php';
    $result = claude_call(
        'Eres un asistente de prueba. Responde solo con JSON.',
        'Devuelve exactamente este JSON: {"test": "ok", "modelo": "' . CLAUDE_MODEL . '"}'
    );

    $inp = $result['input_tokens'];
    $out = $result['output_tokens'];
    $cost = round(($inp / 1e6 * 15) + ($out / 1e6 * 75), 6);
    $text = substr($result['text'], 0, 80);
    return "OK ({$inp} inp / {$out} out tokens — \${$cost} USD) → {$text}...";
});

// ── 8. Cliente demo existe y tiene perfil ─────────────────────
$checks[] = check('Cliente demo "carlos@wellcore.com" con perfil completo', function() {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT c.id, c.name, c.plan, c.status, p.peso, p.altura, p.objetivo, p.nivel
        FROM clients c LEFT JOIN client_profiles p ON p.client_id = c.id
        WHERE c.email = 'carlos@wellcore.com' LIMIT 1
    ");
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) throw new \Exception('Cliente carlos@wellcore.com no encontrado. Crear en DB.');
    $missing = [];
    if (!$row['peso'])    $missing[] = 'peso';
    if (!$row['altura'])  $missing[] = 'altura';
    if (!$row['objetivo']) $missing[] = 'objetivo';
    if ($missing) throw new \Exception('Perfil incompleto, faltan: ' . implode(', ', $missing));
    return "ID {$row['id']} | {$row['name']} | Plan {$row['plan']} | {$row['peso']}kg / {$row['altura']}cm";
});

// ── 9. Cola de generaciones vacía o procesable ───────────────
$checks[] = check('Cola de generaciones (queued)', function() {
    $db     = getDB();
    $queued = (int) $db->query("SELECT COUNT(*) FROM ai_generations WHERE status='queued'")->fetchColumn();
    $failed = (int) $db->query("SELECT COUNT(*) FROM ai_generations WHERE status='failed'")->fetchColumn();
    return "Queued: $queued | Failed: $failed";
});

// ── 10. Generar plan de entrenamiento para cliente demo ───────
$checks[] = check('Generación completa: entrenamiento para carlos@wellcore.com', function() {
    if (!defined('CLAUDE_API_KEY')) require_once __DIR__ . '/../config/ai.php';
    if (CLAUDE_API_KEY === 'sk-ant-REPLACE_WITH_YOUR_KEY') {
        throw new \Exception('API key no configurada. Ver check #2.');
    }

    require_once __DIR__ . '/helpers.php';
    $db   = getDB();
    $stmt = $db->prepare("SELECT id FROM clients WHERE email='carlos@wellcore.com' LIMIT 1");
    $stmt->execute();
    $client = $stmt->fetch();
    if (!$client) throw new \Exception('Cliente carlos@wellcore.com no encontrado');

    $clientId = (int) $client['id'];
    $c        = get_client_for_ai($clientId);

    $sysPrompt = "Eres un entrenador experto de WellCore Fitness. Genera un microciclo de entrenamiento de 1 semana basado en el perfil del cliente. DEBES devolver ÚNICAMENTE JSON válido con esta estructura: {\"dias\":[{\"dia\":\"string\",\"enfoque\":\"string\",\"ejercicios\":[{\"nombre\":\"string\",\"series\":3,\"reps\":\"10-12\",\"descanso\":\"90s\",\"rir\":2}]}]}";
    $userPrompt = build_client_profile_text($c) . "\n\nGenera 3-4 días de entrenamiento para esta semana.";

    $result = claude_call($sysPrompt, $userPrompt);
    $parsed = extract_json_from_response($result['text']);

    if (!$parsed || empty($parsed['dias'])) {
        throw new \Exception('JSON no parseable. Respuesta: ' . substr($result['text'], 0, 200));
    }

    $diasCount = count($parsed['dias']);
    $inp  = $result['input_tokens'];
    $out  = $result['output_tokens'];
    $cost = round(($inp / 1e6 * 15) + ($out / 1e6 * 75), 6);
    return "OK — $diasCount días generados | {$inp} inp / {$out} out tokens | \${$cost} USD";
});

// ── Render HTML ───────────────────────────────────────────────
$passed = count(array_filter($checks, fn($c) => $c['ok']));
$failed = count(array_filter($checks, fn($c) => !$c['ok']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>WellCore AI — Diagnóstico</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body  { font-family: 'JetBrains Mono', monospace; background: #0a0a0a; color: #fff; padding: 40px; max-width: 1000px; margin: 0 auto; }
h1    { color: #E31E24; font-family: Arial, sans-serif; letter-spacing: 3px; margin-bottom: 8px; font-size: 22px; }
.sub  { color: rgba(255,255,255,.35); font-size: 11px; margin-bottom: 32px; }
.check { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,.05); display: grid; grid-template-columns: 24px 1fr 60px; gap: 12px; align-items: start; }
.check:hover { background: rgba(255,255,255,.02); }
.ok   { color: #22C55E; font-size: 16px; }
.err  { color: #E31E24; font-size: 16px; }
.label { font-size: 13px; font-weight: bold; }
.detail { font-size: 11px; color: rgba(255,255,255,.45); margin-top: 4px; word-break: break-all; }
.detail.err { color: #F87171; }
.ms   { font-size: 10px; color: rgba(255,255,255,.2); text-align: right; padding-top: 2px; }
.summary { margin-top: 32px; padding: 20px; border: 2px solid; font-size: 14px; font-weight: bold; letter-spacing: 1px; }
.s-ok   { border-color: #22C55E; color: #22C55E; }
.s-warn { border-color: #F59E0B; color: #F59E0B; }
.s-fail { border-color: #E31E24; color: #E31E24; }
.next { margin-top: 24px; padding: 20px; border: 1px solid rgba(255,255,255,.1); }
.next h3 { color: #00D9FF; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 12px; }
.next li  { font-size: 12px; color: rgba(255,255,255,.5); padding: 5px 0; line-height: 1.5; }
code { background: rgba(0,217,255,.08); color: #00D9FF; padding: 1px 5px; border-radius: 2px; font-size: 11px; }
a    { color: #00D9FF; }
.badge { display: inline-block; background: #E31E24; color: #fff; font-size: 9px; font-family: 'JetBrains Mono', monospace; padding: 2px 6px; letter-spacing: 1px; margin-left: 8px; vertical-align: middle; }
</style>
</head>
<body>

<h1>WELLCORE AI <span class="badge">DIAGNÓSTICO</span></h1>
<div class="sub">Generado: <?= date('Y-m-d H:i:s') ?> UTC | <?= count($checks) ?> verificaciones</div>

<?php foreach ($checks as $i => $c): ?>
<div class="check">
  <span class="<?= $c['ok'] ? 'ok' : 'err' ?>"><?= $c['ok'] ? '✓' : '✗' ?></span>
  <div>
    <div class="label"><?= ($i + 1) ?>. <?= htmlspecialchars($c['label']) ?></div>
    <div class="detail <?= $c['ok'] ? '' : 'err' ?>"><?= htmlspecialchars($c['detail']) ?></div>
  </div>
  <div class="ms"><?= $c['ms'] ?>ms</div>
</div>
<?php endforeach; ?>

<div class="summary <?= $failed === 0 ? 's-ok' : ($passed >= 7 ? 's-warn' : 's-fail') ?>">
<?php if ($failed === 0): ?>
  ✓ SISTEMA IA 100% OPERATIVO — <?= $passed ?>/<?= count($checks) ?> verificaciones pasadas.
<?php elseif ($passed >= 7): ?>
  ⚠ SISTEMA PARCIAL — <?= $passed ?>/<?= count($checks) ?> OK. Ver errores arriba.
<?php else: ?>
  ✗ SISTEMA NO OPERATIVO — <?= $failed ?> errores críticos. Resolver antes de continuar.
<?php endif; ?>
</div>

<?php if ($failed > 0): ?>
<div class="next">
  <h3>// Acciones Requeridas</h3>
  <ol>
    <?php foreach ($checks as $c): if ($c['ok']) continue; ?>
    <li>
      <strong><?= htmlspecialchars($c['label']) ?></strong><br>
      <span style="color:#F87171"><?= htmlspecialchars($c['detail']) ?></span>
    </li>
    <?php endforeach; ?>
  </ol>
</div>
<?php endif; ?>

<div class="next" style="margin-top:24px;">
  <h3>// Próximos pasos</h3>
  <ol>
    <li>Configurar API key: editar <code>api/config/ai.php</code> línea 10</li>
    <li>Crear tablas IA: visitar <a href="setup-tables.php?secret=WC_AI_SETUP_2026">setup-tables.php?secret=WC_AI_SETUP_2026</a></li>
    <li>Procesar cola manualmente: <a href="auto-trigger.php?action=process_queue&secret=WC_DIAG_2026">auto-trigger.php?action=process_queue</a></li>
    <li>Ver panel admin IA: <code>/admin-ia.html</code> (requiere login admin)</li>
    <li>Eliminar este archivo cuando todo esté OK: <code>api/ai/diagnostic.php</code></li>
  </ol>
</div>

</body>
</html>
