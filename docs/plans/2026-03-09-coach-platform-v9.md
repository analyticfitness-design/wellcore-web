# WellCore Coach Platform v9 — Plan de Implementacion

> **For Claude:** Use superpowers:executing-plans para ejecutar tarea a tarea.

**Goal:** Plataforma de clase mundial — XP, video check-ins IA, analytics coach, pods, booking Elite, PWA, referidos.
**Stack:** PHP 8+, MySQL, Vanilla JS, Claude Haiku API, Web Push VAPID, Wompi.

---

## ORDEN DE EJECUCION

| # | Fase | Archivos | Prioridad |
|---|------|----------|-----------|
| 0 | Migracion DB | api/setup/migrate-v9-coach-platform.php | CRITICA |
| 1 | XP + Racha + Leaderboard | api/gamification/ (3 files) | ALTA |
| 2 | Video Check-ins + Haiku | api/video-checkins/ (3 files) | ALTA |
| 3 | Coach Analytics | api/coach/analytics.php | MEDIA |
| 4 | Audio Coaching | api/coach/audio.php | MEDIA |
| 5 | Accountability Pods | api/pods/ (2 files) | MEDIA |
| 6 | Booking Elite | api/appointments/ (3 files) | MEDIA |
| 7 | Achievement Sharing | api/achievements/share.php | NORMAL |
| 8 | Referral Trial 3d | api/referral/create-trial.php | NORMAL |
| 9 | Push + SW | sw.js + api/notifications/subscribe.php | NORMAL |
| 10 | Video Tips | api/coach/video-tips.php | NORMAL |
| 11 | Leaderboard Modal | modificar cliente.html | NORMAL |
| 12 | PWA Manifest | manifest.json | NORMAL |

---

## FASE 0 — Migracion DB

**Archivo:** api/setup/migrate-v9-coach-platform.php
**Patron:** array de queries CREATE TABLE, iterar con pdo->query(sql). Echo OK/ERROR por tabla.

### 14 Tablas

| Tabla | Columnas clave |
|-------|---------------|
| client_xp | client_id UNIQUE, xp_total, level, streak_days, streak_last_date, streak_protected |
| xp_events | client_id, event_type ENUM, xp_gained, description |
| accountability_pods | coach_id, name, max_members=8, is_active |
| pod_members | pod_id, client_id UNIQUE |
| pod_messages | pod_id, client_id, message |
| coach_audio | coach_id, title, audio_url, duration_sec, plan_access JSON, category, sort_order |
| video_checkins | client_id, coach_id, media_type ENUM(video/image), media_url, exercise_name, notes, coach_response, ai_response, ai_used, status ENUM(pending/coach_reviewed/ai_reviewed), plan_uses_this_month |
| coach_analytics_snapshots | coach_id, snapshot_date UNIQUE, active_clients, churn_risk_count, revenue_month |
| appointments | coach_id, client_id, scheduled_at, duration_min=30, status ENUM(pending/confirmed/cancelled/completed) |
| coach_availability | coach_id, day_of_week 0-6, time_start, time_end, is_active |
| referral_trials | referral_code, referrer_client_id, referred_email UNIQUE, trial_days=3, trial_expires_at |
| coach_video_tips | coach_id, title, video_url, thumbnail_url, duration_sec, is_active, sort_order |
| shared_achievements | client_id, achievement_type, achievement_data JSON, share_token UNIQUE, views |
| push_subscriptions | client_id, endpoint UNIQUE, p256dh, auth, is_active |

Commit: feat(v9): migration — 14 new tables

---

## FASE 1 — XP + Racha + Leaderboard

### 1.1: api/gamification/earn-xp.php
POST {event_type, description?} — Auth cliente

XP Map:
- checkin=50, workout=20, photo_progress=30, video_checkin=40
- plan_week_100=100, referral=200, challenge_win=75, pod_message=5
- Bonos racha: streak_7=150, streak_14=250, streak_30=500

Logica racha (solo para checkin):
1. INSERT IGNORE client_xp para inicializar si no existe
2. SELECT con FOR UPDATE — leer estado actual
3. Si streak_last_date == ayer O streak_protected → streak++ → verificar bonos 7/14/30
4. Si streak_last_date != ayer y no protegida → streak = 1
5. Si streak_last_date == hoy → no cambiar racha (ya registro hoy)
6. Calcular nivel: umbrales [0, 200, 500, 1000, 2000, 4000]
7. UPDATE client_xp + INSERT xp_events
8. Return: {xp_gained, xp_total, level, streak_days, leveled_up}

