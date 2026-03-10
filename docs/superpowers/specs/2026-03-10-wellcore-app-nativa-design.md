# WellCore App Nativa — Diseño Arquitectural Completo
**Fecha:** 2026-03-10
**Stack:** Laravel 11 (API REST) + Flutter 3.x (iOS + Android)
**Destino local:** `C:\Users\GODSF\Herd\App wellcorefitness\`
**Producción actual (NO TOCAR):** `C:\Users\GODSF\Herd\wellcorefitness\`

---

## 1. VISIÓN GENERAL

WellCore pasa de una PWA PHP monolítica a una **arquitectura cliente-servidor moderna** donde:

- **`wellcore-api/`** — Laravel 11, API REST versionada `/api/v1/`, reemplaza los 228 endpoints PHP
- **`wellcore-app/`** — Flutter 3.x, app nativa iOS + Android, reemplaza las 31 páginas HTML

La producción PHP sigue operando **sin interrupciones** hasta que el nuevo stack esté certificado y listo para el migration script final.

```
App wellcorefitness/
├── wellcore-api/          ← Laravel 11
├── wellcore-app/          ← Flutter 3.x
├── .gitignore
└── README.md
```

---

## 2. PRINCIPIOS DE DISEÑO

1. **Zero downtime migration** — PHP en producción nunca se toca durante el desarrollo
2. **Feature parity first** — cada módulo PHP existente se migra antes de añadir features nuevas
3. **Offline-first mobile** — Flutter usa Hive cache para funcionar sin conexión
4. **Design system fiel** — los tokens v7 (colores OKLCH, tipografía Axiforma, spacing 4px) se replican 1:1 en Flutter ThemeData
5. **Competitive upgrades** — las 38 opciones del estudio de mercado se distribuyen en fases post-parity

---

## 3. BACKEND — wellcore-api (Laravel 11)

### Stack Laravel

| Capa | Paquete | Reemplaza |
|------|---------|-----------|
| Framework | Laravel 11 | PHP vanilla |
| Auth | Laravel Sanctum | `auth_tokens` manual + `auth.php` |
| ORM | Eloquent + Relationships | PDO prepared statements |
| Queues | Laravel Queue + Jobs | Crons supervisord |
| Push | Laravel Notifications + FCM | Web Push VAPID |
| Pagos | Wompi SDK wrapeado | `wompi/` PHP actual |
| Storage | Laravel Storage (S3 ready) | `uploads/` directorio |
| Cache | Redis via Laravel Cache | localStorage + JSON files |
| Tests | Pest PHP | Sin tests actuales |
| Documentación | L5-Swagger / Scribe | Sin docs actuales |

### Estructura de carpetas Laravel

```
wellcore-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Auth/           LoginController, MeController, LogoutController
│   │   │   ├── Client/         ProfileController, MetricsController, PhotosController
│   │   │   ├── Training/       ProgramController, LogController, WeekController
│   │   │   ├── Checkins/       CheckinController
│   │   │   ├── Gamification/   XpController, LeaderboardController, AchievementController
│   │   │   ├── Challenges/     ChallengeController, LeaderboardController
│   │   │   ├── Community/      PostController, ReactionController
│   │   │   ├── Academy/        ContentController, ProgressController
│   │   │   ├── Notifications/  NotificationController, PushController
│   │   │   ├── Coach/          ClientsController, NotesController, MessagesController
│   │   │   ├── Admin/          ClientsController, PaymentsController, ImpersonateController
│   │   │   ├── Rise/           EnrollController, IntakeController, StatusController
│   │   │   ├── Payments/       WompiController, PaymentMethodController
│   │   │   ├── Ai/             ChatController, GenerateController
│   │   │   └── Shop/           ProductController, OrderController
│   │   ├── Middleware/
│   │   │   ├── RequireClientPlan.php   (reemplaza requirePlan())
│   │   │   ├── SingleSessionAdmin.php  (reemplaza single-session logic)
│   │   │   └── RateLimit*.php
│   │   └── Requests/           (Form validation por endpoint)
│   ├── Models/
│   │   ├── User.php (clients), Admin.php, Coach.php
│   │   ├── ClientProfile.php, AuthToken.php
│   │   ├── Metric.php, Training.php, Checkin.php, Photo.php
│   │   ├── ClientXp.php, XpEvent.php, Achievement.php
│   │   ├── Challenge.php, ChallengeParticipant.php
│   │   ├── CommunityPost.php, CommunityReaction.php
│   │   ├── AcademyContent.php, AcademyProgress.php
│   │   ├── Notification.php, PushSubscription.php
│   │   ├── CoachNote.php, CoachMessage.php
│   │   ├── Payment.php, PaymentMethod.php, AutoChargeLog.php
│   │   ├── RiseProgram.php, AssignedPlan.php
│   │   └── ShopProduct.php, ShopOrder.php
│   ├── Services/
│   │   ├── GamificationService.php   (lógica XP + niveles + streaks)
│   │   ├── WompiService.php          (pagos + tokenización)
│   │   ├── ClaudeAiService.php       (llamadas Anthropic API)
│   │   ├── PushNotificationService.php
│   │   ├── EmailService.php          (templates comportamentales)
│   │   └── RiseService.php           (30-day challenge logic)
│   └── Jobs/
│       ├── SendBehavioralTrigger.php  (reemplaza behavioral-triggers.php cron)
│       ├── ProcessAutoRenewal.php     (reemplaza auto-renewal.php cron)
│       ├── SendPushNotification.php
│       └── GenerateAiPlan.php
├── routes/
│   └── api.php                       (todas las rutas en /api/v1/)
├── database/
│   ├── migrations/                   (schema limpio completo)
│   └── seeders/                      (datos demo para testing local)
└── config/
    ├── sanctum.php
    ├── queue.php                     (Redis driver)
    └── services.php                  (Wompi, Claude, FCM keys)
