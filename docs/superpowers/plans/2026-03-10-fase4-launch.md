# WellCore App Nativa — FASE 4: Consolidar + Launch

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Preparar el stack completo para producción: script de migración de datos PHP→Laravel, WebSockets en tiempo real con Laravel Reverb, Laravel Octane para alto rendimiento, publicación en App Store + Google Play, y CI/CD completo.

**Architecture:** Esta fase no añade features nuevas — consolida y optimiza todo lo construido en Fases 0-3. El script de migración es el puente entre el PHP de producción y el nuevo Laravel. El launch es zero-downtime: el PHP sigue corriendo durante la migración.

**Tech Stack:** Laravel Octane (Swoole), Laravel Reverb (WebSockets), Redis (cache + queues), GitHub Actions (CI/CD), Fastlane (App Store + Play Store automation)

**Prerequisito:** `v0.4.0-fase3` completado. Todos los tests pasando. App probada en emuladores físicos.

---

## Chunk 1: WebSockets en tiempo real con Laravel Reverb

### Task 1: Activity Feed en tiempo real

**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\js\activity-feed.js` — actualmente usa polling cada 30s

**Files:**
- Create: `wellcore-api/app/Events/ActivityFeedEvent.php`
- Create: `wellcore-api/app/Events/LeaderboardUpdated.php`
- Create: `wellcore-api/app/Events/NewCheckinReceived.php`

- [ ] **Step 1: Instalar Laravel Reverb**

```bash
cd wellcore-api
composer require laravel/reverb
php artisan reverb:install
```

- [ ] **Step 2: Tests de broadcasting**

```php
// tests/Feature/Broadcasting/ActivityFeedTest.php
it('broadcasts event when checkin is submitted', function () {
    Event::fake([NewCheckinReceived::class]);

    $user = User::factory()->create(['role' => 'client']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/checkins', [
        'week' => '2026-W10',
        'bienestar' => 8,
    ]);

    Event::assertDispatched(NewCheckinReceived::class, fn($event) =>
        $event->checkin->user_id === $user->id
    );
});

it('broadcasts leaderboard update when XP is earned', function () {
    Event::fake([LeaderboardUpdated::class]);

    $user = User::factory()->create();
    GamificationService::earnXp($user, 'checkin');

    Event::assertDispatched(LeaderboardUpdated::class);
});
```

- [ ] **Step 3: Crear eventos broadcast**

```php
// app/Events/NewCheckinReceived.php
class NewCheckinReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Checkin $checkin) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("coach.{$this->checkin->user->coach_id}"),
            new Channel('activity-feed'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'checkin',
            'client_name' => $this->checkin->user->name,
            'plan' => $this->checkin->user->plan,
            'bienestar' => $this->checkin->bienestar,
            'timestamp' => $this->checkin->created_at->toISOString(),
        ];
    }
}

// app/Events/LeaderboardUpdated.php
class LeaderboardUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): Channel
    {
        return new Channel('leaderboard');
    }
}
```

- [ ] **Step 4: Flutter WebSocket client**

```dart
// pubspec.yaml
web_socket_channel: ^2.4.0
laravel_echo: ^1.0.0  # o implementación manual

// lib/core/realtime/reverb_client.dart
class ReverbClient {
  static WebSocketChannel? _channel;

  static void connect(String token) {
    _channel = WebSocketChannel.connect(
      Uri.parse('ws://wellcore-api.test/app/wellcore-key?token=$token'),
    );

    _channel!.stream.listen((message) {
      final data = jsonDecode(message);
      _handleEvent(data);
    });
  }

