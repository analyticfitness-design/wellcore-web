<?php
// CLI ONLY — Crear cliente elite en produccion
// Uso: php /code/api/setup/cli-create-elite.php
// DELETE after use
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode(['error' => 'CLI only']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = getDB();

$email    = 'elite@wellcore.com';
$password = 'elite2026';
$hash     = password_hash($password, PASSWORD_BCRYPT);

$ex = $db->prepare("SELECT id FROM clients WHERE email = ?");
$ex->execute([$email]);
$row = $ex->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $db->prepare("UPDATE clients SET plan='elite', status='activo', password_hash=? WHERE email=?")
       ->execute([$hash, $email]);
    echo "UPDATED  id={$row['id']} | email=$email | pass=$password | plan=elite\n";
} else {
    $db->prepare("INSERT INTO clients (client_code, email, name, plan, status, password_hash, created_at)
                  VALUES ('WC-ELITE-001', ?, 'Cliente Elite Test', 'elite', 'activo', ?, NOW())")
       ->execute([$email, $hash]);
    $id = $db->lastInsertId();
    echo "CREATED  id=$id | email=$email | pass=$password | plan=elite\n";
}

echo "Listo. Ahora puedes hacer login en /login.html con:\n";
echo "  Email: $email\n";
echo "  Pass:  $password\n";
echo "DELETE este archivo: api/setup/cli-create-elite.php\n";
