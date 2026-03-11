<?php
/**
 * WellCore Fitness — Behavioral Email Templates
 * ============================================================
 * 6 trigger-based email templates:
 *   1. email_inactive_7d     — 7 days without check-in
 *   2. email_inactive_14d    — 14 days without check-in
 *   3. email_renewal_reminder — subscription expiring in N days
 *   4. email_streak_milestone — 4 or 7 total check-ins completed (milestone)
 *   5. email_birthday         — client birthday
 *   6. email_welcome_day1     — day 1 after joining
 * ============================================================
 */

// ─── Shared helpers ──────────────────────────────────────────

function _bt_header(string $planLabel, string $tag): string {
    $planEsc = htmlspecialchars($planLabel);
    $tagEsc  = htmlspecialchars($tag);
    return <<<HTML
    <!-- HEADER -->
    <tr>
      <td style="background:#E31E24;padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="padding:20px 32px;">
            <span style="font-size:22px;font-weight:900;letter-spacing:4px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;text-transform:uppercase;">WELLCORE</span>
            <span style="font-size:11px;letter-spacing:2px;color:rgba(255,255,255,0.75);margin-left:8px;font-family:Arial,Helvetica,sans-serif;text-transform:uppercase;">FITNESS</span>
          </td>
          <td align="right" style="padding:20px 32px;">
            <span style="font-family:'Courier New',Courier,monospace;font-size:9px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,0.7);text-transform:uppercase;">{$tagEsc} &middot; {$planEsc}</span>
          </td>
        </tr></table>
      </td>
    </tr>
HTML;
}

function _bt_footer(): string {
    $year = date('Y');
    return <<<HTML
    <!-- FOOTER -->
    <tr>
      <td style="padding:28px 40px;background:#111113;border-top:1px solid #1e1e22;">
        <p style="font-family:'Courier New',Courier,monospace;font-size:9px;letter-spacing:2px;color:rgba(255,255,255,0.3);text-transform:uppercase;margin:0 0 4px 0;">WELLCORE FITNESS &copy; {$year}</p>
        <p style="font-size:11px;color:rgba(255,255,255,0.25);font-family:Arial,Helvetica,sans-serif;margin:0;">Si necesitas ayuda, responde a este correo o escr&iacute;benos por WhatsApp.</p>
      </td>
    </tr>
HTML;
}

function _bt_cta(string $url, string $label): string {
    $urlEsc   = htmlspecialchars($url);
    $labelEsc = htmlspecialchars($label);
    return <<<HTML
        <table cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="background:#E31E24;border-radius:8px;">
            <a href="{$urlEsc}" style="display:inline-block;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:8px;background:#E31E24;">
              {$labelEsc}
            </a>
          </td>
        </tr></table>
HTML;
}

