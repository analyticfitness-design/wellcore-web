# WellCore v7 — MEGA UPGRADE: Ultra Premium Platform

**Fecha:** 2026-03-08
**Estado:** DISEÑO APROBADO → IMPLEMENTACIÓN
**Base de diseño:** rise.html (bento grid, particles, kinetic typography, glassmorphism)
**Tesis visual:** WELLCORE_TENDENCIAS_2026.html (200 herramientas, 22 categorías)
**Objetivo:** Transformar WellCore en la referencia de diseño fitness digital LATAM 2027

---

## 1. SISTEMA TIPOGRÁFICO v7

### Fuentes Nuevas

| Rol | Fuente | Peso | CDN | CSS Variable |
|-----|--------|------|-----|--------------|
| Body/UI | Axiforma | 300,400,500,600,700 | cdnfonts.com | `--v7-font-body` |
| Display/Headings | Bebas Neue Pro | Bold | cdnfonts.com + Google | `--v7-font-display` |
| Data/Stats/Numbers | DIN Next LT Pro | Bold | cdnfonts.com | `--v7-font-data` |
| Impact Statements | Impact | System | System font | `--v7-font-impact` |
| Mono/Technical | Monoblock | Regular | cdnfonts.com / fallback | `--v7-font-mono` |

### Fuentes Retiradas
- ~~Inter~~ → Axiforma
- ~~JetBrains Mono~~ → Monoblock (mono), DIN Next LT Pro (data)
- ~~Urbanist~~ → Axiforma
- ~~Anton~~ → DIN Next LT Pro

### Escala Tipográfica Fluida (clamp)

```css
--v7-text-hero:    clamp(3rem, 8vw, 6rem);
--v7-text-display: clamp(2rem, 5vw, 4rem);
--v7-text-h1:      clamp(1.8rem, 4vw, 3rem);
--v7-text-h2:      clamp(1.5rem, 3vw, 2.2rem);
--v7-text-h3:      clamp(1.2rem, 2vw, 1.5rem);
--v7-text-body:    clamp(0.9rem, 1.1vw, 1.05rem);
--v7-text-small:   clamp(0.75rem, 0.9vw, 0.85rem);
--v7-text-micro:   clamp(0.65rem, 0.7vw, 0.72rem);
```

---

## 2. DESIGN TOKENS v7 (OKLCH)

### Colores

```css
--wc-red:       oklch(52% 0.24 24);      /* Brand red */
--wc-red-light: oklch(62% 0.20 24);      /* Hover red */
--wc-red-dark:  oklch(42% 0.24 24);      /* Active red */
--wc-red-dim:   oklch(52% 0.24 24 / 10%);/* Background red */
--wc-red-glow:  oklch(52% 0.24 24 / 40%);/* Glow red */
--wc-cyan:      oklch(78% 0.15 220);     /* Accent cyan */
--wc-green:     oklch(68% 0.18 145);     /* Success */
--wc-gold:      oklch(75% 0.15 85);      /* Elite/Gold */
--wc-black:     oklch(5% 0 0);           /* Background */
--wc-surface-0: oklch(8% 0.005 24);      /* Card bg */
--wc-surface-1: oklch(11% 0.005 24);     /* Elevated */
--wc-surface-2: oklch(14% 0.005 24);     /* Higher */
--wc-surface-3: oklch(17% 0.005 24);     /* Highest */
--wc-border:    oklch(100% 0 0 / 6%);    /* Borders */
--wc-text:      oklch(98% 0 0);          /* Primary text */
--wc-text-dim:  oklch(100% 0 0 / 45%);   /* Secondary text */
--wc-text-muted:oklch(100% 0 0 / 18%);   /* Tertiary text */
```

### Spacing

```css
--v7-space-2xs: 4px;
--v7-space-xs:  8px;
--v7-space-sm:  12px;
--v7-space-md:  16px;
--v7-space-lg:  24px;
--v7-space-xl:  32px;
--v7-space-2xl: 48px;
--v7-space-3xl: 64px;
--v7-space-4xl: 96px;
```

### Radius

```css
--v7-radius-sm:   6px;
--v7-radius-md:   12px;
--v7-radius-lg:   20px;
--v7-radius-xl:   28px;
--v7-radius-pill:  100px;
--v7-radius-full: 9999px;
```

