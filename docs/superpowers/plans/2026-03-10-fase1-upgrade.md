# WellCore App Nativa — FASE 1: Upgrade

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Completar feature parity con el proyecto PHP actual — Coach portal completo, Admin dashboard, Comunidad, Academia, Challenges con leaderboard, y Behavioral Triggers migrados a Laravel Queue.

**Architecture:** Extiende el monorepo de Fase 0. Se agregan 40+ nuevas rutas API, 6 nuevos módulos Flutter, y los crons PHP se convierten en Laravel Jobs disparados por el Scheduler. Prerequisito: Fase 0 completada y todos sus tests en verde.

**Tech Stack:** Laravel Queue (database driver), Laravel Scheduler, Pest PHP | Flutter Riverpod, fl_chart, video_player

**Prerequisito:** `v0.1.0-fase0` tag en git, todos los tests pasando.

---

## Chunk 1: Coach Portal Backend

### Task 1: APIs Coach completas

**Reference PHP:**
- `C:\Users\GODSF\Herd\wellcorefitness\api\coach\clients.php`
- `C:\Users\GODSF\Herd\wellcorefitness\api\coach\notes.php`

**Files:**
- Create: `wellcore-api/app/Http/Controllers/Api/V1/Coach/ClientsController.php`
- Create: `wellcore-api/app/Http/Controllers/Api/V1/Coach/NotesController.php`
- Create: `wellcore-api/app/Http/Controllers/Api/V1/Coach/MessagesController.php`
- Create: `wellcore-api/app/Http/Controllers/Api/V1/Coach/AnalyticsController.php`
- Create: `wellcore-api/app/Models/CoachNote.php`
- Create: `wellcore-api/app/Models/CoachMessage.php`
- Modify: `wellcore-api/routes/api.php`

- [ ] **Step 1: Migraciones coach_notes y coach_messages**

```bash
php artisan make:migration create_coach_notes_table
php artisan make:migration create_coach_messages_table
```

```php
// coach_notes
Schema::create('coach_notes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('coach_id')->constrained('users');
    $table->foreignId('user_id')->constrained('users'); // cliente
    $table->text('content');
    $table->enum('note_type', ['general', 'seguimiento', 'alerta', 'logro'])->default('general');
    $table->timestamps();
    $table->index(['coach_id', 'user_id']);
});

// coach_messages (chat 1:1)
Schema::create('coach_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('coach_id')->constrained('users');
    $table->foreignId('client_id')->constrained('users');
    $table->enum('direction', ['coach_to_client', 'client_to_coach']);
    $table->text('content');
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
    $table->index(['coach_id', 'client_id', 'created_at']);
});
```

- [ ] **Step 2: Tests Coach Portal**

```php
// tests/Feature/Coach/CoachClientsTest.php
it('returns coach client roster with last activity', function () {
    $coach = User::factory()->create(['role' => 'coach']);
    $clients = User::factory()->count(3)->create(['coach_id' => $coach->id, 'role' => 'client']);

    Sanctum::actingAs($coach);

    $this->getJson('/api/v1/coach/clients')
        ->assertOk()
        ->assertJsonCount(3, 'clients')
        ->assertJsonStructure(['clients' => [['id', 'name', 'plan', 'status']]]);
});

it('allows coach to create note for client', function () {
    $coach = User::factory()->create(['role' => 'coach']);
    $client = User::factory()->create(['coach_id' => $coach->id]);

    Sanctum::actingAs($coach);

    $this->postJson("/api/v1/coach/notes/{$client->id}", [
        'content' => 'Cliente mejoró su forma de sentadilla',
        'note_type' => 'logro',
    ])->assertCreated();
});

it('blocks non-coach from accessing coach routes', function () {
    $client = User::factory()->create(['role' => 'client']);
    Sanctum::actingAs($client);

    $this->getJson('/api/v1/coach/clients')->assertForbidden();
});
```

- [ ] **Step 3: Implementar CoachClientsController**

