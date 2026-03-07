<?php
/**
 * Check & Award Achievements
 * POST — Checks all applicable achievements for the authenticated client.
 * Called after key actions (checkin, photo upload, measurement, community post).
 * Body: { "trigger": "checkin"|"photo"|"measurement"|"community_post" }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$client = authenticateClient();
$db = getDB();
$cid  = (int)$client['id'];
$plan = $client['plan'] ?? 'esencial';

$body = getJsonBody();
$trigger = $body['trigger'] ?? '';

$awarded = [];

// Helper: award achievement if not already earned
function awardIfNew(PDO $db, int $clientId, string $type, string $title, string $desc, string $icon, string $audience = 'all'): ?array {
    // Check if already earned
    $check = $db->prepare("SELECT id FROM achievements WHERE client_id = ? AND achievement_type = ?");
    $check->execute([$clientId, $type]);
    if ($check->fetch()) return null;

    // Award
    $db->prepare("
        INSERT INTO achievements (client_id, achievement_type, title, description, icon)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$clientId, $type, $title, $desc, $icon]);
    $achievementId = (int)$db->lastInsertId();

    // Auto-post to community
    $postContent = "desbloqueo el logro: \"$title\" — $desc";
    $db->prepare("
        INSERT INTO community_posts (client_id, content, post_type, achievement_id, audience)
        VALUES (?, ?, 'achievement', ?, ?)
    ")->execute([$clientId, $postContent, $achievementId, $audience]);

    return [
        'achievement_type' => $type,
        'title'            => $title,
        'description'      => $desc,
        'icon'             => $icon,
    ];
}

// --- Check time-based achievements ---
$stmt = $db->prepare("SELECT subscription_start, fecha_inicio FROM clients WHERE id = ?");
$stmt->execute([$cid]);
$clientRow = $stmt->fetch();
$startDate = $clientRow['subscription_start'] ?? $clientRow['fecha_inicio'] ?? null;

if ($startDate) {
    $daysActive = (int)((time() - strtotime($startDate)) / 86400);

    if ($daysActive >= 7) {
        $r = awardIfNew($db, $cid, 'first_week', 'Primera Semana', '7 dias activo en el programa', 'calendar-week');
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 30) {
        $r = awardIfNew($db, $cid, '30_days', '30 Dias Activo', 'Un mes completo en el programa', 'medal');
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 90) {
        $r = awardIfNew($db, $cid, '90_days', '3 Meses Fuerte', '90 dias de constancia', 'award');
        if ($r) $awarded[] = $r;
    }

    // RISE-specific time achievements
    if ($plan === 'rise') {
        $audience = 'rise';
        if ($daysActive >= 7) {
            $r = awardIfNew($db, $cid, 'rise_day7', 'RISE Dia 7', 'Primera semana del reto completada', 'bolt', $audience);
            if ($r) $awarded[] = $r;
        }
        if ($daysActive >= 15) {
            $r = awardIfNew($db, $cid, 'rise_day15', 'RISE Medio Camino', 'Llegaste a la mitad del reto', 'flag-checkered', $audience);
            if ($r) $awarded[] = $r;
        }
        if ($daysActive >= 30) {
            $r = awardIfNew($db, $cid, 'rise_day30', 'RISE Completado', 'Completaste los 30 dias del reto', 'trophy', $audience);
            if ($r) $awarded[] = $r;
        }
    }
}

// --- Check trigger-based achievements ---
if ($trigger === 'checkin') {
    $r = awardIfNew($db, $cid, 'first_checkin', 'Primer Check-in', 'Enviaste tu primer check-in semanal', 'clipboard-check');
    if ($r) $awarded[] = $r;

    // Check streak (7 consecutive weeks with checkins)
    $streakStmt = $db->prepare("SELECT COUNT(*) FROM checkins WHERE client_id = ?");
    $streakStmt->execute([$cid]);
    $checkinCount = (int)$streakStmt->fetchColumn();
    if ($checkinCount >= 7) {
        $r = awardIfNew($db, $cid, 'streak_7', 'Racha Imparable', '7 check-ins consecutivos', 'fire');
        if ($r) $awarded[] = $r;
    }
}

if ($trigger === 'photo') {
    $r = awardIfNew($db, $cid, 'first_photo', 'Primera Foto', 'Subiste tu primera foto de progreso', 'camera');
    if ($r) $awarded[] = $r;
}

if ($trigger === 'measurement' && $plan === 'rise') {
    $r = awardIfNew($db, $cid, 'rise_first_measurement', 'Primera Medicion RISE', 'Registraste tu primera medicion', 'weight-scale', 'rise');
    if ($r) $awarded[] = $r;
}

if ($trigger === 'community_post') {
    $r = awardIfNew($db, $cid, 'first_community', 'Voz de la Comunidad', 'Publicaste en la comunidad por primera vez', 'comments');
    if ($r) $awarded[] = $r;
}

respond([
    'ok'      => true,
    'awarded' => $awarded,
    'count'   => count($awarded),
]);
