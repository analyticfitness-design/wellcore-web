<?php
/**
 * WellCore Fitness — Email Templates
 * ============================================================
 * Plantillas HTML para emails transaccionales.
 * Usa table-based layout (compatible con todos los clientes de email).
 *
 * Funciones disponibles:
 *   email_rise_payment_confirmed(string $name, string $gender, string $dashboardUrl): string
 *   email_rise_plan_ready(string $name, string $gender, string $planUrl, string $dashboardUrl): string
 * ============================================================
 */

/**
 * Email de confirmación de pago RISE.
 * Enviado inmediatamente cuando Wompi aprueba el pago.
 */
function email_rise_payment_confirmed(string $name, string $gender = 'male', string $dashboardUrl = 'https://wellcorefitness.com/rise-dashboard.html'): string {
    $firstName = explode(' ', trim($name))[0];

    if ($gender === 'female') {
        return _email_rise_female_payment($firstName, $dashboardUrl);
    }
    return _email_rise_male_payment($firstName, $dashboardUrl);
}

/**
 * Email de plan listo — enviado cuando el admin aprueba y activa el plan IA.
 */
function email_rise_plan_ready(string $name, string $gender = 'male', string $planUrl = '', string $dashboardUrl = 'https://wellcorefitness.com/rise-dashboard.html'): string {
    $firstName = explode(' ', trim($name))[0];

    if ($gender === 'female') {
        return _email_rise_female_plan($firstName, $planUrl, $dashboardUrl);
    }
    return _email_rise_male_plan($firstName, $planUrl, $dashboardUrl);
}

// ─────────────────────────────────────────────────────────────
// PLANTILLAS MASCULINAS — Identidad WellCore (rojo #E31E24)
// ─────────────────────────────────────────────────────────────

