<?php
// TEMPORAL — diagnostico IA prompts + planes activos. DELETE after use.
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$a = $_GET['a'] ?? 'prompts';

if ($a === 'prompts') {
    try {
        $r = $db->query("SELECT type, LENGTH(system_prompt) as sp_len, LEFT(system_prompt, 300) as sp_head FROM ai_prompts");
        echo json_encode(['ok' => true, 'prompts' => $r->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'note' => 'Table ai_prompts may not exist']);
    }
} elseif ($a === 'plans') {
    $r = $db->query("
        SELECT ap.id, ap.client_id, ap.plan_type, ap.version, ap.active, ap.created_at,
               c.name, c.email, c.plan,
               LENGTH(ap.content) as json_len,
               LEFT(ap.content, 300) as json_head
        FROM assigned_plans ap
        JOIN clients c ON c.id = ap.client_id
        WHERE ap.active = 1
        ORDER BY ap.client_id
    ");
    echo json_encode(['ok' => true, 'active_plans' => $r->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
} elseif ($a === 'plan_detail') {
    $id = (int) ($_GET['id'] ?? 0);
    $r = $db->prepare("SELECT id, client_id, plan_type, content FROM assigned_plans WHERE id = ?");
    $r->execute([$id]);
    $row = $r->fetch(PDO::FETCH_ASSOC);
    if ($row) $row['content'] = json_decode($row['content'], true);
    echo json_encode(['ok' => true, 'plan' => $row], JSON_UNESCAPED_UNICODE);
} elseif ($a === 'update_plan') {
    // POST with JSON body: {id: int, content: object}
    $body = json_decode(file_get_contents('php://input'), true);
    $id = (int) ($body['id'] ?? 0);
    $content = $body['content'] ?? null;
    if (!$id || !$content) { echo json_encode(['error' => 'id and content required']); exit; }
    $stmt = $db->prepare("UPDATE assigned_plans SET content = ? WHERE id = ?");
    $stmt->execute([json_encode($content, JSON_UNESCAPED_UNICODE), $id]);
    echo json_encode(['ok' => true, 'updated' => $id, 'rows' => $stmt->rowCount()]);
} elseif ($a === 'rise_clients') {
    $r = $db->query("
        SELECT c.id, c.name, c.email, c.plan, c.status,
               (SELECT COUNT(*) FROM assigned_plans ap WHERE ap.client_id = c.id AND ap.active = 1) as active_plans,
               (SELECT GROUP_CONCAT(CONCAT(ap.id, ':', ap.plan_type, ':v', ap.version) SEPARATOR ', ')
                FROM assigned_plans ap WHERE ap.client_id = c.id AND ap.active = 1) as plan_ids
        FROM clients c
        WHERE c.plan = 'rise' AND c.status = 'activo'
        ORDER BY c.id
    ");
    echo json_encode(['ok' => true, 'clients' => $r->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
}
