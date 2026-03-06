# Preview Bloqueado + Nutricion/Habitos Interactivos + Tabs Elite — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Expand "Mi Plan" from 3 to 6 interactive tabs with rich locked previews for gated content.

**Architecture:** All changes in `cliente.html`. Each tab follows the TRAINING_DATA pattern: hardcoded demo data object, render function, optional API override. Locked tabs show static demo HTML behind a semi-transparent blur overlay. No new API endpoints needed — all client-side.

**Tech Stack:** HTML/CSS/JS inline in cliente.html, Claude Haiku for coach tips (existing endpoint).

**Security note:** All demo content is hardcoded static data (no user input). DOM construction uses createElement + textContent for text, and safe DOM methods. No unsanitized user content is rendered.

---

## Task 1: Update CSS — Locked Overlay + New Tab Styles

**Files:**
- Modify: `cliente.html` CSS section (~lines 413-434)

**Step 1: Update .locked-overlay CSS**

Find the existing `.locked-overlay` block (~line 413) and replace with updated styles:
- Change background from rgba(10,10,10,.92) to rgba(10,10,10,0.65)
- Keep backdrop-filter: blur(3px)
- Add z-index:5, padding:24px
- Add .lock-icon, .lock-features styles
- .lock-features li uses ::before with checkmark + var(--red)

**Step 2: Add tab-nav scrollable styles for 6 tabs**

After the .tab-btn styles, add overflow-x:auto, scrollbar-width:none for horizontal scroll on mobile.

**Step 3: Commit**

```bash
git add cliente.html
git commit -m "style: updated locked overlay (blur reduced) + scrollable tab nav"
```

---

## Task 2: Add 3 New Tab Buttons + Tab Content Containers

**Files:**
- Modify: `cliente.html` HTML tab-nav section (~lines 1523-1533)
- Modify: `cliente.html` after tab-content-habitos (~lines 1603-1616)

**Step 1: Add 3 new tab buttons (Suplementacion, Ciclo Hormonal, Bloodwork) after Habitos button**

**Step 2: Add 3 new empty tab-content containers**

**Step 3: Commit**

```bash
git add cliente.html
git commit -m "feat: add 3 new tab buttons and containers (suplementacion, ciclo, bloodwork)"
```

---

## Task 3: Update loadPlanTab() — New tabLevel + Demo Content Rendering

**Files:**
- Modify: `cliente.html` JS loadPlanTab function (~line 2598-2660)

**Step 1: Update tabLevel map**

Old: `{ entrenamiento: 1, nutricion: 2, habitos: 3 }`
New: `{ entrenamiento: 1, nutricion: 2, habitos: 1, suplementacion: 1, ciclo: 3, bloodwork: 3 }`

**Step 2: Add rendering for new interactive tabs**

In the unlocked block, call render functions: renderNutritionInteractive(), renderHabitsInteractive(), renderSupplementsInteractive(), renderCycleInteractive(), renderBloodworkInteractive()

**Step 3: For locked tabs, render demo content + overlay using safe DOM methods**

Use createElement/textContent pattern. Build demo content via getDemoContent(tab) helper, overlay via buildLockedOverlay(tab) helper. Append to container.

**Step 4: Commit**

```bash
git add cliente.html
git commit -m "feat: loadPlanTab supports 6 tabs with interactive rendering + locked demo"
```

---

## Task 4: Locked Demo Content Functions

**Files:**
- Modify: `cliente.html` JS section (add after loadPlanTab)

**Step 1: Add getTabDisplayName(tab) — returns display names**

**Step 2: Add getTabFeatures(tab) — returns array of feature strings per tab**

Features per tab:
- nutricion: Plan de comidas, macros, tips coach, historial
- habitos: Tracker diario, categorias, progreso visual, tips
- suplementacion: Protocolo suplementos, timing, categorias, notas
- ciclo: Timeline visual, compuestos, PCT, calendario labs
- bloodwork: Marcadores con rangos, categorias, historial, alertas

