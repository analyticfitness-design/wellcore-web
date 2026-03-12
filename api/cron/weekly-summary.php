<?php
/**
 * WellCore — Weekly Summary Cron
 * Generates weekly summary for each active client.
 * Sundays 8pm: 0 20 * * 0 php /code/api/cron/weekly-summary.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/web-push.php';

$db = getDB();
$weekStart = date('Y-m-d', strtotime('monday this week'));
$generated = 0;

echo "[" . date('Y-m-d H:i:s') . "] Weekly summary cron started (week: $weekStart)\n";

$clients = $db->query("
    SELECT c.id, c.name, c.email, c.plan, c.coach_id
    FROM clients c
    WHERE c.status = 'activo'
")->fetchAll(PDO::FETCH_ASSOC);

echo "Processing " . count($clients) . " active clients\n";

foreach ($clients as $c) {
    $cid = (int)$c['id'];

    // Skip if already generated this week
    $exists = $db->prepare("SELECT id FROM weekly_summaries WHERE client_id = ? AND week_start = ?");
    $exists->execute([$cid, $weekStart]);
    if ($exists->fetchColumn()) continue;

    // Gather data
    $data = [];

    // Check-ins this week
    $stmt = $db->prepare("
        SELECT bienestar, dias_entrenados, nutricion_seguida
        FROM checkins
        WHERE client_id = ? AND checkin_date >= ?
        ORDER BY checkin_date DESC LIMIT 1
    ");
    $stmt->execute([$cid, $weekStart]);
    $checkin = $stmt->fetch(PDO::FETCH_ASSOC);
    $data['checkin'] = $checkin ?: null;
    $data['bienestar'] = $checkin ? (int)$checkin['bienestar'] : null;
    $data['dias_entrenados'] = $checkin ? (int)$checkin['dias_entrenados'] : null;
    $data['nutricion'] = $checkin ? $checkin['nutricion_seguida'] : null;

    // Habits completion this week
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) AS total, SUM(completed) AS done
            FROM daily_habits
            WHERE client_id = ? AND habit_date >= ?
        ");
        $stmt->execute([$cid, $weekStart]);
        $habits = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['habits_total'] = (int)($habits['total'] ?? 0);
        $data['habits_done'] = (int)($habits['done'] ?? 0);
    } catch (\Throwable $e) {
        $data['habits_total'] = 0;
        $data['habits_done'] = 0;
    }

    // XP this week
    try {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(xp_amount), 0) FROM xp_events
            WHERE client_id = ? AND created_at >= ?
        ");
        $stmt->execute([$cid, $weekStart]);
        $data['xp_week'] = (int)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        $data['xp_week'] = 0;
    }

    // Streak
    try {
        $stmt = $db->prepare("SELECT current_streak, total_xp FROM client_xp WHERE client_id = ?");
        $stmt->execute([$cid]);
        $xpRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['streak'] = (int)($xpRow['current_streak'] ?? 0);
        $data['total_xp'] = (int)($xpRow['total_xp'] ?? 0);
    } catch (\Throwable $e) {
        $data['streak'] = 0;
        $data['total_xp'] = 0;
    }

    // Previous week bienestar for comparison
    $prevWeek = date('Y-m-d', strtotime($weekStart . ' -7 days'));
    $stmt = $db->prepare("
        SELECT data_json FROM weekly_summaries
        WHERE client_id = ? AND week_start = ?
    ");
    $stmt->execute([$cid, $prevWeek]);
    $prevData = $stmt->fetchColumn();
    if ($prevData) {
        $prev = json_decode($prevData, true);
        $data['prev_bienestar'] = $prev['bienestar'] ?? null;
        $data['prev_dias'] = $prev['dias_entrenados'] ?? null;
    }

    // Save summary
    $db->prepare("
        INSERT INTO weekly_summaries (client_id, week_start, data_json, created_at)
        VALUES (?, ?, ?, NOW())
    ")->execute([$cid, $weekStart, json_encode($data, JSON_UNESCAPED_UNICODE)]);

    // Send push
    $fn = explode(' ', trim($c['name']))[0];
    webpush_send_to_client($db, $cid, 'Tu semana en WellCore', "Hola $fn, tu resumen semanal esta listo.", '/cliente.html#dashboard');

    $generated++;
    echo "  [OK] Client $cid ($fn)\n";
}

echo "\n[" . date('Y-m-d H:i:s') . "] Weekly summary done. Generated: $generated\n";
