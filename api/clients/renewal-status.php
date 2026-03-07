<?php
/**
 * GET /api/clients/renewal-status.php
 * Returns renewal window info for the authenticated client.
 * Called by frontend on dashboard load to show renewal banner.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$client = authenticateClient();
$db = getDB();
$cid = (int)$client['id'];

$stmt = $db->prepare("
    SELECT subscription_end, plan,
           DATEDIFF(subscription_end, CURDATE()) AS days_remaining
    FROM clients
    WHERE id = ?
");
$stmt->execute([$cid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || !$row['subscription_end']) {
    respond([
        'show_banner' => false,
        'days_remaining' => null,
        'form_url' => null,
        'plan' => $client['plan'] ?? 'esencial',
    ]);
}

$daysRemaining = (int)$row['days_remaining'];
$plan = $row['plan'] ?? 'esencial';

// Show banner when 3 or fewer days remaining (including day 0 = expiry day)
$showBanner = $daysRemaining >= 0 && $daysRemaining <= 3;

// Formspree URLs — must match cron config
$formUrl = ($plan === 'rise')
    ? 'https://formspree.io/f/PLACEHOLDER_RISE'
    : 'https://formspree.io/f/PLACEHOLDER_REGULAR';

respond([
    'show_banner'    => $showBanner,
    'days_remaining' => $daysRemaining,
    'form_url'       => $formUrl,
    'plan'           => $plan,
    'subscription_end' => $row['subscription_end'],
]);
