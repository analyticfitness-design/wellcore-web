<?php
/**
 * Diagnóstico rápido: coach_id en clients vs coach_audio
 * Llamar con Bearer token de admin.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db = getDB();
header('Content-Type: text/plain');

echo "=== clients.coach_id ===\n";
foreach ($db->query("SELECT id, name, plan, coach_id FROM clients ORDER BY id LIMIT 15")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  id={$r['id']} plan={$r['plan']} coach_id=" . var_export($r['coach_id'], true) . " name={$r['name']}\n";
}

echo "\n=== coach_audio (coach_id, is_active) ===\n";
foreach ($db->query("SELECT id, coach_id, title, is_active FROM coach_audio ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  id={$r['id']} coach_id=" . var_export($r['coach_id'], true) . " is_active={$r['is_active']} title={$r['title']}\n";
}

echo "\n=== coach_video_tips (coach_id, is_active) ===\n";
foreach ($db->query("SELECT id, coach_id, title, is_active FROM coach_video_tips ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  id={$r['id']} coach_id=" . var_export($r['coach_id'], true) . " is_active={$r['is_active']} title={$r['title']}\n";
}

echo "\n=== admins (id, username, role) ===\n";
foreach ($db->query("SELECT id, username, role FROM admins ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  id={$r['id']} username={$r['username']} role={$r['role']}\n";
}

echo "\n=== COLUMN TYPE clients.coach_id ===\n";
foreach ($db->query("DESCRIBE clients")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if ($r['Field'] === 'coach_id') {
        echo "  {$r['Field']} | {$r['Type']} | {$r['Null']} | {$r['Default']}\n";
    }
}