```php
// app/Http/Controllers/Api/V1/Coach/ClientsController.php
class ClientsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coach = $request->user();

        $clients = User::with(['profile', 'xp'])
            ->where('coach_id', $coach->id)
            ->where('role', 'client')
            ->withCount(['checkins as sessions_last_7d' => function ($q) {
                $q->where('checkin_date', '>=', now()->subDays(7));
            }])
            ->orderBy('status')
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'plan' => $c->plan,
                'status' => $c->status,
                'peso' => $c->profile?->peso,
                'objetivo' => $c->profile?->objetivo,
                'whatsapp' => $c->profile?->whatsapp,
                'sessions_last_7d' => $c->sessions_last_7d,
                'xp_level' => $c->xp?->level ?? 1,
                'streak_days' => $c->xp?->streak_days ?? 0,
            ]);

        return response()->json(['clients' => $clients, 'count' => $clients->count()]);
    }

    public function show(Request $request, User $client): JsonResponse
    {
        // Verificar que el cliente pertenece al coach
        abort_if($client->coach_id !== $request->user()->id, 403);

        return response()->json([
            'client' => $client->load(['profile', 'xp']),
            'recent_checkins' => $client->checkins()->orderByDesc('checkin_date')->limit(4)->get(),
            'recent_metrics' => $client->metrics()->orderByDesc('log_date')->limit(4)->get(),
        ]);
    }
}
```

- [ ] **Step 4: Middleware para verificar rol coach**

```php
// app/Http/Middleware/RequireRole.php
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array($request->user()?->role, $roles)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        return $next($request);
    }
}
```

- [ ] **Step 5: Reply de check-ins del coach**

```php
// Agregar al CheckinController — disponible para coach
public function reply(Request $request, Checkin $checkin): JsonResponse
{
    // Verificar que el coach tiene acceso al cliente
    abort_if($checkin->user->coach_id !== $request->user()->id, 403);

    $checkin->update([
        'coach_reply' => $request->validated()['reply'],
        'replied_at' => now(),
    ]);

    return response()->json(['data' => $checkin]);
}
```

- [ ] **Step 6: Ejecutar tests Coach**

```bash
./vendor/bin/pest tests/Feature/Coach/ -v
```

Resultado: PASS todos

- [ ] **Step 7: Commit coach portal backend**

```bash
git commit -m "feat: Coach portal APIs (roster, notes, messages, checkin reply, analytics)"
```

---

## Chunk 2: Admin Dashboard Backend

### Task 2: Admin APIs

**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\admin.html` (182KB), `api\admin\*`

- [ ] **Step 1: Tests Admin**

```php
// tests/Feature/Admin/AdminClientsTest.php
it('superadmin can list all clients', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    User::factory()->count(5)->create(['role' => 'client']);
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/clients')
        ->assertOk()
        ->assertJsonStructure(['clients', 'total', 'kpis']);
});

it('admin can impersonate a client', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client']);
    Sanctum::actingAs($admin);

    $response = $this->postJson("/api/v1/admin/impersonate/{$client->id}");
    $response->assertOk()->assertJsonStructure(['token', 'expires_in', 'client']);
});

