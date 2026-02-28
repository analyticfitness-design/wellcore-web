<?php
/**
 * WellCore Fitness — AI Router Client
 * ============================================================
 * Cliente PHP que habla con el Router inteligente local (FastAPI).
 * El Router decide si usar Ollama (local, gratis) o Claude (cloud, pago).
 *
 * ARQUITECTURA:
 * - Desarrollo local: Router en localhost:8000 (WSL2)
 * - Produccion: Router NO accesible desde Docker.
 *   Usar claude_call() en helpers.php como fallback directo.
 *   Cuando se tenga VPS/servidor dedicado, apuntar AI_ROUTER_URL ahi.
 *
 * Uso:
 *   $ai = new WellCoreAI();
 *   $result = $ai->chat("Cuantas series para hipertrofia?");
 *   echo $result['content'];
 * ============================================================
 */

require_once __DIR__ . '/../config/ai.php';

class WellCoreAI {
    private string $routerUrl;
    private string $difyUrl;
    private string $difyApiKey;
    private int $timeout;
    private bool $routerAvailable;

    public function __construct() {
        $this->routerUrl = defined('AI_ROUTER_URL') ? AI_ROUTER_URL : 'http://localhost:8000';
        $this->difyUrl   = defined('DIFY_URL') ? DIFY_URL : 'http://localhost:3000';
        $this->difyApiKey = defined('DIFY_API_KEY') ? DIFY_API_KEY : '';
        $this->timeout   = defined('AI_ROUTER_TIMEOUT') ? AI_ROUTER_TIMEOUT : 60;
        $this->routerAvailable = $this->checkRouter();
    }

    /**
     * Chat con prioridad: Dify (RAG) -> Router local -> Claude directo.
     */
    public function chat(string $message, string $system = ''): array {
        if ($this->difyApiKey) {
            try {
                return $this->chatDify($message);
            } catch (\Throwable $e) {
                error_log('[WellCore] Dify fallback: ' . $e->getMessage());
            }
        }

        if ($this->routerAvailable) {
            try {
                return $this->post('/v1/chat', [
                    'message' => $message,
                    'system'  => $system,
                ]);
            } catch (\Throwable $e) {
                error_log('[WellCore] Router fallback: ' . $e->getMessage());
            }
        }

        return $this->fallbackClaude($message, $system);
    }

