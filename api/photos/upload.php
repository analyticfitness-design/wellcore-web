<?php
// POST /api/photos/upload  (multipart/form-data)
// Fields: photo (file), tipo (frente|perfil|espalda), date (Y-m-d)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$client = authenticateClient();

if (empty($_FILES['photo'])) {
    respondError('No se recibió ninguna foto', 422);
}

$tipo = $_POST['tipo'] ?? 'frente';
$date = $_POST['date'] ?? date('Y-m-d');

if (!in_array($tipo, ['frente', 'perfil', 'espalda'])) {
    respondError('Tipo inválido (frente|perfil|espalda)', 422);
}

$file = $_FILES['photo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    respondError('Error al subir el archivo', 500);
}
if ($file['size'] > MAX_PHOTO_SIZE) {
    respondError('La foto no debe superar 5MB', 413);
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowedMimes)) {
    respondError('Solo se permiten imágenes JPEG, PNG o WebP', 415);
}

$ext = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => 'jpg',
};

// Generate unique filename
$filename = sprintf('%s_%s_%s_%s.%s',
    $client['client_code'],
    $date,
    $tipo,
    substr(bin2hex(random_bytes(4)), 0, 8),
    $ext
);

$uploadDir = UPLOAD_DIR . 'photos/' . $client['client_code'] . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filepath = $uploadDir . $filename;
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    respondError('No se pudo guardar la foto', 500);
}

$db   = getDB();
$stmt = $db->prepare("
    INSERT INTO progress_photos (client_id, photo_date, tipo, filename)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$client['id'], $date, $tipo, $filename]);

respond([
    'message'  => 'Foto guardada correctamente',
    'filename' => $filename,
    'url'      => UPLOAD_URL . 'photos/' . $client['client_code'] . '/' . $filename,
    'date'     => $date,
    'tipo'     => $tipo,
], 201);
