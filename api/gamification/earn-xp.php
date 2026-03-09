<?php
/**
 * POST /api/gamification/earn-xp
 * Otorga XP al cliente autenticado por un evento específico.
 * Actualiza racha si el evento es 'checkin' o 'video_checkin'.
 *
 * Body: { event_type, description? }
 * Responde: { xp_total, level, xp_gained, streak_days, streak_protected }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();

$client = authenticateClient();
$db     = getDB();

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$event_type = $body['event_type'] ?? '';
$description = trim($body['description'] ?? '');

// XP por tipo de evento
$xp_table = [
    'checkin'      => 50,
    'video_checkin'=> 80,
    'streak_7'     => 150,
    'streak_30'    => 500,
    'badge'        => 100,
    'challenge'    => 200,
    'referral'     => 300,
    'bonus'        => 0, // cantidad en description como int
];

$allowed_types = array_keys($xp_table);
if (!in_array($event_type, $allowed_types, true)) {
    respondError('event_type inválido', 400);
}

$xp_gained = $xp_table[$event_type];
if ($event_type === 'bonus' && is_numeric($body['xp_amount'] ?? '')) {
    $xp_gained = (int) $body['xp_amount'];
}

if ($description === '') {
    $labels = [
        'checkin'      => 'Check-in semanal completado',
        'video_checkin'=> 'Video check-in enviado',
        'streak_7'     => '¡7 días de racha!',
        'streak_30'    => '¡30 días de racha!',
        'badge'        => 'Logro desbloqueado',
        'challenge'    => 'Reto completado',
        'referral'     => 'Referido convertido',
        'bonus'        => 'XP adicional',
    ];
    $description = $labels[$event_type];
}

$client_id = $client['id'];

// Niveles: [min_xp => level]
$level_thresholds = [0 => 1, 200 => 2, 500 => 3, 1000 => 4, 2000 => 5, 4000 => 6];

function calcLevel(int $xp): int {
    global $level_thresholds;
    $level = 1;
    foreach ($level_thresholds as $min => $lvl) {
        if ($xp >= $min) $level = $lvl;
    }
    return $level;
}

$db->beginTransaction();
try {
    // Obtener estado actual
    $row = $db->prepare("SELECT xp_total, streak_days, streak_last_date, streak_protected FROM client_xp WHERE client_id = ?");
    $row->execute([$client_id]);
    $cur = $row->fetch(PDO::FETCH_ASSOC);

    $new_xp      = ($cur ? (int)$cur['xp_total'] : 0) + $xp_gained;
    $new_level   = calcLevel($new_xp);
    $streak_days = $cur ? (int)$cur['streak_days'] : 0;
    $streak_protected = $cur ? (int)$cur['streak_protected'] : 0;
    $today       = date('Y-m-d');

    // Actualizar racha si es check-in
    if (in_array($event_type, ['checkin', 'video_checkin'], true)) {
        $last = $cur['streak_last_date'] ?? null;
        if ($last === null) {
            $streak_days = 1;
        } elseif ($last === $today) {
            // Ya contó hoy — no incrementar
        } else {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            if ($last === $yesterday) {
                $streak_days++;
            } elseif ($streak_protected) {
                // Usar protección una vez
                $streak_protected = 0;
            } else {
                $streak_days = 1; // reiniciar
            }
        }
    }

    // UPSERT client_xp
    $upsert = $db->prepare("
        INSERT INTO client_xp (client_id, xp_total, level, streak_days, streak_last_date, streak_protected)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            xp_total = VALUES(xp_total),
            level = VALUES(level),
            streak_days = VALUES(streak_days),
            streak_last_date = CASE WHEN event_streak THEN VALUES(streak_last_date) ELSE streak_last_date END,
            streak_protected = VALUES(streak_protected)
    ");

    // Simplificado: siempre actualizar streak_last_date en check-ins
    $streak_date = in_array($event_type, ['checkin', 'video_checkin'], true) ? $today : ($cur['streak_last_date'] ?? null);

    $upsert2 = $db->prepare("
        INSERT INTO client_xp (client_id, xp_total, level, streak_days, streak_last_date, streak_protected)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            xp_total        = VALUES(xp_total),
            level           = VALUES(level),
            streak_days     = VALUES(streak_days),
            streak_last_date = VALUES(streak_last_date),
            streak_protected = VALUES(streak_protected)
    ");
    $upsert2->execute([$client_id, $new_xp, $new_level, $streak_days, $streak_date, $streak_protected]);

    // Registrar evento XP
    $ev = $db->prepare("
        INSERT INTO xp_events (client_id, event_type, xp_gained, description)
        VALUES (?, ?, ?, ?)
    ");
    $ev->execute([$client_id, $event_type, $xp_gained, $description]);

    // Bonus XP automático por hitos de racha
    $milestone_bonus = null;
    if (in_array($event_type, ['checkin', 'video_checkin'], true)) {
        if ($streak_days === 7) {
            $milestone_bonus = 'streak_7';
        } elseif ($streak_days === 30) {
            $milestone_bonus = 'streak_30';
        }
    }

    $milestone_xp = 0;
    if ($milestone_bonus) {
        $milestone_xp = $xp_table[$milestone_bonus];
        $new_xp += $milestone_xp;
        $new_level = calcLevel($new_xp);
        $db->prepare("UPDATE client_xp SET xp_total = ?, level = ? WHERE client_id = ?")
           ->execute([$new_xp, $new_level, $client_id]);
        $db->prepare("INSERT INTO xp_events (client_id, event_type, xp_gained, description) VALUES (?, ?, ?, ?)")
           ->execute([$client_id, $milestone_bonus, $milestone_xp, $milestone_bonus === 'streak_7' ? '¡Racha de 7 días!' : '¡Racha de 30 días!']);
    }

    $db->commit();

    respond([
        'xp_total'         => $new_xp,
        'xp_gained'        => $xp_gained + $milestone_xp,
        'level'            => $new_level,
        'streak_days'      => $streak_days,
        'streak_protected' => (bool)$streak_protected,
        'milestone_bonus'  => $milestone_bonus,
    ]);

} catch (\Exception $e) {
    $db->rollBack();
    respondError('Error interno: ' . $e->getMessage(), 500);
}
