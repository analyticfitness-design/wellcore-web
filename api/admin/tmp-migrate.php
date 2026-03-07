<?php
/**
 * TEMPORAL — Run AI consolidation migration
 * DELETE after running: GET /api/admin/tmp-migrate.php
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
$db = getDB();
$results = [];

$queries = [
    '1_plan_type_enum' => "ALTER TABLE assigned_plans MODIFY COLUMN plan_type ENUM('entrenamiento','nutricion','habitos','rise') NOT NULL",
    '2_ai_gen_type' => "ALTER TABLE ai_generations MODIFY COLUMN type VARCHAR(30) NOT NULL DEFAULT 'entrenamiento'",
    '3_ai_gen_status' => "ALTER TABLE ai_generations MODIFY COLUMN status ENUM('queued','pending','generating','completed','failed','approved','rejected') DEFAULT 'pending'",
    '4_clients_plan' => "ALTER TABLE clients MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise') DEFAULT 'esencial'",
];

foreach ($queries as $name => $sql) {
    try {
        $db->exec($sql);
        $results[$name] = 'OK';
    } catch (\Throwable $e) {
        $results[$name] = 'ERROR: ' . $e->getMessage();
    }
}

// Check ai_generation_id column
try {
    $col = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assigned_plans' AND COLUMN_NAME = 'ai_generation_id'")->fetchColumn();
    if ((int)$col === 0) {
        $db->exec("ALTER TABLE assigned_plans ADD COLUMN ai_generation_id INT DEFAULT NULL");
        $results['5_ai_gen_id_col'] = 'ADDED';
    } else {
        $results['5_ai_gen_id_col'] = 'EXISTS';
    }
} catch (\Throwable $e) {
    $results['5_ai_gen_id_col'] = 'ERROR: ' . $e->getMessage();
}

echo json_encode(['migration' => 'ai-consolidation', 'results' => $results], JSON_PRETTY_PRINT);