  static void _handleEvent(Map<String, dynamic> data) {
    final event = data['event'] as String?;
    switch (event) {
      case 'NewCheckinReceived':
        ActivityFeedNotifier.add(CheckinActivity.fromJson(data['data']));
      case 'LeaderboardUpdated':
        LeaderboardNotifier.refresh();
    }
  }
}
```

- [ ] **Step 5: Leaderboard en tiempo real Flutter**

```dart
// El leaderboard se actualiza automáticamente cuando alguien gana XP
// Animación de posición cuando subes en el ranking
```

- [ ] **Step 6: Ejecutar tests broadcasting**

```bash
./vendor/bin/pest tests/Feature/Broadcasting/ -v
```

- [ ] **Step 7: Commit WebSockets**

```bash
git commit -m "feat: Laravel Reverb WebSockets (activity feed + leaderboard tiempo real)"
```

---

## Chunk 2: Script de Migración de Datos

### Task 2: PHP → Laravel data migration

**CRÍTICO:** Este script se ejecuta UNA SOLA VEZ en producción. Debe ser idempotente y verificable.

**Files:**
- Create: `wellcore-api/database/migrations/scripts/migrate_from_php.php`
- Create: `wellcore-api/database/migrations/scripts/verify_migration.php`

- [ ] **Step 1: Tests del script de migración**

```php
// tests/Feature/Migration/MigrationScriptTest.php
it('migrates clients from PHP schema to Laravel schema correctly', function () {
    // Simular DB "antigua" en test
    DB::connection('mysql_legacy')->table('clients')->insert([
        'id' => 1, 'name' => 'Carlos', 'email' => 'carlos@test.com',
        'password_hash' => '$2y$12$...', 'plan' => 'elite', 'status' => 'activo',
        'client_code' => 'elite-123',
    ]);

    $migrator = new PhpToLaravelMigrator();
    $migrator->migrateClients();

    $this->assertDatabaseHas('users', [
        'email' => 'carlos@test.com',
        'role' => 'client',
        'plan' => 'elite',
    ]);
});

it('preserves XP data during migration', function () {
    // Setup legacy data
    DB::connection('mysql_legacy')->table('client_xp')->insert([
        'client_id' => 1, 'xp_total' => 850, 'level' => 3, 'streak_days' => 7,
    ]);

    $migrator = new PhpToLaravelMigrator();
    $migrator->migrateGamification();

    $user = User::where('email', 'carlos@test.com')->first();
    expect($user->xp->xp_total)->toBe(850)
        ->and($user->xp->level)->toBe(3);
});
```

- [ ] **Step 2: PhpToLaravelMigrator class**

```php
// app/Services/Migration/PhpToLaravelMigrator.php
class PhpToLaravelMigrator
{
    private PDO $legacyDb;
    private array $idMap = []; // legacy_id => new_id

    public function __construct()
    {
        // Conecta a la DB del PHP (configurar en .env.migration)
        $this->legacyDb = new PDO(
            "mysql:host=" . env('LEGACY_DB_HOST') . ";dbname=" . env('LEGACY_DB_NAME'),
            env('LEGACY_DB_USER'),
            env('LEGACY_DB_PASS')
        );
    }

