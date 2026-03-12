<?php
/**
 * WellCore Fitness — Enviar Invitación de Plan
 * POST /api/admin/send-invitation
 * Solo superadmin.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../emails/templates.php';

requireMethod('POST');
$admin = authenticateAdmin();

if (($admin['role'] ?? '') !== 'superadmin') {
    respondError('Acceso restringido a superadmin', 403);
}

$body      = getJsonBody();
$toName    = trim($body['to_name']    ?? '');
$toEmail   = trim($body['to_email']   ?? '');
$plan      = trim($body['plan']       ?? 'rise');
$gender    = trim($body['gender']     ?? 'male');
$customMsg = trim($body['custom_msg'] ?? '');

if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    respondError('Email inválido', 422);
}
if (!in_array($plan, ['rise', 'esencial', 'metodo', 'elite', 'presencial'], true)) {
    respondError('Plan inválido', 422);
}

// For presencial plan, auto-create an invitation code in the DB
$invitationCode = null;
if ($plan === 'presencial') {
    $db = getDB();
    $invitationCode = bin2hex(random_bytes(16));
    $expiresAt = date('Y-m-d H:i:s', time() + (30 * 86400)); // 30 days
    $db->prepare("
        INSERT INTO invitations (code, plan, email_hint, note, status, created_by, expires_at)
        VALUES (?, 'presencial', ?, 'Invitación presencial enviada por email', 'pending', ?, ?)
    ")->execute([$invitationCode, $toEmail, $admin['id'], $expiresAt]);
}

$html = email_invitation($toName ?: 'Amig@', $plan, $gender, $customMsg, $invitationCode);

$subjects = [
    'rise'       => 'Te invito al Reto RISE 30 Días — WellCore Fitness',
    'esencial'   => 'Tu invitación al Plan Esencial — WellCore Fitness',
    'metodo'     => 'Tu invitación al Plan Método — WellCore Fitness',
    'elite'      => 'Tu invitación al Plan Elite — WellCore Fitness',
    'presencial' => 'Tu invitación al Entrenamiento Presencial — WellCore Fitness',
];

$result = sendEmail($toEmail, $subjects[$plan], $html);

if (!$result['ok']) {
    respondError('Error enviando email: ' . ($result['error'] ?? 'desconocido'), 500);
}

try {
    if (!isset($db)) $db = getDB();
    $db->prepare("INSERT INTO email_logs (sent_by, to_email, to_name, template, plan, sent_at) VALUES (?, ?, ?, 'invitation', ?, NOW())")
       ->execute([$admin['id'], $toEmail, $toName, $plan]);
} catch (\Throwable $ignored) {}

$response = ['ok' => true, 'sent_to' => $toEmail, 'plan' => $plan, 'message' => "Invitación enviada a {$toEmail}"];
if ($invitationCode) {
    $response['invitation_code'] = $invitationCode;
    $response['registration_url'] = "https://wellcorefitness.com/presencial.html?code={$invitationCode}";
}
respond($response);
