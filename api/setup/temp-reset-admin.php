<?php
// Temporary script — delete after use
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$db = getDB();

// Show admins
$admins = $db->query("SELECT id, username, role FROM admins")->fetchAll(PDO::FETCH_ASSOC);

// Reset daniel.esparza password
$hash = password_hash("RISE2026Admin!SuperPower", PASSWORD_BCRYPT);
$db->prepare("UPDATE admins SET password = ? WHERE username = ?")->execute([$hash, 'daniel.esparza']);

// Check ENUM
$enum = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='plan'")->fetchColumn();

echo json_encode([
    'admins' => $admins,
    'password_reset' => 'daniel.esparza',
    'clients_plan_enum' => $enum,
]);
