<?php
// POST /api/challenges/join → join a challenge

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
if (!$challengeId) {
    respondError('challenge_id requerido', 400);
}

// Verify challenge exists and is active
$stmt = $db->prepare("
    SELECT id FROM challenges
    WHERE id = ? AND is_active = 1 AND end_date >= CURDATE()
");
$stmt->execute([$challengeId]);
if (!$stmt->fetch()) {
    respondError('Reto no encontrado o no disponible', 404);
}

// INSERT IGNORE — UNIQUE key prevents duplicates
$stmt = $db->prepare("
    INSERT IGNORE INTO challenge_participants (challenge_id, client_id, progress)
    VALUES (?, ?, 0)
");
$stmt->execute([$challengeId, $clientId]);

respond(['success' => true, 'message' => 'Te uniste al reto']);
