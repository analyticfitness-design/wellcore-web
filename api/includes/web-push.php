<?php
/**
 * WellCore — Web Push sender (RFC 8291 + RFC 8292 + RFC 8188)
 * Pure PHP, no Composer. Requires PHP 8.1+ (openssl_pkey_derive).
 *
 * Public functions:
 *   webpush_send(endpoint, p256dh, auth, data): bool
 *   webpush_send_to_client(PDO, clientId, title, body, url): int
 */

// ── Base64url helpers ──────────────────────────────────────────────────────

function webpush_b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function webpush_b64url_decode(string $data): string {
    $pad  = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

// ── DER → raw r+s (64 bytes) ───────────────────────────────────────────────

/**
 * Converts an OpenSSL DER-encoded ECDSA signature to raw 64-byte r||s format
 * required by JWT ES256 (RFC 7518 §3.4).
 */
function webpush_der_to_rs(string $der): string {
    // DER: 0x30 <len> 0x02 <rlen> <r> 0x02 <slen> <s>
    $offset = 2; // skip SEQUENCE tag + length
    // r
    $offset += 1; // skip INTEGER tag
    $rLen    = ord($der[$offset++]);
    $r       = substr($der, $offset, $rLen);
    $offset += $rLen;
    // s
    $offset += 1; // skip INTEGER tag
    $sLen    = ord($der[$offset++]);
    $s       = substr($der, $offset, $sLen);

    // DER integers may have a leading 0x00 byte to signal positivity — strip it
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");

    // Pad each component to 32 bytes
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

// ── VAPID JWT (ES256) ──────────────────────────────────────────────────────

/**
 * Creates and signs a VAPID JWT for the given push endpoint.
 * Uses the VAPID_PRIVATE_KEY from env (base64url-encoded DER ECPrivateKey).
 */
function webpush_create_vapid_jwt(string $endpoint): string {
    // Parse audience from endpoint URL
    $parsed   = parse_url($endpoint);
    $audience = $parsed['scheme'] . '://' . $parsed['host'];

    $header  = webpush_b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = webpush_b64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200,   // 12 hours
        'sub' => getenv('VAPID_SUBJECT') ?: 'mailto:hola@wellcorefitness.com',
    ]));

    $sigInput = $header . '.' . $payload;

    // Load private key: VAPID_PRIVATE_KEY is base64url-encoded DER (ECPrivateKey)
    $derB64    = getenv('VAPID_PRIVATE_KEY');
    $derBytes  = webpush_b64url_decode($derB64);
    $pem       = "-----BEGIN EC PRIVATE KEY-----\n"
               . chunk_split(base64_encode($derBytes), 64, "\n")
               . "-----END EC PRIVATE KEY-----\n";

    $privKey = openssl_pkey_get_private($pem);
    if (!$privKey) {
        error_log('[webpush] Failed to load VAPID private key: ' . openssl_error_string());
        return '';
    }

    openssl_sign($sigInput, $derSig, $privKey, OPENSSL_ALGO_SHA256);

    $signature = webpush_b64url_encode(webpush_der_to_rs($derSig));

    return $sigInput . '.' . $signature;
}

// ── Encryption (RFC 8291 / RFC 8188 aes128gcm) ────────────────────────────

/**
 * Encrypts a push payload for a specific subscriber.
 *
 * @param string $payload  JSON string (or any text) to encrypt
 * @param string $p256dh   Base64url-encoded subscriber public key (65 bytes)
 * @param string $auth     Base64url-encoded subscriber auth secret (16 bytes)
 * @return array           [$body] — the complete RFC 8188 encrypted body
 */
