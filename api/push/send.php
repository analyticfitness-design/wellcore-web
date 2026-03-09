<?php
// POST /api/push/send  — Envía notificación push a un cliente (requiere admin)
//
// Body: { "client_id": N, "title": "...", "body": "...", "url": "/cliente.html" }
//
// Implementación VAPID manual (sin composer/minishlink).
// MVP: envía push sin payload cifrado (silent push compatible).

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
requireAdminRole('admin', 'superadmin', 'coach');

$body = getJsonBody();
$db   = getDB();

$clientId = (int)($body['client_id'] ?? 0);
$title    = trim($body['title'] ?? '');
$msgBody  = trim($body['body']  ?? '');
$url      = trim($body['url']   ?? '/cliente.html');

if ($clientId <= 0) respondError('client_id requerido', 422);
if ($title === '')  respondError('title requerido', 422);

// Obtener suscripción activa del cliente
$stmt = $db->prepare("
    SELECT id, endpoint, p256dh_key, auth_key
    FROM push_subscriptions
    WHERE client_id = ? AND is_active = 1
    ORDER BY updated_at DESC
    LIMIT 1
");
$stmt->execute([$clientId]);
$sub = $stmt->fetch();

if (!$sub) {
    respondError('El cliente no tiene suscripcion push activa', 404);
}

// ===== Cargar claves VAPID desde entorno =====
$vapidPublic  = getenv('VAPID_PUBLIC_KEY')  ?: '';
$vapidPrivate = getenv('VAPID_PRIVATE_KEY') ?: '';
$vapidSubject = getenv('VAPID_SUBJECT')     ?: 'mailto:hola@wellcorefitness.com';

if ($vapidPublic === '' || $vapidPrivate === '') {
    respondError('VAPID keys no configuradas en el entorno', 500);
}

// ===== Construir JWT VAPID =====
$endpoint = $sub['endpoint'];

// Extraer origen del endpoint
$parsed = parse_url($endpoint);
if (!$parsed || empty($parsed['host'])) {
    respondError('Endpoint invalido', 422);
}
$audience = $parsed['scheme'] . '://' . $parsed['host'];

$jwtHeader  = wc_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
$jwtClaims  = wc_base64url_encode(json_encode([
    'aud' => $audience,
    'exp' => time() + 43200,
    'sub' => $vapidSubject,
]));
$signingInput = $jwtHeader . '.' . $jwtClaims;

// Reconstruir PEM privada desde DER base64url
$derBytes   = wc_base64url_decode($vapidPrivate);
$privatePem = "-----BEGIN EC PRIVATE KEY-----\n"
            . chunk_split(base64_encode($derBytes), 64, "\n")
            . "-----END EC PRIVATE KEY-----";

$privKey = openssl_pkey_get_private($privatePem);
if (!$privKey) {
    error_log('VAPID key load error: ' . openssl_error_string());
    respondError('Error de configuración VAPID. Contacta al administrador.', 500);
}

// Firmar (SHA256 + EC → DER)
$signatureRaw = '';
if (!openssl_sign($signingInput, $signatureRaw, $privKey, OPENSSL_ALGO_SHA256)) {
    respondError('Error al firmar JWT VAPID', 500);
}

// DER → IEEE P1363 (r||s, 32+32 bytes) para ES256
$signatureB64 = wc_base64url_encode(wc_der_to_p1363($signatureRaw));
$jwt = $signingInput . '.' . $signatureB64;

// ===== Enviar con cURL (silent push — sin payload) =====
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'TTL: 86400',
        'Urgency: normal',
        'Authorization: vapid t=' . $jwt . ',k=' . $vapidPublic,
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => false,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 410 = suscripción expirada permanentemente — desactivar
// 404 = posible error temporal — no desactivar
if ($httpCode === 410) {
    $db->prepare('UPDATE push_subscriptions SET is_active = 0 WHERE id = ?')
       ->execute([$sub['id']]);
    respondError('Suscripción expirada (410)', 410);
} elseif ($httpCode === 404) {
    error_log("Push 404 para suscripción {$sub['id']} — no desactivada");
    respondError('Push endpoint no encontrado temporalmente (404)', 404);
}

if ($curlError) {
    respondError('Error de red al enviar push: ' . $curlError, 502);
}

if ($httpCode < 200 || $httpCode >= 300) {
    respondError('Push service respondio HTTP ' . $httpCode . ': ' . substr((string)$response, 0, 200), 502);
}

respond([
    'success'   => true,
    'http_code' => $httpCode,
    'client_id' => $clientId,
    'endpoint'  => substr($endpoint, 0, 60) . '...',
]);

// ===== Helpers =====

function wc_base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function wc_base64url_decode(string $data): string {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Convierte firma DER ASN.1 (SEQUENCE de dos INTEGERs) a IEEE P1363 (r||s, 32+32 bytes)
 * requerido por ES256 en JWT VAPID.
 */
function wc_der_to_p1363(string $der): string {
    $offset = 2; // saltar 0x30 + length total
    $offset++; // 0x02 (tipo INTEGER para r)
    $rLen = ord($der[$offset++]);
    $r = substr($der, $offset, $rLen);
    $offset += $rLen;
    $offset++; // 0x02 (tipo INTEGER para s)
    $sLen = ord($der[$offset++]);
    $s = substr($der, $offset, $sLen);

    // Quitar padding de cero al inicio, luego rellenar a 32 bytes
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}
