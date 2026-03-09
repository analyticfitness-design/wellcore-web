<?php
/**
 * Diagnóstico y asignación de coach_id a clientes.
 * Ejecutar una vez en producción, luego eliminar.
 * GET ?action=check  → muestra estado actual
 * GET ?action=assign → asigna coach_id a todos los clientes activos sin coach
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: text/plain; charset=utf-8');

$db     = getDB();
$action = $_GET['action'] ?? 'check';

// --- 1. Coaches disponibles ---
$coaches = $db->query(
    "SELECT id, username, name FROM admins WHERE role = 'coach' ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

echo "=== COACHES ===\n";
foreach ($coaches as $c) {
    echo "{$c['id']} | {$c['username']} | {$c['name']}\n";
}
echo "\n";

// --- 2. Estado actual de clientes ---
$clients = $db->query(
    "SELECT id, name, plan, status, coach_id FROM clients ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

echo "=== CLIENTS (id | name | plan | status | coach_id) ===\n";
foreach ($clients as $c) {
    $cid = $c['coach_id'] ?? 'NULL';
    echo "{$c['id']} | {$c['name']} | {$c['plan']} | {$c['status']} | {$cid}\n";
}
echo "\n";

if ($action !== 'assign') {
    echo "Pasa ?action=assign para asignar coach_id.\n";
    exit;
}

// --- 3. Asignar: usar el primer coach disponible para todos los clientes sin coach ---
if (empty($coaches)) {
    echo "ERROR: No hay coaches en la tabla admins.\n";
    exit;
}

$default_coach_id = $coaches[0]['id'];
echo "=== ASIGNANDO coach_id = {$default_coach_id} ({$coaches[0]['username']}) ===\n";

$stmt = $db->prepare(
    "UPDATE clients SET coach_id = ? WHERE coach_id IS NULL OR coach_id = 0"
);
$stmt->execute([$default_coach_id]);
$updated = $stmt->rowCount();

echo "Filas actualizadas: {$updated}\n\n";

// --- 4. Verificar resultado ---
$after = $db->query(
    "SELECT id, name, status, coach_id FROM clients ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

echo "=== RESULTADO FINAL ===\n";
foreach ($after as $c) {
    echo "{$c['id']} | {$c['name']} | {$c['status']} | coach_id={$c['coach_id']}\n";
}
echo "\nLISTO.\n";
