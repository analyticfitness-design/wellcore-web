# WellCore Platform — Plan de Upgrade Total 2026

> **Para Claude:** Usar `superpowers:executing-plans` para implementar este plan tarea por tarea.

**Goal:** Llevar toda la plataforma WellCore (landing, dashboards, admin, RISE) a nivel de app premium comparable a Whoop, Nike Training Club y Peloton — sin cambiar el stack base (vanilla HTML/CSS/JS + PHP).

**Architecture:** Mejoras incrementales y aditivas que no rompen lo existente. Cada módulo es independiente. Se puede implementar en cualquier orden sin riesgo.

**Tech Stack actual:** Vanilla HTML/CSS/JS · PHP · MySQL · Font Awesome 6.4 · AOS 2.3.4 · Bootstrap 5.3 (solo checkout)

---

## INVESTIGACIÓN DE MERCADO — Hallazgos 2026

### Estado del Arte (Marzo 2026)

| Área | Referentes | Lo que usan |
|------|-----------|-------------|
| Fitness Premium | Whoop, Peloton, Nike TC | Gamificación, streaks, confetti, gráficas de progreso real |
| CSS 2026 | Chrome 125+, Safari 26+, Firefox 147+ | scroll-driven animations, anchor positioning, CSS if(), @scope, masonry |
| Charts | Chart.js, ApexCharts, CanvasJS | ApexCharts = mejor balance size/features para fitness |
| Animaciones | GSAP 3, Motion.js, Anime.js | GSAP domina en vanillaJS, 23KB, 20x más rápido que jQuery |
| Performance | Interop 2026, Core Web Vitals | INP <200ms, LCP <2.5s, CLS <0.1 = +8-15% SEO |
| UX Conversión | Unbounce, Optimizepress | Video +34%, fotos reales +35%, CTA sticky = +20-35% conversión |

---

## MÓDULOS DE UPGRADE — 6 Áreas Críticas

---

## MÓDULO 1 — CSS 2026 Nativo (Base de Todo)
**Impacto:** Toda la plataforma · **Riesgo:** Cero · **Tiempo:** 3-4 días

### ¿Qué es?
Features de CSS que ya tienen soporte completo en Chrome 125+, Safari 26+, Firefox 147+ gracias a **Interop 2025/2026**. Son nativas del browser, sin librerias.

### Herramientas Específicas

**1.1 — CSS `@property` + Design Tokens OKLCH**
```css
/* ANTES: colores planos HEX */
:root { --red: #E31E24; }

/* DESPUÉS: sistema de tokens semánticos con OKLCH */
@layer tokens {
  :root {
    --brand-hue: 4;
    --brand: oklch(52% 0.28 var(--brand-hue));
    --brand-light: oklch(72% 0.22 var(--brand-hue));
    --brand-glow: oklch(52% 0.28 var(--brand-hue) / 30%);
    --surface-1: oklch(12% 0.02 240);
    --surface-2: oklch(16% 0.02 240);
    --text-1: oklch(95% 0.01 240);
  }
}
```
Beneficio: Colores P3 más vibrantes (imposibles con HEX), dark mode trivial, brand consistency.

**1.2 — CSS `@layer` Cascade Layers**
```css
/* Organización sin !important */
@layer reset, tokens, base, components, utilities, overrides;

@layer components {
  .btn-primary { background: var(--brand); }
}
@layer overrides {
  /* Las overrides siempre ganan — sin specificity wars */
  .btn-primary { color: white; }
}
```
Beneficio: Elimina todos los conflictos de especificidad del CSS actual.

