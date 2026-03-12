<?php
/**
 * WellCore — Coach AI Suggestion for Client
 * GET /api/coach/ai-suggestion.php?client_id=X
 * Returns an AI-generated coaching suggestion based on client data patterns.
 */
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$admin = authenticateAdmin();
$clientId = (int)($_GET['client_id'] ?? 0);

if (!$clientId) respondError('client_id requerido', 422);

$db = getDB();

// Verify coach owns this client (or is admin/superadmin)
$stmt = $db->prepare("SELECT id, name, plan FROM clients WHERE id = ? AND coach_id = ?");
$stmt->execute([$clientId, $admin['id']]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    if (!in_array($admin['role'], ['admin', 'superadmin'], true)) {
        respondError('Cliente no asignado a ti', 403);
    }
    $stmt = $db->prepare("SELECT id, name, plan FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) respondError('Cliente no encontrado', 404);
}

// Gather client data for AI analysis
$context = [];
$firstName = explode(' ', trim($client['name']))[0];
$context[] = "Nombre: " . $client['name'];
$context[] = "Plan: " . strtoupper($client['plan']);

// Streak + XP
try {
    $stmt = $db->prepare("SELECT current_streak, total_xp FROM client_xp WHERE client_id = ?");
    $stmt->execute([$clientId]);
    $xp = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($xp) {
        $context[] = "Racha actual: " . ($xp['current_streak'] ?? 0) . " dias";
        $context[] = "XP total: " . ($xp['total_xp'] ?? 0);
    }
} catch (\Throwable $e) {}

// Last 4 check-ins (trend analysis)
try {
    $stmt = $db->prepare("
        SELECT bienestar, dias_entrenados, nutricion_seguida, checkin_date
        FROM checkins WHERE client_id = ?
        ORDER BY checkin_date DESC LIMIT 4
    ");
    $stmt->execute([$clientId]);
    $checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($checkins) {
        $context[] = "Ultimos check-ins:";
        foreach ($checkins as $ci) {
            $context[] = "  - {$ci['checkin_date']}: bienestar {$ci['bienestar']}/10, {$ci['dias_entrenados']} dias, nutricion: {$ci['nutricion_seguida']}";
        }
    } else {
        $context[] = "Sin check-ins registrados.";
    }
} catch (\Throwable $e) {}

// Recent biometric trend
try {
    $stmt = $db->prepare("
        SELECT weight_kg, body_fat_pct, log_date
        FROM biometric_logs WHERE client_id = ?
        ORDER BY log_date DESC LIMIT 3
    ");
    $stmt->execute([$clientId]);
    $bios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($bios) {
        $context[] = "Metricas recientes:";
        foreach ($bios as $b) {
            $line = "  - {$b['log_date']}: peso {$b['weight_kg']}kg";
            if ($b['body_fat_pct']) $line .= ", grasa {$b['body_fat_pct']}%";
            $context[] = $line;
        }
    }
} catch (\Throwable $e) {}

// Habits completion this week
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total, SUM(completed) AS done
        FROM daily_habits
        WHERE client_id = ? AND habit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$clientId]);
    $habits = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($habits && (int)$habits['total'] > 0) {
        $pct = round(((int)$habits['done'] / (int)$habits['total']) * 100);
        $context[] = "Adherencia habitos ultimos 7 dias: {$habits['done']}/{$habits['total']} ({$pct}%)";
    }
} catch (\Throwable $e) {}