### 1.2: api/gamification/get-status.php
GET — Auth cliente
progress_pct = (xp_en_nivel / xp_para_siguiente) * 100
Return: {xp_total, level, level_name, progress_pct, streak_days, streak_protected}

Niveles: 1=Iniciado(0) | 2=Atleta(200) | 3=Guerrero(500) | 4=Elite(1000) | 5=Leyenda(2000) | 6=WellCore Master(4000)

### 1.3: api/gamification/leaderboard.php
GET — Auth cliente — Top 10 del mismo coach por xp_this_week (ultimos 7 dias)
JOIN clients + client_xp + xp_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
Return: {leaderboard: [{position, nombre, xp_this_week, level, streak, is_me}]}

### 1.4: Widget XP en cliente.html
Buscar .wc-page-header y agregar div#xp-widget con:
- span#xp-level-badge (textContent = NVL + level)
- div#xp-bar-fill (style.width = progress_pct + %)
- span#xp-total-label (textContent = xp_total + XP)
- span#xp-streak con emoji fuego (oculto si streak_days == 0)
Al hacer clic en el widget: showLeaderboard()

JS loadXpWidget(): GET /api/gamification/get-status — actualizar elementos via .textContent y .style.width

Commit: feat(v9): XP system — earn-xp, get-status, leaderboard

---

## FASE 2 — Video Check-ins con Claude Haiku

Limites por plan: esencial=2/mes | metodo=5/mes | rise=3/mes | elite=ilimitado (coach manual, SLA 24h)

### 2.1: api/video-checkins/upload.php
POST multipart: file, exercise_name, notes — Auth cliente

1. Obtener plan y coach_id del cliente
2. COUNT video_checkins del mes actual → comparar con limite → 429 si excede
3. Validar MIME (video/mp4, video/quicktime, video/webm, image/jpeg, image/png) y size <= 100MB
4. move_uploaded_file a /uploads/video-checkins/vc_{id}_{time}.ext
5. INSERT video_checkins
6. INSERT notification al coach
7. UPDATE client_xp xp_total += 40 + INSERT xp_events(video_checkin, 40)
8. Return: {checkin_id, xp_gained:40, uses_remaining, message}

### 2.2: api/video-checkins/respond.php
POST {checkin_id, response?, use_ai?, ai_secret?} — Auth coach OR ai_secret interno

Ruta IA (use_ai=true): file_get_contents + stream_context_create para POST a Anthropic API:
  URL: https://api.anthropic.com/v1/messages
  Headers: Content-Type application/json, x-api-key, anthropic-version: 2023-06-01
  Body: model=claude-haiku-4-5-20251001, max_tokens=300, prompt del ejercicio en espanol
  ai_text = result[content][0][text]
  UPDATE video_checkins: ai_response, ai_used=1, status=ai_reviewed
  INSERT notification al cliente

Ruta coach manual:
  UPDATE video_checkins: coach_response, status=coach_reviewed
  INSERT notification al cliente

### 2.3: api/video-checkins/list.php
GET ?status=pending — Auth coach → lista check-ins con datos del cliente

Commit: feat(v9): video check-ins — upload + AI respond + list

---

## FASE 3 — Coach Analytics

### 3.1: api/coach/analytics.php
GET — Auth coach

Queries:
- activity: COUNT total, activos (check-in en 7d), inactivos (JOIN subconsulta MAX check-in)
- churn_risk: clientes sin check-in en 14+ dias, ORDER BY days_inactive DESC LIMIT 10
- top_performers: top 5 por SUM xp_events.xp_gained de ultimos 7 dias
- pending_videos: video_checkins WHERE status=pending, TIMESTAMPDIFF(HOUR) como hours_pending

Return: {activity, churn_risk, top_performers, pending_videos}

### 3.2: Seccion Analytics en coach-portal.html
Nav: agregar boton con fa-chart-line + onclick showSection('analytics')

Seccion HTML:
- Grid 2 cards: Activos/Inactivos | Videos pendientes con count
- Lista churn con borde rojo si days_inactive > 14
- Lista top performers con medallas
- Boton Responder por cada video pendiente (onclick openVideoResponse(id))

