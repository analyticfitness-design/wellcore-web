<?php
/**
 * WellCore Fitness — Engagement Emails Cron
 * ============================================================
 * Sends 2 mid-cycle emails to active clients:
 *   Day 10: "Tu Progreso Importa" — motivation + actionable tips
 *   Day 20: "Sigue Asi, Estamos Contigo" — value-add + final stretch
 *
 * Run daily at 9am:
 *   0 9 * * * php /path/to/wellcorefitness/api/cron/engagement-emails.php
 *
 * Safe to re-run: uses UNIQUE KEY in engagement_emails to prevent duplicates.
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../emails/engagement-templates.php';

$db = getDB();
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');
$cycleMonth = date('Y-m-01'); // first day of current month for grouping
$sent = 0;
$errors = 0;

echo "[{$now}] Engagement emails cron started\n";

// ─── Fetch all active clients with a start date ─────────────
// Regular clients use fecha_inicio, RISE clients use rise_programs.created_at
$stmt = $db->query("
    SELECT
        c.id,
        c.name,
        c.email,
        c.plan,
        COALESCE(c.fecha_inicio, rp.created_at, c.created_at) AS start_date
    FROM clients c
    LEFT JOIN rise_programs rp
        ON rp.client_id = c.id AND rp.status = 'active'
    WHERE c.status = 'activo'
      AND c.email IS NOT NULL
      AND c.email != ''
    GROUP BY c.id
");

$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($clients) . " active clients\n";

foreach ($clients as $client) {
    $cid   = (int)$client['id'];
    $name  = $client['name'];
    $email = $client['email'];
    $plan  = $client['plan'] ?? 'esencial';
    $start = $client['start_date'];

    if (!$start) continue;

    // Calculate day in current cycle (modulo 30 for recurring cycles)
    $daysSinceStart = (int)((strtotime($today) - strtotime($start)) / 86400);
    if ($daysSinceStart < 0) continue;

    $dayInCycle = $daysSinceStart % 30;

    // Determine which email to send (if any)
    $emailType = null;
    if ($dayInCycle === 10) {
        $emailType = 'day10_motivation';
    } elseif ($dayInCycle === 20) {
        $emailType = 'day20_value';
    }

    if (!$emailType) continue;

    // Check if already sent this cycle
    $check = $db->prepare("
        SELECT id FROM engagement_emails
        WHERE client_id = ? AND email_type = ? AND cycle_month = ?
    ");
    $check->execute([$cid, $emailType, $cycleMonth]);
    if ($check->fetchColumn()) {
        echo "  [{$email}] Already sent {$emailType} this month, skipping\n";
        continue;
    }

    // Build dashboard URL based on plan
    $dashboardUrl = ($plan === 'rise')
        ? 'https://wellcorefitness.com/rise-dashboard.html'
        : 'https://wellcorefitness.com/cliente.html';

    // Generate email HTML
    if ($emailType === 'day10_motivation') {
        $subject = "Tu progreso importa, " . explode(' ', trim($name))[0] . " — WellCore Fitness";
        $html = email_engagement_day10($name, $plan, $dashboardUrl);
    } else {
        $subject = "Sigue asi, " . explode(' ', trim($name))[0] . " — WellCore Fitness";
        $html = email_engagement_day20($name, $plan, $dashboardUrl);
    }

    // Send via Mailjet
    $result = sendEmail($email, $subject, $html);

    if ($result['ok']) {
        // Log to prevent duplicates
        $ins = $db->prepare("
            INSERT IGNORE INTO engagement_emails (client_id, email_type, cycle_month)
            VALUES (?, ?, ?)
        ");
        $ins->execute([$cid, $emailType, $cycleMonth]);
        $sent++;
        echo "  [{$email}] Sent {$emailType} OK\n";
    } else {
        $errors++;
        echo "  [{$email}] FAILED {$emailType}: {$result['error']}\n";
    }
}

echo "\n[" . date('Y-m-d H:i:s') . "] Done. Sent: {$sent}, Errors: {$errors}\n";
