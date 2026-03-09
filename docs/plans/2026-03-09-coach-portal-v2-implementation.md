# Coach Portal v2 — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Upgrade coach-portal.html to super-premium state with V8 visuals, notifications, messaging, revenue dashboard, community, and PWA white-label.

**Architecture:** 3 phases deployable independently. PHP + HTML vanilla. Zero framework changes. All additions are additive — nothing removed.

**Tech Stack:** PHP 8+, PDO MySQL, HTML vanilla, CSS inline + v7.css + v8.css, api.js pattern

---

## Phase 1 — Visual Superpremium + V8 Notifications

### Task 1: Load V8 CSS + Lenis in head ✅ COMPLETE
- Modified: `coach-portal.html` head — added `wellcore-v8.css` + Lenis CDN

### Task 2: v7-magnetic + v7-glow-track additions ✅ COMPLETE
- sec-marca: added v7-reveal, v7-split-word, v7-glow-track on all cards
- sec-referidos: added v7-reveal, v7-split-word, v7-glow-track on cards
- sec-recursos: added v7-split-word + v7-glow-track on resource-cards
- Buttons: + v7-magnetic on Guardar Marca, Nuevo Ticket, Enviar Ticket

### Task 3: Canvas particles + Lenis init ✅ COMPLETE
- Canvas #sidebar-particles in sidebar-logo
- JS: initSidebarParticles() — 14 red dots floating animation
- JS: initLenis() — smooth scroll with lerp 0.08

### Task 4: Topbar redesign — Bell icon V8 ✅ COMPLETE
- Added notif-bell #clientNotifBell with SVG bell icon
- Added notif-badge #clientNotifBadge
- Added notif-panel #clientNotifPanel with notif-list #clientNotifList

### Task 5: Skeleton loaders CSS ✅ COMPLETE
- Added .skeleton, .kpi-skeleton, .skeleton-row CSS
- Added kpiEnter stagger animation for KPI cards
- Added sectionFadeIn animation for sections

### Task 6: Load V8 JS + coach notifications override ✅ COMPLETE
- Added `js/wellcore-v8.js` defer script tag
- JS: initCoachNotifications() — overrides V8.Notifications.load to call coach endpoint

### Task 7: Glassmorphism sidebar upgrade ✅ COMPLETE
- sidebar: backdrop-filter blur(20px) + gradient border
- Pulse dot enhanced animation

### Task 8: Create /api/coach/notifications.php ✅ COMPLETE
- GET endpoint, authenticateCoach(), returns last 20 notifications for coach

---

## Phase 2 — Comunicación + Negocio

### Task 9: DB Migration — coach_messages table
**Files:**
- Create: `database/migrations/017_coach_messages.sql`

```sql
CREATE TABLE IF NOT EXISTS coach_messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coach_id   INT UNSIGNED NOT NULL,
  client_id  INT UNSIGNED NOT NULL,
  message    TEXT NOT NULL,
  direction  ENUM('coach_to_client','client_to_coach') NOT NULL,
  read_at    DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_thread (coach_id, client_id, created_at),
  INDEX idx_unread (coach_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Task 10: API — /api/coach/messages.php
- GET: ?client_id=X — list thread messages
- POST: { client_id, message } — send message

### Task 11: API — /api/coach/broadcast.php
- POST: { message } — send to all active clients of coach
- Creates a coach_message per client

### Task 12: UI — Centro de Mensajes en coach-portal.html
- New section #sec-mensajes in sidebar nav
- Thread list on left, message view on right
- Templates: Bienvenida, Motivacion, Check-in, Recordatorio Pago
- Broadcast button

### Task 13: API — /api/coach/revenue.php
- GET — Returns: { mrr_current, mrr_history: [{month, amount}x6], clients_renewing: [{name, plan, days_left}] }
- Queries: clients table WHERE coach_id=X AND status='active', payments for 6 months

### Task 14: UI — Revenue Dashboard expandido
- New tab within Dashboard or sub-section
- Sparkline chart (pure SVG) for 6-month history
- Renewal alerts table with "Enviar recordatorio" button

---

## Phase 3 — Comunidad + PWA White-Label

### Task 15: DB Migration — community + pwa_config
**Files:**
- Create: `database/migrations/018_coach_community_pwa.sql`

```sql
CREATE TABLE IF NOT EXISTS coach_community_posts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coach_id   INT UNSIGNED NOT NULL,
  content    TEXT NOT NULL,
  type       ENUM('post','tip','achievement') DEFAULT 'post',
  likes      INT UNSIGNED DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_feed (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coach_pwa_config (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coach_id     INT UNSIGNED NOT NULL UNIQUE,
  app_name     VARCHAR(60) NOT NULL DEFAULT 'Mi App Fitness',
  icon_url     VARCHAR(255),
  color        VARCHAR(7) DEFAULT '#E31E24',
  subdomain    VARCHAR(40),
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_coach (coach_id),
  INDEX idx_subdomain (subdomain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Task 16: API — /api/coach/community.php
- GET: feed de posts de todos los coaches
- POST: crear nuevo post

### Task 17: API — /api/coach/pwa-config.php
- GET: config del coach
- POST: actualizar config (app_name, color, icon)

### Task 18: API — /api/pwa/manifest.php
- GET ?coach={slug}
- Returns dynamic manifest.json for coach's PWA

### Task 19: UI — Sección Comunidad
- Feed posts con avatar, nombre coach, tiempo
- Botón "Publicar tip" con textarea
- Simple leaderboard (clientes activos top 5)

### Task 20: UI — PWA Configurator en Mi Marca
- App name input
- Color picker (already exists)
- Preview del manifest generado
- "Instalar como app" button
- Subdomain badge: {slug}.wellcorefitness.com

---

## Deploy Checklist (per phase)
1. `git add -A && git commit -m "feat: ..."`
2. `git push`
3. EasyPanel console: `cd /code && git pull`
4. Si hay migración: `php database/run_migration.php MIGRATION_FILE.sql`
5. `nginx -s reload` (si se toca nginx.conf)
