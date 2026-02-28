<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Analisis Nutricional con IA
 * ============================================================
 * POST /api/nutrition/analyze
 *
 * Auth:  Bearer token de cliente
 * Body:  { image_base64?: string, description?: string, meal_type?: string }
 *
 * Ruta de IA (en orden de prioridad):
 *   1. Claude Vision directo (si hay imagen + API key configurada)
 *   2. Router local con Vision (si hay imagen + Router activo)
 *   3. Claude text (si solo hay descripcion + API key)
 *   4. Router local text (si solo hay descripcion + Router activo)
 * ============================================================
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai.php';

requireMethod('POST');
$client = authenticateClient();
$body   = getJsonBody();

$imageBase64 = $body['image_base64'] ?? '';
$mealType    = $body['meal_type'] ?? null;
$description = trim($body['description'] ?? '');

if (!$imageBase64 && !$description) {
    respondError('Se requiere image_base64 o description', 422);
}

$validMeals = ['desayuno', 'almuerzo', 'cena', 'snack', 'pre_entreno', 'post_entreno'];
if ($mealType && !in_array($mealType, $validMeals, true)) {
    $mealType = null;
}

$db = getDB();

// ─── SYSTEM PROMPT (experto en estimacion visual) ─────────────────────────

$systemPrompt = <<<'SYSTEM'
Eres un nutricionista deportivo certificado con 15 anos de experiencia en estimacion visual de porciones y composicion de alimentos. Trabajas para WellCore Fitness.

TU METODOLOGIA DE ESTIMACION VISUAL:

1. IDENTIFICACION: Nombra cada alimento visible con precision (no "carne" → "pechuga de pollo a la plancha")

2. ESTIMACION DE GRAMOS — USA ESTAS REFERENCIAS VISUALES:
   Proteinas:
   - Palma de la mano = ~100g de carne/pollo/pescado cocido
   - Pechuga completa en plato = ~180-220g
   - Filete mediano = ~150g
   - 1 huevo entero = 50g
   - Porcion visible de atun = ~80g

   Carbohidratos:
   - Puno cerrado de arroz = ~150g cocido
   - Porcion tipica latina de arroz en plato = ~180-220g cocido
   - 1 arepa mediana = ~120g
   - 1 papa mediana = ~150g
   - 1 platano maduro = ~120g
   - Porcion de pasta = ~180g cocida

   Vegetales y ensalada:
   - Ensalada de acompanamiento = ~80-120g
   - Porcion de frijoles/lentejas = ~120g cocidas
   - Medio aguacate = ~75g

   Grasas y salsas:
   - 1 cucharada aceite visible = ~14g
   - Aguacate medio = ~75g (10g grasa)
   - Queso rallado = ~20-30g

3. MACROS POR ALIMENTO — VALORES POR 100g (USDA):
   - Pechuga pollo cocida: 31g prot, 0g carb, 3.6g grasa, 165 kcal
   - Arroz blanco cocido: 2.7g prot, 28g carb, 0.3g grasa, 130 kcal
   - Carne res magra cocida: 26g prot, 0g carb, 8g grasa, 176 kcal
   - Huevo entero: 13g prot, 1.1g carb, 11g grasa, 155 kcal
   - Frijol rojo cocido: 8.7g prot, 22.8g carb, 0.5g grasa, 127 kcal
   - Platano maduro frito: 1.3g prot, 35g carb, 7g grasa, 204 kcal
   - Aguacate: 2g prot, 8.5g carb, 15g grasa, 160 kcal
   - Papa cocida: 2g prot, 17g carb, 0.1g grasa, 77 kcal

4. CALCULO: gramos_estimados * (valor_por_100g / 100) = macros del alimento

5. TOTALES: Suma aritmetica EXACTA de todos los alimentos individuales

6. CONFIANZA:
   - "alta": alimentos claramente visibles, cantidades estimables
   - "media": algunos alimentos parcialmente ocultos o con salsas
   - "baja": foto borrosa, plato mixto dificil de separar, o solo descripcion sin foto

RESPONDE UNICAMENTE CON JSON VALIDO. Sin texto antes ni despues del JSON.
SYSTEM;

// ─── USER PROMPT ──────────────────────────────────────────────────────────

$userPrompt = "Analiza esta comida y devuelve el desglose nutricional.\n\n";

if ($description) {
    $userPrompt .= "El usuario describe su comida como: \"{$description}\"\n\n";
}

if ($imageBase64) {
    $userPrompt .= "IMPORTANTE: Observa la foto con atencion. Estima el tamano real de cada porcion "
        . "comparando con el plato, cubiertos y otros objetos visibles. "
        . "No sobreestimes ni subestimes — se preciso.\n\n";
}

$userPrompt .= "Responde en este formato JSON exacto:\n"
    . "{\n"
    . "  \"alimentos\": [\n"
    . "    {\n"
    . "      \"nombre\": \"Nombre especifico del alimento\",\n"
    . "      \"gramos\": 200,\n"
    . "      \"calorias\": 330,\n"
    . "      \"proteina_g\": 62.0,\n"
    . "      \"carbohidratos_g\": 0,\n"
    . "      \"grasa_g\": 7.2\n"
    . "    }\n"
    . "  ],\n"
    . "  \"totales\": {\n"
    . "    \"calorias\": 536,\n"
    . "    \"proteina_g\": 66.3,\n"
    . "    \"carbohidratos_g\": 44.5,\n"
    . "    \"grasa_g\": 7.6\n"
    . "  },\n"
    . "  \"fibra_g\": 3.2,\n"
    . "  \"confianza\": \"alta\",\n"
    . "  \"notas\": \"Observacion breve sobre la comida\"\n"
    . "}";

