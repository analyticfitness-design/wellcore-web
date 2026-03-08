# WellCore v7 — PLAN DE IMPLEMENTACION COMPLETO PARA HANDOFF

**Fecha:** 2026-03-08
**Estado:** FASE 0 COMPLETA, FASE 1 PARCIAL (index.html empezado)
**Objetivo:** Transformar TODO el frontend de WellCore Fitness en un diseno ultra-premium, sofisticado, moderno y cinematografico digno del lanzamiento 2026/2027
**Base de diseno:** rise.html (bento grid, particles, kinetic typography, glassmorphism)

---

## CONTEXTO CRITICO — LEE ESTO PRIMERO

### Que es WellCore Fitness
- Empresa de coaching online 1:1 basado en ciencia (LATAM, espanol)
- URL local: https://wellcorefitness.test | Prod: https://www.wellcorefitness.com
- 42+ archivos HTML, frontend-only (PHP APIs ya existen, NO TOCAR)
- El usuario quiere que sea "la mejor empresa de coaching online en diseno visual"

### Tesis Visual
El usuario investigo 200 herramientas visuales modernas documentadas en:
`C:\Users\GODSF\Music\PROYECTO WELLCOREFITNESS\NUEVAS TENDENCIAS Y DESARROLLOSS VISUALES\WELLCORE_TENDENCIAS_2026.html`
De ahi selecciono las siguientes para implementar (por numero de herramienta):

### Herramientas Seleccionadas por el Usuario

| # | Nombre | Prioridad | Implementacion |
|---|--------|-----------|----------------|
| 13 | GSAP ScrollTrigger + Timeline | ALTO | Ya en v7.js como dependencia opcional |
| 15 | Anime.js v4 | MEDIO | Alternativa a GSAP para micro-animaciones |
| 18 | Rive State Machines | FUTURO | Coach virtual animado (requiere .riv asset) |
| 19 | Kinetic Typography | CRITICO | SplitText char-by-char con GSAP stagger. YA en v7.js |
| 20 | Micro-interactions | CRITICO | Hover translateY(-2px), active scale(0.98), shimmer. YA en v7.css |
| 21 | Three.js + WebGL | FUTURO | Backgrounds 3D experimentales |
| 22 | Spline 3D | FUTURO | Modelos 3D interactivos |
| 25 | Variable Fonts WOFF2 | ALTO | font-variation-settings en hover. Agregar a v7.css |
| 33 | Glassmorphism Dark UI | CRITICO | backdrop-filter blur(24px) + rgba bg. YA en v7.css |
| 34 | Canvas 2D Particles | CRITICO | Particulas rojas en heroes. YA en v7.js |
| 41 | CSS mix-blend-mode | ALTO | `difference` para hero text. Agregar a heroes |
| 45 | accent-color + light-dark() | ALTO | Native form styling. YA en v7.css |
| 48 | Barba.js Page Transitions | MEDIO | Transiciones cinematograficas entre paginas. Fase 6 |
| 50 | PIXI.js | FUTURO | Canvas avanzado |
| 51 | Vanta.js | MEDIO | WebGL animated NET backgrounds con WellCore red |
| 54 | Cursor Personalizado + Magnetic Buttons | CRITICO | YA en v7.js (cursor lerp + GSAP magnetic) |
| 55 | SVG Animations + clip-path Morphing | ALTO | stroke-dashoffset para progress circles de logros |
| 57 | tsParticles | MEDIO | Confetti celebration para achievements. Agregar CDN |
| 60 | CSS Skeleton Screens | CRITICO | YA en v6 y v7.css (shimmer loading) |
| 61 | CSS Image Comparison | ALTO | Before/After slider. YA en v6 |
| 67 | Whoop 4.0 Recovery Ring | CRITICO | conic-gradient progress rings para dashboards |
| 69 | NTC Progressive Disclosure | ALTO | Cards expandibles con --open variable |
| 70 | Freeletics AI Adaptive Interface | ALTO | UI colores semanticos segun rendimiento |
| 82 | Rive State Machines | FUTURO | Coach avatar animado |
| 88 | Theatre.js Cinematic Sequences | FUTURO | Timeline visual para intros |
| 94 | Adaptive Plan UI - Fases Visuales | CRITICO | --phase-color CSS property (load=rojo, deload=verde, peak=ambar, test=morado) |
| -- | CSS @counter-style | MEDIO | Numeracion custom para listas RISE y beneficios |