**1.3 — CSS Scroll-Driven Animations (Chrome 115+, Safari 26+)**
```css
/* Reemplaza AOS completamente — 0 JavaScript */
@keyframes fade-up {
  from { opacity: 0; transform: translateY(30px); }
  to   { opacity: 1; transform: translateY(0); }
}

.reveal {
  animation: fade-up linear both;
  animation-timeline: view();
  animation-range: entry 0% entry 30%;
}

/* Barra de progreso de lectura */
#reading-progress {
  transform-origin: left;
  animation: scaleX linear;
  animation-timeline: scroll(root);
}
```
Beneficio: **Reemplaza AOS** — 0KB extra, corre en compositor thread (nunca jankea), reversible al scrollear hacia arriba.

**1.4 — CSS `@starting-style` (Baseline 2025)**
```css
/* Animaciones de entrada desde display:none — sin JS */
.modal {
  display: none;
  opacity: 0;
  transform: scale(0.95);
  transition: opacity 0.2s, transform 0.2s, display 0.2s allow-discrete;
}
.modal.open {
  display: block;
  opacity: 1;
  transform: scale(1);
}
@starting-style {
  .modal.open { opacity: 0; transform: scale(0.9); }
}
```
Beneficio: Modales y popovers con animaciones de entrada elegantes sin JS ni trucos de timing.

**1.5 — CSS Anchor Positioning + Popover API**
```html
<!-- Tooltips nativos SIN JavaScript ni librerías -->
<button popovertarget="tip-1">Ver info</button>
<div id="tip-1" popover anchor="btn-1" style="position-anchor: --btn">
  Tooltip con posicionamiento automático
</div>
```
```css
#tip-1 {
  position: fixed;
  position-anchor: --btn;
  top: anchor(bottom);
  left: anchor(center);
  translate: -50% 8px;
}
```
Beneficio: Elimina todas las librerías de tooltip/dropdown, el browser maneja z-index, focus trap y accesibilidad.

**1.6 — CSS Grid Masonry (Interop 2026)**
```css
/* Pinterest-style layout nativo */
.gallery {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  grid-template-rows: masonry; /* ← NUEVO en 2026 */
  gap: 16px;
}
```
Beneficio: Galería de fotos transformaciones de RISE sin JavaScript ni Masonry.js.

**1.7 — `text-wrap: balance` + `text-wrap: pretty`**
```css
h1, h2, h3 { text-wrap: balance; }     /* Títulos: líneas balanceadas */
p           { text-wrap: pretty; }      /* Párrafos: sin palabras huérfanas */
```
Beneficio: Títulos nunca se ven raros en mobile (fin de los headlines con 1 palabra sola).

**1.8 — Container Queries**
```css
.plan-card { container-type: inline-size; container-name: card; }

@container card (min-width: 280px) {
  .plan-card .price { font-size: 2.5rem; }
  .plan-card .features { columns: 2; }
}
```
Beneficio: Las cards de planes se adaptan a su contenedor, no al viewport — layouts imposibles antes.

**1.9 — CSS `if()` (Chrome 2026)**
```css
.btn {
  background: if(
    style(--variant: primary): var(--brand);
    style(--variant: ghost): transparent;
    else: var(--surface-2)
  );
}
```
Beneficio: Lógica condicional en CSS puro — elimina variantes de clases duplicadas.

**Archivos a modificar:** `css/wellcore-base.css`, `css/wellcore-v5.css`

---

## MÓDULO 2 — Dashboard RISE — Nivel App Premium
**Impacto:** `rise-dashboard.html` · **Riesgo:** Bajo · **Tiempo:** 5-7 días

### Herramientas Específicas

**2.1 — ApexCharts (CDN 400KB) — Gráficas de Progreso**
```html
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
```
```javascript
// Gráfica de peso con anotaciones de metas
const chart = new ApexCharts(document.querySelector('#weight-chart'), {
  chart: { type: 'area', height: 200, animations: { speed: 800 } },
  series: [{ name: 'Peso kg', data: clientData.measurements }],
  annotations: {
    yaxis: [{ y: clientData.goal, label: { text: 'Meta' } }]
  },
  stroke: { curve: 'smooth', width: 3 },
  colors: ['#E31E24'],
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0 } }
});
```
Métricas a graficar: peso, cintura, cadera, % grasa, músculo, asistencias, hidratación.