    /**
     * Chat via Dify API (SSE streaming, recoge respuesta completa).
     */
    private function chatDify(string $message): array {
        $payload = json_encode([
            'inputs'         => new \stdClass(),
            'query'          => $message,
            'response_mode'  => 'streaming',
            'user'           => 'wellcore-web-widget',
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->difyUrl . '/v1/chat-messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->difyApiKey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("Dify no disponible: $curlErr");
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException("Dify error HTTP $httpCode: " . substr($response, 0, 300));
        }

        $answer = '';
        $tokens = 0;
        foreach (explode("\n", $response) as $line) {
            if (strpos($line, 'data: ') !== 0) continue;
            $json = json_decode(substr($line, 6), true);
            if (!$json) continue;
            if ($json['event'] === 'workflow_finished' && isset($json['data']['outputs']['answer'])) {
                $answer = $json['data']['outputs']['answer'];
                $tokens = $json['data']['total_tokens'] ?? 0;
                break;
            }
        }

        if (!$answer) {
            throw new \RuntimeException('Dify respuesta vacia');
        }

        return [
            'content'     => $answer,
            'model'       => 'dify-ollama',
            'route'       => 'dify_rag',
            'tokens_used' => $tokens,
        ];
    }

    /**
     * Forzar ruta local (Ollama). Gratis, rapido, para FAQ y clasificacion.
     */
    public function chatLocal(string $message, string $system = ''): array {
        return $this->post('/v1/chat', [
            'message'     => $message,
            'system'      => $system,
            'force_route' => 'local',
        ]);
    }

    /**
     * Forzar ruta cloud (Claude API). Para tareas complejas o criticas.
     */
    public function chatCloud(string $message, string $system = ''): array {
        if (!$this->routerAvailable) {
            return $this->fallbackClaude($message, $system);
        }
        return $this->post('/v1/chat', [
            'message'     => $message,
            'system'      => $system,
            'force_route' => 'cloud',
        ]);
    }

    /**
     * Analizar imagen: primero LLaVA local (gratis), fallback a Claude Vision.
     */
    public function analyzeImage(string $imageBase64, string $prompt): array {
        if ($this->routerAvailable) {
            try {
                return $this->post('/v1/vision', [
                    'image_base64' => $imageBase64,
                    'prompt'       => $prompt,
                ]);
            } catch (\Throwable $e) {
                // LLaVA fallo, intentar via /v1/chat con imagen
                try {
                    return $this->post('/v1/chat', [
                        'message'      => $prompt,
                        'image_base64' => $imageBase64,
                    ]);
                } catch (\Throwable $e2) {
                    // Router completamente caido, fallback directo
                }
            }
        }
        return $this->fallbackClaudeVision($imageBase64, $prompt);
    }

    /**
     * Clasificar una peticion sin generar respuesta completa.
     */
    public function classify(string $message): array {
        return $this->post('/v1/chat', [
            'message'     => "Clasifica esta peticion: $message",
            'force_route' => 'local',
            'model'       => 'wellcore-classifier-v2',
        ]);
    }

    /**
     * Verificar que el Router y Ollama estan activos.
     */
    public function health(): array {
        $ch = curl_init($this->routerUrl . '/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['status' => 'error', 'router' => 'down'];
        }
        return json_decode($response, true) ?: ['status' => 'error'];
    }

    /**
     * Verificacion rapida de conectividad al Router (2s timeout).
     */
    private function checkRouter(): bool {
        $ch = curl_init($this->routerUrl . '/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode === 200;
    }

    /**
     * Fallback vision: Claude Vision directo si LLaVA y Router no estan disponibles.
     */
    private function fallbackClaudeVision(string $imageBase64, string $prompt): array {
        require_once __DIR__ . '/../ai/helpers.php';
        try {
            $system = "Eres un nutricionista experto analizando fotos de comida para WellCore Fitness.";
            $result = claude_call_vision($system, $prompt, $imageBase64);
            return [
                'content'    => $result['text'],
                'model'      => CLAUDE_MODEL,
                'route'      => 'cloud_vision_direct',
                'tokens_used'=> $result['input_tokens'] + $result['output_tokens'],
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Vision no disponible: LLaVA offline y Claude Vision fallback fallo: ' . $e->getMessage());
        }
    }

    /**
     * Fallback: si el Router no esta disponible, llamar a Claude directo.
     * Usa claude_call() de helpers.php que ya existe en el backend.
     */
    private function fallbackClaude(string $message, string $system): array {
        require_once __DIR__ . '/../ai/helpers.php';
        try {
            $result = claude_call($system, $message);
            return [
                'content'  => $result['text'],
                'model'    => CLAUDE_MODEL,
                'route'    => 'cloud_direct',
                'tokens_used' => $result['input_tokens'] + $result['output_tokens'],
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('AI no disponible: Router offline y Claude fallback fallo: ' . $e->getMessage());
        }
    }

    /**
     * Llamada POST al Router.
     *
     * @throws RuntimeException si falla la conexion o el Router responde error
     */
    private function post(string $endpoint, array $data): array {
        $ch = curl_init($this->routerUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("AI Router no disponible: $error");
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException("AI Router error HTTP $httpCode: " . substr($response, 0, 200));
        }

        $result = json_decode($response, true);
        if (!$result || !isset($result['content'])) {
            throw new \RuntimeException('AI Router respuesta invalida');
        }
        return $result;
    }
}
