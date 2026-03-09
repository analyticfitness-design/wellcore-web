<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: text/plain');
$db = getDB();
$admins = $db->query("SELECT id, username, name, role FROM admins ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($admins as $a) {
    echo "{$a['id']} | {$a['username']} | {$a['name']} | {$a['role']}\n";
}
