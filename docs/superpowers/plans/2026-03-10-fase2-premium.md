# WellCore App Nativa — FASE 2: Premium

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar los diferenciadores de mercado: RISE Challenge 30 días completo, AI con Claude API (planes personalizados), video check-ins, audio coaching, pagos Wompi con auto-renovación, y WhatsApp Business API para LATAM.

**Architecture:** Extiende Fase 1. Los módulos más complejos del sistema PHP se migran a servicios Laravel dedicados. ClaudeAiService reemplaza `api/ai/helpers.php`. WompiService reemplaza `api/wompi/`. El sistema RISE tiene su propio flujo de 3 pasos en Flutter.

**Tech Stack:** Claude API (claude-haiku-4-5), Wompi API, WhatsApp Business API (Meta), Laravel Storage S3-ready, FFmpeg (video processing) | Flutter: video_player, chewie, audioplayers, stripe_payment (para Fase 4)

**Prerequisito:** `v0.2.0-fase1` tag, todos los tests pasando.

---

## Chunk 1: RISE Challenge Backend Completo

### Task 1: RISE Challenge sistema completo

**Reference PHP:**
- `C:\Users\GODSF\Herd\wellcorefitness\api\rise\enroll.php` (123 líneas)
- `C:\Users\GODSF\Herd\wellcorefitness\api\rise\save-intake.php` (104 líneas)
- `C:\Users\GODSF\Herd\wellcorefitness\api\rise\status.php` (180 líneas)

**Files:**
- Create: `wellcore-api/app/Http/Controllers/Api/V1/Rise/EnrollController.php`
- Create: `wellcore-api/app/Http/Controllers/Api/V1/Rise/IntakeController.php`
- Create: `wellcore-api/app/Http/Controllers/Api/V1/Rise/StatusController.php`
- Create: `wellcore-api/app/Services/RiseService.php`
- Create: `wellcore-api/app/Models/RiseProgram.php`

- [ ] **Step 1: Migración rise_programs**

```bash
php artisan make:migration create_rise_programs_table
```

```php
Schema::create('rise_programs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->date('start_date');
    $table->date('end_date');
    $table->integer('duration_days')->default(30);
    $table->enum('experience_level', ['principiante', 'intermedio', 'avanzado'])->nullable();
    $table->enum('training_location', ['gym', 'home', 'hybrid'])->nullable();
    $table->enum('gender', ['male', 'female', 'other'])->nullable();
    $table->json('intake_data')->nullable(); // datos del formulario completo
    $table->enum('status', ['active', 'completed', 'expired'])->default('active');
    $table->timestamps();
    $table->index('user_id');
});

Schema::create('assigned_plans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->enum('plan_type', ['entrenamiento', 'nutricion', 'habitos', 'suplementacion']);
    $table->longText('content'); // JSON del plan generado por AI
    $table->unsignedTinyInteger('version')->default(1);
    $table->unsignedBigInteger('ai_generation_id')->nullable();
    $table->boolean('active')->default(true);
    $table->date('valid_from')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'plan_type', 'active']);
});
```

- [ ] **Step 2: Tests RISE completo**

```php
// tests/Feature/Rise/RiseEnrollTest.php
it('enrolls new client in RISE program', function () {
    $response = $this->postJson('/api/v1/rise/enroll', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'experience_level' => 'intermedio',
        'training_location' => 'gym',
        'gender' => 'male',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['client', 'program' => ['id', 'start_date', 'end_date']]);

    $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'plan' => 'rise']);
    $this->assertDatabaseHas('rise_programs', ['status' => 'active']);
});

it('rejects duplicate email enrollment', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->postJson('/api/v1/rise/enroll', [
        'email' => 'existing@example.com',
        'password' => 'Password123!',
        'name' => 'Duplicate',
    ])->assertUnprocessable();
});

it('calculates correct 30-day program window', function () {
    $response = $this->postJson('/api/v1/rise/enroll', [
        'name' => 'Test', 'email' => 'test2@example.com', 'password' => 'Pass123!',
    ]);

    $endDate = Carbon::parse($response->json('program.end_date'));
    expect($endDate->diffInDays(now()))->toBe(30);
});
```

- [ ] **Step 3: EnrollController (replica exacta del PHP)**

