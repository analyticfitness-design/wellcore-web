<?php
// GET  /api/client/profile  → { id, name, email, plan, avatar_url, bio, city, birth_date, referral_code }
// POST /api/client/profile  → Body: { name?, bio?, city?, birth_date? } → { ok: true, client: {...} }
// Nota: no permite cambiar email ni plan

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST');
$client = authenticateClient();
$db = getDB();

// Auto-generar referral_code si no tiene
function ensureReferralCode(PDO $db, int $clientId): string {
    $row = $db->prepare("SELECT referral_code FROM clients WHERE id = ?");
    $row->execute([$clientId]);
    $code = $row->fetchColumn();
    if ($code) return $code;

    // Generar código único de 8 chars
    do {
        $new = strtoupper(substr(base_convert(bin2hex(random_bytes(4)), 16, 36), 0, 8));
        $check = $db->prepare("SELECT COUNT(*) FROM clients WHERE referral_code = ?");
        $check->execute([$new]);
    } while ($check->fetchColumn() > 0);

    $db->prepare("UPDATE clients SET referral_code = ? WHERE id = ?")->execute([$new, $clientId]);
    return $new;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $code = ensureReferralCode($db, (int)$client['id']);

    $stmt = $db->prepare("
        SELECT id, name, email, plan, status, fecha_inicio,
               avatar_url, bio, city, birth_date, referral_code
        FROM clients WHERE id = ?
    ");
    $stmt->execute([$client['id']]);
    $data = $stmt->fetch();

    respond(['client' => $data]);
}

// POST — update
$body = getJsonBody();

$updates = [];
$values  = [];

if (isset($body['name'])) {
    $name = trim($body['name']);
    if (strlen($name) < 2) respondError('El nombre debe tener al menos 2 caracteres', 422);
    $updates[] = 'name = ?';
    $values[]  = substr($name, 0, 120);
}

if (isset($body['bio'])) {
    $bio = trim($body['bio']);
    if (strlen($bio) > 500) respondError('La bio no puede superar 500 caracteres', 422);
    $updates[] = 'bio = ?';
    $values[]  = $bio;
}

if (isset($body['city'])) {
    $updates[] = 'city = ?';
    $values[]  = substr(trim($body['city']), 0, 100);
}

if (isset($body['birth_date'])) {
    $bd = trim($body['birth_date']);
    if ($bd !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bd)) {
        respondError('birth_date debe tener formato YYYY-MM-DD', 422);
    }
    $updates[] = 'birth_date = ?';
    $values[]  = $bd ?: null;
}

if (empty($updates)) {
    respondError('No hay campos validos para actualizar', 422);
}

$values[] = $client['id'];
$db->prepare("UPDATE clients SET " . implode(', ', $updates) . " WHERE id = ?")
   ->execute($values);

// Devolver perfil actualizado
$stmt = $db->prepare("
    SELECT id, name, email, plan, status, fecha_inicio,
           avatar_url, bio, city, birth_date, referral_code
    FROM clients WHERE id = ?
");
$stmt->execute([$client['id']]);
$updated = $stmt->fetch();

respond(['ok' => true, 'client' => $updated]);
