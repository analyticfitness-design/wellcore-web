<?php
/**
 * WellCore Fitness — AI Helpers
 * ============================================================
 * Utilidades compartidas para todos los módulos de IA.
 * Incluir este archivo desde cada endpoint de api/ai/.
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai.php';
require_once __DIR__ . '/../includes/logger.php';

// Auto-log todas las peticiones AI
if (!isset($GLOBALS['__wc_log'])) {
    logStart();
}

// ──────────────────────────────────────────────────────────────
// CLAUDE API CALL
// ──────────────────────────────────────────────────────────────

/**
 * Llama a la API de Claude y devuelve el texto de respuesta.
 *
 * @param  string  $systemPrompt  Instrucciones de sistema
 * @param  string  $userPrompt    Mensaje del usuario
 * @return array   ['text', 'input_tokens', 'output_tokens']
 * @throws RuntimeException si falla la llamada
 */
function claude_call(string $systemPrompt, string $userPrompt, ?string $model = null, int $maxTokens = 0): array {
    if (!AI_ENABLED) {
        throw new \RuntimeException('AI deshabilitada en configuración.');
    }
    if (CLAUDE_API_KEY === 'sk-ant-REPLACE_WITH_YOUR_KEY') {
        throw new \RuntimeException('API key de Claude no configurada. Edita api/config/ai.php');
    }

    $payload = json_encode([
        'model'      => $model ?: CLAUDE_MODEL,
        'max_tokens' => $maxTokens ?: CLAUDE_MAX_TOKENS,
        'system'     => $systemPrompt,
        'messages'   => [
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $headers = implode("\r\n", [
        'Content-Type: application/json',
        'anthropic-version: ' . CLAUDE_API_VERSION,
        'x-api-key: ' . CLAUDE_API_KEY,
    ]);

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => $headers,
            'content'       => $payload,
            'timeout'       => 600,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $url      = CLAUDE_BASE_URL . '/v1/messages';
    $raw      = @file_get_contents($url, false, $context);
    $httpMeta = $http_response_header ?? [];

    // Extraer código HTTP desde los headers
    $httpCode = 0;
    foreach ($httpMeta as $h) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) {
            $httpCode = (int) $m[1];
        }
    }

    if ($raw === false) {
        throw new \RuntimeException('No se pudo conectar con la API de Claude. Verifica la conexión del servidor.');
    }

    $data = json_decode($raw, true);

    if ($httpCode !== 200 || empty($data['content'][0]['text'])) {
        $msg = $data['error']['message'] ?? substr($raw, 0, 200);
        throw new \RuntimeException("Claude API error ($httpCode): $msg");
    }

    return [
        'text'          => $data['content'][0]['text'],
        'input_tokens'  => (int) ($data['usage']['input_tokens']  ?? 0),
        'output_tokens' => (int) ($data['usage']['output_tokens'] ?? 0),
    ];
}

/**
 * Llama a Claude Vision API con una imagen base64.
 *
 * @param  string  $systemPrompt  Instrucciones de sistema
 * @param  string  $userPrompt    Texto del usuario
 * @param  string  $imageBase64   Imagen en base64 (sin prefijo data:)
 * @param  string  $mediaType     image/jpeg, image/png, image/webp
 * @return array   ['text', 'input_tokens', 'output_tokens']
 */
function claude_call_vision(string $systemPrompt, string $userPrompt, string $imageBase64, string $mediaType = 'image/jpeg'): array {
    if (!AI_ENABLED) {
        throw new \RuntimeException('AI deshabilitada en configuracion.');
    }
    if (CLAUDE_API_KEY === 'sk-ant-REPLACE_WITH_YOUR_KEY') {
        throw new \RuntimeException('API key de Claude no configurada. Edita api/config/ai.php');
    }

    $payload = json_encode([
        'model'      => CLAUDE_MODEL,
        'max_tokens' => CLAUDE_MAX_TOKENS,
        'system'     => $systemPrompt,
        'messages'   => [
            [
                'role'    => 'user',
                'content' => [
                    [
                        'type'   => 'image',
                        'source' => [
                            'type'       => 'base64',
                            'media_type' => $mediaType,
                            'data'       => $imageBase64,
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => $userPrompt,
                    ],
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(CLAUDE_BASE_URL . '/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'anthropic-version: ' . CLAUDE_API_VERSION,
            'x-api-key: ' . CLAUDE_API_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $raw      = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new \RuntimeException("No se pudo conectar con Claude API: $curlErr");
    }

    $data = json_decode($raw, true);

    if ($httpCode !== 200 || empty($data['content'][0]['text'])) {
        $msg = $data['error']['message'] ?? substr($raw, 0, 300);
        throw new \RuntimeException("Claude Vision error ($httpCode): $msg");
    }

    return [
        'text'          => $data['content'][0]['text'],
        'input_tokens'  => (int) ($data['usage']['input_tokens']  ?? 0),
        'output_tokens' => (int) ($data['usage']['output_tokens'] ?? 0),
    ];
}

// ──────────────────────────────────────────────────────────────
// GENERACIONES — DB
// ──────────────────────────────────────────────────────────────

/**
 * Guarda una generación en la tabla ai_generations. Devuelve el ID.
 */
function ai_save_generation(array $p): int {
    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO ai_generations
            (client_id, type, ticket_id, prompt_tokens, completion_tokens,
             model, status, raw_response, parsed_json, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $p['client_id']         ?? null,
        $p['type'],
        $p['ticket_id']         ?? null,
        $p['prompt_tokens']     ?? 0,
        $p['completion_tokens'] ?? 0,
        CLAUDE_MODEL,
        $p['status']            ?? 'pending',
        $p['raw_response']      ?? null,
        $p['parsed_json']       ?? null,
    ]);
    return (int) $db->lastInsertId();
}

/**
 * Actualiza estado y respuesta de una generación.
 */
function ai_update_generation(int $id, string $status, ?string $raw = null, ?string $parsed = null): void {
    getDB()->prepare("
        UPDATE ai_generations SET status = ?, raw_response = ?, parsed_json = ? WHERE id = ?
    ")->execute([$status, $raw, $parsed, $id]);
}

/**
 * Calcula costo estimado en USD.
 */
function ai_calc_cost(int $inputTokens, int $outputTokens): float {
    return round(
        ($inputTokens  / 1_000_000 * AI_COST_INPUT_PER_MILLION) +
        ($outputTokens / 1_000_000 * AI_COST_OUTPUT_PER_MILLION),
        6
    );
}

// ──────────────────────────────────────────────────────────────
// PERFIL DE CLIENTE
// ──────────────────────────────────────────────────────────────

/**
 * Devuelve perfil completo del cliente para construir prompts.
 * @throws RuntimeException si no existe
 */
function get_client_for_ai(int $clientId): array {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT
            c.id, c.client_code, c.name, c.email, c.plan, c.status, c.fecha_inicio,
            p.edad, p.peso, p.altura, p.objetivo, p.ciudad, p.whatsapp,
            p.nivel, p.lugar_entreno, p.dias_disponibles, p.restricciones,
            p.macros
        FROM clients c
        LEFT JOIN client_profiles p ON p.client_id = c.id
        WHERE c.id = ?
    ");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();

    if (!$client) {
        throw new \RuntimeException("Cliente ID $clientId no encontrado.");
    }

    $client['dias_disponibles'] = json_decode($client['dias_disponibles'] ?? '[]', true) ?: [];
    $client['macros']           = json_decode($client['macros']           ?? 'null', true);

    return $client;
}

/**
 * Obtiene el último plan asignado de un tipo para un cliente.
 */
function get_last_plan(int $clientId, string $type): ?array {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT id, content, created_at FROM assigned_plans
        WHERE client_id = ? AND plan_type = ?
        ORDER BY version DESC, created_at DESC LIMIT 1
    ");
    $stmt->execute([$clientId, $type]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['content'] = json_decode($row['content'] ?? 'null', true);
    return $row;
}

// ──────────────────────────────────────────────────────────────
// PROMPTS EDITABLES DESDE ADMIN
// ──────────────────────────────────────────────────────────────

/**
 * Prompt personalizado desde DB, o null si no existe.
 */
function get_ai_prompt(string $type): array {
    try {
        $stmt = getDB()->prepare("SELECT system_prompt, user_prompt_template FROM ai_prompts WHERE type = ?");
        $stmt->execute([$type]);
        $row = $stmt->fetch();
        if ($row) return $row;
    } catch (\Throwable $e) {
        // Tabla no existe aún
    }
    return ['system_prompt' => null, 'user_prompt_template' => null];
}

// ──────────────────────────────────────────────────────────────
// UTILIDADES DE PARSEO
// ──────────────────────────────────────────────────────────────

/**
 * Extrae el primer JSON válido de un texto (maneja bloques ```json```).
 */
function extract_json_from_response(string $text): ?array {
    if (preg_match('/```(?:json)?\s*(\{[\s\S]+?\})\s*```/s', $text, $m)) {
        $data = json_decode($m[1], true);
        if ($data !== null) return $data;
    }
    if (preg_match('/(\{[\s\S]+\})/s', $text, $m)) {
        $data = json_decode($m[1], true);
        if ($data !== null) return $data;
    }
    return null;
}

/**
 * Rate limit: máx AI_RATE_LIMIT_PER_HOUR generaciones activas por hora.
 */
function ai_check_rate_limit(): bool {
    try {
        $stmt = getDB()->prepare("
            SELECT COUNT(*) FROM ai_generations
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
              AND status NOT IN ('failed', 'queued')
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn() < AI_RATE_LIMIT_PER_HOUR;
    } catch (\Throwable $e) {
        return true;
    }
}

/**
 * Guarda un plan generado por IA en assigned_plans con active=0 (pendiente de revisión).
 * Usa el schema real: plan_type, active, version, valid_from, assigned_by.
 * Retorna el ID del plan insertado (o 0 si falla).
 */
function ai_save_plan(int $clientId, string $type, array $content, int $genId): int {
    $db   = getDB();
    $json = json_encode($content, JSON_UNESCAPED_UNICODE);

    // Calcular siguiente versión
    $verStmt = $db->prepare("SELECT COALESCE(MAX(version),0) FROM assigned_plans WHERE client_id = ? AND plan_type = ?");
    $verStmt->execute([$clientId, $type]);
    $version = (int) $verStmt->fetchColumn() + 1;

    try {
        $db->prepare("
            INSERT INTO assigned_plans
                (client_id, plan_type, content, version, ai_generation_id, active, valid_from)
            VALUES (?, ?, ?, ?, ?, 0, CURDATE())
        ")->execute([$clientId, $type, $json, $version, $genId]);
        return (int) $db->lastInsertId();
    } catch (\Throwable $e) {
        // Sin columna ai_generation_id — intentar sin ella
        try {
            $db->prepare("
                INSERT INTO assigned_plans
                    (client_id, plan_type, content, version, active, valid_from)
                VALUES (?, ?, ?, ?, 0, CURDATE())
            ")->execute([$clientId, $type, $json, $version]);
            return (int) $db->lastInsertId();
        } catch (\Throwable $e2) {
            error_log('[WellCore AI] assigned_plans insert error: ' . $e2->getMessage());
            return 0;
        }
    }
}

/**
 * Construye prompt enriquecido para planes RISE usando TODOS los datos del intake.
 * Formato texto estructurado (más eficiente que raw JSON para Haiku).
 */
function build_rise_enriched_prompt(array $c, ?array $intake): string {
    $text  = "CLIENTE RISE — DATOS COMPLETOS:\n";
    $text .= "Nombre: " . ($c['name'] ?: '?') . "\n";
    $text .= "Edad: " . ($c['edad'] ?: '?') . " | Peso: " . ($c['peso'] ?: '?') . "kg | Altura: " . ($c['altura'] ?: '?') . "cm\n";
    if ($c['gender'] ?? null) $text .= "Género: " . $c['gender'] . "\n";
    $text .= "Objetivo: " . ($c['objetivo'] ?: 'mejorar composición corporal') . "\n";
    $text .= "Nivel experiencia: " . ($c['nivel'] ?: 'intermedio') . "\n";
    $text .= "Restricciones físicas: " . ($c['restricciones'] ?: 'ninguna') . "\n";

    if (!$intake) {
        $text .= "\n[Sin datos de intake disponibles — usar perfil base]\n";
        return $text;
    }

    // Mediciones
    $m = $intake['measurements'] ?? [];
    if (array_filter($m)) {
        $text .= "\nMEDIDAS CORPORALES:\n";
        $labels = ['waist' => 'Cintura', 'hips' => 'Cadera', 'chest' => 'Pecho', 'arms' => 'Brazos', 'thighs' => 'Muslos', 'bodyFat' => '% Grasa estimado'];
        foreach ($labels as $key => $label) {
            if (!empty($m[$key])) $text .= "- $label: {$m[$key]}\n";
        }
    }

    // Entrenamiento
    $t = $intake['training'] ?? [];
    if ($t) {
        $text .= "\nHISTORIAL DE ENTRENAMIENTO:\n";
        $text .= "- Años de experiencia: " . ($t['years'] ?? '?') . "\n";
        $types = $t['trainingType'] ?? [];
        if ($types) $text .= "- Tipo de entrenamiento actual: " . (is_array($types) ? implode(', ', $types) : $types) . "\n";
        $avoid = $t['exercisesToAvoid'] ?? [];
        if ($avoid) $text .= "- ⚠️ EVITAR: " . (is_array($avoid) ? implode(', ', $avoid) : $avoid) . "\n";
    }

    // Disponibilidad
    $a = $intake['availability'] ?? [];
    if ($a) {
        $text .= "\nDISPONIBILIDAD:\n";
        $text .= "- Lugar de entrenamiento: " . ($a['place'] ?? '?') . "\n";
        $days = $a['days'] ?? [];
        if ($days) $text .= "- Días disponibles: " . (is_array($days) ? implode(', ', $days) : $days) . " (" . (is_array($days) ? count($days) : '?') . " días/semana)\n";
        if (!empty($a['time'])) $text .= "- Tiempo por sesión: " . $a['time'] . " minutos\n";
        $eq = $a['equipment'] ?? [];
        if ($eq) $text .= "- Equipo disponible: " . (is_array($eq) ? implode(', ', $eq) : $eq) . "\n";
    }

    // Nutrición
    $n = $intake['nutrition'] ?? [];
    if ($n) {
        $text .= "\nNUTRICIÓN:\n";
        $text .= "- Tipo de dieta: " . ($n['dietType'] ?? 'sin restricción') . "\n";
        $allergies = $n['allergies'] ?? [];
        if ($allergies) $text .= "- ⚠️ Alergias/intolerancias: " . (is_array($allergies) ? implode(', ', $allergies) : $allergies) . "\n";
        $supps = $n['supplements'] ?? [];
        if ($supps) $text .= "- Suplementos actuales: " . (is_array($supps) ? implode(', ', $supps) : $supps) . "\n";
    }

    // Estilo de vida
    $l = $intake['lifestyle'] ?? [];
    if ($l) {
        $text .= "\nESTILO DE VIDA:\n";
        if (!empty($l['sleep']))    $text .= "- Sueño: " . $l['sleep'] . "h/noche\n";
        if (!empty($l['activity'])) $text .= "- Actividad diaria: " . $l['activity'] . "\n";
        if (!empty($l['stress']))   $text .= "- Nivel de estrés: " . $l['stress'] . "/10\n";
    }

    // Motivación y metas
    $mv = $intake['motivation'] ?? [];
    if ($mv) {
        $text .= "\nMETAS Y MOTIVACIÓN:\n";
        $goals = $mv['goals'] ?? ($mv['motivation'] ?? []);
        if ($goals) $text .= "- Metas declaradas: " . (is_array($goals) ? implode(', ', $goals) : $goals) . "\n";
        if (!empty($mv['expectedResult'])) $text .= "- Resultado esperado en 30 días: " . $mv['expectedResult'] . "\n";
        if (!empty($mv['commitment']))     $text .= "- Nivel de compromiso: " . $mv['commitment'] . "/10\n";
    }

    // Goals (formato alternativo)
    $g = $intake['goals'] ?? [];
    if ($g && !$mv) {
        $text .= "\nMETAS:\n";
        if (!empty($g['primary']))   $text .= "- Objetivo principal: " . $g['primary'] . "\n";
        if (!empty($g['secondary'])) $text .= "- Objetivo secundario: " . $g['secondary'] . "\n";
    }

    // Instrucciones del coach (override directo)
    if (!empty($intake['coach_instructions'])) {
        $text .= "\n⚠️ INSTRUCCIONES OBLIGATORIAS DEL COACH (seguir al pie de la letra):\n";
        $text .= $intake['coach_instructions'] . "\n";
    }

    return $text;
}

/**
 * Construye un texto-resumen del perfil de un cliente para incluir en prompts.
 */
function build_client_profile_text(array $c): string {
    $dias = is_array($c['dias_disponibles']) && count($c['dias_disponibles'])
        ? count($c['dias_disponibles']) . ' días (' . implode(', ', $c['dias_disponibles']) . ')'
        : ($c['dias_disponibles'] ?: '3-4 días/semana');

    $text  = "PERFIL DEL CLIENTE:\n";
    $text .= "- Nombre:       " . ($c['name']         ?: 'No especificado') . "\n";
    $text .= "- Edad:         " . ($c['edad']         ?: '?') . " años\n";
    $text .= "- Peso:         " . ($c['peso']         ?: '?') . " kg\n";
    $text .= "- Altura:       " . ($c['altura']       ?: '?') . " cm\n";
    $text .= "- Objetivo:     " . ($c['objetivo']     ?: 'Mejorar composición corporal') . "\n";
    $text .= "- Nivel:        " . ($c['nivel']        ?: 'Intermedio') . "\n";
    $text .= "- Plan:         " . strtoupper($c['plan'] ?: 'esencial') . "\n";
    $text .= "- Lugar:        " . ($c['lugar_entreno']?: 'Gimnasio completo') . "\n";
    $text .= "- Días disp.:   $dias\n";
    $text .= "- Restricciones:" . ($c['restricciones'] ?: 'Ninguna conocida') . "\n";
    $text .= "- Dieta actual: " . (($c['dieta']    ?? '') ?: 'No especificada') . "\n";
    $text .= "- Alergias:     " . (($c['alergias'] ?? '') ?: 'Ninguna') . "\n";
    if ($c['macros']) {
        $m     = $c['macros'];
        $text .= "- Macros actuales: Proteína " . ($m['protein'] ?? '?') . "g"
               . " | Carbs " . ($m['carbs'] ?? '?') . "g"
               . " | Grasas " . ($m['fats'] ?? '?') . "g\n";
    }
    if ($c['notas'] ?? null) {
        $text .= "- Notas adicionales: " . $c['notas'] . "\n";
    }
    return $text;
}