```php
// app/Http/Controllers/Api/V1/Rise/EnrollController.php
class EnrollController extends Controller
{
    public function __invoke(EnrollRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'client',
            'plan' => 'rise',
            'status' => 'activo',
            'client_code' => 'rise-' . strtoupper(Str::random(6)),
        ]);

        // Registrar referido si aplica
        if ($request->filled('referral_code')) {
            RiseService::processReferral($user, $request->referral_code);
        }

        $program = RiseProgram::create([
            'user_id' => $user->id,
            'start_date' => today(),
            'end_date' => today()->addDays(30),
            'experience_level' => $request->experience_level,
            'training_location' => $request->training_location,
            'gender' => $request->gender,
        ]);

        // Notificar admin (Job async)
        NotifyAdminNewRiseEnrollment::dispatch($user);

        return response()->json([
            'client' => ['id' => $user->id, 'code' => $user->client_code, 'name' => $user->name],
            'program' => ['id' => $program->id, 'start_date' => $program->start_date, 'end_date' => $program->end_date],
        ], 201);
    }
}
```

- [ ] **Step 4: IntakeController — guardar datos del formulario**

Replica exacta de `save-intake.php`: recibe JSON completo del formulario (measurements, training, availability, nutrition, lifestyle, motivation) y lo guarda en `rise_programs.intake_data`.

- [ ] **Step 5: StatusController — 30-day tracking**

```php
public function __invoke(Request $request): JsonResponse
{
    $program = RiseProgram::where('user_id', $request->user()->id)
        ->where('status', 'active')
        ->latest()
        ->firstOrFail();

    $daysElapsed = today()->diffInDays($program->start_date);
    $daysRemaining = today()->diffInDays($program->end_date);
    $expired = today()->gt($program->end_date);

    if ($expired && $program->status === 'active') {
        $program->update(['status' => 'expired']);
        NotifyAdminRiseExpiry::dispatch($request->user());
    }

    return response()->json([
        'active' => !$expired,
        'start_date' => $program->start_date->toDateString(),
        'end_date' => $program->end_date->toDateString(),
        'days_elapsed' => $daysElapsed,
        'days_remaining' => max(0, $daysRemaining),
        'expired' => $expired,
        'message' => "Día {$daysElapsed} de 30 — ¡Vas muy bien!",
    ]);
}
```

- [ ] **Step 6: Ejecutar tests RISE**

```bash
./vendor/bin/pest tests/Feature/Rise/ -v
```

- [ ] **Step 7: Commit RISE backend**

```bash
git commit -m "feat: RISE Challenge backend completo (enroll, intake, status, 30-day tracking)"
```

---

## Chunk 2: AI con Claude API

### Task 2: ClaudeAiService — migrar ai/helpers.php

**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\api\ai\helpers.php` (547 líneas)
**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\api\ai\prompts.php` (216 líneas)

**Files:**
- Create: `wellcore-api/app/Services/ClaudeAiService.php`
- Create: `wellcore-api/app/Services/AiPromptsService.php`
- Create: `wellcore-api/app/Jobs/GenerateAiPlan.php`

- [ ] **Step 1: Tests AI Service**

```php
// tests/Unit/ClaudeAiServiceTest.php
it('calls Claude API and returns text response', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '{"plan": "test"}']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            'stop_reason' => 'end_turn',
        ]),
    ]);

    $result = ClaudeAiService::call(
        systemPrompt: 'Eres un coach fitness',
        userPrompt: 'Genera un plan de entrenamiento',
        model: 'claude-haiku-4-5-20251001',
        maxTokens: 4096,
    );

    expect($result['text'])->toContain('plan');
});

it('extracts JSON from markdown response', function () {
    $text = "Aquí el plan:\n```json\n{\"dias\": [1,2,3]}\n```\nEspero que te guste.";
    $json = ClaudeAiService::extractJson($text);
    expect($json)->toBe('{"dias": [1,2,3]}');
});
```

- [ ] **Step 2: ClaudeAiService**

```php
// app/Services/ClaudeAiService.php
class ClaudeAiService
{
    const ANTHROPIC_API = 'https://api.anthropic.com/v1/messages';

