<?php
// POST /api/clients/avatar — upload profile picture

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$client = authenticateClient();
$db = getDB();

if (empty($_FILES['avatar'])) respondError('No image received', 400);
$file = $_FILES['avatar'];
if ($file['error'] !== UPLOAD_ERR_OK) respondError('Upload error: ' . $file['error'], 400);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowed[$mime])) respondError('Tipo de archivo no permitido', 422);
if ($file['size'] > 2 * 1024 * 1024) respondError('Archivo demasiado grande (máx 2MB)', 422);

$ext      = $allowed[$mime];
$isServer = file_exists('/.dockerenv');
$uploadsBase = $isServer ? '/code/uploads/avatars' : __DIR__ . '/../../uploads/avatars';
if (!is_dir($uploadsBase)) mkdir($uploadsBase, 0755, true);

// Delete old avatar if exists
$old = $db->prepare("SELECT avatar_url FROM client_profiles WHERE client_id = ?");
$old->execute([$client['id']]);
$oldUrl = $old->fetchColumn();
if ($oldUrl) {
    $oldPath = $isServer ? '/code' . $oldUrl : __DIR__ . '/../../' . ltrim($oldUrl, '/');
    if (file_exists($oldPath)) @unlink($oldPath);
}

$filename = 'client_' . $client['id'] . '_' . time() . '.' . $ext;
move_uploaded_file($file['tmp_name'], $uploadsBase . '/' . $filename);

$url = '/uploads/avatars/' . $filename;

$check = $db->prepare("SELECT id FROM client_profiles WHERE client_id = ?");
$check->execute([$client['id']]);
if ($check->fetchColumn()) {
    $db->prepare("UPDATE client_profiles SET avatar_url = ? WHERE client_id = ?")->execute([$url, $client['id']]);
} else {
    $db->prepare("INSERT INTO client_profiles (client_id, avatar_url) VALUES (?, ?)")->execute([$client['id'], $url]);
}

respond(['avatar_url' => $url]);