```

### Rutas API v1 — Mapeo completo desde PHP

```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me

GET    /api/v1/profile
PUT    /api/v1/profile
POST   /api/v1/profile/avatar

GET    /api/v1/metrics
POST   /api/v1/metrics
GET    /api/v1/metrics/biometric
POST   /api/v1/metrics/biometric

GET    /api/v1/training/week
GET    /api/v1/training/range
POST   /api/v1/training/toggle
GET    /api/v1/training/plan

GET    /api/v1/checkins
POST   /api/v1/checkins
POST   /api/v1/checkins/video

GET    /api/v1/photos
POST   /api/v1/photos

GET    /api/v1/gamification/status
POST   /api/v1/gamification/earn-xp
GET    /api/v1/gamification/leaderboard
GET    /api/v1/gamification/achievements

GET    /api/v1/challenges
POST   /api/v1/challenges/{id}/join
POST   /api/v1/challenges/{id}/progress
GET    /api/v1/challenges/{id}/leaderboard

GET    /api/v1/community/posts
POST   /api/v1/community/posts
POST   /api/v1/community/posts/{id}/react
DELETE /api/v1/community/posts/{id}

GET    /api/v1/academy
POST   /api/v1/academy/{id}/complete

GET    /api/v1/notifications
PUT    /api/v1/notifications/{id}/read
POST   /api/v1/push/subscribe

GET    /api/v1/plans
GET    /api/v1/plans/{type}

GET    /api/v1/rise/status
POST   /api/v1/rise/enroll
POST   /api/v1/rise/intake

GET    /api/v1/payments
POST   /api/v1/payments/method
POST   /api/v1/payments/wompi/webhook

GET    /api/v1/ai/chat (GET history)
POST   /api/v1/ai/chat

GET    /api/v1/shop/products
POST   /api/v1/shop/orders

# Coach routes (role: coach)
GET    /api/v1/coach/clients
GET    /api/v1/coach/clients/{id}
GET    /api/v1/coach/messages
POST   /api/v1/coach/messages
GET    /api/v1/coach/notes/{clientId}
POST   /api/v1/coach/notes/{clientId}
GET    /api/v1/coach/analytics
GET    /api/v1/coach/appointments
POST   /api/v1/coach/appointments
GET    /api/v1/coach/checkins
PUT    /api/v1/coach/checkins/{id}/reply

# Admin routes (role: admin|superadmin)
GET    /api/v1/admin/clients
POST   /api/v1/admin/clients
PUT    /api/v1/admin/clients/{id}
POST   /api/v1/admin/impersonate/{id}
GET    /api/v1/admin/payments
GET    /api/v1/admin/kpis
POST   /api/v1/admin/plans/assign
```

### Gamificación — Niveles exactos migrados

```
Nivel 1: Iniciado      0 XP
Nivel 2: Comprometido  200 XP
Nivel 3: Constante     500 XP
Nivel 4: Dedicado      1000 XP
Nivel 5: Elite         2000 XP
Nivel 6: Leyenda       4000+ XP

