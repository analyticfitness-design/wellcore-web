<?php
// GET /api/clients/profile  → get own profile
// PUT /api/clients/profile  → update own profile

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'PUT');
$client = authenticateClient();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT c.id, c.client_code, c.name, c.email, c.plan, c.status, c.fecha_inicio,
               p.edad, p.peso, p.altura, p.objetivo, p.ciudad, p.whatsapp,
               p.nivel, p.lugar_entreno, p.dias_disponibles, p.restricciones, p.macros,
               p.bio, p.avatar_url, p.dashboard_video_url
        FROM clients c
        LEFT JOIN client_profiles p ON p.client_id = c.id
        WHERE c.id = ?
    ");
    $stmt->execute([$client['id']]);
    $data = $stmt->fetch();

    // Decode JSON fields
    if ($data) {
        $data['dias_disponibles'] = json_decode($data['dias_disponibles'] ?? '[]');
        $data['macros'] = json_decode($data['macros'] ?? 'null');
    }

    respond(['profile' => $data]);
}

// PUT — update profile
$body = getJsonBody();
$allowed = ['edad','peso','altura','objetivo','ciudad','whatsapp','nivel','lugar_entreno','dias_disponibles','restricciones','macros','bio'];

$checkStmt = $db->prepare("SELECT id FROM client_profiles WHERE client_id = ?");
$checkStmt->execute([$client['id']]);
$exists = $checkStmt->fetchColumn();

if ($exists) {
    $updateFields = [];
    $updateValues = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $val = $body[$field];
            if (in_array($field, ['dias_disponibles', 'macros'])) {
                $val = json_encode($val);
            }
            $updateFields[] = "$field = ?";
            $updateValues[] = $val;
        }
    }
    if (empty($updateFields)) {
        respondError('No valid fields to update', 422);
    }
    $updateValues[] = $client['id'];
    $stmt = $db->prepare("UPDATE client_profiles SET " . implode(', ', $updateFields) . " WHERE client_id = ?");
    $stmt->execute($updateValues);
} else {
    $insertCols         = ['client_id'];
    $insertVals         = [$client['id']];
    $insertPlaceholders = ['?'];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $insertCols[] = $field;
            $val = $body[$field];
            if (in_array($field, ['dias_disponibles', 'macros'])) {
                $val = json_encode($val);
            }
            $insertVals[]         = $val;
            $insertPlaceholders[] = '?';
        }
    }
    if (count($insertCols) === 1) {
        respondError('No valid fields to update', 422);
    }
    $stmt = $db->prepare(
        "INSERT INTO client_profiles (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $insertPlaceholders) . ")"
    );
    $stmt->execute($insertVals);
}

// Also update name in clients table if provided
if (isset($body['name'])) {
    $db->prepare("UPDATE clients SET name = ? WHERE id = ?")->execute([$body['name'], $client['id']]);
}

respond(['message' => 'Perfil actualizado correctamente']);
