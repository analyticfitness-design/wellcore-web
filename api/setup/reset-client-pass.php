<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';

$secret = $_GET['secret'] ?? '';
if ($secret !== 'WC_RESET_2026') {
    respondError('Forbidden', 403);
}

$db = getDB();
$hash = password_hash('wc2026', PASSWORD_BCRYPT, ['cost' => 12]);

$action = $_GET['action'] ?? 'reset';

if ($action === 'list') {
    $stmt = $db->query("SELECT id, client_code, email, name, plan, status FROM clients ORDER BY id");
    respond(['clients' => $stmt->fetchAll()]);
}

if ($action === 'clear-rate-limit') {
    $db->query("DELETE FROM rate_limits");
    respond(['message' => 'Rate limits limpiados']);
}

$db->prepare("UPDATE clients SET password_hash = ? WHERE email = 'carlos@wellcore.com'")->execute([$hash]);
$db->prepare("UPDATE clients SET password_hash = ? WHERE email = 'sofia@wellcore.com'")->execute([$hash]);
$db->prepare("UPDATE clients SET password_hash = ? WHERE email = 'andres@wellcore.com'")->execute([$hash]);

respond(['message' => 'Passwords reseteados a wc2026 para Carlos, Sofia y Andres']);
