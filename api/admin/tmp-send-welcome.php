<?php
/**
 * TEMPORAL — Asigna password a cliente y envía correo de bienvenida premium
 * DELETE after use.
 * GET /api/admin/tmp-send-welcome.php?action=set_password&client_id=16&password=RiseAD1D44!
 * GET /api/admin/tmp-send-welcome.php?action=send_email&client_id=16&password=RiseAD1D44!&cc=analyticfitness@gmail.com
 * GET /api/admin/tmp-send-welcome.php?action=test_login&email=langarita499@unab.edu.co&password=RiseAD1D44!
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/email.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? 'check';
$db = getDB();

if ($action === 'set_password') {
    $clientId = (int) ($_GET['client_id'] ?? 0);
    $password = $_GET['password'] ?? '';
    if (!$clientId || !$password) { echo json_encode(['error' => 'client_id and password required']); exit; }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare("UPDATE clients SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hash, $clientId]);

    echo json_encode(['ok' => true, 'message' => "Password set for client $clientId", 'rows' => $stmt->rowCount()]);

} elseif ($action === 'test_login') {
    $email = $_GET['email'] ?? '';
    $password = $_GET['password'] ?? '';
    $stmt = $db->prepare("SELECT id, name, email, password_hash, plan, status FROM clients WHERE email = ?");
    $stmt->execute([$email]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) { echo json_encode(['error' => 'Client not found']); exit; }

    $valid = password_verify($password, $client['password_hash']);
    echo json_encode([
        'ok' => $valid,
        'client_id' => $client['id'],
        'name' => $client['name'],
        'plan' => $client['plan'],
        'status' => $client['status'],
        'password_works' => $valid,
    ]);

} elseif ($action === 'send_email') {
    $clientId = (int) ($_GET['client_id'] ?? 0);
    $password = $_GET['password'] ?? '';
    $cc = $_GET['cc'] ?? '';
    if (!$clientId || !$password) { echo json_encode(['error' => 'client_id and password required']); exit; }

    $stmt = $db->prepare("SELECT id, name, email, plan FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) { echo json_encode(['error' => 'Client not found']); exit; }

    $firstName = explode(' ', trim($client['name']))[0];
    $email = $client['email'];
    $year = date('Y');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Tu Acceso a WellCore Fitness</title>
<style>
@media only screen and (max-width:600px){
  .email-body{padding:20px 16px!important;}
  .hero-title{font-size:32px!important;letter-spacing:2px!important;}
  .btn-cta{display:block!important;text-align:center!important;padding:16px 24px!important;}
  .credential-box{padding:20px!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;color:#0a0a0a;">Tu acceso exclusivo al Reto RISE 30 dias esta listo. &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0a0a0a;">
<tr><td align="center" style="padding:32px 16px;">

  <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#0a0a0a;border:1px solid #1e1e22;border-radius:8px;overflow:hidden;">

    <!-- HEADER ROJO PREMIUM -->
    <tr>
      <td style="background:linear-gradient(135deg,#E31E24 0%,#b91519 100%);padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding:20px 32px;">
              <span style="font-size:14px;font-weight:700;color:rgba(255,255,255,0.9);letter-spacing:3px;text-transform:uppercase;">WellCore Fitness</span>
            </td>
            <td align="right" style="padding:20px 32px;">
              <span style="font-size:11px;color:rgba(255,255,255,0.6);letter-spacing:1px;text-transform:uppercase;">Reto RISE &bull; 30 Dias</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- HERO SECTION -->
    <tr>
      <td class="email-body" style="padding:48px 40px 32px;background:#0a0a0a;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td align="center">
              <!-- Icono de bienvenida -->
              <div style="width:72px;height:72px;border-radius:50%;background:rgba(227,30,36,0.12);border:2px solid rgba(227,30,36,0.3);display:inline-block;line-height:72px;text-align:center;margin-bottom:24px;">
                <span style="font-size:32px;color:#E31E24;">&#9733;</span>
              </div>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding-bottom:16px;">
              <h1 class="hero-title" style="margin:0;font-size:38px;font-weight:800;color:#ffffff;letter-spacing:3px;text-transform:uppercase;line-height:1.15;">BIENVENIDO,<br>{$firstName}</h1>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding-bottom:32px;">
              <p style="margin:0;font-size:16px;color:#a0a0a5;line-height:1.6;max-width:440px;">
                Tu cuenta en <strong style="color:#ffffff;">WellCore Fitness</strong> esta lista. A continuacion encontraras tus credenciales de acceso para ingresar a tu portal exclusivo del <strong style="color:#E31E24;">Reto RISE</strong>.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- SEPARADOR -->
    <tr>
      <td style="padding:0 40px;">
        <div style="height:1px;background:linear-gradient(90deg,transparent,#1e1e22,#E31E24,#1e1e22,transparent);"></div>
      </td>
    </tr>

    <!-- CREDENCIALES -->
    <tr>
      <td class="email-body" style="padding:32px 40px;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td>
              <p style="margin:0 0 16px;font-size:12px;font-weight:700;color:#E31E24;letter-spacing:2px;text-transform:uppercase;">Tus Credenciales de Acceso</p>
            </td>
          </tr>
          <tr>
            <td>
              <div class="credential-box" style="background:#111114;border:1px solid #1e1e22;border-radius:8px;padding:28px 32px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                  <tr>
                    <td style="padding-bottom:20px;">
                      <p style="margin:0 0 6px;font-size:11px;font-weight:600;color:#666;letter-spacing:1.5px;text-transform:uppercase;">Correo electronico</p>
                      <p style="margin:0;font-size:18px;font-weight:700;color:#ffffff;font-family:'Courier New',monospace;letter-spacing:0.5px;">{$email}</p>
                    </td>
                  </tr>
                  <tr>
                    <td style="padding-top:4px;border-top:1px solid #1e1e22;">
                      <p style="margin:16px 0 6px;font-size:11px;font-weight:600;color:#666;letter-spacing:1.5px;text-transform:uppercase;">Contrasena</p>
                      <p style="margin:0;font-size:22px;font-weight:700;color:#E31E24;font-family:'Courier New',monospace;letter-spacing:1.5px;">{$password}</p>
                    </td>
                  </tr>
                </table>
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- CTA BUTTON -->
    <tr>
      <td align="center" style="padding:8px 40px 40px;">
        <table cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td align="center" style="border-radius:6px;background:#E31E24;">
              <a class="btn-cta" href="https://wellcorefitness.com/login.html" target="_blank" style="display:inline-block;padding:16px 48px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:1.5px;text-transform:uppercase;font-family:Arial,Helvetica,sans-serif;">Ingresar a Mi Portal &rarr;</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- PASOS RAPIDOS -->
    <tr>
      <td style="padding:0 40px;">
        <div style="height:1px;background:linear-gradient(90deg,transparent,#1e1e22,#E31E24,#1e1e22,transparent);"></div>
      </td>
    </tr>
    <tr>
      <td class="email-body" style="padding:32px 40px;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td>
              <p style="margin:0 0 20px;font-size:12px;font-weight:700;color:#E31E24;letter-spacing:2px;text-transform:uppercase;">Como Empezar</p>
            </td>
          </tr>
          <tr>
            <td style="padding-bottom:16px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="36" valign="top">
                    <div style="width:28px;height:28px;border-radius:50%;background:rgba(227,30,36,0.15);border:1px solid rgba(227,30,36,0.3);text-align:center;line-height:28px;font-size:13px;font-weight:700;color:#E31E24;">1</div>
                  </td>
                  <td style="padding-left:12px;">
                    <p style="margin:0;font-size:14px;color:#ffffff;font-weight:600;">Ingresa a tu portal</p>
                    <p style="margin:4px 0 0;font-size:13px;color:#666;line-height:1.4;">Usa el boton de arriba o ve a <span style="color:#E31E24;">wellcorefitness.com/login.html</span></p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding-bottom:16px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="36" valign="top">
                    <div style="width:28px;height:28px;border-radius:50%;background:rgba(227,30,36,0.15);border:1px solid rgba(227,30,36,0.3);text-align:center;line-height:28px;font-size:13px;font-weight:700;color:#E31E24;">2</div>
                  </td>
                  <td style="padding-left:12px;">
                    <p style="margin:0;font-size:14px;color:#ffffff;font-weight:600;">Explora tu dashboard RISE</p>
                    <p style="margin:4px 0 0;font-size:13px;color:#666;line-height:1.4;">Ahi encontraras tu plan de entrenamiento personalizado, progreso y mas.</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td>
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td width="36" valign="top">
                    <div style="width:28px;height:28px;border-radius:50%;background:rgba(227,30,36,0.15);border:1px solid rgba(227,30,36,0.3);text-align:center;line-height:28px;font-size:13px;font-weight:700;color:#E31E24;">3</div>
                  </td>
                  <td style="padding-left:12px;">
                    <p style="margin:0;font-size:14px;color:#ffffff;font-weight:600;">Comienza tu transformacion</p>
                    <p style="margin:4px 0 0;font-size:13px;color:#666;line-height:1.4;">30 dias de entrenamiento intenso con seguimiento profesional.</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style="background:#08080a;padding:28px 40px;border-top:1px solid #1e1e22;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td>
              <p style="margin:0 0 4px;font-size:13px;font-weight:700;color:#ffffff;letter-spacing:1px;">WELLCORE FITNESS</p>
              <p style="margin:0;font-size:11px;color:#555;line-height:1.5;">Este correo contiene informacion confidencial de acceso.<br>No compartas tus credenciales con nadie.</p>
            </td>
            <td align="right" valign="bottom">
              <p style="margin:0;font-size:11px;color:#333;">&copy; {$year}</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>
HTML;

    $subject = "Tu Acceso al Reto RISE - WellCore Fitness";

    // Send to client
    $result1 = sendEmail($email, $subject, $html);

    // Send CC copy
    $result2 = ['ok' => false, 'error' => 'no cc'];
    if ($cc) {
        $result2 = sendEmail($cc, "[Copia] $subject — $email", $html);
    }

    echo json_encode([
        'ok' => $result1['ok'],
        'sent_to' => $email,
        'cc_to' => $cc,
        'result_client' => $result1,
        'result_cc' => $result2,
    ]);

} elseif ($action === 'check') {
    $clientId = (int) ($_GET['client_id'] ?? 16);
    $stmt = $db->prepare("SELECT id, name, email, plan, status, password_hash IS NOT NULL as has_password FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));

} else {
    echo json_encode(['error' => 'action: set_password, test_login, send_email, check']);
}
