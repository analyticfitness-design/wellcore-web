<?php
// WellCore — Helper para ejecutar migraciones SQL
// Uso: php database/run_migration.php <archivo.sql>

require_once __DIR__ . '/../api/config/database.php';

$file = $argv[1] ?? null;
if (!$file) {
    echo "Uso: php database/run_migration.php <archivo.sql>\n";
    exit(1);
}

$path = __DIR__ . '/migrations/' . basename($file);
if (!file_exists($path)) {
    echo "Error: archivo no encontrado: $path\n";
    exit(1);
}

$sql = file_get_contents($path);
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($s) { return !empty($s) && strpos(ltrim($s), '--') !== 0; }
);

$db = getDB();
foreach ($statements as $stmt) {
    if (empty(trim($stmt))) continue;
    try {
        $db->query($stmt);
        echo "OK: " . substr($stmt, 0, 70) . "\n";
    } catch (PDOException $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nMigracion '$file' ejecutada exitosamente.\n";
