<?php
/**
 * GET /api/coach/stats.php
 * Returns dashboard stats for the authenticated coach.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');

$coach = authenticateCoach();
$coachId = (int) $coach['id'];
$coachIdStr = (string) $coachId;

$db = getDB();

// ── Active clients ──────────────────────────────────────────
$stmt = $db->prepare("SELECT COUNT(*) FROM clients WHERE coach_id = ? AND status = 'activo'");
$stmt->execute([$coachId]);
$activeClients = (int) $stmt->fetchColumn();

// ── Total clients ───────────────────────────────────────────
$stmt = $db->prepare("SELECT COUNT(*) FROM clients WHERE coach_id = ?");
$stmt->execute([$coachId]);
$totalClients = (int) $stmt->fetchColumn();

// ── Open tickets ────────────────────────────────────────────
// tickets.coach_id is VARCHAR, match by string
$stmt = $db->prepare("SELECT COUNT(*) FROM tickets WHERE coach_id = ? AND status = 'open'");
$stmt->execute([$coachIdStr]);
$openTickets = (int) $stmt->fetchColumn();

// ── Revenue this month ──────────────────────────────────────
$stmt = $db->prepare("
    SELECT COALESCE(SUM(p.amount), 0)
    FROM payments p
    JOIN clients c ON c.id = p.client_id
    WHERE c.coach_id = ?
      AND p.status = 'approved'
      AND YEAR(p.created_at) = YEAR(CURDATE())
      AND MONTH(p.created_at) = MONTH(CURDATE())
");
$stmt->execute([$coachId]);
$revenueMonth = (float) $stmt->fetchColumn();

// ── Coach share (60%) ───────────────────────────────────────
$coachShare = round($revenueMonth * 0.60, 2);

// ── Referral clicks ─────────────────────────────────────────
$stmt = $db->prepare("SELECT COUNT(*) FROM referral_stats WHERE coach_id = ?");
$stmt->execute([$coachId]);
$referralClicks = (int) $stmt->fetchColumn();

// ── Referral conversions ────────────────────────────────────
$stmt = $db->prepare("SELECT COALESCE(SUM(converted), 0) FROM referral_stats WHERE coach_id = ?");
$stmt->execute([$coachId]);
$referralConversions = (int) $stmt->fetchColumn();

respond([
    'ok'    => true,
    'stats' => [
        'active_clients'       => $activeClients,
        'total_clients'        => $totalClients,
        'open_tickets'         => $openTickets,
        'revenue_month'        => $revenueMonth,
        'coach_share'          => $coachShare,
        'referral_clicks'      => $referralClicks,
        'referral_conversions' => $referralConversions,
    ]
]);
