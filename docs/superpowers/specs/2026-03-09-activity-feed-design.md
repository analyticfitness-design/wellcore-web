# Activity Feed Flotante — Diseño de Especificación

**Fecha:** 2026-03-09
**Proyecto:** WellCore Fitness Admin Dashboard
**Autor:** Claude Code
**Estado:** Aprobado

---

## 📋 Resumen Ejecutivo

Agregar un panel flotante "Activity Feed" en la esquina superior derecha del dashboard de superadmin (`admin.html`) que muestre notificaciones en tiempo real de todas las acciones que realizan los clientes. El panel será brutal en diseño, incorporando los efectos visuales épicos de WellCore v7 (glassmorphism, glow, particles, smooth transitions, magnetic effects).

**Objetivo:** Que el superadmin vea instantáneamente qué están haciendo sus clientes (check-ins, métricas, retos, mensajes, contenido de academia) con acumulados por día y capacidad de filtrar por cliente específico.

---

## 🎯 Requisitos Funcionales

### Frontend
- **Panel flotante fijo** en esquina superior derecha
- **Dimensiones:** width 360px, altura variable (máx 600px)
- **Posición:** z-index 1000+ (encima de todo menos modales críticos)
- **Collapse/Expand:** Botón para minimizar/maximizar
- **Actualización automática:** Cada 4 segundos (polling a `/api/admin/activity-feed.php`)
- **Filtro de cliente:** Dropdown para ver "All Clients" o cliente específico
- **Breakdown visual:** Contador compacto mostrando hoy: 47 EVENTS con desglose por tipo (✅ 15 | 📈 12 | 🏆 8 | 💬 12)
- **Feed scrolleable:** Últimos 15 eventos con avatar, nombre, acción, timestamp relativo
- **Interactividad:** Click en evento → impersonate cliente (ir a su portal)

### Backend
- **Nueva API:** `/api/admin/activity-feed.php`
  - `GET` con parámetros: `client_id` (optional), `limit` (default 15), `type` (optional)
  - Retorna JSON con `today_count`, `breakdown`, `events[]`
- **Tabla de auditoría:** `admin_activity_log` que registra cada vez que superadmin abre/usa el feed (para demostración IA después)
- **Eventos a capturar de:**
  - `checkin` → `client_checkins`
  - `metric` → `biometric_logs`
  - `challenge` → `challenge_progress`
  - `academy` → `academy_progress`
  - `message` → `messages` (to coach)
  - `plan_change` → `client_subscriptions` (cambios)

---

## 🎨 Diseño Visual (Brutal Mode)