it('non-admin cannot access admin routes', function () {
    $client = User::factory()->create(['role' => 'client']);
    Sanctum::actingAs($client);

    $this->getJson('/api/v1/admin/clients')->assertForbidden();
});
```

- [ ] **Step 2: Implementar AdminClientsController**

```php
class AdminClientsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['profile', 'xp'])
            ->where('role', 'client');

        if ($request->filled('plan')) {
            $query->where('plan', $request->plan);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where(fn($q) => $q
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
            );
        }

        $clients = $query->orderByDesc('created_at')->paginate(20);

        $kpis = [
            'total_activos' => User::where('role', 'client')->where('status', 'activo')->count(),
            'elite' => User::where('role', 'client')->where('plan', 'elite')->count(),
            'metodo' => User::where('role', 'client')->where('plan', 'metodo')->count(),
            'esencial' => User::where('role', 'client')->where('plan', 'esencial')->count(),
        ];

        return response()->json([
            'clients' => $clients->items(),
            'total' => $clients->total(),
            'kpis' => $kpis,
        ]);
    }

    public function impersonate(Request $request, User $client): JsonResponse
    {
        abort_if(! $request->user()->isAdmin(), 403);
        abort_if($client->role !== 'client', 422);

        $token = $client->createToken('impersonate', ['*'], now()->addHours(2))->plainTextToken;

        return response()->json([
            'token' => $token,
            'expires_in' => now()->addHours(2)->toISOString(),
            'client' => ['id' => $client->id, 'name' => $client->name, 'plan' => $client->plan],
        ]);
    }
}
```

- [ ] **Step 3: Commit admin backend**

```bash
git commit -m "feat: Admin APIs (clientes CRUD, impersonación, KPIs)"
```

---

## Chunk 3: Comunidad + Academy + Challenges

### Task 3: Community Backend

**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\api\community\posts.php` (175 líneas)

- [ ] **Step 1: Migración community**

```bash
php artisan make:migration create_community_tables
```

```php
Schema::create('community_posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->text('content');
    $table->enum('post_type', ['text', 'workout', 'milestone'])->default('text');
    $table->enum('audience', ['all', 'rise'])->default('all');
    $table->unsignedBigInteger('parent_id')->nullable(); // para replies
    $table->timestamps();
    $table->index(['audience', 'created_at']);
    $table->index('parent_id');
});

Schema::create('community_reactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained();
    $table->string('emoji', 10);
    $table->timestamps();
    $table->unique(['post_id', 'user_id', 'emoji']);
});
```

- [ ] **Step 2: Tests Community**

```php
it('creates a community post', function () {
    $user = User::factory()->create(['role' => 'client']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/community/posts', [
        'content' => '¡Logré mi meta de peso!',
        'post_type' => 'milestone',
        'audience' => 'all',
    ])->assertCreated();
});

it('strips HTML from post content', function () {
    $user = User::factory()->create(['role' => 'client']);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/community/posts', [
        'content' => '<script>alert("xss")</script>¡Hola!',
        'audience' => 'all',
    ])->assertCreated();

    expect($response->json('data.content'))->toBe('¡Hola!');
});
```

- [ ] **Step 3: PostController con sanitización**

```php
public function store(StoreCommunityPostRequest $request): JsonResponse
{
    $post = $request->user()->communityPosts()->create([
        'content' => strip_tags($request->content), // seguridad: sin HTML
        'post_type' => $request->post_type ?? 'text',
        'audience' => $request->audience ?? 'all',
        'parent_id' => $request->parent_id,
    ]);

    // XP por participar en comunidad
    GamificationService::earnXp($request->user(), 'community_post');

    return response()->json(['data' => $post], 201);
}
```

- [ ] **Step 4: Academy + Challenges (pattern similar)**

```bash
php artisan make:migration create_academy_tables
php artisan make:migration create_challenges_tables
php artisan make:controller Api/V1/Academy/ContentController --api
php artisan make:controller Api/V1/Challenges/ChallengeController --api
```

Reutilizar mismo patrón: tests primero → controller → migrar lógica del PHP.

- [ ] **Step 5: Ejecutar todos los tests**

```bash
./vendor/bin/pest --coverage
```

- [ ] **Step 6: Commit community + academy + challenges**

```bash
git commit -m "feat: Community posts/reactions, Academy, Challenges completos"
```

---

## Chunk 4: Behavioral Triggers → Laravel Queue

### Task 4: Migrar crons PHP a Laravel Jobs

**Reference PHP:** `C:\Users\GODSF\Herd\wellcorefitness\api\cron\behavioral-triggers.php` (164 líneas)

