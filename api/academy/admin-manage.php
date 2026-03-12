<?php
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cors.php';

requireMethod('GET', 'POST', 'PUT', 'DELETE');

// Admin-only endpoint
$admin = authenticateAdmin();
$adminId = (int)$admin['id'];

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ===== GET — List all content for admin panel =====
if ($method === 'GET') {
    $stmt = $db->prepare("
        SELECT id, title, content_type, is_published, plan_access,
               tags, created_at, updated_at, created_by
        FROM academy_content
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['plan_access']  = json_decode($row['plan_access'] ?? '[]', true) ?? [];
        $row['is_published'] = (bool)$row['is_published'];
    }
    unset($row);

    respond(['ok' => true, 'items' => $rows]);
}

// ===== POST — Create content =====
if ($method === 'POST') {
    $body = getJsonBody();

    $title       = trim($body['title'] ?? '');
    $description = trim($body['description'] ?? '');
    $contentType = trim($body['content_type'] ?? '');
    $contentUrl  = trim($body['content_url'] ?? '');
    $contentBody = $body['content_body'] ?? '';
    $planAccess  = $body['plan_access'] ?? [];
    $tags        = trim($body['tags'] ?? '');
    $isPublished = !empty($body['is_published']) ? 1 : 0;

    // Validation
    if ($title === '') {
        respondError('El título es requerido', 400);
    }

    $validTypes = ['video', 'pdf', 'article', 'exercise'];
    if (!in_array($contentType, $validTypes, true)) {
        respondError('Tipo de contenido inválido. Valores permitidos: ' . implode(', ', $validTypes), 400);
    }

    if (!is_array($planAccess)) {
        respondError('plan_access debe ser un array', 400);
    }
    $validPlans = ['esencial', 'metodo', 'rise', 'elite'];
    foreach ($planAccess as $p) {
        if (!in_array($p, $validPlans, true)) {
            respondError("Plan inválido: $p. Valores permitidos: " . implode(', ', $validPlans), 400);
        }
    }

    $planAccessJson = json_encode(array_values($planAccess));

    $stmt = $db->prepare("
        INSERT INTO academy_content
            (title, description, content_type, content_url, content_body, plan_access, tags, is_published, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $title, $description, $contentType,
        $contentUrl ?: null,
        $contentBody ?: null,
        $planAccessJson,
        $tags ?: null,
        $isPublished,
        $adminId,
    ]);

    $newId = (int)$db->lastInsertId();

    respond(['ok' => true, 'id' => $newId, 'message' => 'Contenido creado'], 201);
}

// ===== PUT — Update content =====
if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        respondError('Parámetro id requerido', 400);
    }

    // Verify exists
    $check = $db->prepare("SELECT id FROM academy_content WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        respondError('Contenido no encontrado', 404);
    }

    $body = getJsonBody();

    $sets   = [];
    $params = [];

    $validTypes = ['video', 'pdf', 'article', 'exercise'];
    $validPlans = ['esencial', 'metodo', 'rise', 'elite'];

    if (array_key_exists('title', $body)) {
        $title = trim($body['title']);
        if ($title === '') respondError('El título no puede estar vacío', 400);
        $sets[]   = 'title = ?';
        $params[] = $title;
    }
    if (array_key_exists('description', $body)) {
        $sets[]   = 'description = ?';
        $params[] = trim($body['description']);
    }
    if (array_key_exists('content_type', $body)) {
        $ct = trim($body['content_type']);
        if (!in_array($ct, $validTypes, true)) {
            respondError('Tipo de contenido inválido', 400);
        }
        $sets[]   = 'content_type = ?';
        $params[] = $ct;
    }
    if (array_key_exists('content_url', $body)) {
        $sets[]   = 'content_url = ?';
        $params[] = $body['content_url'] ?: null;
    }
    if (array_key_exists('content_body', $body)) {
        $sets[]   = 'content_body = ?';
        $params[] = $body['content_body'] ?: null;
    }
    if (array_key_exists('plan_access', $body)) {
        $pa = $body['plan_access'];
        if (!is_array($pa)) respondError('plan_access debe ser un array', 400);
        foreach ($pa as $p) {
            if (!in_array($p, $validPlans, true)) {
                respondError("Plan inválido: $p", 400);
            }
        }
        $sets[]   = 'plan_access = ?';
        $params[] = json_encode(array_values($pa));
    }
    if (array_key_exists('tags', $body)) {
        $sets[]   = 'tags = ?';
        $params[] = trim($body['tags']) ?: null;
    }
    if (array_key_exists('is_published', $body)) {
        $sets[]   = 'is_published = ?';
        $params[] = !empty($body['is_published']) ? 1 : 0;
    }

    if (empty($sets)) {
        respondError('No se proporcionaron campos para actualizar', 400);
    }

    $sets[]   = 'updated_at = NOW()';
    $params[] = $id;

    $sql = 'UPDATE academy_content SET ' . implode(', ', $sets) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    respond(['ok' => true, 'message' => 'Contenido actualizado']);
}

// ===== DELETE — Remove content =====
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        respondError('Parámetro id requerido', 400);
    }

    $check = $db->prepare("SELECT id FROM academy_content WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        respondError('Contenido no encontrado', 404);
    }

    $stmt = $db->prepare("DELETE FROM academy_content WHERE id = ?");
    $stmt->execute([$id]);

    respond(['ok' => true, 'message' => 'Contenido eliminado']);
}
