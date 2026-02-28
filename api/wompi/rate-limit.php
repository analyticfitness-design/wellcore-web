<?php
/**
 * ============================================================
 * WELLCORE FITNESS — RATE LIMITING BASICO (WOMPI)
 * ============================================================
 */

define('RATE_LIMIT_FILE', __DIR__ . '/data/rate_limits.json');

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

function rate_limit_check(string $ip, string $action, int $limit, int $window): bool {
    $dir = dirname(RATE_LIMIT_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $data = [];
    if (file_exists(RATE_LIMIT_FILE)) {
        $data = json_decode(file_get_contents(RATE_LIMIT_FILE), true) ?? [];
    }

    $now = time();
    $key = md5($ip . '|' . $action);

    if (!isset($data[$key])) {
        $data[$key] = ['count' => 0, 'window_start' => $now];
    }

    if ($now - $data[$key]['window_start'] >= $window) {
        $data[$key] = ['count' => 0, 'window_start' => $now];
    }

    $data[$key]['count']++;

    foreach ($data as $k => $entry) {
        if ($now - $entry['window_start'] > $window * 2) unset($data[$k]);
    }

    file_put_contents(RATE_LIMIT_FILE, json_encode($data), LOCK_EX);

    return $data[$key]['count'] <= $limit;
}