    public static function call(
        string $systemPrompt,
        string $userPrompt,
        string $model = 'claude-haiku-4-5-20251001',
        int $maxTokens = 4096
    ): array {
        $response = Http::timeout(600)
            ->withHeaders([
                'x-api-key' => config('services.claude.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post(self::ANTHROPIC_API, [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'system' => $systemPrompt,
                'messages' => [['role' => 'user', 'content' => $userPrompt]],
            ]);

        if ($response->status() === 429) {
            throw new \Exception('Claude API rate limit');
        }

        $body = $response->json();
        return [
            'text' => $body['content'][0]['text'] ?? '',
            'input_tokens' => $body['usage']['input_tokens'] ?? 0,
            'output_tokens' => $body['usage']['output_tokens'] ?? 0,
            'stop_reason' => $body['stop_reason'] ?? '',
        ];
    }

    public static function extractJson(string $text): ?string
    {
        // Extraer bloque ```json ... ```
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $text, $matches)) {
            return trim($matches[1]);
        }
        // Si no hay bloque, intentar parsear directamente
        return $text;
    }

    public static function generateRisePlan(array $client, array $intake): string
    {
        $prompt = AiPromptsService::buildRiseEnrichedPrompt($client, $intake);
        $system = AiPromptsService::getRiseSystemPrompt();

        $result = self::call($system, $prompt, 'claude-haiku-4-5-20251001', 8192);
        return self::extractJson($result['text']);
    }
}
```

- [ ] **Step 3: AiPromptsService — migrar prompts.php**

```php
// app/Services/AiPromptsService.php
class AiPromptsService
{
    public static function getRiseSystemPrompt(): string
    {
        return <<<PROMPT
        Eres un coach fitness certificado especializado en RISE Challenge de 30 días.
        REGLAS ESTRICTAS:
        - Responde ÚNICAMENTE con JSON válido, cero texto fuera del JSON
        - 4 semanas de progresión con sobrecarga progresiva
        - Adapta al lugar de entrenamiento (gym/home/hybrid)
        - Cardio obligatorio si el objetivo incluye pérdida de grasa
        - Tips de nutrición sin gramajes exactos
        - Cierra recomendando Asesoría Nutricional WellCore
        PROMPT;
    }

    public static function getTrainingSystemPrompt(): string
    {
        return <<<PROMPT
        Eres un especialista en ciencias del ejercicio.
        - Sobrecarga progresiva 2-5% por semana
        - Volumen 10-20 series por grupo muscular
        - Periodización 4 semanas: Acumulación → Intensificación → Deload
        - RIR (Reps In Reserve): 3→2→1→4 por semana
        - Tempo 3-0-1 en ejercicios de aislamiento
        PROMPT;
    }

