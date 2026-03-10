# WellCore App Nativa — FASE 3: Sofisticado

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar analytics avanzados para coaches, integración con Apple Health / Google Fit, Accountability Pods, nutrition tracking completo, mental wellness tracker, plan phases visibles, y el portal B2B para coaches externos — posicionando a WellCore como plataforma premium superior a Bejao y Trainerize.

**Architecture:** Extiende Fase 2. Se agregan integraciones con SDKs nativos (health package Flutter), modelos de datos ricos para nutrition/mental wellness, y el sistema multi-tenant básico para el modelo B2B.

**Tech Stack:** Flutter `health` package (Apple Health + Google Fit), Laravel Sanctum abilities (permisos granulares para B2B), MySQL JSON columns para tracking flexible | fl_chart avanzado, table_calendar

**Prerequisito:** `v0.3.0-fase2` completado.

---

## Chunk 1: Coach Analytics Avanzados

### Task 1: Dashboard analítico del coach

**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\api\coach\` — analytics endpoints

**Files:**
- Create: `wellcore-api/app/Http/Controllers/Api/V1/Coach/AnalyticsController.php`
- Create: `wellcore-api/app/Services/CoachAnalyticsService.php`

- [ ] **Step 1: Tests analytics**

```php
// tests/Feature/Coach/CoachAnalyticsTest.php
it('returns adherence metrics for coach clients', function () {
    $coach = User::factory()->create(['role' => 'coach']);
    $client = User::factory()->create(['coach_id' => $coach->id]);

    // 3 check-ins en las últimas 4 semanas → 75% adherencia
    Checkin::factory()->count(3)->for($client)->create([
        'checkin_date' => fn() => now()->subWeeks(rand(0, 3)),
    ]);

    Sanctum::actingAs($coach);

    $this->getJson('/api/v1/coach/analytics')
        ->assertOk()
        ->assertJsonStructure([
            'overview' => ['total_clients', 'active_clients', 'avg_adherence_pct'],
            'clients_at_risk',
            'top_performers',
            'weekly_trends',
        ]);
});

it('identifies clients at churn risk', function () {
    $coach = User::factory()->create(['role' => 'coach']);
    $riskClient = User::factory()->create([
        'coach_id' => $coach->id,
        'role' => 'client',
        'status' => 'activo',
    ]);
    // Sin check-ins en 21 días → alto riesgo

    Sanctum::actingAs($coach);

    $response = $this->getJson('/api/v1/coach/analytics');
    $atRisk = collect($response->json('clients_at_risk'));

    expect($atRisk->contains('id', $riskClient->id))->toBeTrue();
});
```

- [ ] **Step 2: CoachAnalyticsService**

```php
// app/Services/CoachAnalyticsService.php
class CoachAnalyticsService
{
    public static function getDashboard(User $coach): array
    {
        $clients = User::where('coach_id', $coach->id)->where('role', 'client')->get();
        $clientIds = $clients->pluck('id');

        return [
            'overview' => [
                'total_clients' => $clients->count(),
                'active_clients' => $clients->where('status', 'activo')->count(),
                'avg_adherence_pct' => self::calculateAvgAdherence($clientIds),
                'clients_with_streak' => ClientXp::whereIn('user_id', $clientIds)
                    ->where('streak_days', '>', 0)->count(),
            ],
            'clients_at_risk' => self::getAtRiskClients($clients),
            'top_performers' => self::getTopPerformers($clients),
            'weekly_trends' => self::getWeeklyTrends($clientIds),
        ];
    }

    private static function calculateAvgAdherence(Collection $clientIds): float
    {
        if ($clientIds->isEmpty()) return 0;

        $totalCheckins = Checkin::whereIn('user_id', $clientIds)
            ->where('checkin_date', '>=', now()->subWeeks(4))
            ->count();

        // Esperamos 1 check-in por semana por cliente = 4 por cliente en 4 semanas
        $expected = $clientIds->count() * 4;
        return $expected > 0 ? round(($totalCheckins / $expected) * 100) : 0;
    }

