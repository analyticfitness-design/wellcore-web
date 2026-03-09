<?php
/**
 * WellCore Fitness — M15: Plan Templates CRUD
 * ============================================================
 * GET    /api/plans/templates.php          — Lista plantillas del coach
 * GET    /api/plans/templates.php?plan_type=esencial — Filtra por tipo
 * POST   /api/plans/templates.php          — Crea nueva plantilla
 * PUT    /api/plans/templates.php?id=N     — Actualiza plantilla propia
 * DELETE /api/plans/templates.php?id=N     — Soft-delete plantilla propia
 *
 * Auth: authenticateAdmin() — solo coaches y admins pueden usar este endpoint
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ──────────────────────────────────────────────────────────────
// GET — Listar plantillas del coach autenticado
// ──────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $admin = authenticateAdmin();
    $db    = getDB();

    $sql    = "SELECT id, coach_id, title, description, plan_type, methodology,
                      template_data, is_active, created_at, updated_at
               FROM plan_templates
               WHERE coach_id = ? AND is_active = 1";
    $params = [$admin['id']];

    $planType = $_GET['plan_type'] ?? null;
    if ($planType !== null) {
        $validTypes = ['esencial', 'metodo', 'rise', 'elite'];
        if (!in_array($planType, $validTypes, true)) {
            respondError('plan_type inválido. Valores permitidos: esencial, metodo, rise, elite', 400);
        }
        $sql    .= ' AND plan_type = ?';
        $params[] = $planType;
    }

    $sql .= ' ORDER BY created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Decodificar template_data JSON para cada fila
    foreach ($rows as &$row) {
        $row['template_data'] = json_decode($row['template_data'] ?? 'null', true);
    }
    unset($row);

    respond(['templates' => $rows, 'total' => count($rows)]);
}

// ──────────────────────────────────────────────────────────────
// POST — Crear nueva plantilla
// ──────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $admin = authenticateAdmin();
    $body  = getJsonBody();
    $db    = getDB();

    // Validaciones
    $title = trim($body['title'] ?? '');
    if ($title === '') {
        respondError('El campo title es requerido', 400);
    }
    if (mb_strlen($title) > 200) {
        respondError('El campo title no puede superar 200 caracteres', 400);
    }

    $validPlanTypes = ['esencial', 'metodo', 'rise', 'elite'];
    $planType = $body['plan_type'] ?? '';
    if (!in_array($planType, $validPlanTypes, true)) {
        respondError('plan_type inválido. Valores permitidos: esencial, metodo, rise, elite', 400);
    }

    $templateData = $body['template_data'] ?? null;
    if ($templateData === null) {
        respondError('El campo template_data es requerido', 400);
    }
    if (!is_array($templateData) && !is_object($templateData)) {
        respondError('template_data debe ser un objeto JSON válido', 400);
    }
    $templateDataJson = json_encode($templateData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($templateDataJson === false) {
        respondError('template_data contiene valores no serializables', 400);
    }

    $description = isset($body['description']) ? trim($body['description']) : null;
    $methodology = isset($body['methodology']) ? trim($body['methodology']) : null;

    $stmt = $db->prepare("
        INSERT INTO plan_templates (coach_id, title, description, plan_type, methodology, template_data, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $admin['id'],
        $title,
        $description ?: null,
        $planType,
        $methodology ?: null,
        $templateDataJson,
    ]);
    $newId = (int) $db->lastInsertId();

    // Devolver la plantilla recién creada
    $stmt2 = $db->prepare("SELECT id, coach_id, title, description, plan_type, methodology, template_data, is_active, created_at FROM plan_templates WHERE id = ?");
    $stmt2->execute([$newId]);
    $template = $stmt2->fetch();
    $template['template_data'] = json_decode($template['template_data'] ?? 'null', true);

    respond(['template' => $template, 'message' => 'Plantilla creada correctamente'], 201);
}

// ──────────────────────────────────────────────────────────────
// PUT — Actualizar plantilla existente
// ──────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $admin = authenticateAdmin();
    $db    = getDB();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        respondError('Parámetro id requerido en la URL (?id=N)', 400);
    }

    // Verificar que la plantilla existe y pertenece a este coach
    $stmt = $db->prepare("SELECT id, coach_id FROM plan_templates WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        respondError('Plantilla no encontrada', 404);
    }
    if ((int) $existing['coach_id'] !== (int) $admin['id']) {
        respondError('No tienes permisos para editar esta plantilla', 403);
    }

    $body   = getJsonBody();
    $fields = [];
    $params = [];

    if (isset($body['title'])) {
        $title = trim($body['title']);
        if ($title === '') {
            respondError('El campo title no puede estar vacío', 400);
        }
        if (mb_strlen($title) > 200) {
            respondError('El campo title no puede superar 200 caracteres', 400);
        }
        $fields[] = 'title = ?';
        $params[] = $title;
    }

    if (isset($body['description'])) {
        $fields[] = 'description = ?';
        $params[] = trim($body['description']) ?: null;
    }

    if (isset($body['plan_type'])) {
        $validPlanTypes = ['esencial', 'metodo', 'rise', 'elite'];
        if (!in_array($body['plan_type'], $validPlanTypes, true)) {
            respondError('plan_type inválido. Valores permitidos: esencial, metodo, rise, elite', 400);
        }
        $fields[] = 'plan_type = ?';
        $params[] = $body['plan_type'];
    }

    if (isset($body['methodology'])) {
        $fields[] = 'methodology = ?';
        $params[] = trim($body['methodology']) ?: null;
    }

    if (isset($body['template_data'])) {
        $templateData = $body['template_data'];
        if (!is_array($templateData) && !is_object($templateData)) {
            respondError('template_data debe ser un objeto JSON válido', 400);
        }
        $templateDataJson = json_encode($templateData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($templateDataJson === false) {
            respondError('template_data contiene valores no serializables', 400);
        }
        $fields[] = 'template_data = ?';
        $params[] = $templateDataJson;
    }

    if (empty($fields)) {
        respondError('No se enviaron campos para actualizar', 400);
    }

    $fields[] = 'updated_at = NOW()';
    $params[] = $id;
    $db->prepare("UPDATE plan_templates SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

    // Devolver la plantilla actualizada
    $stmt2 = $db->prepare("SELECT id, coach_id, title, description, plan_type, methodology, template_data, is_active, created_at, updated_at FROM plan_templates WHERE id = ?");
    $stmt2->execute([$id]);
    $template = $stmt2->fetch();
    $template['template_data'] = json_decode($template['template_data'] ?? 'null', true);

    respond(['template' => $template, 'message' => 'Plantilla actualizada correctamente']);
}

// ──────────────────────────────────────────────────────────────
// DELETE — Soft-delete (is_active = 0)
// ──────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $admin = authenticateAdmin();
    $db    = getDB();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        respondError('Parámetro id requerido en la URL (?id=N)', 400);
    }

    // Verificar que la plantilla existe y pertenece a este coach
    $stmt = $db->prepare("SELECT id, coach_id FROM plan_templates WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        respondError('Plantilla no encontrada', 404);
    }
    if ((int) $existing['coach_id'] !== (int) $admin['id']) {
        respondError('No tienes permisos para eliminar esta plantilla', 403);
    }

    $db->prepare("UPDATE plan_templates SET is_active = 0 WHERE id = ?")->execute([$id]);

    respond(['message' => 'Plantilla eliminada correctamente', 'id' => $id]);
}

// ──────────────────────────────────────────────────────────────
// Método no permitido
// ──────────────────────────────────────────────────────────────
respondError('Método no permitido', 405);
