<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Proxy Dify RAG Chat
 * POST /api/ai/chat-dify
 *
 * Flujo: Dify RAG (local) -> fallback /api/ai/chat (Router directo)
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

$difyUrl    = defined('DIFY_URL')     ? DIFY_URL     : 'http://localhost:3000';
$difyApiKey = defined('DIFY_API_KEY') ? DIFY_API_KEY : '';

$clientId = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.user_id FROM auth_tokens t
        WHERE t.token = ? AND t.user_type = 'client' AND t.expires_at > NOW()
    ");
    $stmt->execute([$m[1]]);
    $row = $stmt->fetch();
    if ($row) {
        $clientId = (int) $row['user_id'];
    }
}

if ($difyApiKey) {
    $difyResult = difyChat($difyUrl, $difyApiKey, $message, $sessionId);
    if ($difyResult) {
        saveChatPair($clientId, $sessionId, $message, $difyResult, 'dify_rag', 'wellcore-coach-v2');

        respond([
            'ok'         => true,
            'response'   => $difyResult,
            'session_id' => $sessionId,
            'route'      => 'dify_rag',
            'model'      => 'wellcore-coach-v2',
        ]);
    }
}

require __DIR__ . '/chat.php';

function difyChat(string $url, string $apiKey, string $msg, string $sid): ?string {
    $payload = json_encode([
        'inputs'          => new \stdClass(),
        'query'           => $msg,
        'response_mode'   => 'blocking',
        'conversation_id' => '',
        'user'            => $sid,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url . '/v1/chat-messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || $resp === false) return null;

    $data = json_decode($resp, true);
    return $data['answer'] ?? null;
}

function saveChatPair(?int $cid, string $sid, string $userMsg, string $aiMsg, string $route, string $model): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO chat_messages
                (client_id, session_id, role, content, route, model, tokens_used, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$cid, $sid, 'user', $userMsg, null, null, 0]);
        $stmt->execute([$cid, $sid, 'assistant', $aiMsg, $route, $model, 0]);
    } catch (\Throwable $e) {
        error_log('[WellCore] chat save: ' . $e->getMessage());
    }
}
