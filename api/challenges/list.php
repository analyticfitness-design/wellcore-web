<?php
// GET /api/challenges/list → active challenges with user join status

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$client = authenticateClient();
$db = getDB();
$clientId = (int)$client['id'];

$stmt = $db->prepare("
    SELECT
        c.id,
        c.title,
        c.description,
        c.challenge_type,
        c.goal_value,
        c.start_date,
        c.end_date,
        (SELECT COUNT(*) FROM challenge_participants cp WHERE cp.challenge_id = c.id) AS participant_count,
        (SELECT COUNT(*) FROM challenge_participants cp2 WHERE cp2.challenge_id = c.id AND cp2.client_id = ?) AS user_joined,
        (SELECT cp3.progress FROM challenge_participants cp3 WHERE cp3.challenge_id = c.id AND cp3.client_id = ?) AS user_progress,
        (SELECT cp4.`rank` FROM challenge_participants cp4 WHERE cp4.challenge_id = c.id AND cp4.client_id = ?) AS user_rank
    FROM challenges c
    WHERE c.is_active = 1 AND c.end_date >= CURDATE()
    ORDER BY c.start_date DESC
");
$stmt->execute([$clientId, $clientId, $clientId]);
$challenges = $stmt->fetchAll();

foreach ($challenges as &$ch) {
    $ch['user_joined']   = (bool)(int)$ch['user_joined'];
    $ch['user_progress'] = $ch['user_joined'] ? (float)$ch['user_progress'] : null;
    $ch['user_rank']     = $ch['user_joined'] ? ($ch['user_rank'] !== null ? (int)$ch['user_rank'] : null) : null;
    $ch['goal_value']    = (float)$ch['goal_value'];
    $ch['participant_count'] = (int)$ch['participant_count'];
}
unset($ch);

respond(['challenges' => $challenges]);
