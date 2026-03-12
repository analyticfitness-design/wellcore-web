<?php
/**
 * Activity Feed API - Real-time event aggregation
 * Aggregates client actions across multiple tables for admin dashboard
 *
 * Tables queried (all safe — skips missing tables):
 *   checkins, biometric_logs, challenge_participants, coach_messages,
 *   training_logs, weight_logs, xp_events, habit_logs, progress_photos,
 *   community_posts
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cors.php';

requireMethod('GET');

// Any admin role can access the activity feed
$auth = authenticateAdmin();
$admin_id = $auth['user_id'];

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 30;
$type = isset($_GET['type']) ? $_GET['type'] : null;
$days = isset($_GET['days']) ? max(1, min((int)$_GET['days'], 30)) : 7; // 1-30 days, default 7

try {
    $db = getDB();

    $range_start = date('Y-m-d 00:00:00', strtotime("-{$days} days"));
    $range_end = date('Y-m-d 23:59:59');
    $today_start = date('Y-m-d 00:00:00');
    $today_end = $range_end;

    $events = [];

    // Helper: extract avatar initials from full name
    function avatarFromName($name) {
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        }
        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    // Helper: safe query — skips if table/column doesn't exist
    function safeQuery($db, $sql, $params) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Activity Feed query skipped: ' . $e->getMessage());
            return [];
        }
    }

    // Build client filter clause
    $clientWhere = $client_id ? " AND c.id = ?" : "";

    // ── 1. Check-ins (weekly submissions) ──────────────────────────────
    $sql = "
        SELECT ch.id, ch.client_id, ch.created_at as timestamp,
               'checkin' as action, c.name, c.plan,
               CONCAT('Check-in semanal — bienestar: ', ch.bienestar, '/10, días: ', ch.dias_entrenados) as description
        FROM checkins ch
        JOIN clients c ON ch.client_id = c.id
        WHERE ch.created_at >= ? AND ch.created_at <= ?
        {$clientWhere}
        ORDER BY ch.created_at DESC
    ";
    $params = [$range_start, $range_end];
    if ($client_id) $params[] = $client_id;
    foreach (safeQuery($db, $sql, $params) as $r) {
        $events[] = [
            'action' => 'checkin',
            'client_id' => (int)$r['client_id'],
            'client_name' => $r['name'],
            'client_avatar' => avatarFromName($r['name']),
            'client_plan' => $r['plan'] ?? '',
            'description' => $r['description'],
            'timestamp' => $r['timestamp'],
        ];
    }

    // ── 2. Biometric Logs (daily metrics) ──────────────────────────────
    $sql = "
        SELECT bl.id, bl.client_id, bl.created_at as timestamp,
               'metric' as action, c.name, c.plan,
               bl.steps, bl.sleep_hours, bl.heart_rate, bl.calories,
               bl.weight_kg, bl.body_fat_pct, bl.waist_cm, bl.energy_level
        FROM biometric_logs bl
        JOIN clients c ON bl.client_id = c.id
        WHERE bl.created_at >= ? AND bl.created_at <= ?
        {$clientWhere}
        ORDER BY bl.created_at DESC
    ";
    $params = [$range_start, $range_end];
    if ($client_id) $params[] = $client_id;
    foreach (safeQuery($db, $sql, $params) as $r) {
        $metrics = [];
        if ($r['weight_kg'])    $metrics[] = "peso: {$r['weight_kg']}kg";
        if ($r['steps'])        $metrics[] = "pasos: {$r['steps']}";
        if ($r['sleep_hours'])  $metrics[] = "sueño: {$r['sleep_hours']}h";
        if ($r['heart_rate'])   $metrics[] = "FC: {$r['heart_rate']}bpm";
        if ($r['calories'])     $metrics[] = "cal: {$r['calories']}";
        if ($r['body_fat_pct']) $metrics[] = "grasa: {$r['body_fat_pct']}%";
        if ($r['waist_cm'])     $metrics[] = "cintura: {$r['waist_cm']}cm";
        if ($r['energy_level']) $metrics[] = "energía: {$r['energy_level']}/10";
        $desc = $metrics ? 'Registró ' . implode(', ', $metrics) : 'Registró métricas biométricas';

        $events[] = [
            'action' => 'metric',
            'client_id' => (int)$r['client_id'],
            'client_name' => $r['name'],
            'client_avatar' => avatarFromName($r['name']),
            'client_plan' => $r['plan'] ?? '',
            'description' => $desc,
            'timestamp' => $r['timestamp'],
        ];
    }

    // ── 3. Challenge Participants (joined) ─────────────────────────────
    $sql = "
        SELECT cp.id, cp.client_id, cp.joined_at as timestamp,
               'challenge' as action, c.name, c.plan,
               CONCAT('Se unió al reto: ', ch.name) as description
        FROM challenge_participants cp
        JOIN clients c ON cp.client_id = c.id
        JOIN challenges ch ON cp.challenge_id = ch.id
        WHERE cp.joined_at >= ? AND cp.joined_at <= ?
        {$clientWhere}
        ORDER BY cp.joined_at DESC
    ";
    $params = [$range_start, $range_end];
    if ($client_id) $params[] = $client_id;
    foreach (safeQuery($db, $sql, $params) as $r) {
        $events[] = [
            'action' => 'challenge',
            'client_id' => (int)$r['client_id'],
            'client_name' => $r['name'],
            'client_avatar' => avatarFromName($r['name']),
            'client_plan' => $r['plan'] ?? '',
            'description' => $r['description'],
            'timestamp' => $r['timestamp'],
        ];
    }

    // Challenge completions
    $sql = "
        SELECT cp.id, cp.client_id, cp.completed_at as timestamp,
               'challenge' as action, c.name, c.plan,
               CONCAT('Completó reto: ', ch.name) as description
        FROM challenge_participants cp
        JOIN clients c ON cp.client_id = c.id
        JOIN challenges ch ON cp.challenge_id = ch.id
        WHERE cp.completed = 1 AND cp.completed_at >= ? AND cp.completed_at <= ?
        {$clientWhere}
        ORDER BY cp.completed_at DESC
    ";
    $params = [$range_start, $range_end];
    if ($client_id) $params[] = $client_id;
    foreach (safeQuery($db, $sql, $params) as $r) {
        $events[] = [
            'action' => 'challenge',
            'client_id' => (int)$r['client_id'],
            'client_name' => $r['name'],
            'client_avatar' => avatarFromName($r['name']),
            'client_plan' => $r['plan'] ?? '',
            'description' => $r['description'],
            'timestamp' => $r['timestamp'],
        ];
    }

    // ── 4. Coach Messages ──────────────────────────────────────────────
    $sql = "
        SELECT cm.id, cm.client_id, cm.created_at as timestamp,
               'message' as action, c.name, c.plan,
               CASE cm.direction
                   WHEN 'client_to_coach' THEN 'Envió mensaje al coach'
                   WHEN 'coach_to_client' THEN 'Recibió mensaje del coach'
               END as description
        FROM coach_messages cm
        JOIN clients c ON cm.client_id = c.id
        WHERE cm.created_at >= ? AND cm.created_at <= ?
        {$clientWhere}
        ORDER BY cm.created_at DESC
    ";
    $params = [$range_start, $range_end];
    if ($client_id) $params[] = $client_id;
    foreach (safeQuery($db, $sql, $params) as $r) {
        $events[] = [
            'action' => 'message',
            'client_id' => (int)$r['client_id'],
            'client_name' => $r['name'],
            'client_avatar' => avatarFromName($r['name']),
            'client_plan' => $r['plan'] ?? '',
            'description' => $r['description'],
            'timestamp' => $r['timestamp'],
        ];
    }

    // ── 5. Training Logs (daily workout completion) ────────────────────
    $sql = "
        SELECT tl.id, tl.client_id, tl.log_date as timestamp,
               'training' as action, c.name, c.plan,
               CASE WHEN tl.completed = 1
                    THEN 'Completó entrenamiento del día'
                    ELSE 'Registró día de entrenamiento'
               END as description
        FROM training_logs tl
        JOIN clients c ON tl.client_id = c.id
        WHERE tl.log_date = CURDATE()
        {$clientWhere}
        ORDER BY tl.log_date DESC
    ";
    $params = [];
    if ($client_id) $params[] = $client_id;
    foreach (safeQuery($db, $sql, $params) as $r) {
        $events[] = [
            'action' => 'training',
            'client_id' => (int)$r['client_id'],
            'client_name' => $r['name'],
            'client_avatar' => avatarFromName($r['name']),
            'client_plan' => $r['plan'] ?? '',
            'description' => $r['description'],
            'timestamp' => $r['timestamp'],
        ];
    }

    // ── 6. Weight Logs (strength entries — client_id is client_code VARCHAR) ──
    $clientWhereWL = $client_id ? " AND c.id = ?" : "";
    $sql = "
        SELECT wl.id, c.id as client_id, wl.created_at as timestamp,
               'weight' as action, c.name, c.plan,
               CONCAT(wl.exercise, ': ', wl.weight_kg, 'kg × ', wl.`sets`, '×', wl.reps) as description
        FROM weight_logs wl
        JOIN clients c ON wl.client_id = c.client_code
        WHERE wl.created_at >= ? AND wl.created_at <= ?
        {$clientWhereWL}
        ORDER BY wl.created_at DESC
    ";
    $params = [$range_start, $range_end];
    if ($client_id) $params[] = $client_id;
    foreach (safeQuery($db, $sql, $params) as $r) {
        $events[] = [
            'action' => 'weight',
            'client_id' => (int)$r['client_id'],
            'client_name' => $r['name'],
            'client_avatar' => avatarFromName($r['name']),
            'client_plan' => $r['plan'] ?? '',
            'description' => $r['description'],
            'timestamp' => $r['timestamp'],
        ];
    }

    // ── 7. XP Events (gamification) ────────────────────────────────────
    $sql = "
        SELECT xe.id, xe.client_id, xe.created_at as timestamp,
               'xp' as action, c.name, c.plan,
               CONCAT('+', xe.xp_gained, ' XP — ', xe.event_type) as description
        FROM xp_events xe
        JOIN clients c ON xe.client_id = c.id
        WHERE xe.created_at >= ? AND xe.created_at <= ?
        {$clientWhere}
        ORDER BY xe.created_at DESC
    ";
    $params = [$range_start, $range_end];
    if ($client_id) $params[] = $client_id;
    foreach (safeQuery($db, $sql, $params) as $r) {
        $events[] = [
            'action' => 'xp',
            'client_id' => (int)$r['client_id'],
            'client_name' => $r['name'],
            'client_avatar' => avatarFromName($r['name']),
            'client_plan' => $r['plan'] ?? '',
            'description' => $r['description'],
            'timestamp' => $r['timestamp'],
        ];
    }

    // ── 8. Habit Logs ──────────────────────────────────────────────────
    $sql = "
        SELECT hl.id, hl.client_id, hl.created_at as timestamp,
               'habit' as action, c.name, c.plan,
               CONCAT('Hábito: ', hl.habit_type, ' completado') as description
        FROM habit_logs hl
        JOIN clients c ON hl.client_id = c.id
        WHERE hl.log_date = CURDATE()
        {$clientWhere}
        ORDER BY hl.created_at DESC
    ";
    $params = [];
    if ($client_id) $params[] = $client_id;
    foreach (safeQuery($db, $sql, $params) as $r) {
        $events[] = [
            'action' => 'habit',
            'client_id' => (int)$r['client_id'],
            'client_name' => $r['name'],
            'client_avatar' => avatarFromName($r['name']),
            'client_plan' => $r['plan'] ?? '',
            'description' => $r['description'],
            'timestamp' => $r['timestamp'],
        ];
    }

    // ── 9. Progress Photos ─────────────────────────────────────────────
    $sql = "
        SELECT pp.id, pp.client_id, pp.created_at as timestamp,
               'photo' as action, c.name, c.plan,
               'Subió foto de progreso' as description
        FROM progress_photos pp
        JOIN clients c ON pp.client_id = c.id
        WHERE pp.created_at >= ? AND pp.created_at <= ?
        {$clientWhere}
        ORDER BY pp.created_at DESC
    ";
    $params = [$range_start, $range_end];
    if ($client_id) $params[] = $client_id;
    foreach (safeQuery($db, $sql, $params) as $r) {
        $events[] = [
            'action' => 'photo',
            'client_id' => (int)$r['client_id'],
            'client_name' => $r['name'],
            'client_avatar' => avatarFromName($r['name']),
            'client_plan' => $r['plan'] ?? '',
            'description' => $r['description'],
            'timestamp' => $r['timestamp'],
        ];
    }

    // ── 10. Community Posts ────────────────────────────────────────────
    $sql = "
        SELECT cp2.id, cp2.client_id, cp2.created_at as timestamp,
               'community' as action, c.name, c.plan,
               'Publicó en la comunidad' as description
        FROM community_posts cp2
        JOIN clients c ON cp2.client_id = c.id
        WHERE cp2.created_at >= ? AND cp2.created_at <= ?
        {$clientWhere}
        ORDER BY cp2.created_at DESC
    ";
    $params = [$range_start, $range_end];
    if ($client_id) $params[] = $client_id;
    foreach (safeQuery($db, $sql, $params) as $r) {
        $events[] = [
            'action' => 'community',
            'client_id' => (int)$r['client_id'],
            'client_name' => $r['name'],
            'client_avatar' => avatarFromName($r['name']),
            'client_plan' => $r['plan'] ?? '',
            'description' => $r['description'],
            'timestamp' => $r['timestamp'],
        ];
    }

    // ── Filter by type if requested ────────────────────────────────────
    if ($type) {
        $events = array_values(array_filter($events, fn($e) => $e['action'] === $type));
    }

    // ── Sort by timestamp (newest first) ───────────────────────────────
    usort($events, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    // ── Build breakdown counts (before limit) ─────────────────────────
    $actionTypes = ['checkin','metric','challenge','message','training','weight','xp','habit','photo','community'];
    $breakdown = [];
    foreach ($actionTypes as $at) {
        $breakdown[$at] = count(array_filter($events, fn($e) => $e['action'] === $at));
    }
    $range_count = array_sum($breakdown);

    // Today-only count
    $today_count = count(array_filter($events, fn($e) => $e['timestamp'] >= $today_start));

    // ── Limit results ──────────────────────────────────────────────────
    $events = array_slice($events, 0, $limit);

    // ── Log admin usage ────────────────────────────────────────────────
    @include_once __DIR__ . '/../includes/activity-log-helper.php';
    if (function_exists('logActivityFeedUsage')) {
        logActivityFeedUsage($db, $admin_id, 'feed-view', ['client_id' => $client_id, 'type' => $type]);
    }

    respond([
        'today_count' => $today_count,
        'range_count' => $range_count,
        'days' => $days,
        'breakdown' => $breakdown,
        'events' => $events,
    ]);

} catch (Exception $e) {
    error_log('Activity Feed error: ' . $e->getMessage());
    respond(['error' => 'Internal error'], 500);
}
