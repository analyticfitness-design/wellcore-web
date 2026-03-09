<?php
// GET /api/coach/revenue
// Response: { mrr_current, mrr_history: [{month,amount}x6], clients_renewing: [...] }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$coach = authenticateCoach();
$db    = getDB();
$cid   = $coach['id'];

// Precio por plan
$prices = ['esencial' => 95, 'metodo' => 120, 'elite' => 150, 'rise' => 0];

// MRR actual (clientes activos del coach)
$activeStmt = $db->prepare("SELECT plan FROM clients WHERE coach_id = ? AND status = 'active'");
$activeStmt->execute([$cid]);
$active = $activeStmt->fetchAll();
$mrr = 0;
foreach ($active as $c) $mrr += $prices[$c['plan']] ?? 0;

// Histórico 6 meses — estimado por clientes activos por mes (basado en fecha_inicio)
$history = [];
for ($i = 5; $i >= 0; $i--) {
    $monthLabel = date('Y-m', strtotime("-$i months"));
    $stmt = $db->prepare("
        SELECT plan FROM clients
        WHERE coach_id = ?
          AND status = 'active'
          AND fecha_inicio <= LAST_DAY(DATE_FORMAT(NOW() - INTERVAL ? MONTH, '%Y-%m-01'))
    ");
    $stmt->execute([$cid, $i]);
    $rows  = $stmt->fetchAll();
    $total = 0;
    foreach ($rows as $r) $total += $prices[$r['plan']] ?? 0;
    $history[] = ['month' => $monthLabel, 'amount' => $total];
}

// Clientes por renovar en los próximos 30 días
// Usamos fecha_inicio + N meses como proxy de renovación
$renewStmt = $db->prepare("
    SELECT id, name, plan, fecha_inicio,
           DATEDIFF(
             DATE_ADD(fecha_inicio, INTERVAL CEIL(DATEDIFF(CURDATE(), fecha_inicio) / 30) * 30 DAY),
             CURDATE()
           ) AS days_left
    FROM clients
    WHERE coach_id = ? AND status = 'active'
    HAVING days_left BETWEEN 0 AND 30
    ORDER BY days_left ASC
    LIMIT 20
");
$renewStmt->execute([$cid]);
$renewing = $renewStmt->fetchAll();

respond([
    'mrr_current'      => $mrr,
    'mrr_history'      => $history,
    'clients_renewing' => $renewing,
]);
