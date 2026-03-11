<?php
/**
 * WellCore — Behavioral Triggers Cron
 * Detecta comportamientos de clientes y envía emails personalizados.
 * Run daily at 8am: 0 8 * * * php /app/api/cron/behavioral-triggers.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../emails/behavioral-templates.php';
require_once __DIR__ . '/../includes/web-push.php';

$db    = getDB();
$today = date('Y-m-d');
$sent  = 0;
$errors = 0;

echo "[" . date('Y-m-d H:i:s') . "] Behavioral triggers cron started\n";

// Helper: verificar si ya se envió este trigger hoy al cliente
function wasSentToday(PDO $db, int $clientId, string $triggerType): bool {
    $stmt = $db->prepare("
        SELECT id FROM auto_message_log
        WHERE client_id = ? AND trigger_type = ? AND date_sent = CURDATE()
    ");
    $stmt->execute([$clientId, $triggerType]);
    return (bool)$stmt->fetchColumn();
}

// Helper: verificar si este trigger fue enviado alguna vez al cliente (para milestones)
function wasSentEver(PDO $db, int $clientId, string $triggerType): bool {
    $stmt = $db->prepare("SELECT id FROM auto_message_log WHERE client_id = ? AND trigger_type = ?");
    $stmt->execute([$clientId, $triggerType]);
    return (bool)$stmt->fetchColumn();
}

// Helper: registrar trigger enviado
function logTrigger(PDO $db, int $clientId, string $triggerType): void {
    $stmt = $db->prepare("
        INSERT IGNORE INTO auto_message_log (client_id, trigger_type, channel, date_sent)
        VALUES (?, ?, 'email', CURDATE())
    ");
    $stmt->execute([$clientId, $triggerType]);
}

// Helper: enviar email y loggear
function sendTriggerEmail(PDO $db, string $email, string $subject, string $html, int $clientId, string $triggerType, int &$sent, int &$errors): void {
    $result = sendEmail($email, $subject, $html);
    if ($result['ok']) {
        logTrigger($db, $clientId, $triggerType);
        $sent++;
        echo "  [OK] $email — $triggerType\n";
    } else {
        $errors++;
        echo "  [FAIL] $email — $triggerType: " . ($result['error'] ?? 'unknown') . "\n";
    }
}

function getCoachEmail(PDO $db, int $clientId): ?string {
    $stmt = $db->prepare("
        SELECT u.email FROM clients c
        JOIN users u ON u.id = c.coach_id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$clientId]);
    return $stmt->fetchColumn() ?: null;
}

function getSuperadminEmail(PDO $db): ?string {
    $stmt = $db->query("SELECT email FROM users WHERE role = 'superadmin' LIMIT 1");
    return $stmt->fetchColumn() ?: null;
}

// Query principal: clientes activos con datos necesarios
// subscription_end se deriva de fecha_inicio + 30 días (ciclo mensual estándar)
$clients = $db->query("
    SELECT
        c.id,
        c.name,
        c.email,
        c.plan,
        c.birth_date,
        c.created_at,
        DATE_ADD(COALESCE(c.fecha_inicio, c.created_at), INTERVAL 30 DAY) AS subscription_end,
        MAX(ch.checkin_date) AS last_checkin,
        COUNT(ch.id)         AS total_checkins
    FROM clients c
    LEFT JOIN checkins ch ON ch.client_id = c.id
    WHERE c.status = 'activo'
      AND c.email IS NOT NULL
      AND c.email != ''
    GROUP BY c.id
")->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($clients) . " active clients\n";

$dashUrl = 'https://wellcorefitness.com/cliente.html';

foreach ($clients as $c) {
    $cid   = (int)$c['id'];
    $email = $c['email'];
    $plan  = $c['plan'] ?? 'esencial';

    // Calcular metricas
    $daysSinceCheckin = $c['last_checkin']
        ? (int)(( strtotime($today) - strtotime($c['last_checkin']) ) / 86400)
        : 9999;

    $daysToExpiry = ($c['subscription_end'])
        ? (int)(( strtotime($c['subscription_end']) - strtotime($today) ) / 86400)
        : 9999;

    $daysSinceJoin = (int)(( strtotime($today) - strtotime($c['created_at']) ) / 86400);
    $totalCheckins = (int)$c['total_checkins'];

    // ── inactive_7d ──────────────────────────────
    if ($daysSinceCheckin >= 7 && $daysSinceCheckin < 14 && !wasSentEver($db, $cid, 'inactive_7d')) {
        $html = email_inactive_7d($c['name'], $plan, $dashUrl);
        $fn   = explode(' ', trim($c['name']))[0];
        sendTriggerEmail($db, $email, "Te extrañamos, $fn — ¿Todo bien? 💪", $html, $cid, 'inactive_7d', $sent, $errors);
    }

    // ── inactive_14d ─────────────────────────────
    if ($daysSinceCheckin >= 14 && $daysSinceCheckin < 30 && !wasSentEver($db, $cid, 'inactive_14d')) {
        $html = email_inactive_14d($c['name'], $plan, $dashUrl);
        $fn   = explode(' ', trim($c['name']))[0];
        sendTriggerEmail($db, $email, "Llevamos 14 días sin saber de ti, $fn", $html, $cid, 'inactive_14d', $sent, $errors);
    }

    // ── subscription_7d ──────────────────────────
    if ($daysToExpiry >= 5 && $daysToExpiry <= 8 && !wasSentEver($db, $cid, 'subscription_7d')) {
        $html = email_renewal_reminder($c['name'], $plan, $c['subscription_end'], $dashUrl, 7);
        sendTriggerEmail($db, $email, "Tu plan WellCore vence en 7 días — Renueva sin perder tu progreso", $html, $cid, 'subscription_7d', $sent, $errors);
    }

    // ── subscription_3d ──────────────────────────
    if ($daysToExpiry >= 2 && $daysToExpiry <= 4 && !wasSentEver($db, $cid, 'subscription_3d')) {
        $html = email_renewal_reminder($c['name'], $plan, $c['subscription_end'], $dashUrl, 3);
        sendTriggerEmail($db, $email, "⏰ 3 días para que venza tu plan — Actúa ahora", $html, $cid, 'subscription_3d', $sent, $errors);
    }

    // ── milestone_4 ──────────────────────────────
    if ($totalCheckins === 4 && !wasSentEver($db, $cid, 'milestone_4')) {
        $html = email_streak_milestone($c['name'], $plan, 4, $dashUrl);
        $fn   = explode(' ', trim($c['name']))[0];
        sendTriggerEmail($db, $email, "🔥 4 check-ins completados — Eso es disciplina real, $fn", $html, $cid, 'milestone_4', $sent, $errors);
    }

    // ── milestone_7 ──────────────────────────────
    if ($totalCheckins === 7 && !wasSentEver($db, $cid, 'milestone_7')) {
        $html = email_streak_milestone($c['name'], $plan, 7, $dashUrl);
        $fn   = explode(' ', trim($c['name']))[0];
        sendTriggerEmail($db, $email, "🏆 7 check-ins completados — Eres imparable, $fn", $html, $cid, 'milestone_7', $sent, $errors);
    }

    // ── birthday ─────────────────────────────────
    if ($c['birth_date']) {
        $birthMMDD = date('m-d', strtotime($c['birth_date']));
        $todayMMDD = date('m-d');
        if ($birthMMDD === $todayMMDD && !wasSentToday($db, $cid, 'birthday')) {
            $fn   = explode(' ', trim($c['name']))[0];
            $html = email_birthday($c['name'], $plan, $dashUrl);
            sendTriggerEmail($db, $email, "¡Feliz cumpleaños, $fn! 🎂 — WellCore Fitness", $html, $cid, 'birthday', $sent, $errors);
        }
    }

    // ── welcome_day1 ─────────────────────────────
    if ($daysSinceJoin >= 1 && $daysSinceJoin <= 3 && !wasSentToday($db, $cid, 'welcome_day1')) {
        $html = email_welcome_day1($c['name'], $plan, $dashUrl);
        $fn   = explode(' ', trim($c['name']))[0];
        sendTriggerEmail($db, $email, "Tu primer día en WellCore — Aquí empieza todo 🚀", $html, $cid, 'welcome_day1', $sent, $errors);
    }

    // ── first_pr ─────────────────────────────────
    // Requires joining personal_records table
    $prCount = 0;
    $prStmt = $db->prepare("SELECT COUNT(*) FROM personal_records WHERE client_id = ?");
    $prStmt->execute([$cid]);
    $prCount = (int)$prStmt->fetchColumn();
    if ($prCount >= 1 && !wasSentEver($db, $cid, 'first_pr')) {
        $fn   = explode(' ', trim($c['name']))[0];
        $html = email_first_pr($c['name'], $plan, $dashUrl);
        sendTriggerEmail($db, $email, "🏆 Primer PR registrado, $fn — ¡Eso es progreso real!", $html, $cid, 'first_pr', $sent, $errors);
    }

    // ── low_bienestar ─────────────────────────────
    // Last check-in had bienestar < 5 — alert coach via email
    $lastCheckin = null;
    $lcStmt = $db->prepare("SELECT bienestar, checkin_date FROM checkins WHERE client_id = ? ORDER BY checkin_date DESC LIMIT 1");
    $lcStmt->execute([$cid]);
    $lastCheckin = $lcStmt->fetch();
    if ($lastCheckin && (int)$lastCheckin['bienestar'] <= 4 && !wasSentToday($db, $cid, 'low_bienestar')) {
        $html = email_low_bienestar_coach($c['name'], $plan, (int)$lastCheckin['bienestar'], $lastCheckin['checkin_date']);
        // Send to coach, not client
        $coachEmail = getCoachEmail($db, $cid);
        if ($coachEmail) {
            $fn = explode(' ', trim($c['name']))[0];
            sendTriggerEmail($db, $coachEmail, "⚠️ Alerta: $fn reportó bienestar bajo (" . $lastCheckin['bienestar'] . "/10)", $html, $cid, 'low_bienestar', $sent, $errors);
        }
        // Push to client: let them know coach was notified
        webpush_send_to_client($db, $cid, '💬 Coach notificado', '¡Tu coach fue alertado sobre tu bienestar de hoy. Pronto recibirás apoyo!', '/cliente.html#checkin');
    }

    // ── subscription_1d ──────────────────────────
    if ($daysToExpiry >= 0 && $daysToExpiry <= 1 && !wasSentToday($db, $cid, 'subscription_1d')) {
        $html = email_renewal_reminder($c['name'], $plan, $c['subscription_end'], $dashUrl, 1);
        $fn = explode(' ', trim($c['name']))[0];
        sendTriggerEmail($db, $email, "🚨 Tu plan WellCore vence HOY, $fn — Renueva ahora", $html, $cid, 'subscription_1d', $sent, $errors);
    }

    // ── inactive_30d ─────────────────────────────
    if ($daysSinceCheckin >= 30 && !wasSentEver($db, $cid, 'inactive_30d')) {
        $fn   = explode(' ', trim($c['name']))[0];
        $html = email_inactive_30d($c['name'], $plan, $dashUrl);
        sendTriggerEmail($db, $email, "Llevamos un mes sin saber de ti, $fn — ¿Regresamos juntos? 💪", $html, $cid, 'inactive_30d', $sent, $errors);
    }

    // ── milestone_12 ─────────────────────────────
    if ($totalCheckins === 12 && !wasSentEver($db, $cid, 'milestone_12')) {
        $fn   = explode(' ', trim($c['name']))[0];
        $html = email_streak_milestone($c['name'], $plan, 12, $dashUrl);
        sendTriggerEmail($db, $email, "🎯 12 check-ins completados, $fn — 3 meses de consistencia", $html, $cid, 'milestone_12', $sent, $errors);
    }

    // ── milestone_24 ─────────────────────────────
    if ($totalCheckins === 24 && !wasSentEver($db, $cid, 'milestone_24')) {
        $fn   = explode(' ', trim($c['name']))[0];
        $html = email_streak_milestone($c['name'], $plan, 24, $dashUrl);
        sendTriggerEmail($db, $email, "🏅 6 meses de check-ins, $fn — Eres parte del 1% más consistente", $html, $cid, 'milestone_24', $sent, $errors);
    }

    // ── first_checkin_month ───────────────────────
    // First check-in of the current calendar month
    $monthStart = date('Y-m-01');
    $monthEnd   = date('Y-m-t');
    $mStmt = $db->prepare("SELECT COUNT(*) FROM checkins WHERE client_id = ? AND checkin_date BETWEEN ? AND ?");
    $mStmt->execute([$cid, $monthStart, $monthEnd]);
    $monthCheckins = (int)$mStmt->fetchColumn();
    if ($monthCheckins === 1 && !wasSentToday($db, $cid, 'first_checkin_month')) {
        $fn = explode(' ', trim($c['name']))[0];
        $html = email_first_checkin_month($c['name'], $plan, $dashUrl);
        sendTriggerEmail($db, $email, "🚀 Arranque del mes perfecto, $fn — Así se hace", $html, $cid, 'first_checkin_month', $sent, $errors);
    }

    // ── coach_no_reply_48h ────────────────────────
    // Check-in sent >48h ago with no coach reply — alert superadmin
    $unanswered = null;
    $uaStmt = $db->prepare("
        SELECT id, checkin_date FROM checkins
        WHERE client_id = ? AND coach_reply IS NULL
          AND checkin_date <= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY checkin_date DESC LIMIT 1
    ");
    $uaStmt->execute([$cid]);
    $unanswered = $uaStmt->fetch();
    if ($unanswered && !wasSentToday($db, $cid, 'coach_no_reply_48h')) {
        $adminEmail = getSuperadminEmail($db);
        if ($adminEmail) {
            $html = email_coach_no_reply($c['name'], $plan, $unanswered['checkin_date']);
            sendTriggerEmail($db, $adminEmail, "⚠️ Sin respuesta al check-in de {$c['name']} (>{$unanswered['checkin_date']})", $html, $cid, 'coach_no_reply_48h', $sent, $errors);
        }
    }
}

echo "\n[" . date('Y-m-d H:i:s') . "] Behavioral triggers done. Sent: {$sent}, Errors: {$errors}\n";
