<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Análisis de Progreso con IA
 * ============================================================
 * GET  /api/ai/analyze-progress?client_id=X
 * POST /api/ai/analyze-progress  { client_id, force_regenerate? }
 *
 * Lee métricas, check-ins y fotos del cliente. Genera análisis
 * de progreso con detección de patrones y recomendaciones de ajuste.
 *
 * Auth: Bearer token de admin o coach
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST');
$admin    = authenticateAdmin();
$clientId = (int) ($_GET['client_id'] ?? (getJsonBody()['client_id'] ?? 0));

if (!$clientId) respondError('client_id requerido', 422);
if (!ai_check_rate_limit()) respondError('Rate limit alcanzado.', 429);

try {
    $client = get_client_for_ai($clientId);
} catch (\RuntimeException $e) {
    respondError($e->getMessage(), 404);
}

$db = getDB();

// ── Cargar historial de métricas (últimas 8 semanas) ─────────
$metricas = [];
try {
    $stmt = $db->prepare("
        SELECT fecha, peso, grasa_corporal, masa_muscular, notas
        FROM client_metrics
        WHERE client_id = ?
        ORDER BY fecha DESC
        LIMIT 16
    ");
    $stmt->execute([$clientId]);
    $metricas = $stmt->fetchAll();
} catch (\Throwable $e) {
    // Tabla no existe o sin datos
}

// ── Cargar check-ins (últimas 4 semanas) ─────────────────────
$checkins = [];
try {
    $stmt = $db->prepare("
        SELECT fecha, energia, sueno, adherencia_entreno, adherencia_nutricion, notas
        FROM client_checkins
        WHERE client_id = ?
        ORDER BY fecha DESC
        LIMIT 8
    ");
    $stmt->execute([$clientId]);
    $checkins = $stmt->fetchAll();
} catch (\Throwable $e) {
    // Sin datos de check-in
}

// ── Cargar fotos de progreso ──────────────────────────────────
$fotos = [];
try {
    $stmt = $db->prepare("
        SELECT photo_date, tipo, created_at
        FROM progress_photos
        WHERE client_id = ?
        ORDER BY photo_date DESC
        LIMIT 30
    ");
    $stmt->execute([$clientId]);
    $fotos = $stmt->fetchAll();
} catch (\Throwable $e) {
    // Sin datos de fotos
}

// ── Cargar plan actual ────────────────────────────────────────
$planActual = get_last_plan($clientId, 'entrenamiento');

// ── Construir contexto de datos ───────────────────────────────
$profileText = build_client_profile_text($client);

$metricasText = "HISTORIAL DE MÉTRICAS:\n";
if (empty($metricas)) {
    $metricasText .= "Sin registros de métricas aún.\n";
} else {
    foreach ($metricas as $m) {
        $metricasText .= "- [{$m['fecha']}] Peso: {$m['peso']}kg";
        if ($m['grasa_corporal']) $metricasText .= " | Grasa: {$m['grasa_corporal']}%";
        if ($m['masa_muscular'])  $metricasText .= " | Músculo: {$m['masa_muscular']}kg";
        if ($m['notas'])          $metricasText .= " | Nota: {$m['notas']}";
        $metricasText .= "\n";
    }
}

$checkinsText = "CHECK-INS RECIENTES:\n";
if (empty($checkins)) {
    $checkinsText .= "Sin check-ins registrados.\n";
} else {
    foreach ($checkins as $c) {
        $checkinsText .= "- [{$c['fecha']}] Energía: {$c['energia']}/10 | Sueño: {$c['sueno']}h";
        if ($c['adherencia_entreno'])  $checkinsText .= " | Entreno: {$c['adherencia_entreno']}%";
        if ($c['adherencia_nutricion']) $checkinsText .= " | Nutrición: {$c['adherencia_nutricion']}%";
        if ($c['notas'])               $checkinsText .= " | {$c['notas']}";
        $checkinsText .= "\n";
    }
}

$fotosText = "FOTOS DE PROGRESO:\n";
if (empty($fotos)) {
    $fotosText .= "Sin fotos de progreso registradas. Recomendar al cliente subir fotos para mejor seguimiento visual.\n";
} else {
    $fotosByDate = [];
    foreach ($fotos as $f) {
        $fotosByDate[$f['photo_date']][] = $f['tipo'];
    }
    foreach ($fotosByDate as $date => $tipos) {
        $fotosText .= "- [$date] Ángulos: " . implode(', ', $tipos) . "\n";
    }
    $fotosText .= "Total sesiones fotográficas: " . count($fotosByDate) . " | Total fotos: " . count($fotos) . "\n";
    $fotosText .= "La frecuencia de fotos indica el nivel de compromiso del cliente con el seguimiento visual.\n";
}

$planText = "PLAN ACTUAL:\n";
if ($planActual && $planActual['content']) {
    $planText .= "Objetivo: " . ($planActual['content']['objetivo_principal'] ?? 'N/A') . "\n";
    $planText .= "Días/semana: " . ($planActual['content']['dias_por_semana'] ?? '?') . "\n";
    $planText .= "Semanas en el plan: " . ($planActual['content']['semanas'] ?? 4) . "\n";
    $planText .= "Fecha inicio: " . ($planActual['created_at'] ?? 'desconocida') . "\n";
} else {
    $planText .= "Sin plan asignado aún.\n";
}

// ── System Prompt ─────────────────────────────────────────────
$systemPrompt = <<<'SYSTEM'
Eres un analista de rendimiento y composición corporal para WellCore Fitness.
Tu función es interpretar los datos objetivos del cliente y generar un análisis accionable.

DETECTAR Y REPORTAR:
1. Tendencia de peso: bajando/subiendo/estancado (calcular velocidad semanal)
2. Adherencia: al entrenamiento y la nutrición — patrón de las últimas semanas
3. Recuperación: calidad del sueño y energía (indicadores de sobreentrenamiento)
4. Stagnation detection: sin cambio significativo >2 semanas → requiere ajuste
5. Pérdida de músculo: bajada de peso con masa muscular en descenso → peligro
6. Señales de alarma: energía <5/10 persistente, sueño <6h, adherencia <60%

SEMÁFORO DE ESTADO:
- VERDE: progreso según objetivo, buena adherencia, recuperación adecuada
- AMARILLO: progreso lento o adherencia irregular, ajustes menores recomendados
- ROJO: estancamiento >2 semanas, pérdida de músculo, señales de sobreentrenamiento

RECOMENDACIONES CONCRETAS:
- Ajuste calórico específico (±X kcal)
- Ajuste de volumen de entrenamiento (+ / - series)
- Cambio de estructura del programa si aplica
- Acción de urgencia si hay señal roja

FORMATO: JSON estricto. Sin texto fuera del JSON.
SYSTEM;

$userPrompt  = "Analiza el progreso del siguiente cliente y genera un informe completo.\n\n";
$userPrompt .= $profileText . "\n";
$userPrompt .= $metricasText . "\n";
$userPrompt .= $checkinsText . "\n";
$userPrompt .= $fotosText . "\n";
$userPrompt .= $planText . "\n";

$jsonSchema = json_encode([
    'fecha_analisis'   => date('Y-m-d'),
    'semaforo'         => 'verde|amarillo|rojo',
    'resumen_ejecutivo' => 'Qué está pasando con este cliente en 2-3 líneas.',
    'analisis_metricas' => [
        'tendencia_peso'       => 'bajando 0.4kg/semana — en rango óptimo para pérdida grasa',
        'velocidad_semanal_kg' => -0.4,
        'semanas_estancado'    => 0,
        'riesgo_perdida_musculo' => false,
    ],
    'analisis_adherencia' => [
        'entreno_promedio'   => '85%',
        'nutricion_promedio' => '72%',
        'patron'             => 'Buena adherencia entre semana, baja los fines de semana',
    ],
    'analisis_recuperacion' => [
        'sueno_promedio'    => '6.8h',
        'energia_promedio'  => '6.8/10',
        'señales_alarma'    => [],
    ],
    'recomendaciones' => [
        [
            'prioridad'  => 'alta|media|baja',
            'area'       => 'Nutrición / Entrenamiento / Recuperación / Mental',
            'accion'     => 'Descripción específica de la acción a tomar',
            'razon'      => 'Por qué esta acción ahora',
        ],
    ],
    'ajustes_programa' => [
        'calorias_ajuste'  => 0,
        'volumen_ajuste'   => 'sin cambio',
        'cambiar_programa' => false,
        'detalle'          => 'Continuar con el programa actual. Revisar en 2 semanas.',
    ],
    'proxima_revision' => '2 semanas',
    'notas_coach'      => 'Puntos específicos que el coach debe discutir con el cliente.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$userPrompt .= "\n\nESTRUCTURA EXACTA DE RESPUESTA (JSON):\n$jsonSchema";
$userPrompt .= "\n\nBasar el semáforo y recomendaciones en los datos reales disponibles. Si hay pocos datos, indicarlo y ser conservador en las recomendaciones.";

// ── Registrar y llamar ────────────────────────────────────────
$genId = ai_save_generation([
    'client_id' => $clientId,
    'type'      => 'analisis',
    'status'    => 'pending',
]);

try {
    $result   = claude_call($systemPrompt, $userPrompt);
    $parsed   = extract_json_from_response($result['text']);
    $cost     = ai_calc_cost($result['input_tokens'], $result['output_tokens']);
    $semaforo = $parsed['semaforo'] ?? 'amarillo';

    ai_update_generation(
        $genId, 'completed',
        $result['text'],
        $parsed ? json_encode($parsed, JSON_UNESCAPED_UNICODE) : null
    );

    respond([
        'ok'            => true,
        'generation_id' => $genId,
        'client'        => ['id' => $clientId, 'name' => $client['name']],
        'semaforo'      => $semaforo,
        'analisis'      => $parsed,
        'datos_usados'  => [
            'metricas_count' => count($metricas),
            'checkins_count' => count($checkins),
            'fotos_count'    => count($fotos),
            'tiene_plan'     => $planActual !== null,
        ],
        'tokens'        => [
            'input'     => $result['input_tokens'],
            'output'    => $result['output_tokens'],
            'costo_usd' => $cost,
        ],
    ]);

} catch (\Exception $e) {
    ai_update_generation($genId, 'failed', $e->getMessage());
    error_log('[WellCore AI] analyze-progress error: ' . $e->getMessage());
    respondError('Error analizando progreso. Intenta de nuevo.', 500);
}
