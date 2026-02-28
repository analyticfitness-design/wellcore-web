<?php
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('POST');

$body = getJsonBody();
$referralCode = trim($body['referral_code'] ?? '');
$sourceUrl = trim($body['source_url'] ?? '');

if ($referralCode === '') {
    respondError('referral_code es requerido', 400);
}

$db = getDB();

// Find coach by referral code
$stmt = $db->prepare("SELECT admin_id FROM coach_profiles WHERE referral_code = ?");
$stmt->execute([$referralCode]);
$coach = $stmt->fetch();

if (!$coach) {
    respondError('Codigo de referido no encontrado', 404);
}

$coachId = (int) $coach['admin_id'];

// Hash visitor IP for daily dedup
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (str_contains($ip, ',')) {
    $ip = trim(explode(',', $ip)[0]);
}
$visitorHash = hash('sha256', $ip . date('Y-m-d'));

// Check if already tracked today
$stmt = $db->prepare("SELECT id FROM referral_stats WHERE coach_id = ? AND visitor_hash = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$coachId, $visitorHash]);

if ($stmt->fetch()) {
    respond(['ok' => true, 'message' => 'Already tracked']);
}

// Insert new referral stat
$stmt = $db->prepare("INSERT INTO referral_stats (coach_id, visitor_hash, source_url) VALUES (?, ?, ?)");
$stmt->execute([$coachId, $visitorHash, $sourceUrl ?: null]);

respond(['ok' => true, 'message' => 'Referral tracked']);
