<?php
/**
 * WellCore — Environment loader
 * Lee variables desde api/.env (si existe) y las carga en $_ENV.
 * No requiere Composer ni phpdotenv.
 */

function loadEnv(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) return;

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        // Remove surrounding quotes
        if (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'")) {
            $val = substr($val, 1, -1);
        }
        $_ENV[$key] = $val;
        putenv("$key=$val");
    }
}

/**
 * Obtiene variable de entorno con fallback.
 */
function env(string $key, string $default = ''): string {
    $val = $_ENV[$key] ?? null;
    if ($val !== null) return $val;
    $val = getenv($key);
    return ($val !== false && $val !== '') ? $val : $default;
}

// Auto-load al incluir este archivo
loadEnv();