function _bt_wrap(string $title, string $preheader, string $body): string {
    $titleEsc     = htmlspecialchars($title);
    $preheaderEsc = htmlspecialchars($preheader);
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{$titleEsc}</title>
<style>
@media only screen and (max-width:600px){
  .email-body{padding:20px 16px!important;}
  .hero-title{font-size:28px!important;}
  .btn-cta{display:block!important;text-align:center!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#0a0a0f;font-family:Arial,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;color:#0a0a0f;">{$preheaderEsc} &nbsp;&zwnj;&nbsp;&zwnj;</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0a0a0f;">
<tr><td align="center" style="padding:32px 16px;">

  <table width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;background:#0a0a0f;border:1px solid #1e1e22;">

    {$body}

  </table>

</td></tr>
</table>
</body>
</html>
HTML;
}

// ─── Function 1: inactive_7d ─────────────────────────────────

/**
 * Email for clients with no check-in in the last 7 days.
 */
function email_inactive_7d(string $name, string $plan, string $dashUrl): string {
    $fn   = htmlspecialchars(explode(' ', trim($name))[0]);
    $plan = htmlspecialchars($plan);

    $header = _bt_header(strtoupper($plan), 'CHECK-IN');
    $cta    = _bt_cta($dashUrl, 'Hacer mi check-in →');
    $footer = _bt_footer();

    $body = <<<HTML
    {$header}

    <!-- HERO -->
    <tr>
      <td class="email-body" style="padding:48px 40px 36px;background:#111113;border-bottom:1px solid #1e1e22;">
        <p style="font-size:56px;margin:0 0 16px 0;line-height:1;">&#x1F4AC;</p>
        <h1 class="hero-title" style="font-size:36px;font-weight:900;color:#E31E24;font-family:Arial,Helvetica,sans-serif;margin:0 0 20px 0;">
          &iquest;Todo bien, {$fn}?
        </h1>
        <p style="font-size:15px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 16px 0;">
          Han pasado 7 d&iacute;as desde tu &uacute;ltimo check-in. Tu coach est&aacute; pendiente de ti y queremos saber c&oacute;mo vas.
        </p>
        <p style="font-size:15px;color:#aaaaaa;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 28px 0;">
          5 minutos de check-in pueden hacer la diferencia en tu progreso. &iquest;Lo hacemos ahora?
        </p>
        {$cta}
      </td>
    </tr>

    {$footer}
HTML;

    return _bt_wrap('¿Todo bien? — WellCore Fitness', 'Han pasado 7 días desde tu último check-in. Tu coach está pendiente.', $body);
}

// ─── Function 2: inactive_14d ────────────────────────────────

/**
 * Email for clients with no check-in in the last 14 days.
 */
function email_inactive_14d(string $name, string $plan, string $dashUrl): string {
    $fn   = htmlspecialchars(explode(' ', trim($name))[0]);
    $plan = htmlspecialchars($plan);

    $header = _bt_header(strtoupper($plan), 'SEGUIMIENTO');
    $cta    = _bt_cta($dashUrl, 'Volver al programa →');
    $footer = _bt_footer();

    $body = <<<HTML
    {$header}

    <!-- HERO -->
    <tr>
      <td class="email-body" style="padding:48px 40px 36px;background:#111113;border-bottom:1px solid #1e1e22;">
        <p style="font-size:56px;margin:0 0 16px 0;line-height:1;">&#x26A0;&#xFE0F;</p>
        <h1 class="hero-title" style="font-size:32px;font-weight:900;color:#F59E0B;font-family:Arial,Helvetica,sans-serif;margin:0 0 20px 0;">
          Llevamos 14 d&iacute;as sin saber de ti, {$fn}
        </h1>
        <p style="font-size:15px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 16px 0;">
          Tu coach tiene un plan esperando por ti. No dejes que el silencio borre todo el trabajo que ya construiste.
        </p>
        <p style="font-size:15px;color:#aaaaaa;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 28px 0;">
          Si algo te est&aacute; frenando, cu&eacute;ntanos. Estamos aqu&iacute; para ayudarte.
        </p>
        {$cta}
      </td>
    </tr>

    {$footer}
HTML;

    return _bt_wrap("Llevamos 14 días sin saber de ti — WellCore Fitness", '14 días sin check-in. Tu coach tiene un plan esperando.', $body);
}

// ─── Function 3: renewal_reminder ────────────────────────────

/**
 * Email reminding client their subscription expires in $days days.
 */
function email_renewal_reminder(string $name, string $plan, string $endDate, string $dashUrl, int $days): string {
    $fn            = htmlspecialchars(explode(' ', trim($name))[0]);
    $planUpper     = strtoupper($plan);
    $planEsc       = htmlspecialchars($plan);
    $dateFormatted = date('d M Y', strtotime($endDate));
    $emoji         = ($days <= 3) ? '&#x23F0;' : '&#x1F4C5;';
    $emojiText     = ($days <= 3) ? '⏰' : '📅';

    $header = _bt_header($planUpper, 'RENOVACION');
    $cta    = _bt_cta($dashUrl, 'Renovar mi plan →');
    $footer = _bt_footer();

    $body = <<<HTML
    {$header}

    <!-- HERO -->
    <tr>
      <td class="email-body" style="padding:48px 40px 36px;background:#111113;border-bottom:1px solid #1e1e22;">
        <p style="font-size:56px;margin:0 0 16px 0;line-height:1;">{$emoji}</p>
        <h1 class="hero-title" style="font-size:32px;font-weight:900;color:#F59E0B;font-family:Arial,Helvetica,sans-serif;margin:0 0 20px 0;">
          Tu plan vence en {$days} d&iacute;as, {$fn}
        </h1>
        <p style="font-size:15px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 28px 0;">
          Tu plan <strong>{$planUpper}</strong> vence el <strong>{$dateFormatted}</strong>. Renueva hoy para no perder tu historial, tus PRs y la conexi&oacute;n con tu coach.
        </p>
        {$cta}
      </td>
    </tr>

    {$footer}
HTML;

    return _bt_wrap("Tu plan vence en {$days} días — WellCore Fitness", "Tu plan {$planUpper} vence el {$dateFormatted}. Renueva hoy.", $body);
}

// ─── Function 4: streak_milestone ────────────────────────────

/**
 * Email celebrating a check-in milestone (4 or 7 total check-ins completed).
 */
function email_streak_milestone(string $name, string $plan, int $weeks, string $dashUrl): string {
    $fn   = htmlspecialchars(explode(' ', trim($name))[0]);
    $plan = htmlspecialchars($plan);

    if ($weeks >= 7) {
        $message = '7 check-ins completados. Muy pocas personas llegan aqu&iacute;.';
        $tag     = '7 CHECK-INS';
    } else {
        $message = '4 check-ins completados. Est&aacute;s construyendo h&aacute;bitos reales.';
        $tag     = '4 CHECK-INS';
    }

    $header = _bt_header(strtoupper($plan), $tag);
    $cta    = _bt_cta($dashUrl, 'Ver mi progreso →');
    $footer = _bt_footer();

    $body = <<<HTML
    {$header}

    <!-- HERO -->
    <tr>
      <td class="email-body" style="padding:48px 40px 36px;background:#111113;border-bottom:1px solid #1e1e22;">
        <p style="font-size:56px;margin:0 0 16px 0;line-height:1;">&#x1F525;</p>
        <h1 class="hero-title" style="font-size:36px;font-weight:900;color:#E31E24;font-family:Arial,Helvetica,sans-serif;margin:0 0 20px 0;">
          {$fn}, eso es disciplina
        </h1>
        <p style="font-size:15px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 28px 0;">
          {$message}
        </p>
        {$cta}
      </td>
    </tr>

    {$footer}
HTML;

    return _bt_wrap("{$fn}, eso es disciplina — WellCore Fitness", "{$weeks} check-ins completados. Sigue así.", $body);
}

// ─── Function 5: birthday ────────────────────────────────────

/**
 * Email sent on client's birthday.
 */
function email_birthday(string $name, string $plan, string $dashUrl): string {
    $fn   = htmlspecialchars(explode(' ', trim($name))[0]);
    $plan = htmlspecialchars($plan);

    $header = _bt_header(strtoupper($plan), 'CUMPLEANOS');
    $cta    = _bt_cta($dashUrl, 'Ver mi portal →');
    $footer = _bt_footer();

    $body = <<<HTML
    {$header}

    <!-- HERO -->
    <tr>
      <td class="email-body" style="padding:48px 40px 36px;background:#111113;border-bottom:1px solid #1e1e22;">
        <p style="font-size:56px;margin:0 0 16px 0;line-height:1;">&#x1F382;</p>
        <h1 class="hero-title" style="font-size:36px;font-weight:900;color:#E31E24;font-family:Arial,Helvetica,sans-serif;margin:0 0 20px 0;">
          &iexcl;Feliz cumplea&ntilde;os, {$fn}!
        </h1>
        <p style="font-size:15px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 28px 0;">
          Todo el equipo WellCore te desea un a&ntilde;o lleno de PRs, progreso y resultados reales. Hoy es tu d&iacute;a &mdash; &iexcl;c&eacute;libralo con fuerza!
        </p>
        {$cta}
      </td>
    </tr>

    {$footer}
HTML;

    return _bt_wrap("¡Feliz cumpleaños, {$fn}! — WellCore Fitness", "Todo el equipo te desea un año lleno de PRs y resultados.", $body);
}

// ─── Function 6: welcome_day1 ────────────────────────────────

/**
 * Welcome email sent on the day after a client joins.
 */
function email_welcome_day1(string $name, string $plan, string $dashUrl): string {
    $fn        = htmlspecialchars(explode(' ', trim($name))[0]);
    $planUpper = strtoupper($plan);
    $planEsc   = htmlspecialchars($plan);

    $header = _bt_header($planUpper, 'BIENVENIDO');
    $cta    = _bt_cta($dashUrl, 'Entrar a mi portal →');
    $footer = _bt_footer();

    $body = <<<HTML
    {$header}

    <!-- HERO -->
    <tr>
      <td class="email-body" style="padding:48px 40px 36px;background:#111113;border-bottom:1px solid #1e1e22;">
        <p style="font-size:56px;margin:0 0 16px 0;line-height:1;">&#x1F680;</p>
        <h1 class="hero-title" style="font-size:36px;font-weight:900;color:#E31E24;font-family:Arial,Helvetica,sans-serif;margin:0 0 20px 0;">
          Bienvenido a WellCore, {$fn}
        </h1>
        <p style="font-size:15px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 16px 0;">
          Tu plan <strong>{$planUpper}</strong> est&aacute; activo y tu coach ya tiene tu programa listo. Este es el primer d&iacute;a del resto de tu transformaci&oacute;n.
        </p>
        <p style="font-size:15px;color:#aaaaaa;font-family:Arial,Helvetica,sans-serif;line-height:1.7;margin:0 0 28px 0;">
          <strong style="color:#ffffff;">Siguiente paso:</strong> Entra a tu portal y revisa tu plan de entrenamiento.
        </p>
        {$cta}
      </td>
    </tr>

    {$footer}
HTML;

    return _bt_wrap("Bienvenido a WellCore, {$fn} — WellCore Fitness", "Tu plan {$planUpper} está activo. Tu coach ya tiene tu programa listo.", $body);
}

// ─── Function 7: first_pr ─────────────────────────────────────

function email_first_pr(string $name, string $plan, string $dashUrl): string {
    $planLabel = strtoupper($plan);
    $fn = htmlspecialchars(explode(' ', trim($name))[0]);
    $header = _bt_header($planLabel, 'PR REGISTRADO');
    $footer = _bt_footer();
    $cta    = _bt_cta($dashUrl, 'Ver mis PRs');
    return <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#0a0a0a">
  <tr><td align="center" style="padding:32px 16px">
    <table width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#18181b" style="border-radius:12px;overflow:hidden;border:1px solid #2d2d2d">
      {$header}
      <tr><td style="padding:36px 40px">
        <h2 style="color:#E31E24;font-size:1.1rem;letter-spacing:.1em;text-transform:uppercase;margin:0 0 8px">🏆 ¡PRIMER RÉCORD PERSONAL!</h2>
        <p style="color:#ffffff;font-size:1.15rem;font-weight:700;margin:0 0 16px">Esto es lo que significa el progreso real, {$fn}.</p>
        <p style="color:rgba(255,255,255,.7);font-size:.95rem;line-height:1.7;margin:0 0 24px">Registraste tu primer PR en WellCore. Cada número que superas es evidencia de que el trabajo está funcionando. Sigue registrando — en 3 meses, tu yo del futuro te lo agradecerá.</p>
        {$cta}
      </td></tr>
      {$footer}
    </table>
  </td></tr>
</table>
HTML;
}

// ─── Function 8: low_bienestar_coach ─────────────────────────

function email_low_bienestar_coach(string $clientName, string $plan, int $bienestar, string $checkinDate): string {
    $planLabel = strtoupper($plan);
    $clientNameEsc = htmlspecialchars($clientName);
    $header = _bt_header($planLabel, 'ALERTA BIENESTAR');
    $footer = _bt_footer();
    return <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#0a0a0a">
  <tr><td align="center" style="padding:32px 16px">
    <table width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#18181b" style="border-radius:12px;overflow:hidden;border:1px solid #2d2d2d">
      {$header}
      <tr><td style="padding:36px 40px">
        <h2 style="color:#f59e0b;font-size:1.1rem;letter-spacing:.1em;text-transform:uppercase;margin:0 0 8px">⚠️ ALERTA: BIENESTAR BAJO</h2>
        <p style="color:#ffffff;font-size:1.1rem;font-weight:700;margin:0 0 16px">Tu cliente <strong>{$clientNameEsc}</strong> reportó bienestar bajo.</p>
        <div style="background:#111113;border:1px solid #2d2d2d;border-radius:8px;padding:16px;margin-bottom:24px">
          <div style="color:rgba(255,255,255,.5);font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px">Detalles</div>
          <div style="color:#fff;font-size:1rem">Bienestar: <strong style="color:#ef4444">{$bienestar}/10</strong></div>
          <div style="color:rgba(255,255,255,.6);font-size:.85rem;margin-top:4px">Check-in del: {$checkinDate}</div>
        </div>
        <p style="color:rgba(255,255,255,.7);font-size:.9rem;line-height:1.7;margin:0">Considera revisar su plan o enviarle un mensaje de apoyo esta semana.</p>
      </td></tr>
      {$footer}
    </table>
  </td></tr>
</table>
HTML;
}

// ─── Function 9: inactive_30d ─────────────────────────────────

function email_inactive_30d(string $name, string $plan, string $dashUrl): string {
    $planLabel = strtoupper($plan);
    $fn = htmlspecialchars(explode(' ', trim($name))[0]);
    $header = _bt_header($planLabel, 'REACTIVACIÓN');
    $footer = _bt_footer();
    $cta    = _bt_cta($dashUrl, 'Retomar mi plan ahora');
    return <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#0a0a0a">
  <tr><td align="center" style="padding:32px 16px">
    <table width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#18181b" style="border-radius:12px;overflow:hidden;border:1px solid #2d2d2d">
      {$header}
      <tr><td style="padding:36px 40px">
        <h2 style="color:#E31E24;font-size:1.1rem;letter-spacing:.1em;text-transform:uppercase;margin:0 0 8px">30 DÍAS DESPUÉS</h2>
        <p style="color:#ffffff;font-size:1.15rem;font-weight:700;margin:0 0 16px">{$fn}, llevamos un mes sin saber de ti.</p>
        <p style="color:rgba(255,255,255,.7);font-size:.95rem;line-height:1.7;margin:0 0 24px">La vida pasa. Los compromisos cambian. Pero tu progreso sigue ahí, esperándote. No necesitas empezar desde cero — solo un paso hoy.</p>
        {$cta}
      </td></tr>
      {$footer}
    </table>
  </td></tr>
</table>
HTML;
}

// ─── Function 10: first_checkin_month ────────────────────────

function email_first_checkin_month(string $name, string $plan, string $dashUrl): string {
    $planLabel = strtoupper($plan);
    $fn = htmlspecialchars(explode(' ', trim($name))[0]);
    $mes = ucfirst(strftime('%B') ?: date('F'));
    $header = _bt_header($planLabel, 'ARRANQUE DEL MES');
    $footer = _bt_footer();
    $cta    = _bt_cta($dashUrl, 'Ver mi dashboard');
    return <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#0a0a0a">
  <tr><td align="center" style="padding:32px 16px">
    <table width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#18181b" style="border-radius:12px;overflow:hidden;border:1px solid #2d2d2d">
      {$header}
      <tr><td style="padding:36px 40px">
        <h2 style="color:#E31E24;font-size:1.1rem;letter-spacing:.1em;text-transform:uppercase;margin:0 0 8px">🚀 PRIMER CHECK-IN DEL MES</h2>
        <p style="color:#ffffff;font-size:1.15rem;font-weight:700;margin:0 0 16px">¡Así se arranca {$mes}, {$fn}!</p>
        <p style="color:rgba(255,255,255,.7);font-size:.95rem;line-height:1.7;margin:0 0 24px">Quien reporta en la primera semana del mes tiene 3x más probabilidad de mantener consistencia. Eres de ese grupo. Sigue así.</p>
        {$cta}
      </td></tr>
      {$footer}
    </table>
  </td></tr>
</table>
HTML;
}

// ─── Function 11: coach_no_reply ─────────────────────────────

function email_coach_no_reply(string $clientName, string $plan, string $checkinDate): string {
    $planLabel = strtoupper($plan);
    $clientNameEsc = htmlspecialchars($clientName);
    $header = _bt_header($planLabel, 'SIN RESPUESTA');
    $footer = _bt_footer();
    return <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#0a0a0a">
  <tr><td align="center" style="padding:32px 16px">
    <table width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#18181b" style="border-radius:12px;overflow:hidden;border:1px solid #2d2d2d">
      {$header}
      <tr><td style="padding:36px 40px">
        <h2 style="color:#ef4444;font-size:1.1rem;letter-spacing:.1em;text-transform:uppercase;margin:0 0 8px">⚠️ CHECK-IN SIN RESPUESTA (+48H)</h2>
        <p style="color:#ffffff;font-size:1.1rem;font-weight:700;margin:0 0 16px">El check-in de <strong>{$clientNameEsc}</strong> lleva más de 48 horas sin respuesta del coach.</p>
        <div style="background:#111113;border:1px solid #2d2d2d;border-radius:8px;padding:16px;margin-bottom:24px">
          <div style="color:#fff;font-size:.9rem">Cliente: <strong>{$clientNameEsc}</strong></div>
          <div style="color:rgba(255,255,255,.6);font-size:.85rem;margin-top:4px">Check-in enviado: {$checkinDate}</div>
        </div>
        <p style="color:rgba(255,255,255,.7);font-size:.9rem;">Por favor revisa el panel admin para asignar respuesta.</p>
      </td></tr>
      {$footer}
    </table>
  </td></tr>
</table>
HTML;
}
