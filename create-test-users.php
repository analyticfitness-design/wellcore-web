<?php
/**
 * Create test users for all roles in WellCore
 * Run: php create-test-users.php
 */

require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/includes/auth.php';

$db = getDB();

// Test data - Real users for platform testing
$testUsers = [
    // Admin roles
    ['type' => 'admin', 'username' => 'dannyrios', 'password' => 'DannyRios2026!', 'role' => 'admin', 'name' => 'Danny Ríos'],
    ['type' => 'admin', 'username' => 'operaciones', 'password' => 'Operaciones2026!', 'role' => 'jefe', 'name' => 'Jefe de Operaciones'],
    ['type' => 'admin', 'username' => 'admin_master', 'password' => 'AdminMaster2026!', 'role' => 'superadmin', 'name' => 'Super Administrador'],
    ['type' => 'admin', 'username' => 'coach_principal', 'password' => 'CoachPrincipal2026!', 'role' => 'coach', 'name' => 'Coach Principal'],

    // Client roles (plans) - Real users for testing each plan
    ['type' => 'client', 'code' => 'cli-001-esencial', 'email' => 'juan.perez@email.com', 'password' => 'JuanPerez2026!', 'name' => 'Juan Pérez', 'plan' => 'esencial'],
    ['type' => 'client', 'code' => 'cli-002-metodo', 'email' => 'maria.garcia@email.com', 'password' => 'MariaGarcia2026!', 'name' => 'María García', 'plan' => 'metodo'],
    ['type' => 'client', 'code' => 'cli-003-elite', 'email' => 'carlos.rodriguez@email.com', 'password' => 'CarlosRodriguez2026!', 'name' => 'Carlos Rodríguez', 'plan' => 'elite'],
];

echo "Creating test users for WellCore...\n";
echo str_repeat("=", 60) . "\n";

// Admin users
echo "\n📋 ADMIN USERS\n";
echo str_repeat("-", 60) . "\n";

foreach ($testUsers as $user) {
    if ($user['type'] === 'admin') {
        try {
            // Check if exists
            $stmt = $db->prepare("SELECT id FROM admins WHERE username = ?");
            $stmt->execute([$user['username']]);
            $exists = $stmt->fetch();

            if ($exists) {
                // Update
                $hashedPwd = password_hash($user['password'], PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE admins SET password_hash = ? WHERE username = ?");
                $stmt->execute([$hashedPwd, $user['username']]);
                echo "✓ Updated: {$user['username']}\n";
            } else {
                // Create
                $hashedPwd = password_hash($user['password'], PASSWORD_BCRYPT);
                $stmt = $db->prepare(
                    "INSERT INTO admins (username, password_hash, role, name) VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$user['username'], $hashedPwd, $user['role'], $user['name']]);
                echo "✓ Created: {$user['username']}\n";
            }

            echo "  └─ Credentials: {$user['username']} / {$user['password']}\n";
            echo "  └─ Role: {$user['role']}\n";
            echo "  └─ Name: {$user['name']}\n\n";
        } catch (Exception $e) {
            echo "✗ Error with {$user['username']}: " . $e->getMessage() . "\n";
        }
    }
}

// Client users
echo "\n👥 CLIENT USERS\n";
echo str_repeat("-", 60) . "\n";

foreach ($testUsers as $user) {
    if ($user['type'] === 'client') {
        try {
            // Check if exists
            $stmt = $db->prepare("SELECT id FROM clients WHERE email = ?");
            $stmt->execute([$user['email']]);
            $exists = $stmt->fetch();

            if ($exists) {
                // Update
                $hashedPwd = password_hash($user['password'], PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE clients SET password_hash = ? WHERE email = ?");
                $stmt->execute([$hashedPwd, $user['email']]);
                echo "✓ Updated: {$user['email']}\n";
            } else {
                // Create
                $hashedPwd = password_hash($user['password'], PASSWORD_BCRYPT);
                $stmt = $db->prepare(
                    "INSERT INTO clients (client_code, name, email, password_hash, plan, status, fecha_inicio, created_at)
                     VALUES (?, ?, ?, ?, ?, 'activo', CURDATE(), NOW())"
                );
                $stmt->execute([$user['code'], $user['name'], $user['email'], $hashedPwd, $user['plan']]);
                echo "✓ Created: {$user['email']}\n";
            }

            echo "  └─ Credentials: {$user['email']} / {$user['password']}\n";
            echo "  └─ Plan: {$user['plan']}\n";
            echo "  └─ Code: {$user['code']}\n";
            echo "  └─ Status: activo\n\n";
        } catch (Exception $e) {
            echo "✗ Error with {$user['email']}: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Test users setup complete!\n";
echo str_repeat("=", 60) . "\n";