**2.2 — Heatmap de Adherencia (tipo GitHub contributions)**
```javascript
// Cal-Heatmap.js (17KB) — heatmap de días completados
import CalHeatmap from 'cal-heatmap';
const cal = new CalHeatmap();
cal.paint({
  data: { source: clientData.checkins },
  date: { start: new Date('2026-01-01') },
  range: 3, // 3 meses
  scale: { color: { range: ['#1a1a2e', '#E31E24'] } }
});
```

**2.3 — Contador de Streak + Gamificación**
```javascript
// CountUp.js (3KB) — números animados al cargar
import { CountUp } from 'countup.js';
new CountUp('streak-count', 0, clientData.streak, 0, 2, {
  suffix: ' días',
  useGrouping: true,
  onComplete: () => {
    if (clientData.streak >= 7) triggerConfetti(); // tsParticles
  }
});
```

**2.4 — tsParticles — Confetti en Logros**
```javascript
// tsParticles (cuando el cliente cumple una meta)
import { tsParticles } from 'https://cdn.jsdelivr.net/npm/@tsparticles/confetti@3.0.3/tsparticles.confetti.bundle.min.js';

function triggerConfetti() {
  tsParticles.confetti('tsparticles', {
    particleCount: 120,
    spread: 70,
    origin: { y: 0.6 },
    colors: ['#E31E24', '#FFD700', '#FFFFFF']
  });
}
// Se activa: primera semana completa, meta de peso alcanzada, etc.
```

**2.5 — Before/After Slider Nativo (CSS + JS mínimo)**
```html
<!-- Image comparison slider sin librerías -->
<div class="comparison-slider">
  <div class="before"><img src="foto-antes.jpg" alt="Antes"></div>
  <div class="after" style="clip-path: inset(0 var(--position) 0 0)">
    <img src="foto-despues.jpg" alt="Después">
  </div>
  <input type="range" min="0" max="100" value="50"
         oninput="this.parentNode.style.setProperty('--position', (100-this.value)+'%')">
</div>
```

**2.6 — Skeleton Screens (CSS puro)**
```css
/* Reemplaza spinners — UX premium en carga de datos */
.skeleton {
  background: linear-gradient(90deg,
    var(--surface-2) 25%,
    oklch(20% 0.02 240) 37%,
    var(--surface-2) 63%);
  background-size: 400% 100%;
  animation: shimmer 1.4s ease infinite;
  border-radius: 4px;
}
@keyframes shimmer {
  0%   { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}
```

**2.7 — Notificaciones Toast Nativas**
```javascript
// Sonner.js (2KB vanilla fork) o nativo con CSS @starting-style
function showToast(msg, type = 'success') {
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.textContent = msg;
  document.body.appendChild(t);
  requestAnimationFrame(() => t.classList.add('show'));
  setTimeout(() => t.remove(), 3500);
}
```

**Archivos a modificar:** `rise-dashboard.html`, `js/v5-effects.js`

---

## MÓDULO 3 — Animaciones y Experiencia Premium
**Impacto:** `index.html`, `rise.html`, todas las páginas · **Riesgo:** Medio · **Tiempo:** 4-5 días

### Herramientas Específicas

**3.1 — GSAP 3 + ScrollTrigger (reemplaza AOS)**
```html
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.7/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.7/dist/ScrollTrigger.min.js"></script>
```
```javascript
gsap.registerPlugin(ScrollTrigger);

// Stagger de cards — mejor que AOS
gsap.from('.feature-card', {
  opacity: 0, y: 60, stagger: 0.12, duration: 0.8, ease: 'power3.out',
  scrollTrigger: { trigger: '.features-grid', start: 'top 80%' }
});

// Hero text kinetic
gsap.from('.hero-title .char', {
  opacity: 0, rotateX: 90, stagger: 0.05, duration: 0.7, ease: 'back.out(1.7)'
});
```