### Easing

```css
--v7-ease-out:    cubic-bezier(0.2, 0, 0, 1);
--v7-ease-spring: cubic-bezier(0.16, 1, 0.3, 1);
--v7-ease-bounce: cubic-bezier(0.34, 1.56, 0.64, 1);
--v7-duration-fast:   150ms;
--v7-duration-normal: 300ms;
--v7-duration-slow:   500ms;
```

---

## 3. COMPONENTES GLOBALES v7

### 3.1 Navbar
**Base:** rise.html nav pattern
- Glassmorphism: `backdrop-filter: blur(20px) saturate(180%)`
- Axiforma 500 para links, DIN Next para micro-labels
- Logo: Bebas Neue Pro Bold
- Animated underline hover (scale-x transform)
- Shrink on scroll (padding reduce, bg blur increase)
- Mobile: Full-screen overlay con animación @starting-style
- CTA "Empezar" con shimmer effect

### 3.2 Footer
- 4-column grid (Axiforma body, DIN Next micro-labels)
- Red glow separator line arriba
- Social icons con hover translateY + glow
- Newsletter input con glassmorphism

### 3.3 Buttons (5 variantes)

| Variante | Clase | Uso |
|----------|-------|-----|
| Primary | `.v7-btn-primary` | CTAs principales (red bg, shimmer hover) |
| Secondary | `.v7-btn-secondary` | Acciones secundarias (border only, fill hover) |
| Ghost | `.v7-btn-ghost` | Links estilo botón (transparent, underline hover) |
| Glass | `.v7-btn-glass` | Sobre fondos con imagen (glassmorphism) |
| Glow | `.v7-btn-glow` | Impacto máximo (red glow pulse + shimmer) |

Todos con: Axiforma 600, 11px, letter-spacing: 2px, uppercase, magnetic effect (GSAP)

### 3.4 Cards (4 variantes)

| Variante | Clase | Uso |
|----------|-------|-----|
| Glass | `.v7-card-glass` | Glassmorphism dark (blur 24px, border 6% white) |
| Solid | `.v7-card-solid` | Surface background (surface-1 bg, border) |
| Gradient | `.v7-card-gradient` | Gradient mesh bg (red/cyan radial gradients) |
| Bento | `.v7-card-bento` | Bento grid items (minimal, hover lift+glow) |

Todos con: mouse-tracking glow (radial-gradient follows cursor), hover translateY(-6px)

### 3.5 Forms
- Float labels (label sube al focus)
- Glassmorphism inputs (surface-0 bg, border 6%)
- Focus: red bottom border glow
- Validation: green check / red X animated
- Axiforma 400 para inputs, DIN Next para labels

### 3.6 Badges/Pills
- `.v7-badge` — small rounded pill (DIN Next, 9px, uppercase)
- `.v7-pill` — larger pill con icono (Axiforma, glassmorphism bg)
- Variants: red, cyan, green, gold, gray

### 3.7 Section Headers (patrón rise.html)
- Eyebrow: DIN Next, 10px, letter-spacing 3px, red, uppercase
- Title: Bebas Neue Pro Bold, fluid size, kinetic split-text animation
- Subtitle: Axiforma 300, gray, max-width 560px
- Red accent line before eyebrow

### 3.8 Modals/Dialogs
- Native `<dialog>` con Popover API
- @starting-style para entrada suave
- Glassmorphism background
- Close button: X con hover rotate(90deg)

---

## 4. EFECTOS VISUALES v7 (de rise.html)

### 4.1 Canvas 2D Particles
- Partículas rojas flotando en heroes
- Responsive (reduce count en mobile)
- requestAnimationFrame + IntersectionObserver (pause cuando no visible)

### 4.2 Gradient Mesh Backgrounds
- ::before con múltiples radial-gradient
- Red (20% 30%), cyan (80% 70%), red (50% 0%)
- Sutil, no agresivo

### 4.3 Kinetic Typography (SplitText)
- Títulos se animan letra por letra al entrar en viewport
- stagger: 40ms por caracter
- ease: cubic-bezier(0.16,1,0.3,1)
- Gradient text en línea 2 (red → gold → red, animado)

### 4.4 Floating Geo Shapes
- Círculos y cuadrados con border transparente
- Animación rotate + float infinita
- Different speeds (20s, 25s, 30s)

