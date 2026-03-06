<?php
// CORS configuration
$allowed_origins = [
    'https://wellcorefitness.com',
    'https://www.wellcorefitness.com',
    'http://172.17.216.45:1420',
    'http://localhost:1420',
];

// Dev-only origins (never allowed in production)
if (defined('APP_ENV') && APP_ENV === 'development') {
    $allowed_origins[] = 'http://172.17.216.45:8080';
    $allowed_origins[] = 'http://127.0.0.1:8080';
    $allowed_origins[] = 'http://localhost:8080';
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: https://wellcorefitness.com");
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Security headers (2026 best practices)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
// APIs JSON no sirven HTML — bloquear todo content rendering
header("Content-Security-Policy: default-src 'none'");
// Proteccion contra cross-origin attacks
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-site');
header('Cross-Origin-Embedder-Policy: require-corp');
if (!defined('APP_ENV') || APP_ENV !== 'development') {
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
