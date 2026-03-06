<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
/**
 * WellCore Fitness — AI Photo Review (Coach Feedback)
 * ============================================================
 * POST /api/ai/photo-review.php
 *
 * Genera un review de fotos de progreso usando Claude Haiku Vision.
 * Se presenta al cliente como retroalimentacion del coach.
 *
 * Body (JSON): { photo_date: "YYYY-MM-DD" }
 * Auth: Bearer token de cliente
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$client = authenticateClient();
$cid    = (int)$client['id'];
$plan   = strtolower($client['plan'] ?? 'esencial');
$db     = getDB();

// Plan restrictions
// esencial/rise: blocked
// metodo: 2/month, every 10-15 days
// elite: 1/week, weekends only
if (in_array($plan, ['esencial', 'rise'])) {
    respondError('El review de fotos no esta disponible en tu plan actual. Actualiza a Metodo o Elite.', 403);
}

// Check existing reviews for rate limiting
$reviews = $db->prepare("
    SELECT id, photo_date, created_at
    FROM photo_reviews
    WHERE client_id = ?
    ORDER BY created_at DESC
");
$reviews->execute([$cid]);
$allReviews = $reviews->fetchAll(PDO::FETCH_ASSOC);

if ($plan === 'metodo') {
    // 2 per month
    $thisMonth = date('Y-m');
    $monthCount = 0;
    $lastReviewDate = null;
    foreach ($allReviews as $r) {
        if (substr($r['created_at'], 0, 7) === $thisMonth) $monthCount++;
        if (!$lastReviewDate) $lastReviewDate = $r['created_at'];
    }
    if ($monthCount >= 2) {
        respondError('Ya usaste tus 2 reviews de este mes. Se reinicia el proximo mes.', 429);
    }
    // Minimum 10 days between reviews
    if ($lastReviewDate) {
        $daysSince = (int)((time() - strtotime($lastReviewDate)) / 86400);
        if ($daysSince < 10) {
            $remaining = 10 - $daysSince;
            respondError('Debes esperar ' . $remaining . ' dia(s) mas para solicitar otro review.', 429);
        }
    }
} elseif ($plan === 'elite') {
    // 1 per week, weekends only (Saturday=6, Sunday=0)
    $dayOfWeek = (int)date('w'); // 0=Sun, 6=Sat
    if ($dayOfWeek !== 0 && $dayOfWeek !== 6) {
        respondError('El review de fotos esta disponible los fines de semana (sabado y domingo).', 429);
    }
    // Check if already used this week
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekUsed = false;
    foreach ($allReviews as $r) {
        if ($r['created_at'] >= $weekStart) { $weekUsed = true; break; }
    }
    if ($weekUsed) {
        respondError('Ya solicitaste tu review semanal. Disponible nuevamente el proximo fin de semana.', 429);
    }
}

$body      = json_decode(file_get_contents('php://input'), true) ?: [];
$photoDate = $body['photo_date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $photoDate)) {
    respondError('Fecha de fotos requerida (photo_date)', 400);
}

// Get photos for that date
$stmt = $db->prepare("
    SELECT tipo, filename FROM progress_photos
    WHERE client_id = ? AND photo_date = ?
");
$stmt->execute([$cid, $photoDate]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($photos)) {
    respondError('No hay fotos registradas en esa fecha.', 404);
}

// Get client profile for context
$profile = '';
try {
    $cData = get_client_for_ai($cid);
    $profile = "Cliente: " . ($cData['name'] ?: 'Usuario') . "\n";
    $profile .= "Plan: " . strtoupper($plan) . "\n";
    $profile .= "Objetivo: " . ($cData['objetivo'] ?: 'mejorar composicion corporal') . "\n";
    if ($cData['peso'] ?? null) $profile .= "Peso: " . $cData['peso'] . "kg\n";
    if ($cData['restricciones'] ?? null) $profile .= "Restricciones: " . $cData['restricciones'] . "\n";
} catch (\Throwable $e) {
    $profile = "Plan: " . strtoupper($plan) . "\n";
}

// Get previous photos for comparison context
$prevPhotos = $db->prepare("
    SELECT photo_date, COUNT(*) as cnt FROM progress_photos
    WHERE client_id = ? AND photo_date < ?
    GROUP BY photo_date ORDER BY photo_date ASC
");
$prevPhotos->execute([$cid, $photoDate]);
$prevSets = $prevPhotos->fetchAll(PDO::FETCH_ASSOC);
$comparisonContext = count($prevSets) > 0
    ? "El cliente tiene " . count($prevSets) . " registro(s) anteriores de fotos. Fecha mas antigua: " . $prevSets[0]['photo_date'] . "."
    : "Este es el primer registro de fotos del cliente.";

// Build image content for Vision API - use first available photo
$uploadBase = __DIR__ . '/../../uploads/progress/' . $cid . '/';
$imageData = null;
$mediaType = 'image/jpeg';

foreach ($photos as $photo) {
    $filePath = $uploadBase . $photo['filename'];
    if (file_exists($filePath)) {
        $imageData = base64_encode(file_get_contents($filePath));
        $ext = strtolower(pathinfo($photo['filename'], PATHINFO_EXTENSION));
        $mediaType = match($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
        break;
    }
}

if (!$imageData) {
    respondError('No se pudieron leer las fotos del servidor.', 500);
}

$systemPrompt = <<<SYSTEM
Eres el coach de WellCore Fitness haciendo un review visual del progreso de un cliente.
Responde en espanol como si fueras su entrenador personal.

Tu review debe incluir:
1. OBSERVACIONES GENERALES (2-3 oraciones): Que se observa en la foto — postura, composicion visual, tonicidad aparente.
2. AREAS DE PROGRESO (2-3 puntos): Aspectos positivos que notas o areas donde se ve mejora potencial.
3. RECOMENDACIONES (2-3 puntos): Sugerencias concretas de entrenamiento o nutricion basadas en lo observado.
4. MOTIVACION (1 oracion): Mensaje de animo personalizado.

Formato: usa viñetas simples (-). Se directo, tecnico pero amigable.
NO hagas diagnosticos medicos. NO menciones peso exacto ni porcentaje de grasa.
NO uses emojis. Maximo 250 palabras total.
SYSTEM;

$userPrompt = "Review de fotos de progreso — Fecha: $photoDate\n";
$userPrompt .= "Fotos disponibles: " . implode(', ', array_column($photos, 'tipo')) . "\n";
$userPrompt .= $comparisonContext . "\n";
$userPrompt .= $profile;
$userPrompt .= "\nRealiza el review visual de esta foto de progreso.";

try {
    $result = claude_call_vision(
        $systemPrompt,
        $userPrompt,
        $imageData,
        $mediaType
    );

    $reviewText = trim($result['text']);

    // Save review to DB
    $db->prepare("
        INSERT INTO photo_reviews (client_id, photo_date, review_text, tokens_used, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ")->execute([$cid, $photoDate, $reviewText, $result['input_tokens'] + $result['output_tokens']]);

    $reviewId = (int)$db->lastInsertId();

    respond([
        'ok'        => true,
        'review_id' => $reviewId,
        'review'    => $reviewText,
        'tokens'    => $result['input_tokens'] + $result['output_tokens'],
    ]);
} catch (\Throwable $e) {
    error_log('photo-review error: ' . $e->getMessage());
    respondError('No se pudo generar el review. Intenta de nuevo.', 500);
}
