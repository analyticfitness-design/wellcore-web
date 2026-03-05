<?php
require_once __DIR__ . '/../config/database.php';

function generateToken(): string {
    return bin2hex(random_bytes(32));  // 64 char hex token
}

// Generate a fingerprint from IP + User-Agent for token binding
function getClientFingerprint(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (str_contains($ip, ',')) $ip = trim(explode(',', $ip)[0]);
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $ip . '|' . $ua);
}

function getClientIp(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (str_contains($ip, ',')) $ip = trim(explode(',', $ip)[0]);
    return $ip;
}

function createToken(string $userType, int $userId, bool $isAdmin = false): string {
    $db = getDB();
    $token = generateToken();
    $hours = $isAdmin ? TOKEN_EXPIRY_ADMIN : TOKEN_EXPIRY_HOURS;
    $expires = date('Y-m-d H:i:s', time() + ($hours * 3600));
    $fingerprint = getClientFingerprint();
    $ip = getClientIp();

    $stmt = $db->prepare(
        "INSERT INTO auth_tokens (user_type, user_id, token, fingerprint, ip_address, expires_at) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userType, $userId, $token, $fingerprint, $ip, $expires]);

    // Cleanup: revoke other tokens for same user (single active session for admins)
    if ($isAdmin) {
        $stmt2 = $db->prepare("DELETE FROM auth_tokens WHERE user_type = ? AND user_id = ? AND token != ?");
        $stmt2->execute([$userType, $userId, $token]);
    }

    return $token;
}

function revokeToken(string $token): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM auth_tokens WHERE token = ?");
    $stmt->execute([$token]);
}

function getTokenFromHeader(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

function authenticateClient(): array {
    $token = getTokenFromHeader();
    if (!$token) {
        respondError('Authentication required', 401);
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.user_id, c.id, c.client_code, c.name, c.email, c.plan, c.status
        FROM auth_tokens t
        JOIN clients c ON c.id = t.user_id
        WHERE t.token = ? AND t.user_type = 'client' AND t.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $client = $stmt->fetch();

    if (!$client) {
        respondError('Invalid or expired token', 401);
    }
    if ($client['status'] !== 'activo') {
        respondError('Account is not active', 403);
    }
    return $client;
}

function authenticateAdmin(): array {
    $token = getTokenFromHeader();
    if (!$token) {
        respondError('Authentication required', 401);
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.user_id, t.fingerprint, a.id, a.username, a.name, a.role
        FROM auth_tokens t
        JOIN admins a ON a.id = t.user_id
        WHERE t.token = ? AND t.user_type = 'admin' AND t.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $admin = $stmt->fetch();

    if (!$admin) {
        respondError('Invalid or expired token', 401);
    }

    // Validate fingerprint for admin tokens (token binding)
    // TODO: Fingerprint validation disabled temporarily due to browser navigation issues
    // When re-enabled, will need to handle browser context changes more gracefully
    // if ($admin['fingerprint'] && $admin['fingerprint'] !== getClientFingerprint()) {
    //     // Token stolen or used from different client — revoke it
    //     revokeToken($token);
    //     respondError('Session invalidated: client mismatch', 401);
    // }

    return $admin;
}

function authenticateCoach(): array {
    $admin = authenticateAdmin();
    if ($admin['role'] !== 'coach') {
        respondError('Solo coaches pueden acceder a este recurso', 403);
    }
    return $admin;
}

// Require admin with specific role(s)
function requireAdminRole(string ...$roles): array {
    $admin = authenticateAdmin();
    if (!empty($roles) && !in_array($admin['role'], $roles, true)) {
        respondError('Insufficient permissions', 403);
    }
    return $admin;
}

// Guard for setup/migration endpoints: require admin auth OR CLI execution
function requireSetupAuth(): void {
    // Always allow CLI execution (cron jobs, manual migrations)
    if (php_sapi_name() === 'cli') return;

    // For HTTP requests: require admin token with 'admin' or 'jefe' or 'superadmin' role
    $token = getTokenFromHeader();
    if ($token) {
        try {
            $admin = authenticateAdmin();
            if (in_array($admin['role'], ['admin', 'jefe', 'superadmin'], true)) return;
        } catch (\Exception $e) {
            // fall through to error
        }
    }

    // GET secret for migration endpoints — DISABLED (migrations complete)
    // To re-enable temporarily: uncomment the block below, push, run migration, re-comment
    // $secret = $_GET['secret'] ?? '';
    // if ($secret && in_array($secret, ['WC_MIGRATE_V2_2026', 'WC_MIGRATE_V3_2026', 'WELLCORE_SETUP_2026', 'WC_AI_SETUP_2026', 'WC_AI_V3_2026', 'WC_WEBHOOK_2026', 'WC_COACH_MIGRATE_2026', 'WC_SHOP_SEED_V3_2026', 'WC_RISE_TICKETS_2026'], true)) {
    //     return;
    // }

    http_response_code(401);
    echo json_encode(['error' => 'Admin authentication required for setup endpoints']);
    exit;
}

// Optional: require specific plan level
function requirePlan(array $client, string $minPlan): void {
    $levels = ['esencial' => 1, 'metodo' => 2, 'elite' => 3];
    if (($levels[$client['plan']] ?? 0) < ($levels[$minPlan] ?? 99)) {
        respondError("This feature requires plan: $minPlan", 403, ['required_plan' => $minPlan]);
    }
}
