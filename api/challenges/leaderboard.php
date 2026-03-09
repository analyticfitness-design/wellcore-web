<?php
// GET /api/challenges/leaderboard?challenge_id=N → top 20 participants

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$client      = authenticateClient();
$db          = getDB();
$clientId    = (int)$client['id'];
$challengeId = isset($_GET['challenge_id']) ? (int)$_GET['challenge_id'] : 0;

if (!$challengeId) {
    respondError('challenge_id requerido', 400);
}

// Verify challenge exists
$stmt = $db->prepare("SELECT id, title, challenge_type, goal_value FROM challenges WHERE id = ? AND is_active = 1");
$stmt->execute([$challengeId]);
$challenge = $stmt->fetch();
if (!$challenge) {
    respondError('Reto no encontrado', 404);
}

// Fetch top 20, with privacy: show first name only
$stmt = $db->prepare("
    SELECT
        cp.`rank`,
        SUBSTRING_INDEX(c.name, ' ', 1) AS first_name,
        cp.progress,
        cp.completed_at,
        (cp.client_id = ?) AS is_current_user
    FROM challenge_participants cp
    JOIN clients c ON c.id = cp.client_id
    WHERE cp.challenge_id = ?
    ORDER BY cp.`rank` ASC, cp.progress DESC
    LIMIT 20
");
$stmt->execute([$clientId, $challengeId]);
$participants = $stmt->fetchAll();

foreach ($participants as &$p) {
    $p['rank']            = $p['rank'] !== null ? (int)$p['rank'] : null;
    $p['progress']        = (float)$p['progress'];
    $p['is_current_user'] = (bool)(int)$p['is_current_user'];
}
unset($p);

$challenge['id']                = (int)$challenge['id'];
$challenge['goal_value']        = (float)$challenge['goal_value'];
$challenge['participant_count'] = (int)($challenge['participant_count'] ?? 0);

respond([
    'challenge'    => $challenge,
    'participants' => $participants,
]);
