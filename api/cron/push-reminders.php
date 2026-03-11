<?php
/**
 * WellCore — Push Reminder Cron (M38)
 * Sends nightly push notifications for habit tracking and weekly check-ins.
 *
 * Run daily at 9pm: 0 21 * * * php /app/api/cron/push-reminders.php
 *
 * Triggers:
 *   1. Habit reminder (every day, 9pm):
 *      Clients with active push subscriptions who haven't completed all 4 habits today.
 *
 *   2. Weekly check-in reminder (Fridays only):
 *      Clients with active push subscriptions who have no check-in in the last 7 days.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/web-push.php';

$db      = getDB();
$today   = date('Y-m-d');
$isFriday = (date('N') === '5');  // ISO weekday: 5 = Friday

$habitsSent  = 0;
$checkinSent = 0;
$errors      = 0;

echo "[" . date('Y-m-d H:i:s') . "] Push reminders cron started (Friday: " . ($isFriday ? 'yes' : 'no') . ")\n";

// ── 1. Habit reminder — all days ──────────────────────────────────────────
//
// Find clients with at least one active push subscription who have NOT
// logged all 4 habit categories today (agua, sueno, nutricion, estres).
// We look at the daily_habits table if it exists; fall back to a simple
// "has no habit log at all today" check that avoids breaking if the table
// doesn't exist yet.

try {
    // Clients with active subscriptions
    $subsStmt = $db->query("
        SELECT DISTINCT ps.client_id
        FROM push_subscriptions ps
        JOIN clients c ON c.id = ps.client_id
        WHERE ps.is_active = 1
          AND c.status = 'activo'
    ");
    $subscribedClients = $subsStmt->fetchAll(PDO::FETCH_COLUMN);

    // Check if daily_habits table exists
    $habitsTableExists = false;
    try {
        $db->query("SELECT 1 FROM daily_habits LIMIT 0");
        $habitsTableExists = true;
    } catch (\PDOException $e) {
        // Table doesn't exist yet — use fallback
    }

    foreach ($subscribedClients as $clientId) {
        $cid = (int)$clientId;

        if ($habitsTableExists) {
            // Count distinct habit types logged today (agua, sueno, nutricion, estres = 4 expected)
            $hStmt = $db->prepare("
                SELECT COUNT(DISTINCT habit_type)
                FROM daily_habits
                WHERE client_id = ? AND log_date = ?
            ");
            $hStmt->execute([$cid, $today]);
            $completedCount = (int)$hStmt->fetchColumn();
            $habitsComplete = ($completedCount >= 4);
        } else {
            // Fallback: check biometric_logs as a proxy (logged any today)
            $hStmt = $db->prepare("
                SELECT COUNT(*) FROM biometric_logs
                WHERE client_id = ? AND log_date = ?
            ");
            $hStmt->execute([$cid, $today]);
            $habitsComplete = ((int)$hStmt->fetchColumn() > 0);
        }

        if (!$habitsComplete) {
            $n = webpush_send_to_client(
                $db,
                $cid,
                '🌙 ¿Completaste tus hábitos hoy?',
                'Registra agua, sueño, nutrición y estrés antes de las 12.',
                '/cliente.html#habitos'
            );
            $habitsSent += $n;
            if ($n === 0) {
                $errors++;
            }
            echo "  [habitos] client_id={$cid} — sent={$n}\n";
        }
    }
} catch (\Throwable $e) {
    echo "  [ERROR] Habit reminder loop failed: " . $e->getMessage() . "\n";
    $errors++;
}

// ── 2. Weekly check-in reminder — Fridays only ────────────────────────────

if ($isFriday) {
    try {
        // Clients with active subscriptions who have no check-in in the last 7 days
        $checkinStmt = $db->query("
            SELECT DISTINCT ps.client_id
            FROM push_subscriptions ps
            JOIN clients c ON c.id = ps.client_id
            LEFT JOIN checkins ch
              ON ch.client_id = ps.client_id
             AND ch.checkin_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            WHERE ps.is_active = 1
              AND c.status = 'activo'
              AND ch.id IS NULL
        ");
        $noCheckinClients = $checkinStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($noCheckinClients as $clientId) {
            $cid = (int)$clientId;
            $n   = webpush_send_to_client(
                $db,
                $cid,
                '📋 Check-in semanal pendiente',
                'Tómate 2 minutos para registrar cómo va tu semana.',
                '/cliente.html#checkin'
            );
            $checkinSent += $n;
            if ($n === 0) {
                $errors++;
            }
            echo "  [checkin] client_id={$cid} — sent={$n}\n";
        }
    } catch (\Throwable $e) {
        echo "  [ERROR] Check-in reminder loop failed: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n[" . date('Y-m-d H:i:s') . "] Push reminders done."
   . " Habits sent: {$habitsSent}, Check-in sent: {$checkinSent}, Errors: {$errors}\n";
