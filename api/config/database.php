<?php
// WellCore — Database configuration
// Auto-detects local vs production environment

// Detect environment: Docker = production, else = local dev
$isDocker = file_exists('/.dockerenv');

if ($isDocker) {
    // Production (EasyPanel Docker)
    define('DB_HOST',    'wellcore-fitness_mysql-db');
    define('DB_PORT',    '3306');
    define('DB_NAME',    'wellcore_fitness');
    define('DB_USER',    'wellcore');
    define('DB_PASS',    '01e3951218591b77af8e');
    define('APP_ENV',    'production');
    define('UPLOAD_DIR', '/var/www/html/wellcore/uploads/');
} else {
    // Local development (WSL2)
    define('DB_HOST',    '127.0.0.1');
    define('DB_PORT',    '3306');
    define('DB_NAME',    'wellcore_fitness');
    define('DB_USER',    'root');
    define('DB_PASS',    'QY@P6Ak2?');
    define('APP_ENV',    'development');
    define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
}

define('DB_CHARSET', 'utf8mb4');
define('TOKEN_EXPIRY_HOURS', 168);      // 7 days
define('TOKEN_EXPIRY_ADMIN', 8);        // 8 hours for admin
define('UPLOAD_URL', '/uploads/');
define('MAX_PHOTO_SIZE', 10 * 1024 * 1024);  // 10MB

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}
