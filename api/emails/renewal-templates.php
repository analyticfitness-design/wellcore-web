<?php
/**
 * WellCore Fitness — Renewal Reminder Email Templates
 * ============================================================
 * Regular plans: Form A — satisfaction + renewal choice
 * RISE challenge: Form B — experience + continuation interest
 * ============================================================
 */

/**
 * Regular plan renewal email (Esencial/Metodo/Elite)
 */
function email_renewal_regular(string $name, string $plan, int $daysLeft, string $formUrl, string $dashboardUrl): string {
    $fn   = htmlspecialchars(explode(' ', trim($name))[0]);
    $url  = htmlspecialchars($dashboardUrl);
    $form = htmlspecialchars($formUrl);
    $year = date('Y');
    $planLabel = strtoupper(htmlspecialchars($plan));
    $daysText = $daysLeft === 1 ? '1 dia' : "{$daysLeft} dias";

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tu plan vence pronto — WellCore Fitness</title>
<style>
@media only screen and (max-width:600px){
  .email-body{padding:20px 16px!important;}
  .hero-title{font-size:28px!important;}
  .btn-cta{display:block!important;text-align:center!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;color:#0a0a0a;">Tu plan {$planLabel} vence en {$daysText}. Renueva para seguir progresando. &nbsp;&zwnj;&nbsp;&zwnj;</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0a0a0a;">
<tr><td align="center" style="padding:32px 16px;">

  <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#0a0a0a;border:1px solid #1e1e22;">

    <!-- HEADER -->
    <tr>
      <td style="background:#E31E24;padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="padding:20px 32px;">
            <span style="font-size:22px;font-weight:900;letter-spacing:4px;color:#ffffff;text-transform:uppercase;">WELLCORE</span>
            <span style="font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.75);margin-left:8px;text-transform:uppercase;">FITNESS</span>
          </td>
          <td align="right" style="padding:20px 32px;">
            <span style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,0.7);text-transform:uppercase;">RENOVACION &middot; {$planLabel}</span>
          </td>
        </tr></table>
      </td>
    </tr>

    <!-- BODY -->
    <tr>
      <td class="email-body" style="padding:40px 32px;">

        <!-- Hero -->
        <h1 class="hero-title" style="margin:0 0 8px;font-size:36px;font-weight:900;color:#ffffff;line-height:1.1;">
          Tu plan vence en {$daysText}
        </h1>
        <p style="margin:0 0 28px;font-size:14px;color:rgba(255,255,255,0.5);line-height:1.5;">
          {$fn}, queremos saber como te fue este mes y ayudarte a decidir tu siguiente paso.
        </p>

        <!-- Divider -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="height:1px;background:linear-gradient(90deg,#E31E24,#1e1e22);"></td>
        </tr></table>

        <!-- Message -->
        <div style="padding:24px 0;">
          <p style="font-size:14px;color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 16px;">
            Tu suscripcion al plan <strong style="color:#fff;">{$planLabel}</strong> esta por terminar.
            Antes de renovar, nos encantaria conocer tu experiencia:
          </p>

          <table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
            <tr><td style="padding:6px 0;font-size:13px;color:rgba(255,255,255,0.6);">&#10003; &nbsp;Como te sentiste con tu programa</td></tr>
            <tr><td style="padding:6px 0;font-size:13px;color:rgba(255,255,255,0.6);">&#10003; &nbsp;Que resultados lograste</td></tr>
            <tr><td style="padding:6px 0;font-size:13px;color:rgba(255,255,255,0.6);">&#10003; &nbsp;Si quieres renovar, subir de plan o pausar</td></tr>
            <tr><td style="padding:6px 0;font-size:13px;color:rgba(255,255,255,0.6);">&#10003; &nbsp;Tu check-in final del mes</td></tr>
          </table>

          <p style="font-size:13px;color:rgba(255,255,255,0.5);line-height:1.6;margin:0 0 24px;">
            Completar este formulario toma menos de 3 minutos y nos ayuda a mejorar tu experiencia.
          </p>
        </div>

        <!-- CTA -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td align="center" style="padding:8px 0 32px;">
            <a href="{$form}" class="btn-cta" style="display:inline-block;background:#E31E24;color:#ffffff;text-decoration:none;padding:14px 40px;font-size:14px;font-weight:700;letter-spacing:2px;text-transform:uppercase;border-radius:6px;">
              Completar Formulario
            </a>
          </td>
        </tr></table>

        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td align="center">
            <a href="{$url}" style="font-size:12px;color:#E31E24;text-decoration:none;">Ir a mi dashboard &rarr;</a>
          </td>
        </tr></table>

      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style="padding:20px 32px;border-top:1px solid #1e1e22;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="font-size:11px;color:rgba(255,255,255,0.25);line-height:1.6;">
            WellCore Fitness &copy; {$year}<br>
            <a href="https://wa.me/573001234567" style="color:rgba(255,255,255,0.35);text-decoration:none;">WhatsApp Soporte</a>
          </td>
          <td align="right" style="font-size:10px;color:rgba(255,255,255,0.2);">
            Renovacion &middot; {$planLabel}
          </td>
        </tr></table>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * RISE challenge renewal email
 */
function email_renewal_rise(string $name, int $daysLeft, string $formUrl, string $dashboardUrl): string {
    $fn   = htmlspecialchars(explode(' ', trim($name))[0]);
    $url  = htmlspecialchars($dashboardUrl);
    $form = htmlspecialchars($formUrl);
    $year = date('Y');
    $daysText = $daysLeft === 1 ? '1 dia' : "{$daysLeft} dias";

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tu reto RISE esta por terminar — WellCore Fitness</title>
<style>
@media only screen and (max-width:600px){
  .email-body{padding:20px 16px!important;}
  .hero-title{font-size:28px!important;}
  .btn-cta{display:block!important;text-align:center!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;color:#0a0a0a;">Tu reto RISE termina en {$daysText}. Cuentanos como te fue. &nbsp;&zwnj;&nbsp;&zwnj;</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0a0a0a;">
<tr><td align="center" style="padding:32px 16px;">

  <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#0a0a0a;border:1px solid #1e1e22;">

    <!-- HEADER -->
    <tr>
      <td style="background:#E31E24;padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="padding:20px 32px;">
            <span style="font-size:22px;font-weight:900;letter-spacing:4px;color:#ffffff;text-transform:uppercase;">WELLCORE</span>
            <span style="font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.75);margin-left:8px;text-transform:uppercase;">FITNESS</span>
          </td>
          <td align="right" style="padding:20px 32px;">
            <span style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,0.7);text-transform:uppercase;">RISE &middot; FINAL</span>
          </td>
        </tr></table>
      </td>
    </tr>

    <!-- BODY -->
    <tr>
      <td class="email-body" style="padding:40px 32px;">

        <h1 class="hero-title" style="margin:0 0 8px;font-size:36px;font-weight:900;color:#ffffff;line-height:1.1;">
          Tu reto RISE termina en {$daysText}
        </h1>
        <p style="margin:0 0 28px;font-size:14px;color:rgba(255,255,255,0.5);line-height:1.5;">
          {$fn}, felicidades por llegar hasta aqui. Queremos conocer tu experiencia.
        </p>

        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="height:1px;background:linear-gradient(90deg,#E31E24,#1e1e22);"></td>
        </tr></table>

        <div style="padding:24px 0;">
          <p style="font-size:14px;color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 16px;">
            Completaste (o estas por completar) los <strong style="color:#fff;">30 dias del Reto RISE</strong>.
            Tu opinion nos importa mucho:
          </p>

          <table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px;">
            <tr><td style="padding:6px 0;font-size:13px;color:rgba(255,255,255,0.6);">&#9733; &nbsp;Como fue tu experiencia RISE</td></tr>
            <tr><td style="padding:6px 0;font-size:13px;color:rgba(255,255,255,0.6);">&#9733; &nbsp;Que resultados obtuviste</td></tr>
            <tr><td style="padding:6px 0;font-size:13px;color:rgba(255,255,255,0.6);">&#9733; &nbsp;Si te gustaria continuar con un plan personalizado</td></tr>
            <tr><td style="padding:6px 0;font-size:13px;color:rgba(255,255,255,0.6);">&#9733; &nbsp;Tus medidas finales</td></tr>
          </table>

          <!-- Upgrade teaser -->
          <div style="background:rgba(227,30,36,0.08);border:1px solid rgba(227,30,36,0.2);border-radius:8px;padding:16px;margin-bottom:24px;">
            <p style="margin:0 0 8px;font-size:13px;font-weight:700;color:#E31E24;text-transform:uppercase;letter-spacing:1px;">Siguiente nivel</p>
            <p style="margin:0;font-size:13px;color:rgba(255,255,255,0.65);line-height:1.6;">
              Despues de RISE, puedes unirte a un <strong style="color:#fff;">plan personalizado</strong> (Esencial, Metodo o Elite)
              con una interfaz diferente que llevara tu proceso a otro nivel.
            </p>
          </div>
        </div>

        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td align="center" style="padding:8px 0 20px;">
            <a href="{$form}" class="btn-cta" style="display:inline-block;background:#E31E24;color:#ffffff;text-decoration:none;padding:14px 40px;font-size:14px;font-weight:700;letter-spacing:2px;text-transform:uppercase;border-radius:6px;">
              Completar Formulario RISE
            </a>
          </td>
        </tr></table>

        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td align="center" style="padding:0 0 8px;">
            <a href="{$url}" style="font-size:12px;color:#E31E24;text-decoration:none;">Ver mi dashboard RISE &rarr;</a>
          </td>
        </tr></table>

        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td align="center">
            <a href="https://wa.me/573001234567?text=Hola%2C%20termine%20RISE%20y%20quiero%20info%20sobre%20planes" style="font-size:12px;color:rgba(255,255,255,0.4);text-decoration:none;">Preguntas? Escribenos por WhatsApp &rarr;</a>
          </td>
        </tr></table>

      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style="padding:20px 32px;border-top:1px solid #1e1e22;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="font-size:11px;color:rgba(255,255,255,0.25);line-height:1.6;">
            WellCore Fitness &copy; {$year}<br>
            <a href="https://wa.me/573001234567" style="color:rgba(255,255,255,0.35);text-decoration:none;">WhatsApp Soporte</a>
          </td>
          <td align="right" style="font-size:10px;color:rgba(255,255,255,0.2);">
            RISE Challenge &middot; Final
          </td>
        </tr></table>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>
HTML;
}
