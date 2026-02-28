<?php
/**
 * WellCore — Reset all accounts and create single jefe admin
 * Run once then DELETE this file from production.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db = getDB();
$results = [];

$db->exec("SET FOREIGN_KEY_CHECKS = 0");

$db->exec("TRUNCATE TABLE auth_tokens");
$results[] = "auth_tokens: cleared";

$clientTables = [
    'checkins', 'training_logs', 'metrics', 'progress_photos',
    'assigned_plans', 'weight_logs', 'payments', 'invitations',
    'inscriptions', 'client_profiles', 'clients'
];
foreach ($clientTables as $t) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM `$t`");
        $count = $stmt->fetchColumn();
        $db->exec("DELETE FROM `$t`");
        $db->exec("ALTER TABLE `$t` AUTO_INCREMENT = 1");
        $results[] = "$t: cleared ($count rows)";
    } catch (PDOException $e) {
        $results[] = "$t: skip - " . $e->getMessage();
    }
}

$adminTables = ['coach_achievements', 'referral_stats', 'coach_profiles'];
foreach ($adminTables as $t) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM `$t`");
        $count = $stmt->fetchColumn();
        $db->exec("DELETE FROM `$t`");
        $results[] = "$t: cleared ($count rows)";
    } catch (PDOException $e) {
        $results[] = "$t: skip - " . $e->getMessage();
    }
}

$db->exec("DELETE FROM admins");
$db->exec("ALTER TABLE admins AUTO_INCREMENT = 1");
$results[] = "admins: cleared";

$db->exec("SET FOREIGN_KEY_CHECKS = 1");

$username = 'CoachDann';
$password = 'KingLord6962';
$name     = 'Coach Dann';
$role     = 'jefe';
$hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $db->prepare("INSERT INTO admins (username, password_hash, name, role) VALUES (?, ?, ?, ?)");
$stmt->execute([$username, $hash, $name, $role]);
$adminId = $db->lastInsertId();
$results[] = "Created admin: $username (role: $role, id: $adminId)";

echo json_encode([
    'ok'      => true,
    'message' => 'All accounts reset. Single jefe admin created.',
    'admin'   => ['username' => $username, 'role' => $role, 'id' => (int)$adminId],
    'results' => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