### 4.5 Scroll-Driven Animations
- Bento items: fade+scale al entrar en viewport
- Cards: translateY(60px) → 0 con view()
- Fallback: IntersectionObserver para Firefox/Safari

### 4.6 Parallax Grid
- Grid de líneas finas con perspective 3D
- mask-image radial para fade en bordes
- Sutil background texture

### 4.7 Mouse-Tracking Glow
- Cards emiten glow radial que sigue el cursor
- CSS: `--mx` / `--my` custom properties via JS mousemove
- radial-gradient at (var(--mx) var(--my)) en ::before

### 4.8 Bento Grid Layout
- 12-column grid con items de diferentes spans
- bento-hero (8 col, 2 row), bento-phone (4 col), bento-stat (3 col)
- Responsive: 2 cols en tablet, 1 col en mobile

### 4.9 Custom Cursor (desktop only)
- Red dot (8px) + circle (40px) que sigue el mouse
- Scale up en hover de links/buttons
- pointer: fine media query

### 4.10 Lenis Smooth Scroll
- CDN 3KB
- Smooth easing: 0.1 duration
- Pause en modales abiertos

---

## 5. PLAN DE IMPLEMENTACIÓN POR FASES

### FASE 0: Foundation (Sesión 1)
**Entregables:**
1. `css/wellcore-v7.css` — Nuevo archivo CSS con:
   - @import de fuentes (Axiforma, DIN Next, Monoblock via cdnfonts)
   - Google Fonts update (Bebas Neue)
   - Design tokens completos (OKLCH colors, spacing, radius, easing)
   - Sistema tipográfico fluido
   - 5 variantes de botón
   - 4 variantes de card
   - Sistema de forms
   - Badges/pills
   - Section headers pattern
   - Navbar v7
   - Footer v7
   - Scroll-driven animations
   - Glassmorphism utilities
   - Mouse-tracking glow
   - Custom cursor
   - Media queries responsive
   - prefers-reduced-motion

2. `js/wellcore-v7.js` — Nuevo archivo JS con:
   - Magnetic buttons (GSAP)
   - Kinetic typography (SplitText chars)
   - Canvas 2D particles
   - Mouse-tracking glow handler
   - Custom cursor
   - CountUp animations
   - Scroll reveals (IO fallback)
   - Lenis init
   - Toast system
   - Confetti system

3. Update Google Fonts URL en TODAS las páginas (bulk replace)

**Estimado:** ~1200 líneas CSS, ~400 líneas JS

### FASE 1: Páginas Públicas Core (Sesión 2)
**Páginas:** index.html, metodo.html, nosotros.html, proceso.html
**Cambios por página:**
- Reemplazar `<style>` inline por v7 classes
- Navbar v7
- Footer v7
- Hero sections con particles + kinetic text
- Bento grids para features
- Scroll-driven card reveals
- Gradient mesh backgrounds
- Botones v7 en todas las CTAs
- Responsive audit

### FASE 2: Páginas de Conversión (Sesión 3)
**Páginas:** planes.html, rise.html, inscripcion.html, coaches.html, faq.html, tienda.html
**Cambios por página:**
- Pricing cards con glassmorphism + animated borders
- Rise landing ya tiene base → upgrade fonts + effects
- Coach cards con bento layout
- FAQ con animated accordion
- Tienda con bento product grid
- CTAs con glow buttons

### FASE 3: Flujo RISE (Sesión 4)
**Páginas:** rise-enroll.html, rise-intake.html, rise-payment.html
**Cambios:**
- Forms v7 con float labels
- Progress stepper animado (@property)
- Glassmorphism containers
- Wompi integration check (no romper payment flow)
- Security badges v7
- Validation animations

### FASE 4: Dashboards (Sesión 5-6)
**Páginas:** login.html, cliente.html, rise-dashboard.html, coach-portal.html, admin.html
**Cambios:**
- Login: premium split design con particles + v7 forms
- Cliente dashboard:
  - SVG progress rings (OKLCH gradient)
  - Activity heatmap (GitHub-style 52 semanas)
  - Bento KPI cards
  - Tab system v7 con shimmer active state
  - Weight chart mejorado
  - Check-in form v7
  - Photo comparison slider v7