---

## ESTADO ACTUAL DE ARCHIVOS

### YA CREADOS (Fase 0 completa)

#### `css/wellcore-v7.css` — 2,072 lineas, 31 secciones
Contiene:
- @import fonts (Axiforma via cdnfonts, DIN Next LT Pro via cdnfonts)
- CSS custom properties completas (fonts, fluid typography, OKLCH colors, surfaces, borders, spacing, radius, easing)
- Global base styles con `accent-color: var(--wc-red)` y `color-scheme: light dark`
- Typography classes (.v7-hero, .v7-display, .v7-h1-h3, .v7-body, .v7-small, .v7-mono)
- Text effects (.v7-gradient-text, .v7-glow-text, .v7-text-outline)
- Section header pattern (.v7-section, .v7-eyebrow, .v7-section-title, .v7-section-subtitle)
- 5 button variants (.v7-btn-primary con shimmer, .v7-btn-secondary, .v7-btn-ghost, .v7-btn-glass, .v7-btn-glow)
- 4 card variants (.v7-card-glass, .v7-card-solid, .v7-card-gradient, .v7-card-bento)
- Mouse-tracking glow (.v7-glow-track)
- Bento grid system (12-col responsive)
- Badges & pills (5 color variants)
- Glassmorphism utilities (.v7-glass, .v7-glass-strong)
- Gradient mesh backgrounds (.v7-mesh)
- Parallax grid background (.v7-grid-bg)
- Floating geometric shapes (.v7-geo)
- Scroll-driven animations con IO fallback (.v7-reveal, .v7-reveal-left, .v7-reveal-right)
- Kinetic typography helpers (.v7-split-word, .v7-split-char)
- Form system (.v7-input, .v7-select, .v7-textarea, .v7-label)
- Tables (.v7-table)
- KPI cards (.v7-kpi-grid, .v7-kpi)
- Progress bars & rings
- Tabs (.v7-tab-nav, .v7-tab-btn)
- Accordion (.v7-accordion)
- Navbar v7 (.v7-nav)
- Footer v7 (4-col, gradient top border)
- Custom cursor (.v7-cursor-dot, .v7-cursor-circle)
- Scroll progress bar
- Toast notifications
- Skeleton loading
- @keyframes library
- Utility classes
- prefers-reduced-motion + print styles

#### `js/wellcore-v7.js` — 852 lineas, 16 sistemas
Contiene:
- IIFE con DOMContentLoaded
- `window.V7` global API (toast, confetti, countUp, lenis, destroy)
- Toast notifications
- Confetti system
- CountUp (data-v7-count)
- Magnetic buttons (GSAP con CSS fallback)
- Mouse-tracking glow handler (.v7-glow-track → --mx/--my)
- Custom cursor (lerp dot+circle, pointer:fine guard)
- Kinetic typography (.v7-split-word → split chars + animate on IO)
- Scroll reveal fallback (IO para .v7-reveal)
- Canvas 2D particles ([data-v7-particles])
- Scroll progress bar
- Lenis smooth scroll (opcional)
- Navbar scroll handler
- Mobile menu toggle
- Accordion system
- Cleanup/destroy

### PARCIALMENTE MODIFICADO

#### `index.html` — 4,024 lineas
Cambios ya aplicados:
- Linea ~55: Agregados links a cdnfonts (Axiforma, DIN Next LT Pro)
- Linea ~75: `--font-body` cambiado de 'Inter' a 'Axiforma', agregado `--font-data`
- Linea ~2492: Agregado `<link rel="stylesheet" href="css/wellcore-v7.css">`
- Linea ~3431: Agregado `<script src="js/wellcore-v7.js" defer></script>`
- Hero section: Agregado `<canvas class="v7-particles" data-v7-particles>`, clases v7-split-word, v7-btn-primary, v7-magnetic, stats bar con glassmorphism, fuente DIN Next para stats/prices
- Plan cards: Agregado .v7-glow-track a las 3 cards
- Proceso steps: Agregado .v7-reveal, fuente DIN Next para numeros grandes
- About: .v7-reveal en statement band, v7-split-word en quote
- CTA final: v7-reveal, v7-btn-primary, v7-btn-ghost, v7-split-word, v7-magnetic
- RISE section: v7-btn-primary en boton
- Footer: gradient top border via border-image, v7-reveal en col 1

