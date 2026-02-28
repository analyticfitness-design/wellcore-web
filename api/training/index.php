<?php
// GET /api/training?year=2026&week=8   → week log
// GET /api/training?range=8            → last 8 weeks
// POST /api/training                   → toggle day

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST');
$client = authenticateClient();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['range'])) {
        // Last N weeks
        $weeks = min((int)$_GET['range'], 52);
        $stmt = $db->prepare("
            SELECT log_date, completed, year_num, week_num
            FROM training_logs
            WHERE client_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
            ORDER BY log_date ASC
        ");
        $stmt->execute([$client['id'], $weeks]);
        respond(['logs' => $stmt->fetchAll()]);
    }

    $year = (int)($_GET['year'] ?? date('Y'));
    $week = (int)($_GET['week'] ?? (int)date('W'));

    // Return 7-element boolean array [Mon, Tue, ..., Sun]
    $stmt = $db->prepare("
        SELECT log_date, completed FROM training_logs
        WHERE client_id = ? AND year_num = ? AND week_num = ?
        ORDER BY log_date ASC
    ");
    $stmt->execute([$client['id'], $year, $week]);
    $rows = $stmt->fetchAll();

    $weekLog = array_fill(0, 7, false);
    foreach ($rows as $row) {
        $dow = (int)date('N', strtotime($row['log_date'])) - 1; // 0=Mon
        $weekLog[$dow] = (bool)$row['completed'];
    }

    $completed = count(array_filter($weekLog));
    respond([
        'year'      => $year,
        'week'      => $week,
        'log'       => $weekLog,
        'completed' => $completed,
        'total'     => 7,
    ]);
}

// POST — toggle a day
$body      = getJsonBody();
$date      = $body['date']      ?? date('Y-m-d');
$completed = (bool)($body['completed'] ?? true);

$dt   = new DateTime($date);
$year = (int)$dt->format('Y');
$week = (int)$dt->format('W');

$stmt = $db->prepare("
    INSERT INTO training_logs (client_id, log_date, completed, year_num, week_num)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE completed = VALUES(completed)
");
$stmt->execute([$client['id'], $date, $completed, $year, $week]);

respond(['message' => 'Log actualizado', 'date' => $date, 'completed' => $completed]);
