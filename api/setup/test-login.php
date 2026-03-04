<?php
// Test: login y me.php para daniel.esparza
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();

// Buscar admin
$stmt = $db->prepare("SELECT id, username, password_hash, name, role FROM admins WHERE username = ?");
$stmt->execute(['daniel.esparza']);
$user = $stmt->fetch();

if (!$user) { echo "ERROR: Usuario no encontrado\n"; exit; }

$ok = password_verify('RISE2026Admin!SuperPower', $user['password_hash']);
echo "Password verify: " . ($ok ? 'OK' : 'FAIL') . "\n";
echo "Role: " . $user['role'] . "\n";
echo "Name: " . $user['name'] . "\n";
echo "ID: " . $user['id'] . "\n";

// Simular token
$_SERVER['HTTP_USER_AGENT'] = 'TestCLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$token = createToken('admin', $user['id'], true);
echo "Token creado: " . $token . "\n";

// Verificar que me.php lo resuelve bien
$stmt2 = $db->prepare("SELECT t.user_type, t.user_id, a.username, a.role, a.name
    FROM auth_tokens t JOIN admins a ON a.id = t.user_id
    WHERE t.token = ? AND t.user_type = 'admin' AND t.expires_at > NOW()");
$stmt2->execute([$token]);
$result = $stmt2->fetch();

echo "me.php simulation:\n";
echo json_encode(['type' => 'admin', 'admin' => $result], JSON_PRETTY_PRINT) . "\n";
?>
