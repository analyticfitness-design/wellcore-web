<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

try {
    $db = getDB();

    $sql = "CREATE TABLE IF NOT EXISTS invitations ("
         . "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,"
         . "code VARCHAR(32) UNIQUE NOT NULL,"
         . "plan ENUM('esencial','metodo','elite') NOT NULL,"
         . "email_hint VARCHAR(255),"
         . "note VARCHAR(500),"
         . "status ENUM('pending','used','expired') DEFAULT 'pending',"
         . "created_by INT UNSIGNED,"
         . "used_by INT UNSIGNED,"
         . "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,"
         . "expires_at TIMESTAMP NULL,"
         . "used_at TIMESTAMP NULL,"
         . "INDEX idx_code (code),"
         . "INDEX idx_status (status)"
         . ")";

    $db->exec($sql);

    $stmt = $db->query("SHOW TABLES LIKE 'invitations'");
    $exists = $stmt->fetch() ? true : false;

    $tables = [];
    $stmt2 = $db->query("SHOW TABLES");
    while ($row = $stmt2->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    echo json_encode([
        'ok' => true,
        'invitations_exists' => $exists,
        'all_tables' => $tables
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
