<?php
// GET  /api/prs  → lista todos los PRs del cliente autenticado
// POST /api/prs  → guarda/actualiza un PR { exercise_id, value }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST');
$client = authenticateClient();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare(
        "SELECT exercise_id, value, recorded_at
         FROM personal_records
         WHERE client_id = ?
         ORDER BY exercise_id"
    );
    $stmt->execute([$client['id']]);
    $prs = [];
    foreach ($stmt->fetchAll() as $row) {
        $prs[$row['exercise_id']] = [
            'val'  => (float) $row['value'],
            'date' => date('d M Y', strtotime($row['recorded_at']))
        ];
    }
    respond(['prs' => $prs]);
}

// POST
$body       = getJsonBody();
$exerciseId = trim($body['exercise_id'] ?? '');
$value      = (float) ($body['value'] ?? 0);

$validIds = ['sq', 'dl', 'bp', 'ohp', 'pu', 'row', 'run', 'pbw'];
if (!in_array($exerciseId, $validIds, true)) respondError('exercise_id inválido', 422);
if ($value <= 0)                             respondError('value debe ser positivo', 422);

$stmt = $db->prepare("
    INSERT INTO personal_records (client_id, exercise_id, value, recorded_at)
    VALUES (?, ?, ?, CURDATE())
    ON DUPLICATE KEY UPDATE value = VALUES(value), recorded_at = CURDATE()
");
$stmt->execute([$client['id'], $exerciseId, $value]);
respond(['ok' => true]);
