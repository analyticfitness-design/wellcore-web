<?php
/**
 * WellCore Fitness — Renewal Reminder Cron
 * ============================================================
 * Sends renewal reminder email 3 days before subscription_end.
 * RISE clients get Form B (challenge feedback).
 * Regular clients get Form A (satisfaction + renewal choice).
 *
 * Run daily at 8am:
 *   0 8 * * * php /path/to/wellcorefitness/api/cron/renewal-reminder.php
 *
 * Safe to re-run: uses renewal_reminder_sent flag to prevent duplicates.
 * ============================================================
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode(['error' => 'CLI only']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../emails/renewal-templates.php';

// ── Formspree form URLs ──────────────────────────────────
// Replace these with your actual Formspree form URLs
define('FORM_REGULAR', 'https://formspree.io/f/PLACEHOLDER_REGULAR');
define('FORM_RISE',    'https://formspree.io/f/PLACEHOLDER_RISE');

$db = getDB();
$now = date('Y-m-d H:i:s');
$sent = 0;
$errors = 0;

echo "[{$now}] Renewal reminder cron started\n";

// Find clients whose subscription ends within 3 days and haven't been reminded
$stmt = $db->query("
    SELECT id, name, email, plan, subscription_end,
           DATEDIFF(subscription_end, CURDATE()) AS days_left
    FROM clients
    WHERE status = 'activo'
      AND email IS NOT NULL AND email != ''
      AND subscription_end IS NOT NULL
      AND subscription_end <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
      AND subscription_end >= CURDATE()
      AND renewal_reminder_sent = 0
");

$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($clients) . " clients in renewal window\n";

foreach ($clients as $client) {
    $cid      = (int)$client['id'];
    $name     = $client['name'];
    $email    = $client['email'];
    $plan     = $client['plan'] ?? 'esencial';
    $daysLeft = max(0, (int)$client['days_left']);

    if ($plan === 'rise') {
        $subject = "Tu reto RISE termina en {$daysLeft} dias — " . explode(' ', trim($name))[0];
        $html    = email_renewal_rise($name, $daysLeft, FORM_RISE, 'https://wellcorefitness.com/rise-dashboard.html');
    } else {
        $subject = "Tu plan vence en {$daysLeft} dias — " . explode(' ', trim($name))[0];
        $html    = email_renewal_regular($name, $plan, $daysLeft, FORM_REGULAR, 'https://wellcorefitness.com/cliente.html');
    }

    $result = sendEmail($email, $subject, $html);

    if ($result['ok']) {
        // Mark as sent
        $db->prepare("UPDATE clients SET renewal_reminder_sent = 1 WHERE id = ?")
           ->execute([$cid]);
        $sent++;
        echo "  [{$email}] Sent renewal reminder OK (plan={$plan}, {$daysLeft}d left)\n";
    } else {
        $errors++;
        echo "  [{$email}] FAILED: {$result['error']}\n";
    }
}

echo "\n[" . date('Y-m-d H:i:s') . "] Done. Sent: {$sent}, Errors: {$errors}\n";
