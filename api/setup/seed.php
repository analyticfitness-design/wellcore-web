<?php
// Run once: php setup/seed.php
// Creates admin accounts with bcrypt hashed passwords

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$admins = [
    ['coach', 'WellCore2026!', 'Coach WellCore', 'coach'],
    ['admin', 'admin123',      'Administrador',  'admin'],
];

$db = getDB();

foreach ($admins as [$username, $password, $name, $role]) {
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare("
        INSERT INTO admins (username, password_hash, name, role)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name)
    ");
    $stmt->execute([$username, $hash, $name, $role]);
    echo "Admin '$username' creado/actualizado\n";
}

// Seed demo clients
$clients = [
    ['cli-001', 'Carlos Mendoza', 'carlos@wellcore.com', 'wc2026', 'elite',    'activo'],
    ['cli-002', 'Sofia Reyes',    'sofia@wellcore.com',  'wc2026', 'metodo',   'activo'],
    ['cli-003', 'Andres Torres',  'andres@wellcore.com', 'wc2026', 'esencial', 'activo'],
];

foreach ($clients as [$code, $name, $email, $pass, $plan, $status]) {
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare("
        INSERT INTO clients (client_code, name, email, password_hash, plan, status, fecha_inicio)
        VALUES (?, ?, ?, ?, ?, ?, CURDATE())
        ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), plan = VALUES(plan), status = VALUES(status)
    ");
    $stmt->execute([$code, $name, $email, $hash, $plan, $status]);

    // Resolve client ID (handles both INSERT and UPDATE paths)
    $id = $db->lastInsertId();
    if (!$id) {
        $id = $db->query("SELECT id FROM clients WHERE email = " . $db->quote($email))->fetchColumn();
    }

    $stmt2 = $db->prepare("
        INSERT IGNORE INTO client_profiles (client_id, objetivo, nivel, lugar_entreno, dias_disponibles)
        VALUES (?, 'Composicion Corporal', 'intermedio', 'gym', '[0,2,4]')
    ");
    $stmt2->execute([$id]);
    echo "Cliente '$name' ($plan) creado\n";
}

echo "\nSetup completado.\n";
