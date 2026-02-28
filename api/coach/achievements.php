<?php
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');

$coach = authenticateCoach();
$coachId = (int) $coach['id'];
$db = getDB();

// Achievement definitions
$definitions = [
    ['type' => 'clients_10',        'label' => '10 Clientes Activos',   'icon' => 'users'],
    ['type' => 'clients_25',        'label' => '25 Clientes Activos',   'icon' => 'users'],
    ['type' => 'clients_50',        'label' => '50 Clientes Activos',   'icon' => 'trophy'],
    ['type' => 'year_1',            'label' => '1 Ano en WellCore',     'icon' => 'calendar'],
    ['type' => 'year_2',            'label' => '2 Anos en WellCore',    'icon' => 'calendar'],
    ['type' => 'first_referral',    'label' => 'Primer Referido',       'icon' => 'link'],
    ['type' => 'referrals_10',      'label' => '10 Referidos Exitosos', 'icon' => 'link'],
    ['type' => 'revenue_milestone', 'label' => '$1M COP Generados',    'icon' => 'dollar'],
];

// Fetch metrics for condition checks
$stmt = $db->prepare("SELECT COUNT(*) AS total FROM clients WHERE coach_id = ? AND status = 'activo'");
$stmt->execute([$coachId]);
$activeClients = (int) $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT DATEDIFF(NOW(), created_at) AS days FROM admins WHERE id = ?");
$stmt->execute([$coachId]);
$daysSinceJoined = (int) $stmt->fetch()['days'];

$stmt = $db->prepare("SELECT COALESCE(SUM(converted), 0) AS total FROM referral_stats WHERE coach_id = ?");
$stmt->execute([$coachId]);
$conversions = (int) $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COALESCE(SUM(p.amount), 0) AS total FROM payments p JOIN clients c ON c.id = p.client_id WHERE c.coach_id = ?");
$stmt->execute([$coachId]);
$totalRevenue = (int) $stmt->fetch()['total'];

// Evaluate and award achievements
$insertStmt = $db->prepare("INSERT IGNORE INTO coach_achievements (admin_id, achievement_type, label, icon) VALUES (?, ?, ?, ?)");

foreach ($definitions as $def) {
    $earned = false;

    switch ($def['type']) {
        case 'clients_10':
            $earned = $activeClients >= 10;
            break;
        case 'clients_25':
            $earned = $activeClients >= 25;
            break;
        case 'clients_50':
            $earned = $activeClients >= 50;
            break;
        case 'year_1':
            $earned = $daysSinceJoined >= 365;
            break;
        case 'year_2':
            $earned = $daysSinceJoined >= 730;
            break;
        case 'first_referral':
            $earned = $conversions >= 1;
            break;
        case 'referrals_10':
            $earned = $conversions >= 10;
            break;
        case 'revenue_milestone':
            $earned = $totalRevenue >= 1000000;
            break;
    }

    if ($earned) {
        $insertStmt->execute([$coachId, $def['type'], $def['label'], $def['icon']]);
    }
}

// Return all earned achievements
$stmt = $db->prepare("SELECT achievement_type, label, icon, earned_at FROM coach_achievements WHERE admin_id = ? ORDER BY earned_at DESC");
$stmt->execute([$coachId]);
$achievements = $stmt->fetchAll();

respond(['ok' => true, 'achievements' => $achievements]);
