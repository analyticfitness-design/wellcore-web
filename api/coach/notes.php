<?php
/**
 * /api/coach/notes.php — M18: Notas privadas de coach por cliente
 *
 * GET    ?client_id=N[&note_type=X]  — Lista notas del coach para ese cliente
 * POST   {client_id, note_type, content, is_private}  — Crea nota
 * PUT    ?id=N  {content, note_type, is_private}       — Edita nota (solo dueño)
 * DELETE ?id=N                                          — Borra nota (solo dueño)
 *
 * Notas son PRIVADAS del coach — los clientes nunca las ven.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$validTypes = ['general', 'nutrition', 'training', 'mental'];

// Auth: solo coaches (y superadmin/admin via requireAdminRole) pueden usar este endpoint
// (coaches solo ven sus propias notas — filtrado por coach_id)
$admin = requireAdminRole('coach', 'superadmin', 'admin');
$coachId = (int) $admin['id'];

$db = getDB();

// ===== GET — Lista notas =====
if ($method === 'GET') {
    $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
    if ($clientId <= 0) {
        respondError('client_id requerido', 400);
    }

    // Verificar que el cliente existe
    $chkClient = $db->prepare('SELECT id FROM clients WHERE id = ? LIMIT 1');
    $chkClient->execute([$clientId]);
    if (!$chkClient->fetchColumn()) {
        respondError('Cliente no encontrado', 404);
    }

    $noteType = $_GET['note_type'] ?? null;

    if ($noteType !== null && !in_array($noteType, $validTypes, true)) {
        respondError('note_type invalido. Valores: ' . implode(', ', $validTypes), 400);
    }

    if ($noteType !== null) {
        $stmt = $db->prepare("
            SELECT id, note_type, content, is_private, created_at, updated_at
            FROM coach_notes
            WHERE coach_id = ? AND client_id = ? AND note_type = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$coachId, $clientId, $noteType]);
    } else {
        $stmt = $db->prepare("
            SELECT id, note_type, content, is_private, created_at, updated_at
            FROM coach_notes
            WHERE coach_id = ? AND client_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$coachId, $clientId]);
    }

    $notes = $stmt->fetchAll();

    foreach ($notes as &$n) {
        $n['id']         = (int) $n['id'];
        $n['is_private'] = (bool) $n['is_private'];
    }
    unset($n);

    respond([
        'ok'        => true,
        'client_id' => $clientId,
        'count'     => count($notes),
        'notes'     => $notes,
    ]);
}

// ===== POST — Crear nota =====
if ($method === 'POST') {
    $body     = getJsonBody();
    $clientId = isset($body['client_id']) ? (int) $body['client_id'] : 0;
    $noteType = $body['note_type'] ?? 'general';
    $content  = trim($body['content'] ?? '');
    $private  = isset($body['is_private']) ? (bool) $body['is_private'] : true;

    if ($clientId <= 0) {
        respondError('client_id requerido', 400);
    }
    if (!in_array($noteType, $validTypes, true)) {
        respondError('note_type invalido. Valores: ' . implode(', ', $validTypes), 400);
    }
    if ($content === '') {
        respondError('content no puede estar vacio', 400);
    }
    if (mb_strlen($content) > 5000) {
        respondError('content supera el limite de 5000 caracteres', 400);
    }

    // Verificar que el cliente existe
    $check = $db->prepare("SELECT id FROM clients WHERE id = ? LIMIT 1");
    $check->execute([$clientId]);
    if (!$check->fetch()) {
        respondError('Cliente no encontrado', 404);
    }

    $stmt = $db->prepare("
        INSERT INTO coach_notes (coach_id, client_id, note_type, content, is_private)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$coachId, $clientId, $noteType, $content, $private ? 1 : 0]);
    $newId = (int) $db->lastInsertId();

    respond([
        'ok'      => true,
        'note_id' => $newId,
        'message' => 'Nota creada correctamente',
    ], 201);
}

// ===== PUT — Editar nota =====
if ($method === 'PUT') {
    $noteId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($noteId <= 0) {
        respondError('id de nota requerido en query string (?id=N)', 400);
    }

    // Verificar que la nota pertenece a este coach
    $check = $db->prepare("SELECT id FROM coach_notes WHERE id = ? AND coach_id = ? LIMIT 1");
    $check->execute([$noteId, $coachId]);
    if (!$check->fetch()) {
        respondError('Nota no encontrada o no tienes permiso para editarla', 404);
    }

    $body     = getJsonBody();
    $content  = isset($body['content'])    ? trim($body['content'])    : null;
    $noteType = isset($body['note_type'])  ? $body['note_type']        : null;
    $private  = isset($body['is_private']) ? (bool) $body['is_private'] : null;

    if ($content !== null && $content === '') {
        respondError('content no puede estar vacio', 400);
    }
    if ($content !== null && mb_strlen($content) > 5000) {
        respondError('content supera el limite de 5000 caracteres', 400);
    }
    if ($noteType !== null && !in_array($noteType, $validTypes, true)) {
        respondError('note_type invalido. Valores: ' . implode(', ', $validTypes), 400);
    }

    // Build update dynamically — only update provided fields
    $sets   = [];
    $params = [];

    if ($content !== null) {
        $sets[]   = 'content = ?';
        $params[] = $content;
    }
    if ($noteType !== null) {
        $sets[]   = 'note_type = ?';
        $params[] = $noteType;
    }
    if ($private !== null) {
        $sets[]   = 'is_private = ?';
        $params[] = $private ? 1 : 0;
    }

    if (empty($sets)) {
        respondError('Ningun campo para actualizar', 400);
    }

    $params[] = $noteId;
    $params[] = $coachId;

    $stmt = $db->prepare("
        UPDATE coach_notes SET " . implode(', ', $sets) . "
        WHERE id = ? AND coach_id = ?
    ");
    $stmt->execute($params);

    respond(['ok' => true, 'message' => 'Nota actualizada correctamente']);
}

// ===== DELETE — Borrar nota =====
if ($method === 'DELETE') {
    $noteId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($noteId <= 0) {
        respondError('id de nota requerido en query string (?id=N)', 400);
    }

    $stmt = $db->prepare("DELETE FROM coach_notes WHERE id = ? AND coach_id = ?");
    $stmt->execute([$noteId, $coachId]);
    $deleted = $stmt->rowCount();

    if (!$deleted) {
        respondError('Nota no encontrada o no tienes permiso para borrarla', 404);
    }

    respond(['ok' => true, 'message' => 'Nota eliminada correctamente']);
}

respondError('Metodo no permitido', 405);
