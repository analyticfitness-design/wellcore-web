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
require_once __DIR__ . '/../includes/ai-client.php';

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

// ── Construir prompt con contexto RAG ────────────────────────

$systemPrompt = "Eres el asistente virtual de WellCore Fitness, plataforma de coaching online "
    . "especializada en entrenamiento de fuerza e hipertrofia basado en ciencia.\n\n"
    . "REGLAS:\n"
    . "- Responde SIEMPRE en espanol\n"
    . "- Se conciso (maximo 3 parrafos)\n"
    . "- Basa respuestas en ciencia del ejercicio\n"
    . "- Si no sabes algo, di 'Te recomiendo consultar con tu coach'\n"
    . "- NO des consejos medicos\n"
    . "- Menciona RPE, RIR, periodizacion cuando sea relevante\n"
    . "- Si preguntan por precios: Esencial $399.000 COP, Metodo $504.000 COP, Elite $630.000 COP\n";

if ($plan !== 'visitor' && $clientId) {
    $systemPrompt .= "\nEl cliente tiene plan " . strtoupper($plan) . ".\n";
}

$fullPrompt = $context . "PREGUNTA DEL USUARIO:\n" . $message;

// ── Llamar al Router IA ──────────────────────────────────────

$ai = new WellCoreAI();

try {
    $result = $ai->chat($fullPrompt, $systemPrompt);

    $aiContent  = $result['content'] ?? '';
    $route      = $result['route'] ?? 'unknown';
    $model      = $result['model'] ?? 'unknown';
    $tokensUsed = $result['tokens_used'] ?? 0;

    // Guardar mensajes en DB
    $insertMsg = $db->prepare("
        INSERT INTO chat_messages
            (client_id, session_id, role, content, route, model, tokens_used, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $insertMsg->execute([$clientId, $sessionId, 'user', $message, null, null, 0]);
    $insertMsg->execute([$clientId, $sessionId, 'assistant', $aiContent, $route, $model, $tokensUsed]);

    respond([
        'ok'         => true,
        'response'   => $aiContent,
        'session_id' => $sessionId,
        'route'      => $route,
        'model'      => $model,
    ]);

} catch (\Throwable $e) {
    error_log('[WellCore AI] chat error: ' . $e->getMessage());
    respondError('Error del chatbot. Intenta de nuevo.', 500);
}
