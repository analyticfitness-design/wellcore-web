<?php
/**
 * WellCore Fitness — Render Plan HTML
 * ============================================================
 * Convierte un plan JSON de assigned_plans en un archivo HTML
 * personalizado guardado en planes/{clientId}-{type}.html
 * y activa el plan (active=1) para que el cliente lo vea.
 *
 * POST /api/ai/render-plan
 * Body: { plan_id: int }    → plan específico
 *       { client_id: int, plan_type: string } → último plan de ese tipo
 *
 * Auth: Bearer token de admin
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$admin = authenticateAdmin();
$db    = getDB();
$body  = getJsonBody();

// ── Resolver qué plan renderizar ──────────────────────────────
$planId   = (int) ($body['plan_id']   ?? 0);
$clientId = (int) ($body['client_id'] ?? 0);
$planType = trim($body['plan_type']   ?? '');

if ($planId > 0) {
    $stmt = $db->prepare("
        SELECT ap.*, c.client_code, c.name AS client_name, c.plan AS client_plan
        FROM assigned_plans ap JOIN clients c ON c.id = ap.client_id
        WHERE ap.id = ?
    ");
    $stmt->execute([$planId]);
} elseif ($clientId > 0 && $planType !== '') {
    $stmt = $db->prepare("
        SELECT ap.*, c.client_code, c.name AS client_name, c.plan AS client_plan
        FROM assigned_plans ap JOIN clients c ON c.id = ap.client_id
        WHERE ap.client_id = ? AND ap.plan_type = ?
        ORDER BY ap.version DESC LIMIT 1
    ");
    $stmt->execute([$clientId, $planType]);
} else {
    respondError('Proporciona plan_id o client_id + plan_type', 400);
}

$plan = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$plan) respondError('Plan no encontrado', 404);

$validTypes = ['entrenamiento', 'nutricion', 'habitos', 'rise'];
if (!in_array($plan['plan_type'], $validTypes, true)) {
    respondError('Tipo de plan inválido para renderizar', 400);
}

// ── Para RISE: obtener género del cliente (para identidad visual) ──
$clientGender = 'male';
if ($plan['plan_type'] === 'rise') {
    try {
        $gStmt = $db->prepare("SELECT gender FROM rise_programs WHERE client_id = ? ORDER BY id DESC LIMIT 1");
        $gStmt->execute([$plan['client_id']]);
        $gRow = $gStmt->fetch(PDO::FETCH_ASSOC);
        if ($gRow && in_array($gRow['gender'], ['female', 'mujer', 'f'], true)) {
            $clientGender = 'female';
        }
    } catch (\Throwable $ignored) {}
}

// ── Parsear contenido JSON ────────────────────────────────────
$content = json_decode($plan['content'] ?? '{}', true);
if (!$content) respondError('Contenido del plan inválido (no es JSON)', 422);

// ── Generar HTML según tipo ───────────────────────────────────
$html = match($plan['plan_type']) {
    'entrenamiento' => render_entrenamiento($plan, $content),
    'nutricion'     => render_nutricion($plan, $content),
    'habitos'       => render_habitos($plan, $content),
    'rise'          => render_rise($plan, $content, $clientGender),
};

// ── Guardar archivo HTML ──────────────────────────────────────
$planesDir = __DIR__ . '/../../planes/';
if (!is_dir($planesDir)) {
    @mkdir($planesDir, 0755, true);
    if (!is_dir($planesDir)) {
        respondError('No se pudo crear directorio planes/', 500);
    }
}

$filename = $plan['client_code'] . '-' . $plan['plan_type'] . '.html';
$filepath = $planesDir . $filename;

if (file_put_contents($filepath, $html) === false) {
    respondError('No se pudo escribir el archivo HTML', 500);
}

// ── Guardar HTML en columna content para que el portal cliente lo sirva ──
$db->prepare("UPDATE assigned_plans SET content = ? WHERE id = ?")
   ->execute([$html, $plan['id']]);

// ── Activar el plan en DB (desactivar anteriores, activar este) ──
$db->prepare("UPDATE assigned_plans SET active = 0 WHERE client_id = ? AND plan_type = ?")
   ->execute([$plan['client_id'], $plan['plan_type']]);
$db->prepare("UPDATE assigned_plans SET active = 1, assigned_by = ? WHERE id = ?")
   ->execute([$admin['id'], $plan['id']]);

// ── Sincronizar ai_status si el plan viene de IA ──────────────
if (!empty($plan['ai_generation_id'])) {
    try {
        $db->prepare("UPDATE ai_generations SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
           ->execute([$admin['id'], $plan['ai_generation_id']]);
    } catch (\Throwable $ignore) {}
}

// ── Email de plan listo (solo para RISE) ──────────────────────
if ($plan['plan_type'] === 'rise') {
    try {
        require_once __DIR__ . '/../includes/email.php';
        require_once __DIR__ . '/../emails/templates.php';

        $clientStmt = $db->prepare("SELECT email, name FROM clients WHERE id = ?");
        $clientStmt->execute([$plan['client_id']]);
        $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);

        if ($clientRow && $clientRow['email']) {
            $planPublicUrl = 'https://wellcorefitness.com/planes/' . $filename;
            $emailHtml = email_rise_plan_ready(
                $clientRow['name'] ?? $clientRow['email'],
                $clientGender,
                $planPublicUrl
            );
            $subjPrefix = ($clientGender === 'female') ? 'Tu plan RISE está listo' : 'Tu plan RISE está listo';
            sendEmail($clientRow['email'], $subjPrefix . ' — WellCore Fitness', $emailHtml);
        }
    } catch (\Throwable $emailErr) {
        error_log('[WellCore] Error email plan RISE: ' . $emailErr->getMessage());
    }
}

respond([
    'rendered'  => true,
    'plan_id'   => $plan['id'],
    'client_id' => $plan['client_id'],
    'plan_type' => $plan['plan_type'],
    'filename'  => $filename,
    'url'       => '/planes/' . $filename,
    'version'   => $plan['version'],
]);

// ═══════════════════════════════════════════════════════════════
// RENDERERS
// ═══════════════════════════════════════════════════════════════

function header_html(string $titulo, string $subtitulo, string $clientName, string $plan): string {
    $fecha = date('d/m/Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$titulo} — {$clientName} — WellCore</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=JetBrains+Mono:wght@400;500;600&family=Montserrat:wght@600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--red:#E31E24;--bg:#000;--surface:#111114;--border:rgba(255,255,255,.06);--text:#fff;--muted:#71717A;--accent:#00D9FF;--green:#22C55E}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;font-size:14px;line-height:1.6;padding:0}
.print-btn{position:fixed;top:16px;right:16px;z-index:9999;background:var(--red);color:#fff;border:none;padding:8px 18px;font-family:'Montserrat',sans-serif;font-weight:700;font-size:12px;letter-spacing:.05em;cursor:pointer}
.print-btn:hover{background:#B01519}
@media print{.print-btn{display:none}}
.cover{padding:48px 40px 36px;border-bottom:4px solid var(--red);background:linear-gradient(135deg,#0a0000 0%,#000 100%)}
.cover-eyebrow{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--accent);letter-spacing:3px;text-transform:uppercase;margin-bottom:8px}
.cover-title{font-family:'Bebas Neue',sans-serif;font-size:clamp(40px,6vw,72px);letter-spacing:2px;line-height:.95;margin-bottom:12px}
.cover-title span{color:var(--red)}
.cover-meta{display:flex;gap:32px;margin-top:20px;flex-wrap:wrap}
.cover-meta-item{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted)}
.cover-meta-item strong{color:var(--text);display:block;font-size:13px;margin-bottom:2px}
.content{padding:32px 40px;max-width:900px;margin:0 auto}
.section-label{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--accent);letter-spacing:3px;text-transform:uppercase;margin-bottom:6px;margin-top:40px}
.section-title{font-family:'Bebas Neue',sans-serif;font-size:28px;letter-spacing:1px;margin-bottom:20px}
.section-title span{color:var(--red)}
.card{background:var(--surface);border:1px solid var(--border);border-left:3px solid var(--red);padding:20px 24px;margin-bottom:12px}
.card-title{font-family:'Montserrat',sans-serif;font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--text);margin-bottom:8px;display:flex;align-items:center;justify-content:space-between}
table{width:100%;border-collapse:collapse;margin-top:8px}
th{background:var(--red);color:#fff;font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;padding:8px 12px;text-align:left}
td{padding:10px 12px;border-bottom:1px solid var(--border);font-size:12px;color:#D4D4D8}
tr:nth-child(even) td{background:rgba(255,255,255,.02)}
.badge{display:inline-block;font-family:'JetBrains Mono',monospace;font-size:9px;padding:2px 6px;font-weight:700;letter-spacing:1px}
.badge-red{background:var(--red);color:#fff}
.badge-cyan{background:var(--accent);color:#000}
.badge-green{background:var(--green);color:#000}
.badge-gray{background:#333;color:#aaa}
.pill-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.pill{background:#1a1a1a;border:1px solid var(--border);padding:4px 10px;font-size:12px;color:#D4D4D8}
.footer{padding:24px 40px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;margin-top:48px}
.footer-logo{font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px}
.footer-logo span{color:var(--red)}
.footer-meta{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted);text-align:right;line-height:1.8}
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">Imprimir PDF</button>
<div class="cover">
  <div class="cover-eyebrow">// WellCore Fitness — Plan Personalizado</div>
  <h1 class="cover-title">{$titulo}<br><span>{$subtitulo}</span></h1>
  <div class="cover-meta">
    <div class="cover-meta-item"><strong>{$clientName}</strong>Cliente</div>
    <div class="cover-meta-item"><strong>{$plan}</strong>Plan</div>
    <div class="cover-meta-item"><strong>{$fecha}</strong>Generado</div>
    <div class="cover-meta-item"><strong>Claude Opus 4.6</strong>Motor IA</div>
  </div>
</div>
<div class="content">
HTML;
}

function footer_html(): string {
    return <<<HTML

</div>
<div class="footer">
  <div class="footer-logo">WELL<span>CORE</span></div>
  <div class="footer-meta">
    <div>wellcorefitness.com</div>
    <div>info@wellcorefitness.com</div>
    <div>Plan generado con Claude AI</div>
  </div>
</div>
</body></html>
HTML;
}

// ── ENTRENAMIENTO ─────────────────────────────────────────────
function render_entrenamiento(array $plan, array $c): string {
    $name = htmlspecialchars($plan['client_name'] ?? 'Cliente');
    $planLabel = strtoupper($plan['client_plan'] ?? 'esencial');

    $html  = header_html('PROGRAMA DE', 'ENTRENAMIENTO', $name, $planLabel);

    // Resumen
    if (!empty($c['objetivo_principal']) || !empty($c['semanas'])) {
        $html .= '<div class="section-label">// Resumen</div>';
        $html .= '<div class="section-title">DATOS DEL <span>PROGRAMA</span></div>';
        $html .= '<div class="card">';
        $html .= '<table>';
        $html .= '<tr><th>Parámetro</th><th>Valor</th></tr>';
        if (!empty($c['semanas']))            $html .= '<tr><td>Duración</td><td>' . (int)$c['semanas'] . ' semanas</td></tr>';
        if (!empty($c['dias_por_semana']))    $html .= '<tr><td>Días / semana</td><td>' . (int)$c['dias_por_semana'] . '</td></tr>';
        if (!empty($c['objetivo_principal'])) $html .= '<tr><td>Objetivo</td><td>' . htmlspecialchars($c['objetivo_principal']) . '</td></tr>';
        if (!empty($c['progresion']))         $html .= '<tr><td>Progresión</td><td>' . htmlspecialchars($c['progresion']) . '</td></tr>';
        $html .= '</table></div>';

        if (!empty($c['principios_clave']) && is_array($c['principios_clave'])) {
            $html .= '<div class="card"><div class="card-title">Principios Clave</div><div class="pill-list">';
            foreach ($c['principios_clave'] as $p) {
                $html .= '<div class="pill">' . htmlspecialchars($p) . '</div>';
            }
            $html .= '</div></div>';
        }
    }

    // Días
    $dias = $c['dias'] ?? $c['semana'] ?? [];
    if (!empty($dias) && is_array($dias)) {
        $html .= '<div class="section-label">// Programa</div>';
        $html .= '<div class="section-title">DÍAS DE <span>ENTRENAMIENTO</span></div>';
        foreach ($dias as $dia) {
            $diaNombre  = htmlspecialchars($dia['nombre'] ?? $dia['dia'] ?? 'Día');
            $enfoque    = htmlspecialchars($dia['enfoque'] ?? $dia['grupo_muscular'] ?? '');
            $html .= '<div class="card">';
            $html .= '<div class="card-title">' . $diaNombre;
            if ($enfoque) $html .= ' <span class="badge badge-cyan">' . $enfoque . '</span>';
            $html .= '</div>';
            $ejercicios = $dia['ejercicios'] ?? [];
            if (!empty($ejercicios)) {
                $html .= '<table>';
                $html .= '<tr><th>Ejercicio</th><th>Series</th><th>Reps</th><th>Descanso</th><th>RIR</th><th>Notas</th></tr>';
                foreach ($ejercicios as $ej) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($ej['nombre'] ?? '') . '</td>';
                    $html .= '<td>' . htmlspecialchars((string)($ej['series'] ?? '')) . '</td>';
                    $html .= '<td>' . htmlspecialchars((string)($ej['reps'] ?? '')) . '</td>';
                    $html .= '<td>' . htmlspecialchars($ej['descanso'] ?? '') . '</td>';
                    $html .= '<td>' . htmlspecialchars((string)($ej['rir'] ?? $ej['rir_semana'] ?? '')) . '</td>';
                    $html .= '<td style="color:#71717A">' . htmlspecialchars($ej['notas'] ?? '') . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</table>';
            }
            $html .= '</div>';
        }
    }

    if (!empty($c['notas_coach'])) {
        $html .= '<div class="section-label">// Notas del Coach</div>';
        $html .= '<div class="card" style="border-left-color:var(--accent)">';
        $html .= '<div style="font-size:13px;color:#D4D4D8;line-height:1.7">' . nl2br(htmlspecialchars($c['notas_coach'])) . '</div>';
        $html .= '</div>';
    }

    return $html . footer_html();
}

// ── NUTRICIÓN ─────────────────────────────────────────────────
function render_nutricion(array $plan, array $c): string {
    $name      = htmlspecialchars($plan['client_name'] ?? 'Cliente');
    $planLabel = strtoupper($plan['client_plan'] ?? 'esencial');

    $html = header_html('PLAN', 'NUTRICIONAL', $name, $planLabel);

    // Macros totales
    $html .= '<div class="section-label">// Objetivos Diarios</div>';
    $html .= '<div class="section-title">DISTRIBUCIÓN DE <span>MACROS</span></div>';
    $html .= '<div class="card"><table>';
    $html .= '<tr><th>Macro</th><th>Gramos</th><th>kcal</th></tr>';
    if (!empty($c['calorias_objetivo'])) $html .= '<tr><td>Calorías objetivo</td><td>—</td><td><strong>' . (int)$c['calorias_objetivo'] . ' kcal</strong></td></tr>';
    if (!empty($c['proteina_g']))        $html .= '<tr><td>Proteína</td><td>' . (int)$c['proteina_g'] . 'g</td><td>' . ((int)$c['proteina_g'] * 4) . ' kcal</td></tr>';
    if (!empty($c['carbohidratos_g']))   $html .= '<tr><td>Carbohidratos</td><td>' . (int)$c['carbohidratos_g'] . 'g</td><td>' . ((int)$c['carbohidratos_g'] * 4) . ' kcal</td></tr>';
    if (!empty($c['grasas_g']))          $html .= '<tr><td>Grasas</td><td>' . (int)$c['grasas_g'] . 'g</td><td>' . ((int)$c['grasas_g'] * 9) . ' kcal</td></tr>';
    $html .= '</table></div>';

    // Comidas
    $comidas = $c['comidas'] ?? [];
    if (!empty($comidas)) {
        $html .= '<div class="section-label">// Distribución Diaria</div>';
        $html .= '<div class="section-title">PLAN DE <span>COMIDAS</span></div>';
        foreach ($comidas as $comida) {
            $html .= '<div class="card">';
            $html .= '<div class="card-title">' . htmlspecialchars($comida['nombre'] ?? 'Comida');
            if (!empty($comida['totales']['calorias'])) {
                $html .= ' <span class="badge badge-gray">' . (int)$comida['totales']['calorias'] . ' kcal</span>';
            }
            $html .= '</div>';
            $alimentos = $comida['alimentos'] ?? [];
            if (!empty($alimentos)) {
                $html .= '<table>';
                $html .= '<tr><th>Alimento</th><th>Gramos</th><th>Proteína</th><th>Carbs</th><th>Grasas</th></tr>';
                foreach ($alimentos as $a) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($a['nombre'] ?? '') . '</td>';
                    $html .= '<td>' . htmlspecialchars((string)($a['gramos'] ?? '')) . 'g</td>';
                    $html .= '<td>' . htmlspecialchars((string)($a['proteina'] ?? '')) . 'g</td>';
                    $html .= '<td>' . htmlspecialchars((string)($a['carbs'] ?? '')) . 'g</td>';
                    $html .= '<td>' . htmlspecialchars((string)($a['grasas'] ?? '')) . 'g</td>';
                    $html .= '</tr>';
                }
                $html .= '</table>';
            }
            $html .= '</div>';
        }
    }

    // Suplementación
    $supl = $c['suplementacion'] ?? [];
    if (!empty($supl) && is_array($supl)) {
        $html .= '<div class="section-label">// Opcionales</div>';
        $html .= '<div class="section-title">SUPLE<span>MENTACIÓN</span></div>';
        $html .= '<div class="card"><div class="pill-list">';
        foreach ($supl as $s) {
            $label = is_array($s) ? ($s['nombre'] ?? json_encode($s)) : $s;
            $html .= '<div class="pill">' . htmlspecialchars($label) . '</div>';
        }
        $html .= '</div></div>';
    }

    if (!empty($c['notas_coach'])) {
        $html .= '<div class="section-label">// Notas del Coach</div>';
        $html .= '<div class="card" style="border-left-color:var(--accent)"><div style="font-size:13px;color:#D4D4D8;line-height:1.7">' . nl2br(htmlspecialchars($c['notas_coach'])) . '</div></div>';
    }

    return $html . footer_html();
}

// ── RISE ─────────────────────────────────────────────────────────
// Renderer de plan RISE 30 días — usa el CSS/identidad de la plantilla
// RISE_V2_MUJER_AVANZADO_CASA.html
// Columna "Ver" por ejercicio es placeholder — se linkeará a videos futura versión
function render_rise(array $plan, array $c, string $gender = 'male'): string {
    $name    = htmlspecialchars($plan['client_name'] ?? 'Cliente');
    $fecha   = date('d/m/Y');
    $diasSem = (int) ($c['dias_entreno_semana'] ?? 4);
    $objetivo  = htmlspecialchars($c['objetivo_30_dias'] ?? '');
    $resumen   = htmlspecialchars($c['resumen_cliente'] ?? '');
    $estructura = htmlspecialchars($c['estructura_semana'] ?? '');

    // Paleta según género
    $accentColor  = ($gender === 'female') ? '#D4A8C7' : '#E31E24';
    $accentBorder = ($gender === 'female') ? 'rgba(212,168,199,0.35)' : 'rgba(227,30,36,0.3)';
    $coverBg      = ($gender === 'female')
        ? 'linear-gradient(135deg,#0d0008 0%,#0a0a0a 100%)'
        : 'linear-gradient(135deg,#0d0000 0%,#0a0a0a 100%)';

    // ── CSS (igual que RISE_V2 template) ─────────────────────────
    $h  = "<!DOCTYPE html>\n<html lang=\"es\">\n<head>\n";
    $h .= "<meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1.0\">\n";
    $h .= "<title>RISE 30 Días — {$name} | WellCore Fitness</title>\n";
    $h .= "<link href=\"https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700;800&display=swap\" rel=\"stylesheet\">\n";
    $h .= "<style>\n";
    $h .= ":root{--red:{$accentColor};--bg:#0A0A0A;--surface:#111113;--card:#161618;--border:#2A2A2E;--border-subtle:#1E1E22;--text:#FFFFFF;--text-sec:#D4D4D8;--muted:#71717A;--accent:#00D9FF;}\n";
    $h .= "*{margin:0;padding:0;box-sizing:border-box;}\n";
    $h .= "body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:11px;line-height:1.5;}\n";
    $h .= ".page{width:100%;max-width:860px;margin:0 auto;padding:40px 32px;}\n";
    $h .= ".print-btn{position:fixed;top:16px;right:16px;z-index:9999;background:var(--red);color:#fff;border:none;padding:10px 20px;font-family:'JetBrains Mono',monospace;font-weight:700;font-size:11px;letter-spacing:2px;cursor:pointer;text-transform:uppercase;}\n";
    $h .= "@media print{.print-btn{display:none}.page{padding:12px}}\n";
    // Cover
    $h .= ".cover{position:relative;min-height:300px;display:flex;flex-direction:column;justify-content:flex-end;overflow:hidden;border:1px solid var(--border);margin-bottom:32px;background:{$coverBg};}\n";
    $h .= ".cover-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(10,10,10,0.05) 0%,rgba(10,10,10,0.97) 65%);}\n";
    $h .= ".cover-content{position:relative;z-index:2;padding:40px 36px 28px;}\n";
    $h .= ".cover-badge{display:inline-block;background:var(--red);color:#fff;font-family:'JetBrains Mono',monospace;font-size:9px;font-weight:600;padding:4px 12px;letter-spacing:2px;text-transform:uppercase;margin-bottom:14px;}\n";
    $h .= ".cover-title{font-family:'Bebas Neue',sans-serif;font-size:64px;letter-spacing:3px;line-height:0.95;color:var(--text);margin-bottom:6px;}\n";
    $h .= ".cover-title span{color:var(--red);}\n";
    $h .= ".cover-subtitle{font-family:'Montserrat',sans-serif;font-size:12px;font-weight:600;color:var(--text-sec);letter-spacing:4px;text-transform:uppercase;margin-bottom:20px;}\n";
    $h .= ".cover-meta{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.08);}\n";
    $h .= ".cover-meta-label{font-family:'JetBrains Mono',monospace;font-size:8px;color:var(--muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:3px;}\n";
    $h .= ".cover-meta-value{font-family:'Inter',sans-serif;font-size:12px;font-weight:600;color:var(--text);}\n";
    $h .= ".cover-footer{display:flex;justify-content:space-between;align-items:center;padding:12px 36px;background:rgba(0,0,0,0.7);border-top:1px solid var(--border);position:relative;z-index:2;}\n";
    $h .= ".cover-footer-brand{font-family:'Bebas Neue',sans-serif;font-size:13px;letter-spacing:3px;color:var(--muted);}\n";
    $h .= ".cover-footer-coach{font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:1px;}\n";
    // Sections
    $h .= ".section-header{margin-bottom:20px;margin-top:32px;}\n";
    $h .= ".section-label{font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--red);letter-spacing:2px;text-transform:uppercase;margin-bottom:6px;}\n";
    $h .= ".section-title{font-family:'Bebas Neue',sans-serif;font-size:28px;color:var(--text);letter-spacing:2px;line-height:1;}\n";
    $h .= ".section-divider{width:40px;height:3px;background:var(--red);margin:7px 0 18px;}\n";
    // Overview
    $h .= ".overview-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px;}\n";
    $h .= ".overview-item{background:var(--surface);border-top:2px solid var(--red);padding:12px;}\n";
    $h .= ".overview-number{font-family:'Bebas Neue',sans-serif;font-size:22px;color:var(--text);line-height:1;margin-bottom:2px;}\n";
    $h .= ".overview-label{font-family:'JetBrains Mono',monospace;font-size:8px;color:var(--muted);letter-spacing:1px;text-transform:uppercase;}\n";
    $h .= ".description-block{background:var(--surface);border-left:3px solid var(--border);padding:14px 18px;margin-bottom:20px;font-size:11px;color:var(--text-sec);line-height:1.7;}\n";
    $h .= ".description-block strong{color:var(--text);font-weight:600;}\n";
    // Prog grid
    $h .= ".prog-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:28px;}\n";
    $h .= ".prog-item{background:var(--surface);border-top:2px solid var(--accent);padding:12px;}\n";
    $h .= ".prog-week{font-family:'JetBrains Mono',monospace;font-size:8px;color:var(--accent);letter-spacing:1px;text-transform:uppercase;margin-bottom:4px;}\n";
    $h .= ".prog-title{font-family:'Montserrat',sans-serif;font-size:11px;font-weight:700;color:var(--text);margin-bottom:4px;}\n";
    $h .= ".prog-desc{font-size:10px;color:var(--text-sec);line-height:1.5;}\n";
    // Days
    $h .= ".day-section{margin-bottom:32px;}\n";
    $h .= ".day-header{display:flex;align-items:center;gap:14px;padding-bottom:10px;border-bottom:2px solid var(--border);margin-bottom:14px;}\n";
    $h .= ".day-number{font-family:'Bebas Neue',sans-serif;font-size:38px;color:var(--red);line-height:1;min-width:42px;}\n";
    $h .= ".day-info{flex:1;}\n";
    $h .= ".day-name{font-family:'Montserrat',sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--text);margin-bottom:2px;}\n";
    $h .= ".day-focus{font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:1px;}\n";
    $h .= ".day-duration{text-align:right;}\n";
    $h .= ".day-duration-label{font-family:'JetBrains Mono',monospace;font-size:8px;color:var(--muted);letter-spacing:1px;text-transform:uppercase;}\n";
    $h .= ".day-duration-value{font-family:'Bebas Neue',sans-serif;font-size:16px;color:var(--text);letter-spacing:1px;}\n";
    // Warmup
    $h .= ".warmup-block{background:var(--surface);border-left:3px solid var(--accent);padding:10px 14px;margin-bottom:12px;}\n";
    $h .= ".warmup-title{font-family:'JetBrains Mono',monospace;font-size:9px;font-weight:700;color:var(--accent);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:5px;}\n";
    $h .= ".warmup-text{font-size:10px;color:var(--text-sec);line-height:1.6;}\n";
    // Table
    $h .= ".table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;margin-bottom:14px;}\n";
    $h .= "table{width:100%;min-width:560px;border-collapse:collapse;font-size:10px;background:var(--surface);}\n";
    $h .= "thead tr{background:var(--red);}thead th{padding:7px 6px;font-family:'Montserrat',sans-serif;font-size:8px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#fff;text-align:left;white-space:nowrap;}\n";
    $h .= "thead th:first-child{width:26px;text-align:center;}\n";
    $h .= "tbody tr{border-bottom:1px solid var(--border-subtle);}tbody tr:last-child{border-bottom:none;}\n";
    $h .= "tbody tr:nth-child(even){background:#0D0D0F;}tbody tr:nth-child(odd){background:var(--surface);}\n";
    $h .= "tbody td{padding:7px 6px;color:var(--text-sec);vertical-align:top;line-height:1.4;}\n";
    $h .= "tbody td:first-child{color:var(--red);font-weight:700;font-size:11px;text-align:center;vertical-align:middle;}\n";
    $h .= "td.exercise-name{font-weight:600;color:var(--text);font-size:11px;white-space:normal;min-width:140px;max-width:190px;}\n";
    $h .= "td.exercise-name .muscle-tag{display:block;font-size:9px;font-weight:400;color:var(--muted);margin-top:2px;}\n";
    $h .= "td.sets-col{font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--accent);text-align:center;white-space:nowrap;min-width:38px;vertical-align:middle;}\n";
    $h .= "td.reps-col{font-family:'JetBrains Mono',monospace;font-weight:600;color:var(--accent);text-align:center;white-space:nowrap;min-width:48px;vertical-align:middle;}\n";
    $h .= "td.rest-col{font-family:'JetBrains Mono',monospace;font-size:10px;color:#888;text-align:center;white-space:nowrap;min-width:52px;vertical-align:middle;}\n";
    $h .= "td.rir-col{font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:700;color:var(--red);text-align:center;white-space:nowrap;min-width:38px;vertical-align:middle;}\n";
    $h .= "td.notes-col{font-size:10px;color:var(--muted);white-space:normal;min-width:120px;max-width:170px;line-height:1.4;vertical-align:top;}\n";
    $h .= "td.ver-col{text-align:center;vertical-align:middle;min-width:40px;}\n";
    $h .= ".ver-btn{display:inline-block;font-family:'JetBrains Mono',monospace;font-size:8px;font-weight:700;letter-spacing:0.5px;padding:3px 7px;background:rgba(0,217,255,0.08);border:1px solid rgba(0,217,255,0.18);color:var(--accent);text-decoration:none;cursor:default;white-space:nowrap;}\n";
    // Cardio
    $h .= ".cardio-block{background:var(--surface);border-left:3px solid #22C55E;padding:10px 16px;margin-top:10px;margin-bottom:8px;}\n";
    $h .= ".cardio-title{font-family:'JetBrains Mono',monospace;font-size:9px;font-weight:700;color:#22C55E;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;}\n";
    $h .= ".cardio-text{font-size:10px;color:var(--text-sec);line-height:1.5;}\n";
    // Nutricion
    $h .= ".nutr-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}\n";
    $h .= ".nutr-card{background:var(--surface);border-left:3px solid var(--red);padding:12px 16px;}\n";
    $h .= ".nutr-card-title{font-family:'JetBrains Mono',monospace;font-size:9px;font-weight:700;color:var(--red);letter-spacing:1px;text-transform:uppercase;margin-bottom:5px;}\n";
    $h .= ".nutr-card-text{font-size:10px;color:var(--text-sec);line-height:1.6;}\n";
    $h .= ".nutr-list{list-style:none;display:flex;flex-direction:column;gap:3px;font-size:10px;color:var(--text-sec);}\n";
    $h .= ".nutr-list li::before{content:'› ';color:var(--red);font-weight:700;}\n";
    $h .= ".nutr-cta{background:var(--surface);border:1px solid {$accentBorder};padding:14px 18px;margin-top:14px;font-size:10px;color:var(--text-sec);line-height:1.7;}\n";
    $h .= ".nutr-cta strong{color:var(--red);}\n";
    // Footer
    $h .= ".doc-footer{border-top:1px solid var(--border);padding-top:14px;margin-top:32px;display:flex;justify-content:space-between;align-items:center;}\n";
    $h .= ".doc-footer-brand{font-family:'Bebas Neue',sans-serif;font-size:13px;letter-spacing:3px;color:var(--text);}\n";
    $h .= ".doc-footer-brand span{color:var(--red);}\n";
    $h .= ".doc-footer-info{font-family:'JetBrains Mono',monospace;font-size:8px;color:var(--muted);letter-spacing:1px;text-align:right;line-height:1.6;}\n";
    $h .= "@media(max-width:600px){.page{padding:16px}.cover-content{padding:24px 20px}.cover-title{font-size:46px}.cover-meta,.overview-grid,.prog-grid{grid-template-columns:1fr 1fr}.nutr-grid{grid-template-columns:1fr}}\n";
    $h .= "</style></head><body>\n";
    $h .= "<button class=\"print-btn\" onclick=\"window.print()\">Imprimir / PDF</button>\n";
    $h .= "<div class=\"page\">\n";

    // ── COVER ──────────────────────────────────────────────────
    $cardioLabel = ($c['incluye_cardio'] ?? false) ? 'Sí' : 'No';
    $h .= "<div class=\"cover\"><div class=\"cover-overlay\"></div><div class=\"cover-content\">";
    $h .= "<div class=\"cover-badge\">RETO RISE &middot; 30 D&Iacute;AS</div>";
    $h .= "<div class=\"cover-title\">RISE<br><span>30 D&Iacute;AS</span></div>";
    $h .= "<div class=\"cover-subtitle\">" . htmlspecialchars($name) . "</div>";
    $h .= "<div class=\"cover-meta\">";
    $h .= "<div><div class=\"cover-meta-label\">Cliente</div><div class=\"cover-meta-value\">{$name}</div></div>";
    $h .= "<div><div class=\"cover-meta-label\">Frecuencia</div><div class=\"cover-meta-value\">{$diasSem} d&iacute;as/sem</div></div>";
    $h .= "<div><div class=\"cover-meta-label\">Duraci&oacute;n</div><div class=\"cover-meta-value\">30 d&iacute;as</div></div>";
    $h .= "<div><div class=\"cover-meta-label\">Generado</div><div class=\"cover-meta-value\">{$fecha}</div></div>";
    $h .= "</div></div>";
    $h .= "<div class=\"cover-footer\"><div class=\"cover-footer-brand\">WELLCORE FITNESS</div><div class=\"cover-footer-coach\">@wellcore.fitness</div></div>";
    $h .= "</div>\n";

    // ── RESUMEN ────────────────────────────────────────────────
    $h .= "<div class=\"section-header\"><div class=\"section-label\">Plan de Entrenamiento</div><div class=\"section-title\">RESUMEN DEL PROGRAMA</div><div class=\"section-divider\"></div></div>\n";
    $h .= "<div class=\"overview-grid\">";
    $h .= "<div class=\"overview-item\"><div class=\"overview-number\">{$diasSem} d&iacute;as</div><div class=\"overview-label\">Frecuencia semanal</div></div>";
    $h .= "<div class=\"overview-item\"><div class=\"overview-number\">4 semanas</div><div class=\"overview-label\">Duraci&oacute;n total</div></div>";
    $h .= "<div class=\"overview-item\"><div class=\"overview-number\">{$cardioLabel}</div><div class=\"overview-label\">Incluye cardio</div></div>";
    $h .= "<div class=\"overview-item\"><div class=\"overview-number\">Tips</div><div class=\"overview-label\">Gu&iacute;a nutricional</div></div>";
    $h .= "</div>\n";

    if ($objetivo || $resumen || $estructura) {
        $h .= "<div class=\"description-block\">";
        if ($objetivo)   { $h .= "<strong>Objetivo 30 d&iacute;as:</strong> " . nl2br(htmlspecialchars($objetivo)) . "<br><br>"; }
        if ($resumen)    { $h .= "<strong>Perfil:</strong> " . nl2br(htmlspecialchars($resumen)) . "<br><br>"; }
        if ($estructura) { $h .= "<strong>Estructura semanal:</strong> " . htmlspecialchars($estructura); }
        $h .= "</div>\n";
    }

    // ── PROGRESION 4 SEMANAS ──────────────────────────────────
    $semanas = $c['plan_entrenamiento']['semanas'] ?? [];
    if (!empty($semanas)) {
        $h .= "<div class=\"prog-grid\">";
        foreach ($semanas as $s) {
            $sw = (int) ($s['semana'] ?? 0);
            $sn = htmlspecialchars($s['nombre'] ?? 'Semana ' . $sw);
            $sd = htmlspecialchars($s['descripcion'] ?? '');
            $sr = $s['rir_objetivo'] ?? '';
            $h .= "<div class=\"prog-item\"><div class=\"prog-week\">Semana {$sw}</div><div class=\"prog-title\">{$sn}</div><div class=\"prog-desc\">{$sd}</div>";
            if ($sr !== '') { $h .= "<div style=\"margin-top:6px;font-family:'JetBrains Mono',monospace;font-size:8px;color:var(--red)\">RIR: {$sr}</div>"; }
            $h .= "</div>";
        }
        $h .= "</div>\n";
    }

    // ── DÍAS DE ENTRENAMIENTO (base: semana 1) ────────────────
    $sesionesBase = $semanas[0]['sesiones'] ?? [];
    $dayNum = 1;
    foreach ($sesionesBase as $sesion) {
        $diaNombre = htmlspecialchars(strtoupper($sesion['dia'] ?? ('DÍA ' . $dayNum)));
        $diaFocus  = htmlspecialchars($sesion['nombre'] ?? '');
        $calent    = htmlspecialchars($sesion['calentamiento'] ?? '');
        $vuelta    = htmlspecialchars($sesion['vuelta_calma'] ?? '');
        $numPad    = str_pad((string) $dayNum, 2, '0', STR_PAD_LEFT);

        $h .= "<div class=\"day-section\">\n";
        $h .= "<div class=\"day-header\">";
        $h .= "<div class=\"day-number\">{$numPad}</div>";
        $h .= "<div class=\"day-info\"><div class=\"day-name\">{$diaNombre} &mdash; {$diaFocus}</div><div class=\"day-focus\">Progresi&oacute;n RIR: 3 &middot; 2 &middot; 1 &middot; 4 (deload)</div></div>";
        $h .= "<div class=\"day-duration\"><div class=\"day-duration-label\">Duraci&oacute;n</div><div class=\"day-duration-value\">45-60 MIN</div></div>";
        $h .= "</div>\n";

        if ($calent) {
            $h .= "<div class=\"warmup-block\"><div class=\"warmup-title\">Calentamiento</div><div class=\"warmup-text\">{$calent}</div></div>\n";
        }

        $ejercicios = $sesion['ejercicios'] ?? [];
        if (!empty($ejercicios)) {
            $h .= "<div class=\"table-wrap\"><table>";
            $h .= "<thead><tr><th>#</th><th>Ejercicio</th><th>Series</th><th>Reps</th><th>Descanso</th><th>RIR</th><th>Nota del Coach</th><th>Ver</th></tr></thead><tbody>";
            foreach ($ejercicios as $ei => $ej) {
                $eNum    = $ei + 1;
                $eNombre = htmlspecialchars($ej['nombre'] ?? '');
                $eMuscle = htmlspecialchars($ej['musculos'] ?? $ej['patron_motor'] ?? $ej['musculos_prim'][0] ?? '');
                $eSeries = htmlspecialchars((string) ($ej['series'] ?? '-'));
                $eReps   = htmlspecialchars($ej['reps'] ?? '-');
                $eDesc   = htmlspecialchars($ej['descanso'] ?? '-');
                $eRir    = htmlspecialchars(is_array($ej['rir_semana'] ?? null)
                    ? implode('·', $ej['rir_semana'])
                    : (string) ($ej['rir'] ?? '2'));
                $eNotas  = htmlspecialchars($ej['notas'] ?? '');

                $h .= "<tr>";
                $h .= "<td>{$eNum}</td>";
                $h .= "<td class=\"exercise-name\">{$eNombre}";
                if ($eMuscle) { $h .= "<span class=\"muscle-tag\">{$eMuscle}</span>"; }
                $h .= "</td>";
                $h .= "<td class=\"sets-col\">{$eSeries}</td>";
                $h .= "<td class=\"reps-col\">{$eReps}</td>";
                $h .= "<td class=\"rest-col\">{$eDesc}</td>";
                $h .= "<td class=\"rir-col\">{$eRir}</td>";
                $h .= "<td class=\"notes-col\">{$eNotas}</td>";
                // Botón Ver — placeholder hasta vincular videos
                $h .= "<td class=\"ver-col\"><span class=\"ver-btn\">Ver</span></td>";
                $h .= "</tr>";
            }
            $h .= "</tbody></table></div>\n";
        }

        // Cardio del día (si viene en la sesión)
        $cardioDia = $sesion['cardio_finalizador'] ?? $sesion['cardio'] ?? null;
        if (!empty($cardioDia)) {
            $cardioTxt = is_array($cardioDia)
                ? htmlspecialchars($cardioDia['descripcion'] ?? implode(' | ', array_filter(array_map(fn($v) => is_string($v) ? $v : '', $cardioDia))))
                : htmlspecialchars((string) $cardioDia);
            $h .= "<div class=\"cardio-block\"><div class=\"cardio-title\">CARDIO FINALIZADOR</div><div class=\"cardio-text\">{$cardioTxt}</div></div>\n";
        }

        if ($vuelta) {
            $h .= "<div class=\"warmup-block\" style=\"border-left-color:var(--muted);margin-top:10px\"><div class=\"warmup-title\" style=\"color:var(--muted)\">Vuelta a la calma</div><div class=\"warmup-text\">{$vuelta}</div></div>\n";
        }

        $h .= "</div>\n"; // day-section
        $dayNum++;
    }

    // ── PROTOCOLO CARDIO (sección general) ───────────────────
    $cardio = $c['cardio'] ?? null;
    if (!empty($cardio) && ($cardio['incluido'] ?? false)) {
        $h .= "<div class=\"section-header\"><div class=\"section-label\">Cardio</div><div class=\"section-title\">PROTOCOLO DE CARDIO</div><div class=\"section-divider\"></div></div>\n";
        $cFr  = htmlspecialchars((string) ($cardio['frecuencia_semanal'] ?? ''));
        $cDu  = htmlspecialchars((string) ($cardio['duracion_min'] ?? ''));
        $cTi  = htmlspecialchars($cardio['tipo'] ?? '');
        $cCu  = htmlspecialchars($cardio['cuando'] ?? '');
        $cGym = is_array($cardio['opciones_gym'] ?? null)  ? htmlspecialchars(implode(' &middot; ', $cardio['opciones_gym']))  : '';
        $cCas = is_array($cardio['opciones_casa'] ?? null) ? htmlspecialchars(implode(' &middot; ', $cardio['opciones_casa'])) : '';
        $cPr  = htmlspecialchars($cardio['semanas_progresion'] ?? '');

        $h .= "<div class=\"cardio-block\"><div class=\"cardio-title\">CARDIO &mdash; {$cFr}x/SEMANA &mdash; {$cDu} MIN</div><div class=\"cardio-text\">";
        if ($cTi)  { $h .= "<strong>Tipo:</strong> {$cTi}<br>"; }
        if ($cCu)  { $h .= "<strong>Cu&aacute;ndo:</strong> {$cCu}<br>"; }
        if ($cGym) { $h .= "<strong>Opciones gym:</strong> {$cGym}<br>"; }
        if ($cCas) { $h .= "<strong>Opciones casa:</strong> {$cCas}<br>"; }
        if ($cPr)  { $h .= "<strong>Progresi&oacute;n:</strong> {$cPr}"; }
        $h .= "</div></div>\n";
    }

    // ── TIPS NUTRICIÓN ────────────────────────────────────────
    $nutr = $c['tips_nutricion'] ?? null;
    if (!empty($nutr)) {
        $h .= "<div class=\"section-header\"><div class=\"section-label\">Nutrici&oacute;n</div><div class=\"section-title\">GU&Iacute;A DE ALIMENTACI&Oacute;N</div><div class=\"section-divider\"></div></div>\n";
        $campos = [
            'principio_base'        => 'Principio Base',
            'proteina'              => 'Prote&iacute;na',
            'hidratacion'           => 'Hidrataci&oacute;n',
            'distribucion_comidas'  => 'Distribuci&oacute;n de Comidas',
            'pre_entreno'           => 'Pre-Entreno',
            'post_entreno'          => 'Post-Entreno',
            'respeto_dieta_cliente' => 'Tu Tipo de Dieta',
        ];
        $h .= "<div class=\"nutr-grid\">";
        foreach ($campos as $key => $label) {
            if (!empty($nutr[$key])) {
                $val = htmlspecialchars($nutr[$key]);
                $h .= "<div class=\"nutr-card\"><div class=\"nutr-card-title\">{$label}</div><div class=\"nutr-card-text\">{$val}</div></div>";
            }
        }
        $h .= "</div>\n";

        if (!empty($nutr['alimentos_aliados']) && is_array($nutr['alimentos_aliados'])) {
            $h .= "<div class=\"nutr-card\" style=\"margin-bottom:10px\"><div class=\"nutr-card-title\">Alimentos Aliados</div><ul class=\"nutr-list\">";
            foreach ($nutr['alimentos_aliados'] as $al) { $h .= '<li>' . htmlspecialchars($al) . '</li>'; }
            $h .= "</ul></div>\n";
        }
        if (!empty($nutr['alimentos_reducir']) && is_array($nutr['alimentos_reducir'])) {
            $h .= "<div class=\"nutr-card\" style=\"margin-bottom:10px;border-left-color:#FACC15\"><div class=\"nutr-card-title\" style=\"color:#FACC15\">Reducir / Evitar</div><ul class=\"nutr-list\">";
            foreach ($nutr['alimentos_reducir'] as $al) { $h .= '<li>' . htmlspecialchars($al) . '</li>'; }
            $h .= "</ul></div>\n";
        }

        $ctaText = htmlspecialchars($nutr['nota_asesoria_nutricional'] ?? 'Para maximizar tus resultados con un plan nutricional 100% personalizado — macros exactos, seguimiento semanal y ajustes continuos — te recomendamos la Asesor&iacute;a Nutricional WellCore al finalizar el reto.');
        $h .= "<div class=\"nutr-cta\"><strong>&iquest;Quieres resultados m&aacute;ximos?</strong><br>{$ctaText}</div>\n";
    }

    // ── INDICADORES ───────────────────────────────────────────
    $indicadores = $c['indicadores_progreso'] ?? [];
    if (!empty($indicadores) && is_array($indicadores)) {
        $h .= "<div class=\"section-header\"><div class=\"section-label\">Seguimiento</div><div class=\"section-title\">INDICADORES DE PROGRESO</div><div class=\"section-divider\"></div></div>\n";
        $h .= "<div class=\"description-block\"><ul class=\"nutr-list\">";
        foreach ($indicadores as $ind) { $h .= '<li>' . htmlspecialchars($ind) . '</li>'; }
        $h .= "</ul></div>\n";
    }

    // ── NOTA DEL COACH ────────────────────────────────────────
    if (!empty($c['nota_coach'])) {
        $h .= "<div class=\"section-header\"><div class=\"section-label\">Del Coach</div><div class=\"section-title\">MENSAJE FINAL</div><div class=\"section-divider\"></div></div>\n";
        $h .= "<div class=\"description-block\" style=\"border-left-color:var(--red);font-size:12px;line-height:1.8;font-style:italic\">" . nl2br(htmlspecialchars($c['nota_coach'])) . "</div>\n";
    }

    // ── FOOTER ────────────────────────────────────────────────
    $h .= "<div class=\"doc-footer\"><div class=\"doc-footer-brand\">WELL<span>CORE</span></div>";
    $h .= "<div class=\"doc-footer-info\"><div>wellcorefitness.com</div><div>@wellcore.fitness</div><div>{$fecha}</div></div></div>\n";
    $h .= "</div></body></html>";
    return $h;
}

// ── HÁBITOS ───────────────────────────────────────────────────
function render_habitos(array $plan, array $c): string {
    $name      = htmlspecialchars($plan['client_name'] ?? 'Cliente');
    $planLabel = strtoupper($plan['client_plan'] ?? 'elite');

    $html = header_html('PLAN DE', 'HÁBITOS', $name, $planLabel);

    // Pilares
    $pilares = $c['pilares'] ?? [];
    if (!empty($pilares) && is_array($pilares)) {
        $html .= '<div class="section-label">// Fundamentos</div>';
        $html .= '<div class="section-title">PILARES DE <span>BIENESTAR</span></div>';
        foreach ($pilares as $pilar) {
            $html .= '<div class="card">';
            $html .= '<div class="card-title">' . htmlspecialchars($pilar['nombre'] ?? '') . ' <span class="badge badge-red">' . htmlspecialchars($pilar['prioridad'] ?? '') . '</span></div>';
            if (!empty($pilar['descripcion'])) $html .= '<p style="font-size:13px;color:#A1A1AA;margin-top:8px">' . htmlspecialchars($pilar['descripcion']) . '</p>';
            if (!empty($pilar['acciones']) && is_array($pilar['acciones'])) {
                $html .= '<div class="pill-list" style="margin-top:10px">';
                foreach ($pilar['acciones'] as $acc) {
                    $html .= '<div class="pill">' . htmlspecialchars($acc) . '</div>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }
    }

    // Rutina mañana
    $manana = $c['rutina_manana'] ?? [];
    if (!empty($manana) && is_array($manana)) {
        $html .= '<div class="section-label">// Mañana</div>';
        $html .= '<div class="section-title">RUTINA DE <span>LA MAÑANA</span></div>';
        $html .= '<div class="card"><table><tr><th>#</th><th>Hábito</th><th>Duración</th><th>Descripción</th></tr>';
        foreach ($manana as $i => $h) {
            $habito = is_array($h) ? $h : ['habito' => $h];
            $html .= '<tr>';
            $html .= '<td style="color:var(--red);font-weight:bold">' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '</td>';
            $html .= '<td>' . htmlspecialchars($habito['habito'] ?? $habito['nombre'] ?? '') . '</td>';
            $html .= '<td style="color:var(--accent)">' . htmlspecialchars($habito['duracion'] ?? '') . '</td>';
            $html .= '<td style="color:#71717A">' . htmlspecialchars($habito['descripcion'] ?? '') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table></div>';
    }

    // Rutina noche
    $noche = $c['rutina_noche'] ?? [];
    if (!empty($noche) && is_array($noche)) {
        $html .= '<div class="section-label">// Noche</div>';
        $html .= '<div class="section-title">RUTINA <span>NOCTURNA</span></div>';
        $html .= '<div class="card"><table><tr><th>#</th><th>Hábito</th><th>Duración</th><th>Descripción</th></tr>';
        foreach ($noche as $i => $h) {
            $habito = is_array($h) ? $h : ['habito' => $h];
            $html .= '<tr>';
            $html .= '<td style="color:var(--red);font-weight:bold">' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '</td>';
            $html .= '<td>' . htmlspecialchars($habito['habito'] ?? $habito['nombre'] ?? '') . '</td>';
            $html .= '<td style="color:var(--accent)">' . htmlspecialchars($habito['duracion'] ?? '') . '</td>';
            $html .= '<td style="color:#71717A">' . htmlspecialchars($habito['descripcion'] ?? '') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table></div>';
    }

    // Seguimiento
    $seg = $c['seguimiento_semanal'] ?? [];
    if (!empty($seg) && is_array($seg)) {
        $html .= '<div class="section-label">// Seguimiento</div>';
        $html .= '<div class="section-title">MÉTRICAS <span>SEMANALES</span></div>';
        $html .= '<div class="card"><div class="pill-list">';
        foreach ($seg as $k => $v) {
            $label = is_string($k) ? "$k: $v" : (is_string($v) ? $v : json_encode($v));
            $html .= '<div class="pill">' . htmlspecialchars($label) . '</div>';
        }
        $html .= '</div></div>';
    }

    if (!empty($c['notas_coach'])) {
        $html .= '<div class="section-label">// Notas del Coach</div>';
        $html .= '<div class="card" style="border-left-color:var(--accent)"><div style="font-size:13px;color:#D4D4D8;line-height:1.7">' . nl2br(htmlspecialchars($c['notas_coach'])) . '</div></div>';
    }

    return $html . footer_html();
}
