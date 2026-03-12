<?php
// Temporary script — delete after use
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$db = getDB();
$results = [];

// Show admins
$results['admins'] = $db->query("SELECT id, username, role FROM admins")->fetchAll(PDO::FETCH_ASSOC);

// Reset daniel.esparza password (column is password_hash, not password)
$hash = password_hash("RISE2026Admin!SuperPower", PASSWORD_BCRYPT);
$db->prepare("UPDATE admins SET password_hash = ? WHERE username = ?")->execute([$hash, 'daniel.esparza']);
$results['password_reset'] = 'daniel.esparza OK';

// Run migration 020 — add presencial to ENUMs
try {
    $db->exec("ALTER TABLE clients MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise','presencial') DEFAULT 'esencial'");
    $results['migration_clients'] = 'OK';
} catch (Exception $e) {
    $results['migration_clients'] = $e->getMessage();
}

try {
    $db->exec("ALTER TABLE invitations MODIFY COLUMN plan ENUM('esencial','metodo','elite','presencial') NOT NULL");
    $results['migration_invitations'] = 'OK';
} catch (Exception $e) {
    $results['migration_invitations'] = $e->getMessage();
}

// Check ENUM after migration
$results['clients_plan_enum'] = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='plan'")->fetchColumn();
$results['invitations_plan_enum'] = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME='invitations' AND COLUMN_NAME='plan'")->fetchColumn();

echo json_encode($results, JSON_PRETTY_PRINT);
