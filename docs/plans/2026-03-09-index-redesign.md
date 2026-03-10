# WellCore Index Redesign — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Rediseñar el index.html de WellCore con hero split A+B cinematográfico, mockups CSS ultra-premium del dashboard del cliente, y arreglar el bug de espacios vacíos.

**Architecture:** Edición quirúrgica del index.html (4120 líneas) — se modifica CSS del hero, se eliminan duplicados, se agrega nueva sección "La Plataforma" con mockups CSS del dashboard real, y se ajusta el hero split con browser mockup.

**Tech Stack:** HTML/CSS/JS puro, JetBrains Mono, Bebas Neue, Axiforma, Font Awesome 6.4, AOS

---

## FIXES CRÍTICOS PRIMERO

### Task 1: Eliminar preloaders y barras duplicadas

**Files:**
- Modify: `index.html:2527-2542` (remover v6-promo-bar + v5-preloader)

**Problema:** Hay DOS preloaders (`v5-preloader` + `page-loader`) y DOS barras RISE (`v6-promo-bar` + `rise-bar`). Los preloaders compiten por el DOM y los elementos del hero quedan con `opacity:0`.

**Step 1:** Remover el bloque del v5-preloader (líneas 2539-2542):
```html
<!-- ELIMINAR ESTO -->
<div id="v5-preloader" class="v5-preloader">
  <div class="v5-preloader-logo">WELL<span>CORE</span></div>
  <div class="v5-preloader-bar"><div class="v5-preloader-fill"></div></div>
</div>
```

**Step 2:** Remover el bloque del v6-promo-bar (líneas 2531-2537):
```html
<!-- ELIMINAR ESTO -->
<div class="v6-promo-bar" id="v6PromoBar">
  ...
</div>
```

**Step 3:** Arreglar el CSS del hero — cambiar `padding-top: 72px` a `padding-top: 112px` para compensar barra RISE (40px) + navbar (72px).

---

### Task 2: Fix animaciones del hero — elementos con opacity:0

**Problem:** `.hero-meta`, `.hero-badge`, `.hero-headline`, `.hero-headline-2`, `.hero-subtitle`, `.hero-rule`, `.hero-stats`, `.hero-ctas`, `.hero-prices` todos tienen `opacity: 0` como default + animaciones CSS con delays. Si el browser tiene `prefers-reduced-motion` o hay un error JS, quedan invisibles.

**Fix:** Agregar `animation-fill-mode: forwards` y hacer que los elementos tengan un fallback visible si la animación no corre.

---

## REDISEÑO HERO

### Task 3: Nuevo Hero Split A+B

**Cambios CSS del `#hero`:**
```css
#hero {
  min-height: 100vh;
  background: var(--bg);
  display: grid;
  grid-template-columns: 1fr 1fr;
  align-items: center;
  padding-top: 112px; /* navbar 72px + rise-bar 40px */
  position: relative;
  overflow: hidden;
}
```

**Nueva estructura HTML del hero (reemplaza .hero-inner actual):**
- `.hero-left`: texto, badge, headline, subtitle, stats, CTAs, prices
- `.hero-right`: browser mockup CSS del dashboard

**Browser Mockup CSS:**
```
┌─────────────────────────────┐
│ ● ● ●   wellcorefitness.com │  ← barra del browser
├─────────────────────────────┤
│ WELLCORE  [sidebar]         │
│ ─────     Dashboard >       │
│           Semanas: 8wk      │
│  [ring]   Adherencia: 94%   │
│   74%     Plan: ÉLITE       │
│           [KPI cards]       │
│           [progress bar]    │
└─────────────────────────────┘
```

El mockup tiene:
- Header: dots (●●●) + URL bar "app.wellcorefitness.com"
- Body: sidebar dark + main content con KPI cards y progress ring
- Glow rojo en el bottom
- `box-shadow: 0 40px 120px rgba(227,30,36,0.25)`
- Animación: `translateY(20px) → translateY(0)` con delay 0.8s

---

## NUEVA SECCIÓN: LA PLATAFORMA

### Task 4: Sección "La Plataforma" — 4 Feature Tabs

**Posición:** Después del hero, antes de RISE section

**Layout:**
```
[LABEL: LA PLATAFORMA]
[H2: Todo lo que necesitas]
[Subtext]

[Tab 1: Dashboard] [Tab 2: Check-ins] [Tab 3: Entrenamiento] [Tab 4: XP & Logros]

[Mockup activo del tab seleccionado — full width]
```

**Mockup Tab 1 — Dashboard:**
- Grid de 4 KPI cards (Plan Activo, Semanas, Adherencia, Próxima Entrega)
- Progress ring SVG al 74%
- Timeline de fases (F01 ✓, F02 ✓, F03 ●, F04 ○)
- Mini activity feed

**Mockup Tab 2 — Check-ins:**
- Form con campos: Peso, Energía, Sueño, Fotos
- Historial de check-ins anteriores con scores

**Mockup Tab 3 — Plan de Entrenamiento:**
- Lista de ejercicios con checkboxes
- Progress bar "semana actual 3/4 sesiones"
- Detalle de un ejercicio (series, reps, peso)

**Mockup Tab 4 — XP & Logros:**
- XP bar con nivel actual
- Grid de badges/logros (algunos unlocked, algunos locked)
- Leaderboard top 3

---

## OPTIMIZACIONES

### Task 5: Sección RISE → Banner compacto horizontal
La sección #rise actual ocupa demasiado espacio. Convertir en un banner horizontal llamativo entre "La Plataforma" y "Trust".

### Task 6: Sección de Features/Stats antes del fold
Agregar una barra de stats/features entre hero y plataforma:
- `+120 clientes activos` | `94% adherencia` | `3 planes` | `Desde $399k COP`

---

## SECUENCIA DE IMPLEMENTACIÓN

1. Fix bug (Tasks 1-2) → verificar en browser
2. Nuevo hero CSS + HTML (Task 3)
3. Nueva sección Plataforma con tabs (Task 4)
4. Optimizaciones finales (Tasks 5-6)
5. Commit