// Days since last activity
try {
    $stmt = $db->prepare("
        SELECT MAX(t.last_date) FROM (
            SELECT MAX(habit_date) AS last_date FROM daily_habits WHERE client_id = ? AND completed = 1
            UNION ALL
            SELECT MAX(checkin_date) FROM checkins WHERE client_id = ?
        ) t
    ");
    $stmt->execute([$clientId, $clientId]);
    $lastAny = $stmt->fetchColumn();
    if ($lastAny) {
        $daysSince = (int)((strtotime(date('Y-m-d')) - strtotime($lastAny)) / 86400);
        $context[] = "Dias desde ultima actividad: $daysSince";
    } else {
        $context[] = "Sin actividad registrada.";
    }
} catch (\Throwable $e) {}

// Days as client
try {
    $stmt = $db->prepare("SELECT created_at FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $created = $stmt->fetchColumn();
    if ($created) {
        $daysSinceJoin = (int)((time() - strtotime($created)) / 86400);
        $context[] = "Dias como cliente: $daysSinceJoin";
    }
} catch (\Throwable $e) {}

$contextStr = implode("\n", $context);

// Try Claude for intelligent suggestion
$useAI = !empty(getenv('CLAUDE_API_KEY')) && getenv('CLAUDE_API_KEY') !== 'sk-ant-REPLACE_WITH_YOUR_KEY';

if ($useAI) {
    try {
        require_once __DIR__ . '/../ai/helpers.php';

        $systemPrompt = <<<SYSTEM
Eres un asistente AI para coaches de fitness en WellCore Fitness.
Tu tarea: analizar los datos del cliente y generar UNA sugerencia concreta y accionable para el coach.

Reglas:
- Maximo 2 oraciones. Se directo.
- Usa el nombre del cliente.
- Sugiere una ACCION especifica: felicitar, motivar, ajustar, preguntar, intervenir.
- Basa tu sugerencia en los patrones de datos (tendencia de bienestar, adherencia, inactividad, rachas).
- Si los datos son positivos, sugiere refuerzo positivo.
- Si hay señales de riesgo (bienestar bajo, inactividad, rachas rotas), sugiere intervencion.
- Responde SOLO con la sugerencia, sin prefijos ni explicaciones.

DATOS DEL CLIENTE:
{$contextStr}
SYSTEM;

        $result = claude_call($systemPrompt, "Genera una sugerencia de coaching para este cliente.", 'claude-haiku-4-5-20251001', 150);
        $suggestion = trim($result['text'] ?? '');

        if ($suggestion) {
            respond([
                'ok' => true,
                'suggestion' => $suggestion,
                'client_name' => $firstName,
                'source' => 'ai'
            ]);
        }
    } catch (\Throwable $e) {
        error_log('[WellCore AI Suggestion] ' . $e->getMessage());
    }
}

// Fallback: rule-based suggestion
$suggestion = generateRuleBasedSuggestion($db, $clientId, $firstName, $checkins ?? [], $xp ?? null, $habits ?? null);

respond([
    'ok' => true,
    'suggestion' => $suggestion,
    'client_name' => $firstName,
    'source' => 'rules'
]);

function generateRuleBasedSuggestion(PDO $db, int $clientId, string $name, array $checkins, ?array $xp, ?array $habits): string {
    // Check streak
    $streak = (int)($xp['current_streak'] ?? 0);
    if ($streak >= 7) {
        return "$name lleva $streak dias de racha. Buen momento para felicitarlo y proponerle un reto mas exigente.";
    }

    // Check habits adherence
    if ($habits && (int)($habits['total'] ?? 0) > 0) {
        $pct = round(((int)($habits['done'] ?? 0) / (int)$habits['total']) * 100);
        if ($pct >= 80) {
            return "$name tiene $pct% de adherencia esta semana. Reconoce su consistencia y ajusta intensidad.";
        }
        if ($pct < 40) {
            return "$name solo completo $pct% de habitos esta semana. Preguntale si necesita simplificar su rutina.";
        }
    }

    // Check bienestar trend
    if (count($checkins) >= 2) {
        $latest = (int)($checkins[0]['bienestar'] ?? 5);
        $prev = (int)($checkins[1]['bienestar'] ?? 5);
        if ($latest <= 4 && $prev <= 5) {
            return "El bienestar de $name esta bajo ({$latest}/10). Preguntale como se siente y si necesita ajustar el plan.";
        }
        if ($latest >= 8 && $prev >= 7) {
            return "$name reporta bienestar alto ({$latest}/10) dos semanas seguidas. Aprovecha para subir la exigencia.";
        }
    }

    // Default
    return "Toca base con $name esta semana. Un mensaje breve de seguimiento refuerza el compromiso.";
}
