<?php
/**
 * Achievements API
 * GET — List earned + locked achievements for current client
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$client = authenticateClient();
$db = getDB();
$cid = (int)$client['id'];
$plan = $client['plan'] ?? 'esencial';

// Get earned achievements
$stmt = $db->prepare("
    SELECT achievement_type, title, description, icon, earned_at
    FROM achievements WHERE client_id = ? ORDER BY earned_at DESC
");
$stmt->execute([$cid]);
$earned = $stmt->fetchAll();
$earnedTypes = array_column($earned, 'achievement_type');

// Define all possible achievements
$allAchievements = [
    ['type' => 'first_checkin',     'title' => 'Primer Check-in',          'desc' => 'Enviaste tu primer check-in semanal',     'icon' => 'clipboard-check'],
    ['type' => 'first_week',        'title' => 'Primera Semana',           'desc' => '7 dias activo en el programa',             'icon' => 'calendar-week'],
    ['type' => 'first_photo',       'title' => 'Primera Foto',             'desc' => 'Subiste tu primera foto de progreso',      'icon' => 'camera'],
    ['type' => '30_days',           'title' => '30 Dias Activo',           'desc' => 'Un mes completo en el programa',           'icon' => 'medal'],
    ['type' => '90_days',           'title' => '3 Meses Fuerte',           'desc' => '90 dias de constancia',                    'icon' => 'award'],
    ['type' => 'streak_7',          'title' => 'Racha Imparable',          'desc' => '7 check-ins consecutivos',                 'icon' => 'fire'],
    ['type' => 'first_community',   'title' => 'Voz de la Comunidad',     'desc' => 'Publicaste en la comunidad por primera vez','icon' => 'comments'],
];

if ($plan === 'rise') {
    $allAchievements = array_merge($allAchievements, [
        ['type' => 'rise_day7',              'title' => 'RISE Dia 7',              'desc' => 'Primera semana del reto completada', 'icon' => 'bolt'],
        ['type' => 'rise_day15',             'title' => 'RISE Medio Camino',       'desc' => 'Llegaste a la mitad del reto',       'icon' => 'flag-checkered'],
        ['type' => 'rise_day30',             'title' => 'RISE Completado',         'desc' => 'Completaste los 30 dias del reto',   'icon' => 'trophy'],
        ['type' => 'rise_first_measurement', 'title' => 'Primera Medicion RISE',   'desc' => 'Registraste tu primera medicion',    'icon' => 'weight-scale'],
    ]);
} elseif ($plan === 'elite') {
    $allAchievements = array_merge($allAchievements, [
        ['type' => 'elite_nutrition_streak', 'title' => 'Nutricion al Dia',  'desc' => '7 dias usando el analisis nutricional', 'icon' => 'apple-whole'],
    ]);
}

$locked = [];
foreach ($allAchievements as $a) {
    if (!in_array($a['type'], $earnedTypes, true)) {
        $locked[] = [
            'achievement_type' => $a['type'],
            'title'            => $a['title'],
            'description'      => $a['desc'],
            'icon'             => $a['icon'],
            'locked'           => true,
        ];
    }
}

respond([
    'earned' => $earned,
    'locked' => $locked,
    'total_earned' => count($earned),
    'total_possible' => count($earned) + count($locked),
]);
