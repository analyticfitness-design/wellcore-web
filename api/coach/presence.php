<?php
/**
 * WellCore — Coach Presence
 * GET  /api/coach/presence.php              — Client gets their coach's presence
 * POST /api/coach/presence.php              — Coach heartbeat (updates last_seen)
 * GET  /api/coach/presence.php?coach_id=X   — Get specific coach presence
 */
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coach heartbeat — update presence
    $admin = authenticateAdmin();
    $adminId = (int)$admin['id'];

    $db->prepare("
        INSERT INTO coach_presence (admin_id, last_seen, status)
        VALUES (?, NOW(), 'online')
        ON DUPLICATE KEY UPDATE last_seen = NOW(), status = 'online'
    ")->execute([$adminId]);

    respond(['ok' => true]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userType = peekTokenUserType();

    if ($userType === 'client') {
        $client  = authenticateClient();
        $coachId = null;

        // Get assigned coach
        $stmt = $db->prepare("SELECT coach_id FROM clients WHERE id = ?");
        $stmt->execute([$client['id']]);
        $coachId = $stmt->fetchColumn();

        if (!$coachId) {
            respond(['ok' => true, 'coach' => null, 'message' => 'No coach assigned']);
        }

    } elseif ($userType === 'admin') {
        authenticateAdmin();
        $coachId = (int)($_GET['coach_id'] ?? 0);
        if (!$coachId) respondError('coach_id requerido', 422);
    } else {
        respondError('Authentication required', 401);
    }

    // Get coach info + presence
    $stmt = $db->prepare("
        SELECT a.id, a.name, a.role, a.avatar,
               cp.last_seen, cp.status
        FROM admins a
        LEFT JOIN coach_presence cp ON cp.admin_id = a.id
        WHERE a.id = ?
    ");
    $stmt->execute([$coachId]);
    $coach = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coach) {
        respond(['ok' => true, 'coach' => null]);
    }

    // Determine status based on last_seen
    $lastSeen = $coach['last_seen'] ? strtotime($coach['last_seen']) : 0;
    $diff = time() - $lastSeen;

    if ($diff < 7200) { // 2 hours
        $status = 'online';
        $label  = 'Activo ahora';
    } elseif ($diff < 86400) { // 24 hours
        $hours = floor($diff / 3600);
        $status = 'away';
        $label  = "Hace {$hours}h";
    } else {
        $status = 'offline';
        $label  = $coach['last_seen'] ? 'Hace más de 24h' : 'Sin datos';
    }

    respond([
        'ok'    => true,
        'coach' => [
            'id'        => (int)$coach['id'],
            'name'      => $coach['name'],
            'role'      => $coach['role'],
            'avatar'    => $coach['avatar'] ?? null,
            'status'    => $status,
            'label'     => $label,
            'last_seen' => $coach['last_seen'],
        ],
    ]);

} else {
    respondError('Method not allowed', 405);
}