**Files:**
- Create: `wellcore-api/app/Jobs/SendBehavioralTrigger.php`
- Create: `wellcore-api/app/Jobs/ProcessAutoRenewal.php`
- Modify: `wellcore-api/app/Console/Kernel.php`

- [ ] **Step 1: Tests del Behavioral Trigger**

```php
// tests/Unit/BehavioralTriggerTest.php
it('identifies inactive clients for 7-day trigger', function () {
    // Cliente que no ha hecho check-in en 10 días
    $client = User::factory()->create(['role' => 'client', 'status' => 'activo']);
    Checkin::factory()->create([
        'user_id' => $client->id,
        'checkin_date' => now()->subDays(10),
    ]);

    $trigger = new SendBehavioralTrigger();
    $candidates = $trigger->getInactiveCandidates(7, 13);

    expect($candidates->contains('id', $client->id))->toBeTrue();
});

it('does not resend trigger already sent today', function () {
    $client = User::factory()->create(['role' => 'client']);
    AutoMessageLog::create([
        'user_id' => $client->id,
        'trigger_type' => 'inactive_7d',
        'date_sent' => today(),
    ]);

    $trigger = new SendBehavioralTrigger();
    $alreadySent = $trigger->alreadySentToday($client->id, 'inactive_7d');

    expect($alreadySent)->toBeTrue();
});
```

- [ ] **Step 2: Crear AutoMessageLog migration**

```php
Schema::create('auto_message_log', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users');
    $table->string('trigger_type');
    $table->enum('channel', ['email', 'push', 'whatsapp'])->default('email');
    $table->date('date_sent');
    $table->unique(['user_id', 'trigger_type', 'date_sent']); // deduplicación
    $table->timestamps();
});
```

- [ ] **Step 3: Implementar Job BehavioralTriggers**

```php
// app/Jobs/SendBehavioralTrigger.php
class SendBehavioralTrigger implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $triggers = [
            ['type' => 'inactive_7d',     'min_days' => 7,  'max_days' => 13],
            ['type' => 'inactive_14d',    'min_days' => 14, 'max_days' => 29],
            ['type' => 'subscription_7d', 'min_days' => 5,  'max_days' => 8,  'mode' => 'expiry'],
            ['type' => 'subscription_3d', 'min_days' => 2,  'max_days' => 4,  'mode' => 'expiry'],
            ['type' => 'milestone_4',     'count' => 4],
            ['type' => 'milestone_7',     'count' => 7],
            ['type' => 'birthday',        'mode' => 'birthday'],
            ['type' => 'welcome_day1',    'min_days' => 1,  'max_days' => 3, 'mode' => 'welcome'],
        ];

        foreach ($triggers as $trigger) {
            $this->processTrigger($trigger);
        }
    }

    private function processTrigger(array $trigger): void
    {
        $candidates = $this->getCandidates($trigger);

        foreach ($candidates as $client) {
            if ($this->alreadySentToday($client->id, $trigger['type'])) continue;

            SendTriggerEmail::dispatch($client, $trigger['type']);

            AutoMessageLog::create([
                'user_id' => $client->id,
                'trigger_type' => $trigger['type'],
                'channel' => 'email',
                'date_sent' => today(),
            ]);
        }
    }

    public function alreadySentToday(int $userId, string $triggerType): bool
    {
        return AutoMessageLog::where('user_id', $userId)
            ->where('trigger_type', $triggerType)
            ->whereDate('date_sent', today())
            ->exists();
    }
}
```

