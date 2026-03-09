<?php
/**
 * GET  /api/coach/audio       — Lista audios disponibles para el cliente
 * POST /api/coach/audio       — Coach crea/edita audio tip (auth coach)
 * DELETE /api/coach/audio?id= — Coach elimina audio (auth coach)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Coach gestiona sus propios audios con ?manage=1
    if (!empty($_GET['manage'])) {
        $coach    = authenticateCoach();
        $coach_id = $coach['id'];
        $stmt     = $db->prepare("SELECT id, title, audio_url, duration_sec, category, plan_access, sort_order, is_active FROM coach_audio WHERE coach_id = ? ORDER BY sort_order ASC, created_at DESC");
        $stmt->execute([$coach_id]);
        respond(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // Acceso cliente — filtra por plan y coach_id del cliente
    $client    = authenticateClient();
    $client_id = $client['id'];
    $plan      = strtolower($client['plan'] ?? 'esencial');

    $coach_row = $db->prepare("SELECT coach_id FROM clients WHERE id = ?");
    $coach_row->execute([$client_id]);
    $coach_id = $coach_row->fetchColumn();

    if (!$coach_id) {
        respond(['items' => []]);
    }

    $stmt = $db->prepare("
        SELECT id, title, audio_url, duration_sec, category, plan_access, sort_order
        FROM coach_audio
        WHERE coach_id = ? AND is_active = 1
        ORDER BY sort_order ASC, created_at DESC
    ");
    $stmt->execute([$coach_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filtrar por plan_access
    $items = [];
    foreach ($rows as $r) {
        $access = json_decode($r['plan_access'] ?? 'null', true);
        if ($access === null || in_array($plan, $access, true)) {
            unset($r['plan_access']); // no exponer al cliente
            $items[] = $r;
        }
    }

    respond(['items' => $items]);

} elseif ($method === 'POST') {
    $coach    = authenticateCoach();
    $coach_id = $coach['id'];
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];

    $id          = (int)($body['id'] ?? 0);
    $title       = trim($body['title'] ?? '');
    $audio_url   = trim($body['audio_url'] ?? '');
    $duration    = (int)($body['duration_sec'] ?? 0);
    $category    = trim($body['category'] ?? 'general');
    $plan_access = $body['plan_access'] ?? null;
    $sort_order  = (int)($body['sort_order'] ?? 0);
    $is_active   = isset($body['is_active']) ? (int)(bool)$body['is_active'] : 1;

    if (!$title || !$audio_url) {
        respondError('title y audio_url son requeridos', 400);
    }

    $access_json = $plan_access ? json_encode($plan_access) : null;

    if ($id > 0) {
        // Actualizar
        $db->prepare("
            UPDATE coach_audio SET title=?, audio_url=?, duration_sec=?, category=?, plan_access=?, sort_order=?, is_active=?
            WHERE id = ? AND coach_id = ?
        ")->execute([$title, $audio_url, $duration, $category, $access_json, $sort_order, $is_active, $id, $coach_id]);
        respond(['success' => true, 'id' => $id]);
    } else {
        // Crear
        $db->prepare("
            INSERT INTO coach_audio (coach_id, title, audio_url, duration_sec, category, plan_access, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$coach_id, $title, $audio_url, $duration, $category, $access_json, $sort_order, $is_active]);
        respond(['success' => true, 'id' => (int)$db->lastInsertId()]);
    }

} elseif ($method === 'DELETE') {
    $coach    = authenticateCoach();
    $coach_id = $coach['id'];
    $id       = (int)($_GET['id'] ?? 0);

    if (!$id) respondError('id requerido', 400);

    $db->prepare("UPDATE coach_audio SET is_active = 0 WHERE id = ? AND coach_id = ?")
       ->execute([$id, $coach_id]);

    respond(['success' => true]);

} else {
    respondError('Método no permitido', 405);
}
