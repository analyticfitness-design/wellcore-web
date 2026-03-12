<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$db = getDB();
$r = [];

$r['admins'] = $db->query('SELECT id, username, role FROM admins')->fetchAll(PDO::FETCH_ASSOC);

$hash = password_hash('RISE2026Admin!SuperPower', PASSWORD_BCRYPT);
$st = $db->prepare('UPDATE admins SET password_hash = ? WHERE username = ?');
$st->execute([$hash, 'daniel.esparza']);
$r['pw_reset'] = 'daniel.esparza rows=' . $st->rowCount();

try {
    $db->exec("ALTER TABLE clients MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise','presencial') DEFAULT 'esencial'");
    $r['enum_clients'] = 'OK';
} catch (PDOException $e) {
    $r['enum_clients'] = $e->getMessage();
}

try {
    $db->exec("ALTER TABLE invitations MODIFY COLUMN plan ENUM('esencial','metodo','elite','presencial') NOT NULL");
    $r['enum_invitations'] = 'OK';
} catch (PDOException $e) {
    $r['enum_invitations'] = $e->getMessage();
}

$r['verify_clients'] = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='plan'")->fetchColumn();
$r['verify_invitations'] = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME='invitations' AND COLUMN_NAME='plan'")->fetchColumn();

echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