JS loadAnalytics(): GET /api/coach/analytics → poblar via textContent y createElement

Commit: feat(v9): coach analytics — churn risk, top performers, pending

---

## FASE 4 — Audio Coaching

### 4.1: api/coach/audio.php
GET — Auth cliente
SELECT FROM coach_audio WHERE JSON_CONTAINS(plan_access, JSON_QUOTE(plan_cliente)) ORDER BY sort_order
Return: {audios: [{id, title, audio_url, thumbnail_url, duration_sec, category}]}

### 4.2: Widget en cliente.html
Lista de tarjetas de audio + sticky player bar (position:fixed; bottom:0)
playAudio(url, title, thumb): asignar audio.src + llamar .play()
Boton X para cerrar player

Commit: feat(v9): audio coaching — API + sticky player

---

## FASE 5 — Accountability Pods

### 5.1: api/pods/list.php
GET — Auth cliente — pods donde el cliente es miembro, con recent_messages (ultimos 3 por pod)

### 5.2: api/pods/messages.php
POST {pod_id, message} — Auth cliente
1. Verificar membresia en pod_members → 403 si no es miembro
2. INSERT pod_messages
3. xp_total += 5 + INSERT xp_events(pod_message, 5)
4. Return: {success, xp_gained: 5}

Commit: feat(v9): accountability pods — list + messages

---

## FASE 6 — Booking Citas (Solo Plan Elite)

### 6.1: api/appointments/availability.php
GET ?date=YYYY-MM-DD — Auth cliente (plan == elite)
1. Verificar plan Elite → 403 si no
2. SELECT coach_availability WHERE day_of_week = weekday del date
3. SELECT appointments ocupados ese dia (status != cancelled)
4. Generar slots de 30min, available=false si ocupado
Return: {slots: [{time, datetime, available}], date}

### 6.2: api/appointments/create.php
POST {scheduled_at, duration_min?, notes?} — Auth cliente Elite
1. Verificar plan Elite → 403
2. SELECT conflicto → 409 si ya existe
3. INSERT appointments (status=pending)
4. INSERT notification al coach
Return: {appointment_id, message}

### 6.3: api/appointments/list.php
GET — Auth cliente → lista citas propias con status y datos del coach

Commit: feat(v9): booking Elite — availability + create + list

---

## FASE 7 — Achievement Sharing (Instagram Stories)

