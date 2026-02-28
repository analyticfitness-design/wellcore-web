<?php
// GET /api/admin/photos?client_id=X&limit=50
// Returns progress photos for a specific client (admin auth required)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$admin = authenticateAdmin();

$clientId = (int)($_GET['client_id'] ?? 0);
if (!$clientId) respondError('client_id requerido', 422);

$limit = min((int)($_GET['limit'] ?? 50), 200);
$db = getDB();

// Verify client exists
$stmt = $db->prepare("SELECT id, client_code, name FROM clients WHERE id = ?");
$stmt->execute([$clientId]);
$client = $stmt->fetch();
if (!$client) respondError('Cliente no encontrado', 404);

// Fetch photos
$stmt = $db->prepare("
    SELECT id, photo_date, tipo, filename, created_at,
           CONCAT(?, 'photos/', ?, '/', filename) AS url
    FROM progress_photos
    WHERE client_id = ?
    ORDER BY photo_date DESC, tipo ASC
    LIMIT ?
");
$stmt->execute([UPLOAD_URL, $client['client_code'], $clientId, $limit]);
$photos = $stmt->fetchAll();

// Group by date for easier frontend rendering
$grouped = [];
foreach ($photos as $p) {
    $date = $p['photo_date'];
    if (!isset($grouped[$date])) {
        $grouped[$date] = ['date' => $date, 'photos' => []];
    }
    $grouped[$date]['photos'][] = $p;
}

respond([
    'client' => $client,
    'photos' => $photos,
    'grouped' => array_values($grouped),
    'total' => count($photos),
]);
