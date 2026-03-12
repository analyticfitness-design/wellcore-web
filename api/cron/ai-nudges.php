<?php
/**
 * WellCore — AI Nudges Cron
 * Sends proactive push notifications based on client data patterns.
 * Runs 2x/day: 0 9,21 * * * php /code/api/cron/ai-nudges.php
 *
 * Triggers:
 * 1. No training in 3 days but has streak → "Don't break your streak"
 * 2. Weight up 2+ kg in a week → "Could be retention, check hydration"
 * 3. Bienestar ≤5 for 2 weeks → "Want to talk to your coach?"
 * 4. 100% habits yesterday → "Perfect day, repeat it!"
 * 5. No app activity in 5 days → "We miss you"
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/web-push.php';

$db    = getDB();
$today = date('Y-m-d');
$sent  = 0;

echo "[" . date('Y-m-d H:i:s') . "] AI Nudges cron started\n";

// Helper: check if nudge was sent today
function nudgeSentToday(PDO $db, int $clientId, string $nudgeType): bool {
    $stmt = $db->prepare("
        SELECT id FROM auto_message_log
        WHERE client_id = ? AND trigger_type = ? AND date_sent = CURDATE()
    ");
    $stmt->execute([$clientId, 'nudge_' . $nudgeType]);
    return (bool)$stmt->fetchColumn();
}

function logNudge(PDO $db, int $clientId, string $nudgeType): void {
    $db->prepare("
        INSERT IGNORE INTO auto_message_log (client_id, trigger_type, channel, date_sent)
        VALUES (?, ?, 'push', CURDATE())
    ")->execute([$clientId, 'nudge_' . $nudgeType]);
}

// Get active clients with relevant data
$clients = $db->query("
    SELECT c.id, c.name, c.plan
    FROM clients c
    WHERE c.status = 'activo'
")->fetchAll(PDO::FETCH_ASSOC);

echo "Processing " . count($clients) . " active clients\n";

foreach ($clients as $c) {
    $cid = (int)$c['id'];
    $fn  = explode(' ', trim($c['name']))[0];

    // ── Trigger 1: Streak at risk ─────────────────────────
    try {
        $stmt = $db->prepare("SELECT current_streak FROM client_xp WHERE client_id = ?");
        $stmt->execute([$cid]);
        $streak = (int)($stmt->fetchColumn() ?: 0);

        if ($streak >= 3) {
            // Check last training activity (habits or tracking)
            $stmt = $db->prepare("
                SELECT MAX(habit_date) FROM daily_habits
                WHERE client_id = ? AND completed = 1
            ");
            $stmt->execute([$cid]);
            $lastActive = $stmt->fetchColumn();
            $daysSinceActive = $lastActive ? (int)((strtotime($today) - strtotime($lastActive)) / 86400) : 999;

            if ($daysSinceActive >= 3 && !nudgeSentToday($db, $cid, 'streak_risk')) {
                $pushSent = webpush_send_to_client($db, $cid, "Oye $fn, llevas $streak dias de racha", "No la pierdas hoy! Solo necesitas completar tus habitos.", '/cliente.html#habitos');
                if ($pushSent) { logNudge($db, $cid, 'streak_risk'); $sent++; echo "  [OK] $fn — streak_risk\n"; }
            }
        }
    } catch (\Throwable $e) {}

    // ── Trigger 2: Weight spike ───────────────────────────
    try {
        $stmt = $db->prepare("
            SELECT weight_kg, log_date FROM biometric_logs
            WHERE client_id = ? AND weight_kg IS NOT NULL
            ORDER BY log_date DESC LIMIT 2
        ");
        $stmt->execute([$cid]);
        $weights = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($weights) >= 2) {
            $diff = (float)$weights[0]['weight_kg'] - (float)$weights[1]['weight_kg'];
            $daysBetween = (int)((strtotime($weights[0]['log_date']) - strtotime($weights[1]['log_date'])) / 86400);
            if ($diff >= 2 && $daysBetween <= 10 && !nudgeSentToday($db, $cid, 'weight_spike')) {
                webpush_send_to_client($db, $cid, "$fn, tu peso subio esta semana", "Puede ser retencion de liquidos. Como va tu hidratacion?", '/cliente.html#metricas');
                logNudge($db, $cid, 'weight_spike'); $sent++; echo "  [OK] $fn — weight_spike\n";
            }
        }
    } catch (\Throwable $e) {}

    // ── Trigger 3: Low bienestar 2 weeks ──────────────────
    try {
        $stmt = $db->prepare("
            SELECT bienestar FROM checkins
            WHERE client_id = ? ORDER BY checkin_date DESC LIMIT 2
        ");
        $stmt->execute([$cid]);
        $checkins = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($checkins) >= 2 && $checkins[0] <= 5 && $checkins[1] <= 5 && !nudgeSentToday($db, $cid, 'low_bienestar')) {
            webpush_send_to_client($db, $cid, "$fn, tu bienestar lleva 2 semanas bajo", "Quieres hablar con tu coach? Estamos para ayudarte.", '/cliente.html#chat');
            logNudge($db, $cid, 'low_bienestar'); $sent++; echo "  [OK] $fn — low_bienestar\n";
        }
    } catch (\Throwable $e) {}

    // ── Trigger 4: Perfect habits yesterday ───────────────
    try {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $stmt = $db->prepare("
            SELECT COUNT(*) AS total, SUM(completed) AS done
            FROM daily_habits WHERE client_id = ? AND habit_date = ?
        ");
        $stmt->execute([$cid, $yesterday]);
        $habits = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($habits && (int)$habits['total'] > 0 && (int)$habits['done'] === (int)$habits['total'] && !nudgeSentToday($db, $cid, 'perfect_day')) {
            webpush_send_to_client($db, $cid, "Ayer fue un dia perfecto, $fn!", "Todos tus habitos completados. Repitamoslo hoy!", '/cliente.html#habitos');
            logNudge($db, $cid, 'perfect_day'); $sent++; echo "  [OK] $fn — perfect_day\n";
        }
    } catch (\Throwable $e) {}

    // ── Trigger 5: No activity 5+ days ────────────────────
    try {
        $stmt = $db->prepare("
            SELECT MAX(t.last_date) FROM (
                SELECT MAX(habit_date) AS last_date FROM daily_habits WHERE client_id = ? AND completed = 1
                UNION ALL
                SELECT MAX(checkin_date) FROM checkins WHERE client_id = ?
            ) t
        ");
        $stmt->execute([$cid, $cid]);
        $lastAny = $stmt->fetchColumn();
        $daysSince = $lastAny ? (int)((strtotime($today) - strtotime($lastAny)) / 86400) : 999;

        if ($daysSince >= 5 && $daysSince < 15 && !nudgeSentToday($db, $cid, 'inactive_5d')) {
            webpush_send_to_client($db, $cid, "Te echamos de menos, $fn", "Tu plan te espera. Vuelve cuando quieras, aqui seguimos.", '/cliente.html');
            logNudge($db, $cid, 'inactive_5d'); $sent++; echo "  [OK] $fn — inactive_5d\n";
        }
    } catch (\Throwable $e) {}
}

echo "\n[" . date('Y-m-d H:i:s') . "] AI Nudges done. Sent: $sent\n";
