<?php
// ONE-TIME: Crear cliente elite de prueba en produccion
// Acceder con admin token o desde CLI
// DELETE this file after running
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db = getDB();
$email    = 'elite@wellcore.com';
$password = 'elite2026';
$hash     = password_hash($password, PASSWORD_BCRYPT);

$exists = $db->prepare("SELECT id, plan, status FROM clients WHERE email = ?");
$exists->execute([$email]);
$row = $exists->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $db->prepare("UPDATE clients SET plan='elite', status='activo', password_hash=? WHERE email=?")
       ->execute([$hash, $email]);
    $msg = 'updated';
    $clientId = $row['id'];
} else {
    $db->prepare("INSERT INTO clients (client_code, email, name, plan, status, password_hash, created_at)
                  VALUES ('WC-ELITE-001', ?, 'Cliente Elite Test', 'elite', 'activo', ?, NOW())")
       ->execute([$email, $hash]);
    $clientId = (int)$db->lastInsertId();
    $msg = 'created';
}

header('Content-Type: application/json');
echo json_encode([
    'ok'       => true,
    'action'   => $msg,
    'id'       => $clientId,
    'email'    => $email,
    'password' => $password,
    'plan'     => 'elite',
    'note'     => 'DELETE this file after use: api/setup/create-elite-prod.php',
], JSON_PRETTY_PRINT);