**Step 3: Add buildLockedOverlay(tab) — builds overlay DOM using createElement**

Uses createElement for all elements, textContent for text. Returns DOM element.

**Step 4: Add demo content builder functions per tab**

Each returns a DOM element with realistic-looking static content.

**Step 5: Commit**

```bash
git add cliente.html
git commit -m "feat: locked demo content helpers (tab names, features, demo builders)"
```

---

## Task 5: Nutrition Interactive View

**Files:**
- Modify: `cliente.html` JS section

**Step 1: Add NUTRITION_DATA**

Hardcoded plan: 7 meals (Desayuno, Media Manana, Almuerzo, Merienda, Cena, Pre-Entreno, Post-Entreno). Each meal has foods array with name, qty, cal, prot, carbs, fat. Targets: 2800cal, 180prot, 320carbs, 85fat.

**Step 2: Add renderNutritionInteractive()**

Renders into tab-content-nutricion using DOM methods:
- Toggle buttons (Interactiva vs PDF) — same pattern as training
- KPI bar with progress bars (calorias, proteina, carbos, grasa)
- Meal cards (collapsible, each with food list, macros per food)
- Coach tip button per meal (Metodo/Elite only, uses existing exercise-tip endpoint)
- Demo banner if using demo data

**Step 3: Add getNutriDemoContent() — static DOM for locked preview**

**Step 4: Commit**

```bash
git add cliente.html
git commit -m "feat: nutrition interactive view with meal cards, macros, KPI bars"
```

---

## Task 6: Habits Interactive View

**Files:**
- Modify: `cliente.html` JS section

**Step 1: Add HABITS_DATA**

4 categories (Entrenamiento, Nutricion, Recuperacion, Mindset) with 2-3 habits each. Each habit has id, name, points.

**Step 2: Add renderHabitsInteractive()**

Renders into tab-content-habitos:
- Progress bar (% of daily habits completed)
- Streak counter (localStorage-based day streak)
- Category cards with checkboxes
- Coach tip button per category (Metodo/Elite)
- Save to localStorage
- Demo banner if using demo data

**Step 3: Add getHabitsDemoContent() — static DOM for locked preview**

**Step 4: Commit**

```bash
git add cliente.html
git commit -m "feat: habits interactive view with categories, checkboxes, streak tracker"
```

---

## Task 7: Supplements Interactive View

**Files:**
- Modify: `cliente.html` JS section

**Step 1: Add SUPPLEMENTS_DATA**

3 categories (Rendimiento, Salud General, Recuperacion) with 3-4 supplements each. Each has name, dose, timing, notes.

**Step 2: Add renderSupplementsInteractive()**

Renders cards/table with timing icons, dose, notes per supplement. Categories collapsible.

**Step 3: Add getSupplementsDemoContent() — static DOM for locked preview**

**Step 4: Commit**

```bash
git add cliente.html
git commit -m "feat: supplements interactive view with timing, categories, protocols"
```

---

## Task 8: Cycle Hormonal Interactive View

**Files:**
- Modify: `cliente.html` JS section

**Step 1: Add CYCLE_DATA**

Includes: name, duration, warning text, phases array (name, weeks, color), compounds array (name, dose, freq, weeks, notes), pct array, labs array (name, when, markers).

**Step 2: Add renderCycleInteractive()**

Renders:
- Warning banner (red border, always visible) — medical disclaimer
- Timeline visual with colored phase blocks
- Compounds table
- PCT protocol table
- Labs calendar/timeline
- Demo banner

**Step 3: Add getCycleDemoContent() — static DOM for locked preview**

**Step 4: Commit**

```bash
git add cliente.html
git commit -m "feat: cycle hormonal interactive view with timeline, compounds, PCT, labs"
```

---

## Task 9: Bloodwork Interactive View

**Files:**
- Modify: `cliente.html` JS section

**Step 1: Add BLOODWORK_DATA**