function _email_rise_male_payment(string $firstName, string $dashboardUrl): string {
    $year = date('Y');
    $fn   = htmlspecialchars($firstName);
    $url  = htmlspecialchars($dashboardUrl);

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Bienvenido al Reto RISE — WellCore Fitness</title>
<style>
@media only screen and (max-width:600px){
  .email-body{padding:20px 16px!important;}
  .hero-title{font-size:36px!important;}
  .btn-cta{display:block!important;text-align:center!important;}
  .col-2{display:block!important;width:100%!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:Arial,Helvetica,sans-serif;">
<!-- Preheader oculto -->
<div style="display:none;max-height:0;overflow:hidden;color:#0a0a0a;">Pago confirmado. Tu reto de 30 días comienza ahora. &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0a0a0a;">
<tr><td align="center" style="padding:32px 16px;">

  <!-- CONTENEDOR PRINCIPAL 600px -->
  <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#0a0a0a;border:1px solid #1e1e22;">

    <!-- HEADER ROJO -->
    <tr>
      <td style="background:#E31E24;padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding:20px 32px;">
              <span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:900;letter-spacing:4px;color:#ffffff;text-transform:uppercase;">WELLCORE</span>
              <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:400;letter-spacing:2px;color:rgba(255,255,255,0.75);margin-left:8px;text-transform:uppercase;">FITNESS</span>
            </td>
            <td align="right" style="padding:20px 32px;">
              <span style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,0.7);text-transform:uppercase;">RETO RISE &middot; 30 DÍAS</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- HERO -->
    <tr>
      <td class="email-body" style="padding:48px 40px 36px;background:#111113;border-bottom:1px solid #1e1e22;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:10px;font-weight:700;letter-spacing:3px;color:#E31E24;text-transform:uppercase;margin:0 0 14px 0;">// Pago confirmado</p>
        <h1 class="hero-title" style="font-family:Arial,Helvetica,sans-serif;font-size:48px;font-weight:900;letter-spacing:2px;color:#ffffff;text-transform:uppercase;line-height:0.95;margin:0 0 20px 0;">
          BIENVENIDO<br><span style="color:#E31E24;">AL RETO</span>
        </h1>
        <p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:400;color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 28px 0;">
          {$fn}, tu pago fue confirmado. En las próximas horas tu coach preparará tu plan personalizado de 30 días. Te notificaremos cuando esté listo.
        </p>
        <table cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background:#E31E24;padding:0;">
              <a href="{$url}" class="btn-cta" style="display:inline-block;font-family:'Courier New',Courier,monospace;font-size:12px;font-weight:700;letter-spacing:2px;color:#ffffff;text-decoration:none;padding:14px 32px;text-transform:uppercase;background:#E31E24;">
                → Acceder a mi Dashboard
              </a>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- QUÉ VIENE -->
    <tr>
      <td style="padding:36px 40px;background:#0a0a0a;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:3px;color:#E31E24;text-transform:uppercase;margin:0 0 6px 0;">// Lo que viene</p>
        <h2 style="font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:900;letter-spacing:1px;color:#ffffff;text-transform:uppercase;margin:0 0 24px 0;">TU RETO EMPIEZA HOY</h2>

        <!-- 3 pasos -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td class="col-2" width="33%" style="padding:0 8px 0 0;vertical-align:top;">
              <div style="background:#111113;border-top:3px solid #E31E24;padding:20px 16px;">
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:32px;font-weight:900;color:#E31E24;line-height:1;margin-bottom:8px;">01</div>
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Plan Personalizado</div>
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:rgba(255,255,255,0.5);line-height:1.6;">Tu coach diseñará tu programa de entrenamiento y guía nutricional en las próximas horas.</div>
              </div>
            </td>
            <td class="col-2" width="33%" style="padding:0 4px;vertical-align:top;">
              <div style="background:#111113;border-top:3px solid #E31E24;padding:20px 16px;">
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:32px;font-weight:900;color:#E31E24;line-height:1;margin-bottom:8px;">02</div>
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Dashboard RISE</div>
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:rgba(255,255,255,0.5);line-height:1.6;">Accede a tu dashboard con tu email y la contraseña que creaste durante la inscripción.</div>
              </div>
            </td>
            <td class="col-2" width="33%" style="padding:0 0 0 8px;vertical-align:top;">
              <div style="background:#111113;border-top:3px solid #E31E24;padding:20px 16px;">
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:32px;font-weight:900;color:#E31E24;line-height:1;margin-bottom:8px;">30</div>
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Días de Reto</div>
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:rgba(255,255,255,0.5);line-height:1.6;">Sigue el plan al pie de la letra. En 30 días verás resultados reales si hay adherencia.</div>
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- MENSAJE DEL COACH -->
    <tr>
      <td style="padding:0 40px 36px;background:#0a0a0a;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#111113;border-left:3px solid #E31E24;">
          <tr>
            <td style="padding:20px 24px;">
              <p style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:#E31E24;text-transform:uppercase;margin:0 0 8px 0;">// Mensaje del Coach</p>
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:rgba(255,255,255,0.75);line-height:1.75;margin:0;font-style:italic;">
                "El reto no es perfecto — es consistente. Cada día que entrenas y te alimentas bien es una victoria. Estamos acá para guiarte en cada paso."
              </p>
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#ffffff;margin:12px 0 0 0;letter-spacing:0.5px;">— Equipo WellCore Fitness</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style="background:#111113;border-top:1px solid #1e1e22;padding:24px 40px;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td>
              <span style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:900;letter-spacing:3px;color:#ffffff;text-transform:uppercase;">WELL<span style="color:#E31E24;">CORE</span></span>
            </td>
            <td align="right">
              <span style="font-family:'Courier New',Courier,monospace;font-size:9px;color:rgba(255,255,255,0.3);letter-spacing:1px;">wellcorefitness.com</span>
            </td>
          </tr>
          <tr>
            <td colspan="2" style="padding-top:14px;border-top:1px solid #1e1e22;margin-top:14px;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:rgba(255,255,255,0.25);line-height:1.6;margin:0;">
                &copy; {$year} WellCore Fitness. Recibiste este email porque te inscribiste al Reto RISE 30 Días.<br>
                info@wellcorefitness.com &middot; @wellcore.fitness
              </p>
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
}

function _email_rise_male_plan(string $firstName, string $planUrl, string $dashboardUrl): string {
    $year    = date('Y');
    $fn      = htmlspecialchars($firstName);
    $dUrl    = htmlspecialchars($dashboardUrl);
    $hasPlan = !empty($planUrl);
    $pUrl    = $hasPlan ? htmlspecialchars($planUrl) : htmlspecialchars($dashboardUrl);

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tu Plan RISE está listo — WellCore Fitness</title>
<style>
@media only screen and (max-width:600px){
  .email-body{padding:20px 16px!important;}
  .hero-title{font-size:36px!important;}
  .btn-cta{display:block!important;text-align:center!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;color:#0a0a0a;">Tu plan personalizado de 30 días está listo. Entra ya y empieza. &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0a0a0a;">
<tr><td align="center" style="padding:32px 16px;">
  <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#0a0a0a;border:1px solid #1e1e22;">

    <!-- HEADER -->
    <tr>
      <td style="background:#E31E24;padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding:20px 32px;">
              <span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:900;letter-spacing:4px;color:#ffffff;text-transform:uppercase;">WELLCORE</span>
              <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:400;letter-spacing:2px;color:rgba(255,255,255,0.75);margin-left:8px;text-transform:uppercase;">FITNESS</span>
            </td>
            <td align="right" style="padding:20px 32px;">
              <span style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,0.7);text-transform:uppercase;">PLAN LISTO</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- HERO -->
    <tr>
      <td class="email-body" style="padding:48px 40px 36px;background:#111113;border-bottom:1px solid #1e1e22;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:10px;font-weight:700;letter-spacing:3px;color:#E31E24;text-transform:uppercase;margin:0 0 14px 0;">// Tu plan está listo</p>
        <h1 class="hero-title" style="font-family:Arial,Helvetica,sans-serif;font-size:48px;font-weight:900;letter-spacing:2px;color:#ffffff;text-transform:uppercase;line-height:0.95;margin:0 0 20px 0;">
          {$fn},<br><span style="color:#E31E24;">COMIENZA</span><br>AHORA
        </h1>
        <p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:400;color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 28px 0;">
          Tu plan de entrenamiento personalizado de 30 días y tu guía de alimentación ya están disponibles en tu dashboard. No esperes — hoy es el mejor día para empezar.
        </p>
        <table cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background:#E31E24;padding:0;margin-right:12px;">
              <a href="{$pUrl}" class="btn-cta" style="display:inline-block;font-family:'Courier New',Courier,monospace;font-size:12px;font-weight:700;letter-spacing:2px;color:#ffffff;text-decoration:none;padding:14px 32px;text-transform:uppercase;background:#E31E24;">
                → Ver mi Plan
              </a>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- QUÉ INCLUYE -->
    <tr>
      <td style="padding:36px 40px;background:#0a0a0a;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:3px;color:#E31E24;text-transform:uppercase;margin:0 0 6px 0;">// Tu plan incluye</p>
        <h2 style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:900;letter-spacing:1px;color:#ffffff;text-transform:uppercase;margin:0 0 20px 0;">DISEÑADO PARA TI</h2>

        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding:0 0 10px 0;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#111113;">
                <tr>
                  <td width="4" style="background:#E31E24;"></td>
                  <td style="padding:14px 20px;">
                    <div style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;color:#E31E24;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;">Entrenamiento</div>
                    <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:rgba(255,255,255,0.7);line-height:1.5;">4 semanas de progresión con ejercicios adaptados a tu nivel, lugar de entrenamiento y disponibilidad.</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:0 0 10px 0;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#111113;">
                <tr>
                  <td width="4" style="background:#00D9FF;"></td>
                  <td style="padding:14px 20px;">
                    <div style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;color:#00D9FF;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;">Cardio</div>
                    <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:rgba(255,255,255,0.7);line-height:1.5;">Protocolo de cardio personalizado según tus objetivos — integrado en tu semana para máximos resultados.</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td>
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#111113;">
                <tr>
                  <td width="4" style="background:#22C55E;"></td>
                  <td style="padding:14px 20px;">
                    <div style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;color:#22C55E;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;">Guía Nutricional</div>
                    <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:rgba(255,255,255,0.7);line-height:1.5;">Tips y principios de alimentación para que tus resultados sean reales y duraderos durante el reto.</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- CTA SECUNDARIO -->
    <tr>
      <td style="padding:0 40px 36px;background:#0a0a0a;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#111113;border:1px solid #1e1e22;">
          <tr>
            <td style="padding:24px;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;margin:0 0 8px 0;">¿Dudas o preguntas?</p>
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:rgba(255,255,255,0.5);line-height:1.6;margin:0 0 14px 0;">Estamos disponibles por WhatsApp y email para cualquier consulta sobre tu plan o tu reto.</p>
              <a href="https://wa.me/573124904720" style="font-family:'Courier New',Courier,monospace;font-size:11px;font-weight:700;letter-spacing:1px;color:#E31E24;text-decoration:none;text-transform:uppercase;">WhatsApp →</a>
              &nbsp;&nbsp;
              <a href="mailto:info@wellcorefitness.com" style="font-family:'Courier New',Courier,monospace;font-size:11px;font-weight:700;letter-spacing:1px;color:rgba(255,255,255,0.4);text-decoration:none;text-transform:uppercase;">Email →</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style="background:#111113;border-top:1px solid #1e1e22;padding:24px 40px;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td>
              <span style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:900;letter-spacing:3px;color:#ffffff;text-transform:uppercase;">WELL<span style="color:#E31E24;">CORE</span></span>
            </td>
            <td align="right">
              <span style="font-family:'Courier New',Courier,monospace;font-size:9px;color:rgba(255,255,255,0.3);letter-spacing:1px;">wellcorefitness.com</span>
            </td>
          </tr>
          <tr>
            <td colspan="2" style="padding-top:14px;border-top:1px solid #1e1e22;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:rgba(255,255,255,0.25);line-height:1.6;margin:0;">
                &copy; {$year} WellCore Fitness &middot; info@wellcorefitness.com &middot; @wellcore.fitness
              </p>
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
}

// ─────────────────────────────────────────────────────────────
// PLANTILLAS FEMENINAS — Identidad Rose (#DC3C64)
// ─────────────────────────────────────────────────────────────

function _email_rise_female_payment(string $firstName, string $dashboardUrl): string {
    $year = date('Y');
    $fn   = htmlspecialchars($firstName);
    $url  = htmlspecialchars($dashboardUrl);

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Bienvenida al Reto RISE — WellCore Fitness</title>
<style>
@media only screen and (max-width:600px){
  .email-body{padding:20px 16px!important;}
  .hero-title{font-size:36px!important;}
  .btn-cta{display:block!important;text-align:center!important;}
  .col-2{display:block!important;width:100%!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;color:#0a0a0a;">Pago confirmado. Tu reto de 30 días comienza ahora. &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0a0a0a;">
<tr><td align="center" style="padding:32px 16px;">
  <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#0a0a0a;border:1px solid #1e1e22;">

    <!-- HEADER ROSE -->
    <tr>
      <td style="background:linear-gradient(135deg,#DC3C64 0%,#b82d50 100%);padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding:20px 32px;">
              <span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:900;letter-spacing:4px;color:#ffffff;text-transform:uppercase;">WELLCORE</span>
              <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:400;letter-spacing:2px;color:rgba(255,255,255,0.75);margin-left:8px;text-transform:uppercase;">FITNESS</span>
            </td>
            <td align="right" style="padding:20px 32px;">
              <span style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,0.7);text-transform:uppercase;">RETO RISE &middot; 30 DÍAS</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- HERO -->
    <tr>
      <td class="email-body" style="padding:48px 40px 36px;background:#111113;border-bottom:1px solid #1e1e22;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:10px;font-weight:700;letter-spacing:3px;color:#DC3C64;text-transform:uppercase;margin:0 0 14px 0;">// Pago confirmado</p>
        <h1 class="hero-title" style="font-family:Arial,Helvetica,sans-serif;font-size:48px;font-weight:900;letter-spacing:2px;color:#ffffff;text-transform:uppercase;line-height:0.95;margin:0 0 20px 0;">
          BIENVENIDA<br><span style="color:#DC3C64;">AL RETO</span>
        </h1>
        <p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:400;color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 28px 0;">
          {$fn}, tu pago fue confirmado. En las próximas horas tu coach preparará tu plan personalizado de 30 días. Te notificaremos cuando esté listo.
        </p>
        <table cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background:#DC3C64;padding:0;">
              <a href="{$url}" class="btn-cta" style="display:inline-block;font-family:'Courier New',Courier,monospace;font-size:12px;font-weight:700;letter-spacing:2px;color:#ffffff;text-decoration:none;padding:14px 32px;text-transform:uppercase;background:#DC3C64;">
                → Acceder a mi Dashboard
              </a>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- QUÉ VIENE -->
    <tr>
      <td style="padding:36px 40px;background:#0a0a0a;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:3px;color:#DC3C64;text-transform:uppercase;margin:0 0 6px 0;">// Lo que viene</p>
        <h2 style="font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:900;letter-spacing:1px;color:#ffffff;text-transform:uppercase;margin:0 0 24px 0;">TU RETO EMPIEZA HOY</h2>
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td class="col-2" width="33%" style="padding:0 8px 0 0;vertical-align:top;">
              <div style="background:#111113;border-top:3px solid #DC3C64;padding:20px 16px;">
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:32px;font-weight:900;color:#DC3C64;line-height:1;margin-bottom:8px;">01</div>
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Plan Personalizado</div>
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:rgba(255,255,255,0.5);line-height:1.6;">Tu coach diseñará tu programa completo según tu cuerpo, objetivos y estilo de vida.</div>
              </div>
            </td>
            <td class="col-2" width="33%" style="padding:0 4px;vertical-align:top;">
              <div style="background:#111113;border-top:3px solid #DC3C64;padding:20px 16px;">
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:32px;font-weight:900;color:#DC3C64;line-height:1;margin-bottom:8px;">02</div>
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Dashboard RISE</div>
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:rgba(255,255,255,0.5);line-height:1.6;">Accede con tu email y contraseña. Tu plan y progreso estarán ahí en todo momento.</div>
              </div>
            </td>
            <td class="col-2" width="33%" style="padding:0 0 0 8px;vertical-align:top;">
              <div style="background:#111113;border-top:3px solid #DC3C64;padding:20px 16px;">
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:32px;font-weight:900;color:#DC3C64;line-height:1;margin-bottom:8px;">30</div>
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Días de Reto</div>
                <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:rgba(255,255,255,0.5);line-height:1.6;">Con constancia y el plan correcto, en 30 días habrás transformado hábitos y cuerpo.</div>
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- MENSAJE -->
    <tr>
      <td style="padding:0 40px 36px;background:#0a0a0a;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#111113;border-left:3px solid #DC3C64;">
          <tr>
            <td style="padding:20px 24px;">
              <p style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:#DC3C64;text-transform:uppercase;margin:0 0 8px 0;">// Mensaje del Coach</p>
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:rgba(255,255,255,0.75);line-height:1.75;margin:0;font-style:italic;">
                "Este reto no es sobre ser perfecta — es sobre ser constante. Cada entrenamiento, cada buena decisión alimentaria, te lleva más cerca de la versión que quieres ser."
              </p>
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#ffffff;margin:12px 0 0 0;letter-spacing:0.5px;">— Equipo WellCore Fitness</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style="background:#111113;border-top:1px solid #1e1e22;padding:24px 40px;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td>
              <span style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:900;letter-spacing:3px;color:#ffffff;text-transform:uppercase;">WELL<span style="color:#DC3C64;">CORE</span></span>
            </td>
            <td align="right">
              <span style="font-family:'Courier New',Courier,monospace;font-size:9px;color:rgba(255,255,255,0.3);letter-spacing:1px;">wellcorefitness.com</span>
            </td>
          </tr>
          <tr>
            <td colspan="2" style="padding-top:14px;border-top:1px solid #1e1e22;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:rgba(255,255,255,0.25);line-height:1.6;margin:0;">
                &copy; {$year} WellCore Fitness &middot; info@wellcorefitness.com &middot; @wellcore.fitness
              </p>
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
}

function _email_rise_female_plan(string $firstName, string $planUrl, string $dashboardUrl): string {
    $year    = date('Y');
    $fn      = htmlspecialchars($firstName);
    $dUrl    = htmlspecialchars($dashboardUrl);
    $pUrl    = !empty($planUrl) ? htmlspecialchars($planUrl) : htmlspecialchars($dashboardUrl);

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tu Plan RISE está listo — WellCore Fitness</title>
<style>
@media only screen and (max-width:600px){
  .email-body{padding:20px 16px!important;}
  .hero-title{font-size:36px!important;}
  .btn-cta{display:block!important;text-align:center!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;color:#0a0a0a;">Tu plan personalizado de 30 días está listo. Entra ya y empieza. &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0a0a0a;">
<tr><td align="center" style="padding:32px 16px;">
  <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#0a0a0a;border:1px solid #1e1e22;">

    <tr>
      <td style="background:linear-gradient(135deg,#DC3C64 0%,#b82d50 100%);padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding:20px 32px;">
              <span style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:900;letter-spacing:4px;color:#ffffff;text-transform:uppercase;">WELLCORE</span>
              <span style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:400;letter-spacing:2px;color:rgba(255,255,255,0.75);margin-left:8px;text-transform:uppercase;">FITNESS</span>
            </td>
            <td align="right" style="padding:20px 32px;">
              <span style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,0.7);text-transform:uppercase;">PLAN LISTO</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <tr>
      <td class="email-body" style="padding:48px 40px 36px;background:#111113;border-bottom:1px solid #1e1e22;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:10px;font-weight:700;letter-spacing:3px;color:#DC3C64;text-transform:uppercase;margin:0 0 14px 0;">// Tu plan está listo</p>
        <h1 class="hero-title" style="font-family:Arial,Helvetica,sans-serif;font-size:48px;font-weight:900;letter-spacing:2px;color:#ffffff;text-transform:uppercase;line-height:0.95;margin:0 0 20px 0;">
          {$fn},<br><span style="color:#DC3C64;">EMPIEZA</span><br>AHORA
        </h1>
        <p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:400;color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 28px 0;">
          Tu plan de entrenamiento personalizado de 30 días y tu guía de alimentación ya están disponibles. El momento de empezar es hoy.
        </p>
        <table cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background:#DC3C64;padding:0;">
              <a href="{$pUrl}" class="btn-cta" style="display:inline-block;font-family:'Courier New',Courier,monospace;font-size:12px;font-weight:700;letter-spacing:2px;color:#ffffff;text-decoration:none;padding:14px 32px;text-transform:uppercase;background:#DC3C64;">
                → Ver mi Plan
              </a>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <tr>
      <td style="padding:36px 40px;background:#0a0a0a;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:3px;color:#DC3C64;text-transform:uppercase;margin:0 0 6px 0;">// Tu plan incluye</p>
        <h2 style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:900;letter-spacing:1px;color:#ffffff;text-transform:uppercase;margin:0 0 20px 0;">DISEÑADO PARA TI</h2>
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr><td style="padding:0 0 10px 0;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#111113;">
              <tr>
                <td width="4" style="background:#DC3C64;"></td>
                <td style="padding:14px 20px;">
                  <div style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;color:#DC3C64;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;">Entrenamiento</div>
                  <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:rgba(255,255,255,0.7);line-height:1.5;">4 semanas de progresión adaptadas a tu nivel, lugar y disponibilidad — para resultados reales.</div>
                </td>
              </tr>
            </table>
          </td></tr>
          <tr><td style="padding:0 0 10px 0;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#111113;">
              <tr>
                <td width="4" style="background:#00D9FF;"></td>
                <td style="padding:14px 20px;">
                  <div style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;color:#00D9FF;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;">Cardio</div>
                  <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:rgba(255,255,255,0.7);line-height:1.5;">Protocolo de cardio integrado según tus objetivos específicos.</div>
                </td>
              </tr>
            </table>
          </td></tr>
          <tr><td>
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#111113;">
              <tr>
                <td width="4" style="background:#22C55E;"></td>
                <td style="padding:14px 20px;">
                  <div style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;color:#22C55E;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;">Guía Nutricional</div>
                  <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:rgba(255,255,255,0.7);line-height:1.5;">Principios de alimentación que te darán resultados sin dietas rígidas ni restricciones extremas.</div>
                </td>
              </tr>
            </table>
          </td></tr>
        </table>
      </td>
    </tr>

    <tr>
      <td style="padding:0 40px 36px;background:#0a0a0a;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#111113;border:1px solid #1e1e22;">
          <tr>
            <td style="padding:24px;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;margin:0 0 8px 0;">¿Dudas o preguntas?</p>
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:rgba(255,255,255,0.5);line-height:1.6;margin:0 0 14px 0;">Estamos disponibles para resolver cualquier duda sobre tu plan o tu reto.</p>
              <a href="https://wa.me/573124904720" style="font-family:'Courier New',Courier,monospace;font-size:11px;font-weight:700;letter-spacing:1px;color:#DC3C64;text-decoration:none;text-transform:uppercase;">WhatsApp →</a>
              &nbsp;&nbsp;
              <a href="mailto:info@wellcorefitness.com" style="font-family:'Courier New',Courier,monospace;font-size:11px;font-weight:700;letter-spacing:1px;color:rgba(255,255,255,0.4);text-decoration:none;text-transform:uppercase;">Email →</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <tr>
      <td style="background:#111113;border-top:1px solid #1e1e22;padding:24px 40px;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td><span style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:900;letter-spacing:3px;color:#ffffff;text-transform:uppercase;">WELL<span style="color:#DC3C64;">CORE</span></span></td>
            <td align="right"><span style="font-family:'Courier New',Courier,monospace;font-size:9px;color:rgba(255,255,255,0.3);letter-spacing:1px;">wellcorefitness.com</span></td>
          </tr>
          <tr>
            <td colspan="2" style="padding-top:14px;border-top:1px solid #1e1e22;">
              <p style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:rgba(255,255,255,0.25);line-height:1.6;margin:0;">&copy; {$year} WellCore Fitness &middot; info@wellcorefitness.com &middot; @wellcore.fitness</p>
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
}


// ─────────────────────────────────────────────────────────────
// EMAIL DE INVITACIÓN — Prospectos (superadmin only)
// ─────────────────────────────────────────────────────────────

/**
 * Genera HTML para email de invitación a un prospecto.
 */
function email_invitation(string $toName, string $plan = 'rise', string $gender = 'male', string $customMsg = '', ?string $invitationCode = null): string {
    $year  = date('Y');
    $fn    = htmlspecialchars($toName ?: 'Amig@');
    $acent = ($gender === 'female') ? '#DC3C64' : '#E31E24';
    $msg   = $customMsg ? htmlspecialchars($customMsg) : '';

    $plans = [
        'rise'     => [
            'name'  => 'Reto RISE 30 Días',
            'tag'   => 'TRANSFORMACIÓN EN 30 DÍAS',
            'cop'   => '$99.900 COP',
            'usd'   => '~$27 USD',
            'link'  => 'https://wellcorefitness.com/rise-enroll.html',
            'cta'   => 'Comenzar el Reto RISE',
            'desc'  => 'Un reto de 30 días con plan de entrenamiento personalizado, tips de nutrición y acceso a la plataforma WellCore. Diseñado para personas que quieren resultados reales con método.',
            'items' => ['Plan de entrenamiento personalizado (4 semanas)', 'Tips de nutrición y hábitos saludables', 'Cardio adaptado a tus objetivos', 'Acceso a plataforma digital privada', 'Seguimiento y check-ins semanales'],
        ],
        'esencial' => [
            'name'  => 'Plan Esencial',
            'tag'   => 'EL PUNTO DE PARTIDA',
            'cop'   => '$299.000 COP',
            'usd'   => '~$72 USD',
            'link'  => 'https://wellcorefitness.com/inscripcion.html',
            'cta'   => 'Inscribirme al Plan Esencial',
            'desc'  => 'Asesoría de entrenamiento con estructura mensual. Ideal para quienes buscan comenzar con guía profesional y resultados consistentes.',
            'items' => ['Plan de entrenamiento mensual', 'Ajuste de plan cada mes', 'Acceso a plataforma digital', 'Soporte vía WhatsApp', 'Seguimiento de progreso'],
        ],
        'metodo'   => [
            'name'  => 'Plan Método',
            'tag'   => 'ENTRENAMIENTO + NUTRICIÓN',
            'cop'   => '$399.000 COP',
            'usd'   => '~$97 USD',
            'link'  => 'https://wellcorefitness.com/inscripcion.html',
            'cta'   => 'Inscribirme al Plan Método',
            'desc'  => 'La combinación completa: entrenamiento y asesoría nutricional personalizada. Para quienes quieren maximizar resultados con un enfoque integral.',
            'items' => ['Plan de entrenamiento mensual', 'Asesoría nutricional personalizada', 'Plan alimentario + ajustes', 'Acceso a plataforma digital', 'Soporte prioritario WhatsApp', 'Seguimiento semanal de progreso'],
        ],
        'elite'    => [
            'name'  => 'Plan Elite',
            'tag'   => 'EXPERIENCIA PREMIUM',
            'cop'   => '$549.000 COP',
            'usd'   => '~$134 USD',
            'link'  => 'https://wellcorefitness.com/inscripcion.html',
            'cta'   => 'Inscribirme al Plan Elite',
            'desc'  => 'Atención 1-a-1 de alta intensidad. Entrenamiento, nutrición y coaching personalizado para quienes exigen lo mejor de sí mismos.',
            'items' => ['Plan de entrenamiento hiperpersonalizado', 'Asesoría nutricional + plan detallado', 'Sesiones de revisión semanales', 'Acceso a plataforma digital', 'Respuesta en menos de 2h (horario laboral)', 'Seguimiento diario de adherencia'],
        ],
        'presencial' => [
            'name'  => 'Entrenamiento Presencial',
            'tag'   => 'INVITACIÓN EXCLUSIVA',
            'cop'   => 'Invitación',
            'usd'   => 'Sin costo',
            'link'  => 'https://wellcorefitness.com/presencial.html',
            'cta'   => 'Registrarme ahora',
            'desc'  => 'Has sido invitado al programa de entrenamiento presencial de WellCore Fitness. Accede a tu plataforma digital personalizada con plan de entrenamiento, seguimiento de progreso y todas las herramientas de la experiencia WellCore.',
            'items' => ['Plan de entrenamiento personalizado', 'Acceso a plataforma digital completa', 'Seguimiento de progreso y métricas', 'Registro de check-ins semanales', 'Herramientas de nutrición y hábitos'],
        ],
    ];

    $p    = $plans[$plan] ?? $plans['rise'];
    $name = htmlspecialchars($p['name']);
    $tag  = htmlspecialchars($p['tag']);
    $cop  = htmlspecialchars($p['cop']);
    $usd  = htmlspecialchars($p['usd']);
    $link = $p['link'];
    if ($plan === 'presencial' && $invitationCode) {
        $link = 'https://wellcorefitness.com/presencial.html?code=' . urlencode($invitationCode);
    }
    $link = htmlspecialchars($link);
    $cta  = htmlspecialchars($p['cta']);
    $desc = htmlspecialchars($p['desc']);

    $featureRows = '';
    foreach ($p['items'] as $item) {
        $i = htmlspecialchars($item);
        $featureRows .= "<tr>"
            . "<td width=\"20\" valign=\"top\" style=\"padding:5px 10px 5px 0;\">"
            . "<span style=\"font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{$acent}\">&#10003;</span>"
            . "</td>"
            . "<td style=\"padding:5px 0;\">"
            . "<span style=\"font-family:Arial,Helvetica,sans-serif;font-size:13px;color:rgba(255,255,255,0.8);line-height:1.5;\">{$i}</span>"
            . "</td></tr>\n";
    }

    $customBlock = '';
    if ($msg) {
        $customBlock = "<tr><td style=\"padding:0 40px 32px;\">"
            . "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"border-left:3px solid {$acent};background:#111113;\">"
            . "<tr><td style=\"padding:16px 20px;\">"
            . "<p style=\"font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:{$acent};text-transform:uppercase;margin:0 0 8px 0;\">// Mensaje de Daniel</p>"
            . "<p style=\"font-family:Arial,Helvetica,sans-serif;font-size:13px;color:rgba(255,255,255,0.75);line-height:1.7;margin:0;\">{$msg}</p>"
            . "</td></tr></table></td></tr>";
    }

    $hdr = "linear-gradient(135deg,{$acent} 0%,#7a0a10 100%)";

    $html = "<!DOCTYPE html><html lang=\"es\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><meta name=\"color-scheme\" content=\"dark\"><meta name=\"supported-color-schemes\" content=\"dark\"><title>{$name} — WellCore Fitness</title>"
        . "<style>:root{color-scheme:dark}body,table,td{background-color:#0a0a0a!important}</style></head>"
        . "<body style=\"margin:0;padding:0;background-color:#0a0a0a;background:#0a0a0a;\" bgcolor=\"#0a0a0a\">"
        . "<div style=\"display:none;max-height:0;overflow:hidden;color:#0a0a0a;\">Te invito — {$name} de WellCore Fitness. &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</div>"
        . "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" bgcolor=\"#0a0a0a\" style=\"background-color:#0a0a0a;\"><tr><td align=\"center\" style=\"padding:32px 16px;\" bgcolor=\"#0a0a0a\">"
        . "<table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"max-width:600px;width:100%;background-color:#0a0a0a;\" bgcolor=\"#0a0a0a\">"
        . "<tr><td style=\"background:{$hdr};padding:0;\">"
        . "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>"
        . "<td style=\"padding:24px 32px;\"><img src=\"https://wellcorefitness.com/images/logo/logo-blanco.png\" alt=\"WellCore Fitness\" width=\"140\" height=\"auto\" style=\"display:block;\"></td>"
        . "<td align=\"right\" style=\"padding:24px 32px;\"><span style=\"font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,0.8);text-transform:uppercase;\">{$tag}</span></td>"
        . "</tr></table></td></tr>"
        . "<tr><td bgcolor=\"#111113\" style=\"padding:48px 40px 36px;background-color:#111113;border-bottom:1px solid #1e1e22;\">"
        . "<p style=\"font-family:'Courier New',Courier,monospace;font-size:10px;font-weight:700;letter-spacing:3px;color:{$acent};text-transform:uppercase;margin:0 0 14px 0;\">// Una invitación para ti</p>"
        . "<h1 style=\"font-family:Arial,Helvetica,sans-serif;font-size:44px;font-weight:900;letter-spacing:2px;color:#ffffff;text-transform:uppercase;line-height:0.95;margin:0 0 20px 0;\">{$fn},<br><span style=\"color:{$acent};\">TE INVITO</span><br>A ESTO</h1>"
        . "<p style=\"font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#b0b0b8;line-height:1.7;margin:0;\">{$desc}</p></td></tr>"
        . "<tr><td bgcolor=\"#0a0a0a\" style=\"padding:36px 40px;background-color:#0a0a0a;border-bottom:1px solid #1e1e22;\">"
        . "<p style=\"font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:3px;color:{$acent};text-transform:uppercase;margin:0 0 6px 0;\">// Lo que obtienes</p>"
        . "<h2 style=\"font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:900;letter-spacing:1px;color:#ffffff;text-transform:uppercase;margin:0 0 20px 0;\">{$name}</h2>"
        . "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">{$featureRows}</table></td></tr>"
        . "<tr><td bgcolor=\"#111113\" style=\"padding:36px 40px;background-color:#111113;border-bottom:1px solid #1e1e22;\">"
        . "<p style=\"font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:3px;color:{$acent};text-transform:uppercase;margin:0 0 6px 0;\">// La plataforma</p>"
        . "<h2 style=\"font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:900;letter-spacing:1px;color:#ffffff;text-transform:uppercase;margin:0 0 16px 0;\">TODO EN UN SOLO LUGAR</h2>"
        . "<p style=\"font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#b0b0b8;line-height:1.7;margin:0 0 16px 0;\">Al inscribirte accedes a tu portal privado en <strong style=\"color:#ffffff;\">WellCore Fitness</strong>: tu plan completo, historial de progreso, check-ins semanales y comunicación directa con tu coach — todo desde cualquier dispositivo.</p>"
        . "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>"
        . "<td width=\"33%\" style=\"padding:0 5px 0 0;\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" bgcolor=\"#0a0a0a\" style=\"background-color:#0a0a0a;border-top:2px solid {$acent};\"><tr><td style=\"padding:14px;\" bgcolor=\"#0a0a0a\"><div style=\"font-family:'Courier New',Courier,monospace;font-size:8px;color:{$acent};letter-spacing:1px;text-transform:uppercase;margin-bottom:4px;\">Portal Privado</div><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#8b8b96;\">Tu plan y progreso siempre disponibles</div></td></tr></table></td>"
        . "<td width=\"33%\" style=\"padding:0 3px;\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" bgcolor=\"#0a0a0a\" style=\"background-color:#0a0a0a;border-top:2px solid {$acent};\"><tr><td style=\"padding:14px;\" bgcolor=\"#0a0a0a\"><div style=\"font-family:'Courier New',Courier,monospace;font-size:8px;color:{$acent};letter-spacing:1px;text-transform:uppercase;margin-bottom:4px;\">Check-ins</div><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#8b8b96;\">Reportes semanales con respuesta real</div></td></tr></table></td>"
        . "<td width=\"33%\" style=\"padding:0 0 0 5px;\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" bgcolor=\"#0a0a0a\" style=\"background-color:#0a0a0a;border-top:2px solid {$acent};\"><tr><td style=\"padding:14px;\" bgcolor=\"#0a0a0a\"><div style=\"font-family:'Courier New',Courier,monospace;font-size:8px;color:{$acent};letter-spacing:1px;text-transform:uppercase;margin-bottom:4px;\">Coach Directo</div><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#8b8b96;\">Sin intermediarios, respuesta rápida</div></td></tr></table></td>"
        . "</tr></table></td></tr>";

    // Sección de inversión — oculta para presencial (ya pagaron en persona)
    if ($plan !== 'presencial') {
        $html .= "<tr><td bgcolor=\"#0a0a0a\" style=\"padding:36px 40px;background-color:#0a0a0a;border-bottom:1px solid #1e1e22;\">"
            . "<p style=\"font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:3px;color:{$acent};text-transform:uppercase;margin:0 0 6px 0;\">// Inversión</p>"
            . "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" bgcolor=\"#111113\" style=\"background-color:#111113;border:1px solid #1e1e22;\"><tr><td style=\"padding:24px 28px;\" bgcolor=\"#111113\">"
            . "<div style=\"font-family:Arial,Helvetica,sans-serif;font-size:42px;font-weight:900;color:{$acent};letter-spacing:-1px;line-height:1;margin-bottom:6px;\">{$cop}</div>"
            . "<div style=\"font-family:'Courier New',Courier,monospace;font-size:11px;color:#8b8b96;letter-spacing:1px;\">Pago único · 30 días de acceso</div>"
            . "<div style=\"font-family:'Courier New',Courier,monospace;font-size:10px;color:#666;margin-top:6px;\">Pagos internacionales: {$usd}</div>"
            . "</td></tr></table></td></tr>";
    }

    $html .= $customBlock;

    // Sección de pasos — personalizada para presencial
    if ($plan === 'presencial') {
        $html .= "<tr><td bgcolor=\"#111113\" style=\"padding:36px 40px;background-color:#111113;border-bottom:1px solid #1e1e22;\">"
            . "<p style=\"font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:3px;color:{$acent};text-transform:uppercase;margin:0 0 6px 0;\">// Cómo activar tu cuenta</p>"
            . "<h2 style=\"font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:900;letter-spacing:1px;color:#ffffff;text-transform:uppercase;margin:0 0 20px 0;\">2 PASOS SIMPLES</h2>"
            . "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">"
            . "<tr><td style=\"padding:0 0 16px 0;\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>"
            . "<td width=\"40\" valign=\"top\"><div style=\"width:32px;height:32px;background-color:{$acent};font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:900;color:#fff;text-align:center;line-height:32px;\">01</div></td>"
            . "<td valign=\"top\" style=\"padding:6px 0 0 10px;\"><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;margin-bottom:3px;\">Haz clic en el botón de abajo</div><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#8b8b96;\">Te lleva directo al formulario de registro — sin pagos.</div></td></tr></table></td></tr>"
            . "<tr><td><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>"
            . "<td width=\"40\" valign=\"top\"><div style=\"width:32px;height:32px;background-color:{$acent};font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:900;color:#fff;text-align:center;line-height:32px;\">02</div></td>"
            . "<td valign=\"top\" style=\"padding:6px 0 0 10px;\"><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;margin-bottom:3px;\">Crea tu cuenta y listo</div><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#8b8b96;\">Pon tu nombre, email y una contraseña. Al confirmar entras directo a tu plataforma.</div></td></tr></table></td></tr>"
            . "</table></td></tr>";
    } else {
        $html .= "<tr><td bgcolor=\"#111113\" style=\"padding:36px 40px;background-color:#111113;border-bottom:1px solid #1e1e22;\">"
            . "<p style=\"font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:3px;color:{$acent};text-transform:uppercase;margin:0 0 6px 0;\">// Cómo inscribirte</p>"
            . "<h2 style=\"font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:900;letter-spacing:1px;color:#ffffff;text-transform:uppercase;margin:0 0 20px 0;\">3 PASOS SIMPLES</h2>"
            . "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">"
            . "<tr><td style=\"padding:0 0 16px 0;\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>"
            . "<td width=\"40\" valign=\"top\"><div style=\"width:32px;height:32px;background-color:{$acent};font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:900;color:#fff;text-align:center;line-height:32px;\">01</div></td>"
            . "<td valign=\"top\" style=\"padding:6px 0 0 10px;\"><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;margin-bottom:3px;\">Haz clic en el botón de abajo</div><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#8b8b96;\">Te lleva directo al formulario de inscripción.</div></td></tr></table></td></tr>"
            . "<tr><td style=\"padding:0 0 16px 0;\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>"
            . "<td width=\"40\" valign=\"top\"><div style=\"width:32px;height:32px;background-color:{$acent};font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:900;color:#fff;text-align:center;line-height:32px;\">02</div></td>"
            . "<td valign=\"top\" style=\"padding:6px 0 0 10px;\"><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;margin-bottom:3px;\">Completa tu perfil</div><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#8b8b96;\">Cuéntanos tu objetivo, nivel y disponibilidad — esto personaliza tu plan.</div></td></tr></table></td></tr>"
            . "<tr><td><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>"
            . "<td width=\"40\" valign=\"top\"><div style=\"width:32px;height:32px;background-color:{$acent};font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:900;color:#fff;text-align:center;line-height:32px;\">03</div></td>"
            . "<td valign=\"top\" style=\"padding:6px 0 0 10px;\"><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;margin-bottom:3px;\">Confirma tu pago y empieza</div><div style=\"font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#8b8b96;\">Pago seguro. En menos de 24h recibes acceso a tu plan y plataforma.</div></td></tr></table></td></tr>"
            . "</table></td></tr>";
    }

    $html .= "<tr><td align=\"center\" bgcolor=\"#0a0a0a\" style=\"padding:40px;background-color:#0a0a0a;\">"
        . "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td bgcolor=\"{$acent}\" style=\"background-color:{$acent};padding:0;border-radius:4px;\">"
        . "<a href=\"{$link}\" style=\"display:inline-block;font-family:'Courier New',Courier,monospace;font-size:13px;font-weight:700;letter-spacing:2px;color:#ffffff;text-decoration:none;padding:16px 40px;text-transform:uppercase;\">&#8594; {$cta}</a>"
        . "</td></tr></table>"
        . "<p style=\"font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#666;margin:16px 0 0 0;\">¿Tienes preguntas? Responde este correo o escríbeme por WhatsApp.</p>"
        . "</td></tr>"
        . "<tr><td bgcolor=\"#0a0a0a\" style=\"padding:24px 40px;background-color:#0a0a0a;border-top:1px solid #1e1e22;\">"
        . "<p style=\"font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#555;line-height:1.6;margin:0;\">&copy; {$year} WellCore Fitness &middot; Daniel Esparza, Coach &middot; info@wellcorefitness.com</p>"
        . "</td></tr>"
        . "</table></td></tr></table></body></html>";

    return $html;
}