    private static function getAtRiskClients(Collection $clients): Collection
    {
        return $clients->filter(function ($client) {
            $lastCheckin = Checkin::where('user_id', $client->id)
                ->orderByDesc('checkin_date')->first();

            $daysSinceLastCheckin = $lastCheckin
                ? today()->diffInDays($lastCheckin->checkin_date)
                : 999;

            $xp = $client->xp;
            $streakBroken = $xp && $xp->streak_days === 0;
            $inactiveLong = $daysSinceLastCheckin >= 14;

            return $streakBroken || $inactiveLong;
        })->map(fn($c) => [
            'id' => $c->id, 'name' => $c->name,
            'days_inactive' => today()->diffInDays(
                Checkin::where('user_id', $c->id)->orderByDesc('checkin_date')->first()?->checkin_date ?? $c->created_at
            ),
            'risk_level' => 'high',
        ])->values();
    }
}
```

- [ ] **Step 3: Commit analytics backend**

```bash
git commit -m "feat: Coach analytics avanzados (adherencia, churn risk, top performers)"
```

---

## Chunk 2: Apple Health + Google Fit

### Task 2: Integración wearables

**Flutter package:** `health: ^10.2.0`

- [ ] **Step 1: Agregar health package**

```yaml
# pubspec.yaml
health: ^10.2.0
permission_handler: ^11.3.0
```

- [ ] **Step 2: Permisos en manifests**

```xml
<!-- android/app/src/main/AndroidManifest.xml -->
<uses-permission android:name="android.permission.health.READ_STEPS"/>
<uses-permission android:name="android.permission.health.READ_SLEEP"/>
<uses-permission android:name="android.permission.health.READ_HEART_RATE"/>
<uses-permission android:name="android.permission.health.READ_BODY_WEIGHT"/>

<!-- iOS Info.plist -->
<key>NSHealthShareUsageDescription</key>
<string>WellCore sincroniza tus pasos, sueño y frecuencia cardíaca para personalizar tu plan.</string>
```

- [ ] **Step 3: HealthSyncService Flutter**

```dart
// lib/features/health/health_sync_service.dart
class HealthSyncService {
  static final HealthFactory _health = HealthFactory();

  static const types = [
    HealthDataType.STEPS,
    HealthDataType.SLEEP_ASLEEP,
    HealthDataType.HEART_RATE,
    HealthDataType.WEIGHT,
    HealthDataType.BODY_FAT_PERCENTAGE,
  ];

  static Future<Map<String, dynamic>> getTodayData() async {
    final granted = await _health.requestAuthorization(types);
    if (!granted) return {};

    final now = DateTime.now();
    final midnight = DateTime(now.year, now.month, now.day);

    final steps = await _health.getTotalStepsInInterval(midnight, now) ?? 0;
    final heartRate = await _getAvgHeartRate(midnight, now);
    final sleep = await _getSleepHours(midnight, now);

    return {
      'steps': steps,
      'heart_rate': heartRate,
      'sleep_hours': sleep,
      'date': now.toIso8601String().split('T')[0],
    };
  }

  static Future<void> syncToBackend(WidgetRef ref) async {
    final data = await getTodayData();
    if (data.isEmpty) return;

    await ref.read(apiClientProvider).saveBiometricLog(BiometricLogData.fromJson(data));
  }
}
```

- [ ] **Step 4: API biometric backend (si no existe de Fase 0)**

```php
// GET/POST /api/v1/metrics/biometric
// Guarda pasos, sueño, FC, peso desde wearables
// Tabla biometric_logs (migración ya existe de referencia PHP)
```

- [ ] **Step 5: Tests de sincronización**

```php
it('saves biometric log from wearable sync', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/metrics/biometric', [
        'steps' => 8543,
        'sleep_hours' => 7.5,
        'heart_rate' => 68,
        'energy_level' => 8,
    ])->assertCreated();
});
```

- [ ] **Step 6: Commit Health integration**

```bash
git commit -m "feat: Apple Health + Google Fit sync (pasos, sueño, FC, peso)"
```

---

## Chunk 3: Accountability Pods

### Task 3: Grupos de accountability

**Reference estudio:** "89% completion vs 61% sin pods" — alto impacto en retención

- [ ] **Step 1: Migraciones pods**

```bash
php artisan make:migration create_pods_tables
```

```php
Schema::create('pods', function (Blueprint $table) {
    $table->id();
    $table->foreignId('coach_id')->constrained('users');
    $table->string('name');
    $table->text('description')->nullable();
    $table->enum('privacy', ['public', 'private'])->default('private');
    $table->unsignedTinyInteger('max_members')->default(8);
    $table->timestamps();
});