- [ ] **Step 4: Registrar en Scheduler (reemplaza supervisord cron)**

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Behavioral triggers — 8am diario (equivalente al cron PHP de producción)
    $schedule->job(new SendBehavioralTrigger)->dailyAt('08:00');

    // Auto-renewal — 7am diario
    $schedule->job(new ProcessAutoRenewal)->dailyAt('07:00');
}
```

- [ ] **Step 5: Ejecutar tests behavioral triggers**

```bash
./vendor/bin/pest tests/Unit/BehavioralTriggerTest.php -v
```

- [ ] **Step 6: Commit behavioral triggers**

```bash
git commit -m "feat: Behavioral triggers migrados a Laravel Queue (8 condiciones + deduplicación)"
```

---

## Chunk 5: Flutter Coach + Admin + Community

### Task 5: Coach Portal Flutter

**Files:**
- Create: `wellcore-app/lib/features/coach/coach_dashboard_screen.dart`
- Create: `wellcore-app/lib/features/coach/client_roster_screen.dart`
- Create: `wellcore-app/lib/features/coach/client_detail_screen.dart`
- Create: `wellcore-app/lib/features/coach/coach_messages_screen.dart`

- [ ] **Step 1: Coach Roster Screen**

La pantalla replica `coach-portal.html` (174KB). Lista de clientes con:
- Avatar + nombre + plan badge
- Streak activo (fuego 🔥 si streak_days > 0)
- Último check-in
- Sessions last 7 days
- Tap → ClientDetailScreen

```dart
// lib/features/coach/client_roster_screen.dart
class ClientRosterScreen extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final clientsAsync = ref.watch(coachClientsProvider);

    return clientsAsync.when(
      loading: () => const ClientRosterSkeleton(),
      error: (e, _) => ErrorView(message: e.toString()),
      data: (clients) => ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: clients.length,
        itemBuilder: (_, i) => ClientCard(
          client: clients[i],
          onTap: () => context.push('/coach/clients/${clients[i].id}'),
        ),
      ),
    );
  }
}
```

- [ ] **Step 2: Community Feed Flutter**

Feed con posts, reacciones emoji, replies — replica la sección comunidad de `cliente.html`.

```dart
// Reacciones con emoji picker
Row(
  children: ['🔥', '💪', '❤️', '👏'].map((emoji) =>
    InkWell(
      onTap: () => ref.read(communityProvider.notifier).react(post.id, emoji),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: WellCoreColors.surface2,
          borderRadius: BorderRadius.circular(100),
        ),
        child: Text('$emoji ${post.reactions[emoji] ?? 0}'),
      ),
    )
  ).toList(),
)
```

- [ ] **Step 3: Academy Screen Flutter**

Video player con chewie, lista de contenido con lock según plan.

- [ ] **Step 4: Challenges Screen Flutter con leaderboard**

Leaderboard animado con `fl_chart` (bar chart). Top 20 con nombre corto + progress bar.

- [ ] **Step 5: Test E2E en emulador**

```bash
flutter run -d android
```

Flujo Coach:
1. Login como `coachsilvia@wellcorefitness.com`
2. Ver roster de clientes
3. Tap cliente → ver métricas, check-ins, notas
4. Crear nota privada

- [ ] **Step 6: Commit Flutter Fase 1**

```bash
git commit -m "feat: Fase 1 — Coach portal, Admin, Community, Academy, Challenges en Flutter"
git tag v0.2.0-fase1
```

---

## Resumen Fase 1 — Entregables

| Entregable | Status |
|---|---|
| Coach portal APIs completo (roster, notes, messages, analytics) | ✅ |
| Admin APIs (clients, impersonate, KPIs, assign plans) | ✅ |
| Community posts + reactions + threads | ✅ |
| Academy content + progress tracking | ✅ |
| Challenges + leaderboard (top 20 + privacy) | ✅ |
| Behavioral Triggers → 8 Laravel Jobs con deduplicación | ✅ |
| Auto-Renewal → Laravel Job diario | ✅ |
| Flutter Coach portal screens | ✅ |
| Flutter Admin screens | ✅ |
| Flutter Community feed + reactions | ✅ |
| Flutter Academy con video + PDF | ✅ |
| Flutter Challenges + leaderboard animado | ✅ |
| Tests > 75% coverage | ✅ |

**Siguiente paso → Fase 2: Premium (RISE + AI + Video + Payments + WhatsApp)**
