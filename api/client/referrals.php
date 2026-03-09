<?php
// GET /api/client/referrals
// Response: { referral_code, referral_link, referrals: [...], total_converted, reward_pending }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$client = authenticateClient();
$db = getDB();

// Asegurar que el cliente tiene referral_code
$row = $db->prepare("SELECT referral_code FROM clients WHERE id = ?");
$row->execute([$client['id']]);
$code = $row->fetchColumn();

if (!$code) {
    do {
        $code = strtoupper(substr(base_convert(bin2hex(random_bytes(4)), 16, 36), 0, 8));
        $check = $db->prepare("SELECT COUNT(*) FROM clients WHERE referral_code = ?");
        $check->execute([$code]);
    } while ($check->fetchColumn() > 0);

    $db->prepare("UPDATE clients SET referral_code = ? WHERE id = ?")->execute([$code, $client['id']]);
}

// Detectar base URL
$isDocker = file_exists('/.dockerenv');
$baseUrl  = $isDocker ? 'https://wellcorefitness.com' : 'https://wellcorefitness.test';
$referralLink = $baseUrl . '/inscripcion.html?ref=' . $code;

// Referidos de este cliente
$stmt = $db->prepare("
    SELECT referred_email, status, reward_granted, created_at, converted_at
    FROM referrals
    WHERE referrer_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$client['id']]);
$referrals = $stmt->fetchAll();

$totalConverted = 0;
$rewardPending  = 0;
foreach ($referrals as $r) {
    if ($r['status'] === 'converted') {
        $totalConverted++;
        if (!$r['reward_granted']) $rewardPending++;
    }
}

respond([
    'referral_code'   => $code,
    'referral_link'   => $referralLink,
    'referrals'       => $referrals,
    'total_converted' => $totalConverted,
    'reward_pending'  => $rewardPending,
]);