Schema::create('pod_members', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pod_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamp('joined_at')->useCurrent();
    $table->unique(['pod_id', 'user_id']);
});
```

- [ ] **Step 2: Tests Pods**

```php
it('allows coach to create a pod with clients', function () {
    $coach = User::factory()->create(['role' => 'coach']);
    $clients = User::factory()->count(5)->create(['coach_id' => $coach->id]);
    Sanctum::actingAs($coach);

    $response = $this->postJson('/api/v1/coach/pods', [
        'name' => 'Equipo Fuerza Enero',
        'max_members' => 8,
        'client_ids' => $clients->pluck('id')->toArray(),
    ])->assertCreated();

    expect(PodMember::where('pod_id', $response->json('pod.id'))->count())->toBe(5);
});

it('rejects pod with more than 8 members', function () {
    $coach = User::factory()->create(['role' => 'coach']);
    Sanctum::actingAs($coach);

    $this->postJson('/api/v1/coach/pods', [
        'name' => 'Pod Grande',
        'max_members' => 8,
        'client_ids' => range(1, 10), // 10 miembros → error
    ])->assertUnprocessable();
});
```

- [ ] **Step 3: Flutter Pod Feed**

```dart
// lib/features/pods/pod_feed_screen.dart
// Feed de actividad del pod: quién entrenó, quién completó check-in
// Badge "Mi equipo" en el sidebar del cliente
// Contador de completados esta semana
```

- [ ] **Step 4: Commit Pods**

```bash
git commit -m "feat: Accountability Pods (grupos 5-8 + feed compartido)"
```

---

## Chunk 4: Nutrition + Mental Wellness Tracker

### Task 4: Módulos de bienestar holístico

**Reference estudio:** Feature diferenciador vs Trainerize/Bejao (ambos tienen gaps aquí)

- [ ] **Step 1: Migraciones nutrition + wellness**

```bash
php artisan make:migration create_nutrition_logs_table
php artisan make:migration create_wellness_logs_table
```

```php
// nutrition_logs
Schema::create('nutrition_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->date('log_date');
    $table->integer('calories_target')->nullable();
    $table->integer('calories_actual')->nullable();
    $table->integer('protein_g')->nullable();
    $table->integer('carbs_g')->nullable();
    $table->integer('fat_g')->nullable();
    $table->tinyInteger('adherence_pct')->nullable(); // 0-100
    $table->string('meal_photo_url')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'log_date']);
});

// wellness_logs (mental health tracker)
Schema::create('wellness_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->date('log_date');
    $table->tinyInteger('energy_level')->nullable();  // 1-10
    $table->tinyInteger('stress_level')->nullable();  // 1-10
    $table->decimal('sleep_hours', 3, 1)->nullable();
    $table->tinyInteger('sleep_quality')->nullable(); // 1-10
    $table->tinyInteger('mood')->nullable();           // 1-10
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'log_date']);
});
```

- [ ] **Step 2: Tests nutrition + wellness**

```php
it('saves daily nutrition log', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/nutrition', [
        'calories_actual' => 2100,
        'protein_g' => 150,
        'carbs_g' => 220,
        'fat_g' => 70,
        'adherence_pct' => 85,
    ])->assertCreated();
});

