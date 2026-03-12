<?php
// POST /api/auth/reset-passwords-temp.php
// Temporary endpoint to reset test client passwords
// Security: simple token (for testing only)
// DELETE THIS FILE AFTER USE

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';

requireMethod('POST');

// Security: require a simple token to prevent abuse
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if ($token !== 'RESET_TEST_PASSWORDS_2026') {
    respondError('Unauthorized', 401);
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
