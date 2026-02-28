<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Create Sponsored Client Account
 * POST /api/admin/create-sponsored
 * Body: { name, email, plan, send_email?: bool }
 *
 * Creates a client account directly (no payment, no invitation code).
 * Generates a temporary password and optionally sends welcome email.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';

requireMethod('POST');
$admin = authenticateAdmin();
$db    = getDB();

$body      = getJsonBody();
$name      = trim($body['name'] ?? '');
$email     = strtolower(trim($body['email'] ?? ''));
$plan      = $body['plan'] ?? '';
$sendEmail = $body['send_email'] ?? true;
$note      = trim($body['note'] ?? '');

// Validate
if (!$name) respondError('Nombre es requerido', 422);
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) respondError('Email valido es requerido', 422);

$validPlans = ['esencial', 'metodo', 'elite'];
if (!in_array($plan, $validPlans, true)) {
    respondError('Plan debe ser: esencial, metodo o elite', 422);
}

// Check email not already in use
$stmt = $db->prepare("SELECT id FROM clients WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    respondError('Ya existe una cuenta con este email', 409);
}

// Generate client code (race-safe)
$maxNum = $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(client_code, 5) AS UNSIGNED)), 0) FROM clients")->fetchColumn();
$clientCode = 'cli-' . str_pad((int)$maxNum + 1, 4, '0', STR_PAD_LEFT);

