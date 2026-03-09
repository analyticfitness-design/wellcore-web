<?php
/**
 * GET /api/gamification/leaderboard
 * Ranking semanal de clientes del mismo coach por XP ganado esta semana.
 * Accesible por cliente autenticado o coach autenticado.
 *
 * Query params: ?period=week|month|all&limit=20
 * Responde: { period, items[{ rank, client_id, name, xp_period, level, streak_days, is_me }] }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();

$db     = getDB();
$period = $_GET['period'] ?? 'week';
$limit  = min(50, max(5, (int)($_GET['limit'] ?? 20)));

// Intentar auth cliente primero, luego coach
$viewer_id   = null;
$viewer_type = null;
$coach_id    = null;

try {
    $client      = authenticateClient();
    $viewer_id   = $client['id'];
    $viewer_type = 'client';
    // Obtener coach_id del cliente para filtrar por coach
    $r = $db->prepare("SELECT coach_id FROM clients WHERE id = ?");
    $r->execute([$viewer_id]);
    $coach_id = $r->fetchColumn() ?: null;
} catch (\Exception $e) {
    try {
        $coach       = authenticateCoach();
        $viewer_id   = $coach['id'];
        $viewer_type = 'coach';
        $coach_id    = $coach['id'];
    } catch (\Exception $e2) {
        respondError('Autenticación requerida', 401);
    }
}

// Calcular ventana de tiempo
$date_filter = match ($period) {
    'week'  => "AND xpe.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'month' => "AND xpe.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    default => "", // all time — usar xp_total directamente
};

if ($period === 'all') {
    // Ranking por XP total
    $coach_filter = $coach_id ? "AND c.coach_id = ?" : "";
    $params = $coach_id ? [$coach_id] : [];

    $stmt = $db->prepare("
        SELECT
            cx.client_id,
            c.name,
            cx.xp_total  AS xp_period,
            cx.level,
            cx.streak_days
        FROM client_xp cx
        JOIN clients c ON c.id = cx.client_id
        WHERE c.status = 'active'
          {$coach_filter}
        ORDER BY cx.xp_total DESC
        LIMIT {$limit}
    ");
    $stmt->execute($params);
} else {
    // Ranking por XP ganado en el periodo
    $coach_filter = $coach_id ? "AND c.coach_id = ?" : "";
    $params = $coach_id ? [$coach_id] : [];

    $stmt = $db->prepare("
        SELECT
            xpe.client_id,
            c.name,
            SUM(xpe.xp_gained) AS xp_period,
            cx.level,
            cx.streak_days
        FROM xp_events xpe
        JOIN clients c  ON c.id = xpe.client_id
        LEFT JOIN client_xp cx ON cx.client_id = xpe.client_id
        WHERE c.status = 'active'
          {$date_filter}
          {$coach_filter}
        GROUP BY xpe.client_id, c.name, cx.level, cx.streak_days
        ORDER BY xp_period DESC
        LIMIT {$limit}
    ");
    $stmt->execute($params);
}

$rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$items = [];
foreach ($rows as $i => $r) {
    $items[] = [
        'rank'       => $i + 1,
        'client_id'  => $r['client_id'],
        'name'       => $r['name'],
        'xp_period'  => (int)$r['xp_period'],
        'level'      => (int)($r['level'] ?? 1),
        'streak_days'=> (int)($r['streak_days'] ?? 0),
        'is_me'      => ($viewer_type === 'client' && $r['client_id'] === $viewer_id),
    ];
}

// Posición del viewer si no está en top N
$my_rank = null;
if ($viewer_type === 'client') {
    foreach ($items as $it) {
        if ($it['is_me']) { $my_rank = $it['rank']; break; }
    }
    if ($my_rank === null) {
        // Buscar posición real
        $pos = $db->prepare("
            SELECT COUNT(*) + 1 AS rank_pos FROM client_xp cx2
            JOIN clients c2 ON c2.id = cx2.client_id
            WHERE c2.status = 'active'
            AND cx2.xp_total > (SELECT COALESCE(xp_total, 0) FROM client_xp WHERE client_id = ?)
        ");
        $pos->execute([$viewer_id]);
        $my_rank = (int)$pos->fetchColumn();
    }
}

respond([
    'period'   => $period,
    'my_rank'  => $my_rank,
    'items'    => $items,
]);
