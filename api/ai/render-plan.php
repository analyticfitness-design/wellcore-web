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

$validTypes = ['entrenamiento', 'nutricion', 'habitos'];
if (!in_array($plan['plan_type'], $validTypes, true)) {
    respondError('Tipo de plan inválido para renderizar', 400);
}

// ── Parsear contenido JSON ────────────────────────────────────
$content = json_decode($plan['content'] ?? '{}', true);
if (!$content) respondError('Contenido del plan inválido (no es JSON)', 422);

// ── Generar HTML según tipo ───────────────────────────────────
$html = match($plan['plan_type']) {
    'entrenamiento' => render_entrenamiento($plan, $content),
    'nutricion'     => render_nutricion($plan, $content),
    'habitos'       => render_habitos($plan, $content),
};

// ── Guardar archivo HTML ──────────────────────────────────────
$planesDir = __DIR__ . '/../../planes/';
if (!is_dir($planesDir)) {
    respondError('Directorio planes/ no encontrado', 500);
}

$filename = $plan['client_code'] . '-' . $plan['plan_type'] . '.html';
$filepath = $planesDir . $filename;

if (file_put_contents($filepath, $html) === false) {
    respondError('No se pudo escribir el archivo HTML', 500);
}

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
