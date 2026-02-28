<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Analisis de imagenes con LLaVA local
 * ============================================================
 * POST /api/ai/ollama-vision
 *
 * Auth:  Bearer token (cliente o admin)
 * Body:  { image_base64: string, prompt?: string }
 *
 * Flujo: LLaVA local (gratis) -> Claude Vision fallback (si API key existe)
 * ============================================================
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/ai-client.php';

requireMethod('POST');
$body = getJsonBody();

$imageBase64 = $body['image_base64'] ?? '';
if (!$imageBase64) {
    respondError('image_base64 requerido', 422);
}

$prompt = trim($body['prompt'] ?? '');
if (!$prompt) {
    $prompt = <<<'PROMPT'
Analiza esta imagen de comida. Para cada alimento visible:
1. Identifica el alimento con nombre comun en espanol
2. Estima la porcion en gramos
3. Calcula macronutrientes aproximados (proteina, carbohidratos, grasas en gramos)
4. Calcula calorias totales

Prioriza alimentos comunes en Latinoamerica (arepa, frijoles, platano, arroz, pollo, aguacate, etc).

Responde SOLO en JSON con esta estructura:
{
  "alimentos": [
    {
      "nombre": "nombre del alimento",
      "porcion_g": 150,
      "proteina_g": 25,
      "carbohidratos_g": 0,
      "grasas_g": 5,
      "calorias": 145
    }
  ],
  "totales": {
    "calorias": 580,
    "proteina_g": 45,
    "carbohidratos_g": 60,
    "grasas_g": 18
  },
  "observaciones": "Comida balanceada con buena proporcion de proteina"
}
PROMPT;
}

$ai = new WellCoreAI();

try {
    $result = $ai->analyzeImage($imageBase64, $prompt);

    respond([
        'ok'       => true,
        'analysis' => $result['content'],
        'route'    => $result['route'] ?? 'unknown',
        'model'    => $result['model'] ?? 'unknown',
    ]);
} catch (\Throwable $e) {
    respondError('Error analizando imagen: ' . $e->getMessage(), 500);
}
