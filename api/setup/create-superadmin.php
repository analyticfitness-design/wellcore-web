<?php
// create-superadmin.php
// Crea el super admin de Daniel Esparza (CEO WellCore)
require_once __DIR__ . '/../config/database.php';

$username = 'daniel.esparza';
$password = 'RISE2026Admin!SuperPower';
$name = 'Daniel Esparza - CEO';
$role = 'superadmin';

// Hash password
$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $pdo = getDB();
    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        echo "⚠️  Super Admin ya existe: $username\n";
        echo "Actualizando contraseña...\n";
        $stmt = $pdo->prepare("UPDATE admins SET password_hash = ?, name = ?, role = ? WHERE username = ?");
        $stmt->execute([$password_hash, $name, $role, $username]);
        echo "✅ Super Admin actualizado exitosamente.\n";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO admins (username, password_hash, role, name, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $result = $stmt->execute([$username, $password_hash, $role, $name]);

        if ($result) {
            echo "✅ Super Admin creado exitosamente:\n";
        } else {
            echo "❌ Error al crear super admin\n";
            exit(1);
        }
    }

    echo "Usuario: $username\n";
    echo "Contraseña: $password\n";
    echo "Rol: $role\n";
    echo "\n⚠️  GUARDA ESTAS CREDENCIALES EN LUGAR SEGURO\n";

} catch (PDOException $e) {
    echo "❌ Error DB: " . $e->getMessage() . "\n";
    exit(1);
}
?>
