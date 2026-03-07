<?php
/**
 * TEMPORAL — Query any client's RISE intake data
 * DELETE after use.
 * GET /api/admin/tmp-query-client.php?client_id=11
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
$db = getDB();
$cid = (int) ($_GET['client_id'] ?? 0);
if (!$cid) { echo json_encode(['error' => 'client_id required']); exit; }

$client = $db->prepare("SELECT id, name, email, plan, objetivo, lugar_entreno, dias_disponibles, nivel FROM clients WHERE id = ?");
$client->execute([$cid]);
$c = $client->fetch(PDO::FETCH_ASSOC);

$rise = $db->prepare("SELECT id, personalized_program, experience_level, training_location, gender, status FROM rise_programs WHERE client_id = ? ORDER BY id DESC LIMIT 1");
$rise->execute([$cid]);
$r = $rise->fetch(PDO::FETCH_ASSOC);

$plans = $db->query("SELECT id, plan_type, active, ai_generation_id, created_at FROM assigned_plans WHERE client_id = $cid ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$gens = $db->query("SELECT id, type, status, prompt_tokens, completion_tokens, created_at FROM ai_generations WHERE client_id = $cid ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'client' => $c,
    'rise_program' => $r ? array_merge($r, ['intake' => json_decode($r['personalized_program'] ?? '{}', true)]) : null,
    'plans' => $plans,
    'generations' => $gens,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
