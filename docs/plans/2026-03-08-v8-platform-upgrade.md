# WellCore v8 Platform Upgrade — Plan de Implementación

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Elevar WellCore al siguiente nivel de plataforma fitness en LATAM — notificaciones, analytics avanzados, perfil editable, referidos, PWA offline-first, y superadmin con command center completo.

**Architecture:** Cada feature se construye encima del stack existente (PHP + PDO + MySQL, HTML/CSS/JS vanilla, auth por Bearer token). Sin frameworks nuevos. Aditivo, nunca destructivo.

**Tech Stack:** PHP 8.x, MySQL, Font Awesome 6.4, CSS custom properties, SVG charts (DOM API), Vanilla JS ES5-compatible, Service Worker para PWA.

**Superadmin Command Center:** YA COMPLETADO (commit actual) — dashboard-stats.php + HTML/CSS/JS del command center.

---

## FASE 1 — Base de Datos (Migraciones)

### Task 1: Tabla notifications

**Files:**
- Create: `database/migrations/008_notifications.sql`

```sql
CREATE TABLE IF NOT EXISTS notifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_type   ENUM('client','admin') NOT NULL DEFAULT 'client',
  user_id     INT UNSIGNED NOT NULL,
  type        VARCHAR(60) NOT NULL,
  title       VARCHAR(160) NOT NULL,
  body        TEXT,
  link        VARCHAR(255),
  read_at     DATETIME DEFAULT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_type, user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Step 1:** Ejecutar migración en local: `mysql -u root wellcore < database/migrations/008_notifications.sql`

**Step 2:** Verificar: `DESCRIBE notifications;`

**Step 3:** Commit: `git add database/migrations/008_notifications.sql && git commit -m "feat: migration notifications table"`

---

### Task 2: Tabla referrals

**Files:**
- Create: `database/migrations/009_referrals.sql`

```sql
CREATE TABLE IF NOT EXISTS referrals (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referrer_id     INT UNSIGNED NOT NULL,
  referred_email  VARCHAR(255) NOT NULL,
  referred_id     INT UNSIGNED DEFAULT NULL,
  status          ENUM('pending','registered','converted') DEFAULT 'pending',
  reward_granted  TINYINT(1) DEFAULT 0,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  converted_at    DATETIME DEFAULT NULL,
  INDEX idx_referrer (referrer_id),
  INDEX idx_email (referred_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### Task 3: Columnas de perfil en clients

**Files:**
- Create: `database/migrations/010_client_profile_fields.sql`

```sql
ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS avatar_url    VARCHAR(500) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS bio           TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS city          VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS birth_date    DATE DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS referral_code VARCHAR(20) DEFAULT NULL UNIQUE,
  ADD COLUMN IF NOT EXISTS referred_by   INT UNSIGNED DEFAULT NULL;
```

---

## FASE 2 — APIs Backend

### Task 4: API de Notificaciones

**Files:**
- Create: `api/notifications/list.php` — GET, devuelve últimas 20 notificaciones del cliente autenticado
- Create: `api/notifications/mark-read.php` — POST `{id}` o `{all: true}`, marca como leídas
- Create: `api/notifications/create.php` — POST (solo admin), crea notificación para un cliente o todos

**list.php estructura:**
```
GET /api/notifications/list
Auth: Bearer token (client)
Response: { notifications: [...], unread_count: N }
```

**mark-read.php:**
```
POST /api/notifications/mark-read
Body: { id: 5 } | { all: true }
Response: { ok: true }
```

---

### Task 5: API de Perfil

**Files:**
- Create: `api/client/profile.php`

```
GET  /api/client/profile   → { id, name, email, plan, avatar_url, bio, city, birth_date, referral_code }
POST /api/client/profile   → Body: { name?, bio?, city?, birth_date? }  → { ok: true, client: {...} }
```

Validaciones: name min 2 chars, bio max 500 chars, birth_date formato YYYY-MM-DD, no permite cambiar email ni plan.

---

### Task 6: API de Referidos

**Files:**
- Create: `api/client/referrals.php`

```
GET /api/client/referrals
Response: {
  referral_code: "ABC123",
  referral_link: "https://wellcorefitness.com/inscripcion.html?ref=ABC123",
  referrals: [ { email, status, converted_at } ],
  total_converted: N,
  reward_pending: N
}
```

Al inscribirse un nuevo cliente con `?ref=CODE`, guardar `referred_by` en clients y crear fila en referrals.

---

### Task 7: API dashboard-stats ya completo ✅

`api/admin/dashboard-stats.php` — Completado en este commit.

---

## FASE 3 — CSS/JS v8

### Task 8: wellcore-v8.css

**Files:**
- Create: `css/wellcore-v8.css`

Tokens y componentes nuevos:
```css
:root {
  /* Notification bell */
  --notif-dot: #E31E24;
  /* Profile card */
  --profile-border: rgba(227,30,36,0.3);
}

/* Notification bell */
.notif-bell { position: relative; cursor: pointer; }
.notif-badge {
  position: absolute; top: -4px; right: -4px;
  width: 16px; height: 16px; border-radius: 50%;
  background: var(--notif-dot);
  font-size: 9px; font-weight: 700; color: #fff;
  display: flex; align-items: center; justify-content: center;
  animation: notif-pulse 2s infinite;
}
@keyframes notif-pulse {
  0%,100% { box-shadow: 0 0 0 0 rgba(227,30,36,0.4); }
  50%      { box-shadow: 0 0 0 6px rgba(227,30,36,0); }
}

/* Notification dropdown */
.notif-panel {
  position: absolute; top: 100%; right: 0;
  width: 320px; background: var(--surface);
  border: 1px solid var(--border); border-radius: var(--radius);
  box-shadow: 0 8px 32px rgba(0,0,0,0.4);
  z-index: 200; overflow: hidden;
}
.notif-item { padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 13px; }
.notif-item.unread { background: rgba(227,30,36,0.05); }
.notif-item:last-child { border-bottom: none; }

/* Renewal banner */
.renewal-banner {
  background: linear-gradient(90deg, rgba(245,158,11,0.15), rgba(245,158,11,0.05));
  border: 1px solid rgba(245,158,11,0.35);
  border-radius: var(--radius-sm);
  padding: 10px 16px;
  display: flex; align-items: center; justify-content: space-between;
  font-size: 13px; margin-bottom: 16px;
}

/* Profile card */
.profile-card {
  background: var(--surface);
  border: 1px solid var(--profile-border);
  border-radius: var(--radius);
  padding: 24px;
}
.profile-avatar-ring {
  width: 80px; height: 80px; border-radius: 50%;
  border: 2px solid var(--red);
  display: flex; align-items: center; justify-content: center;
  font-size: 28px; font-weight: 700;
  background: rgba(227,30,36,0.1);
  color: var(--red);
}

/* Referral link box */
.referral-box {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 12px 16px;
  font-family: var(--font-mono);
  font-size: 13px;
  display: flex; align-items: center; gap: 10px;
}
.referral-copy-btn {
  padding: 4px 12px; border-radius: 20px;
  background: rgba(227,30,36,0.12);
  border: 1px solid rgba(227,30,36,0.3);
  color: var(--red); font-size: 11px; cursor: pointer;
  white-space: nowrap;
}
```

---

### Task 9: wellcore-v8.js

**Files:**
- Create: `js/wellcore-v8.js`

Módulos:
```javascript
var V8 = {};

// Notifications
V8.Notifications = {
  count: 0,
  init: function(bellId, panelId) { /* bind bell click, load count */ },
  load: function() { /* GET /api/notifications/list, update badge */ },
  markAllRead: function() { /* POST /api/notifications/mark-read {all:true} */ }
};

// Profile edit
V8.Profile = {
  init: function(containerId) { /* load + render editable form */ },
  save: function(data) { /* POST /api/client/profile */ }
};

// Referrals
V8.Referrals = {
  init: function(containerId) { /* load + render */ },
  copyLink: function() { /* clipboard API con fallback */ }
};

// Renewal banner
V8.RenewalBanner = {
  check: function(subscriptionEnd) { /* si < 7 días, render banner */ }
};
```

---

## FASE 4 — Portal Cliente (cliente.html)

### Task 10: Notificaciones en navbar cliente

**Files:**
- Modify: `cliente.html` — topbar del portal

Agregar campana de notificaciones junto al avatar. Cargar badge al iniciar sesión. Panel dropdown con lista.

**HTML a agregar en topbar (antes del avatar):**
```html
<div class="notif-bell" id="clientNotifBell" onclick="V8.Notifications.togglePanel()">
  <i class="fa-solid fa-bell"></i>
  <span class="notif-badge" id="clientNotifBadge" style="display:none">0</span>
</div>
<div class="notif-panel" id="clientNotifPanel" style="display:none">
  <div style="padding:12px 16px;font-weight:700;border-bottom:1px solid var(--border)">
    Notificaciones <button onclick="V8.Notifications.markAllRead()">Marcar todas leídas</button>
  </div>
  <div id="clientNotifList"></div>
</div>
```

---

### Task 11: Tab Perfil en cliente.html

**Files:**
- Modify: `cliente.html` — agregar tab "Perfil" en la navegación de tabs

Tab nueva junto a Inicio/Entrenamiento/etc.

**Contenido del tab:**
- Avatar circular con inicial (editable en futuro con upload)
- Nombre editable
- Bio (textarea, 500 chars)
- Ciudad
- Fecha de nacimiento
- Botón guardar → POST /api/client/profile
- Sección "Plan actual" (readonly) con badge de color tier
- Sección "Membresía" con fecha de vencimiento + días restantes

---

### Task 12: Tab Referidos en cliente.html

**Files:**
- Modify: `cliente.html` — agregar tab "Referidos"

Contenido:
- Explicación del programa (cada referido que se convierte = 1 semana gratis)
- Tu código único: `[CODIGO] [Copiar]`
- Link completo: `wellcorefitness.com/inscripcion.html?ref=CODIGO`
- Tabla de referidos: email, estado (pendiente/registrado/convertido), fecha
- Contador: X amigos convertidos

---

### Task 13: Banner de renovación

**Files:**
- Modify: `cliente.html` — al cargar perfil

Lógica: Si `subscription_end` está a ≤7 días, mostrar banner amarillo:
> "⚠ Tu suscripción vence el 15 Mar · Renueva ahora para no perder tu progreso → [Renovar]"

---

### Task 14: Alerta check-in pendiente

**Files:**
- Modify: `cliente.html` — sección Inicio

Si el cliente no ha enviado check-in esta semana (calculado desde el último `checkin_date`), mostrar card:
> "📋 Es hora de tu check-in semanal · Cuéntale a tu coach cómo vas → [Enviar check-in]"

Solo mostrar si último check-in es > 6 días atrás o no existe.

---

## FASE 5 — Superadmin (admin.html)

### Task 15: Command Center ✅ COMPLETO

Completado en este commit. Ver `#section-dashboard` en admin.html y `api/admin/dashboard-stats.php`.

### Task 16: Notificaciones admin — bell en topbar

**Files:**
- Modify: `admin.html` — topbar

Misma campana que el cliente pero cargando notificaciones de admin (check-ins urgentes, pagos fallidos, clientes expirados).

### Task 17: Botón "Enviar Notificación" en sección Clientes

**Files:**
- Modify: `admin.html` — tabla de clientes

Agregar botón "🔔 Notificar" por cada cliente. Modal con título + cuerpo + link opcional. POST a `api/notifications/create.php`.

---

## FASE 6 — PWA (Progressive Web App)

### Task 18: manifest.json

**Files:**
- Create: `manifest.json`

```json
{
  "name": "WellCore Fitness",
  "short_name": "WellCore",
  "description": "Tu plataforma de transformación física en LATAM",
  "start_url": "/cliente.html",
  "display": "standalone",
  "background_color": "#0a0a0f",
  "theme_color": "#E31E24",
  "icons": [
    { "src": "/images/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/images/icon-512.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```

Agregar `<link rel="manifest" href="/manifest.json">` en cliente.html y index.html.

### Task 19: Service Worker

**Files:**
- Create: `sw.js`

Estrategia cache-first para assets estáticos (CSS, JS, imágenes). Network-first para API calls.

```javascript
var CACHE = 'wc-v1';
var STATIC = ['/css/wellcore-v7.css', '/css/wellcore-v8.css',
              '/js/api.js', '/js/wellcore-v7.js'];

self.addEventListener('install', function(e) {
  e.waitUntil(caches.open(CACHE).then(function(c){ return c.addAll(STATIC); }));
});
self.addEventListener('fetch', function(e) {
  if (e.request.url.includes('/api/')) return; // network-only para API
  e.respondWith(caches.match(e.request).then(function(r){ return r || fetch(e.request); }));
});
```

Registrar en cliente.html al final del body:
```javascript
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js');
}
```

---

## FASE 7 — Deploy a Producción

### Task 20: Git commit y push

```bash
git add -A
git commit -m "feat: v8 platform — notifications, profile, referrals, PWA, command center"
git push origin main
```

### Task 21: Git pull en Easypanel

1. Ir a `panel.wellcorefitness.com`
2. WellCore app → Code → Git → Pull
3. Verificar en consola que los archivos nuevos existen
4. Ejecutar migraciones SQL en la consola MySQL del panel:
   - `008_notifications.sql`
   - `009_referrals.sql`
   - `010_client_profile_fields.sql`

### Task 22: Smoke test en producción

- [ ] Login admin funciona → Command Center carga con datos reales
- [ ] KPIs muestran números correctos
- [ ] Gráfica de ingresos muestra barras
- [ ] Donut de planes muestra distribución
- [ ] Clientes en riesgo aparecen
- [ ] Login cliente → perfil editable → guardar
- [ ] Banner de renovación (si aplica)
- [ ] Notificaciones bell funciona

---

## Prioridad de Implementación

| Prioridad | Feature | Impacto | Esfuerzo |
|-----------|---------|---------|---------|
| 🔴 P1 | Command Center (✅ Hecho) | Alto | Alto |
| 🔴 P1 | Notificaciones cliente | Alto | Medio |
| 🔴 P1 | Banner renovación | Alto | Bajo |
| 🟡 P2 | Tab Perfil editable | Medio | Medio |
| 🟡 P2 | Alerta check-in | Medio | Bajo |
| 🟡 P2 | Notif admin bell | Medio | Bajo |
| 🟢 P3 | Referidos | Medio | Alto |
| 🟢 P3 | PWA manifest+SW | Medio | Bajo |
| 🟢 P3 | Botón notificar admin | Bajo | Bajo |

---

**Estado actual:** F1-F2 (DB + APIs de notif/perfil/referidos) pendientes. F5 Command Center completo.

**Siguiente paso sugerido:** Task 1-3 (migraciones), luego Task 4-6 (APIs), luego Task 10-14 (Portal Cliente).