XP por evento:
checkin       → 50 XP
video_checkin → 80 XP
streak_7d     → 150 XP (automático)
streak_30d    → 500 XP (automático)
badge         → 100 XP
challenge     → 200 XP
referral      → 300 XP
```

### Behavioral Triggers — Jobs Laravel (reemplaza cron PHP)

```
SendBehavioralTrigger dispatched diariamente a las 8am (Laravel Scheduler):

inactive_7d     → 7-13 días sin check-in → email "Te extrañamos"
inactive_14d    → 14-29 días sin check-in → email "Llevamos 14 días"
subscription_7d → 5-8 días para expirar  → email "Renueva en 7 días"
subscription_3d → 2-4 días para expirar  → email "Actúa en 3 días"
milestone_4     → 4 check-ins totales    → email "4 check-ins 🔥"
milestone_7     → 7 check-ins totales    → email "7 check-ins 🏆"
birthday        → cumpleaños del cliente → email "Feliz cumpleaños 🎂"
welcome_day1    → 1-3 días de registro   → email "Tu primer día 🚀"

Deduplicación: auto_message_log (UNIQUE client_id + trigger_type + date)
```

---

## 4. MOBILE — wellcore-app (Flutter 3.x)

### Stack Flutter

| Capa | Paquete | Propósito |
|------|---------|-----------|
| State | flutter_riverpod + riverpod_generator | State management reactivo |
| HTTP | dio + retrofit | API client tipado (reemplaza WC_API JS) |
| Auth | flutter_secure_storage | Token seguro (reemplaza localStorage) |
| Navegación | go_router | Rutas declarativas + deep links |
| Push | firebase_messaging | FCM notifications |
| DB local | hive_flutter | Cache offline-first |
| Video | video_player + chewie | Academy + video check-ins |
| Charts | fl_chart | Métricas, progreso, gamificación |
| Camera | image_picker | Fotos de progreso + video check-ins |
| Fonts | google_fonts | Axiforma equivalente |
| Animations | flutter_animate | Reemplaza v7 cubic-bezier |
| IAP | in_app_purchase | Suscripciones nativas (Fase 4) |
| Health | health | Apple Health + Google Fit (Fase 3) |
| Maps | — | No requerido |

### Design System Flutter — Tokens v7 exactos

```dart
// lib/core/theme/wellcore_theme.dart

class WellCoreColors {
  // Brand
  static const primary = Color(0xFFE31E24);      // --wc-red
  static const primaryLight = Color(0xFFFF4A4F); // --wc-red-light
  static const primaryDark = Color(0xFFB8181D);  // --wc-red-dark
  static const gold = Color(0xFFD4A853);         // --wc-gold (achievements)
  static const success = Color(0xFF22C55E);       // --wc-green

  // Surfaces (dark-first)
  static const surface0 = Color(0xFF111113);  // --wc-surface-0
  static const surface1 = Color(0xFF18181B);  // --wc-surface-1
  static const surface2 = Color(0xFF1A1A1D);  // --wc-surface-2
  static const surface3 = Color(0xFF222225);  // --wc-surface-3
  static const canvas = Color(0xFF0A0A0A);    // --wc-black

  // Text
  static const textPrimary = Color(0xFFFFFFFF);
  static const textDim = Color(0x73FFFFFF);   // 45% white
  static const textMuted = Color(0x2EFFFFFF); // 18% white

  // Borders
  static const border = Color(0x0FFFFFFF);       // 6% white
  static const borderHover = Color(0x33E31E24);  // 20% red
}

class WellCoreSpacing {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const base = 16.0;
  static const lg = 24.0;
  static const xl = 32.0;
  static const xxl = 48.0;
  static const xxxl = 64.0;
}

