<?php
// POST /api/challenges/progress → update user progress in a challenge

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$client   = authenticateClient();
$db       = getDB();
$clientId = (int)$client['id'];
$body     = getJsonBody();

$challengeId = isset($body['challenge_id']) ? (int)$body['challenge_id'] : 0;
$progress    = isset($body['progress'])    ? (float)$body['progress']    : null;

if (!$challengeId) {
    respondError('challenge_id requerido', 400);
}
if ($progress === null) {
    respondError('progress requerido', 400);
}
if ($progress < 0) {
    respondError('progress no puede ser negativo', 422);
}

// Verify the client is a participant
$stmt = $db->prepare("
    SELECT cp.id, cp.completed_at, c.goal_value
    FROM challenge_participants cp
    JOIN challenges c ON c.id = cp.challenge_id
    WHERE cp.challenge_id = ? AND cp.client_id = ?
");
$stmt->execute([$challengeId, $clientId]);
$row = $stmt->fetch();
if (!$row) {
    respondError('No estás unido a este reto', 403);
}

$goalValue   = (float)$row['goal_value'];
$completedAt = $row['completed_at'];

// Cap progress at goal_value
$progress = min($progress, $goalValue * 2); // allow going slightly above, leaderboard sorts by it

// Determine if now completing for the first time
$setCompleted = '';
if ($progress >= $goalValue && $completedAt === null) {
    $setCompleted = ', completed_at = NOW()';
}

$db->beginTransaction();
try {
    // Update progress
    $stmt = $db->prepare("
        UPDATE challenge_participants
        SET progress = ?
        $setCompleted
        WHERE challenge_id = ? AND client_id = ?
    ");
    $stmt->execute([$progress, $challengeId, $clientId]);

    // Recalculate ranks for this challenge
    // Rank by progress DESC (higher progress = better rank), ties share same rank
    $db->prepare("
        UPDATE challenge_participants cp
        JOIN (
            SELECT client_id,
                   RANK() OVER (ORDER BY progress DESC) AS new_rank
            FROM challenge_participants
            WHERE challenge_id = ?
        ) ranked ON ranked.client_id = cp.client_id
        SET cp.`rank` = ranked.new_rank
        WHERE cp.challenge_id = ?
    ")->execute([$challengeId, $challengeId]);

    // Fetch updated row
    $stmt = $db->prepare("
        SELECT progress, `rank`, completed_at
        FROM challenge_participants
        WHERE challenge_id = ? AND client_id = ?
    ");
    $stmt->execute([$challengeId, $clientId]);
    $updated = $stmt->fetch();

    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    respondError('Error al actualizar progreso', 500);
}

respond([
    'success'      => true,
    'progress'     => (float)$updated['progress'],
    'rank'         => $updated['rank'] !== null ? (int)$updated['rank'] : null,
    'completed_at' => $updated['completed_at'],
    'goal_value'   => $goalValue,
]);
