<?php
// POST /api/admin/rise-gift.php
// Enviar regalo RISE — crea cuenta + envia email de regalo personalizado
// Body: {from_name, name, email, password, gift_message, experience_level, training_location, gender}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';

function sendRiseGiftEmail(string $toEmail, string $toName, string $fromName, string $giftMessage, string $password, string $startDate, string $endDate): bool {
    $formUrl  = 'https://wellcorefitness.com/inscripcion.html?plan=rise&paid=1';
    $loginUrl = 'https://wellcorefitness.com/login.html';
    $n  = htmlspecialchars($toName,   ENT_QUOTES, 'UTF-8');
    $fn = htmlspecialchars($fromName, ENT_QUOTES, 'UTF-8');
    $e  = htmlspecialchars($toEmail,  ENT_QUOTES, 'UTF-8');
    $p  = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
    $gm = $giftMessage ? htmlspecialchars($giftMessage, ENT_QUOTES, 'UTF-8') : '';
    $gm = nl2br($gm);

    $html = '<!DOCTYPE html>'
        . '<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">'
        . '<title>Tienes un regalo especial</title></head>'
        . '<body style="margin:0;padding:0;background-color:#0a0a0a;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0a0a0a;padding:40px 16px;"><tr><td align="center">'
        . '<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">'

        // ─── HEADER — elegante y limpio ───
        . '<tr><td style="background:linear-gradient(160deg,#1a0008 0%,#200515 50%,#1a0a0a 100%);border-radius:16px 16px 0 0;padding:52px 40px 44px;text-align:center;border-bottom:3px solid #ec4899;">'
        . '<div style="display:inline-block;background:rgba(236,72,153,0.12);border:1px solid rgba(236,72,153,0.25);border-radius:100px;padding:6px 20px;margin-bottom:20px;">'
        . '<span style="font-size:11px;font-weight:700;letter-spacing:4px;color:#ec4899;text-transform:uppercase;">WellCore Fitness</span></div>'
        . '<h1 style="margin:0 0 10px;font-size:42px;font-weight:900;color:#ffffff;line-height:1.1;letter-spacing:-1px;">Tienes un <span style="color:#ec4899;">Regalo</span></h1>'
        . '<p style="margin:0;font-size:16px;font-weight:600;color:rgba(255,255,255,0.5);letter-spacing:2px;">Reto RISE &middot; 30 D&iacute;as de Transformaci&oacute;n</p>'
        . '</td></tr>'

        // ─── Dedicatoria — tarjeta elegante ───
        . '<tr><td style="background:#111111;padding:36px 40px 0;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#161618;border:1px solid rgba(236,72,153,0.18);border-radius:12px;overflow:hidden;">'
        . '<tr><td style="background:linear-gradient(135deg,rgba(236,72,153,0.12),rgba(236,72,153,0.04));padding:6px;text-align:center;">'
        . '<span style="font-size:10px;font-weight:700;letter-spacing:3px;color:#ec4899;text-transform:uppercase;">&#127873; Dedicatoria</span>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 32px;text-align:center;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="width:45%;text-align:center;vertical-align:top;padding:0 8px;">'
        . '<div style="font-size:10px;font-weight:700;color:#666;letter-spacing:2px;text-transform:uppercase;margin-bottom:6px;">De</div>'
        . '<div style="font-size:20px;font-weight:800;color:#ffffff;">' . $fn . '</div>'
        . '</td>'
        . '<td style="width:10%;text-align:center;vertical-align:middle;">'
        . '<div style="font-size:20px;color:#ec4899;">&rarr;</div>'
        . '</td>'
        . '<td style="width:45%;text-align:center;vertical-align:top;padding:0 8px;">'
        . '<div style="font-size:10px;font-weight:700;color:#666;letter-spacing:2px;text-transform:uppercase;margin-bottom:6px;">Para</div>'
        . '<div style="font-size:20px;font-weight:800;color:#ec4899;">' . $n . '</div>'
        . '</td>'
        . '</tr></table>';

    if ($gm) {
        $html .= '<div style="height:1px;background:linear-gradient(90deg,transparent,rgba(236,72,153,0.25),transparent);margin:22px 0 18px;"></div>'
            . '<p style="margin:0;font-size:15px;color:rgba(255,255,255,0.85);line-height:1.8;font-style:italic;">&ldquo;' . $gm . '&rdquo;</p>';
    }

    $html .= '</td></tr></table></td></tr>'

        // ─── Intro motivacional ───
        . '<tr><td style="background:#111111;padding:32px 40px 0;">'
        . '<p style="margin:0;font-size:16px;line-height:1.9;color:#999999;"><span style="color:#ec4899;font-weight:700;">' . $fn . '</span> te ha regalado el acceso completo al <strong style="color:#ffffff;">Reto RISE 30 d&iacute;as</strong> de WellCore Fitness &mdash; nuestro programa de transformaci&oacute;n f&iacute;sica m&aacute;s completo, con plan de entrenamiento personalizado, seguimiento profesional y acceso a todas las herramientas de la plataforma.</p>'
        . '</td></tr>'

        // ─── Urgencia suave ───
        . '<tr><td style="background:#111111;padding:20px 40px 0;">'
        . '<div style="background:rgba(250,204,21,0.06);border:1px solid rgba(250,204,21,0.2);border-radius:10px;padding:16px 20px;text-align:center;">'
        . '<p style="margin:0;font-size:14px;color:#facc15;font-weight:700;">&#9200; Tienes 3 a 5 d&iacute;as para activar tu regalo</p>'
        . '<p style="margin:6px 0 0;font-size:12px;color:#999;line-height:1.6;">No te preocupes &mdash; los 30 d&iacute;as del reto comienzan a contar desde que recibas tu plan personalizado. &iexcl;Pero no dejes pasar la oportunidad!</p>'
        . '</div></td></tr>'

        // ─── Divider ───
        . '<tr><td style="background:#111111;padding:28px 40px 0;"><div style="height:1px;background:linear-gradient(90deg,transparent,#333,transparent);"></div></td></tr>'

        // ─── Credenciales ───
        . '<tr><td style="background:#111111;padding:28px 40px 0;">'
        . '<p style="margin:0 0 16px;font-size:12px;font-weight:700;letter-spacing:3px;color:#ec4899;text-transform:uppercase;">Tus credenciales de acceso</p>'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#1a1a1a;border:1px solid #2a2a2a;border-left:4px solid #ec4899;border-radius:12px;">'
        . '<tr><td style="padding:28px 30px;">'

        // Email
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">'
        . '<tr><td style="width:32px;vertical-align:top;padding-right:12px;">'
        . '<div style="width:28px;height:28px;background:rgba(236,72,153,0.12);border-radius:6px;text-align:center;line-height:28px;font-size:14px;">&#128231;</div>'
        . '</td><td><div style="font-size:10px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:2px;margin-bottom:4px;">Email</div>'
        . '<div style="font-size:16px;color:#ffffff;font-family:monospace;font-weight:600;">' . $e . '</div></td></tr></table>'

        // Password
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0">'
        . '<tr><td style="width:32px;vertical-align:top;padding-right:12px;">'
        . '<div style="width:28px;height:28px;background:rgba(236,72,153,0.12);border-radius:6px;text-align:center;line-height:28px;font-size:14px;">&#128273;</div>'
        . '</td><td><div style="font-size:10px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:2px;margin-bottom:4px;">Contrase&ntilde;a temporal</div>'
        . '<div style="font-size:16px;color:#ffffff;font-family:monospace;font-weight:600;background:#222;display:inline-block;padding:4px 12px;border-radius:6px;">' . $p . '</div></td></tr></table>'

        . '</td></tr></table></td></tr>'

        // ─── CTA — boton grande ───
        . '<tr><td style="background:#111111;padding:32px 40px 0;text-align:center;">'
        . '<a href="' . $formUrl . '" style="display:inline-block;background:linear-gradient(135deg,#ec4899 0%,#e31e24 100%);color:#ffffff;text-decoration:none;font-size:17px;font-weight:800;padding:20px 56px;border-radius:12px;text-transform:uppercase;letter-spacing:2px;box-shadow:0 4px 24px rgba(236,72,153,0.3);">Abrir mi Regalo &rarr;</a>'
        . '</td></tr>'

        // ─── Paso a paso completo ───
        . '<tr><td style="background:#111111;padding:36px 40px 0;">'
        . '<p style="margin:0 0 20px;font-size:12px;font-weight:700;letter-spacing:3px;color:#ec4899;text-transform:uppercase;">Tu camino comienza aqu&iacute;</p>'
        . '<table width="100%" cellpadding="0" cellspacing="0" border="0">'

        // Paso 1
        . '<tr><td style="padding-bottom:16px;"><table cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="width:36px;vertical-align:top;"><div style="width:28px;height:28px;background:linear-gradient(135deg,#ec4899,#e31e24);border-radius:50%;text-align:center;line-height:28px;font-size:13px;font-weight:800;color:#fff;">1</div></td>'
        . '<td style="vertical-align:top;padding-left:12px;"><p style="margin:0 0 2px;font-size:14px;font-weight:700;color:#ffffff;">Completa tu formulario de inscripci&oacute;n</p>'
        . '<p style="margin:0;font-size:12px;color:#888;line-height:1.5;">Haz click en &ldquo;Abrir mi Regalo&rdquo; y llena tus datos personales, objetivos y preferencias de entrenamiento.</p></td>'
        . '</tr></table></td></tr>'

        // Paso 2
        . '<tr><td style="padding-bottom:16px;"><table cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="width:36px;vertical-align:top;"><div style="width:28px;height:28px;background:linear-gradient(135deg,#ec4899,#e31e24);border-radius:50%;text-align:center;line-height:28px;font-size:13px;font-weight:800;color:#fff;">2</div></td>'
        . '<td style="vertical-align:top;padding-left:12px;"><p style="margin:0 0 2px;font-size:14px;font-weight:700;color:#ffffff;">Inicia sesi&oacute;n con tus credenciales</p>'
        . '<p style="margin:0;font-size:12px;color:#888;line-height:1.5;">Usa tu email y contrase&ntilde;a temporal en <strong style="color:#bbb;">wellcorefitness.com/login.html</strong></p></td>'
        . '</tr></table></td></tr>'

        // Paso 3
        . '<tr><td style="padding-bottom:16px;"><table cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="width:36px;vertical-align:top;"><div style="width:28px;height:28px;background:linear-gradient(135deg,#ec4899,#e31e24);border-radius:50%;text-align:center;line-height:28px;font-size:13px;font-weight:800;color:#fff;">3</div></td>'
        . '<td style="vertical-align:top;padding-left:12px;"><p style="margin:0 0 2px;font-size:14px;font-weight:700;color:#ffffff;">Cambia tu contrase&ntilde;a</p>'
        . '<p style="margin:0;font-size:12px;color:#888;line-height:1.5;">El sistema te pedir&aacute; crear una contrase&ntilde;a segura y personal. Es por tu seguridad.</p></td>'
        . '</tr></table></td></tr>'

        // Paso 4
        . '<tr><td style="padding-bottom:16px;"><table cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="width:36px;vertical-align:top;"><div style="width:28px;height:28px;background:linear-gradient(135deg,#ec4899,#e31e24);border-radius:50%;text-align:center;line-height:28px;font-size:13px;font-weight:800;color:#fff;">4</div></td>'
        . '<td style="vertical-align:top;padding-left:12px;"><p style="margin:0 0 2px;font-size:14px;font-weight:700;color:#ffffff;">Sube tus fotos de progreso</p>'
        . '<p style="margin:0;font-size:12px;color:#888;line-height:1.5;">Desde tu dashboard, sube fotos de frente, lateral y espalda. Son privadas y servir&aacute;n para medir tu transformaci&oacute;n.</p></td>'
        . '</tr></table></td></tr>'

        // Paso 5
        . '<tr><td style="padding-bottom:16px;"><table cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="width:36px;vertical-align:top;"><div style="width:28px;height:28px;background:linear-gradient(135deg,#ec4899,#e31e24);border-radius:50%;text-align:center;line-height:28px;font-size:13px;font-weight:800;color:#fff;">5</div></td>'
        . '<td style="vertical-align:top;padding-left:12px;"><p style="margin:0 0 2px;font-size:14px;font-weight:700;color:#ffffff;">Recibe tu plan personalizado</p>'
        . '<p style="margin:0;font-size:12px;color:#888;line-height:1.5;">Tu coach te asignar&aacute; un plan de entrenamiento hecho a tu medida. Los 30 d&iacute;as del reto comienzan desde este momento.</p></td>'
        . '</tr></table></td></tr>'

        // Paso 6
        . '<tr><td><table cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="width:36px;vertical-align:top;"><div style="width:28px;height:28px;background:linear-gradient(135deg,#ec4899,#e31e24);border-radius:50%;text-align:center;line-height:28px;font-size:13px;font-weight:800;color:#fff;">6</div></td>'
        . '<td style="vertical-align:top;padding-left:12px;"><p style="margin:0 0 2px;font-size:14px;font-weight:700;color:#ffffff;">&iexcl;Comienza tu transformaci&oacute;n!</p>'
        . '<p style="margin:0;font-size:12px;color:#888;line-height:1.5;">Registra tus entrenamientos, completa misiones diarias, y disfruta el proceso. &iexcl;Esto es para ti!</p></td>'
        . '</tr></table></td></tr>'

        . '</table></td></tr>'

        // ─── Motivacion final ───
        . '<tr><td style="background:#111111;padding:28px 40px 0;text-align:center;">'
        . '<div style="background:linear-gradient(135deg,rgba(236,72,153,0.06),rgba(227,30,36,0.06));border:1px solid rgba(236,72,153,0.15);border-radius:10px;padding:20px 24px;">'
        . '<p style="margin:0;font-size:15px;color:#ffffff;font-weight:600;line-height:1.7;">&ldquo;El mejor proyecto en el que puedes trabajar eres t&uacute;.&rdquo;</p>'
        . '<p style="margin:8px 0 0;font-size:12px;color:#ec4899;font-weight:600;">&#8212; WellCore Fitness</p>'
        . '</div></td></tr>'

        // ─── Nota seguridad ───
        . '<tr><td style="background:#111111;padding:24px 40px 0;">'
        . '<div style="background:#141414;border:1px solid #222;border-radius:8px;padding:16px 20px;">'
        . '<p style="margin:0;font-size:12px;color:#555;line-height:1.7;">&#128274; <strong style="color:#666;">Seguridad:</strong> Nunca compartiremos tu contrase&ntilde;a. Ante cualquier duda, responde este correo.</p>'
        . '</div></td></tr>'

        // ─── Footer ───
        . '<tr><td style="background:#0d0d0d;border-radius:0 0 16px 16px;padding:28px 40px;text-align:center;margin-top:32px;border-top:1px solid #1a1a1a;">'
        . '<p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#333;letter-spacing:2px;text-transform:uppercase;">WellCore Fitness</p>'
        . '<p style="margin:0;font-size:11px;color:#333;">wellcorefitness.com &mdash; Tu transformaci&oacute;n, nuestro compromiso</p>'
        . '</td></tr>'

        . '</table></td></tr></table></body></html>';

    $subject = $fn . ' te ha regalado el Reto RISE — WellCore Fitness';
    $result = sendEmail($toEmail, $subject, $html);
    return $result['ok'] ?? false;
}

requireMethod('POST');

$admin = requireAdminRole('admin', 'jefe', 'superadmin');

$input = getJsonBody();

$required = ['from_name', 'email', 'name', 'password'];
foreach ($required as $field) {
    if (empty(trim($input[$field] ?? ''))) {
        respondError("Campo requerido: $field", 400);
    }
}

$fromName          = trim($input['from_name']);
$email             = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
$name              = htmlspecialchars(trim($input['name']), ENT_QUOTES, 'UTF-8');
$password          = $input['password'];
$giftMessage       = trim($input['gift_message'] ?? '');
$experience_level  = $input['experience_level'] ?? 'intermedio';
$training_location = $input['training_location'] ?? 'hybrid';
$gender            = $input['gender'] ?? 'female';

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
$client_code   = 'rise-gift-' . strtoupper(bin2hex(random_bytes(4)));
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

    $emailSent = sendRiseGiftEmail($email, $name, $fromName, $giftMessage, $password, $start_date, $end_date);

    respond([
        'success'    => true,
        'message'    => 'Regalo RISE enviado correctamente',
        'gift_from'  => $fromName,
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
    error_log('[WellCore] rise-gift error: ' . $e->getMessage());
    respondError('Error en base de datos', 500);
}
?>