class WellCoreRadius {
  static const sm = Radius.circular(6);
  static const md = Radius.circular(12);
  static const lg = Radius.circular(20);
  static const xl = Radius.circular(28);
  static const pill = Radius.circular(100);
}
```

### Arquitectura Flutter — Feature-first

```
wellcore-app/
├── lib/
│   ├── core/
│   │   ├── theme/            wellcore_theme.dart, colors, typography, spacing
│   │   ├── api/              api_client.dart (Dio + Retrofit)
│   │   ├── auth/             auth_provider.dart, secure_storage.dart
│   │   ├── router/           app_router.dart (go_router)
│   │   └── widgets/          WCButton, WCCard, WCTextField, WCAvatar, XpBar
│   ├── features/
│   │   ├── auth/             login_screen, login_bloc
│   │   ├── dashboard/        dashboard_screen (cliente y coach)
│   │   ├── training/         week_view, exercise_list, toggle_completion
│   │   ├── checkins/         checkin_form, checkin_history, video_checkin
│   │   ├── metrics/          metrics_form, progress_chart, photo_gallery
│   │   ├── gamification/     xp_widget, level_bar, leaderboard, achievements
│   │   ├── challenges/       challenge_list, challenge_detail, leaderboard
│   │   ├── community/        feed, post_composer, reactions
│   │   ├── academy/          content_list, video_player, pdf_viewer
│   │   ├── notifications/    notification_list, push_handler
│   │   ├── coach/            client_roster, client_detail, notes, messages
│   │   ├── rise/             enrollment_flow, intake_form, rise_dashboard
│   │   ├── ai/               chat_screen, plan_viewer
│   │   ├── payments/         subscription_screen, payment_method, history
│   │   └── settings/         profile_edit, language, security, logout
│   └── main.dart
```

### Pantallas Flutter — Mapeo exacto desde HTML actual

| HTML Actual | Flutter Screen | Role |
|-------------|----------------|------|
| `login.html` | `AuthScreen` | Todos |
| `cliente.html` (dashboard) | `ClientDashboardScreen` | Cliente |
| `cliente.html` (métricas) | `MetricsScreen` | Cliente |
| `cliente.html` (entrenamiento) | `TrainingWeekScreen` | Cliente |
| `cliente.html` (check-in) | `CheckinScreen` | Cliente |
| `cliente.html` (fotos) | `PhotoGalleryScreen` | Cliente |
| `cliente.html` (logros) | `AchievementsScreen` | Cliente |
| `cliente.html` (comunidad) | `CommunityFeedScreen` | Cliente |
| `cliente.html` (academia) | `AcademyScreen` | Cliente |
| `cliente.html` (activity feed) | `ActivityFeedSheet` | Cliente |
| `rise-dashboard.html` | `RiseDashboardScreen` | RISE |
| `rise-enroll.html` | `RiseEnrollFlow` | Público |
| `rise-intake.html` | `RiseIntakeFlow` | RISE |
| `coach-portal.html` (roster) | `CoachRosterScreen` | Coach |
| `coach-portal.html` (cliente) | `CoachClientDetailScreen` | Coach |
| `coach-portal.html` (mensajes) | `CoachMessagesScreen` | Coach |
| `coach-portal.html` (analytics) | `CoachAnalyticsScreen` | Coach |
| `admin.html` (clientes) | `AdminClientsScreen` | Admin |
| `admin.html` (pagos) | `AdminPaymentsScreen` | Admin |
| `tienda.html` | `ShopScreen` | Todos |
| `planes.html` | `PlansScreen` | Público |
| `index.html` | — (web only, no en app) | Web |

### Navegación Flutter — go_router

```dart
// Rutas principales
/login
/dashboard          → redirect según rol: /client, /coach, /admin
/client/            → ClientDashboard (bottom nav)
  /client/training
  /client/checkin
  /client/metrics
  /client/community
  /client/academy
  /client/gamification
  /client/rise
  /client/settings
/coach/             → CoachDashboard
  /coach/clients
  /coach/clients/:id
  /coach/messages
  /coach/analytics
/admin/
  /admin/clients
  /admin/payments
  /admin/kpis
