<?php
// Script temporal — crear/verificar admins en producción
// DELETE after use
header('Content-Type: application/json');
$secret = $_GET['secret'] ?? '';
if ($secret !== 'WC2026setup') { http_response_code(403); die('{"error":"forbidden"}'); }

require_once __DIR__ . '/../config/database.php';

$users = [
    ['daniel.esparza', 'Daniel Esparza', 'superadmin', 'RISE2026Admin!SuperPower'],
    ['adminsilvia',    'Silvia Carvajal', 'admin',     'AdminSilvia2026!'],
    ['coachsilvia',    'Silvia Carvajal', 'coach',     'CoachSilvia2026!'],
];

$results = [];
foreach ($users as [$username, $full_name, $role, $password]) {
    // Check if exists
    $stmt = $pdo->prepare("SELECT id, username, role FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update password & role if exists
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE admins SET password=?, full_name=?, role=? WHERE username=?")
            ->execute([$hash, $full_name, $role, $username]);
        $results[] = ['status' => 'updated', 'username' => $username, 'role' => $role, 'id' => $existing['id']];
    } else {
        // Create new
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO admins (username, password, full_name, role) VALUES (?,?,?,?)")
            ->execute([$username, $hash, $full_name, $role]);
        $id = $pdo->lastInsertId();
        $results[] = ['status' => 'created', 'username' => $username, 'role' => $role, 'id' => $id];
    }
}

echo json_encode(['success' => true, 'users' => $results], JSON_PRETTY_PRINT);
