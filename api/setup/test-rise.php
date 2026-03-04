<?php
$base = 'https://wellcorefitness.test';
$ssl  = ['verify_peer' => false, 'verify_peer_name' => false];

// Login
$ctx = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n",
        'content'       => json_encode(['type'=>'admin','username'=>'daniel.esparza','password'=>'RISE2026Admin!SuperPower']),
        'ignore_errors' => true
    ],
    'ssl' => $ssl
]);
$login = json_decode(file_get_contents($base . '/api/auth/login.php', false, $ctx), true);
$token = $login['token'] ?? null;
echo "Login: " . ($token ? "OK token=" . substr($token,0,16) . "..." : "FAIL") . "\n\n";
if (!$token) exit;

// get-enrollments
$ctx2 = stream_context_create([
    'http' => ['method'=>'GET','header'=>"Authorization: Bearer $token\r\n",'ignore_errors'=>true],
    'ssl'  => $ssl
]);
$r = file_get_contents($base . '/api/rise/get-enrollments.php', false, $ctx2);
echo "get-enrollments:\n" . json_encode(json_decode($r), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
?>
