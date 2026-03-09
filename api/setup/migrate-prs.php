<?php
/**
 * WellCore Fitness — Migración: Tabla personal_records
 * =====================================================
 * Crea la tabla para almacenar los récords personales de cada cliente.
 * Requiere auth de admin para ejecutar via HTTP.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();
$db = getDB();

$results = [];

$sql = "
    CREATE TABLE IF NOT EXISTS personal_records (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id   INT UNSIGNED NOT NULL,
        exercise_id VARCHAR(10)  NOT NULL,
        value       DECIMAL(8,2) NOT NULL,
        recorded_at DATE         NOT NULL,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_client_exercise (client_id, exercise_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

try {
    $db->query($sql);
    $results[] = ['ok' => true, 'label' => 'CREATE TABLE personal_records'];
} catch (\PDOException $e) {
    $results[] = ['ok' => false, 'label' => 'CREATE TABLE personal_records', 'error' => $e->getMessage()];
}

respond(['results' => $results]);
