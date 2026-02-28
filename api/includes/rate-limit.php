<?php
/**
 * WellCore Fitness — Rate Limiter (MySQL)
 * ============================================================
 * Reemplaza el rate limiter basado en archivos JSON.
 * Usa tabla `rate_limits` en MySQL para evitar race conditions.
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Obtiene la IP real del cliente, considerando proxies.
 */
function get_client_ip(): string {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Verifica rate limit por IP + accion.
 * Incrementa el contador atomicamente en MySQL.
 *
 * @param  string $action  Identificador (ej: 'login', 'create_order')
 * @param  int    $limit   Maximo de peticiones permitidas
 * @param  int    $window  Ventana de tiempo en segundos
 * @param  string|null $ip IP override (null = auto-detect)
 * @return bool   true si puede continuar, false si bloqueado
 */
function rate_limit_check(string $action, int $limit, int $window, ?string $ip = null): bool {
    $ip    = $ip ?? get_client_ip();
    $key   = hash('sha256', $ip . '|' . $action);
    $db    = getDB();

    try {
        // Upsert: insertar o actualizar atomicamente
        $stmt = $db->prepare("
            INSERT INTO rate_limits (ip_hash, action, hit_count, window_start)
            VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                hit_count = IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) >= ?, 1, hit_count + 1),
                window_start = IF(TIMESTAMPDIFF(SECOND, window_start, NOW()) >= ?, NOW(), window_start)
        ");
        $stmt->execute([$key, $action, $window, $window]);

        // Leer contador actual
        $check = $db->prepare("SELECT hit_count FROM rate_limits WHERE ip_hash = ? AND action = ?");
        $check->execute([$key, $action]);
        $count = (int) $check->fetchColumn();

        return $count <= $limit;
    } catch (\Throwable $e) {
        // Si la tabla no existe, permitir (fail-open para no bloquear en migracion)
        error_log('[WellCore] rate_limit_check error: ' . $e->getMessage());
        return true;
    }
}

/**
 * Limpia el rate limit para una IP + accion (ej: login exitoso).
 */
function rate_limit_clear(string $action, ?string $ip = null): void {
    $ip  = $ip ?? get_client_ip();
    $key = hash('sha256', $ip . '|' . $action);

    try {
        getDB()->prepare("DELETE FROM rate_limits WHERE ip_hash = ? AND action = ?")
               ->execute([$key, $action]);
    } catch (\Throwable $e) {
        // Silently ignore if table doesn't exist yet
    }
}

/**
 * Limpia entradas expiradas (llamar desde cron o n8n).
 * Borra registros cuya ventana haya expirado hace mas de 2x.
 */
function rate_limit_cleanup(int $maxAge = 7200): int {
    try {
        $stmt = getDB()->prepare("DELETE FROM rate_limits WHERE TIMESTAMPDIFF(SECOND, window_start, NOW()) > ?");
        $stmt->execute([$maxAge]);
        return $stmt->rowCount();
    } catch (\Throwable $e) {
        return 0;
    }
}
