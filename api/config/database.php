<?php
// WellCore — Database configuration
// Auto-detects local vs production environment
// Credenciales SIEMPRE desde variables de entorno, nunca hardcodeadas

// Cargar .env para desarrollo local (ignorado por git)
$_envFile = __DIR__ . '/../.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#') continue;
        [$_k, $_v] = array_pad(explode('=', $_line, 2), 2, '');
        putenv(trim($_k) . '=' . trim($_v));
        $_ENV[trim($_k)] = trim($_v);
    }
    unset($_envFile, $_line, $_k, $_v);
}

// Detect environment: Docker = production, else = local dev
$isDocker = file_exists('/.dockerenv');

if ($isDocker) {
    // Production (EasyPanel Docker)
    // Credenciales desde variables de entorno del container (configurar en EasyPanel > Environment)
    define('DB_HOST',    getenv('DB_HOST') ?: 'wellcorefitness_wellcorefitness-mysql');
    define('DB_PORT',    getenv('DB_PORT') ?: '3306');
    define('DB_NAME',    getenv('DB_NAME') ?: 'wellcorefitness');
    define('DB_USER',    getenv('DB_USER') ?: 'wellcorefitness');
    define('DB_PASS',    getenv('DB_PASS') ?: '');  // SIEMPRE setear en EasyPanel env vars
    define('APP_ENV',    'production');
    define('UPLOAD_DIR', '/code/uploads/');
} else {
    // Local development (WSL2)
    define('DB_HOST',    getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_PORT',    '3306');
    define('DB_NAME',    getenv('DB_NAME') ?: 'wellcore_fitness');
    define('DB_USER',    getenv('DB_USER') ?: 'root');
    define('DB_PASS',    getenv('DB_PASS') ?: '');  // setear en api/.env local
    define('APP_ENV',    'development');
    define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
}

define('DB_CHARSET', 'utf8mb4');
define('TOKEN_EXPIRY_HOURS', 168);      // 7 days for clients
define('TOKEN_EXPIRY_ADMIN', 72);       // 3 days for admin (was 8h — too short)
define('TOKEN_EXPIRY_REMEMBER', 720);   // 30 days when "remember me" is checked
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