- RISE dashboard:
  - Countdown ring mejorado
  - Streak badges animados
  - Habit tracker matrix
  - XP bar con GSAP animation
- Coach portal: mismos patrones
- Admin dashboard:
  - KPI cards con sparklines SVG
  - Data tables v7
  - Chart improvements

### FASE 5: Blog + Legal + Utility (Sesión 7)
**Páginas:** 10 blog articles, 3 legal, 404, pago-confirmado, invoice
**Cambios:**
- Blog article template v7 (tipografía, spacing, code blocks)
- Legal pages v7 (clean, readable)
- 404 page con particles
- Pago confirmado con confetti + success animation
- Invoice template limpio

### FASE 6: Performance + Polish (Sesión 8)
**Optimizaciones:**
- Core Web Vitals audit (LCP < 2.5s, CLS < 0.1, INP < 200ms)
- Font subsetting (latin only, reduce payload)
- AVIF images donde posible
- Speculation Rules API (prerender RISE funnel)
- View Transitions API between pages
- content-visibility: auto en secciones below-fold
- fetchpriority="high" en hero images
- Accessibility: prefers-reduced-motion, focus-visible, aria labels

---

## 6. ARCHIVOS NUEVOS vs MODIFICADOS

### Nuevos
- `css/wellcore-v7.css` — ~1200 líneas
- `js/wellcore-v7.js` — ~400 líneas

### Modificados (todas las fases)
- **56 HTML files** — Update font links, add v7 CSS/JS, replace components
- `css/wellcore-v6.css` — Minor adjustments (no breaking changes)
- `css/wellcore-v5.css` — Keep intact (backward compat)
- `css/wellcore-base.css` — Keep intact (backward compat)

### Retirados
- `css/wellcore-premium.css` — Deprecated, replaced by v7
- `js/wellcore-v5.js` — Deprecated, replaced by v7

---

## 7. REGLAS DE ORO

1. **NUNCA tocar /api/ ni la base de datos** — Solo frontend
2. **NUNCA eliminar clases CSS/JS existentes** — Solo agregar v7 layer
3. **Wompi DEBE seguir funcionando** — Test payment flow after cada fase
4. **Dashboards DEBEN seguir funcionando** — Test auth + data after cada fase
5. **Mobile-first** — Cada cambio testeado en 375px-1440px
6. **Performance budget** — No agregar >50KB de CSS/JS nuevos
7. **Primero local → luego producción** — Deploy solo cuando la fase esté completa
8. **Git commit por fase** — Un commit limpio por fase completada

---

## 8. MIGRACIÓN FUTURA A LARAVEL

El diseño v7 está pensado para facilitar la migración:
- **CSS modular** — wellcore-v7.css es standalone, portable a Blade templates
- **JS vanilla** — wellcore-v7.js no depende de framework, funciona en cualquier entorno
- **Design tokens** — CSS custom properties se mapean 1:1 a Tailwind v4 @theme
- **Component classes** — .v7-btn-*, .v7-card-*, .v7-badge-* son reutilizables
- **Sin coupling** — Frontend completamente separado del backend

Cuando migres a Laravel:
1. Copiar css/wellcore-v7.css → resources/css/
2. Copiar js/wellcore-v7.js → resources/js/
3. Las clases CSS funcionan idénticamente en Blade templates
4. Opcionalmente migrar a Tailwind v4 usando los mismos tokens

---

## 9. PREVIEW DE RESULTADO ESPERADO

### Antes (v6)
- Inter + Bebas Neue + JetBrains Mono
- Cards con borders simples
- Botones planos con hover básico
- Scroll básico (algunos scroll-driven)
- Sin particles ni gradient mesh
- Glassmorphism solo en login y pocos elementos

### Después (v7)
- Axiforma + Bebas Neue Pro + DIN Next + Impact + Monoblock
- Cards con glassmorphism + mouse-tracking glow
- 5 variantes de botón con shimmer, magnetic, glow
- Kinetic typography en todos los heroes
- Canvas particles en heroes
- Gradient mesh backgrounds en secciones
- Bento grid layouts
- Floating geometric shapes
- Custom cursor premium
- Scroll-driven animations en todo
- Progress rings, heatmaps, sparklines en dashboards
- Animated accordion, stepper, badges
- View Transitions entre páginas