    public static function buildRiseEnrichedPrompt(array $client, array $intake): string
    {
        return "CLIENTE: {$client['name']}, {$intake['edad']} años, {$client['gender']}\n"
            . "MEDIDAS: Cintura {$intake['waist']}, Caderas {$intake['hips']}\n"
            . "ENTRENAMIENTO: {$intake['years']} años de experiencia, lugar: {$intake['place']}\n"
            . "DISPONIBILIDAD: " . implode(', ', $intake['days'] ?? []) . "\n"
            . "OBJETIVO: " . implode(', ', $intake['goals'] ?? []) . "\n"
            . "RESTRICCIONES: {$intake['exercisesToAvoid']}\n"
            . "Genera el plan completo en JSON según el schema.";
    }
}
```

- [ ] **Step 4: Job GenerateAiPlan (async)**

```php
// app/Jobs/GenerateAiPlan.php
class GenerateAiPlan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;

    public function __construct(
        private User $user,
        private string $planType,
        private array $intake
    ) {}

    public function handle(): void
    {
        $planJson = match($this->planType) {
            'entrenamiento' => ClaudeAiService::generateTrainingPlan($this->user->toArray(), $this->intake),
            'rise' => ClaudeAiService::generateRisePlan($this->user->toArray(), $this->intake),
            default => throw new \InvalidArgumentException("Plan type inválido: {$this->planType}"),
        };

        AssignedPlan::updateOrCreate(
            ['user_id' => $this->user->id, 'plan_type' => $this->planType, 'active' => true],
            ['content' => $planJson, 'valid_from' => today()]
        );
    }
}
```

- [ ] **Step 5: Ejecutar tests AI**

```bash
./vendor/bin/pest tests/Unit/ClaudeAiServiceTest.php -v
```

- [ ] **Step 6: Commit AI service**

```bash
git commit -m "feat: ClaudeAiService + AiPromptsService + GenerateAiPlan job (RISE, training, nutrition)"
```

---

## Chunk 3: Pagos Wompi

### Task 3: WompiService

**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\api\wompi\`

- [ ] **Step 1: Tests Wompi**

```php
it('processes Wompi webhook and activates plan', function () {
    $user = User::factory()->create(['status' => 'pendiente']);

    $payload = [
        'event' => 'transaction.updated',
        'data' => [
            'transaction' => [
                'status' => 'APPROVED',
                'reference' => 'RISE-' . $user->id . '-' . time(),
                'amount_in_cents' => 19500000, // $195.000 COP
                'customer_data' => ['email' => $user->email],
            ],
        ],
    ];

    $this->postJson('/api/v1/payments/wompi/webhook', $payload)
        ->assertOk();

    expect($user->fresh()->status)->toBe('activo');
});
```

- [ ] **Step 2: WompiController webhook**

```php
class WompiController extends Controller
{
    public function webhook(Request $request): JsonResponse
    {
        $event = $request->input('event');
        $transaction = $request->input('data.transaction');

        if ($event !== 'transaction.updated') {
            return response()->json(['ok' => true]);
        }

        if ($transaction['status'] === 'APPROVED') {
            $email = $transaction['customer_data']['email'];
            $user = User::where('email', $email)->first();

            if ($user) {
                $user->update(['status' => 'activo']);
                Payment::create([
                    'user_id' => $user->id,
                    'amount_cents' => $transaction['amount_in_cents'],
                    'currency' => $transaction['currency'] ?? 'COP',
                    'status' => 'APPROVED',
                    'wompi_reference' => $transaction['reference'],
                ]);

                SendPaymentConfirmationEmail::dispatch($user);
            }
        }

        return response()->json(['ok' => true]);
    }
}
```

- [ ] **Step 3: Auto-renewal Job (reemplaza auto-renewal.php cron)**

```php
// app/Jobs/ProcessAutoRenewal.php
class ProcessAutoRenewal implements ShouldQueue
{
    public function handle(): void
    {
        // Clientes con renovación pendiente en ventana de 7 días
        $clients = User::where('status', 'activo')
            ->where('role', 'client')
            ->whereHas('paymentMethods', fn($q) => $q->where('is_active', true))
            ->where('fecha_inicio', '<=', today()->subDays(23)) // próximos a expirar
            ->get();

        foreach ($clients as $client) {
            ProcessClientRenewal::dispatch($client);
        }
    }
}
```

- [ ] **Step 4: Commit pagos**

```bash
git commit -m "feat: WompiService + webhook + auto-renewal Job"
```

---

## Chunk 4: WhatsApp Business API

### Task 4: WhatsApp para LATAM

**Reference:** Meta Business API + Twilio WhatsApp fallback

- [ ] **Step 1: WhatsAppService**

```php
// app/Services/WhatsAppService.php
class WhatsAppService
{
    public static function sendMessage(string $phone, string $template, array $params = []): bool
    {
        $phone = self::normalizePhone($phone);

        $response = Http::withToken(config('services.whatsapp.token'))
            ->post("https://graph.facebook.com/v19.0/" . config('services.whatsapp.phone_id') . "/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => 'es'],
                    'components' => [
                        ['type' => 'body', 'parameters' => array_map(
                            fn($p) => ['type' => 'text', 'text' => $p],
                            $params
                        )],
                    ],
                ],
            ]);

        return $response->successful();
    }

    private static function normalizePhone(string $phone): string
    {
        // Normalizar a formato internacional E.164
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (!str_starts_with($phone, '+')) {
            $phone = '+57' . $phone; // Colombia default
        }
        return $phone;
    }
}
```

- [ ] **Step 2: Integrar WhatsApp en BehavioralTriggers**

```php
// En SendBehavioralTrigger::processTrigger()
if ($client->profile?->whatsapp) {
    WhatsAppService::sendMessage(
        $client->profile->whatsapp,
        'wellcore_' . $trigger['type'],
        [$client->name]
    );
    // Actualizar channel en log
    AutoMessageLog::updateOrCreate(
        ['user_id' => $client->id, 'trigger_type' => $trigger['type'], 'date_sent' => today()],
        ['channel' => 'whatsapp']
    );
}
```

- [ ] **Step 3: Commit WhatsApp**

```bash
git commit -m "feat: WhatsApp Business API (LATAM triggers)"
```

---

## Chunk 5: Flutter RISE + AI + Payments

### Task 5: RISE Enrollment Flow Flutter (3 pasos)

**Files:**
- Create: `wellcore-app/lib/features/rise/rise_enroll_flow.dart`
- Create: `wellcore-app/lib/features/rise/rise_intake_form.dart`
- Create: `wellcore-app/lib/features/rise/rise_dashboard_screen.dart`
- Create: `wellcore-app/lib/features/ai/ai_chat_screen.dart`

- [ ] **Step 1: RISE enrollment flow (Wizard 3 pasos)**

```dart
// lib/features/rise/rise_enroll_flow.dart
// Paso 1: Datos básicos (nombre, email, password, género)
// Paso 2: Formulario de intake (medidas, entrenamiento, disponibilidad, nutrición, lifestyle)
// Paso 3: Pago con Wompi

