<?php
/**
 * GET  /api/coach/video-tips        — Lista tips del coach del cliente
 * POST /api/coach/video-tips        — Coach crea/edita video tip
 * DELETE /api/coach/video-tips?id=  — Coach elimina video tip
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $client    = authenticateClient();
    $client_id = $client['id'];

    $cr = $db->prepare("SELECT coach_id FROM clients WHERE id = ?");
    $cr->execute([$client_id]);
    $coach_id = $cr->fetchColumn();

    if (!$coach_id) respond(['items' => []]);

    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;

    $total = (int)$db->prepare("SELECT COUNT(*) FROM coach_video_tips WHERE coach_id = ? AND is_active = 1")
                     ->execute([$coach_id]) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM coach_video_tips WHERE coach_id = ? AND is_active = 1");
    $cntStmt->execute([$coach_id]);
    $total = (int)$cntStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT id, title, video_url, thumbnail_url, duration_sec, sort_order, created_at
        FROM coach_video_tips
        WHERE coach_id = ? AND is_active = 1
        ORDER BY sort_order ASC, created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$coach_id, $limit, $offset]);
    respond(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page]);

} elseif ($method === 'POST') {
    $coach    = authenticateCoach();
    $coach_id = $coach['id'];
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];

    $id            = (int)($body['id'] ?? 0);
    $title         = trim($body['title'] ?? '');
    $video_url     = trim($body['video_url'] ?? '');
    $thumbnail_url = trim($body['thumbnail_url'] ?? '');
    $duration      = (int)($body['duration_sec'] ?? 0);
    $sort_order    = (int)($body['sort_order'] ?? 0);
    $is_active     = isset($body['is_active']) ? (int)(bool)$body['is_active'] : 1;

    if (!$title || !$video_url) respondError('title y video_url son requeridos', 400);

    if ($id > 0) {
        $db->prepare("
            UPDATE coach_video_tips SET title=?, video_url=?, thumbnail_url=?, duration_sec=?, sort_order=?, is_active=?
            WHERE id = ? AND coach_id = ?
        ")->execute([$title, $video_url, $thumbnail_url ?: null, $duration, $sort_order, $is_active, $id, $coach_id]);
        respond(['success' => true, 'id' => $id]);
    } else {
        $db->prepare("
            INSERT INTO coach_video_tips (coach_id, title, video_url, thumbnail_url, duration_sec, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$coach_id, $title, $video_url, $thumbnail_url ?: null, $duration, $sort_order, $is_active]);
        respond(['success' => true, 'id' => (int)$db->lastInsertId()]);
    }

} elseif ($method === 'DELETE') {
    $coach    = authenticateCoach();
    $coach_id = $coach['id'];
    $id       = (int)($_GET['id'] ?? 0);
    if (!$id) respondError('id requerido', 400);

    $db->prepare("UPDATE coach_video_tips SET is_active = 0 WHERE id = ? AND coach_id = ?")->execute([$id, $coach_id]);
    respond(['success' => true]);

} else {
    respondError('Método no permitido', 405);
}
