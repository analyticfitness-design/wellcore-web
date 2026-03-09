<?php
/**
 * GET  /api/pods/list  — Lista pods del cliente o coach autenticado
 * POST /api/pods/list  — Coach crea un pod nuevo
 *
 * Responde: { pods[] } o { id, name }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Puede ser cliente o coach
    $coach_id  = null;
    $client_id = null;

    try {
        $client    = authenticateClient();
        $client_id = $client['id'];
    } catch (\Exception $e) {
        $coach    = authenticateCoach();
        $coach_id = $coach['id'];
    }

    if ($client_id) {
        // Pods a los que pertenece el cliente
        $stmt = $db->prepare("
            SELECT ap.id, ap.name, ap.description, ap.max_members,
                   COUNT(pm2.id) AS member_count
            FROM accountability_pods ap
            JOIN pod_members pm ON pm.pod_id = ap.id AND pm.client_id = ?
            LEFT JOIN pod_members pm2 ON pm2.pod_id = ap.id
            WHERE ap.is_active = 1
            GROUP BY ap.id, ap.name, ap.description, ap.max_members
        ");
        $stmt->execute([$client_id]);
    } else {
        // Todos los pods del coach con cantidad de miembros
        $stmt = $db->prepare("
            SELECT ap.id, ap.name, ap.description, ap.max_members,
                   COUNT(pm.id) AS member_count
            FROM accountability_pods ap
            LEFT JOIN pod_members pm ON pm.pod_id = ap.id
            WHERE ap.coach_id = ? AND ap.is_active = 1
            GROUP BY ap.id, ap.name, ap.description, ap.max_members
            ORDER BY ap.created_at DESC
        ");
        $stmt->execute([$coach_id]);
    }

    respond(['pods' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

} elseif ($method === 'POST') {
    $coach    = authenticateCoach();
    $coach_id = $coach['id'];
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];

    $action = $body['action'] ?? 'create';

    if ($action === 'create') {
        $name        = trim($body['name'] ?? '');
        $description = trim($body['description'] ?? '');
        $max_members = min(15, max(2, (int)($body['max_members'] ?? 8)));

        if (!$name) respondError('name es requerido', 400);

        $db->prepare("
            INSERT INTO accountability_pods (coach_id, name, description, max_members)
            VALUES (?, ?, ?, ?)
        ")->execute([$coach_id, $name, $description ?: null, $max_members]);

        respond(['id' => (int)$db->lastInsertId(), 'name' => $name]);

    } elseif ($action === 'add_member') {
        $pod_id    = (int)($body['pod_id'] ?? 0);
        $client_id = $body['client_id'] ?? '';

        if (!$pod_id || !$client_id) respondError('pod_id y client_id requeridos', 400);

        // Verificar que el pod pertenece al coach
        $pod = $db->prepare("SELECT max_members FROM accountability_pods WHERE id = ? AND coach_id = ?");
        $pod->execute([$pod_id, $coach_id]);
        $pod_row = $pod->fetch(PDO::FETCH_ASSOC);
        if (!$pod_row) respondError('Pod no encontrado', 404);

        // Verificar capacidad
        $count = $db->prepare("SELECT COUNT(*) FROM pod_members WHERE pod_id = ?");
        $count->execute([$pod_id]);
        if ((int)$count->fetchColumn() >= (int)$pod_row['max_members']) {
            respondError('Pod lleno', 409);
        }

        $db->prepare("INSERT IGNORE INTO pod_members (pod_id, client_id) VALUES (?, ?)")
           ->execute([$pod_id, $client_id]);

        respond(['success' => true]);

    } elseif ($action === 'remove_member') {
        $pod_id    = (int)($body['pod_id'] ?? 0);
        $client_id = $body['client_id'] ?? '';

        $db->prepare("DELETE FROM pod_members WHERE pod_id = ? AND client_id = ?")
           ->execute([$pod_id, $client_id]);

        respond(['success' => true]);
    }

    respondError('Acción no válida', 400);

} else {
    respondError('Método no permitido', 405);
}