/rise/enroll        → flujo público de inscripción
/shop
/plans              → público, sin auth
```

---

## 5. BASE DE DATOS — Schema nuevo en Laravel

### Migraciones Laravel (orden de ejecución)

```
001_create_users_table.php             (clients + admins unificados con roles)
002_create_auth_tokens_table.php       (compatible Sanctum)
003_create_client_profiles_table.php
004_create_metrics_table.php
005_create_training_programs_table.php
006_create_training_logs_table.php
007_create_checkins_table.php
008_create_photos_table.php
009_create_payments_table.php
010_create_payment_methods_table.php
011_create_auto_charge_log_table.php
012_create_gamification_tables.php    (client_xp, xp_events)
013_create_achievements_table.php
014_create_challenges_tables.php      (challenges, challenge_participants)
015_create_community_tables.php       (posts, reactions)
016_create_notifications_table.php
017_create_push_subscriptions_table.php
018_create_academy_tables.php
019_create_coach_notes_table.php
020_create_coach_messages_table.php
021_create_rise_programs_table.php
022_create_assigned_plans_table.php
023_create_ai_generations_table.php
024_create_shop_tables.php
025_create_auto_message_log_table.php
026_create_biometric_logs_table.php
027_create_referrals_table.php
028_create_pods_table.php
029_create_appointments_table.php
030_create_video_checkins_table.php
```

### Cambio crítico: users unificados

El sistema PHP tiene dos tablas separadas `clients` y `admins`. En Laravel se unifican en una tabla `users` con campo `role` (client, coach, admin, superadmin). Esto simplifica Sanctum y los Eloquent relationships.

---

## 6. LAS 5 FASES DE IMPLEMENTACIÓN

### FASE 0 — Base/Desarrollo (~4 semanas)
**Objetivo:** Infraestructura funcional + feature parity core

**Backend Laravel:**
- Scaffolding monorepo completo
- 30 migraciones (schema limpio)
- Auth con Sanctum (login, logout, me, roles)
- APIs core: profile, metrics, training, checkins, photos
- Gamification service (XP, niveles, streaks)
- Seeder con datos demo (clientes, coaches, admin)
- Tests unitarios de auth + gamification

**Flutter:**
- Proyecto base + design system WellCore (tokens v7)
- go_router con rutas por rol
- API client Dio + Retrofit
- Pantallas: Login, ClientDashboard, Training, Checkin, Metrics, Photos
- XP widget + level bar + streak counter
- Offline cache con Hive
- Push notifications FCM (setup)

---

### FASE 1 — Upgrade (~3 semanas)
**Objetivo:** Completar feature parity + Coach portal

**Backend:**
- Coach portal APIs completas (roster, notes, messages, analytics)
- Admin APIs (clients, payments, impersonate, KPIs)
- Community APIs (posts, reactions, threads)
- Academy APIs (content, progress)
- Challenges completos (join, progress, leaderboard)
- Behavioral triggers → Laravel Scheduler + Queue Jobs

**Flutter:**
- Coach portal screens completo (roster, cliente detail, mensajes, notas)
- Admin screens (clientes, pagos, KPIs)
- Community feed con reacciones emoji
- Academy (video player, PDF viewer, guías)
- Challenges con leaderboard animado
- Notifications center

---

### FASE 2 — Premium (~4 semanas)
**Objetivo:** Features diferenciadores de mercado

**Backend:**
- RISE Challenge flow completo (enroll, intake, AI generation, status)
- AI Chat con RAG local (migrar knowledge-base.json a Laravel)
- AI plan generation (Claude API — training + nutrition + habits)
- Video check-in upload + coach review
- Biometric logs completos
- Wompi payment flow (checkout + webhooks + auto-renewal)
- WhatsApp Business API (triggers LATAM — behavioral + renewal)

**Flutter:**
- RISE enrollment flow completo (3 pasos: enroll → intake → payment)
- RISE dashboard animado (30-day progress ring)
- AI Chat screen con historial
- Video check-in (grabar + enviar desde app)
- Audio coaching player (sesiones guiadas)
- Payment screens (selección plan, tarjeta, confirmación)
- Biometric logs + charts avanzados (fl_chart)

---

### FASE 3 — Sofisticado (~4 semanas)
**Objetivo:** Analytics avanzado + integraciones ecosistema

**Backend:**
- Coach analytics avanzados (adherencia, churn risk, engagement score)
- Apple Health / Google Fit sync (pasos, sueño, FC)
- Accountability Pods (grupos 5-8 por coach)
- Referral system mejorado (landing page propia)
- B2B portal coaches externos (coach se suscribe, usa plataforma)
- Nutrition tracking completo (macros + fotos de comidas)
- Mental health tracker (energía, estrés, sueño 1-10)
- Plan phases visibles (cliente ve todo el camino — 4 semanas)

**Flutter:**
- Health integration (pedometer, sleep, heart rate)
- Accountability Pods feed
- Nutrition tracking UI (macro rings, food log)
- Mental wellness daily check
- Plan phases timeline (visual journey)
- Coach advanced analytics (charts, churn alerts)
- Referral share screen (código + link propio del cliente)

---

### FASE 4 — Consolidar + Launch (~3 semanas)
**Objetivo:** Production-ready, stores, performance

**Backend:**
- Script de migración de datos PHP → Laravel (exporta prod MySQL, importa a nuevo schema)
- Laravel Octane (Swoole) para alto rendimiento
- Redis cache (replace JSON files + localStorage)
- Laravel Reverb WebSockets (activity feed en tiempo real, leaderboard live)
- Rate limiting avanzado con Redis
- API documentation completa (Scribe)
- CI/CD GitHub Actions (lint, tests, deploy)

**Flutter:**
- WebSocket client (real-time activity feed)
- App Store + Google Play assets (screenshots, descripción, icons)
- Performance audit (Flutter DevTools)
- Accessibility audit
- Onboarding flow (3 pantallas al primer login)
- Crash reporting (Firebase Crashlytics)
- Analytics (Firebase Analytics)

**Infraestructura:**
- `App wellcorefitness/` → repo Git nuevo
- Laravel en Herd (PHP 8.3, MySQL 8, Redis)
- Flutter corriendo en emulador Android + iOS Simulator
- Cuando aprobado: EasyPanel deploy del nuevo stack

---

## 7. MIGRACIÓN DE DATOS — Script Final (Fase 4)

```
Proceso de migración (cero downtime):

