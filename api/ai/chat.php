<?php
declare(strict_types=1);
/**
 * WellCore Fitness — F2: Chatbot IA con RAG local
 * ============================================================
 * POST /api/ai/chat
 *
 * Auth:  Bearer token de cliente (o sin auth para visitantes con limits)
 * Body:  { message: string, session_id?: string }
 *
 * Usa el Router IA local. Primero busca en la knowledge base local,
 * luego envia al LLM con contexto RAG.
 * ============================================================
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('POST');
$body = getJsonBody();

$message   = trim($body['message'] ?? '');
$sessionId = $body['session_id'] ?? bin2hex(random_bytes(16));

if (!$message) {
    respondError('message requerido', 422);
}
if (strlen($message) > 1000) {
    respondError('Mensaje demasiado largo (max 1000 caracteres)', 422);
}

// Auth opcional: clientes autenticados tienen mas cuota
$clientId = null;
$plan     = 'visitor';
$token    = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/^Bearer\s+(.+)$/i', $token, $m)) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.user_id, c.plan FROM auth_tokens t
        JOIN clients c ON c.id = t.user_id
        WHERE t.token = ? AND t.user_type = 'client' AND t.expires_at > NOW()
    ");
    $stmt->execute([$m[1]]);
    $row = $stmt->fetch();
    if ($row) {
        $clientId = (int) $row['user_id'];
        $plan = $row['plan'];
    }
}

// Rate limit por sesion
$db = getDB();
$countStmt = $db->prepare("
    SELECT COUNT(*) FROM chat_messages
    WHERE session_id = ? AND role = 'user'
    AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$countStmt->execute([$sessionId]);
$hourlyCount = (int) $countStmt->fetchColumn();

$limits = ['visitor' => 5, 'esencial' => 10, 'metodo' => 30, 'elite' => 999];
$maxPerHour = $limits[$plan] ?? 5;

if ($hourlyCount >= $maxPerHour) {
    respondError("Limite de mensajes por hora alcanzado ({$maxPerHour}). Intenta mas tarde.", 429);
}

// ── Knowledge Base local (RAG simple) ────────────────────────

$kbPath = __DIR__ . '/../data/knowledge-base.json';
$context = '';
$topMatches = [];

if (file_exists($kbPath)) {
    $kb = json_decode(file_get_contents($kbPath), true) ?: [];
    $msgNorm = mb_strtolower($message);

    // Buscar las entradas mas relevantes (keyword matching simple)
    $matches = [];
    foreach ($kb as $entry) {
        $score = 0;
        foreach ($entry['keywords'] ?? [] as $kw) {
            if (mb_strpos($msgNorm, mb_strtolower($kw)) !== false) {
                $score += 2;
            }
        }
        if ($score > 0) {
            $matches[] = ['score' => $score, 'content' => $entry['content']];
        }
    }

    // Top 3 resultados
    usort($matches, fn($a, $b) => $b['score'] - $a['score']);
    $topMatches = array_slice($matches, 0, 3);

    if ($topMatches) {
        $context = "CONTEXTO DE WELLCORE FITNESS (usa esta informacion para responder):\n\n";
        foreach ($topMatches as $m) {
            $context .= "---\n" . $m['content'] . "\n";
        }
        $context .= "---\n\n";
    }
}

// ── AI Response: Claude Haiku with client context ─────────────
// Try Claude first, fallback to KB if API key not configured or fails.

$route = 'kb_local';
$model = 'knowledge_base';
$aiContent = '';
$tokensUsed = 0;

$useAI = !empty(getenv('CLAUDE_API_KEY')) && getenv('CLAUDE_API_KEY') !== 'sk-ant-REPLACE_WITH_YOUR_KEY';

if ($useAI && $clientId) {
    try {
        require_once __DIR__ . '/helpers.php';

        // Build client context
        $clientContext = buildClientContext($db, $clientId, $plan);
        $kbContext = $context ?: ''; // KB matches from above

        $systemPrompt = <<<SYSTEM
Eres el asistente AI de WellCore Fitness. Tu nombre es WellCore AI.
Eres motivador, honesto y conciso. Usa el nombre del cliente cuando sea natural.
Responde SOLO sobre fitness, entrenamiento, nutricion, bienestar y habitos saludables.
Para temas medicos, lesiones serias, o condiciones de salud, responde: "Te recomiendo consultar con tu coach o un profesional de salud."
Maximo 300 tokens en tu respuesta. Se directo y util.

DATOS DEL CLIENTE:
{$clientContext}

{$kbContext}
SYSTEM;

        // Get recent conversation for context (last 6 messages)
        $recentStmt = $db->prepare("
            SELECT role, content FROM chat_messages
            WHERE session_id = ? AND client_id = ?
            ORDER BY created_at DESC LIMIT 6
        ");
        $recentStmt->execute([$sessionId, $clientId]);
        $recentMsgs = array_reverse($recentStmt->fetchAll(PDO::FETCH_ASSOC));

        // Use Haiku for cost efficiency
        $aiModel = 'claude-haiku-4-5-20251001';
        $result = claude_call($systemPrompt, $message, $aiModel, 300);

        $aiContent = $result['text'] ?? '';
        $tokensUsed = ($result['input_tokens'] ?? 0) + ($result['output_tokens'] ?? 0);
        $route = 'claude_ai';
        $model = $aiModel;
    } catch (\Throwable $e) {
        error_log('[WellCore AI Chat] Claude fallback: ' . $e->getMessage());
        $aiContent = ''; // Will fall through to KB
    }
}

// Fallback to KB if Claude didn't produce a response
if (!$aiContent) {
    if ($topMatches) {
        $best = $topMatches[0]['content'];
        $extra = count($topMatches) > 1 ? "\n\n" . $topMatches[1]['content'] : '';
        $aiContent = $best . $extra;
    } else {
        $aiContent = "Hola! Soy el asistente de WellCore Fitness.\n\n"
            . "No tengo informacion exacta sobre eso. Te recomiendo:\n\n"
            . "- Preguntarle a tu coach desde el Chat Coach\n"
            . "- Revisar la seccion de FAQ\n\n"
            . "Puedo ayudarte con preguntas sobre entrenamiento, nutricion y habitos.";
    }
    $route = 'kb_local';
    $model = 'knowledge_base';
}

// Guardar mensajes en DB
try {
    $insertMsg = $db->prepare("
        INSERT INTO chat_messages
            (client_id, session_id, role, content, route, model, tokens_used, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $insertMsg->execute([$clientId, $sessionId, 'user', $message, null, null, 0]);
    $insertMsg->execute([$clientId, $sessionId, 'assistant', $aiContent, $route, $model, $tokensUsed]);
} catch (\Throwable $ignored) {}

respond([
    'ok'         => true,
    'response'   => $aiContent,
    'session_id' => $sessionId,
    'route'      => $route,
    'model'      => $model,
]);

// ── Helper: Build client context for AI system prompt ─────────
function buildClientContext(PDO $db, int $clientId, string $plan): string {
    $lines = [];

    // Client basic info
    $stmt = $db->prepare("SELECT name, plan, created_at, birth_date FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($client) {
        $lines[] = "Nombre: " . ($client['name'] ?? 'Cliente');
        $lines[] = "Plan: " . strtoupper($client['plan'] ?? $plan);
        $daysSince = (int)((time() - strtotime($client['created_at'])) / 86400);
        $lines[] = "Dias en WellCore: $daysSince";
    }

    // Streak + XP
    try {
        $stmt = $db->prepare("SELECT current_streak, total_xp FROM client_xp WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $xp = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($xp) {
            $lines[] = "Racha actual: " . ($xp['current_streak'] ?? 0) . " dias";
            $lines[] = "XP total: " . ($xp['total_xp'] ?? 0);
        }
    } catch (\Throwable $e) {}

    // Last 3 check-ins
    try {
        $stmt = $db->prepare("
            SELECT bienestar, dias_entrenados, nutricion_seguida, checkin_date
            FROM checkins WHERE client_id = ?
            ORDER BY checkin_date DESC LIMIT 3
        ");
        $stmt->execute([$clientId]);
        $checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($checkins) {
            $lines[] = "Ultimos check-ins:";
            foreach ($checkins as $ci) {
                $lines[] = "  - {$ci['checkin_date']}: bienestar {$ci['bienestar']}/10, {$ci['dias_entrenados']} dias, nutricion: {$ci['nutricion_seguida']}";
            }
        }
    } catch (\Throwable $e) {}

    // Recent biometric
    try {
        $stmt = $db->prepare("
            SELECT weight_kg, body_fat_pct, log_date
            FROM biometric_logs WHERE client_id = ?
            ORDER BY log_date DESC LIMIT 1
        ");
        $stmt->execute([$clientId]);
        $bio = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($bio) {
            $lines[] = "Metricas recientes ({$bio['log_date']}): peso {$bio['weight_kg']}kg" .
                ($bio['body_fat_pct'] ? ", grasa {$bio['body_fat_pct']}%" : '');
        }
    } catch (\Throwable $e) {}

    // Today's habits
    try {
        $stmt = $db->prepare("
            SELECT habit_name, completed FROM daily_habits
            WHERE client_id = ? AND habit_date = CURDATE()
        ");
        $stmt->execute([$clientId]);
        $habits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($habits) {
            $done = count(array_filter($habits, fn($h) => $h['completed']));
            $lines[] = "Habitos hoy: $done/" . count($habits) . " completados";
        }
    } catch (\Throwable $e) {}

    return implode("\n", $lines);
}
