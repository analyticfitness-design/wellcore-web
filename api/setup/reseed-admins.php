<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db = getDB();
$results = [];

// --- Single Jefe Admin ---
$admins = [
    ['CoachDann', 'KingLord6962', 'Coach Dann', 'jefe'],
];

foreach ($admins as [$username, $password, $name, $role]) {
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare("
        INSERT INTO admins (username, password_hash, name, role)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name), role = VALUES(role)
    ");
    $stmt->execute([$username, $hash, $name, $role]);
    $results[] = "Admin '$username' ($role) OK";
}

echo json_encode(['ok' => true, 'results' => $results], JSON_PRETTY_PRINT);
