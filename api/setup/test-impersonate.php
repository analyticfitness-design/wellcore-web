<?php
$base = 'https://wellcorefitness.test';
$ssl  = ['verify_peer' => false, 'verify_peer_name' => false];

// Login admin
$ctx = stream_context_create([
    'http' => ['method'=>'POST','header'=>"Content-Type: application/json\r\n",'content'=>json_encode(['type'=>'admin','username'=>'daniel.esparza','password'=>'RISE2026Admin!SuperPower']),'ignore_errors'=>true],
    'ssl'  => $ssl
]);
$login = json_decode(file_get_contents($base . '/api/auth/login.php', false, $ctx), true);
$adminToken = $login['token'] ?? null;
echo "Admin token: " . ($adminToken ? substr($adminToken,0,16)."..." : "FAIL") . "\n\n";
if (!$adminToken) exit;

// Impersonate cliente id=1 (Carlos Mendoza - demo)
$ctx2 = stream_context_create([
    'http' => ['method'=>'POST','header'=>"Content-Type: application/json\r\nAuthorization: Bearer $adminToken\r\n",'content'=>json_encode(['client_id'=>1]),'ignore_errors'=>true],
    'ssl'  => $ssl
]);
$r = json_decode(file_get_contents($base . '/api/admin/impersonate.php', false, $ctx2), true);
echo "Impersonate response:\n" . json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if (empty($r['token'])) { echo "ERROR: no client token\n"; exit; }
$clientToken = $r['token'];

// Verificar que el token de cliente funciona con me.php
$ctx3 = stream_context_create([
    'http' => ['method'=>'GET','header'=>"Authorization: Bearer $clientToken\r\n",'ignore_errors'=>true],
    'ssl'  => $ssl
]);
$me = json_decode(file_get_contents($base . '/api/auth/me.php', false, $ctx3), true);
echo "me.php con token de cliente:\n" . json_encode($me, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
?>
