<?php
/**
 * Activity Feed API - Real-time event aggregation
 * Agregates client actions across multiple tables for admin dashboard
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

// Only superadmin can access
$auth = authenticateRequest();
if ($auth['user_type'] !== 'superadmin') {
    respondJson(['error' => 'Unauthorized'], 403);
}

$admin_id = $auth['user_id'];
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
$type = isset($_GET['type']) ? $_GET['type'] : null;

try {
    $pdo = getPDO();

    // Get today's date boundary
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');

    // Build base query for each event type
    $events = [];

    // 1. Check-ins
    $query_checkins = "
        SELECT
            cc.id, cc.client_id, cc.created_at as timestamp,
            'checkin' as action,
            c.first_name, c.last_name,
            SUBSTR(c.first_name, 1, 1) as initial,
            CONCAT(SUBSTR(c.first_name, 1, 1), SUBSTR(c.last_name, 1, 1)) as avatar,
            'Completed check-in' as description
        FROM client_checkins cc
        JOIN clients c ON cc.client_id = c.id
        WHERE cc.created_at >= ? AND cc.created_at <= ?
    ";
    $params = [$today_start, $today_end];

    if ($client_id) {
        $query_checkins .= " AND cc.client_id = ?";
        $params[] = $client_id;
    }

    $stmt = $pdo->prepare($query_checkins);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'action' => 'checkin',
            'client_id' => $row['client_id'],
            'client_name' => $row['first_name'] . ' ' . $row['last_name'],
            'client_avatar' => $row['avatar'],
            'description' => $row['description'],
            'timestamp' => $row['timestamp'],
        ];
    }

    // 2. Metrics (Biometric logs)
    $query_metrics = "
        SELECT
            bl.id, bl.client_id, bl.created_at as timestamp,
            'metric' as action,
            c.first_name, c.last_name,
            CONCAT(SUBSTR(c.first_name, 1, 1), SUBSTR(c.last_name, 1, 1)) as avatar,
            CONCAT('Logged ', bl.metric_type, ': ', bl.value, ' ', bl.unit) as description
        FROM biometric_logs bl
        JOIN clients c ON bl.client_id = c.id
        WHERE bl.created_at >= ? AND bl.created_at <= ?
    ";
    $params = [$today_start, $today_end];

    if ($client_id) {
        $query_metrics .= " AND bl.client_id = ?";
        $params[] = $client_id;
    }

    $stmt = $pdo->prepare($query_metrics);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'action' => 'metric',
            'client_id' => $row['client_id'],
            'client_name' => $row['first_name'] . ' ' . $row['last_name'],
            'client_avatar' => $row['avatar'],
            'description' => $row['description'],
            'timestamp' => $row['timestamp'],
        ];
    }

    // 3. Challenge Progress
    $query_challenges = "
        SELECT
            chp.id, chp.client_id, chp.updated_at as timestamp,
            'challenge' as action,
            c.first_name, c.last_name,
            CONCAT(SUBSTR(c.first_name, 1, 1), SUBSTR(c.last_name, 1, 1)) as avatar,
            CONCAT('Progreso en reto') as description
        FROM challenge_progress chp
        JOIN clients c ON chp.client_id = c.id
        WHERE chp.updated_at >= ? AND chp.updated_at <= ?
    ";
    $params = [$today_start, $today_end];

    if ($client_id) {
        $query_challenges .= " AND chp.client_id = ?";
        $params[] = $client_id;
    }

    $stmt = $pdo->prepare($query_challenges);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'action' => 'challenge',
            'client_id' => $row['client_id'],
            'client_name' => $row['first_name'] . ' ' . $row['last_name'],
            'client_avatar' => $row['avatar'],
            'description' => $row['description'],
            'timestamp' => $row['timestamp'],
        ];
    }

    // 4. Academy Progress
    $query_academy = "
        SELECT
            ap.id, ap.client_id, ap.updated_at as timestamp,
            'academy' as action,
            c.first_name, c.last_name,
            CONCAT(SUBSTR(c.first_name, 1, 1), SUBSTR(c.last_name, 1, 1)) as avatar,
            'Completó contenido de Academia' as description
        FROM academy_progress ap
        JOIN clients c ON ap.client_id = c.id
        WHERE ap.updated_at >= ? AND ap.updated_at <= ? AND ap.completed = 1
    ";
    $params = [$today_start, $today_end];

    if ($client_id) {
        $query_academy .= " AND ap.client_id = ?";
        $params[] = $client_id;
    }

    $stmt = $pdo->prepare($query_academy);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'action' => 'academy',
            'client_id' => $row['client_id'],
            'client_name' => $row['first_name'] . ' ' . $row['last_name'],
            'client_avatar' => $row['avatar'],
            'description' => $row['description'],
            'timestamp' => $row['timestamp'],
        ];
    }

    // 5. Messages to Coach
    $query_messages = "
        SELECT
            m.id, m.sender_id as client_id, m.created_at as timestamp,
            'message' as action,
            c.first_name, c.last_name,
            CONCAT(SUBSTR(c.first_name, 1, 1), SUBSTR(c.last_name, 1, 1)) as avatar,
            'Envió mensaje al coach' as description
        FROM messages m
        JOIN clients c ON m.sender_id = c.id
        WHERE m.recipient_type = 'coach' AND m.created_at >= ? AND m.created_at <= ?
    ";
    $params = [$today_start, $today_end];

    if ($client_id) {
        $query_messages .= " AND m.sender_id = ?";
        $params[] = $client_id;
    }

    $stmt = $pdo->prepare($query_messages);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'action' => 'message',
            'client_id' => $row['client_id'],
            'client_name' => $row['first_name'] . ' ' . $row['last_name'],
            'client_avatar' => $row['avatar'],
            'description' => $row['description'],
            'timestamp' => $row['timestamp'],
        ];
    }

    // Sort by timestamp (newest first)
    usort($events, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    // Limit results
    $events = array_slice($events, 0, $limit);

    // Calculate breakdown
    $breakdown = [
        'checkin' => 0,
        'metric' => 0,
        'challenge' => 0,
        'academy' => 0,
        'message' => 0,
    ];

    // Count all events today (not just limited)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM client_checkins
        WHERE created_at >= ? AND created_at <= ?
        " . ($client_id ? "AND client_id = ?" : "")
    );
    $stmt->execute($client_id ? [$today_start, $today_end, $client_id] : [$today_start, $today_end]);
    $breakdown['checkin'] = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM biometric_logs
        WHERE created_at >= ? AND created_at <= ?
        " . ($client_id ? "AND client_id = ?" : "")
    );
    $stmt->execute($client_id ? [$today_start, $today_end, $client_id] : [$today_start, $today_end]);
    $breakdown['metric'] = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM challenge_progress
        WHERE updated_at >= ? AND updated_at <= ?
        " . ($client_id ? "AND client_id = ?" : "")
    );
    $stmt->execute($client_id ? [$today_start, $today_end, $client_id] : [$today_start, $today_end]);
    $breakdown['challenge'] = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM academy_progress
        WHERE updated_at >= ? AND updated_at <= ? AND completed = 1
        " . ($client_id ? "AND client_id = ?" : "")
    );
    $stmt->execute($client_id ? [$today_start, $today_end, $client_id] : [$today_start, $today_end]);
    $breakdown['academy'] = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM messages
        WHERE recipient_type = 'coach' AND created_at >= ? AND created_at <= ?
        " . ($client_id ? "AND sender_id = ?" : "")
    );
    $stmt->execute($client_id ? [$today_start, $today_end, $client_id] : [$today_start, $today_end]);
    $breakdown['message'] = $stmt->fetch()['count'];

    $today_count = array_sum($breakdown);

    // Log activity
    if (function_exists('logActivityFeedUsage')) {
        logActivityFeedUsage($pdo, $admin_id, 'feed-view', ['client_id' => $client_id, 'type' => $type]);
    }

    respondJson([
        'today_count' => $today_count,
        'breakdown' => $breakdown,
        'events' => $events,
    ]);

} catch (Exception $e) {
    error_log('Activity Feed error: ' . $e->getMessage());
    respondJson(['error' => 'Internal error'], 500);
}