### SIN MODIFICAR (pendientes)
TODAS las demas paginas HTML listadas abajo.

---

## SISTEMA TIPOGRAFICO v7

### Fuentes

| Rol | Fuente | Variable CSS | CDN |
|-----|--------|-------------|-----|
| Body/UI | Axiforma | `--v7-font-body` / `--font-body` | `https://fonts.cdnfonts.com/css/axiforma` |
| Display/Headings | Bebas Neue | `--v7-font-display` / `--font-head` | Google Fonts (ya incluido) |
| Data/Stats/Prices | DIN Next LT Pro | `--v7-font-data` / `--font-data` | `https://fonts.cdnfonts.com/css/din-next-lt-pro` |
| Impact Statements | Impact | `--v7-font-impact` | System font (no CDN) |
| Mono/Technical | JetBrains Mono | `--v7-font-mono` | Google Fonts (ya incluido) |

### Donde usar cada fuente
- **Axiforma**: Todo el body text, paragrafos, descripciones, nav links, botones, form inputs
- **Bebas Neue**: Headlines h1-h2, titulos de seccion, logo, nombres de planes
- **DIN Next LT Pro**: Precios ($399k), stats numericos (1:1, SEMANAL), eyebrows/labels (01 — DIAGNOSTICO), countdown numbers, KPI values, badges con datos
- **Impact**: Frases de impacto grandes ("TRANSFORMATE", "80%"), splash text
- **JetBrains Mono**: Codigo, elementos tecnicos, monospaced real (usar solo donde se necesite monospace real)

### Patron de override de variables
En cada pagina HTML, modificar el `:root` inline:
```css
:root {
  --font-body: 'Axiforma', 'Inter', system-ui, sans-serif;
  --font-head: 'Bebas Neue', Impact, sans-serif;
  --font-data: 'DIN Next LT Pro', 'Barlow', system-ui, sans-serif;
  /* --font-mono se mantiene JetBrains Mono */
}
```
Y donde haya `font-family: var(--font-mono)` para DATOS (no codigo), cambiar a `var(--font-data, var(--font-mono))`.

---

## INVENTARIO COMPLETO DE PAGINAS A MODIFICAR

### Fase 1: Paginas Publicas Core (4 paginas)
| Pagina | Lineas | Prioridad | Notas |
|--------|--------|-----------|-------|
| index.html | 4,024 | CRITICO | PARCIALMENTE HECHO (ver arriba) |
| metodo.html | ~2,000 | CRITICO | Pagina de metodologia |
| nosotros.html | ~1,800 | ALTO | Pagina about |
| proceso.html | ~1,500 | ALTO | Pagina de proceso |

### Fase 2: Paginas de Conversion (6 paginas)
| Pagina | Lineas | Prioridad | Notas |
|--------|--------|-----------|-------|
| planes.html | ~2,500 | CRITICO | Pricing — plan cards con glassmorphism |
| rise.html | ~3,000 | CRITICO | BASE DE DISENO — ya tiene bento grid y particles |
| inscripcion.html | ~1,500 | CRITICO | Formulario de inscripcion |
| coaches.html | ~2,000 | ALTO | Reclutamiento coaches |
| faq.html | ~1,500 | ALTO | FAQ page |
| tienda.html | ~1,200 | MEDIO | Tienda (basica) |

### Fase 3: Flujo RISE (3 paginas)
| Pagina | Lineas | Prioridad | Notas |
|--------|--------|-----------|-------|
| rise-enroll.html | ~1,200 | CRITICO | Enrollment form — NO ROMPER |
| rise-intake.html | ~1,500 | CRITICO | Intake form — NO ROMPER |
| rise-payment.html | ~1,200 | CRITICO | Wompi payment — NO ROMPER FLUJO DE PAGO |

