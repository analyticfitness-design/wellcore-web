<?php
/**
 * WellCore Fitness — Coach Logo Upload
 * POST /api/coach/logo.php  (multipart/form-data)
 * Requires: Bearer coach token
 * Field: logo (file, max 500KB, png/svg/webp/jpeg)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$coach = authenticateCoach();
$adminId = (int) $coach['user_id'];
$db = getDB();

// ─── Validate file upload ───────────────────────────────────────────────────
if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['logo']['error'] ?? -1;
    respondError('No se recibio el archivo logo (error code: ' . $errCode . ')', 400);
}

$file = $_FILES['logo'];
$maxSize = 512000; // 500KB

if ($file['size'] > $maxSize) {
    respondError('El logo no puede superar 500KB', 400);
}

// Validate MIME type using finfo (not trusting client-provided type)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

$allowedMimes = [
    'image/png'     => 'png',
    'image/svg+xml' => 'svg',
    'image/webp'    => 'webp',
    'image/jpeg'    => 'jpg',
];

if (!isset($allowedMimes[$mime])) {
    respondError('Formato no permitido. Usa PNG, SVG, WebP o JPEG', 400);
}

$ext = $allowedMimes[$mime];

// ─── Get coach slug ─────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT slug FROM coach_profiles WHERE admin_id = ?");
$stmt->execute([$adminId]);
$profile = $stmt->fetch();

if (!$profile || empty($profile['slug'])) {
    respondError('Coach profile not found or missing slug', 404);
}

$slug = $profile['slug'];

// ─── Save file ──────────────────────────────────────────────────────────────
$uploadDir = dirname(__DIR__, 2) . '/uploads/coaches';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = $slug . '-logo.' . $ext;
$destPath = $uploadDir . '/' . $filename;

// Remove any previous logo for this coach
$globPattern = $uploadDir . '/' . $slug . '-logo.*';
foreach (glob($globPattern) as $oldFile) {
    if (is_file($oldFile)) {
        unlink($oldFile);
    }
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    respondError('Error al guardar el archivo', 500);
}

// ─── Update DB ──────────────────────────────────────────────────────────────
$logoUrl = '/uploads/coaches/' . $filename;
$stmt = $db->prepare("UPDATE coach_profiles SET logo_url = ? WHERE admin_id = ?");
$stmt->execute([$logoUrl, $adminId]);

respond([
    'ok'       => true,
    'logo_url' => $logoUrl,
]);
