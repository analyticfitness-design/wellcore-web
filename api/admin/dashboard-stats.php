<?php
/**
 * GET /api/admin/dashboard-stats
 * Single-call endpoint for the Superadmin Command Center dashboard.
 * Returns clients, revenue, checkins, risk, and monthly revenue data.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$admin = authenticateAdmin();
$db = getDB();

// ── Clients breakdown ─────────────────────────────────────────────────────────
$clientStats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'activo') AS active,
        SUM(plan = 'esencial' AND status = 'activo') AS esencial,
        SUM(plan = 'metodo'   AND status = 'activo') AS metodo,
        SUM(plan = 'elite'    AND status = 'activo') AS elite,
        SUM(plan = 'rise'     AND status = 'activo') AS rise,
        SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7  DAY)) AS new_week,
        SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS new_month
    FROM clients
")->fetch();

// ── Revenue ───────────────────────────────────────────────────────────────────
$revStats = $db->query("
    SELECT
        SUM(CASE WHEN status = 'approved' AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01') THEN amount ELSE 0 END) AS total_month,
        SUM(CASE WHEN status = 'approved' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount ELSE 0 END) AS total_30d,
        SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) AS total_all
    FROM payments
")->fetch();

// MRR from active subscriptions (plan prices)
$PLAN_PRICES = ['esencial' => 95, 'metodo' => 120, 'elite' => 150, 'rise' => 97];
$mrr = 0;
foreach (['esencial','metodo','elite','rise'] as $p) {
    $mrr += (int)($clientStats[$p] ?? 0) * ($PLAN_PRICES[$p] ?? 0);
}

// ── Checkins ──────────────────────────────────────────────────────────────────
$checkinStats = $db->query("
    SELECT
        SUM(coach_reply IS NULL OR coach_reply = '') AS pending,
        ROUND(AVG(bienestar), 1) AS avg_bienestar,
        SUM(DATE(replied_at) = CURDATE()) AS responded_today
    FROM checkins
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch();

// ── Risk ──────────────────────────────────────────────────────────────────────
$atRiskCount = (int)$db->query("
    SELECT COUNT(*) FROM (
        SELECT c.id
        FROM clients c
        LEFT JOIN checkins ch ON ch.client_id = c.id
        WHERE c.status = 'activo'
        GROUP BY c.id
        HAVING MAX(ch.checkin_date) < DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            OR MAX(ch.checkin_date) IS NULL
    ) sub
")->fetchColumn();

$expiringSoon = (int)$db->query("
    SELECT COUNT(*) FROM clients
    WHERE status = 'activo'
      AND subscription_end IS NOT NULL
      AND subscription_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")->fetchColumn();

// ── Monthly revenue (last 6 months) ──────────────────────────────────────────
$monthlyStmt = $db->query("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS ym,
        DATE_FORMAT(created_at, '%b')     AS label,
        SUM(amount) AS amount
    FROM payments
    WHERE status = 'approved'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY ym, label
    ORDER BY ym ASC
");
$monthly = $monthlyStmt->fetchAll();

// ── Top 5 at-risk clients ─────────────────────────────────────────────────────
$atRiskClients = $db->query("
    SELECT
        c.id, c.name, c.plan, c.email,
        c.subscription_end,
        MAX(ch.checkin_date) AS last_checkin,
        DATEDIFF(CURDATE(), MAX(ch.checkin_date)) AS days_since,
        DATEDIFF(c.subscription_end, CURDATE()) AS days_expiry
    FROM clients c
    LEFT JOIN checkins ch ON ch.client_id = c.id
    WHERE c.status = 'activo'
    GROUP BY c.id
    HAVING days_since > 14 OR days_since IS NULL OR days_expiry <= 10
    ORDER BY days_since DESC
    LIMIT 8
")->fetchAll();

// ── Pending checkins (most urgent) ───────────────────────────────────────────
$pendingCheckins = $db->query("
    SELECT ch.id, ch.client_id, c.name AS client_name, c.plan,
           ch.checkin_date, ch.bienestar, ch.comentario,
           DATEDIFF(CURDATE(), ch.checkin_date) AS days_waiting
    FROM checkins ch
    JOIN clients c ON c.id = ch.client_id
    WHERE (ch.coach_reply IS NULL OR ch.coach_reply = '')
    ORDER BY ch.checkin_date ASC
    LIMIT 10
")->fetchAll();

// ── Recent activity ───────────────────────────────────────────────────────────
$recentActivity = $db->query("
    (SELECT 'payment' AS type, c.name, p.amount AS extra, p.plan AS detail, p.created_at
     FROM payments p JOIN clients c ON c.id = p.client_id
     WHERE p.status = 'approved' ORDER BY p.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'client' AS type, name, NULL, plan, created_at
     FROM clients ORDER BY created_at DESC LIMIT 5)
    ORDER BY created_at DESC LIMIT 10
")->fetchAll();

respond([
    'clients'          => $clientStats,
    'revenue'          => array_merge((array)$revStats, ['mrr' => $mrr]),
    'checkins'         => $checkinStats,
    'risk'             => ['at_risk' => $atRiskCount, 'expiring_7d' => $expiringSoon],
    'monthly'          => $monthly,
    'at_risk_clients'  => $atRiskClients,
    'pending_checkins' => $pendingCheckins,
    'recent_activity'  => $recentActivity,
]);
