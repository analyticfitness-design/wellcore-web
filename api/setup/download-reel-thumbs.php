<?php
/**
 * Descarga thumbnails de Instagram Reels y los guarda en /images/coach-tips/
 * Actualiza thumbnail_url en DB para apuntar al archivo local.
 *
 * EJECUCION: php /code/api/setup/download-reel-thumbs.php
 *            o via Bearer token admin en HTTP
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db = getDB();
header('Content-Type: text/plain; charset=utf-8');

$dir = '/code/images/coach-tips';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
    echo "Creado directorio: $dir\n";
}

$rows = $db->query(
    "SELECT id, title, thumbnail_url, video_url FROM coach_video_tips WHERE is_active = 1 ORDER BY sort_order"
)->fetchAll(PDO::FETCH_ASSOC);

echo "Thumbnails a descargar: " . count($rows) . "\n\n";

$updated = 0;
$errors  = [];

foreach ($rows as $row) {
    $localFile = $dir . '/tip-' . $row['id'] . '.jpg';
    $localUrl  = '/images/coach-tips/tip-' . $row['id'] . '.jpg';

    if (file_exists($localFile) && filesize($localFile) > 1000) {
        $db->prepare("UPDATE coach_video_tips SET thumbnail_url = ? WHERE id = ?")->execute([$localUrl, $row['id']]);
        echo "  SKIP (ya existe)  id={$row['id']}\n";
        $updated++;
        continue;
    }

    if (!$row['thumbnail_url']) {
        echo "  NO-URL  id={$row['id']} -- {$row['title']}\n";
        continue;
    }

    $ch = curl_init($row['thumbnail_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Referer: https://www.instagram.com/',
            'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($data && $code === 200 && strlen($data) > 1000) {
        file_put_contents($localFile, $data);
        $db->prepare("UPDATE coach_video_tips SET thumbnail_url = ? WHERE id = ?")
           ->execute([$localUrl, $row['id']]);
        $updated++;
        $kb = round(strlen($data) / 1024, 1);
        echo "  OK  id={$row['id']} {$kb}KB => $localUrl\n";
    } else {
        $errors[] = "id={$row['id']} code=$code err=$err";
        echo "  FAIL  id={$row['id']} code=$code {$row['title']}\n";
    }

    usleep(300000);
}

echo "\n=== Resultado ===\n";
echo "Descargados/actualizados: $updated\n";
echo "Errores: " . count($errors) . "\n";
if ($errors) {
    foreach ($errors as $e) echo "  $e\n";
}
