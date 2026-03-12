<?php
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$secret = $_GET['key'] ?? '';
if ($secret !== 'wc_fix_enum_2026') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = getDB();
$results = [];

// Check current ENUM for clients.plan
$stmt = $db->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'plan'");
$stmt->execute();
$clientsPlan = $stmt->fetchColumn();
$results['clients_plan_before'] = $clientsPlan;

// Check current ENUM for invitations.plan
$stmt = $db->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invitations' AND COLUMN_NAME = 'plan'");
$stmt->execute();
$invPlan = $stmt->fetchColumn();
$results['invitations_plan_before'] = $invPlan;

// Fix clients.plan if needed
if (strpos($clientsPlan, 'presencial') === false) {
    $db->exec("ALTER TABLE clients MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise','presencial') DEFAULT 'esencial'");
    $results['clients_fixed'] = true;
} else {
    $results['clients_fixed'] = false;
}

// Fix invitations.plan if needed
if (strpos($invPlan, 'presencial') === false) {
    $db->exec("ALTER TABLE invitations MODIFY COLUMN plan ENUM('esencial','metodo','elite','presencial') NOT NULL");
    $results['invitations_fixed'] = true;
} else {
    $results['invitations_fixed'] = false;
}

// Verify after fix
$stmt = $db->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'plan'");
$stmt->execute();
$results['clients_plan_after'] = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invitations' AND COLUMN_NAME = 'plan'");
$stmt->execute();
$results['invitations_plan_after'] = $stmt->fetchColumn();

echo json_encode(['ok' => true, 'results' => $results], JSON_PRETTY_PRINT);