1. Fase prep: correr script en Herd contra un dump de producción
2. Validar integridad: contar registros antes y después
3. Ventana de migración: 2 horas de madrugada (mínimo tráfico)
4. Ejecutar script: PHP → Laravel MySQL nuevo
5. Switchover: DNS apunta a nuevo servidor
6. Rollback plan: mantener PHP 48h como fallback

Tablas a migrar:
clients → users (role='client')
admins → users (role='admin'|'coach'|'superadmin')
client_profiles → client_profiles (FK a users.id)
metrics, checkins, photos, training → migración directa
gamification (client_xp, xp_events, achievements) → migración directa
challenges, community, academy → migración directa
payments, payment_methods → migración directa
rise_programs, assigned_plans → migración directa
auth_tokens → invalidados (usuarios re-login en nuevo sistema)
```

---

## 8. VENTAJAS COMPETITIVAS VS MERCADO

| Feature | WellCore App | Bejao | Trainerize |
|---------|-------------|-------|------------|
| Gamificación (XP + niveles) | ✅ 6 niveles | ❌ | ⚠️ básico |
| Check-ins estructurados | ✅ semanal | ❌ | ✅ |
| Video check-in | ✅ Fase 2 | ❌ | ✅ ($10/mes add-on) |
| AI plan generation | ✅ Claude API | ❌ | ✅ ($45/mes add-on) |
| RISE 30-day challenge | ✅ único | ❌ | ❌ |
| Español 100% | ✅ | ✅ | ❌ |
| Pagos LATAM (Wompi) | ✅ | ❌ | ❌ |
| WhatsApp triggers | ✅ Fase 2 | ❌ | ❌ |
| Audio coaching | ✅ Fase 2 | ❌ | ❌ |
| Accountability Pods | ✅ Fase 3 | ❌ | ⚠️ |
| App nativa iOS+Android | ✅ Flutter | ✅ | ✅ |
| Offline-first | ✅ Hive cache | ❌ | ❌ |

---

## 9. HERD — CONFIGURACIÓN LOCAL

```
# wellcore-api en Herd
Site: wellcore-api.test
PHP: 8.3
MySQL: base de datos nueva wellcore_app_dev

# wellcore-app en Flutter
flutter run -d android (emulador)
flutter run -d ios (simulator)
API_BASE_URL=http://wellcore-api.test

# Nunca en producción hasta Fase 4 completada
```

---

*Documento generado: 2026-03-10*
*Stack validado contra 228 endpoints PHP + 30 tablas MySQL + estudio de 38 competidores*
