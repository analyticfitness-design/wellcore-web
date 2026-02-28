<?php
// GET /api/photos/list?limit=20

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$client = authenticateClient();
$limit  = min((int)($_GET['limit'] ?? 20), 100);

$db   = getDB();
$stmt = $db->prepare("
    SELECT id, photo_date, tipo, filename, created_at,
           CONCAT(?, 'photos/', ?, '/', filename) AS url
    FROM progress_photos
    WHERE client_id = ?
    ORDER BY photo_date DESC, created_at DESC
    LIMIT ?
");
$uploadUrl = UPLOAD_URL;
$code      = $client['client_code'];
$stmt->execute([$uploadUrl, $code, $client['id'], $limit]);
respond(['photos' => $stmt->fetchAll()]);
