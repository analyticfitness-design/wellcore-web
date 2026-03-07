<?php
/**
 * WellCore Fitness — Engagement Email Templates
 * ============================================================
 * Day 10: "Tu Progreso Importa" — motivational + tips
 * Day 20: "Sigue Asi, Estamos Contigo" — value-add + reminder
 * ============================================================
 */

/**
 * Day 10 email — "Tu Progreso Importa"
 */
function email_engagement_day10(string $name, string $plan, string $dashboardUrl = 'https://wellcorefitness.com/cliente.html'): string {
    $fn   = htmlspecialchars(explode(' ', trim($name))[0]);
    $url  = htmlspecialchars($dashboardUrl);
    $year = date('Y');
    $planLabel = strtoupper(htmlspecialchars($plan));

    $tipsByPlan = [
        'esencial' => [
            'Registra tu entrenamiento en el dashboard para mantener tu racha activa.',
            'Revisa tu plan de entrenamiento y ajusta los pesos si ya te sientes comodo.',
            'Toma tus medidas corporales esta semana para tener un punto de referencia.',
        ],
        'metodo' => [
            'Aprovecha tu plan de nutricion — sigue las porciones sugeridas esta semana.',
            'Sube una foto de progreso para que tu coach vea como vas.',
            'Usa el analisis de comidas IA para verificar que estas en buen camino.',
        ],
        'elite' => [
            'Envia tu check-in semanal con fotos y medidas para que tu coach ajuste el plan.',
            'Revisa la seccion de habitos — pequenos cambios generan grandes resultados.',
            'Agenda una consulta con tu coach si tienes dudas sobre tu programa.',
        ],
        'rise' => [
            'Registra tus medidas en el dashboard para ver tu progreso del reto.',
            'Sigue tu plan de entrenamiento al pie de la letra — cada dia cuenta.',
            'Revisa las instrucciones de tu coach en el dashboard RISE.',
        ],
    ];

    $tips = $tipsByPlan[$plan] ?? $tipsByPlan['esencial'];
    $tipsHtml = '';
    foreach ($tips as $i => $tip) {
        $num = str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
        $tipEsc = htmlspecialchars($tip);
        $tipsHtml .= <<<TIP
        <tr>
          <td style="padding:12px 0;border-bottom:1px solid #1e1e22;">
            <table cellpadding="0" cellspacing="0" border="0"><tr>
              <td style="vertical-align:top;padding-right:14px;">
                <span style="font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:900;color:#E31E24;line-height:1;">{$num}</span>
              </td>
              <td style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:rgba(255,255,255,0.7);line-height:1.6;">
                {$tipEsc}
              </td>
            </tr></table>
          </td>
        </tr>
TIP;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tu Progreso Importa — WellCore Fitness</title>
<style>
@media only screen and (max-width:600px){
  .email-body{padding:20px 16px!important;}
  .hero-title{font-size:32px!important;}
  .btn-cta{display:block!important;text-align:center!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;color:#0a0a0a;">Llevas 10 dias y queremos que sepas que tu progreso importa. &nbsp;&zwnj;&nbsp;&zwnj;</div>

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
            <span style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,0.7);text-transform:uppercase;">DIA 10 &middot; {$planLabel}</span>
          </td>
        </tr></table>
      </td>
    </tr>

    <!-- HERO -->
    <tr>
      <td class="email-body" style="padding:48px 40px 36px;background:#111113;border-bottom:1px solid #1e1e22;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:10px;font-weight:700;letter-spacing:3px;color:#E31E24;text-transform:uppercase;margin:0 0 14px 0;">// Tu progreso importa</p>
        <h1 class="hero-title" style="font-size:42px;font-weight:900;letter-spacing:2px;color:#ffffff;text-transform:uppercase;line-height:0.95;margin:0 0 20px 0;">
          10 DIAS<br><span style="color:#E31E24;">Y CONTANDO</span>
        </h1>
        <p style="font-size:16px;color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 28px 0;">
          {$fn}, llevas 10 dias en tu programa y eso ya es un logro. La consistencia que estas construyendo es la base de resultados reales. Nuestro equipo esta disponible para cualquier apoyo que necesites.
        </p>
        <table cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="background:#E31E24;padding:0;">
            <a href="{$url}" class="btn-cta" style="display:inline-block;font-family:'Courier New',Courier,monospace;font-size:12px;font-weight:700;letter-spacing:2px;color:#ffffff;text-decoration:none;padding:14px 32px;text-transform:uppercase;background:#E31E24;">
              &rarr; Accede a tu Dashboard
            </a>
          </td>
        </tr></table>
      </td>
    </tr>

    <!-- TIPS -->
    <tr>
      <td style="padding:36px 40px;background:#0a0a0a;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:3px;color:#E31E24;text-transform:uppercase;margin:0 0 6px 0;">// Acciones recomendadas</p>
        <h2 style="font-size:22px;font-weight:900;letter-spacing:1px;color:#ffffff;text-transform:uppercase;margin:0 0 20px 0;">ESTA SEMANA</h2>
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          {$tipsHtml}
        </table>
      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style="padding:28px 40px;background:#111113;border-top:1px solid #1e1e22;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:9px;letter-spacing:2px;color:rgba(255,255,255,0.3);text-transform:uppercase;margin:0 0 4px 0;">WELLCORE FITNESS &copy; {$year}</p>
        <p style="font-size:11px;color:rgba(255,255,255,0.25);margin:0;">Si necesitas ayuda, responde a este correo o escribenos por WhatsApp.</p>
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
 * Day 20 email — "Sigue Asi, Estamos Contigo"
 */
function email_engagement_day20(string $name, string $plan, string $dashboardUrl = 'https://wellcorefitness.com/cliente.html'): string {
    $fn   = htmlspecialchars(explode(' ', trim($name))[0]);
    $url  = htmlspecialchars($dashboardUrl);
    $year = date('Y');
    $planLabel = strtoupper(htmlspecialchars($plan));
    $whatsapp = 'https://wa.me/573001234567';

    $tipsByPlan = [
        'esencial' => [
            'Revisa tu evolucion de peso en la seccion Seguimiento — los datos cuentan la historia.',
            'Incrementa ligeramente la intensidad esta semana si te sientes listo.',
            'Prepara tu check-in final del mes con tus medidas actualizadas.',
        ],
        'metodo' => [
            'Tu plan de nutricion tiene ajustes para esta fase — revisalo en el dashboard.',
            'Sube fotos de progreso para comparar con el inicio del mes.',
            'El analisis IA de comidas te ayuda a mantener la consistencia nutricional.',
        ],
        'elite' => [
            'Comunicate con tu coach para revisar los ajustes de la recta final del mes.',
            'Completa tu check-in semanal — tu coach necesita los datos para planificar el proximo ciclo.',
            'Revisa la seccion de habitos y ajusta lo que no ha funcionado.',
        ],
        'rise' => [
            'Quedan 10 dias del reto — ahora es cuando se ven los resultados.',
            'Toma tus medidas y comparalas con el dia 1 en tu dashboard RISE.',
            'Mantente firme con el plan — la recta final es donde se marcan diferencias.',
        ],
    ];

    $tips = $tipsByPlan[$plan] ?? $tipsByPlan['esencial'];
    $tipsHtml = '';
    foreach ($tips as $i => $tip) {
        $num = str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
        $tipEsc = htmlspecialchars($tip);
        $tipsHtml .= <<<TIP
        <tr>
          <td style="padding:12px 0;border-bottom:1px solid #1e1e22;">
            <table cellpadding="0" cellspacing="0" border="0"><tr>
              <td style="vertical-align:top;padding-right:14px;">
                <span style="font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:900;color:#E31E24;line-height:1;">{$num}</span>
              </td>
              <td style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:rgba(255,255,255,0.7);line-height:1.6;">
                {$tipEsc}
              </td>
            </tr></table>
          </td>
        </tr>
TIP;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Sigue Asi — WellCore Fitness</title>
<style>
@media only screen and (max-width:600px){
  .email-body{padding:20px 16px!important;}
  .hero-title{font-size:32px!important;}
  .btn-cta{display:block!important;text-align:center!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;color:#0a0a0a;">Dia 20 — la recta final. Estamos contigo. &nbsp;&zwnj;&nbsp;&zwnj;</div>

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
            <span style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,0.7);text-transform:uppercase;">DIA 20 &middot; {$planLabel}</span>
          </td>
        </tr></table>
      </td>
    </tr>

    <!-- HERO -->
    <tr>
      <td class="email-body" style="padding:48px 40px 36px;background:#111113;border-bottom:1px solid #1e1e22;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:10px;font-weight:700;letter-spacing:3px;color:#E31E24;text-transform:uppercase;margin:0 0 14px 0;">// La recta final</p>
        <h1 class="hero-title" style="font-size:42px;font-weight:900;letter-spacing:2px;color:#ffffff;text-transform:uppercase;line-height:0.95;margin:0 0 20px 0;">
          SIGUE ASI,<br><span style="color:#E31E24;">{$fn}</span>
        </h1>
        <p style="font-size:16px;color:rgba(255,255,255,0.7);line-height:1.7;margin:0 0 28px 0;">
          Llevas 20 dias de consistencia y eso habla de tu compromiso. La recta final es donde se consolidan los resultados. Tu equipo WellCore esta contigo hasta el final.
        </p>

        <!-- Dual CTA -->
        <table cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="background:#E31E24;padding:0;">
            <a href="{$url}" class="btn-cta" style="display:inline-block;font-family:'Courier New',Courier,monospace;font-size:12px;font-weight:700;letter-spacing:2px;color:#ffffff;text-decoration:none;padding:14px 32px;text-transform:uppercase;background:#E31E24;">
              &rarr; Accede a tu Dashboard
            </a>
          </td>
          <td style="padding:0 0 0 12px;">
            <a href="{$whatsapp}" style="display:inline-block;font-family:'Courier New',Courier,monospace;font-size:12px;font-weight:700;letter-spacing:2px;color:#E31E24;text-decoration:none;padding:12px 24px;text-transform:uppercase;border:2px solid #E31E24;">
              WHATSAPP
            </a>
          </td>
        </tr></table>
      </td>
    </tr>

    <!-- TIPS -->
    <tr>
      <td style="padding:36px 40px;background:#0a0a0a;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:3px;color:#E31E24;text-transform:uppercase;margin:0 0 6px 0;">// Para la recta final</p>
        <h2 style="font-size:22px;font-weight:900;letter-spacing:1px;color:#ffffff;text-transform:uppercase;margin:0 0 20px 0;">RECOMENDACIONES</h2>
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          {$tipsHtml}
        </table>
      </td>
    </tr>

    <!-- MOTIVATIONAL QUOTE -->
    <tr>
      <td style="padding:32px 40px;background:#111113;border-top:1px solid #1e1e22;border-bottom:1px solid #1e1e22;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="border-left:3px solid #E31E24;padding-left:20px;">
            <p style="font-size:15px;font-style:italic;color:rgba(255,255,255,0.6);line-height:1.7;margin:0;">
              "El exito no es definitivo, el fracaso no es fatal: lo que cuenta es el coraje de continuar."
            </p>
            <p style="font-family:'Courier New',Courier,monospace;font-size:9px;letter-spacing:2px;color:#E31E24;text-transform:uppercase;margin:8px 0 0 0;">— Winston Churchill</p>
          </td>
        </tr></table>
      </td>
    </tr>

    <!-- FOOTER -->
    <tr>
      <td style="padding:28px 40px;background:#0a0a0a;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:9px;letter-spacing:2px;color:rgba(255,255,255,0.3);text-transform:uppercase;margin:0 0 4px 0;">WELLCORE FITNESS &copy; {$year}</p>
        <p style="font-size:11px;color:rgba(255,255,255,0.25);margin:0;">Si necesitas ayuda, responde a este correo o escribenos por WhatsApp.</p>
      </td>
    </tr>

  </table>

</td></tr>
</table>
</body>
</html>
HTML;
}
