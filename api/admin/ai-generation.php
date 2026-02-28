<?php
/**
 * WellCore Fitness — Admin AI Generation Detail
 *
 * GET  /api/admin/ai-generation?id=X  → detalle completo de una generación
 * PUT  /api/admin/ai-generation?id=X  → actualizar status / coach_notes
 * DELETE /api/admin/ai-generation?id=X → eliminar registro
 *
 * PUT body: { status?, coach_notes?, approved_by? }
 *   status aceptados: approved | rejected | completed | pending
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'PUT', 'DELETE');
$admin = authenticateAdmin();
$db    = getDB();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) respondError('Se requiere ?id=', 400);

// ── GET ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT
            g.*,
            c.name    AS client_name,
            c.email   AS client_email,
            c.plan    AS client_plan
        FROM ai_generations g
        LEFT JOIN clients c ON c.id = g.client_id
        WHERE g.id = ?
    ");
    $stmt->execute([$id]);
    $gen = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$gen) respondError('Generación no encontrada', 404);

    // Decodificar JSON si está guardado
    if ($gen['parsed_json']) {
        $decoded = json_decode($gen['parsed_json'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $gen['parsed_json'] = $decoded;
        }
    }

    // Si es ticket_response, traer datos del ticket
    if ($gen['ticket_id']) {
        try {
            $tStmt = $db->prepare("
                SELECT id, status, ticket_type, client_name, description,
                       priority, ai_status, ai_draft, created_at
                FROM tickets WHERE id = ?
            ");
            $tStmt->execute([$gen['ticket_id']]);
            $gen['ticket'] = $tStmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $ignore) {}
    }

    respond(['generation' => $gen]);
}

// ── PUT ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $validStatuses = ['queued', 'pending', 'completed', 'failed', 'approved', 'rejected'];
    $sets   = [];
    $params = [];

    if (isset($body['status'])) {
        if (!in_array($body['status'], $validStatuses, true)) {
            respondError('status inválido', 400);
        }
        $sets[]   = 'status = ?';
        $params[] = $body['status'];

        if ($body['status'] === 'approved') {
            $sets[]   = 'approved_at = NOW()';
            $sets[]   = 'approved_by = ?';
            $params[] = $admin['id'] ?? null;
        }
    }

    if (array_key_exists('coach_notes', $body)) {
        $sets[]   = 'coach_notes = ?';
        $params[] = $body['coach_notes'];
    }

    if (empty($sets)) respondError('Nada que actualizar', 400);

    $params[] = $id;
    $db->prepare("UPDATE ai_generations SET " . implode(', ', $sets) . " WHERE id = ?")
       ->execute($params);

    // Si aprobando ticket_response, sincronizar ai_draft en tickets
    if (($body['status'] ?? '') === 'approved') {
        try {
            $rawText = $db->prepare("SELECT raw_response, ticket_id FROM ai_generations WHERE id = ?");
            $rawText->execute([$id]);
            $row = $rawText->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['ticket_id']) {
                $db->prepare("UPDATE tickets SET ai_status = 'approved' WHERE id = ?")
                   ->execute([$row['ticket_id']]);
            }
        } catch (\Throwable $ignore) {}
    }

    respond(['updated' => true, 'id' => $id]);
}

// ── DELETE ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $db->prepare("DELETE FROM ai_generations WHERE id = ?")->execute([$id]);
    respond(['deleted' => true, 'id' => $id]);
}
