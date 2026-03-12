<?php
/**
 * WellCore — Client Celebrations
 * GET  /api/client/celebrations.php         — Check for pending celebrations
 * POST /api/client/celebrations.php         — Mark celebration as shown { event_type: string }
 */
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$client = authenticateClient();
$clientId = (int)$client['id'];
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $pending = [];

    // Check streak milestones
    try {
        $stmt = $db->prepare("SELECT current_streak FROM client_xp WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $streak = (int)($stmt->fetchColumn() ?: 0);

        if ($streak >= 7 && !wasShown($db, $clientId, 'streak_7'))   $pending[] = ['type' => 'streak_7',   'title' => '7 dias seguidos!', 'icon' => 'fire', 'xp' => 100];
        if ($streak >= 30 && !wasShown($db, $clientId, 'streak_30')) $pending[] = ['type' => 'streak_30',  'title' => '30 dias seguidos!', 'icon' => 'trophy', 'xp' => 300];
    } catch (\Throwable $e) {}

    // Check first week
    $daysSinceJoin = (int)((time() - strtotime($client['created_at'] ?? 'now')) / 86400);
    if ($daysSinceJoin >= 7 && !wasShown($db, $clientId, 'first_week')) {
        $pending[] = ['type' => 'first_week', 'title' => 'Semana 1 completada!', 'icon' => 'confetti', 'xp' => 50];
    }

    // Check total check-ins
    $stmt = $db->prepare("SELECT COUNT(*) FROM checkins WHERE client_id = ?");
    $stmt->execute([$clientId]);
    $totalCheckins = (int)$stmt->fetchColumn();

    if ($totalCheckins >= 1 && !wasShown($db, $clientId, 'first_checkin')) {
        $pending[] = ['type' => 'first_checkin', 'title' => 'Primer check-in completado!', 'icon' => 'star', 'xp' => 20];
    }

    // Check perfect habits (7 days in a row with all habits done)
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM daily_habits
            WHERE client_id = ? AND completed = 1
              AND habit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$clientId]);
        $perfectWeek = (int)$stmt->fetchColumn();
        if ($perfectWeek >= 7 && !wasShown($db, $clientId, 'perfect_habits_week')) {
            $pending[] = ['type' => 'perfect_habits_week', 'title' => 'Habitos perfectos toda la semana!', 'icon' => 'gold_star', 'xp' => 150];
        }
    } catch (\Throwable $e) {}

    // Check personal records
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM personal_records WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $prCount = (int)$stmt->fetchColumn();
        if ($prCount >= 1 && !wasShown($db, $clientId, 'first_pr')) {
            $pending[] = ['type' => 'first_pr', 'title' => 'Nuevo record personal!', 'icon' => 'muscle', 'xp' => 80];
        }
    } catch (\Throwable $e) {}

    // Weekly summary available
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $stmt = $db->prepare("SELECT data_json, coach_note FROM weekly_summaries WHERE client_id = ? AND week_start = ?");
    $stmt->execute([$clientId, $weekStart]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    respond([
        'ok'             => true,
        'celebrations'   => $pending,
        'weekly_summary' => $summary ? [
            'data'       => json_decode($summary['data_json'], true),
            'coach_note' => $summary['coach_note'],
        ] : null,
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getJsonBody();
    $eventType = trim($body['event_type'] ?? '');
    if (!$eventType) respondError('event_type requerido', 422);

    // Mark as shown + award XP
    $xpMap = [
        'first_week'           => 50,
        'streak_7'             => 100,
        'streak_30'            => 300,
        'first_checkin'        => 20,
        'first_pr'             => 80,
        'perfect_habits_week'  => 150,
    ];

    $db->prepare("
        INSERT IGNORE INTO celebrations (client_id, event_type, shown_at)
        VALUES (?, ?, NOW())
    ")->execute([$clientId, $eventType]);

    $xp = $xpMap[$eventType] ?? 0;
    if ($xp > 0) {
        try {
            $db->prepare("
                INSERT INTO xp_events (client_id, event_type, xp_amount, description, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([$clientId, 'celebration_' . $eventType, $xp, 'Celebracion: ' . $eventType]);
            $db->prepare("
                INSERT INTO client_xp (client_id, total_xp, weekly_xp, current_streak)
                VALUES (?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE total_xp = total_xp + ?, weekly_xp = weekly_xp + ?
            ")->execute([$clientId, $xp, $xp, $xp, $xp]);
        } catch (\Throwable $e) {}
    }

    respond(['ok' => true, 'xp_awarded' => $xp]);

} else {
    respondError('Method not allowed', 405);
}

function wasShown(PDO $db, int $clientId, string $eventType): bool {
    $stmt = $db->prepare("SELECT id FROM celebrations WHERE client_id = ? AND event_type = ?");
    $stmt->execute([$clientId, $eventType]);
    return (bool)$stmt->fetchColumn();
}
