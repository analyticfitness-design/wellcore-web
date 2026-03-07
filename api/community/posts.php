<?php
/**
 * Community Posts API
 * GET  — List posts (paginated, filtered by audience)
 * POST — Create a new post or reply
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST');
$client = authenticateClient();
$db = getDB();
$cid = (int)$client['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $audience = $_GET['audience'] ?? 'all';
    if (!in_array($audience, ['all', 'rise'], true)) $audience = 'all';

    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    // Get top-level posts (parent_id IS NULL)
    $stmt = $db->prepare("
        SELECT p.id, p.client_id, p.content, p.post_type, p.audience,
               p.parent_id, p.created_at,
               c.name AS author_name, c.plan AS author_plan,
               (SELECT COUNT(*) FROM community_posts r WHERE r.parent_id = p.id) AS reply_count
        FROM community_posts p
        JOIN clients c ON c.id = p.client_id
        WHERE p.audience = ? AND p.parent_id IS NULL
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$audience, $limit, $offset]);
    $posts = $stmt->fetchAll();

    // Get reactions grouped per post
    $postIds = array_column($posts, 'id');
    $reactions = [];
    if ($postIds) {
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $rStmt = $db->prepare("
            SELECT post_id, emoji, COUNT(*) AS cnt,
                   MAX(CASE WHEN client_id = ? THEN 1 ELSE 0 END) AS user_reacted
            FROM community_reactions
            WHERE post_id IN ($placeholders)
            GROUP BY post_id, emoji
        ");
        $rStmt->execute(array_merge([$cid], $postIds));
        foreach ($rStmt->fetchAll() as $r) {
            $reactions[$r['post_id']][] = [
                'emoji'        => $r['emoji'],
                'count'        => (int)$r['cnt'],
                'user_reacted' => (bool)$r['user_reacted'],
            ];
        }
    }

    // Get latest 3 replies per post
    $replies = [];
    if ($postIds) {
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $repStmt = $db->prepare("
            SELECT r.id, r.client_id, r.content, r.parent_id, r.created_at,
                   c.name AS author_name
            FROM community_posts r
            JOIN clients c ON c.id = r.client_id
            WHERE r.parent_id IN ($placeholders)
            ORDER BY r.created_at ASC
        ");
        $repStmt->execute($postIds);
        foreach ($repStmt->fetchAll() as $rep) {
            $replies[$rep['parent_id']][] = $rep;
        }
    }

    // Build response
    $result = [];
    foreach ($posts as $p) {
        $pid = $p['id'];
        $allReplies = $replies[$pid] ?? [];
        $result[] = [
            'id'           => (int)$pid,
            'client_id'    => (int)$p['client_id'],
            'content'      => $p['content'],
            'post_type'    => $p['post_type'],
            'audience'     => $p['audience'],
            'author_name'  => $p['author_name'],
            'author_plan'  => $p['author_plan'],
            'author_initial' => mb_strtoupper(mb_substr($p['author_name'], 0, 1)),
            'created_at'   => $p['created_at'],
            'reactions'    => $reactions[$pid] ?? [],
            'reply_count'  => (int)$p['reply_count'],
            'replies'      => array_slice(array_map(function($r) {
                return [
                    'id'          => (int)$r['id'],
                    'client_id'   => (int)$r['client_id'],
                    'content'     => $r['content'],
                    'author_name' => $r['author_name'],
                    'author_initial' => mb_strtoupper(mb_substr($r['author_name'], 0, 1)),
                    'created_at'  => $r['created_at'],
                ];
            }, $allReplies), 0, 3),
        ];
    }

    // Total count for pagination
    $countStmt = $db->prepare("SELECT COUNT(*) FROM community_posts WHERE audience = ? AND parent_id IS NULL");
    $countStmt->execute([$audience]);
    $total = (int)$countStmt->fetchColumn();

    respond([
        'posts'       => $result,
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => (int)ceil($total / $limit),
    ]);
}

// POST — Create post
$body = getJsonBody();
$content = trim($body['content'] ?? '');
if (!$content || mb_strlen($content) > 500) {
    respondError('El contenido debe tener entre 1 y 500 caracteres', 400);
}

// Strip HTML tags
$content = strip_tags($content);

$postType = $body['post_type'] ?? 'text';
if (!in_array($postType, ['text', 'workout', 'milestone'], true)) $postType = 'text';

$parentId = isset($body['parent_id']) ? (int)$body['parent_id'] : null;
$audience = $body['audience'] ?? 'all';
if (!in_array($audience, ['all', 'rise'], true)) $audience = 'all';

// If reply, validate parent exists
if ($parentId) {
    $pStmt = $db->prepare("SELECT id FROM community_posts WHERE id = ? AND parent_id IS NULL");
    $pStmt->execute([$parentId]);
    if (!$pStmt->fetch()) {
        respondError('Post padre no encontrado', 404);
    }
}

$stmt = $db->prepare("
    INSERT INTO community_posts (client_id, content, post_type, parent_id, audience)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$cid, $content, $postType, $parentId, $audience]);
$newId = (int)$db->lastInsertId();

respond([
    'ok'   => true,
    'post' => [
        'id'             => $newId,
        'client_id'      => $cid,
        'content'        => $content,
        'post_type'      => $postType,
        'audience'       => $audience,
        'parent_id'      => $parentId,
        'author_name'    => $client['name'],
        'author_initial' => mb_strtoupper(mb_substr($client['name'], 0, 1)),
        'created_at'     => date('Y-m-d H:i:s'),
        'reactions'      => [],
        'reply_count'    => 0,
        'replies'        => [],
    ],
], 201);
