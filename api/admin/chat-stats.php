<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Admin: Chat Stats
 * GET /api/admin/chat-stats?days=30
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('GET');
$admin = authenticateAdmin();
if (!in_array($admin['role'], ['admin', 'jefe', 'superadmin'], true)) {
    respondError('No autorizado', 403);
}

$db   = getDB();
$days = min(180, max(1, (int) ($_GET['days'] ?? 30)));
$since = date('Y-m-d', strtotime("-{$days} days"));

// Stats
$statsStmt = $db->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(DATE(created_at) = CURDATE()) AS today,
        COUNT(DISTINCT session_id) AS sessions,
        ROUND(AVG(tokens_used)) AS avg_tokens
    FROM chat_messages
    WHERE role = 'user' AND created_at >= ?
");
$statsStmt->execute([$since]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Recent messages
$msgStmt = $db->prepare("
    SELECT id, session_id, content, tokens_used, model, created_at
    FROM chat_messages
    WHERE role = 'user' AND created_at >= ?
    ORDER BY created_at DESC
    LIMIT 50
");
$msgStmt->execute([$since]);
$messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

// Top questions (group by similar first 60 chars)
$topStmt = $db->prepare("
    SELECT LEFT(content, 60) AS question, COUNT(*) AS count
    FROM chat_messages
    WHERE role = 'user' AND created_at >= ? AND content IS NOT NULL
    GROUP BY LEFT(content, 60)
    ORDER BY count DESC
    LIMIT 10
");
$topStmt->execute([$since]);
$topQuestions = $topStmt->fetchAll(PDO::FETCH_ASSOC);

respond([
    'ok'             => true,
    'stats'          => $stats,
    'messages'       => $messages,
    'top_questions'  => $topQuestions,
]);