### Fase 4: Dashboards (5 paginas)
| Pagina | Lineas | Prioridad | Notas |
|--------|--------|-----------|-------|
| login.html | ~800 | ALTO | Login con v7 forms |
| cliente.html | ~6,000 | CRITICO | Portal cliente — MAS COMPLEJO. 6 tabs, comunidad, chat |
| rise-dashboard.html | ~2,500 | ALTO | Dashboard RISE con gamificacion |
| coach-portal.html | ~2,000 | ALTO | Portal coach |
| admin.html | ~3,000 | ALTO | Panel admin con KPIs |

### Fase 5: Blog + Legal + Utility (20+ paginas)
| Pagina | Prioridad | Notas |
|--------|-----------|-------|
| blog/index.html | MEDIO | Blog listing |
| blog/*.html (10 articulos) | MEDIO | Blog articles |
| legal/privacidad.html | BAJO | Legal |
| legal/terminos.html | BAJO | Legal |
| legal/cookies.html | BAJO | Legal |
| 404.html | BAJO | Error page |
| pago-confirmado.html | MEDIO | Confetti + success |
| pago-exitoso.html | MEDIO | Success page |
| invoice.html | BAJO | Invoice template |
| _preview.html | BAJO | Admin preview bridge |
| maintenance.html | BAJO | Maintenance page |

### Fase 6: Performance + Polish
- Core Web Vitals audit
- Font subsetting
- View Transitions API
- Barba.js page transitions (opcional)
- content-visibility: auto
- Accessibility audit

---

## INSTRUCCIONES DE IMPLEMENTACION POR PAGINA

### Patron General (aplicar a CADA pagina HTML)

#### Paso 1: Agregar font links en <head>
Despues de la linea de Google Fonts existente, agregar:
```html
<!-- v7 Premium Fonts: Axiforma (body), DIN Next LT Pro (data) -->
<link rel="stylesheet" href="https://fonts.cdnfonts.com/css/axiforma" />
<link rel="stylesheet" href="https://fonts.cdnfonts.com/css/din-next-lt-pro" />
```

#### Paso 2: Agregar v7 CSS link
Despues de `<link rel="stylesheet" href="css/wellcore-v6.css">`, agregar:
```html
<!-- WellCore v7 Ultra-Premium Design System 2026 -->
<link rel="stylesheet" href="css/wellcore-v7.css">
```

#### Paso 3: Override font variables en :root
En el bloque `<style>` inline de la pagina, modificar `:root`:
```css
--font-body: 'Axiforma', 'Inter', system-ui, sans-serif;
--font-head: 'Bebas Neue', Impact, sans-serif;
--font-data: 'DIN Next LT Pro', 'Barlow', system-ui, sans-serif;
```

#### Paso 4: Agregar v7 JS script
Antes de `</body>`, despues de v6.js:
```html
<script src="js/wellcore-v7.js" defer></script>
```

#### Paso 5: Upgrades de componentes especificos

**Hero sections:**
- Agregar `<canvas class="v7-particles" data-v7-particles aria-hidden="true"></canvas>` dentro del hero
- Agregar CSS para el canvas: `position:absolute;inset:0;width:100%;height:100%;z-index:1;pointer-events:none;`
- Agregar `.v7-split-word` a headlines para kinetic typography
- Stats bar: agregar glassmorphism inline `style="background:rgba(255,255,255,0.03);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.08);border-radius:8px;"`

**Botones:**
- CTAs principales: agregar `.v7-btn-primary .v7-magnetic`
- CTAs secundarios: agregar `.v7-btn-ghost`
- Sobre imagenes: agregar `.v7-btn-glass`

**Cards:**
- Plan cards: agregar `.v7-glow-track`
- Blog cards: agregar `.v7-glow-track`
- Feature cards: agregar `.v7-card-glass` o `.v7-card-bento`

**Secciones:**
- Agregar `.v7-reveal` a contenedores principales para scroll animation
- Section headers: agregar v7 eyebrow pattern donde aplique

**Datos numericos:**
- Precios, stats, numeros: cambiar `font-family: var(--font-mono)` a `font-family: var(--font-data, var(--font-mono))`
- Numeros grandes (01, 02, 03): agregar `style="font-family:var(--font-data,var(--font-head));"`

**Footer:**
- Agregar gradient top border: `style="border-image:linear-gradient(90deg,transparent,var(--red),transparent) 1;border-top-width:2px;border-top-style:solid;"`

---

## SISTEMA DE LOGROS EXPANDIDO

### Logros Actuales (13 cliente + 8 coach = 21 total)
Ya implementados en DB + API. Ver:
- `api/community/achievements.php` — Definiciones + GET endpoint
- `api/community/check-achievements.php` — POST trigger + award
- `api/setup/migrate-community.php` — Schema SQL

### Nuevos Logros a Agregar (expandir la lista)

#### Para Todos los Planes:
- **consistency_30** — "30 Dias Consistente" | Icon: calendar-check | 30 check-ins consecutivos
- **workout_100** — "Centurion" | Icon: dumbbell | 100 entrenamientos completados
- **early_bird** — "Madrugador" | Icon: sun | 10 check-ins antes de 8am
- **photo_progress_3** — "Documentador" | Icon: images | 3 sets de fotos de progreso
- **referral_first** — "Embajador" | Icon: share | Primer referido exitoso
- **community_helper** — "Mentor" | Icon: hands-helping | 10 comentarios utiles en comunidad
- **streak_30** — "Maquina" | Icon: fire-flame-curved | 30 dias de racha consecutiva
- **transformation** — "Transformacion Real" | Icon: person-rays | Cambio medible verificado por coach

#### Solo RISE:
- **rise_perfect_week** — "Semana Perfecta RISE" | Icon: crown | 7/7 dias completados en una semana
- **rise_no_skip** — "Sin Excusas" | Icon: shield-check | 0 dias saltados en todo el reto
- **rise_first_pr** — "Record Personal" | Icon: arrow-trend-up | Primer PR durante el reto

#### Solo Elite:
- **elite_bloodwork** — "Data Driven" | Icon: vial | Primer analisis de sangre subido
- **elite_deload_master** — "Recuperacion Inteligente" | Icon: bed | Completar 3 deloads programados
- **elite_body_comp** — "Recomposicion" | Icon: scale-balanced | Mejora medible en composicion corporal

#### Solo Coach:
- **coach_perfect_sla** — "SLA Perfecto" | Icon: clock | 30 dias con 100% SLA
- **coach_retention_90** — "Retencion 90%" | Icon: magnet | 90%+ retencion de clientes por 3 meses

### Implementacion Visual de Logros

Los logros deben usar SVG progress circles con stroke-dashoffset (herramienta #55):
```css
.achievement-ring {
  width: 80px; height: 80px;
  transform: rotate(-90deg);
}
.achievement-ring circle {
  fill: none;
  stroke-width: 4;
  stroke-linecap: round;
}
.achievement-ring .bg { stroke: rgba(255,255,255,0.06); }
.achievement-ring .progress {
  stroke: var(--wc-red);
  stroke-dasharray: 226; /* 2*PI*36 */
  stroke-dashoffset: 226;
  transition: stroke-dashoffset 1.5s ease;
}
.achievement-ring.earned .progress {
  stroke-dashoffset: 0;
}
```

Cada logro debe tener:
- SVG ring con progress animado (stroke-dashoffset)
- Icono Font Awesome en el centro
- Titulo + descripcion debajo
- Estado: locked (gris, blur), in-progress (parcial, con porcentaje), earned (full color, glow)
- Al desbloquear: confetti burst (V7.confetti()) + toast notification

### Donde mostrar logros:
1. **cliente.html** — Seccion Comunidad, tab existente + nueva seccion "Mis Logros"
2. **rise-dashboard.html** — Badges section (ya existe, expandir)
3. **coach-portal.html** — Seccion de achievements (ya existe, expandir)
4. **admin.html** — Vista de logros por cliente

---

## DASHBOARD CLIENTE — ADAPTIVE UI (Herramienta #70 y #94)

### Fases Visuales del Plan de Entrenamiento
Implementar `--phase-color` CSS custom property que cambia segun la fase del mesociclo:

```javascript
var PHASES = {
  load:    { color: '#E31E24', label: 'CARGA',        icon: 'fire' },
  deload:  { color: '#10b981', label: 'DESCARGA',     icon: 'leaf' },
  peak:    { color: '#f59e0b', label: 'PICO',         icon: 'bolt' },
  test:    { color: '#a855f7', label: 'TEST/DELOAD',  icon: 'flask' }
};
// Ciclo tipico: load, load, load, deload (cada 4 semanas)
var cycle = ['load','load','load','deload'];
var week = /* semana actual del cliente */;
var phase = PHASES[cycle[(week - 1) % 4]];
document.documentElement.style.setProperty('--phase-color', phase.color);
```

CSS que usa --phase-color:
```css
body[data-phase="load"]   { --phase-color: #E31E24; }
body[data-phase="deload"] { --phase-color: #10b981; }
body[data-phase="peak"]   { --phase-color: #f59e0b; }
body[data-phase="test"]   { --phase-color: #a855f7; }

.phase-indicator {
  background: var(--phase-color);
  /* se aplica a: XP bar, progress rings, badges activos, header accent */
}
```

### Whoop-Style Recovery Rings (Herramienta #67)
Para el dashboard del cliente, usar conic-gradient:
```css
.recovery-ring {
  width: 120px; height: 120px;
  border-radius: 50%;
  background: conic-gradient(
    var(--phase-color) calc(var(--pct) * 1%),
    rgba(255,255,255,0.05) 0%
  );
  display: flex; align-items: center; justify-content: center;
}
.recovery-ring::after {
  content: '';
  width: 100px; height: 100px;
  border-radius: 50%;
  background: var(--bg);
}
```

---

## REGLAS DE ORO — CRITICO

1. **NUNCA tocar /api/ ni la base de datos** — Solo frontend HTML/CSS/JS
2. **NUNCA eliminar clases CSS/JS existentes** — Solo AGREGAR v7 layer encima
3. **Wompi/pagos DEBEN seguir funcionando** — Test payment flow en rise-payment.html
4. **Auth/login DEBE seguir funcionando** — Test login + impersonation
5. **Dashboards DEBEN seguir funcionando** — Test data loading en cliente.html, admin.html
6. **Mobile-first** — Cada cambio testeado en 375px, 768px, 1024px, 1440px
7. **Performance budget** — Total v7 CSS + JS < 100KB combined
8. **Primero local (wellcorefitness.test) → luego produccion**
9. **Git commit por fase** — Un commit limpio por fase completada
10. **NO crear archivos nuevos innecesarios** — Preferir editar existentes
11. **NO agregar emojis** al codigo
12. **Mantener patron de fuentes** — Si algo usaba var(--font-mono) para DATOS, cambiar a var(--font-data). Si usaba var(--font-mono) para CODIGO REAL, dejar como esta.

---

## CSS LAYERS EXISTENTES (NO borrar, solo agregar v7 encima)

| Archivo | Lineas | Rol |
|---------|--------|-----|
| Inline `<style>` en cada HTML | Variable | v3 base styles por pagina |
| css/wellcore-base.css | ~342 | v3 foundation (legacy) |
| css/wellcore-v5.css | ~1,427 | v5 system |
| css/wellcore-premium.css | ~650 | Legacy overrides |
| css/wellcore-v6.css | ~880 | v6 modern (scroll-driven, skeleton, toast, badges) |
| **css/wellcore-v7.css** | **2,072** | **v7 ultra-premium (NUEVO)** |

Orden de carga: base → v5 → premium → v6 → **v7** (v7 gana por cascade)

---

## JS EXISTENTES

| Archivo | Lineas | Rol |
|---------|--------|-----|
| js/v5-effects.js | ~200 | v5 preloader, mobile menu toggle |
| js/wellcore-v6.js | ~310 | v6 toast, magnetic, IO reveal, countUp, Lenis, cursor |
| **js/wellcore-v7.js** | **852** | **v7 completo (NUEVO)** |

Scripts CDN usados:
- Bootstrap 5.3
- AOS 2.3.1
- GSAP 3.12.7 + ScrollTrigger (CDN, defer)
- Lenis 1.1.14 (CDN, defer)

---

## DEPLOY

### Local
```bash
# Acceder via Herd (Laravel Herd)
# URL: https://wellcorefitness.test
```

### Produccion
```bash
# SSH a Easypanel
cd /code && git pull origin main
# Panel: panel.wellcorefitness.com
```

---

## CREDENCIALES PARA TESTING

### Admin (para verificar dashboards)
- URL: /admin.html
- Usuario: `daniel.esparza`
- Password: `RISE2026Admin!SuperPower`

### Clientes demo (via impersonacion admin)
- Elite: ID 9 (elite@wellcore.com)
- Metodo: ID 2 (maria.garcia@email.com)
- Esencial: ID 1 (juan.perez@email.com)

Flujo: Login admin → Clientes → "Ver Portal" → abre /_preview.html con token temporal

---

## CHECKLIST POR FASE

### Fase 1: Paginas Publicas Core
- [ ] index.html — COMPLETAR upgrades pendientes (blog cards v7-glow-track, FAQ v7-reveal, mobile audit)
- [ ] metodo.html — Full v7 upgrade
- [ ] nosotros.html — Full v7 upgrade
- [ ] proceso.html — Full v7 upgrade
- [ ] Test en mobile 375px
- [ ] Git commit

### Fase 2: Paginas de Conversion
- [ ] planes.html — Glassmorphism plan cards, DIN Next precios, v7 buttons
- [ ] rise.html — Ya es la base de diseno, upgrade fonts + agregar v7 classes
- [ ] inscripcion.html — v7 form system
- [ ] coaches.html — v7 cards + bento
- [ ] faq.html — v7 accordion + fonts
- [ ] tienda.html — v7 cards
- [ ] Test conversion flows
- [ ] Git commit

### Fase 3: Flujo RISE
- [ ] rise-enroll.html — v7 forms (NO ROMPER enrollment)
- [ ] rise-intake.html — v7 forms (NO ROMPER intake save)
- [ ] rise-payment.html — v7 visual ONLY (NO ROMPER Wompi payment)
- [ ] Test flujo completo: enroll → intake → payment
- [ ] Git commit

### Fase 4: Dashboards
- [ ] login.html — v7 premium login
- [ ] cliente.html — v7 fonts + adaptive UI + expanded achievements + recovery rings
- [ ] rise-dashboard.html — v7 gamification upgrade + new achievement badges
- [ ] coach-portal.html — v7 fonts + achievement display
- [ ] admin.html — v7 KPI cards + fonts
- [ ] Test auth + data loading en cada dashboard
- [ ] Git commit

### Fase 5: Blog + Legal + Utility
- [ ] blog/index.html + 10 articulos — v7 fonts + cards
- [ ] legal/*.html (3) — v7 fonts
- [ ] pago-confirmado.html — v7 confetti + success
- [ ] 404.html, maintenance.html, etc
- [ ] Git commit

### Fase 6: Performance + Polish
- [ ] Core Web Vitals < LCP 2.5s, CLS 0.1, INP 200ms
- [ ] Font preload hints
- [ ] content-visibility: auto en secciones below-fold
- [ ] Accessibility: focus-visible, aria labels, reduced-motion
- [ ] View Transitions API (opcional)
- [ ] Deploy a produccion
- [ ] Git commit final

---

## NOTA IMPORTANTE SOBRE LOGROS

El usuario pidio explicitamente: "necesito que agregues algo de crear logros, varios logros mas personalizados que motiven a los asesorados y la comunidad."

Esto implica:
1. Expandir la lista de achievements en `api/community/achievements.php` (agregar los nuevos achievement_type)
2. Expandir la logica de check en `api/community/check-achievements.php`
3. Actualizar el SQL schema si se necesitan nuevas columnas
4. Crear la UI visual con SVG rings en cliente.html y rise-dashboard.html
5. NOTA: Esto SI requiere tocar archivos PHP en /api/ — es la UNICA excepcion a la regla "no tocar API"

---

## REFERENCIAS VISUALES ADICIONALES DEL USUARIO

El usuario envio multiples screenshots de la TESIS_VISUAL como inspiracion directa. Resumen consolidado:

### Efectos CSS Nativos a Implementar
- **Glassmorphism Dark UI (#33)**: `backdrop-filter: blur(24px) saturate(180%)`, `background: rgba(255,255,255,0.05)`, `border: 1px solid rgba(255,255,255,0.08)` — Aplicar a: nav scrolled, stat bars, cards, modals
- **Canvas 2D Particles (#34)**: 50 desktop / 25 mobile, WellCore red, IO-paused — Aplicar a: todos los heroes
- **mix-blend-mode (#41)**: `difference` para hero text sobre fotos, `multiply` para photo overlays
- **accent-color (#45)**: `accent-color: var(--wc-red)` + `color-scheme: light dark` en formularios
- **CSS @counter-style**: `@counter-style wellcore-steps { system: cyclic; symbols: '01' '02' '03' ... '08'; suffix: ' — '; }` para listas numeradas
- **Skeleton Screens (#60)**: shimmer loading ya en v6/v7
- **Before/After Slider (#61)**: clip-path + JS drag ya en v6

### Animaciones y Motion
- **GSAP ScrollTrigger (#13)**: Timeline animations en scroll
- **Kinetic Typography (#19)**: SplitText char-by-char con stagger 40ms
- **Micro-interactions (#20)**: hover translateY(-2px), active scale(0.98), shimmer sweep
- **Cursor Personalizado + Magnetic Buttons (#54)**: lerp cursor + GSAP elastic magnetic
- **SVG Animations + clip-path Morphing (#55)**: stroke-dashoffset drawing effect para progress circles
- **Barba.js Page Transitions (#48)**: GSAP leave/enter entre paginas (Fase 6)

### Competitive Intelligence (Patrones de Apps Fitness)
- **Whoop 4.0 Recovery Ring (#67)**: conic-gradient dinamico, color semantico por zona, sin bordes
- **NTC Progressive Disclosure (#69)**: Cards con `max-height: calc(72px + var(--open) * 360px); transition: max-height 0.4s` — collapse/expand
- **Freeletics AI Adaptive Interface (#70)**: UI cambia color segun fatiga (verde=alcanzable, rojo=stretch, gris=recuperacion)
- **Adaptive Plan UI - Fases Visuales (#94)**: `--phase-color` CSS property global que cambia segun fase del mesociclo

### Herramientas Avanzadas (Futuro)
- **Rive State Machines (#82)**: Coach avatar animado con state machines (idle/encouraging/celebrating)
- **Theatre.js (#88)**: Timeline visual para secuencias cinematograficas de intro
- **Three.js/WebGL (#21)**: Backgrounds 3D
- **Spline 3D (#22)**: Modelos interactivos
- **Vanta.js (#51)**: WebGL NET effect con WellCore red `0xe31e24`
- **PIXI.js (#50)**: Canvas avanzado
- **tsParticles (#57)**: Confetti celebration (shape: 'confetti', colors: ['#E31E24', '#fff', '#FFD700'])

### Variable Fonts (#25)
```css
/* En hover, animar peso de fuente */
.v7-variable-weight {
  font-variation-settings: 'wght' 400;
  transition: font-variation-settings 0.3s ease;
}
.v7-variable-weight:hover {
  font-variation-settings: 'wght' 700;
}
```

---

## ARQUITECTURA DEL PROYECTO

```
wellcorefitness/
├── css/
│   ├── wellcore-base.css      (v3, no tocar)
│   ├── wellcore-v5.css        (v5, no tocar)
│   ├── wellcore-premium.css   (legacy, no tocar)
│   ├── wellcore-v6.css        (v6, no tocar)
│   └── wellcore-v7.css        (v7, NUEVO ✅)
├── js/
│   ├── v5-effects.js          (v5 preloader/menu)
│   ├── wellcore-v6.js         (v6 effects)
│   └── wellcore-v7.js         (v7, NUEVO ✅)
├── api/                       (PHP backend, NO TOCAR excepto achievements)
├── images/                    (assets, no tocar)
├── blog/                      (10 articulos HTML)
├── legal/                     (3 paginas legales)
├── planes/                    (6 demos de planes)
├── docs/plans/                (planes de implementacion)
├── index.html                 (PARCIALMENTE v7 ✅)
├── [40+ paginas HTML]         (PENDIENTES)
└── CLAUDE.md                  (instrucciones del proyecto)
```
