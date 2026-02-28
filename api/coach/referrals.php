<?php
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');

$coach = authenticateCoach();
$coachId = (int) $coach['id'];
$db = getDB();

// Get referral code and commission from coach profile
$stmt = $db->prepare("SELECT referral_code, referral_commission FROM coach_profiles WHERE admin_id = ?");
$stmt->execute([$coachId]);
$profile = $stmt->fetch();

if (!$profile || !$profile['referral_code']) {
    respondError('No tienes un codigo de referido asignado', 404);
}

$code = $profile['referral_code'];
$commission = (float) ($profile['referral_commission'] ?? 0);

// Total clicks
$stmt = $db->prepare("SELECT COUNT(*) AS total FROM referral_stats WHERE coach_id = ?");
$stmt->execute([$coachId]);
$clicks = (int) $stmt->fetch()['total'];

// Total conversions
$stmt = $db->prepare("SELECT COALESCE(SUM(converted), 0) AS total FROM referral_stats WHERE coach_id = ?");
$stmt->execute([$coachId]);
$conversions = (int) $stmt->fetch()['total'];

// Recent 20 referral events
$stmt = $db->prepare("SELECT created_at, converted FROM referral_stats WHERE coach_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$coachId]);
$recent = $stmt->fetchAll();

respond([
    'ok' => true,
    'code' => $code,
    'link' => 'https://wellcorefitness.com/?ref=' . $code,
    'commission' => $commission,
    'clicks' => $clicks,
    'conversions' => $conversions,
    'recent' => $recent,
]);
