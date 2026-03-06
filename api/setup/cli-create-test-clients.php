<?php
// CLI ONLY — Crear clientes de prueba esencial y metodo
// Uso: php /code/api/setup/cli-create-test-clients.php
// DELETE after use
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode(['error' => 'CLI only']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = getDB();

$clients = [
    ['code' => 'WC-ESENCIAL-001', 'email' => 'esencial@wellcore.com', 'name' => 'Cliente Esencial Test', 'plan' => 'esencial', 'pass' => 'esencial2026'],
    ['code' => 'WC-METODO-001',   'email' => 'metodo@wellcore.com',   'name' => 'Cliente Metodo Test',   'plan' => 'metodo',   'pass' => 'metodo2026'],
];

foreach ($clients as $c) {
    $hash = password_hash($c['pass'], PASSWORD_BCRYPT);
    $ex = $db->prepare("SELECT id FROM clients WHERE email = ?");
    $ex->execute([$c['email']]);
    $row = $ex->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $db->prepare("UPDATE clients SET plan=?, status='activo', password_hash=? WHERE email=?")
           ->execute([$c['plan'], $hash, $c['email']]);
        echo "UPDATED  id={$row['id']} | email={$c['email']} | pass={$c['pass']} | plan={$c['plan']}\n";
    } else {
        $db->prepare("INSERT INTO clients (client_code, email, name, plan, status, password_hash, created_at)
                      VALUES (?, ?, ?, ?, 'activo', ?, NOW())")
           ->execute([$c['code'], $c['email'], $c['name'], $c['plan'], $hash]);
        $id = $db->lastInsertId();
        echo "CREATED  id=$id | email={$c['email']} | pass={$c['pass']} | plan={$c['plan']}\n";
    }
}

echo "\nListo. Credenciales:\n";
echo "  esencial@wellcore.com / esencial2026 (plan: esencial)\n";
echo "  metodo@wellcore.com   / metodo2026   (plan: metodo)\n";
echo "DELETE este archivo: api/setup/cli-create-test-clients.php\n";