    public function run(): void
    {
        DB::beginTransaction();
        try {
            $this->migrateAdmins();
            $this->migrateCoaches();
            $this->migrateClients();
            $this->migrateClientProfiles();
            $this->migrateMetrics();
            $this->migrateCheckins();
            $this->migratePhotos();
            $this->migrateGamification();
            $this->migratePayments();
            $this->migrateChallenges();
            $this->migrateAcademy();
            $this->migrateCommunity();
            $this->migrateNotifications();
            $this->migrateRisePrograms();
            DB::commit();
            $this->log("✅ Migración completada sin errores");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->log("❌ Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function migrateClients(): void
    {
        $clients = $this->legacyDb->query("SELECT * FROM clients")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($clients as $c) {
            $newUser = User::create([
                'name' => $c['name'],
                'email' => $c['email'],
                'password' => $c['password_hash'], // ya está hasheado en bcrypt
                'role' => 'client',
                'plan' => $c['plan'],
                'status' => $c['status'],
                'client_code' => $c['client_code'],
                'fecha_inicio' => $c['fecha_inicio'],
                'birth_date' => $c['birth_date'] ?? null,
                'created_at' => $c['created_at'],
            ]);

            $this->idMap['clients'][$c['id']] = $newUser->id;
        }

        $this->log("✅ Clientes migrados: " . count($clients));
    }

    private function migrateGamification(): void
    {
        $xpRecords = $this->legacyDb->query("SELECT * FROM client_xp")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($xpRecords as $xp) {
            $newUserId = $this->idMap['clients'][$xp['client_id']] ?? null;
            if (!$newUserId) continue;

            ClientXp::create([
                'user_id' => $newUserId,
                'xp_total' => $xp['xp_total'],
                'level' => $xp['level'],
                'streak_days' => $xp['streak_days'],
                'last_activity_date' => $xp['last_activity_date'] ?? null,
            ]);
        }

        $this->log("✅ XP migrado: " . count($xpRecords) . " registros");
    }

    // ... (mismo patrón para todas las tablas)

    private function log(string $message): void
    {
        echo date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
        file_put_contents(storage_path('logs/migration.log'), $message . PHP_EOL, FILE_APPEND);
    }
}
```

- [ ] **Step 3: Script de verificación post-migración**

```php
// database/migrations/scripts/verify_migration.php
$checks = [
    'clients' => ['legacy' => 'clients', 'new' => 'users WHERE role=\'client\''],
    'checkins' => ['legacy' => 'checkins', 'new' => 'checkins'],
    'metrics' => ['legacy' => 'metrics', 'new' => 'metrics'],
    'payments' => ['legacy' => 'payments', 'new' => 'payments'],
    'xp_records' => ['legacy' => 'client_xp', 'new' => 'client_xp'],
];

foreach ($checks as $name => $tables) {
    $legacyCount = $legacy->query("SELECT COUNT(*) FROM {$tables['legacy']}")->fetchColumn();
    $newCount = DB::selectOne("SELECT COUNT(*) as c FROM {$tables['new']}")->c;

    $status = $legacyCount == $newCount ? '✅' : '❌';
    echo "{$status} {$name}: legacy={$legacyCount} new={$newCount}\n";
}
```

- [ ] **Step 4: Artisan command para migración**

```bash
php artisan make:command MigrateFromLegacy
```

```php
// Solo se puede ejecutar en entorno local o con flag --force
public function handle(): void
{
    if (app()->environment('production') && !$this->option('force')) {
        $this->error('Use --force en producción. Asegúrate de tener backup.');
        return;
    }

    if (!$this->confirm('¿Confirmas la migración completa de datos?')) return;

    $migrator = new PhpToLaravelMigrator();
    $migrator->run();

    $this->info('✅ Migración completada. Verifica con: php artisan migrate:verify');
}
```

- [ ] **Step 5: Commit migration script**

```bash
git commit -m "feat: Script migración datos PHP→Laravel con verificación de integridad"
```

---

## Chunk 3: Laravel Octane + Redis

### Task 3: Performance production-ready

- [ ] **Step 1: Instalar Octane con Swoole**

```bash
composer require laravel/octane
php artisan octane:install --server=swoole
```

- [ ] **Step 2: Instalar Redis**

```bash
composer require predis/predis
```

```env
# .env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

- [ ] **Step 3: Tests de performance**

```php
// tests/Feature/Performance/CacheTest.php
it('caches leaderboard response for 5 minutes', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Primera llamada — sin cache
    $response1 = $this->getJson('/api/v1/gamification/leaderboard');

    // Segunda llamada — desde cache
    $response2 = $this->getJson('/api/v1/gamification/leaderboard');

    // Mismo resultado
    expect($response1->json())->toBe($response2->json());
});
```

- [ ] **Step 4: Cache en endpoints costosos**

```php
// En LeaderboardController
public function index(Request $request): JsonResponse
{
    $period = $request->input('period', 'week');
    $coachId = $request->user()->coach_id;

    $data = Cache::remember("leaderboard.{$coachId}.{$period}", 300, function () use ($request, $period) {
        return $this->buildLeaderboard($request->user(), $period);
    });

    return response()->json($data);
}

// Invalidar cache cuando hay nueva actividad
// En GamificationService::earnXp():
Cache::forget("leaderboard.{$user->coach_id}.week");
Cache::forget("leaderboard.{$user->coach_id}.all");
```

- [ ] **Step 5: Rate limiting con Redis**

```php
// app/Http/Kernel.php — reemplaza el rate limiter PHP manual
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinutes(15, 5)->by($request->ip()); // 5 intentos/15min
});

RateLimiter::for('ai-chat', function (Request $request) {
    $limits = ['esencial' => 10, 'metodo' => 30, 'elite' => 999];
    $plan = $request->user()?->plan ?? 'esencial';
    return Limit::perHour($limits[$plan] ?? 5)->by($request->user()?->id ?? $request->ip());
});
```

- [ ] **Step 6: Commit Octane + Redis**

```bash
git commit -m "perf: Laravel Octane + Redis cache + rate limiting avanzado"
```

---

## Chunk 4: App Store + Google Play

### Task 4: Publicación en stores

- [ ] **Step 1: Configurar app signing Android**

```bash
# Generar keystore de producción
keytool -genkey -v -keystore wellcore-release.jks -keyAlias wellcore -keyalg RSA -keysize 2048 -validity 10000

# android/key.properties (NO committear — en .gitignore)
storePassword=<password>
keyPassword=<password>
keyAlias=wellcore
storeFile=../wellcore-release.jks
```

- [ ] **Step 2: Build release Android**

```bash
flutter build appbundle --release
# Output: build/app/outputs/bundle/release/app-release.aab
```

- [ ] **Step 3: Configurar iOS signing**

```bash
# Requiere Mac con Xcode instalado
# Crear App ID en Apple Developer Portal
# Crear Distribution Certificate
# Crear Provisioning Profile

cd ios && pod install && cd ..
flutter build ipa --release
```

- [ ] **Step 4: Assets requeridos para stores**

**Google Play:**
```
- Ícono 512x512px (sin alpha)
- Feature graphic 1024x500px
- Screenshots: 2-8 imágenes (teléfono + tablet)
- Descripción corta (80 chars): "Tu coach de fitness personalizado con IA"
- Descripción larga (4000 chars): el pitch completo de WellCore
```

**App Store:**
```
- Ícono 1024x1024px
- Screenshots: iPhone 6.7", 6.5", 5.5" (obligatorio)
- Screenshots: iPad Pro 12.9" (recomendado)
- Privacy policy URL: wellcorefitness.com/privacidad
- App Store Connect: crear app, subir build con Transporter
```

- [ ] **Step 5: Metadata completa**

```
Bundle ID: com.wellcorefitness.app
Version: 1.0.0
Build: 1

Palabras clave (ASO):
coach fitness, entrenamiento personalizado, RISE challenge,
fitness en español, plan de entrenamiento IA, wellcore
```

- [ ] **Step 6: Commit assets + configuración stores**

```bash
git add android/app/build.gradle ios/Runner.xcodeproj/
git commit -m "chore: configuración release Android + iOS para stores"
```

---

## Chunk 5: CI/CD con GitHub Actions

### Task 5: Pipeline automatizado

**Files:**
- Create: `.github/workflows/backend-tests.yml`
- Create: `.github/workflows/flutter-tests.yml`
- Create: `.github/workflows/deploy-production.yml`

- [ ] **Step 1: Pipeline backend (Laravel tests)**

```yaml
# .github/workflows/backend-tests.yml
name: Backend Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wellcore_test
        options: --health-cmd="mysqladmin ping" --health-interval=10s

    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }

      - name: Install dependencies
        working-directory: wellcore-api
        run: composer install --no-interaction

      - name: Run migrations
        working-directory: wellcore-api
        run: php artisan migrate --env=testing

      - name: Run Pest tests
        working-directory: wellcore-api
        run: ./vendor/bin/pest --coverage --min=80
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_DATABASE: wellcore_test
          DB_USERNAME: root
          DB_PASSWORD: root
```

- [ ] **Step 2: Pipeline Flutter (tests + build check)**

```yaml
# .github/workflows/flutter-tests.yml
name: Flutter Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: subosito/flutter-action@v2
        with: { flutter-version: '3.x' }

      - name: Install dependencies
        working-directory: wellcore-app
        run: flutter pub get

      - name: Run tests
        working-directory: wellcore-app
        run: flutter test --coverage

      - name: Build Android (verify compilación)
        working-directory: wellcore-app
        run: flutter build apk --debug
```

- [ ] **Step 3: Deploy automático a EasyPanel (cuando main pasa tests)**

```yaml
# .github/workflows/deploy-production.yml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    needs: [backend-tests, flutter-tests]

    steps:
      - name: Deploy Laravel to EasyPanel
        run: |
          curl -X POST "${{ secrets.EASYPANEL_WEBHOOK_URL }}" \
            -H "Authorization: Bearer ${{ secrets.EASYPANEL_TOKEN }}" \
            -d '{"action": "git-pull"}'

      - name: Run migrations in production
        run: |
          curl -X POST "https://wellcorefitness.com/api/trpc/services.box.runScript" \
            -H "Authorization: Bearer ${{ secrets.EASYPANEL_TOKEN }}" \
            -d '{"action": "php artisan migrate --force"}'
```

- [ ] **Step 4: Commit CI/CD**

```bash
git add .github/
git commit -m "ci: GitHub Actions para tests + deploy automático"
```

---

## Chunk 6: Verificación Final + Tag de Launch

### Task 6: Checklist de launch

- [ ] **Step 1: Ejecutar test suite completa**

```bash
# Backend
cd wellcore-api && ./vendor/bin/pest --coverage

# Flutter
cd wellcore-app && flutter test
```

Resultado esperado: > 80% coverage backend, todos los Flutter tests en verde.

- [ ] **Step 2: Test E2E flujo completo**

Flujo 1 — Nuevo cliente:
1. Abrir app → Login screen
2. Tap "Unirme al RISE" → Enrollment wizard
3. Completar intake form
4. Procesar pago sandbox → Dashboard RISE activo
5. Ver plan generado por IA
6. Completar primer entreno → XP ganado
7. Ver leaderboard actualizado en tiempo real

Flujo 2 — Coach:
1. Login como coach
2. Ver roster de clientes
3. Tap cliente → Ver métricas + check-ins
4. Crear nota privada tipo "logro"
5. Responder check-in pendiente
6. Ver analytics (adherencia, clientes en riesgo)

Flujo 3 — Admin:
1. Login como superadmin
2. Ver lista de todos los clientes
3. Impersonar cliente → verificar dashboard
4. Ver KPIs de pagos

- [ ] **Step 3: Ejecutar script de migración en staging**

```bash
# Usar un dump de la DB de producción PHP
cd wellcore-api
php artisan migrate:from-legacy --dry-run
# Revisar output sin errores
php artisan migrate:from-legacy
php artisan migrate:verify
```

- [ ] **Step 4: Performance test con Octane**

```bash
php artisan octane:start --server=swoole --port=8000
ab -n 1000 -c 50 http://wellcore-api.test/api/v1/auth/me
# Target: < 100ms p99
```

- [ ] **Step 5: Tag de release final**

```bash
git add .
git commit -m "feat: Fase 4 completa — Octane, Reverb, CI/CD, stores ready"
git tag v1.0.0-launch
git push origin main --tags
```

---

## Resumen Fase 4 — Entregables de Launch

| Entregable | Status |
|---|---|
| Laravel Reverb WebSockets (activity feed + leaderboard live) | ✅ |
| Script migración PHP→Laravel con verificación de integridad | ✅ |
| Laravel Octane (Swoole) para alto rendimiento | ✅ |
| Redis para cache + queues (reemplaza JSON files) | ✅ |
| Rate limiting avanzado con Redis | ✅ |
| CI/CD GitHub Actions (tests + deploy automático) | ✅ |
| Android App Bundle listo para Google Play | ✅ |
| iOS IPA listo para App Store | ✅ |
| Test suite > 80% coverage | ✅ |
| E2E tests de los 3 flujos principales | ✅ |
| Performance < 100ms p99 con Octane | ✅ |
| Tag v1.0.0-launch | ✅ |

---

## Roadmap Post-Launch (Fase 5 — Innovación 2026+)

Una vez lanzado, las siguientes features de la Zona F del estudio de mercado:

| Feature | Impacto | Esfuerzo |
|---------|---------|---------|
| AI Workout Builder (coach describe → IA genera) | Alto | Medio |
| AI Meal Planner personalizado | Alto | Medio |
| Computer Vision forma (cámara analiza postura) | Muy alto | Alto |
| Integración Oura Ring / WHOOP (HRV) | Alto | Medio |
| Move-to-earn sin crypto (recompensas reales) | Alto | Medio |
| White-label coaches (su propia "app WellCore") | Muy alto | Alto |
| Comunidad propia tipo Discord (canales por objetivo) | Alto | Medio |

---

*Documento generado: 2026-03-10*
*WellCore App Nativa v1.0.0 — Laravel 11 + Flutter 3.x*
*228 endpoints PHP migrados | 30+ tablas | App Store + Google Play*