// ─── LLAMADA A LA IA ──────────────────────────────────────────────────────

$route   = 'unknown';
$content = '';

try {
    $hasApiKey = defined('CLAUDE_API_KEY') && CLAUDE_API_KEY !== 'sk-ant-REPLACE_WITH_YOUR_KEY';

    if ($imageBase64 && $hasApiKey) {
        // RUTA 1: Claude Vision directo (la imagen se envia de verdad)
        require_once __DIR__ . '/../ai/helpers.php';

        // Detectar media type
        $mediaType = 'image/jpeg';
        $first4 = substr($imageBase64, 0, 4);
        if (str_starts_with($first4, 'iVBO')) $mediaType = 'image/png';
        elseif (str_starts_with($first4, 'UklG')) $mediaType = 'image/webp';

        $result  = claude_call_vision($systemPrompt, $userPrompt, $imageBase64, $mediaType);
        $content = $result['text'];
        $route   = 'claude_vision_direct';

    } elseif ($imageBase64) {
        // RUTA 2: Router local con imagen
        require_once __DIR__ . '/../includes/ai-client.php';
        $ai      = new WellCoreAI();
        $result  = $ai->analyzeImage($imageBase64, $systemPrompt . "\n\n" . $userPrompt);
        $content = $result['content'] ?? '';
        $route   = $result['route'] ?? 'router_vision';

    } elseif ($hasApiKey) {
        // RUTA 3: Claude text directo (solo descripcion)
        require_once __DIR__ . '/../ai/helpers.php';
        $result  = claude_call($systemPrompt, $userPrompt);
        $content = $result['text'];
        $route   = 'claude_text_direct';

    } else {
        // RUTA 4: Router local text (solo descripcion)
        require_once __DIR__ . '/../includes/ai-client.php';
        $ai      = new WellCoreAI();
        $result  = $ai->chatLocal($userPrompt, $systemPrompt);
        $content = $result['content'] ?? '';
        $route   = $result['route'] ?? 'router_local';
    }

    // Parsear JSON de la respuesta
    $parsed = null;
    if (preg_match('/\{[\s\S]+\}/s', $content, $m)) {
        $parsed = json_decode($m[0], true);
    }

    if (!$parsed || !isset($parsed['alimentos'])) {
        respondError('La IA no devolvio un analisis valido. Intenta con una foto mas clara o agrega descripcion.', 422);
    }

    // Validar y recalcular totales si no cuadran
    $calcTotals = ['calorias' => 0, 'proteina_g' => 0, 'carbohidratos_g' => 0, 'grasa_g' => 0];
    foreach ($parsed['alimentos'] as &$item) {
        if (!is_array($item)) continue;
        $calcTotals['calorias']        += (float) ($item['calorias'] ?? 0);
        $calcTotals['proteina_g']      += (float) ($item['proteina_g'] ?? 0);
        $calcTotals['carbohidratos_g'] += (float) ($item['carbohidratos_g'] ?? 0);
        $calcTotals['grasa_g']         += (float) ($item['grasa_g'] ?? 0);
    }
    unset($item);

    // Usar totales calculados si la IA no los dio o si difieren mucho
    $aiTotals = $parsed['totales'] ?? [];
    $aiCal    = (float) ($aiTotals['calorias'] ?? 0);
    if ($aiCal === 0.0 || abs($aiCal - $calcTotals['calorias']) > 50) {
        $parsed['totales'] = [
            'calorias'        => round($calcTotals['calorias']),
            'proteina_g'      => round($calcTotals['proteina_g'], 1),
            'carbohidratos_g' => round($calcTotals['carbohidratos_g'], 1),
            'grasa_g'         => round($calcTotals['grasa_g'], 1),
        ];
    }

    // Guardar imagen si viene
    $imagePath = null;
    if ($imageBase64) {
        $uploadDir    = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . '/../../uploads/';
        $nutritionDir = $uploadDir . 'nutrition/';
        if (!is_dir($nutritionDir)) {
            mkdir($nutritionDir, 0755, true);
        }
        $filename  = $client['client_code'] . '_' . date('Ymd_His') . '.jpg';
        $imagePath = 'nutrition/' . $filename;
        $decoded   = base64_decode($imageBase64);
        if ($decoded && strlen($decoded) <= MAX_PHOTO_SIZE) {
            file_put_contents($nutritionDir . $filename, $decoded);
        }
    }

    // Guardar en DB
    $totales = $parsed['totales'];
    $stmt = $db->prepare("
        INSERT INTO nutrition_logs
            (client_id, image_path, calories, protein, carbs, fat,
             foods, meal_type, confidence, ai_raw, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $client['id'],
        $imagePath,
        $totales['calorias'],
        $totales['proteina_g'],
        $totales['carbohidratos_g'],
        $totales['grasa_g'],
        json_encode($parsed['alimentos'], JSON_UNESCAPED_UNICODE),
        $mealType,
        $parsed['confianza'] ?? 'media',
        $content,
    ]);
    $logId = (int) $db->lastInsertId();

    respond([
        'ok'       => true,
        'log_id'   => $logId,
        'analysis' => $parsed,
        'route'    => $route,
        'message'  => 'Analisis completado',
    ], 201);

} catch (\Throwable $e) {
    error_log('[WellCore] nutrition/analyze error: ' . $e->getMessage());
    respondError('Error analizando comida: ' . $e->getMessage(), 500);
}
