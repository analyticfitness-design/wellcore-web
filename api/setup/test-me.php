<?php
// Test directo del flujo login + me.php
$base = 'https://wellcorefitness.test';

$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode([
            'type' => 'admin',
            'username' => 'daniel.esparza',
            'password' => 'RISE2026Admin!SuperPower'
        ]),
        'ignore_errors' => true
    ],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
]);

$response = file_get_contents($base . '/api/auth/login.php', false, $ctx);
$login = json_decode($response, true);
echo "Login response:\n";
echo json_encode($login, JSON_PRETTY_PRINT) . "\n\n";

if (empty($login['token'])) {
    echo "ERROR: No token in login response\n";
    exit;
}

$token = $login['token'];

// Ahora probar me.php con ese token
$ctx2 = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
        'ignore_errors' => true
    ],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
]);

$me_response = file_get_contents($base . '/api/auth/me.php', false, $ctx2);
echo "me.php response:\n";
echo json_encode(json_decode($me_response, true), JSON_PRETTY_PRINT) . "\n";
?>
