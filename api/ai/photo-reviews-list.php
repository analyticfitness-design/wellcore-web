<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
/**
 * GET /api/ai/photo-reviews-list.php
 * Lista reviews de fotos del cliente autenticado.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$client = authenticateClient();
$db     = getDB();

$stmt = $db->prepare("
    SELECT id, photo_date, review_text, created_at
    FROM photo_reviews
    WHERE client_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([(int)$client['id']]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count usage for plan limits
$plan = strtolower($client['plan'] ?? 'esencial');
$thisMonth = date('Y-m');
$monthCount = 0;
$lastReview = null;
foreach ($reviews as $r) {
    if (substr($r['created_at'], 0, 7) === $thisMonth) $monthCount++;
    if (!$lastReview) $lastReview = $r['created_at'];
}

$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekUsed = false;
foreach ($reviews as $r) {
    if ($r['created_at'] >= $weekStart) { $weekUsed = true; break; }
}

respond([
    'reviews'     => $reviews,
    'usage'       => [
        'month_count' => $monthCount,
        'last_review' => $lastReview,
        'week_used'   => $weekUsed,
    ],
]);