// Generate temporary password (8 chars, readable)
$tempPass = substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
$hash = password_hash($tempPass, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $db->beginTransaction();

    // Create client
    $stmt = $db->prepare("
        INSERT INTO clients (client_code, name, email, password_hash, plan, status, fecha_inicio)
        VALUES (?, ?, ?, ?, ?, 'activo', CURDATE())
    ");
    $stmt->execute([$clientCode, $name, $email, $hash, $plan]);
    $clientId = $db->lastInsertId();

    // Create default profile
    $db->prepare("INSERT INTO client_profiles (client_id) VALUES (?)")->execute([$clientId]);

    // Log in invitations table as sponsored
    $code = bin2hex(random_bytes(16));
    $db->prepare("
        INSERT INTO invitations (code, plan, email_hint, note, status, created_by, used_by, used_at, expires_at)
        VALUES (?, ?, ?, ?, 'used', ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY))
    ")->execute([$code, $plan, $email, 'PATROCINIO: ' . ($note ?: 'Sin nota'), $admin['id'], $clientId]);

    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    respondError('Error al crear cuenta: ' . (APP_ENV === 'development' ? $e->getMessage() : 'DB error'), 500);
}

// Send welcome email
$emailSent = false;
if ($sendEmail) {
    $planLabel = ucfirst($plan);
    $loginUrl  = 'https://wellcorefitness.com/login.html';
    $planColors = ['esencial' => '#60a5fa', 'metodo' => '#F5C842', 'elite' => '#E31E24'];
    $planColor  = $planColors[$plan] ?? '#E31E24';
    $planFeatures = [
        'esencial' => 'Plan de entrenamiento personalizado &bull; Seguimiento semanal &bull; Acceso al panel de cliente',
        'metodo'   => 'Entrenamiento + Nutricion &bull; Check-ins semanales &bull; Soporte por chat &bull; Analisis de progreso',
        'elite'    => 'Coaching integral 1:1 &bull; Entrenamiento + Nutricion + Suplementacion &bull; Soporte prioritario &bull; Analisis IA',
    ];
    $features = $planFeatures[$plan] ?? '';

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bienvenido a WellCore Fitness</title>
<!--[if mso]><style>table{border-collapse:collapse;}td{font-family:Arial,sans-serif;}</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:#050505;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%">

<!-- Preheader -->
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;overflow:hidden">
Bienvenido a WellCore Fitness, {$name}. Tu cuenta esta lista con el plan {$planLabel}.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#050505;padding:20px 10px">
<tr><td align="center">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#0a0a0a;border:1px solid #1a1a1a">

<!-- Red top bar -->
<tr><td style="background:#E31E24;padding:3px 0;font-size:0;line-height:0">&nbsp;</td></tr>

<!-- Logo -->
<tr><td style="padding:36px 40px 24px;text-align:center;background:#0a0a0a">
  <table role="presentation" cellpadding="0" cellspacing="0" align="center">
  <tr>
    <td style="font-family:Arial,Helvetica,sans-serif;font-size:32px;font-weight:700;color:#ffffff;letter-spacing:3px">WELL</td>
    <td style="font-family:Arial,Helvetica,sans-serif;font-size:32px;font-weight:700;color:#E31E24;letter-spacing:3px">[CORE]</td>
  </tr>
  </table>
  <div style="font-size:10px;color:#52525b;letter-spacing:4px;margin-top:6px;text-transform:uppercase">Entrenamiento basado en ciencia</div>
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 40px"><div style="border-top:1px solid #1a1a1a"></div></td></tr>

<!-- Welcome -->
<tr><td style="padding:32px 40px 24px">
  <div style="font-size:12px;color:#22c55e;letter-spacing:3px;text-transform:uppercase;font-weight:700;margin-bottom:16px">// CUENTA ACTIVADA</div>
  <div style="font-size:22px;font-weight:700;color:#ffffff;line-height:1.3;margin-bottom:20px">
    Bienvenido, {$name}
  </div>
  <div style="font-size:14px;color:#a1a1aa;line-height:1.7;margin-bottom:28px">
    Tu cuenta en WellCore Fitness ha sido creada con acceso <strong style="color:{$planColor}">patrocinado</strong>. Ya tienes todo listo para comenzar tu transformacion.
  </div>

  <!-- Plan Card -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#111113;border-left:3px solid {$planColor};margin-bottom:24px">
  <tr><td style="padding:20px 24px">
    <div style="font-size:10px;color:#52525b;letter-spacing:2px;text-transform:uppercase;margin-bottom:8px">TU PLAN</div>
    <div style="font-size:20px;font-weight:700;color:{$planColor};letter-spacing:1px;text-transform:uppercase;margin-bottom:10px">{$planLabel}</div>
    <div style="font-size:12px;color:#71717a;line-height:1.6">{$features}</div>
  </td></tr>
  </table>

  <!-- Credentials Card -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#111113;border:1px solid #1a1a1a;margin-bottom:28px">
  <tr><td style="padding:24px">
    <div style="font-size:10px;color:#E31E24;letter-spacing:2px;text-transform:uppercase;font-weight:700;margin-bottom:16px">// TUS CREDENCIALES</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td style="padding:8px 0;font-size:12px;color:#52525b;width:140px;vertical-align:top">Email</td>
      <td style="padding:8px 0;font-size:14px;color:#00D9FF;font-family:'Courier New',monospace">{$email}</td>
    </tr>
    <tr><td colspan="2" style="padding:0"><div style="border-top:1px solid #1a1a1a"></div></td></tr>
    <tr>
      <td style="padding:8px 0;font-size:12px;color:#52525b;width:140px;vertical-align:top">Contrasena</td>
      <td style="padding:8px 0;font-size:16px;color:#00D9FF;font-family:'Courier New',monospace;letter-spacing:2px;font-weight:700">{$tempPass}</td>
    </tr>
    </table>

    <div style="margin-top:12px;padding:10px 14px;background:rgba(227,30,36,.08);border-left:2px solid #E31E24">
      <div style="font-size:11px;color:#a1a1aa;line-height:1.5">Contrasena temporal. Recomendamos cambiarla despues de tu primer ingreso.</div>
    </div>
  </td></tr>
  </table>
</td></tr>

<!-- CTA Button -->
<tr><td style="padding:0 40px 32px" align="center">
  <!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{$loginUrl}" style="height:50px;v-text-anchor:middle;width:320px" fill="true" stroke="false"><v:fill type="tile" color="#E31E24"/><center style="color:#ffffff;font-family:Arial;font-size:14px;font-weight:bold">INGRESAR A MI CUENTA</center></v:roundrect><![endif]-->
  <!--[if !mso]><!-->
  <a href="{$loginUrl}" target="_blank" style="display:inline-block;background:#E31E24;color:#ffffff;text-decoration:none;padding:16px 48px;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;mso-padding-alt:0;text-underline-color:#E31E24">
    INGRESAR A MI CUENTA &rarr;
  </a>
  <!--<![endif]-->
</td></tr>

<!-- What's Next -->
<tr><td style="padding:0 40px 32px">
  <div style="font-size:10px;color:#52525b;letter-spacing:2px;text-transform:uppercase;margin-bottom:14px">PROXIMOS PASOS</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
  <tr><td style="padding:6px 0;font-size:13px;color:#a1a1aa;line-height:1.5">
    <span style="color:#E31E24;font-weight:700;margin-right:6px">01</span> Ingresa con tus credenciales
  </td></tr>
  <tr><td style="padding:6px 0;font-size:13px;color:#a1a1aa;line-height:1.5">
    <span style="color:#E31E24;font-weight:700;margin-right:6px">02</span> Completa tu perfil con tus datos fisicos
  </td></tr>
  <tr><td style="padding:6px 0;font-size:13px;color:#a1a1aa;line-height:1.5">
    <span style="color:#E31E24;font-weight:700;margin-right:6px">03</span> Recibe tu plan personalizado en 48h
  </td></tr>
  </table>
</td></tr>

<!-- Divider -->
<tr><td style="padding:0 40px"><div style="border-top:1px solid #1a1a1a"></div></td></tr>

<!-- Footer -->
<tr><td style="padding:24px 40px 20px;text-align:center">
  <div style="font-size:11px;color:#3f3f46;line-height:1.8">
    <strong style="color:#52525b">WellCore Fitness</strong><br>
    <a href="https://wellcorefitness.com" style="color:#3f3f46;text-decoration:none">wellcorefitness.com</a> &nbsp;|&nbsp;
    <a href="mailto:info@wellcorefitness.com" style="color:#3f3f46;text-decoration:none">info@wellcorefitness.com</a><br>
    <a href="https://wa.me/573124904720" style="color:#3f3f46;text-decoration:none">WhatsApp: +57 312 490 4720</a>
  </div>
  <div style="font-size:10px;color:#27272a;margin-top:12px;letter-spacing:1px">
    &copy; 2026 WellCore Fitness. Todos los derechos reservados.
  </div>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;

    $result = sendEmail($email, "Bienvenido a WellCore Fitness — Acceso {$planLabel}", $html);
    $emailSent = $result['ok'];
}

respond([
    'ok'          => true,
    'message'     => 'Cuenta patrocinada creada',
    'client_id'   => $clientId,
    'client_code' => $clientCode,
    'name'        => $name,
    'email'       => $email,
    'plan'        => $plan,
    'temp_pass'   => $tempPass,
    'email_sent'  => $emailSent,
], 201);