### 7.1: api/achievements/share.php
POST {achievement_type, achievement_data} — Auth cliente
share_token = bin2hex(random_bytes(16))
INSERT shared_achievements
Return: {share_token, share_url: https://wellcorefitness.com/share/{token}}

### 7.2: Modal celebracion en cliente.html
showAchievementModal(type, title, desc, emoji):
- Setear contenido via textContent (NO HTML dinamico de datos de usuario)
- Abrir modal position:fixed
- Preview 160x284 (9:16 ratio) con gradient rojo para Instagram Stories
- Boton: navigator.share (movil) || navigator.clipboard.writeText (desktop)

Commit: feat(v9): achievement sharing — token + Stories modal

---

## FASE 8 — Referral Trial 3 Dias

### 8.1: api/referral/create-trial.php
POST {referral_code, email, name} — Publico (sin auth)
1. SELECT clients WHERE referral_code → 404 si no existe
2. SELECT referral_trials WHERE referred_email → 409 si ya tiene trial
3. INSERT referral_trials (trial_days=3, trial_expires_at = NOW + 3 dias)
4. INSERT clients (plan=trial, status=trial, referral_source=code)
5. token = bin2hex(random_bytes(24)) + INSERT auth_tokens (expires = trial_expires)
6. UPDATE referidor: xp_total += 200 + INSERT xp_events(referral, 200)
7. Return: {token, trial_expires, message}

Commit: feat(v9): referral 3-day trial + XP reward

---

## FASE 9 — Service Worker + Push Notifications

### 9.1: sw.js (raiz del proyecto)
Install: cache de assets estaticos en CACHE_NAME wellcore-v9
Push: showNotification con title/body/icon=/images/icon-192.png/badge/vibrate=[200,100,200]/data={url}
Notificationclick: abrir data.url en ventana existente o nueva

### 9.2: api/notifications/subscribe.php
POST {endpoint, p256dh, auth} — Auth cliente
INSERT push_subscriptions ON DUPLICATE KEY UPDATE p256dh, auth, is_active=1

### 9.3: JS en cliente.html
const VAPID_KEY = TU_VAPID_PUBLIC_KEY // generar: web-push generate-vapid-keys
Registrar SW → solicitar permiso → subscribir PushManager → POST subscribe API

Commit: feat(v9): PWA service worker + push notifications

---

## FASE 10 — Coach Video Tips

### 10.1: api/coach/video-tips.php
GET — Auth cliente → top 5 tips del coach, ORDER BY sort_order ASC LIMIT 5
Return: {tips: [{id, title, video_url, thumbnail_url, category, duration_sec}]}

### 10.2: Widget en cliente.html
Scroll horizontal de tarjetas 160x90px con thumbnail
openVideoModal(url, title): document.createElement('div') + video element + boton cerrar
Usar textContent para el titulo (no strings de datos externos)

Commit: feat(v9): coach video tips — API + scroll widget

---

## FASE 11 — Leaderboard Modal

### 11.1: Modal en cliente.html
showLeaderboard():
1. Mostrar modal position:fixed; z-index:9998
2. GET /api/gamification/leaderboard
3. Por cada posicion: createElement('div') + setear textContent
4. Resaltar is_me=true con color rojo y font-weight:800
5. Append al contenedor del modal

Commit: feat(v9): leaderboard modal

---

## FASE 12 — PWA Manifest

### 12.1: manifest.json (raiz del proyecto)
name: WellCore Fitness | short_name: WellCore
start_url: /cliente.html | display: standalone
background_color: #0a0a0e | theme_color: #C8102E
icons: icon-192.png (192x192) + icon-512.png (512x512) con purpose: any maskable
shortcuts: [{name:Check-in, url:/cliente.html#checkin}, {name:Mi Plan, url:/cliente.html#plan}]
categories: [health, fitness] | lang: es

Agregar en head de cliente.html si no existe:
  link rel=manifest href=/manifest.json
  meta name=theme-color content=#C8102E
  meta name=apple-mobile-web-app-capable content=yes
  link rel=apple-touch-icon href=/images/icon-192.png

Install prompt: beforeinstallprompt event → guardar deferredPrompt → banner custom tras 30s de uso

Commit: feat(v9): PWA manifest + install prompt

---

## TABLAS DE REFERENCIA

### XP por Evento
| Evento | XP | Nota |
|--------|----|------|
| checkin | 50 | + bonus racha |
| workout | 20 | — |
| foto progreso | 30 | — |
| video check-in | 40 | — |
| semana 100% completada | 100 | — |
| referido convertido | 200 | — |
| racha 7 dias | +150 | bonus |
| racha 14 dias | +250 | bonus |
| racha 30 dias | +500 | bonus |
| ganar challenge | 75 | — |
| mensaje en pod | 5 | — |

### Niveles
| Level | XP Requerido | Titulo |
|-------|-------------|--------|
| 1 | 0 | Iniciado |
| 2 | 200 | Atleta |
| 3 | 500 | Guerrero |
| 4 | 1000 | Elite |
| 5 | 2000 | Leyenda |
| 6 | 4000 | WellCore Master |

### Limites Video Check-in por Plan
| Plan | IA / mes | Coach manual |
|------|----------|-------------|
| esencial | 2 | No |
| metodo | 5 | No |
| rise | 3 | No |
| elite | Ilimitado | Si (SLA 24h) |

### Features B2B para Coaches Externos (Roadmap)
| Feature | Descripcion | Prioridad |
|---------|-------------|-----------|
| Perfil publico del coach | /coach/{slug}.html — bio, especialidades, reviews | ALTA |
| Dashboard de metricas | KPIs: retencion, engagement, ingresos propios | ALTA |
| Upload de audios | Interface en coach-portal para mp3/m4a | ALTA |
| Upload video tips | Interface para videos cortos | MEDIA |
| Gestionar pods | CRUD grupos accountability | MEDIA |
| Configurar disponibilidad | Slots para booking Elite | MEDIA |
| Responder video check-ins | Preview inline + campo respuesta | ALTA |
| Onboarding guiado | Flujo 5 pasos para coaches nuevos | ALTA |
| Comisiones transparentes | Dashboard pagos + historial detallado | MEDIA |

---

*Plan generado: 2026-03-09 — WellCore Coach Platform v9 — Basado en estudio de 20+ competidores*
