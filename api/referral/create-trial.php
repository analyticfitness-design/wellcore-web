<?php
/**
 * POST /api/referral/create-trial
 * Genera un trial de 3 días para un referido del cliente.
 *
 * Body: { referred_email }
 * Auth: cliente
 * Responde: { trial_url, trial_days, referred_email, expires_at }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

respondJson();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respondError('Método no permitido', 405);

$client    = authenticateClient();
$db        = getDB();
$client_id = $client['id'];
$body      = json_decode(file_get_contents('php://input'), true) ?? [];

$email = strtolower(trim($body['referred_email'] ?? ''));
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondError('Email inválido', 400);
}

// No pueden referirse a sí mismos
$own = $db->prepare("SELECT email FROM clients WHERE id = ?");
$own->execute([$client_id]);
if (strtolower((string)$own->fetchColumn()) === $email) {
    respondError('No puedes referirte a ti mismo', 400);
}

// No puede existir ya una cuenta con ese email
$exists = $db->prepare("SELECT COUNT(*) FROM clients WHERE email = ?");
$exists->execute([$email]);
if ((int)$exists->fetchColumn() > 0) {
    respondError('Este email ya tiene una cuenta en WellCore', 409);
}

// Obtener referral_code del cliente
$ref_row = $db->prepare("SELECT referral_code FROM clients WHERE id = ?");
$ref_row->execute([$client_id]);
$referral_code = $ref_row->fetchColumn() ?: strtoupper(substr(md5($client_id), 0, 8));

// Upsert referral trial
$expires = date('Y-m-d H:i:s', strtotime('+3 days'));
$db->prepare("
    INSERT INTO referral_trials (referral_code, referrer_client_id, referred_email, trial_days, trial_expires_at)
    VALUES (?, ?, ?, 3, ?)
    ON DUPLICATE KEY UPDATE
        referrer_client_id = VALUES(referrer_client_id),
        trial_expires_at   = VALUES(trial_expires_at),
        converted          = 0
")->execute([$referral_code, $client_id, $email, $expires]);

$base     = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$trial_url = $base . '/rise-enroll.html?trial=1&ref=' . $referral_code . '&email=' . rawurlencode($email);

respond([
    'trial_url'      => $trial_url,
    'trial_days'     => 3,
    'referred_email' => $email,
    'expires_at'     => $expires,
    'message'        => "Invitación creada. Comparte el link con {$email}",
]);