function webpush_encrypt(string $payload, string $p256dh, string $auth): array {
    // Decode subscriber key material
    $userPubKeyRaw = webpush_b64url_decode($p256dh);  // 65 bytes, uncompressed point
    $userAuth      = webpush_b64url_decode($auth);     // 16 bytes

    // Import subscriber public key as PEM
    // DER header for P-256 SubjectPublicKeyInfo: OID ecPublicKey + OID prime256v1
    $derHeader      = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    $derPub         = $derHeader . $userPubKeyRaw;
    $subPubKeyPem   = "-----BEGIN PUBLIC KEY-----\n"
                    . chunk_split(base64_encode($derPub), 64, "\n")
                    . "-----END PUBLIC KEY-----\n";
    $subscriberPubKey = openssl_pkey_get_public($subPubKeyPem);
    if (!$subscriberPubKey) {
        error_log('[webpush] Failed to import subscriber public key: ' . openssl_error_string());
        return [''];
    }

    // Generate ephemeral P-256 keypair (server side)
    $ephemeralPrivKey = openssl_pkey_new([
        'curve_name'        => 'prime256v1',
        'private_key_type'  => OPENSSL_KEYTYPE_EC,
    ]);
    if (!$ephemeralPrivKey) {
        error_log('[webpush] Failed to generate ephemeral keypair: ' . openssl_error_string());
        return [''];
    }

    $details = openssl_pkey_get_details($ephemeralPrivKey);
    // Raw uncompressed server public key: 0x04 || x (32 bytes) || y (32 bytes)
    $serverPubKeyRaw = "\x04"
        . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

    // ECDH shared secret (PHP 8.1+)
    $sharedSecret = openssl_pkey_derive($subscriberPubKey, $ephemeralPrivKey);
    if ($sharedSecret === false) {
        error_log('[webpush] ECDH derive failed: ' . openssl_error_string());
        return [''];
    }

    // HKDF (RFC 8291 §3.3)
    // Step 1: PRK from auth secret
    $info = "WebPush: info\x00" . $userPubKeyRaw . $serverPubKeyRaw;
    $prk  = hash_hmac('sha256', $sharedSecret, $userAuth, true);
    $ikm  = substr(hash_hmac('sha256', $info . "\x01", $prk, true), 0, 32);

    // Step 2: derive CEK and nonce from random salt
    $salt = random_bytes(16);
    $prk2  = hash_hmac('sha256', $ikm, $salt, true);
    $cek   = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk2, true), 0, 16);
    $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk2, true), 0, 12);

    // Encrypt payload with AES-128-GCM (RFC 8188 record)
    // Append 0x02 delimiter byte (end-of-record marker)
    $tag        = '';
    $ciphertext = openssl_encrypt(
        $payload . "\x02",
        'aes-128-gcm',
        $cek,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        '',
        16
    );
    if ($ciphertext === false) {
        error_log('[webpush] AES-128-GCM encrypt failed: ' . openssl_error_string());
        return [''];
    }

    // RFC 8188 aes128gcm content-encoding header:
    //   salt (16) || rs (4, big-endian, 4096) || keyid_len (1) || keyid (65) || ciphertext || tag
    $body = $salt
          . pack('N', 4096)          // record size
          . chr(65)                  // length of server public key (keyid)
          . $serverPubKeyRaw         // 65-byte uncompressed point
          . $ciphertext
          . $tag;

    return [$body];
}

// ── Send a single push message ─────────────────────────────────────────────

/**
 * Sends a Web Push message to a single subscription endpoint.
 *
 * @param string $endpoint  Push endpoint URL
 * @param string $p256dh    Subscriber public key (base64url)
 * @param string $auth      Subscriber auth secret (base64url)
 * @param array  $data      Notification data: ['title', 'body', 'url']
 * @return bool             True if accepted (201/202), false otherwise
 */
function webpush_send(string $endpoint, string $p256dh, string $auth, array $data): bool {
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    [$body] = webpush_encrypt($payload, $p256dh, $auth);
    if (empty($body)) {
        error_log("[webpush] Encryption failed for endpoint: $endpoint");
        return false;
    }

    $jwt      = webpush_create_vapid_jwt($endpoint);
    $vapidPub = getenv('VAPID_PUBLIC_KEY');

    $headers = implode("\r\n", [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: 86400',
        "Authorization: vapid t={$jwt},k={$vapidPub}",
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => $headers,
            'content'       => $body,
            'ignore_errors' => true,
            'timeout'       => 10,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $response     = @file_get_contents($endpoint, false, $ctx);
    $responseCode = 0;

    if (isset($http_response_header) && is_array($http_response_header)) {
        // First line: "HTTP/1.1 201 Created"
        if (preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m)) {
            $responseCode = (int)$m[1];
        }
    }

    $ok = in_array($responseCode, [200, 201, 202], true);
    if (!$ok) {
        error_log("[webpush] Push failed — HTTP {$responseCode} for endpoint: " . substr($endpoint, 0, 60) . '...');
    }
    return $ok;
}

// ── Send to all active subscriptions for a client ─────────────────────────

/**
 * Sends a push notification to every active subscription of a client.
 *
 * @param PDO    $db       Database connection
 * @param int    $clientId Target client ID
 * @param string $title    Notification title
 * @param string $body     Notification body text
 * @param string $url      URL to open on click
 * @return int             Number of successful deliveries
 */
function webpush_send_to_client(PDO $db, int $clientId, string $title, string $body, string $url = '/cliente.html'): int {
    $stmt = $db->prepare("
        SELECT endpoint, p256dh, auth
        FROM push_subscriptions
        WHERE client_id = ? AND is_active = 1
    ");
    $stmt->execute([$clientId]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($subscriptions)) {
        return 0;
    }

    $data = ['title' => $title, 'body' => $body, 'url' => $url];
    $sent = 0;

    foreach ($subscriptions as $sub) {
        $ok = webpush_send($sub['endpoint'], $sub['p256dh'], $sub['auth'], $data);
        if ($ok) {
            $sent++;
        } else {
            // Mark expired/gone subscriptions as inactive (410 Gone)
            // Note: webpush_send returns false on any error; caller may choose to deactivate
            // For now just log — the send function already error_logs details
        }
    }

    return $sent;
}
