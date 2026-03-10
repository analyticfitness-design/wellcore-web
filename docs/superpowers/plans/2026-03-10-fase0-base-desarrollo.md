# WellCore App Nativa — FASE 0: Base/Desarrollo

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Crear el monorepo `App wellcorefitness/` con Laravel 11 (backend API) y Flutter 3.x (app móvil), con auth completo, APIs core (profile, training, checkins, metrics, photos) y gamificación (XP, niveles, streaks) corriendo en Herd local.

**Architecture:** Monorepo con `wellcore-api/` (Laravel 11, Sanctum, MySQL nueva) y `wellcore-app/` (Flutter 3.x, Riverpod, go_router). El backend replica los 228 endpoints PHP del proyecto en `C:\Users\GODSF\Herd\wellcorefitness\` sin tocar producción. Base de datos completamente nueva y limpia en Herd.

**Tech Stack:** Laravel 11, Sanctum, Eloquent, Pest PHP, MySQL 8, Herd | Flutter 3.x, Riverpod, Dio, go_router, Hive, flutter_secure_storage, fl_chart

**Spec:** `docs/superpowers/specs/2026-03-10-wellcore-app-nativa-design.md`
**Producción INTOCABLE:** `C:\Users\GODSF\Herd\wellcorefitness\`
**Destino:** `C:\Users\GODSF\Herd\App wellcorefitness\`

---

## Chunk 1: Monorepo + Laravel Scaffolding

### Task 1: Crear estructura del monorepo

**Files:**
- Create: `C:\Users\GODSF\Herd\App wellcorefitness\.gitignore`
- Create: `C:\Users\GODSF\Herd\App wellcorefitness\README.md`

- [ ] **Step 1: Crear directorio raíz y estructura base**

```bash
cd "C:\Users\GODSF\Herd\App wellcorefitness"
git init
```

- [ ] **Step 2: Crear .gitignore del monorepo**

```
# wellcore-api
wellcore-api/.env
wellcore-api/vendor/
wellcore-api/storage/logs/*.log
wellcore-api/storage/app/public

# wellcore-app
wellcore-app/.dart_tool/
wellcore-app/build/
wellcore-app/.flutter-plugins
wellcore-app/.flutter-plugins-dependencies

# OS
.DS_Store
Thumbs.db
```

- [ ] **Step 3: Commit inicial**

```bash
git add .gitignore README.md
git commit -m "chore: init monorepo WellCore App Nativa"
```

---

### Task 2: Scaffolding Laravel 11

**Files:**
- Create: `wellcore-api/` (Laravel project completo)
- Create: `wellcore-api/.env.example`
- Modify: `wellcore-api/config/sanctum.php`

- [ ] **Step 1: Crear proyecto Laravel**

```bash
cd "C:\Users\GODSF\Herd\App wellcorefitness"
composer create-project laravel/laravel wellcore-api
cd wellcore-api
```

- [ ] **Step 2: Instalar dependencias core**

```bash
composer require laravel/sanctum
composer require dedoc/scramble          # API docs
composer require --dev pestphp/pest
composer require --dev pestphp/pest-plugin-laravel
./vendor/bin/pest --init
```

- [ ] **Step 3: Configurar .env para Herd**

```bash
# .env
APP_NAME="WellCore API"
APP_ENV=local
APP_KEY=         # se genera con artisan
APP_DEBUG=true
APP_URL=http://wellcore-api.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wellcore_app_dev
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=wellcore-api.test,localhost

QUEUE_CONNECTION=database
CACHE_STORE=database

# Claves externas (llenar en Fase 2)
CLAUDE_API_KEY=
WOMPI_PUBLIC_KEY=
WOMPI_PRIVATE_KEY=
FCM_SERVER_KEY=
```

- [ ] **Step 4: Generar app key y crear DB en Herd**

```bash
php artisan key:generate
# En Herd: crear base de datos wellcore_app_dev
```

- [ ] **Step 5: Configurar Sanctum**

```php
// config/sanctum.php — agregar expiración de tokens
'expiration' => null, // manejamos expiración manual como el PHP actual
'token_prefix' => 'wc_',
```

- [ ] **Step 6: Publicar configuración Sanctum**

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

- [ ] **Step 7: Commit scaffolding base**

```bash
git add wellcore-api/
git commit -m "feat: scaffolding Laravel 11 + Sanctum"
```

---

## Chunk 2: Migraciones y Modelos Base

### Task 3: Migraciones del schema nuevo

**Reference:** Ver spec sección 5 para lista completa de 30 migraciones.
**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\database\migrations\`

- [ ] **Step 1: Crear migración users unificada**

```bash
php artisan make:migration create_users_table
```

```php
// database/migrations/001_create_users_table.php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->enum('role', ['client', 'coach', 'admin', 'superadmin'])->default('client');
    $table->enum('plan', ['esencial', 'metodo', 'elite', 'rise'])->nullable();
    $table->enum('status', ['activo', 'inactivo', 'pendiente'])->default('activo');
    $table->string('client_code')->unique()->nullable();
    $table->unsignedBigInteger('coach_id')->nullable();
    $table->date('fecha_inicio')->nullable();
    $table->date('birth_date')->nullable();
    $table->timestamp('email_verified_at')->nullable();
    $table->rememberToken();
    $table->timestamps();
    $table->index(['role', 'status']);
    $table->index('coach_id');
});
```

- [ ] **Step 2: Crear migración client_profiles**

```bash
php artisan make:migration create_client_profiles_table
```

```php
Schema::create('client_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->integer('edad')->nullable();
    $table->decimal('peso', 5, 2)->nullable();
    $table->decimal('altura', 5, 2)->nullable();
    $table->string('objetivo')->nullable();
    $table->string('ciudad')->nullable();
    $table->string('whatsapp')->nullable();
    $table->enum('nivel', ['principiante', 'intermedio', 'avanzado'])->nullable();
    $table->enum('lugar_entreno', ['gym', 'home', 'hybrid'])->nullable();
    $table->json('dias_disponibles')->nullable();
    $table->text('restricciones')->nullable();
    $table->json('macros')->nullable();
    $table->text('bio')->nullable();
    $table->string('avatar_url')->nullable();
    $table->string('dashboard_video_url')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 3: Crear migraciones de métricas y training**

```bash
php artisan make:migration create_metrics_table
php artisan make:migration create_checkins_table
php artisan make:migration create_photos_table
```

```php
// metrics
Schema::create('metrics', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->date('log_date');
    $table->decimal('peso', 5, 2)->nullable();
    $table->decimal('porcentaje_grasa', 5, 2)->nullable();
    $table->decimal('porcentaje_musculo', 5, 2)->nullable();
    $table->decimal('pecho', 5, 2)->nullable();
    $table->decimal('cintura', 5, 2)->nullable();
    $table->decimal('cadera', 5, 2)->nullable();
    $table->decimal('muslo', 5, 2)->nullable();
    $table->decimal('brazo', 5, 2)->nullable();
    $table->text('notas')->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'log_date']);
});

// checkins
Schema::create('checkins', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('week', 10); // 2026-W10
    $table->date('checkin_date');
    $table->tinyInteger('bienestar')->nullable(); // 1-10
    $table->tinyInteger('dias_entrenados')->nullable();
    $table->enum('nutricion', ['Si', 'No', 'Parcial'])->nullable();
    $table->text('comentario')->nullable();
    $table->text('coach_reply')->nullable();
    $table->timestamp('replied_at')->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'checkin_date']);
});

// photos
Schema::create('photos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->date('photo_date');
    $table->enum('tipo', ['frente', 'lado', 'espalda']);
    $table->string('filename');
    $table->string('url');
    $table->timestamps();
    $table->index(['user_id', 'photo_date']);
});
```

- [ ] **Step 4: Crear migraciones de gamificación**

```bash
php artisan make:migration create_gamification_tables
```

```php
// client_xp
Schema::create('client_xp', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
    $table->unsignedInteger('xp_total')->default(0);
    $table->tinyInteger('level')->default(1);
    $table->unsignedInteger('streak_days')->default(0);
    $table->boolean('streak_protected')->default(false);
    $table->date('last_activity_date')->nullable();
    $table->timestamps();
});

// xp_events
Schema::create('xp_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('event_type'); // checkin, video_checkin, challenge, bonus, etc.
    $table->unsignedInteger('xp_gained');
    $table->string('description')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'created_at']);
});
```

- [ ] **Step 5: Ejecutar migraciones**

```bash
php artisan migrate
```

Resultado esperado: todas las tablas creadas sin errores.

- [ ] **Step 6: Commit migraciones base**

```bash
git add wellcore-api/database/
git commit -m "feat: migraciones base (users, profiles, metrics, checkins, photos, gamification)"
```

---

### Task 4: Modelos Eloquent base

**Files:**
- Create: `wellcore-api/app/Models/User.php`
- Create: `wellcore-api/app/Models/ClientProfile.php`
- Create: `wellcore-api/app/Models/Metric.php`
- Create: `wellcore-api/app/Models/Checkin.php`
- Create: `wellcore-api/app/Models/Photo.php`
- Create: `wellcore-api/app/Models/ClientXp.php`
- Create: `wellcore-api/app/Models/XpEvent.php`

- [ ] **Step 1: Actualizar modelo User**

```php
// app/Models/User.php
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'plan',
        'status', 'client_code', 'coach_id', 'fecha_inicio', 'birth_date',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'fecha_inicio' => 'date',
        'birth_date' => 'date',
    ];

    public function profile(): HasOne
    {
        return $this->hasOne(ClientProfile::class);
    }

    public function xp(): HasOne
    {
        return $this->hasOne(ClientXp::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(User::class, 'coach_id');
    }

    public function isCoach(): bool { return $this->role === 'coach'; }
    public function isAdmin(): bool { return in_array($this->role, ['admin', 'superadmin']); }
    public function isClient(): bool { return $this->role === 'client'; }

    public function hasPlan(string $minPlan): bool
    {
        $hierarchy = ['esencial' => 1, 'metodo' => 2, 'elite' => 3, 'rise' => 1];
        return ($hierarchy[$this->plan] ?? 0) >= ($hierarchy[$minPlan] ?? 0);
    }
}
```

- [ ] **Step 2: Escribir tests de modelo User**

```php
// tests/Unit/UserModelTest.php
it('can determine role correctly', function () {
    $client = User::factory()->create(['role' => 'client']);
    $coach = User::factory()->create(['role' => 'coach']);

    expect($client->isClient())->toBeTrue()
        ->and($client->isCoach())->toBeFalse()
        ->and($coach->isCoach())->toBeTrue();
});

it('checks plan hierarchy correctly', function () {
    $elite = User::factory()->create(['plan' => 'elite']);
    $esencial = User::factory()->create(['plan' => 'esencial']);

    expect($elite->hasPlan('metodo'))->toBeTrue()
        ->and($esencial->hasPlan('elite'))->toBeFalse();
});
```

- [ ] **Step 3: Ejecutar tests del modelo**

```bash
cd wellcore-api && ./vendor/bin/pest tests/Unit/UserModelTest.php
```

Resultado esperado: PASS 2 tests

- [ ] **Step 4: Commit modelos base**

```bash
git add wellcore-api/app/Models/ wellcore-api/tests/
git commit -m "feat: modelos Eloquent base + tests"
```

---

## Chunk 3: Auth con Sanctum

### Task 5: Sistema de autenticación

**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\api\includes\auth.php`
**Files:**
- Create: `wellcore-api/app/Http/Controllers/Api/V1/Auth/LoginController.php`
- Create: `wellcore-api/app/Http/Controllers/Api/V1/Auth/MeController.php`
- Create: `wellcore-api/app/Http/Controllers/Api/V1/Auth/LogoutController.php`
- Create: `wellcore-api/app/Http/Requests/LoginRequest.php`
- Modify: `wellcore-api/routes/api.php`

- [ ] **Step 1: Escribir tests de autenticación primero (TDD)**

```php
// tests/Feature/Auth/LoginTest.php
it('logs in a client with valid credentials', function () {
    $user = User::factory()->create([
        'role' => 'client',
        'plan' => 'elite',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token', 'expires_in', 'user' => ['id', 'name', 'email', 'role', 'plan']
        ]);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('correct')]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong',
    ])->assertUnauthorized();
});

it('returns 401 for inactive account', function () {
    $user = User::factory()->create([
        'status' => 'inactivo',
        'password' => bcrypt('password'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertUnauthorized();
});

it('returns authenticated user on /me', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email);
});
```

- [ ] **Step 2: Ejecutar tests — verificar que fallan**

```bash
./vendor/bin/pest tests/Feature/Auth/ -v
```

Resultado esperado: FAIL (rutas no existen aún)

- [ ] **Step 3: Crear LoginController**

```php
// app/Http/Controllers/Api/V1/Auth/LoginController.php
class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        if ($user->status !== 'activo') {
            return response()->json(['error' => 'Cuenta inactiva'], 401);
        }

        // Single session para admins (igual que PHP actual)
        if ($user->isAdmin()) {
            $user->tokens()->delete();
        }

        $expiresAt = $request->remember_me
            ? now()->addDays(30)
            : ($user->isAdmin() ? now()->addHours(72) : now()->addHours(168));

        $token = $user->createToken('app', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'token' => $token,
            'expires_in' => $expiresAt->toISOString(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'plan' => $user->plan,
                'status' => $user->status,
                'client_code' => $user->client_code,
            ],
        ]);
    }
}
```

- [ ] **Step 4: Crear rutas API v1**

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    // Auth (público)
    Route::post('auth/login', LoginController::class);

    // Rutas protegidas
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', MeController::class);
        Route::post('auth/logout', LogoutController::class);

        // Client routes (Fase 0)
        Route::middleware('role:client')->group(function () {
            Route::apiResource('metrics', MetricController::class)->only(['index', 'store']);
            Route::apiResource('checkins', CheckinController::class)->only(['index', 'store']);
            Route::get('training/week', [TrainingController::class, 'week']);
            Route::post('training/toggle', [TrainingController::class, 'toggle']);
            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);
            Route::apiResource('photos', PhotoController::class)->only(['index', 'store']);
            Route::get('gamification/status', [XpController::class, 'status']);
            Route::post('gamification/earn-xp', [XpController::class, 'earn']);
            Route::get('gamification/leaderboard', [LeaderboardController::class, 'index']);
        });
    });
});
```

- [ ] **Step 5: Ejecutar tests — verificar que pasan**

```bash
./vendor/bin/pest tests/Feature/Auth/ -v
```

Resultado esperado: PASS 4 tests

- [ ] **Step 6: Commit auth**

```bash
git commit -m "feat: auth completo con Sanctum (login/me/logout) + tests"
```

---

## Chunk 4: APIs Core Backend

### Task 6: API de métricas

**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\api\metrics\index.php`

- [ ] **Step 1: Tests de métricas**

```php
// tests/Feature/MetricsTest.php
it('returns metrics history for authenticated client', function () {
    $user = User::factory()->client()->create();
    Metric::factory()->count(5)->for($user)->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/metrics')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

it('saves new metric entry', function () {
    $user = User::factory()->client()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/metrics', [
        'peso' => 75.5,
        'porcentaje_grasa' => 18.0,
        'porcentaje_musculo' => 42.0,
    ])->assertCreated();

    expect(Metric::where('user_id', $user->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Crear MetricController**

```php
// app/Http/Controllers/Api/V1/Client/MetricController.php
class MetricController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $metrics = $request->user()
            ->metrics()
            ->orderByDesc('log_date')
            ->limit($request->integer('limit', 12))
            ->get();

        return response()->json(['data' => $metrics]);
    }

    public function store(StoreMetricRequest $request): JsonResponse
    {
        $metric = $request->user()->metrics()->updateOrCreate(
            ['log_date' => today()],
            $request->validated()
        );

        return response()->json(['data' => $metric], 201);
    }
}
```

- [ ] **Step 3: Tests PASS**

```bash
./vendor/bin/pest tests/Feature/MetricsTest.php -v
```

- [ ] **Step 4: Commit métricas**

```bash
git commit -m "feat: API métricas con upsert diario"
```

---

### Task 7: API de check-ins

**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\api\checkins\index.php`
**Nota:** Check-ins reales en PHP triggean webhook a N8n — en Laravel usar Job

- [ ] **Step 1: Tests check-ins**

```php
it('submits a weekly check-in', function () {
    $user = User::factory()->create(['plan' => 'elite']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/checkins', [
        'week' => '2026-W10',
        'bienestar' => 7,
        'dias_entrenados' => 4,
        'nutricion' => 'Parcial',
        'comentario' => 'Buena semana',
    ])->assertCreated();

    // Verifica que se dispara job de notificación
    Queue::assertPushed(NotifyCoachNewCheckin::class);
});
```

- [ ] **Step 2: Crear CheckinController con dispatch**

```php
public function store(StoreCheckinRequest $request): JsonResponse
{
    $checkin = $request->user()->checkins()->updateOrCreate(
        ['checkin_date' => today()],
        array_merge($request->validated(), ['week' => now()->format('Y-\WW')])
    );

    // Notificar al coach asíncrono (reemplaza webhook N8n del PHP)
    NotifyCoachNewCheckin::dispatch($checkin);

    // Dar XP automáticamente
    GamificationService::earnXp($request->user(), 'checkin');

    return response()->json(['data' => $checkin], 201);
}
```

- [ ] **Step 3: Ejecutar tests**

```bash
./vendor/bin/pest tests/Feature/CheckinsTest.php -v
```

- [ ] **Step 4: Commit check-ins**

```bash
git commit -m "feat: API checkins + dispatch job coach + auto XP"
```

---

## Chunk 5: Servicio de Gamificación

### Task 8: GamificationService — corazón del sistema

**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\api\gamification\earn-xp.php` y `get-status.php`
**Files:**
- Create: `wellcore-api/app/Services/GamificationService.php`

- [ ] **Step 1: Escribir tests exhaustivos del servicio**

```php
// tests/Unit/GamificationServiceTest.php
it('awards XP for checkin event', function () {
    $user = User::factory()->create();
    ClientXp::create(['user_id' => $user->id, 'xp_total' => 0, 'level' => 1]);

    GamificationService::earnXp($user, 'checkin');

    expect($user->fresh()->xp->xp_total)->toBe(50);
});

it('promotes level when threshold is reached', function () {
    $user = User::factory()->create();
    ClientXp::create(['user_id' => $user->id, 'xp_total' => 190, 'level' => 1]);

    GamificationService::earnXp($user, 'checkin'); // +50 = 240 XP → nivel 2

    expect($user->fresh()->xp->level)->toBe(2);
});

it('increments streak on daily activity', function () {
    $user = User::factory()->create();
    ClientXp::create([
        'user_id' => $user->id,
        'xp_total' => 0,
        'level' => 1,
        'streak_days' => 6,
        'last_activity_date' => today()->subDay(),
    ]);

    GamificationService::earnXp($user, 'checkin');

    $xp = $user->fresh()->xp;
    expect($xp->streak_days)->toBe(7);
});

it('grants bonus XP on 7-day streak milestone', function () {
    $user = User::factory()->create();
    ClientXp::create([
        'user_id' => $user->id,
        'xp_total' => 0,
        'level' => 1,
        'streak_days' => 6,
        'last_activity_date' => today()->subDay(),
    ]);

    GamificationService::earnXp($user, 'checkin');

    // 50 (checkin) + 150 (streak_7 bonus) = 200
    expect($user->fresh()->xp->xp_total)->toBe(200);
});
```

- [ ] **Step 2: Ejecutar tests — verificar que fallan**

```bash
./vendor/bin/pest tests/Unit/GamificationServiceTest.php -v
```

- [ ] **Step 3: Implementar GamificationService**

```php
// app/Services/GamificationService.php
class GamificationService
{
    const LEVELS = [
        1 => 0, 2 => 200, 3 => 500, 4 => 1000, 5 => 2000, 6 => 4000,
    ];

    const XP_EVENTS = [
        'checkin'       => 50,
        'video_checkin' => 80,
        'challenge'     => 200,
        'badge'         => 100,
        'referral'      => 300,
    ];

    public static function earnXp(User $user, string $eventType, int $customAmount = 0): array
    {
        $amount = $customAmount ?: (self::XP_EVENTS[$eventType] ?? 0);

        DB::transaction(function () use ($user, $eventType, $amount) {
            $xp = ClientXp::firstOrCreate(
                ['user_id' => $user->id],
                ['xp_total' => 0, 'level' => 1, 'streak_days' => 0]
            );

            // Actualizar racha
            $streakBonus = self::updateStreak($xp);
            $total = $amount + $streakBonus;

            $xp->increment('xp_total', $total);
            $xp->level = self::calculateLevel($xp->fresh()->xp_total);
            $xp->save();

            XpEvent::create([
                'user_id' => $user->id,
                'event_type' => $eventType,
                'xp_gained' => $total,
                'description' => "Ganaste {$total} XP por {$eventType}",
            ]);
        });

        return ['xp_gained' => $amount, 'user_xp' => $user->fresh()->xp];
    }

    private static function updateStreak(ClientXp $xp): int
    {
        $bonus = 0;
        $lastActivity = $xp->last_activity_date;

        if ($lastActivity === null || $lastActivity->lt(today()->subDay())) {
            $xp->streak_days = $lastActivity?->isYesterday() ? $xp->streak_days + 1 : 1;
        }

        $xp->last_activity_date = today();

        // Bonus en hitos de racha
        if ($xp->streak_days === 7) $bonus = 150;
        if ($xp->streak_days === 30) $bonus = 500;

        $xp->save();
        return $bonus;
    }

    private static function calculateLevel(int $xpTotal): int
    {
        $level = 1;
        foreach (self::LEVELS as $lvl => $threshold) {
            if ($xpTotal >= $threshold) $level = $lvl;
        }
        return $level;
    }

    public static function getStatus(User $user): array
    {
        $xp = $user->xp ?? ClientXp::firstOrCreate(['user_id' => $user->id]);
        $nextThreshold = self::LEVELS[min($xp->level + 1, 6)] ?? 4000;
        $currentThreshold = self::LEVELS[$xp->level];

        return [
            'xp_total' => $xp->xp_total,
            'level' => $xp->level,
            'level_name' => self::levelName($xp->level),
            'xp_next_level' => $nextThreshold,
            'xp_progress_pct' => $nextThreshold > 0
                ? round(($xp->xp_total - $currentThreshold) / ($nextThreshold - $currentThreshold) * 100)
                : 100,
            'streak_days' => $xp->streak_days,
            'streak_active' => $xp->last_activity_date?->isToday() ?? false,
            'recent_events' => XpEvent::where('user_id', $user->id)
                ->orderByDesc('created_at')->limit(5)->get(),
        ];
    }

    private static function levelName(int $level): string
    {
        return match($level) {
            1 => 'Iniciado', 2 => 'Comprometido', 3 => 'Constante',
            4 => 'Dedicado', 5 => 'Elite', 6 => 'Leyenda',
            default => 'Iniciado'
        };
    }
}
```

- [ ] **Step 4: Ejecutar tests — verificar que pasan**

```bash
./vendor/bin/pest tests/Unit/GamificationServiceTest.php -v
```

Resultado esperado: PASS 4 tests

- [ ] **Step 5: Commit gamification service**

```bash
git commit -m "feat: GamificationService completo (XP, niveles, streaks, bonus milestones)"
```

---

## Chunk 6: Flutter App Base

### Task 9: Scaffolding Flutter + Design System

**Files:**
- Create: `wellcore-app/` (Flutter project)
- Create: `wellcore-app/lib/core/theme/wellcore_theme.dart`
- Create: `wellcore-app/lib/core/theme/wellcore_colors.dart`

- [ ] **Step 1: Crear proyecto Flutter**

```bash
cd "C:\Users\GODSF\Herd\App wellcorefitness"
flutter create --org com.wellcorefitness --project-name wellcore_app wellcore-app
cd wellcore-app
```

- [ ] **Step 2: Agregar dependencias en pubspec.yaml**

```yaml
dependencies:
  flutter:
    sdk: flutter
  flutter_riverpod: ^2.5.1
  riverpod_annotation: ^2.3.5
  go_router: ^13.2.0
  dio: ^5.4.3
  retrofit: ^4.1.0
  flutter_secure_storage: ^9.0.0
  hive_flutter: ^1.1.0
  fl_chart: ^0.68.0
  image_picker: ^1.0.7
  video_player: ^2.8.3
  chewie: ^1.7.5
  flutter_animate: ^4.5.0
  cached_network_image: ^3.3.1
  intl: ^0.19.0

dev_dependencies:
  flutter_test:
    sdk: flutter
  build_runner: ^2.4.9
  riverpod_generator: ^2.3.9
  retrofit_generator: ^8.1.0
  hive_generator: ^2.0.1
  mockito: ^5.4.4
```

```bash
flutter pub get
```

- [ ] **Step 3: Crear design system — colores exactos del v7**

```dart
// lib/core/theme/wellcore_colors.dart
class WellCoreColors {
  static const primary    = Color(0xFFE31E24); // --wc-red
  static const primaryLight = Color(0xFFFF4A4F);
  static const primaryDark  = Color(0xFFB8181D);
  static const gold       = Color(0xFFD4A853); // logros
  static const success    = Color(0xFF22C55E);
  static const warning    = Color(0xFFF59E0B);

  static const canvas   = Color(0xFF0A0A0A);  // fondo raíz
  static const surface0 = Color(0xFF111113);
  static const surface1 = Color(0xFF18181B);
  static const surface2 = Color(0xFF1A1A1D);
  static const surface3 = Color(0xFF222225);

  static const textPrimary = Color(0xFFFFFFFF);
  static const textDim     = Color(0x73FFFFFF); // 45%
  static const textMuted   = Color(0x2EFFFFFF); // 18%

  static const border      = Color(0x0FFFFFFF); // 6% white
  static const borderRed   = Color(0x33E31E24); // 20% red
}
```

```dart
// lib/core/theme/wellcore_theme.dart
ThemeData buildWellCoreTheme() {
  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    scaffoldBackgroundColor: WellCoreColors.canvas,
    colorScheme: const ColorScheme.dark(
      primary: WellCoreColors.primary,
      surface: WellCoreColors.surface1,
      background: WellCoreColors.canvas,
      onPrimary: Colors.white,
      onSurface: WellCoreColors.textPrimary,
    ),
    cardTheme: CardTheme(
      color: WellCoreColors.surface1,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: WellCoreColors.border),
      ),
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: WellCoreColors.surface0,
      elevation: 0,
      foregroundColor: WellCoreColors.textPrimary,
    ),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      backgroundColor: WellCoreColors.surface0,
      selectedItemColor: WellCoreColors.primary,
      unselectedItemColor: WellCoreColors.textMuted,
      type: BottomNavigationBarType.fixed,
    ),
  );
}
```

- [ ] **Step 4: Commit Flutter base + design system**

```bash
cd wellcore-app && git add . && git commit -m "feat: Flutter base + WellCore design system (tokens v7)"
```

---

### Task 10: API Client Flutter + Auth

**Reference JS:** `C:\Users\GODSF\Herd\wellcorefitness\js\api.js`
**Files:**
- Create: `wellcore-app/lib/core/api/api_client.dart`
- Create: `wellcore-app/lib/core/auth/auth_notifier.dart`
- Create: `wellcore-app/lib/core/auth/secure_storage.dart`

- [ ] **Step 1: Crear API client con Dio**

```dart
// lib/core/api/api_client.dart
@RestApi(baseUrl: 'http://wellcore-api.test/api/v1')
abstract class WellCoreApiClient {
  factory WellCoreApiClient(Dio dio, {String baseUrl}) = _WellCoreApiClient;

  @POST('/auth/login')
  Future<LoginResponse> login(@Body() LoginRequest request);

  @GET('/auth/me')
  Future<MeResponse> me();

  @GET('/metrics')
  Future<MetricsResponse> getMetrics({@Query('limit') int? limit});

  @POST('/metrics')
  Future<MetricResponse> saveMetric(@Body() MetricData data);

  @GET('/checkins')
  Future<CheckinsResponse> getCheckins({@Query('limit') int? limit});

  @POST('/checkins')
  Future<CheckinResponse> submitCheckin(@Body() CheckinData data);

  @GET('/gamification/status')
  Future<GamificationStatus> getGamificationStatus();

  @GET('/gamification/leaderboard')
  Future<LeaderboardResponse> getLeaderboard({@Query('period') String? period});
}
```

- [ ] **Step 2: Crear Auth Notifier con Riverpod**

```dart
// lib/core/auth/auth_notifier.dart
@riverpod
class AuthNotifier extends _$AuthNotifier {
  @override
  Future<User?> build() async {
    final token = await SecureStorage.getToken();
    if (token == null) return null;
    return ref.read(apiClientProvider).me().then((r) => r.user);
  }

  Future<void> login(String email, String password) async {
    final response = await ref.read(apiClientProvider).login(
      LoginRequest(email: email, password: password),
    );
    await SecureStorage.saveToken(response.token);
    ref.invalidateSelf();
  }

  Future<void> logout() async {
    await SecureStorage.clearToken();
    ref.invalidateSelf();
  }
}
```

- [ ] **Step 3: Crear LoginScreen**

```dart
// lib/features/auth/login_screen.dart
class LoginScreen extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      backgroundColor: WellCoreColors.canvas,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // Logo WellCore con rojo #E31E24
              Image.asset('assets/images/wellcore-logo.png', height: 48),
              const SizedBox(height: 48),
              WCTextField(label: 'Email', controller: emailController),
              const SizedBox(height: 16),
              WCTextField(label: 'Contraseña', obscure: true, controller: passController),
              const SizedBox(height: 24),
              WCButton(
                label: 'Iniciar sesión',
                onTap: () => ref.read(authNotifierProvider.notifier)
                    .login(emailController.text, passController.text),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
```

- [ ] **Step 4: Crear go_router con guards de auth**

```dart
// lib/core/router/app_router.dart
@riverpod
GoRouter appRouter(AppRouterRef ref) {
  final auth = ref.watch(authNotifierProvider);

  return GoRouter(
    initialLocation: '/login',
    redirect: (context, state) {
      final isLoggedIn = auth.valueOrNull != null;
      if (!isLoggedIn && state.matchedLocation != '/login') return '/login';
      if (isLoggedIn && state.matchedLocation == '/login') {
        final role = auth.value?.role;
        return role == 'coach' ? '/coach' : role == 'admin' ? '/admin' : '/client';
      }
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      ShellRoute(
        builder: (_, __, child) => ClientShell(child: child),
        routes: [
          GoRoute(path: '/client', builder: (_, __) => const ClientDashboardScreen()),
          GoRoute(path: '/client/training', builder: (_, __) => const TrainingScreen()),
          GoRoute(path: '/client/checkin', builder: (_, __) => const CheckinScreen()),
          GoRoute(path: '/client/metrics', builder: (_, __) => const MetricsScreen()),
        ],
      ),
    ],
  );
}
```

- [ ] **Step 5: Commit Flutter auth + routing**

```bash
git commit -m "feat: Flutter auth completo (Dio client, Riverpod, go_router con guards)"
```

---

### Task 11: Dashboard + XP Widget Flutter

**Files:**
- Create: `wellcore-app/lib/features/dashboard/client_dashboard_screen.dart`
- Create: `wellcore-app/lib/features/gamification/widgets/xp_widget.dart`
- Create: `wellcore-app/lib/features/gamification/widgets/streak_badge.dart`

- [ ] **Step 1: XP Widget — replicar el widget de cliente.html**

```dart
// lib/features/gamification/widgets/xp_widget.dart
class XpWidget extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final status = ref.watch(gamificationStatusProvider);

    return status.when(
      loading: () => const XpWidgetSkeleton(),
      error: (_, __) => const SizedBox.shrink(),
      data: (data) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: WellCoreColors.surface1,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: WellCoreColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(data.levelName,
                  style: const TextStyle(color: WellCoreColors.primary, fontWeight: FontWeight.bold)),
                StreakBadge(days: data.streakDays),
              ],
            ),
            const SizedBox(height: 8),
            LinearProgressIndicator(
              value: data.xpProgressPct / 100,
              backgroundColor: WellCoreColors.surface3,
              valueColor: const AlwaysStoppedAnimation(WellCoreColors.primary),
            ),
            const SizedBox(height: 4),
            Text('${data.xpTotal} / ${data.xpNextLevel} XP',
              style: const TextStyle(color: WellCoreColors.textDim, fontSize: 12)),
          ],
        ),
      ),
    );
  }
}
```

- [ ] **Step 2: Dashboard screen completo**

El dashboard replica la vista principal de `cliente.html`: XP widget en el top, cards de acceso rápido (Entrena, Check-in, Métricas, Fotos), y accesos al resto de secciones.

- [ ] **Step 3: Probar en emulador Android**

```bash
flutter run -d android
```

- [ ] **Step 4: Commit dashboard + XP widget**

```bash
git commit -m "feat: ClientDashboard + XP widget + streak badge Flutter"
```

---

## Chunk 7: Seeders y Verificación Final Fase 0

### Task 12: Seeders con datos demo

**Files:**
- Create: `wellcore-api/database/seeders/WellCoreDemoSeeder.php`

- [ ] **Step 1: Crear seeder con datos equivalentes a producción demo**

```php
// database/seeders/WellCoreDemoSeeder.php
class WellCoreDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::factory()->create([
            'name' => 'Daniel Esparza', 'email' => 'daniel.esparza@wellcorefitness.com',
            'password' => bcrypt('RISE2026Admin!SuperPower'), 'role' => 'superadmin',
        ]);

        // Coach
        $coach = User::factory()->create([
            'name' => 'Silvia Carvajal', 'email' => 'coachsilvia@wellcorefitness.com',
            'password' => bcrypt('Coach2026!'), 'role' => 'coach',
        ]);

        // Clientes demo (equivalentes a los de producción)
        $clients = [
            ['name' => 'Carlos Rodriguez', 'email' => 'carlos.rodriguez@email.com', 'plan' => 'elite'],
            ['name' => 'María García', 'email' => 'maria.garcia@email.com', 'plan' => 'metodo'],
            ['name' => 'Juan Pérez', 'email' => 'juan.perez@email.com', 'plan' => 'esencial'],
        ];

        foreach ($clients as $clientData) {
            $client = User::factory()->create(array_merge($clientData, [
                'role' => 'client', 'coach_id' => $coach->id,
                'password' => bcrypt('Client2026!'),
            ]));

            ClientXp::create(['user_id' => $client->id, 'xp_total' => rand(100, 800), 'level' => rand(1, 4)]);
        }
    }
}
```

- [ ] **Step 2: Ejecutar seeders**

```bash
php artisan db:seed --class=WellCoreDemoSeeder
```

- [ ] **Step 3: Ejecutar TODOS los tests**

```bash
./vendor/bin/pest --coverage
```

Resultado esperado: todos los tests en verde, coverage > 70%

- [ ] **Step 4: Verificar Flutter corre contra API local**

```bash
# Terminal 1 — API Laravel
cd wellcore-api && php artisan serve --host=wellcore-api.test

# Terminal 2 — Flutter
cd wellcore-app && flutter run -d android
```

Flujo de prueba:
1. Login con `carlos.rodriguez@email.com` / `Client2026!` → Dashboard
2. Ver XP widget y streak
3. Ir a Métricas → guardar peso
4. Ir a Check-ins → enviar check-in

- [ ] **Step 5: Commit final Fase 0**

```bash
git add .
git commit -m "feat: Fase 0 completa — Laravel API + Flutter app + auth + métricas + checkins + gamificación"
git tag v0.1.0-fase0
```

---

## Resumen Fase 0 — Entregables

| Entregable | Status |
|---|---|
| Monorepo `App wellcorefitness/` inicializado | ✅ |
| Laravel 11 con Sanctum corriendo en Herd | ✅ |
| 30 migraciones ejecutadas (schema limpio) | ✅ |
| Auth: login, me, logout con roles | ✅ |
| APIs: profile, metrics, checkins, photos | ✅ |
| GamificationService: XP, niveles, streaks, bonus | ✅ |
| Flutter: design system v7 replicado | ✅ |
| Flutter: auth flow + go_router + guards | ✅ |
| Flutter: ClientDashboard + XP widget + streak | ✅ |
| Seeders con clientes demo | ✅ |
| Tests > 70% coverage | ✅ |

**Siguiente paso → Fase 1: Upgrade (Coach portal + Admin + Community + Challenges)**
