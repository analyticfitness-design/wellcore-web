<?php
/**
 * WellCore Fitness — Admin AI Prompts CRUD
 *
 * GET    /api/admin/ai-prompt          → listar todos los prompts
 * GET    /api/admin/ai-prompt?type=X   → obtener prompt por tipo
 * PUT    /api/admin/ai-prompt?type=X   → actualizar prompt
 * DELETE /api/admin/ai-prompt?type=X   → restaurar prompt a default
 *
 * PUT body: { system_prompt, user_prompt_template? }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'PUT', 'DELETE');
$admin = authenticateAdmin();
$db    = getDB();

$VALID_TYPES = ['entrenamiento', 'nutricion', 'habitos', 'ticket_response', 'analisis'];

$DEFAULT_PROMPTS = [
    'entrenamiento' => [
        'display_name'         => 'Entrenamiento',
        'system_prompt'        => 'Eres un entrenador de alto rendimiento de WellCore Fitness. Genera programas basados en ciencia con periodización real (RIR 3/2/1/4 por semana). Devuelve JSON estricto.',
        'user_prompt_template' => null,
    ],
    'nutricion' => [
        'display_name'         => 'Nutrición',
        'system_prompt'        => 'Eres un nutricionista deportivo de WellCore Fitness. Calcula TDEE con Mifflin-St Jeor, distribuye macros por evidencia (proteína 1.6-2.2g/kg) y crea planes realistas. Devuelve JSON estricto.',
        'user_prompt_template' => null,
    ],
    'habitos' => [
        'display_name'         => 'Hábitos',
        'system_prompt'        => 'Eres un coach de bienestar de WellCore Fitness. Diseña planes de hábitos progresivos y sostenibles basados en habit stacking. Devuelve JSON estricto.',
        'user_prompt_template' => null,
    ],
    'ticket_response' => [
        'display_name'         => 'Respuesta de Ticket',
        'system_prompt'        => 'Eres el equipo técnico de WellCore Fitness. Redacta respuestas directas, técnicas y útiles a los tickets de coaches y clientes. El coach revisa antes de enviar.',
        'user_prompt_template' => null,
    ],
    'analisis' => [
        'display_name'         => 'Análisis de Progreso',
        'system_prompt'        => 'Eres un analista de rendimiento de WellCore Fitness. Interpreta métricas, detecta estancamiento y genera informes accionables con semáforo verde/amarillo/rojo. Devuelve JSON estricto.',
        'user_prompt_template' => null,
    ],
];

// ── GET ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = $_GET['type'] ?? '';

    if ($type !== '') {
        if (!in_array($type, $VALID_TYPES, true)) respondError('Tipo inválido', 400);

        $stmt = $db->prepare("SELECT * FROM ai_prompts WHERE type = ?");
        $stmt->execute([$type]);
        $prompt = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fallback a default si no existe en DB
        if (!$prompt) {
            $prompt = array_merge(['type' => $type], $DEFAULT_PROMPTS[$type]);
            $prompt['id'] = null;
            $prompt['updated_by'] = null;
            $prompt['updated_at'] = null;
        }

        respond(['prompt' => $prompt]);
    }

    // Listar todos
    $rows = $db->query("SELECT * FROM ai_prompts ORDER BY type")->fetchAll(PDO::FETCH_ASSOC);

    // Incluir defaults para tipos que no estén en DB
    $existing = array_column($rows, null, 'type');
    $all = [];
    foreach ($VALID_TYPES as $t) {
        if (isset($existing[$t])) {
            $all[] = $existing[$t];
        } else {
            $all[] = array_merge(
                ['type' => $t, 'id' => null, 'updated_by' => null, 'updated_at' => null],
                $DEFAULT_PROMPTS[$t]
            );
        }
    }

    respond(['prompts' => $all]);
}

// ── PUT ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $type = $_GET['type'] ?? '';
    if (!in_array($type, $VALID_TYPES, true)) respondError('Tipo inválido', 400);

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $sysPrompt = trim($body['system_prompt'] ?? '');
    if ($sysPrompt === '') respondError('system_prompt es requerido', 400);

    $userTemplate = $body['user_prompt_template'] ?? null;
    $displayName  = $body['display_name'] ?? ($DEFAULT_PROMPTS[$type]['display_name'] ?? $type);
    $adminId      = $admin['id'] ?? null;

    // Upsert
    $stmt = $db->prepare("
        INSERT INTO ai_prompts (type, display_name, system_prompt, user_prompt_template, updated_by)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            display_name          = VALUES(display_name),
            system_prompt         = VALUES(system_prompt),
            user_prompt_template  = VALUES(user_prompt_template),
            updated_by            = VALUES(updated_by)
    ");
    $stmt->execute([$type, $displayName, $sysPrompt, $userTemplate, $adminId]);

    respond(['updated' => true, 'type' => $type]);
}

// ── DELETE (restaurar default) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $type = $_GET['type'] ?? '';
    if (!in_array($type, $VALID_TYPES, true)) respondError('Tipo inválido', 400);

    // Borrar customización → próxima lectura usará hardcoded default
    $db->prepare("DELETE FROM ai_prompts WHERE type = ?")->execute([$type]);

    respond(['reset' => true, 'type' => $type, 'default' => $DEFAULT_PROMPTS[$type]]);
}
