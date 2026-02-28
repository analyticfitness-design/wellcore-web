<?php
/**
 * WellCore Fitness — API Request Logger
 * ============================================================
 * Registra peticiones a endpoints criticos en tabla api_logs.
 * Uso: al inicio del endpoint, llamar logStart().
 *      Al final (o via shutdown), se registra automaticamente.
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Inicia el logging de la peticion actual.
 * Registra endpoint, method, IP y marca el tiempo de inicio.
 * El log se escribe a DB al finalizar el script via register_shutdown_function.
 */
function logStart(?int $userId = null, ?string $userType = null): void {
    $GLOBALS['__wc_log'] = [
        'endpoint'    => $_SERVER['REQUEST_URI'] ?? '',
        'method'      => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'ip'          => $_SERVER['HTTP_CF_CONNECTING_IP']
                         ?? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]
                         ?? $_SERVER['REMOTE_ADDR']
                         ?? '0.0.0.0',
        'user_id'     => $userId,
        'user_type'   => $userType,
        'start_time'  => hrtime(true),
        'status_code' => 200,
    ];

    register_shutdown_function('_wc_log_flush');
}

/**
 * Actualiza el user asociado al log (util despues de autenticar).
 */
function logSetUser(int $userId, string $userType): void {
    if (isset($GLOBALS['__wc_log'])) {
        $GLOBALS['__wc_log']['user_id']   = $userId;
        $GLOBALS['__wc_log']['user_type'] = $userType;
    }
}

/**
 * Shutdown function — escribe el log a la DB.
 * @internal
 */
function _wc_log_flush(): void {
    $log = $GLOBALS['__wc_log'] ?? null;
    if (!$log) return;

    $statusCode = http_response_code() ?: $log['status_code'];
    $durationMs = (int) ((hrtime(true) - $log['start_time']) / 1_000_000);

    // Truncar endpoint a 255 chars
    $endpoint = substr(strtok($log['endpoint'], '?'), 0, 255);

    try {
        getDB()->prepare("
            INSERT INTO api_logs (endpoint, method, ip, user_id, user_type, status_code, duration_ms, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $endpoint,
            $log['method'],
            trim($log['ip']),
            $log['user_id'],
            $log['user_type'],
            $statusCode,
            $durationMs,
        ]);
    } catch (\Throwable $e) {
        // No bloquear la respuesta si falla el logging
        error_log('[WellCore] api_log write error: ' . $e->getMessage());
    }

    unset($GLOBALS['__wc_log']);
}
