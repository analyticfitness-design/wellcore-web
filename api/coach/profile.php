<?php
/**
 * WellCore Fitness — Coach Profile (Self-Service)
 * GET  /api/coach/profile.php  — Retrieve own profile
 * PUT  /api/coach/profile.php  — Update own profile
 * Requires: Bearer coach token
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'PUT');
$coach = authenticateCoach();
$adminId = (int) $coach['user_id'];
$db = getDB();

// ─── GET: Return coach profile + achievements ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT cp.*, a.name AS coach_name, a.username
        FROM coach_profiles cp
        JOIN admins a ON a.id = cp.admin_id
        WHERE cp.admin_id = ?
    ");
    $stmt->execute([$adminId]);
    $profile = $stmt->fetch();

    if (!$profile) {
        respondError('Coach profile not found', 404);
    }

    // Decode specializations JSON
    if (isset($profile['specializations']) && is_string($profile['specializations'])) {
        $decoded = json_decode($profile['specializations'], true);
        $profile['specializations'] = $decoded !== null ? $decoded : [];
    }

    // Fetch achievements
    $stmtA = $db->prepare("
        SELECT id, achievement_type, label, icon, earned_at
        FROM coach_achievements
        WHERE admin_id = ?
        ORDER BY earned_at DESC
    ");
    $stmtA->execute([$adminId]);
    $achievements = $stmtA->fetchAll();

    respond([
        'ok'           => true,
        'profile'      => $profile,
        'achievements' => $achievements,
    ]);
}

// ─── PUT: Update coach profile ──────────────────────────────────────────────
$data = getJsonBody();
if (empty($data)) {
    respondError('No data provided', 400);
}

$allowed = [
    'bio', 'city', 'experience', 'specializations',
    'whatsapp', 'instagram', 'color_primary', 'public_visible',
];

$fields = [];
$values = [];

foreach ($allowed as $field) {
    if (!array_key_exists($field, $data)) {
        continue;
    }

    $val = $data[$field];

    // Validate color_primary
    if ($field === 'color_primary') {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $val)) {
            respondError('color_primary debe ser un color hex valido (#RRGGBB)', 400);
        }
    }

    // Encode specializations array to JSON
    if ($field === 'specializations' && is_array($val)) {
        $val = json_encode($val, JSON_UNESCAPED_UNICODE);
    }

    // Sanitize public_visible to 0/1
    if ($field === 'public_visible') {
        $val = $val ? 1 : 0;
    }

    $fields[] = "`$field` = ?";
    $values[] = $val;
}

if (empty($fields)) {
    respondError('No valid fields to update', 400);
}

$values[] = $adminId;
$sql = "UPDATE coach_profiles SET " . implode(', ', $fields) . " WHERE admin_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute($values);

respond([
    'ok'      => true,
    'message' => 'Perfil actualizado correctamente',
]);