**3.2 — Lenis Smooth Scroll (3KB)**
```html
<script src="https://cdn.jsdelivr.net/npm/lenis@1.1.14/dist/lenis.min.js"></script>
```
```javascript
const lenis = new Lenis({ lerp: 0.1, smoothWheel: true });

// Integración con GSAP ScrollTrigger
lenis.on('scroll', ScrollTrigger.update);
gsap.ticker.add((time) => lenis.raf(time * 1000));
gsap.ticker.lagSmoothing(0);
```
Beneficio: Scroll como butter — elimina el scroll brusco nativo. 3KB, 0 dependencias.

**3.3 — Botones Magnéticos (Vanilla JS, 0KB)**
```javascript
document.querySelectorAll('.btn-magnetic').forEach(btn => {
  btn.addEventListener('mousemove', e => {
    const rect = btn.getBoundingClientRect();
    const x = (e.clientX - rect.left - rect.width/2) * 0.35;
    const y = (e.clientY - rect.top - rect.height/2) * 0.35;
    btn.style.transform = `translate(${x}px, ${y}px)`;
  });
  btn.addEventListener('mouseleave', () => btn.style.transform = '');
});
```
Aplicar a: CTAs "Unirme al Reto", "Empezar RISE".

**3.4 — CSS View Transitions (Cross-document, Chrome 126+)**
```css
/* En wellcore-base.css */
@view-transition { navigation: auto; }

/* Personalizar la transición entre páginas */
::view-transition-old(root) {
  animation: 300ms ease-out fade-out;
}
::view-transition-new(root) {
  animation: 400ms ease-in slide-from-right;
}
```
Beneficio: Transición suave entre páginas (login → dashboard, planes → inscripción) sin Barba.js.

**3.5 — Cursor Personalizado Premium**
```javascript
// Solo en desktop — cursor personalizado con lag
const cursor = document.createElement('div');
cursor.className = 'custom-cursor';
document.body.appendChild(cursor);

document.addEventListener('mousemove', e => {
  cursor.animate([
    { left: cursor.getBoundingClientRect().left + 'px', top: cursor.getBoundingClientRect().top + 'px' },
    { left: e.clientX - 10 + 'px', top: e.clientY - 10 + 'px' }
  ], { duration: 150, fill: 'forwards', easing: 'ease-out' });
});
```

**3.6 — SVG Path Animations (draw-on-scroll)**
```css
/* Iconos que se "dibujan" al aparecer */
.icon-path {
  stroke-dasharray: 1000;
  stroke-dashoffset: 1000;
  animation: draw 1.5s ease forwards;
  animation-timeline: view();
  animation-range: entry 0% entry 60%;
}
@keyframes draw {
  to { stroke-dashoffset: 0; }
}
```
Aplicar a: Iconos del proceso de 4 pasos en `rise.html`, iconos de features.

**Archivos a modificar:** `index.html`, `rise.html`, `js/v5-effects.js`, `css/wellcore-v5.css`

---

## MÓDULO 4 — Performance + Core Web Vitals
**Impacto:** Todo el sitio · SEO directo · **Riesgo:** Ninguno · **Tiempo:** 2-3 días

### Herramientas Específicas

**4.1 — Optimización de Imágenes**
```html
<!-- ANTES -->
<img src="images/fotos/hero-01.jpg">

<!-- DESPUÉS -->
<picture>
  <source srcset="images/fotos/hero-01.avif" type="image/avif">
  <source srcset="images/fotos/hero-01.webp" type="image/webp">
  <img src="images/fotos/hero-01.jpg"
       loading="lazy"
       decoding="async"
       fetchpriority="high" <!-- Solo para LCP image -->
       width="1200" height="800"
       alt="WellCore Fitness - Transformación">
</picture>
```
Impacto: LCP -40%, reduce bandwidth 50-60%.

