<?php
// POST /api/admin/rise-invite.php
// Inscripcion manual al reto RISE sin pago (admin only)
// Body: {name, email, password, experience_level, training_location, gender}
// Requiere token de admin valido

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';

function sendRiseWelcomeEmail(string $toEmail, string $toName, string $password, string $startDate, string $endDate): bool {
    $loginUrl = 'https://wellcorefitness.com/login.html';
    $startFmt = date('d/m/Y', strtotime($startDate));
    $endFmt   = date('d/m/Y', strtotime($endDate));
    $n        = htmlspecialchars($toName,   ENT_QUOTES, 'UTF-8');
    $e        = htmlspecialchars($toEmail,  ENT_QUOTES, 'UTF-8');
    $p        = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');

    $html = '<!DOCTYPE html>'
        . '<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">'
        . '<title>Tu acceso al Reto RISE 30 Dias</title></head>'
        . '<body style="margin:0;padding:0;background-color:#0a0a0a;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0a0a0a;padding:40px 16px;"><tr><td align="center">'
        . '<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">'

        // HEADER
        . '<tr><td style="background:linear-gradient(160deg,#1a0000 0%,#2d0505 50%,#1a0a0a 100%);border-radius:16px 16px 0 0;padding:48px 40px 40px;text-align:center;border-bottom:3px solid #e31e24;">'
        . '<div style="display:inline-block;background:rgba(227,30,36,0.15);border:1px solid rgba(227,30,36,0.3);border-radius:100px;padding:6px 18px;margin-bottom:20px;">'
        . '<span style="font-size:11px;font-weight:700;letter-spacing:4px;color:#e31e24;text-transform:uppercase;">WellCore Fitness</span></div>'
        . '<h1 style="margin:0 0 10px;font-size:42px;font-weight:900;color:#ffffff;line-height:1.1;letter-spacing:-1px;">Reto <span style="color:#e31e24;">RISE</span></h1>'
        . '<p style="margin:0;font-size:18px;font-weight:600;color:#888;letter-spacing:2px;text-transform:uppercase;">30 D&iacute;as &middot; Transformaci&oacute;n Total</p>'
        . '</td></tr>'

        // INTRO
        . '<tr><td style="background:#111111;padding:40px 40px 0;">'
        . '<p style="margin:0 0 8px;font-size:22px;font-weight:700;color:#ffffff;">Hola, <span style="color:#e31e24;">' . $n . '</span></p>'
        . '<p style="margin:0;font-size:16px;line-height:1.8;color:#999999;">Tu acceso al <strong style="color:#ffffff;">Reto RISE 30 d&iacute;as</strong> est&aacute; listo. A partir de hoy comienza tu transformaci&oacute;n con el programa m&aacute;s exigente y efectivo de WellCore Fitness.</p>'
        . '</td></tr>'

        // DIVIDER
        . '<tr><td style="background:#111111;padding:28px 40px 0;"><div style="height:1px;background:linear-gradient(90deg,transparent,#333,transparent);"></div></td></tr>'

        // CREDENTIALS BOX
        . '<tr><td style="background:#111111;padding:28px 40px 0;">'
        . '<p style="margin:0 0 16px;font-size:12px;font-weight:700;letter-spacing:3px;color:#e31e24;text-transform:uppercase;">Tus credenciales de acceso</p>'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#1a1a1a;border:1px solid #2a2a2a;border-left:4px solid #e31e24;border-radius:12px;">'
        . '<tr><td style="padding:28px 30px;">'

        // Email row
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">'
        . '<tr><td style="width:32px;vertical-align:top;padding-right:12px;">'
        . '<div style="width:28px;height:28px;background:rgba(227,30,36,0.12);border-radius:6px;text-align:center;line-height:28px;font-size:14px;">&#128231;</div>'
        . '</td><td><div style="font-size:10px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:2px;margin-bottom:4px;">Email</div>'
        . '<div style="font-size:16px;color:#ffffff;font-family:monospace;font-weight:600;">' . $e . '</div></td></tr></table>'

        // Password row
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">'
        . '<tr><td style="width:32px;vertical-align:top;padding-right:12px;">'
        . '<div style="width:28px;height:28px;background:rgba(227,30,36,0.12);border-radius:6px;text-align:center;line-height:28px;font-size:14px;">&#128273;</div>'
        . '</td><td><div style="font-size:10px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:2px;margin-bottom:4px;">Contrase&ntilde;a temporal</div>'
        . '<div style="font-size:16px;color:#ffffff;font-family:monospace;font-weight:600;background:#222;display:inline-block;padding:4px 12px;border-radius:6px;">' . $p . '</div></td></tr></table>'

        // Dates row
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0">'
        . '<tr><td style="width:32px;vertical-align:top;padding-right:12px;">'
        . '<div style="width:28px;height:28px;background:rgba(227,30,36,0.12);border-radius:6px;text-align:center;line-height:28px;font-size:14px;">&#128197;</div>'
        . '</td><td><div style="font-size:10px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:2px;margin-bottom:4px;">Vigencia del reto</div>'
        . '<div style="font-size:15px;color:#ffffff;font-weight:600;">' . $startFmt . ' <span style="color:#e31e24;">&rarr;</span> ' . $endFmt . '</div>'
        . '<div style="font-size:12px;color:#666;margin-top:2px;">30 d&iacute;as de acceso completo</div></td></tr></table>'

        . '</td></tr></table></td></tr>'

        // CTA BUTTON
        . '<tr><td style="background:#111111;padding:32px 40px 0;text-align:center;">'
        . '<a href="' . $loginUrl . '" style="display:inline-block;background:linear-gradient(135deg,#e31e24 0%,#c01020 100%);color:#ffffff;text-decoration:none;font-size:16px;font-weight:800;padding:18px 52px;border-radius:10px;text-transform:uppercase;letter-spacing:2px;">Iniciar Sesi&oacute;n &rarr;</a>'
        . '<p style="margin:14px 0 0;font-size:12px;color:#555;"><a href="' . $loginUrl . '" style="color:#555;text-decoration:underline;">' . $loginUrl . '</a></p>'
        . '</td></tr>'

        // STEPS
        . '<tr><td style="background:#111111;padding:36px 40px 0;">'
        . '<p style="margin:0 0 20px;font-size:12px;font-weight:700;letter-spacing:3px;color:#555;text-transform:uppercase;">Primeros pasos</p>'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0">'
        . '<tr><td style="padding-bottom:14px;"><table cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="width:32px;vertical-align:top;"><div style="width:24px;height:24px;background:#e31e24;border-radius:50%;text-align:center;line-height:24px;font-size:12px;font-weight:800;color:#fff;">1</div></td>'
        . '<td style="vertical-align:top;padding-left:10px;"><p style="margin:0;font-size:14px;color:#cccccc;line-height:1.6;">Inicia sesi&oacute;n con tus credenciales en <strong style="color:#fff;">wellcorefitness.com/login.html</strong></p></td>'
        . '</tr></table></td></tr>'
        . '<tr><td style="padding-bottom:14px;"><table cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="width:32px;vertical-align:top;"><div style="width:24px;height:24px;background:#e31e24;border-radius:50%;text-align:center;line-height:24px;font-size:12px;font-weight:800;color:#fff;">2</div></td>'
        . '<td style="vertical-align:top;padding-left:10px;"><p style="margin:0;font-size:14px;color:#cccccc;line-height:1.6;">Cambia tu contrase&ntilde;a temporal por una segura</p></td>'
        . '</tr></table></td></tr>'
        . '<tr><td><table cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="width:32px;vertical-align:top;"><div style="width:24px;height:24px;background:#e31e24;border-radius:50%;text-align:center;line-height:24px;font-size:12px;font-weight:800;color:#fff;">3</div></td>'
        . '<td style="vertical-align:top;padding-left:10px;"><p style="margin:0;font-size:14px;color:#cccccc;line-height:1.6;">Accede a tu dashboard RISE y comienza el entrenamiento del D&iacute;a 1</p></td>'
        . '</tr></table></td></tr>'
        . '</table></td></tr>'

        // SECURITY NOTE
        . '<tr><td style="background:#111111;padding:24px 40px 0;">'
        . '<div style="background:#141414;border:1px solid #222;border-radius:8px;padding:16px 20px;">'
        . '<p style="margin:0;font-size:12px;color:#555;line-height:1.7;">&#128274; <strong style="color:#666;">Seguridad:</strong> Nunca compartiremos tu contrase&ntilde;a. Ante cualquier duda, responde este correo.</p>'
        . '</div></td></tr>'

        // FOOTER
        . '<tr><td style="background:#0d0d0d;border-radius:0 0 16px 16px;padding:28px 40px;text-align:center;margin-top:32px;border-top:1px solid #1a1a1a;">'
        . '<p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#333;letter-spacing:2px;text-transform:uppercase;">WellCore Fitness</p>'
        . '<p style="margin:0;font-size:11px;color:#333;">wellcorefitness.com &mdash; Tu transformaci&oacute;n, nuestro compromiso</p>'
        . '</td></tr>'

        . '</table></td></tr></table></body></html>';

    $subject = 'Tu acceso al Reto RISE 30 Dias esta listo';
    $result  = sendEmail($toEmail, $subject, $html);
    return $result['ok'] ?? false;
}

