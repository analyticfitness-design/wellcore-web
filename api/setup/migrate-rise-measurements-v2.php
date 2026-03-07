<?php
/**
 * Migración: Agrega muscle_pct y fat_pct a rise_measurements
 * CLI: php api/setup/migrate-rise-measurements-v2.php
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();
$db = getDB();

$results = [];

function runDDL(PDO $db, string $label, string $sql): void {
    global $results;
    try {
        $db->exec($sql);
        $results[] = ['ok' => true, 'label' => $label];
    } catch (\PDOException $e) {
        $results[] = ['ok' => false, 'label' => $label, 'error' => $e->getMessage()];
    }
}

$cols = $db->query("SHOW COLUMNS FROM rise_measurements")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('muscle_pct', $cols)) {
    runDDL($db, "ADD muscle_pct to rise_measurements",
        "ALTER TABLE rise_measurements ADD COLUMN muscle_pct DECIMAL(5,2) DEFAULT NULL AFTER arm_cm"
    );
}

if (!in_array('fat_pct', $cols)) {
    runDDL($db, "ADD fat_pct to rise_measurements",
        "ALTER TABLE rise_measurements ADD COLUMN fat_pct DECIMAL(5,2) DEFAULT NULL AFTER muscle_pct"
    );
}

if (empty($results)) {
    $results[] = ['ok' => true, 'label' => 'Columns already exist — nothing to do'];
}

echo "<h2>RISE Measurements V2 Migration</h2><ul>";
foreach ($results as $r) {
    $icon = $r['ok'] ? '&#9989;' : '&#10060;';
    echo "<li>$icon {$r['label']}";
    if (!empty($r['error'])) echo " — <code>{$r['error']}</code>";
    echo "</li>";
}
echo "</ul>";
