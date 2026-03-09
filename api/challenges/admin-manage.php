<?php
// GET    /api/challenges/admin-manage → list all challenges with participant_count
// POST   /api/challenges/admin-manage → create new challenge
// DELETE /api/challenges/admin-manage?id=N → soft delete (is_active = 0)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST', 'DELETE');
$admin = authenticateAdmin();
$db    = getDB();

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET ─────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $db->query("
        SELECT
            c.id,
            c.title,
            c.description,
            c.challenge_type,
            c.goal_value,
            c.start_date,
            c.end_date,
            c.is_active,
            c.created_at,
            COUNT(cp.id) AS participant_count
        FROM challenges c
        LEFT JOIN challenge_participants cp ON cp.challenge_id = c.id
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $challenges = $stmt->fetchAll();
    foreach ($challenges as &$ch) {
        $ch['goal_value']        = (float)$ch['goal_value'];
        $ch['participant_count'] = (int)$ch['participant_count'];
        $ch['is_active']         = (bool)(int)$ch['is_active'];
    }
    unset($ch);
    respond(['challenges' => $challenges]);
}

// ─── POST (create) ────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body = getJsonBody();

    $title         = trim($body['title'] ?? '');
    $description   = trim($body['description'] ?? '');
    $challengeType = $body['challenge_type'] ?? '';
    $goalValue     = isset($body['goal_value']) ? (float)$body['goal_value'] : 0;
    $startDate     = $body['start_date'] ?? '';
    $endDate       = $body['end_date'] ?? '';

    $validTypes = ['steps', 'checkins', 'weight_loss', 'streak'];

    if (empty($title)) {
        respondError('title requerido', 422);
    }
    if (!in_array($challengeType, $validTypes, true)) {
        respondError('challenge_type inválido. Valores: ' . implode(', ', $validTypes), 422);
    }
    if ($goalValue <= 0) {
        respondError('goal_value debe ser mayor a 0', 422);
    }
    if (empty($startDate) || empty($endDate)) {
        respondError('start_date y end_date requeridos', 422);
    }
    if ($endDate <= $startDate) {
        respondError('end_date debe ser posterior a start_date', 422);
    }

    $stmt = $db->prepare("
        INSERT INTO challenges (title, description, challenge_type, goal_value, start_date, end_date, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$title, $description ?: null, $challengeType, $goalValue, $startDate, $endDate, $admin['id']]);
    $newId = $db->lastInsertId();

    respond(['success' => true, 'id' => (int)$newId, 'message' => 'Reto creado exitosamente'], 201);
}

// ─── DELETE (soft delete) ─────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        respondError('id requerido', 400);
    }

    $stmt = $db->prepare("UPDATE challenges SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        respondError('Reto no encontrado', 404);
    }

    respond(['success' => true, 'message' => 'Reto desactivado']);
}
