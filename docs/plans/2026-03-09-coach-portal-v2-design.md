# Coach Portal v2 — Diseño de Implementación
**Fecha:** 2026-03-09
**Stack:** PHP + HTML vanilla + CSS inline + api.js — SIN romper nada existente
**Prioridad:** Fases deployables independientemente

---

## Contexto

El coach-portal.html actual tiene 2,025 líneas, 7 secciones funcionales con APIs
reales, pero visualmente está 30% por debajo de cliente.html. No tiene V8, no tiene
partículas, no tiene magnetic buttons, y las secciones Marca/Referidos son básicas.
El objetivo es convertirlo en el diferenciador clave de WellCore para atraer coaches.

---

## Stack Constraints (NO ROMPER)

- PHP 8+ con PDO MySQL
- HTML vanilla (sin frameworks JS)
- CSS inline en el archivo + css/wellcore-v7.css + css/wellcore-v8.css
- api.js para llamadas API (agrega .php automáticamente)
- Tablas: `admins`, `clients`, `auth_tokens`, `referrals`, `coach_profiles`
- Auth: Bearer token, role='coach', single-session

---

## Fase 1 — Visual Superpremium + V8 + Notificaciones

### Objetivo
El coach entra y siente que tiene la mejor herramienta del mercado.

### Cambios Visuales
1. **Canvas particles** en topbar hero strip (10 partículas, densidad baja)
2. **Custom cursor** premium (red dot + ring, solo desktop) via wellcore-v7.js
3. **Lenis smooth scroll** CDN (3KB) para scroll suave
4. **`v7-magnetic`** en todos los botones CTA (Nuevo Ticket, Guardar, Copiar Link)
5. **`v7-glow-track`** en cards de Marca, Referidos, Recursos (actualmente sin glow)
6. **Skeleton loaders** en tabla clientes, KPIs dashboard (ya tiene estructura, agregar CSS)
7. **Animación stagger** en KPI cards al cargar sección (delay incremental)
8. **Glassmorphism mejorado** en sidebar (backdrop-filter: blur(20px) + border gradient)
9. **`v7-split-word`** en títulos faltantes: MI//MARCA, REFERI//DOS, RECUR//SOS
10. **Topbar redesign**: agregar bell icon V8 + indicador ping online animado

### V8 Integration
- Cargar `css/wellcore-v8.css` + `js/wellcore-v8.js` en coach-portal.html
- Adaptar `V8.Notifications.init()` para rol coach (eventos: cliente_asignado, ticket_respondido, pago_recibido)
- Bell icon en topbar con badge counter
- Endpoint nuevo: `GET /api/coach/notifications.php`

### API Nueva (Fase 1)
- `GET /api/coach/notifications.php` — Lista notificaciones del coach (últimas 20)

---

## Fase 2 — Comunicación + Negocio

### Sección: Centro de Mensajes (nueva)
- Chat directo coach ↔ cliente (sin WhatsApp externo)
- Templates: Bienvenida, Motivación, Check-in Request, Recordatorio Pago
- Broadcast: mensaje a todos los clientes activos del coach
- Tabla `coach_messages` (coach_id, client_id, message, direction, created_at, read_at)

### Sección: Revenue Dashboard (expandir dashboard existente)
- MRR del coach (suma comisiones mes actual)
- Histórico 6 meses con sparkline
- Proyección mes próximo
- Tabla clientes por renovar en 7/14/30 días
- Botón "Enviar recordatorio" por cliente → dispara template de mensaje

### Pipeline de Prospectos
- Sub-sección en Referidos existente
- Leads desde link referral con estado: contactado/interesado/convertido
- Acción directa por lead

### APIs Nuevas (Fase 2)
- `GET/POST /api/coach/messages.php` — Mensajería coach-cliente
- `GET /api/coach/revenue.php` — Revenue histórico + proyección
- `GET /api/coach/renewals.php` — Clientes por renovar
- `POST /api/coach/broadcast.php` — Mensaje masivo

---

## Fase 3 — Comunidad + PWA White-Label

### Sección: Comunidad (nueva)
- Feed privado entre coaches WellCore
- Leaderboard mensual (clientes activos, revenue, retención)
- Biblioteca compartida de rutinas/plantillas
- Sistema de logros colectivos

### PWA White-Label
- Coach sube logo, define nombre de app, color primario
- Sistema genera `manifest-{coach_id}.json` dinámico
- `sw-{coach_id}.js` personalizado
- Sub-dominio `{slug}.wellcorefitness.com` con NGINX rewrite
- Preview en tiempo real en sección "Mi Marca"
- Tabla `coach_pwa_config` (coach_id, app_name, icon_url, color, subdomain)

### APIs Nuevas (Fase 3)
- `GET/POST /api/coach/community.php` — Feed comunidad
- `GET /api/coach/leaderboard.php` — Rankings
- `GET/POST /api/coach/pwa-config.php` — Config PWA white-label
- `GET /api/pwa/manifest.php?coach={slug}` — Manifest dinámico
- `GET /api/pwa/sw.php?coach={slug}` — Service worker dinámico

---

## Prioridad de Ejecución

| Orden | Tarea | Impacto | Riesgo |
|-------|-------|---------|--------|
| 1 | Cargar V8 en coach-portal | Alto | Cero |
| 2 | v7-magnetic + v7-glow-track completo | Alto | Cero |
| 3 | Canvas particles + cursor + Lenis | Alto | Bajo |
| 4 | Topbar redesign + skeleton loaders | Medio | Bajo |
| 5 | Notifications API | Medio | Bajo |
| 6 | Centro de mensajes | Alto | Medio |
| 7 | Revenue dashboard | Alto | Bajo |
| 8 | Pipeline prospectos | Medio | Bajo |
| 9 | Comunidad feed | Alto | Medio |
| 10 | PWA white-label | Muy Alto | Medio |
