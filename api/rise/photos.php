<?php
declare(strict_types=1);
/**
 * RISE Fotos de Progreso
 * GET  /api/rise/photos.php         → lista de fotos del cliente agrupadas por fecha
 * POST /api/rise/photos.php         → subir fotos (multipart/form-data)
 *   campos: photo_date (Y-m-d), frente (file), perfil (file), espalda (file)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST');
$client = authenticateClient();
$db     = getDB();
$cid    = (int)$client['id'];

$uploadBase = __DIR__ . '/../../uploads/progress/' . $cid . '/';
$urlBase    = '/uploads/progress/' . $cid . '/';
if (!is_dir($uploadBase)) {
    mkdir($uploadBase, 0755, true);
}

// ── GET ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT id, photo_date, tipo, filename, created_at
        FROM progress_photos
        WHERE client_id = ?
        ORDER BY photo_date ASC, id ASC
    ");
    $stmt->execute([$cid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($rows as $row) {
        $date = $row['photo_date'];
        if (!isset($grouped[$date])) {
            $grouped[$date] = ['date' => $date, 'photos' => []];
        }
        $grouped[$date]['photos'][$row['tipo']] = [
            'id'  => (int)$row['id'],
            'url' => $urlBase . $row['filename'],
        ];
    }
    respond(['sets' => array_values($grouped), 'total' => count($grouped)]);
}

// ── POST ──────────────────────────────────────────────────────
$photoDate = $_POST['photo_date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $photoDate)) {
    $photoDate = date('Y-m-d');
}

$tipos   = ['frente', 'perfil', 'espalda'];
$saved   = [];
$errors  = [];
$maxSize = 10 * 1024 * 1024;
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

foreach ($tipos as $tipo) {
    if (!isset($_FILES[$tipo]) || $_FILES[$tipo]['error'] === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    $file = $_FILES[$tipo];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = $tipo . ': error upload (' . $file['error'] . ')';
        continue;
    }
    if ($file['size'] > $maxSize) {
        $errors[] = $tipo . ': archivo muy grande (máx 10 MB)';
        continue;
    }
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mimeType, $allowed)) {
        $errors[] = $tipo . ': tipo no permitido';
        continue;
    }
    $ext      = match($mimeType) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };
    $filename = $cid . '_' . $tipo . '_' . $photoDate . '.' . $ext;
    $dest     = $uploadBase . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $errors[] = $tipo . ': error al guardar archivo';
        continue;
    }

    // Upsert sin UNIQUE constraint: SELECT → INSERT o UPDATE
    $existing = $db->prepare("SELECT id, filename FROM progress_photos WHERE client_id=? AND photo_date=? AND tipo=?");
    $existing->execute([$cid, $photoDate, $tipo]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Eliminar archivo anterior si cambió de nombre
        if ($row['filename'] !== $filename) {
            $oldFile = $uploadBase . $row['filename'];
            if (file_exists($oldFile)) unlink($oldFile);
        }
        $db->prepare("UPDATE progress_photos SET filename=?, created_at=NOW() WHERE id=?")->execute([$filename, $row['id']]);
    } else {
        $db->prepare("INSERT INTO progress_photos (client_id, photo_date, tipo, filename) VALUES (?,?,?,?)")->execute([$cid, $photoDate, $tipo, $filename]);
    }

    $saved[] = ['tipo' => $tipo, 'url' => $urlBase . $filename];
}

if (empty($saved) && !empty($errors)) {
    respondError('No se guardó ninguna foto: ' . implode('; ', $errors), 400);
}
respond([
    'ok'      => true,
    'date'    => $photoDate,
    'saved'   => $saved,
    'errors'  => $errors,
    'message' => count($saved) . ' foto(s) guardada(s)',
]);