requireMethod('POST');

$admin = requireAdminRole('admin', 'jefe', 'superadmin');

$input = getJsonBody();

$required = ['email', 'name', 'password', 'experience_level', 'training_location', 'gender'];
foreach ($required as $field) {
    if (empty(trim($input[$field] ?? ''))) {
        respondError("Campo requerido: $field", 400);
    }
}

$email             = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
$name              = htmlspecialchars(trim($input['name']), ENT_QUOTES, 'UTF-8');
$password          = $input['password'];
$experience_level  = $input['experience_level'];
$training_location = $input['training_location'];
$gender            = $input['gender'];

$valid_experience  = ['principiante', 'intermedio', 'avanzado'];
$valid_location    = ['gym', 'home', 'hybrid'];
$valid_gender      = ['male', 'female', 'other'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respondError('Email invalido', 400);
if (!in_array($experience_level, $valid_experience)) respondError('experience_level invalido', 400);
if (!in_array($training_location, $valid_location)) respondError('training_location invalido', 400);
if (!in_array($gender, $valid_gender)) respondError('gender invalido', 400);
if (strlen($password) < 6) respondError('La contrasena debe tener al menos 6 caracteres', 400);

$db = getDB();

$stmt = $db->prepare("SELECT id FROM clients WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->rowCount() > 0) {
    respondError('Este email ya esta registrado', 409);
}

$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$client_code   = 'rise-' . strtoupper(bin2hex(random_bytes(4)));
$start_date    = date('Y-m-d');
$end_date      = date('Y-m-d', strtotime('+30 days'));

$db->beginTransaction();
try {
    $stmt = $db->prepare("
        INSERT INTO clients (client_code, name, email, password_hash, plan, status, must_change_password, created_at)
        VALUES (?, ?, ?, ?, 'rise', 'activo', 1, NOW())
    ");
    $stmt->execute([$client_code, $name, $email, $password_hash]);
    $client_id = $db->lastInsertId();

    $stmt = $db->prepare("
        INSERT INTO rise_programs
        (client_id, start_date, end_date, experience_level, training_location, gender, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$client_id, $start_date, $end_date, $experience_level, $training_location, $gender]);
    $program_id = $db->lastInsertId();

    $db->commit();

    // Enviar email de bienvenida via SMTP
    $emailSent = sendRiseWelcomeEmail($email, $name, $password, $start_date, $end_date);

    respond([
        'success'    => true,
        'message'    => 'Acceso RISE creado correctamente',
        'invited_by' => $admin['username'] ?? 'admin',
        'email_sent' => $emailSent,
        'client'     => [
            'id'    => $client_id,
            'code'  => $client_code,
            'name'  => $name,
            'email' => $email,
            'plan'  => 'rise'
        ],
        'program'    => [
            'id'            => $program_id,
            'start_date'    => $start_date,
            'end_date'      => $end_date,
            'duration_days' => 30
        ]
    ], 201);

} catch (PDOException $e) {
    $db->rollBack();
    respondError('Error en base de datos', 500);
}
?>