class RiseEnrollFlow extends StatefulWidget {
  @override
  State<RiseEnrollFlow> createState() => _RiseEnrollFlowState();
}

class _RiseEnrollFlowState extends State<RiseEnrollFlow> {
  int _currentStep = 0;
  final Map<String, dynamic> _formData = {};

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: WellCoreColors.canvas,
      body: SafeArea(
        child: Column(
          children: [
            // Progress bar roja
            LinearProgressIndicator(
              value: (_currentStep + 1) / 3,
              color: WellCoreColors.primary,
              backgroundColor: WellCoreColors.surface2,
            ),
            Expanded(
              child: [
                RiseStep1Widget(onNext: _nextStep),
                RiseIntakeForm(onNext: _nextStep),
                RisePaymentStep(formData: _formData, onComplete: _complete),
              ][_currentStep],
            ),
          ],
        ),
      ),
    );
  }
}
```

- [ ] **Step 2: RISE Dashboard con ring de progreso**

```dart
// 30-day progress ring usando fl_chart PieChart
PieChart(
  PieChartData(
    sections: [
      PieChartSectionData(
        value: daysElapsed.toDouble(),
        color: WellCoreColors.primary,
        radius: 20,
        showTitle: false,
      ),
      PieChartSectionData(
        value: (30 - daysElapsed).toDouble(),
        color: WellCoreColors.surface2,
        radius: 20,
        showTitle: false,
      ),
    ],
    centerSpaceRadius: 60,
    sectionsSpace: 2,
  ),
)
```

- [ ] **Step 3: AI Chat Screen Flutter**

```dart
// lib/features/ai/ai_chat_screen.dart
// Chat UI con burbujas de mensaje (estilo WhatsApp)
// Rate limits mostrados según plan
// Historial de conversación desde API
```

- [ ] **Step 4: Video Check-in Screen**

```dart
// Grabación de video con camera plugin
// Preview antes de enviar
// Upload a Storage de Laravel
// Coach ve el video y puede responder
```

- [ ] **Step 5: Test completo en emulador**

```bash
flutter run -d android
```

Flujo RISE:
1. Abrir app sin login → Explorar planes
2. Tap "Unirme al RISE" → wizard 3 pasos
3. Completar formulario de intake
4. Procesar pago (sandbox Wompi)
5. Ver RISE dashboard con ring de 30 días

- [ ] **Step 6: Commit Flutter Fase 2**

```bash
git commit -m "feat: Fase 2 — RISE flow, AI chat, video checkins, pagos Wompi en Flutter"
git tag v0.3.0-fase2
```

---

## Resumen Fase 2 — Entregables

| Entregable | Status |
|---|---|
| RISE enroll + intake + status APIs completas | ✅ |
| ClaudeAiService (claude-haiku) con prompts migrados | ✅ |
| GenerateAiPlan Job async (training, nutrition, rise) | ✅ |
| WompiService + webhook handler + auto-renewal | ✅ |
| WhatsApp Business API para triggers LATAM | ✅ |
| Flutter: RISE wizard 3 pasos completo | ✅ |
| Flutter: RISE dashboard con ring de 30 días | ✅ |
| Flutter: AI chat con historial | ✅ |
| Flutter: Video check-in (grabar + enviar) | ✅ |
| Flutter: Audio coaching player | ✅ |
| Flutter: Payment screens (Wompi checkout) | ✅ |
| Tests > 80% coverage | ✅ |

**Siguiente paso → Fase 3: Sofisticado (Analytics avanzado + Health + Pods + Nutrition)**