it('saves daily wellness check', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/wellness', [
        'energy_level' => 7,
        'stress_level' => 4,
        'sleep_hours' => 7.5,
        'sleep_quality' => 8,
        'mood' => 8,
    ])->assertCreated();
});
```

- [ ] **Step 3: Flutter Nutrition Tracker**

```dart
// lib/features/nutrition/nutrition_screen.dart
// Macro rings (fl_chart PieChart)
// Proteína / Carbos / Grasas con colores WellCore
// Ingesta diaria vs objetivo
// Foto de comida (image_picker)
```

- [ ] **Step 4: Flutter Mental Wellness Daily Check**

```dart
// lib/features/wellness/wellness_check_screen.dart
// 5 sliders: energía, estrés, sueño, calidad sueño, mood
// Integrado en dashboard como daily card
// Historial en gráfica de líneas (fl_chart)
```

- [ ] **Step 5: Commit nutrition + wellness**

```bash
git commit -m "feat: Nutrition tracking + Mental wellness daily check"
```

---

## Chunk 5: Plan Phases + B2B Portal

### Task 5: Plan phases visibles al cliente

**Reference estudio:** "cliente ve todo el camino" — compromiso a largo plazo

- [ ] **Step 1: Plan phases en Flutter**

```dart
// lib/features/training/plan_phases_screen.dart
// Timeline visual: 4 semanas (Acumulación → Intensificación → Deload → Pico)
// Semana actual destacada en rojo #E31E24
// Cada semana expandible mostrando ejercicios del día
// Progress ring por semana completada
```

- [ ] **Step 2: B2B Portal — coaches externos básico**

```php
// Nuevo rol: 'coach_external' — coach que se suscribe como B2B
// Plan B2B: paga mensualidad, trae sus propios clientes
// Sus clientes están en su tenant (coach_id como tenant_id)

// Migration nueva tabla
Schema::create('b2b_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('coach_id')->constrained('users');
    $table->enum('plan', ['starter', 'pro', 'studio'])->default('starter');
    $table->integer('max_clients');
    $table->decimal('monthly_price', 10, 2);
    $table->date('billing_date');
    $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
    $table->timestamps();
});
```

- [ ] **Step 3: Tests B2B portal**

```php
it('external coach can manage their own clients only', function () {
    $externalCoach = User::factory()->create(['role' => 'coach_external']);
    $myClient = User::factory()->create(['coach_id' => $externalCoach->id]);
    $otherClient = User::factory()->create(); // otro coach

    Sanctum::actingAs($externalCoach);

    $response = $this->getJson('/api/v1/coach/clients');
    $clientIds = collect($response->json('clients'))->pluck('id');

    expect($clientIds->contains($myClient->id))->toBeTrue()
        ->and($clientIds->contains($otherClient->id))->toBeFalse();
});
```

- [ ] **Step 4: Referral mejorado con landing propia**

```php
// Cada cliente tiene su código único de referido
// GET /api/v1/referral/my-link → retorna URL + stats
// Landing page dedicada: wellcorefitness.com/r/{code}
// Descuento automático aplicado en checkout

class ReferralController extends Controller
{
    public function myLink(Request $request): JsonResponse
    {
        $user = $request->user();
        $referral = Referral::firstOrCreate(
            ['referrer_id' => $user->id],
            ['code' => strtoupper(Str::random(8))]
        );

        return response()->json([
            'code' => $referral->code,
            'link' => url("/r/{$referral->code}"),
            'total_referred' => $referral->referred()->count(),
            'conversions' => $referral->referred()->where('status', 'converted')->count(),
            'xp_earned' => XpEvent::where('user_id', $user->id)
                ->where('event_type', 'referral')->sum('xp_gained'),
        ]);
    }
}
```

- [ ] **Step 5: Commit Fase 3 completa**

```bash
git commit -m "feat: Fase 3 — Plan phases, B2B portal coaches, referral mejorado"
git tag v0.4.0-fase3
```

---

## Resumen Fase 3 — Entregables

| Entregable | Status |
|---|---|
| Coach analytics: adherencia, churn risk, top performers | ✅ |
| Apple Health + Google Fit sync (pasos, sueño, FC) | ✅ |
| Accountability Pods (grupos 5-8 por coach) | ✅ |
| Nutrition tracking completo (macros + fotos) | ✅ |
| Mental wellness daily check (energía, estrés, sueño) | ✅ |
| Plan phases visibles (timeline 4 semanas) | ✅ |
| B2B portal coaches externos (multi-tenant básico) | ✅ |
| Referral system mejorado con landing propia | ✅ |
| Flutter: todas las pantallas nuevas funcionando | ✅ |
| Tests > 82% coverage | ✅ |

**Siguiente paso → Fase 4: Consolidar + Launch**
