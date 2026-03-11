<?php
// POST /api/auth/reset-test-client-passwords.php
// Temporary endpoint to reset test client passwords for development/testing
// SECURITY: Requires admin authentication
// DELETE THIS FILE AFTER USE

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');

// Require admin authentication
$admin = authenticateAdmin();
if (!$admin) {
    respondError('Unauthorized - admin only', 401);
}

$db = getDB();

// Test clients to reset
$clients = [
    ['id' => 12, 'email' => 'elite@wellcore.com', 'password' => 'EliteTest123!'],
    ['id' => 14, 'email' => 'metodo@wellcore.com', 'password' => 'MetodoTest123!'],
    ['id' => 13, 'email' => 'esencial@wellcore.com', 'password' => 'EsencialTest123!'],
];

$updated = [];
$errors = [];

foreach ($clients as $client) {
    try {
        $hash = password_hash($client['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $db->prepare("UPDATE clients SET password_hash = ? WHERE id = ?");
        $result = $stmt->execute([$hash, $client['id']]);

        if ($result) {
            $updated[] = [
                'id' => $client['id'],
                'email' => $client['email'],
                'status' => 'success'
            ];
        } else {
            $errors[] = "Client {$client['id']}: Database update failed";
        }
    } catch (Exception $e) {
        $errors[] = "Client {$client['id']}: " . $e->getMessage();
    }
}

respond([
    'message' => 'Test password reset complete',
    'updated' => $updated,
    'errors' => $errors,
    'count' => count($updated)
]);
