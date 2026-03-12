<?php
/**
 * POST /api/video-checkins/upload
 * Sube un video/imagen de forma del cliente. Multipart/form-data.
 *
 * Límites por plan: esencial=2/mes, metodo=5/mes, rise=3/mes, elite=ilimitado
 * Fields: exercise_name, notes (optional)
 * File:   media (video mp4/mov/webm <= 50MB, image jpg/png <= 10MB)
 *
 * Responde: { id, status, uses_this_month, limit }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/cors.php';

requireMethod('POST');

$client    = authenticateClient();
$db        = getDB();
$client_id = $client['id'];

// Límites por plan
$plan_limits = [
    'esencial' => 2,
    'metodo'   => 5,
    'rise'     => 3,
    'elite'    => PHP_INT_MAX,
];
$plan  = strtolower($client['plan'] ?? 'esencial');
$limit = $plan_limits[$plan] ?? 2;

// Contar usos este mes
$month_start = date('Y-m-01');
$used = $db->prepare("SELECT COUNT(*) FROM video_checkins WHERE client_id = ? AND created_at >= ?");
$used->execute([$client_id, $month_start]);
$uses_this_month = (int)$used->fetchColumn();

if ($uses_this_month >= $limit && $limit !== PHP_INT_MAX) {
    respondError("Límite de video check-ins alcanzado para tu plan ({$limit}/mes)", 429);
}

// Validar archivo subido
if (empty($_FILES['media'])) {
    respondError('Archivo de media requerido', 400);
}

$file    = $_FILES['media'];
$tmpPath = $file['tmp_name'];
$origName= $file['name'];
$size    = $file['size'];
$mime    = mime_content_type($tmpPath);

$allowed_video = ['video/mp4', 'video/quicktime', 'video/webm'];
$allowed_image = ['image/jpeg', 'image/png', 'image/webp'];
$all_allowed   = array_merge($allowed_video, $allowed_image);

if (!in_array($mime, $all_allowed, true)) {
    respondError('Formato no soportado. Usa MP4, MOV, WebM, JPG o PNG', 400);
}

$is_video  = in_array($mime, $allowed_video, true);
$max_bytes = $is_video ? 50 * 1024 * 1024 : 10 * 1024 * 1024;

if ($size > $max_bytes) {
    $max_mb = $is_video ? 50 : 10;
    respondError("Archivo demasiado grande. Máximo {$max_mb}MB", 400);
}

$exercise_name = trim($_POST['exercise_name'] ?? '');
$notes         = trim($_POST['notes'] ?? '');

if ($exercise_name === '') {
    respondError('exercise_name es requerido', 400);
}

// Directorio de upload
$upload_dir = __DIR__ . '/../../uploads/video-checkins/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$ext      = $is_video ? pathinfo($origName, PATHINFO_EXTENSION) : pathinfo($origName, PATHINFO_EXTENSION);
$ext      = strtolower($ext ?: ($is_video ? 'mp4' : 'jpg'));
$filename = uniqid('vc_', true) . '.' . $ext;
$dest     = $upload_dir . $filename;

if (!move_uploaded_file($tmpPath, $dest)) {
    respondError('Error al guardar el archivo', 500);
}

$media_url  = '/uploads/video-checkins/' . $filename;
$media_type = $is_video ? 'video' : 'image';

// Obtener coach_id del cliente
$coach_row = $db->prepare("SELECT coach_id FROM clients WHERE id = ?");
$coach_row->execute([$client_id]);
$coach_id = $coach_row->fetchColumn() ?: '';

$id = uniqid('vc', true);
$ins = $db->prepare("
    INSERT INTO video_checkins (id, client_id, coach_id, media_type, media_url, exercise_name, notes, plan_uses_this_month, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
");
// Nota: id en la tabla es BIGINT AUTO_INCREMENT — usamos lastInsertId
$ins2 = $db->prepare("
    INSERT INTO video_checkins (client_id, coach_id, media_type, media_url, exercise_name, notes, plan_uses_this_month, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
");
$ins2->execute([$client_id, $coach_id, $media_type, $media_url, $exercise_name, $notes ?: null, $uses_this_month + 1]);
$new_id = $db->lastInsertId();

// Otorgar XP por video check-in
$xp_url = 'http://localhost/api/gamification/earn-xp';
$xp_payload = json_encode(['event_type' => 'video_checkin', 'description' => "Video check-in: {$exercise_name}"]);
$ctx = stream_context_create(['http' => [
    'method'  => 'POST',
    'header'  => "Content-Type: application/json\r\nAuthorization: Bearer " . ($_SERVER['HTTP_AUTHORIZATION'] ?? ''),
    'content' => $xp_payload,
    'timeout' => 5,
    'ignore_errors' => true,
]]);
@file_get_contents($xp_url, false, $ctx);

respond([
    'id'               => (int)$new_id,
    'status'           => 'pending',
    'media_url'        => $media_url,
    'uses_this_month'  => $uses_this_month + 1,
    'limit'            => $limit === PHP_INT_MAX ? null : $limit,
    'message'          => 'Video recibido. Tu coach revisará en las próximas 24h.',
]);