**4.2 — `content-visibility: auto`**
```css
/* Secciones largas que están off-screen */
.coaches-section,
.blog-section,
.faq-section {
  content-visibility: auto;
  contain-intrinsic-size: auto 500px; /* Reservar espacio */
}
```
Impacto: Render inicial hasta 3x más rápido en páginas largas.

**4.3 — CSS Containment**
```css
/* Cada card es un universo aislado para el renderer */
.plan-card,
.coach-card,
.blog-card {
  contain: layout style paint;
}
```
Impacto: Cambios en una card no re-calculan layout del resto.

**4.4 — Defer JS Inteligente**
```html
<!-- ANTES (bloquea render) -->
<script src="js/v5-effects.js"></script>

<!-- DESPUÉS (no bloquea) -->
<script src="js/v5-effects.js" type="module" defer></script>
```

**4.5 — Intersection Observer Nativo (reemplaza scroll events)**
```javascript
// ANTES: scroll event pesado
window.addEventListener('scroll', checkVisible); // Malo

// DESPUÉS: IntersectionObserver — 0 costo en main thread
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => e.target.classList.toggle('visible', e.isIntersecting));
}, { threshold: 0.15 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
```

**4.6 — `will-change` Selectivo**
```css
/* Solo en elementos que van a animar */
.hero-particle,
.gsap-target,
.scroll-indicator {
  will-change: transform, opacity;
}
/* Remover después de la animación via JS */
el.addEventListener('animationend', () => el.style.willChange = 'auto');
```

**4.7 — Critical CSS Inline**
```html
<!-- CSS crítico inline en <head> — fonts, nav, hero -->
<style>/* critical path CSS here */</style>

<!-- El resto cargado async -->
<link rel="preload" href="css/wellcore-base.css" as="style"
      onload="this.onload=null;this.rel='stylesheet'">
```

**Métricas objetivo:**
| Métrica | Actual estimado | Objetivo |
|---------|----------------|----------|
| LCP | ~4s | <2.5s |
| INP | ~400ms | <200ms |
| CLS | >0.1 | <0.05 |
| Lighthouse | ~65 | 90+ |

---

## MÓDULO 5 — Gamificación + Retención RISE
**Impacto:** `rise-dashboard.html`, cliente engagement · **Tiempo:** 4-5 días

### Sistema de Gamificación

**5.1 — Sistema de Puntos + Badges**
```javascript
const BADGES = {
  'primera-semana':  { icon: '🔥', label: 'Primera Semana', xp: 100 },
  'mes-completo':    { icon: '💎', label: 'Mes Completo',   xp: 500 },
  'meta-peso':       { icon: '⚡', label: 'Meta Alcanzada', xp: 300 },
  'racha-10':        { icon: '🏆', label: 'Racha de 10',    xp: 250 },
  'hydration-week':  { icon: '💧', label: 'Semana Hidratado', xp: 150 },
};

function checkBadges(clientData) {
  const earned = [];
  if (clientData.streak >= 7) earned.push('primera-semana');
  if (clientData.streak >= 30) earned.push('mes-completo');
  // ... mostrar con animación + confetti
  return earned;
}
```

**5.2 — Progress Ring Animado**
```javascript
// SVG circle progress — peso, cintura, % grasa
function animateRing(selector, percent, color = '#E31E24') {
  const ring = document.querySelector(selector);
  const circumference = 2 * Math.PI * 45; // radio 45
  ring.style.strokeDasharray = circumference;
  ring.style.strokeDashoffset = circumference;

  gsap.to(ring, {
    strokeDashoffset: circumference * (1 - percent / 100),
    duration: 1.5, ease: 'power2.out', delay: 0.3
  });
}
```

**5.3 — Check-in Weekly Interactivo**
```html
<!-- Los días de la semana como botones toggle -->
<div class="week-tracker">
  <button class="day completed" aria-label="Lunes completado">L</button>
  <button class="day completed" aria-label="Martes completado">M</button>
  <button class="day active"    aria-label="Hoy">X</button>
  <button class="day pending"   aria-label="Jueves pendiente">J</button>
  <!-- ... -->
</div>
```