5 categories (Hormonal, Hepatico, Lipidico, Hematologico, Renal). Each marker has name, value, unit, low, high, optimal_low, optimal_high.

**Step 2: Add renderBloodworkInteractive()**

Renders:
- Category cards with marker rows
- Each marker: name, value+unit, visual bar showing position in range (green=optimal, yellow=normal, red=out of range)
- Summary line: "X de Y marcadores en rango optimo"
- All DOM built with createElement/textContent

**Step 3: Add getBloodworkDemoContent() — static DOM for locked preview**

**Step 4: Commit**

```bash
git add cliente.html
git commit -m "feat: bloodwork interactive view with color-coded markers, ranges, categories"
```

---

## Task 10: Update Existing Locked Overlays + Cleanup

**Files:**
- Modify: `cliente.html` HTML — existing lock-nutricion and lock-habitos (~lines 1589-1616)
- Modify: `cliente.html` HTML — checkin locked overlay (~line 1767)
- Modify: `cliente.html` JS loadPlanTab (~line 2608-2650)

**Step 1: Simplify tab-content-nutricion and tab-content-habitos**

Remove old iframe-wrap + static locked-overlay HTML. Replace with empty containers (interactive render functions will populate them).

**Step 2: Update checkin locked overlay to match new style (lock-icon, features list)**

**Step 3: Update loadPlanTab to skip iframe for all interactive tabs**

Change condition to only load iframe for nutricion PDF fallback path.

**Step 4: Commit**

```bash
git add cliente.html
git commit -m "refactor: remove old static locked overlays, use dynamic rendering"
```

---

## Task 11: Integration Test + Deploy

**Files:**
- Modify: `cliente.html` (minor fixes if needed)

**Step 1: Test all 6 tabs with each plan level**

Use Playwright to:
1. Login as esencial — verify: Entrenamiento, Habitos, Suplementacion interactive; Nutricion, Ciclo, Bloodwork show blur preview
2. Login as elite — verify: all 6 tabs unlocked and interactive

**Step 2: Test mobile responsive — verify tab-nav scrolls horizontally**

**Step 3: Commit + push + deploy**

```bash
git push origin main
```

Deploy via Easypanel console: `cd /code && git pull origin main`

---

## Task 12: Full Audit — Console Errors, Visual QA, Edge Cases

**Files:**
- Modify: `cliente.html` (bug fixes as needed)

**Step 1: Console error audit**

Open each plan level (esencial, metodo, elite) in Playwright, navigate to ALL 6 tabs, capture console errors. Fix any JS errors (undefined functions, missing elements, null references).

**Step 2: Visual audit with screenshots**

Take screenshots of:
- Each locked preview (verify blur + demo content visible behind)
- Each unlocked interactive tab (verify cards render, data displays)
- Mobile viewport (verify tab scroll, no overflow)
- Overlay buttons (verify "Mejorar Plan" links work)

**Step 3: Edge case testing**

- Switch tabs rapidly (no duplicate renders)
- Refresh page on each tab (state persists correctly)
- Verify habits checkboxes save/load from localStorage
- Verify coach tip buttons only show for metodo/elite
- Verify training tab still works (no regression)
- Verify nutricion IA section (sidebar) still works (no regression)

**Step 4: Fix all issues found**

**Step 5: Final commit + push + deploy**

```bash
git add cliente.html
git commit -m "fix: audit — resolve all errors and edge cases from task 8 implementation"
git push origin main
```

Deploy via Easypanel: `cd /code && git pull origin main`

---

## Execution Order

Tasks 1-3 are foundational (CSS, HTML structure, JS routing).
Task 4 is the locked overlay system.
Tasks 5-9 are independent feature tasks (can be done sequentially).
Task 10 is cleanup of old code.
Task 11 is integration testing.
Task 12 is full audit + bug fixing.

Estimated: ~800-1000 lines of new JS/HTML added to cliente.html.
