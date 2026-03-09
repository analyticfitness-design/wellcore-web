<?php
/**
 * WellCore Fitness — Migración: Tabla photo_reviews
 * ============================================================
 * Crea la tabla para almacenar reviews de fotos de progreso generados por IA.
 * Requiere auth de admin para ejecutar via HTTP.
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();
$db = getDB();

$results = [];

$createSql = "
    CREATE TABLE IF NOT EXISTS photo_reviews (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_id    INT UNSIGNED NOT NULL,
        photo_date   DATE NOT NULL,
        review_text  TEXT NOT NULL,
        tokens_used  INT UNSIGNED DEFAULT 0,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client (client_id),
        INDEX idx_client_date (client_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

try {
    $db->query($createSql);
    $results[] = ['ok' => true, 'label' => 'CREATE TABLE photo_reviews'];
} catch (\PDOException $e) {
    $results[] = ['ok' => false, 'label' => 'CREATE TABLE photo_reviews', 'error' => $e->getMessage()];
}

respond(['results' => $results]);
