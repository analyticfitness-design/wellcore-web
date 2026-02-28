<?php
/**
 * Router for PHP built-in server (local dev only).
 * Mimics Apache/nginx rewrite: /api/foo → /api/foo.php
 * Usage: php -S 0.0.0.0:8082 -t . router.php
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// API routes: add .php extension if missing
if (preg_match('#^/api/(.+)$#', $uri, $m) && !str_ends_with($uri, '.php')) {
    $phpFile = __DIR__ . '/api/' . $m[1] . '.php';
    if (file_exists($phpFile)) {
        $_SERVER['SCRIPT_NAME'] = '/api/' . $m[1] . '.php';
        require $phpFile;
        return true;
    }
    // Try index.php in directory
    $indexFile = __DIR__ . '/api/' . $m[1] . '/index.php';
    if (file_exists($indexFile)) {
        require $indexFile;
        return true;
    }
}

// Default: serve index.html for root
if ($uri === '/') {
    require __DIR__ . '/index.html';
    return true;
}

// 404
http_response_code(404);
echo '404 Not Found';
return true;
