<?php
/**
 * GET /api/coach/analytics
 * Dashboard de analytics del coach: retención, churn risk, engagement por cliente.
 *
 * Auth: coach
 * Responde: { summary, churn_risk[], top_engaged[], snapshot_saved }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();

$coach    = authenticateCoach();
$db       = getDB();
$coach_id = $coach['id'];

// Clientes activos del coach
$active = $db->prepare("SELECT COUNT(*) FROM clients WHERE coach_id = ? AND status = 'activo'");
$active->execute([$coach_id]);
$active_count = (int)$active->fetchColumn();

// Total clientes
$total = $db->prepare("SELECT COUNT(*) FROM clients WHERE coach_id = ?");
$total->execute([$coach_id]);
$total_count = (int)$total->fetchColumn();

// Ingresos del mes (pagos confirmados)
$rev = $db->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM payments
    WHERE coach_id = ?
      AND status = 'completed'
      AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
");
$rev->execute([$coach_id]);
$revenue_month = (float)$rev->fetchColumn();

// Check-ins esta semana
$cw = $db->prepare("
    SELECT COUNT(*) FROM checkins
    WHERE coach_id = ?
      AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$cw->execute([$coach_id]);
$checkins_week = (int)$cw->fetchColumn();

// Churn risk: clientes sin check-in en 14+ días
$churn = $db->prepare("
    SELECT
        c.id, c.name, c.plan,
        MAX(ch.created_at) AS last_checkin,
        DATEDIFF(NOW(), MAX(ch.created_at)) AS days_inactive
    FROM clients c
    LEFT JOIN checkins ch ON ch.client_id = c.id
    WHERE c.coach_id = ? AND c.status = 'activo'
    GROUP BY c.id, c.name, c.plan
    HAVING days_inactive >= 14 OR last_checkin IS NULL
    ORDER BY days_inactive DESC
    LIMIT 10
");
$churn->execute([$coach_id]);
$churn_risk = $churn->fetchAll(PDO::FETCH_ASSOC);
$churn_risk_count = count($churn_risk);

// Top engaged: más check-ins en últimos 30 días
$top = $db->prepare("
    SELECT
        c.id, c.name, c.plan,
        COUNT(ch.id) AS checkins_30d,
        cx.streak_days,
        cx.level
    FROM clients c
    LEFT JOIN checkins ch ON ch.client_id = c.id AND ch.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    LEFT JOIN client_xp cx ON cx.client_id = c.id
    WHERE c.coach_id = ? AND c.status = 'activo'
    GROUP BY c.id, c.name, c.plan, cx.streak_days, cx.level
    ORDER BY checkins_30d DESC
    LIMIT 10
");
$top->execute([$coach_id]);
$top_engaged = $top->fetchAll(PDO::FETCH_ASSOC);

// Engagement promedio (check-ins/cliente en 30 días)
$avg_engagement = $active_count > 0
    ? round(array_sum(array_column($top_engaged, 'checkins_30d')) / $active_count, 2)
    : 0;

// Guardar snapshot diario (UPSERT)
$today = date('Y-m-d');
$db->prepare("
    INSERT INTO coach_analytics_snapshots
        (coach_id, snapshot_date, active_clients, churn_risk_count, checkins_week, revenue_month, avg_engagement)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        active_clients   = VALUES(active_clients),
        churn_risk_count = VALUES(churn_risk_count),
        checkins_week    = VALUES(checkins_week),
        revenue_month    = VALUES(revenue_month),
        avg_engagement   = VALUES(avg_engagement)
")->execute([$coach_id, $today, $active_count, $churn_risk_count, $checkins_week, $revenue_month, $avg_engagement]);

respond([
    'summary' => [
        'active_clients'   => $active_count,
        'total_clients'    => $total_count,
        'churn_risk_count' => $churn_risk_count,
        'checkins_week'    => $checkins_week,
        'revenue_month'    => $revenue_month,
        'avg_engagement'   => $avg_engagement,
    ],
    'churn_risk'   => $churn_risk,
    'top_engaged'  => $top_engaged,
    'snapshot_saved' => true,
]);