**5.4 — Nivel / XP Bar**
```html
<div class="xp-bar">
  <div class="xp-fill" style="--xp-percent: 73%"></div>
  <span class="xp-label">Nivel 4 · 730/1000 XP</span>
</div>
```
```css
.xp-fill {
  width: calc(var(--xp-percent));
  background: linear-gradient(90deg, var(--brand), #ff6b6b);
  animation: xp-fill-in 1s ease-out forwards;
  animation-timeline: view();
}
```

**5.5 — Leaderboard RISE (comunidad)**
```javascript
// Top 10 del reto — ranking por adherencia + progreso
async function loadLeaderboard() {
  const data = await api.get('/api/rise/leaderboard');
  // Renderizar tabla con posición, nombre, streak, progreso
  // El propio usuario resaltado con borde rojo
}
```
Requiere nuevo endpoint: `GET /api/rise/leaderboard.php`

---

## MÓDULO 6 — Landing + Conversión
**Impacto:** `index.html`, `rise.html`, `planes.html` · **Tiempo:** 3-4 días

### Herramientas de Conversión

**6.1 — Video Hero (reemplaza imagen estática)**
```html
<!-- Video background muted autoplay — +34% conversión -->
<video autoplay muted loop playsinline
       poster="images/fotos/hero-01.webp"
       class="hero-video">
  <source src="video/hero-wellcore.webm" type="video/webm">
  <source src="video/hero-wellcore.mp4" type="video/mp4">
</video>
```

**6.2 — Social Proof Ticker**
```javascript
const PROOFS = [
  '47 personas completaron el Reto RISE este mes',
  'Carlos P. perdió 8kg en 30 días',
  'Sofia M. redujo 6cm de cintura',
  '94% de adherencia promedio en RISE 2026',
];
// Rotación automática cada 4s con CSS @starting-style
```

**6.3 — CTA Sticky con Countdown**
```html
<!-- Barra sticky superior para RISE con urgencia -->
<div class="promo-bar" id="promo-bar">
  <span>El Reto RISE cierra el <strong id="countdown">5 días</strong></span>
  <a href="/rise-enroll.html" class="promo-cta btn-magnetic">Únete Ahora →</a>
  <button class="promo-close" onclick="this.parentNode.remove()">×</button>
</div>
```

**6.4 — Exit Intent Detection**
```javascript
// Detecta cuando el mouse sale del viewport (intención de cerrar)
document.addEventListener('mouseleave', e => {
  if (e.clientY < 0 && !sessionStorage.exitShown) {
    showModal('exit-intent-modal');
    sessionStorage.exitShown = '1';
  }
});
```

**6.5 — Formulario de Inscripción Multi-Step Mejorado**
```javascript
// Stepper visual con validación por paso
// Paso 1: Datos básicos → Paso 2: Objetivos → Paso 3: Pago
// Progress bar animada entre pasos con View Transitions
```

**6.6 — Scroll Progress Reading Bar**
```css
/* rise.html — barra de progreso de lectura */
@property --scroll-position { syntax: '<length-percentage>'; initial-value: 0%; inherits: false; }

#progress-bar {
  position: fixed; top: 0; left: 0; height: 3px;
  background: var(--brand);
  transform-origin: left;
  transform: scaleX(var(--scroll-x, 0));
  animation: linear scroll-progress;
  animation-timeline: scroll(root);
}
@keyframes scroll-progress {
  to { transform: scaleX(1); }
}
```

---

## ROADMAP DE IMPLEMENTACIÓN

