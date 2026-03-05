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
if (!in_array($plan, ['rise', 'esencial', 'metodo', 'elite'], true)) {
    respondError('Plan inválido', 422);
}

$html = email_invitation($toName ?: 'Amig@', $plan, $gender, $customMsg);

$subjects = [
    'rise'     => 'Te invito al Reto RISE 30 Días — WellCore Fitness',
    'esencial' => 'Tu invitación al Plan Esencial — WellCore Fitness',
    'metodo'   => 'Tu invitación al Plan Método — WellCore Fitness',
    'elite'    => 'Tu invitación al Plan Elite — WellCore Fitness',
];

$result = sendEmail($toEmail, $subjects[$plan], $html);

if (!$result['ok']) {
    respondError('Error enviando email: ' . ($result['error'] ?? 'desconocido'), 500);
}

try {
    $db = getDB();
    $db->prepare("INSERT INTO email_logs (sent_by, to_email, to_name, template, plan, sent_at) VALUES (?, ?, ?, 'invitation', ?, NOW())")
       ->execute([$admin['id'], $toEmail, $toName, $plan]);
} catch (\Throwable $ignored) {}

respond(['ok' => true, 'sent_to' => $toEmail, 'plan' => $plan, 'message' => "Invitación enviada a {$toEmail}"]);
