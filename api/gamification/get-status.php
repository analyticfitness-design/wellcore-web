<?php
/**
 * GET /api/gamification/get-status
 * Devuelve el estado completo de XP/gamificación del cliente.
 *
 * Responde: { xp_total, level, level_name, xp_next_level, xp_progress_pct,
 *             streak_days, streak_protected, recent_events[] }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();
requireMethod('GET');

$client    = authenticateClient();
$db        = getDB();
$client_id = $client['id'];

// Niveles con nombre y XP mínimo
$levels = [
    1 => ['name' => 'Iniciado',     'min' => 0,    'next' => 200],
    2 => ['name' => 'Comprometido', 'min' => 200,  'next' => 500],
    3 => ['name' => 'Constante',    'min' => 500,  'next' => 1000],
    4 => ['name' => 'Dedicado',     'min' => 1000, 'next' => 2000],
    5 => ['name' => 'Elite',        'min' => 2000, 'next' => 4000],
    6 => ['name' => 'Leyenda',      'min' => 4000, 'next' => null],
];

$row = $db->prepare("SELECT xp_total, level, streak_days, streak_last_date, streak_protected FROM client_xp WHERE client_id = ?");
$row->execute([$client_id]);
$xp = $row->fetch(PDO::FETCH_ASSOC);

if (!$xp) {
    // Primera vez — inicializar registro
    $db->prepare("INSERT IGNORE INTO client_xp (client_id, xp_total, level, streak_days) VALUES (?, 0, 1, 0)")->execute([$client_id]);
    $xp = ['xp_total' => 0, 'level' => 1, 'streak_days' => 0, 'streak_last_date' => null, 'streak_protected' => 0];
}

$level    = (int)$xp['level'];
$xp_total = (int)$xp['xp_total'];
$lv       = $levels[$level] ?? $levels[6];

$xp_next = $lv['next'];
$xp_progress_pct = 0;
if ($xp_next !== null) {
    $xp_in_level = $xp_total - $lv['min'];
    $xp_needed   = $xp_next - $lv['min'];
    $xp_progress_pct = $xp_needed > 0 ? round(min(100, ($xp_in_level / $xp_needed) * 100)) : 100;
} else {
    $xp_progress_pct = 100;
}

// Verificar si la racha está vigente (last_date = ayer o hoy)
$streak_active = false;
if ($xp['streak_last_date']) {
    $last = strtotime($xp['streak_last_date']);
    $diff = (int)floor((strtotime(date('Y-m-d')) - $last) / 86400);
    $streak_active = ($diff <= 1);
}

// Últimos 10 eventos XP
$evts = $db->prepare("SELECT event_type, xp_gained, description, created_at FROM xp_events WHERE client_id = ? ORDER BY created_at DESC LIMIT 10");
$evts->execute([$client_id]);

respond([
    'xp_total'         => $xp_total,
    'level'            => $level,
    'level_name'       => $lv['name'],
    'xp_next_level'    => $xp_next,
    'xp_progress_pct'  => $xp_progress_pct,
    'streak_days'      => (int)$xp['streak_days'],
    'streak_active'    => $streak_active,
    'streak_protected' => (bool)$xp['streak_protected'],
    'recent_events'    => $evts->fetchAll(PDO::FETCH_ASSOC),
]);