```
SEMANA 1 (Días 1-5)    → Módulo 1: CSS 2026 Base
                           - @layer, OKLCH tokens (wellcore-base.css)
                           - Scroll-Driven Animations (reemplaza AOS)
                           - text-wrap: balance en todos los títulos
                           - @starting-style en modales existentes
                           - Commit por cada subtarea

SEMANA 2 (Días 6-10)   → Módulo 4: Performance
                           - Convertir imágenes a WebP/AVIF
                           - content-visibility en secciones largas
                           - fetchpriority="high" en LCP images
                           - Defer JS + Critical CSS
                           - Lighthouse antes/después

SEMANA 3 (Días 11-15)  → Módulo 2: RISE Dashboard
                           - ApexCharts para mediciones
                           - Skeleton screens en carga
                           - Confetti en logros (tsParticles)
                           - Before/After slider fotos
                           - Toast notifications

SEMANA 4 (Días 16-20)  → Módulo 5: Gamificación
                           - Sistema de badges
                           - Heatmap de adherencia (Cal-Heatmap)
                           - Progress rings animados
                           - XP bar + streak counter con CountUp.js

SEMANA 5 (Días 21-25)  → Módulo 3: Animaciones Premium
                           - GSAP + ScrollTrigger (reemplaza AOS)
                           - Lenis smooth scroll
                           - Botones magnéticos en CTAs
                           - CSS View Transitions entre páginas
                           - SVG draw-on-scroll en proceso

SEMANA 6 (Días 26-30)  → Módulo 6: Conversión
                           - Video hero
                           - Social proof ticker
                           - CTA sticky + countdown
                           - Exit intent popup
```

---

## LIBRERÍAS — Resumen de lo Nuevo a Integrar

| Librería | Tamaño | Propósito | CDN |
|---------|--------|-----------|-----|
| **GSAP 3.12** | 23KB | Animaciones premium (reemplaza AOS parcialmente) | jsDelivr |
| **ScrollTrigger** | +8KB | Scroll animations con GSAP | jsDelivr |
| **Lenis 1.1** | 3KB | Smooth scroll (butter effect) | jsDelivr |
| **ApexCharts** | 400KB | Gráficas de progreso fitness | jsDelivr |
| **CountUp.js** | 4KB | Números animados (streak, KPIs) | jsDelivr |
| **tsParticles confetti** | 15KB | Confetti en logros | jsDelivr |
| **Cal-Heatmap** | 17KB | Heatmap de adherencia | jsDelivr |
| **0 librerías** | 0KB | CSS: @layer, OKLCH, Scroll-Driven, @starting-style, anchor positioning, masonry, if(), container queries | Nativas |

**Total nuevo JS:** ~470KB (solo carga en páginas que lo necesitan)
**Total nuevo CSS:** 0KB extra (todo nativo)

---

## REFERENCIAS DE INVESTIGACIÓN

- [Interop 2026 — web.dev](https://web.dev/blog/interop-2026)
- [CSS 2026 Features — Riad Kilani](https://blog.riadkilani.com/2026-css-features-you-must-know/)
- [GSAP vs Motion 2026 — Satish Kumar](https://satishkumar.xyz/blogs/gsap-vs-motion-guide-2026)
- [CSS Anchor Positioning — Chrome Developers](https://developer.chrome.com/blog/anchor-positioning-api)
- [Popover API — Smashing Magazine](https://www.smashingmagazine.com/2026/03/getting-started-popover-api/)
- [ApexCharts vs Chart.js — Luzmo](https://www.luzmo.com/blog/javascript-chart-libraries)
- [Core Web Vitals 2025 — Digital Applied](https://www.digitalapplied.com/blog/core-web-vitals-optimization-guide-2025)
- [Fitness UX Micro-interactions — Primotech](https://primotech.com/ui-ux-evolution-2026-why-micro-interactions-and-motion-matter-more-than-ever/)
- [CSS Grid Masonry 2026 — Webically](https://webically.com/css-developments-2026/)
- [Design Tokens W3C Spec](https://www.w3.org/community/design-tokens/2025/10/28/design-tokens-specification-reaches-first-stable-version/)
