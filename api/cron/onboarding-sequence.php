<?php
/**
 * WellCore — Onboarding Sequence Cron
 * Runs daily. Processes onboarding steps for clients in their first 14 days.
 * 0 9 * * * php /code/api/cron/onboarding-sequence.php >> /var/log/cron.log 2>&1
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/web-push.php';

$db    = getDB();
$today = date('Y-m-d');
$sent  = 0;
$errors = 0;

echo "[" . date('Y-m-d H:i:s') . "] Onboarding sequence cron started\n";

// Onboarding steps definition: day => [step_key, channel, action]
$steps = [
    0  => ['welcome',       'push',  'Bienvenido a WellCore — Tu transformacion empieza ahora'],
    1  => ['tour',          'push',  'Explora tu dashboard — Descubre tu plan, habitos y mas'],
    2  => ['first_habit',   'push',  'Completaste tus habitos hoy? Solo toma 30 segundos'],
    3  => ['explore_plan',  'push',  'Tu plan de entrenamiento te espera — Asi lo aprovechas al maximo'],
    5  => ['first_checkin', 'push',  'Es hora de tu primer check-in — Cuentanos como van estos primeros dias'],
    7  => ['week1_summary', 'push',  'Tu primera semana en WellCore — Asi te fue'],
    10 => ['motivation',    'push',  '10 dias contigo — Cada dia suma'],
    14 => ['evaluation',    'push',  '2 semanas de transformacion — Como va todo?'],
];

// Get clients in first 14 days
$clients = $db->query("
    SELECT id, name, email, plan, created_at,
           DATEDIFF(CURDATE(), DATE(created_at)) AS days_since_join
    FROM clients
    WHERE status = 'activo'
      AND DATEDIFF(CURDATE(), DATE(created_at)) BETWEEN 0 AND 15
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($clients) . " clients in onboarding window\n";

foreach ($clients as $c) {
    $cid  = (int)$c['id'];
    $days = (int)$c['days_since_join'];
    $fn   = explode(' ', trim($c['name']))[0];

    // Check which steps apply for this day
    foreach ($steps as $day => $stepInfo) {
        if ($days !== $day) continue;

        [$stepKey, $channel, $message] = $stepInfo;

        // Check if already sent
        $check = $db->prepare("SELECT id FROM onboarding_steps WHERE client_id = ? AND step_key = ?");
        $check->execute([$cid, $stepKey]);
        if ($check->fetchColumn()) continue;

        // Personalize message
        $personalizedMsg = str_replace(
            ['[Name]', '[name]'],
            [$fn, $fn],
            $message
        );

        // Send push notification
        $pushSent = webpush_send_to_client($db, $cid, 'WellCore', $personalizedMsg, '/cliente.html');

        // Log the step
        $db->prepare("
            INSERT INTO onboarding_steps (client_id, step_key, completed_at, created_at)
            VALUES (?, ?, NOW(), NOW())
        ")->execute([$cid, $stepKey]);

        $sent++;
        echo "  [OK] Client $cid ($fn) — Day $day: $stepKey" . ($pushSent ? " (push sent)" : " (no push sub)") . "\n";
    }
}

echo "\n[" . date('Y-m-d H:i:s') . "] Onboarding sequence done. Steps sent: $sent\n";
