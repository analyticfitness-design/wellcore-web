<?php
// GET  /api/coach/community        — feed de posts (todos los coaches)
// POST /api/coach/community        — crear post
// POST /api/coach/community?like=1 — dar like a post {post_id}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

$coach = authenticateCoach();
$db    = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT p.id, p.coach_id, p.content, p.type, p.likes, p.created_at,
               a.name AS coach_name
        FROM coach_community_posts p
        JOIN admins a ON a.id = p.coach_id
        ORDER BY p.created_at DESC
        LIMIT 40
    ");
    $stmt->execute();
    respond(['posts' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // Like action
    if (isset($_GET['like'])) {
        $postId = isset($body['post_id']) ? (int)$body['post_id'] : 0;
        if (!$postId) respondError('post_id requerido', 400);
        $db->prepare("UPDATE coach_community_posts SET likes = likes + 1 WHERE id = ?")
           ->execute([$postId]);
        respond(['ok' => true]);
    }

    // Create post
    $content = trim($body['content'] ?? '');
    $type    = in_array($body['type'] ?? '', ['post','tip','achievement']) ? $body['type'] : 'post';
    if (!$content) respondError('content requerido', 400);
    if (strlen($content) > 1000) respondError('Contenido demasiado largo', 400);

    $db->prepare("INSERT INTO coach_community_posts (coach_id, content, type) VALUES (?, ?, ?)")
       ->execute([$coach['id'], $content, $type]);

    respond(['ok' => true, 'id' => $db->lastInsertId()]);
}

respondError('Método no permitido', 405);