### Color Scheme
- Fondo panel: `var(--surface)` (#0f0f12) con border `var(--border-2)` (#2a2a2f)
- Texto eventos: `var(--white)` (#EEEEF0)
- Timestamps: `var(--gray)` (#8b8b96)
- Acciones color-coded:
  - ✅ Check-in: `var(--green)` (#22C55E)
  - 📈 Metric: `var(--blue)` (#3B82F6) [nuevo]
  - 🏆 Challenge: `var(--gold)` (#F5C842)
  - 💬 Message: `var(--red)` (#E31E24)
  - 📚 Academy: `var(--cyan)` (#06B6D4) [nuevo]

### Efectos Visuales (v7 Integration)
- **Glassmorphism:** `backdrop-filter: blur(12px)` + `background: rgba(15, 15, 18, 0.8)` + border glow sutil
- **Glow en eventos nuevos:** Shadow animado `0 0 20px rgba(227, 30, 36, 0.3)` al entrar (300ms)
- **Canvas particles:** Mini particles flotantes dentro del panel (opcional pero épico)
- **Gradient header:** Linear gradient `from-red to-transparent` en header con fade
- **Custom scrollbar:** Thin, red accent (match v7)
- **Magnetic hover:** Eventos se "mueven" ligeramente en hover (`transform: translate`)
- **Reveal animations:** Eventos aparecen con fade + scale staggered (patrón v7-reveal)
- **Split-word typography:** Título con efecto v7-split-word si es posible
- **View Transitions API:** Smooth updates entre refreshes (fade)

### Layout Específico
```
┌─────────────────────────────────────┐
│ ≡ ACTIVITY FEED    ━━━ ⊟ ✕         │ ← header con collapse
├─────────────────────────────────────┤
│ TODAY: 47 EVENTS                    │
│ [✅ 15] [📈 12] [🏆 8] [💬 12]      │ ← mini-cards clickeables (filtro)
├─────────────────────────────────────┤
│ [All Clients ▼]                     │ ← dropdown selector
├─────────────────────────────────────┤
│ 🔔 Luis Angarita         5m ago ✓   │
│    ✅ Completed check-in            │
│ ─────────────────────────────────── │
│ 🔔 Silvia Carvajal       12m ago ✓  │
│    📈 Logged weight: 68.5kg         │
│ ─────────────────────────────────── │
│ 🔔 Cliente Esencial      18m ago ✓  │
│    🏆 Progreso en reto              │
│   [scroll...]                       │
└─────────────────────────────────────┘
```

---

## 🔄 Data Flow

### Request/Response
```
Client JS → GET /api/admin/activity-feed.php?client_id=12&limit=15
Backend → Query múltiples tablas (checkins, metrics, challenges, etc)
Backend → Agrupa por tipo, calcula today_count, retorna JSON
Frontend → Renderiza panel, anima eventos nuevos, actualiza contador
JS → Registra uso en admin_activity_log (para auditoría IA)
```

### Polling Cycle
1. Cada 4 segundos: JS hace fetch a `/api/admin/activity-feed.php`
2. Compara eventos nuevos vs. anteriores
3. Anima entrada de eventos nuevos con glow + reveal
4. Actualiza contador si cambió
5. Mantiene scroll position (no interrumpe lectura)

---

## 🛠️ Implementación Técnica

### Stack
- **Frontend:** JavaScript vanilla (sin dependencias extra) + CSS v7 patterns
- **Backend:** PHP con PDO queries optimizadas
- **Database:** MySQL queries contra múltiples tablas con UNION/JOIN eficientes
- **Animations:** CSS animations + JS event listeners (View Transitions API donde sea soportado)

### Archivos a Crear/Modificar
1. **Nuevo:** `/api/admin/activity-feed.php` (endpoint API)
2. **Nuevo:** `/includes/activity-log.php` (helper para registrar uso)
3. **Nuevo:** Database migration para tabla `admin_activity_log`
4. **Modificar:** `admin.html` (agregar panel + CSS + JS)
5. **Opcional:** CSS nuevo en `css/activity-feed-brutal.css` (si v7 no cubre todo)

---

## ✅ Criterios de Éxito

- [ ] Panel aparece en esquina superior derecha de admin.html
- [ ] Se actualiza cada 4 segundos sin bloquear interacción
- [ ] Muestra breakdown correcto por tipo de acción
- [ ] Filtro "All Clients" vs cliente específico funciona
- [ ] Animaciones son smooth y épicas (no laggy)
- [ ] Click en evento abre impersonate del cliente
- [ ] Cada uso se registra en `admin_activity_log`
- [ ] Styling brutal: glassmorphism, glow, particles, magnetic effects

---

## 📊 Eventos Capturados (Ejemplo)

```json
{
  "today_count": 47,
  "breakdown": {
    "checkin": 15,
    "metric": 12,
    "challenge": 8,
    "message": 7,
    "academy": 5
  },
  "events": [
    {
      "id": "evt_001",
      "client_id": 12,
      "client_name": "Luis Angarita",
      "client_avatar": "LE",
      "action": "checkin",
      "description": "Completed check-in",
      "timestamp": "2026-03-09T14:32:00Z",
      "metadata": {"workout": "Leg Day", "duration": "45min"}
    },
    {
      "id": "evt_002",
      "client_id": 5,
      "client_name": "Silvia Carvajal",
      "client_avatar": "SC",
      "action": "metric",
      "description": "Logged weight: 68.5kg",
      "timestamp": "2026-03-09T14:28:00Z",
      "metadata": {"value": 68.5, "unit": "kg"}
    }
  ]
}
```

---

## 🔐 Seguridad & Auditoría

- Solo accesible para superadmin (validar rol en `/api/admin/activity-feed.php`)
- Registrar en `admin_activity_log` cada uso (timestamp, user, filters usados)
- No exponer datos sensibles de clientes (solo nombre, email cuando sea necesario)
- Rate-limit: máximo 1 request cada 2 segundos por admin

---

## 📝 Notas de Implementación

- Usar clases v7 existentes (`v7-glow-track`, `v7-btn-primary`, `v7-magnetic`, etc)
- Reutilizar variables CSS de v7 para consistencia
- JavaScript sin framework (vanilla para performance)
- Considerar Web Workers si polling causa lag en admin con muchos clientes
- Pruebas en producción: validar que las queries no causen slowdown

---

**Próximo paso:** Invocar `superpowers:writing-plans` para crear plan de implementación detallado.
