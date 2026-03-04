# RISE Index Integration — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Agregar RISE al index.html con announcement bar, sección dedicada y link en navbar.

**Architecture:** Single-file modification (index.html). CSS en el style block existente (antes de `</style>` línea 2293). HTML insertado en posiciones exactas. JS vanilla al final antes de `</body>`. Sin dependencias nuevas — usa Bootstrap 5.3, AOS y variables CSS ya disponibles.

**Tech Stack:** HTML5, CSS3 (variables WellCore existentes), Vanilla JS ES6, Bootstrap 5.3 grid (ya cargado), AOS animations (ya cargado)

---

## Task 1: CSS — Announcement Bar + Sección RISE

**Files:**
- Modify: `index.html:2291-2293` (insertar CSS antes de `</style>`)

**Step 1: Insertar CSS del announcement bar y sección RISE**

Encontrar en `index.html` el bloque:
```
/* ── Mobile nav link slide ── */
.nav-mobile-link { transition: color 0.15s, padding-left 0.15s; }
.nav-mobile-link:hover { padding-left: 8px; }
</style>
```

Reemplazar con:
```css
/* ── Mobile nav link slide ── */
.nav-mobile-link { transition: color 0.15s, padding-left 0.15s; }
.nav-mobile-link:hover { padding-left: 8px; }

/* ════════════════════════════════════════════
   RISE — Announcement Bar
════════════════════════════════════════════ */
.rise-bar {
  position: fixed;
  top: 0; left: 0; right: 0;
  height: 40px;
  background: var(--red);
  color: #fff;
  z-index: 1001;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  font-family: var(--font-mono);
  font-size: 10px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  transition: transform 0.3s ease;
}
.rise-bar.hidden { transform: translateY(-100%); }
.rise-bar-countdown { color: rgba(255,255,255,0.85); }
.rise-bar-cta {
  background: #fff;
  color: var(--red) !important;
  padding: 4px 12px;
  font-weight: 700;
  font-size: 9px;
  letter-spacing: 0.12em;
  text-decoration: none;
  transition: opacity 0.1s;
  white-space: nowrap;
}
.rise-bar-cta:hover { opacity: 0.85; }
.rise-bar-close {
  position: absolute;
  right: 16px;
  background: none;
  border: none;
  color: rgba(255,255,255,0.7);
  cursor: pointer;
  font-size: 16px;
  padding: 4px 8px;
  line-height: 1;
}
.rise-bar-close:hover { color: #fff; }
#navbar.bar-open { top: 40px; }
@media (max-width: 768px) {
  .rise-bar { font-size: 8px; gap: 8px; padding: 0 40px 0 8px; }
  .rise-bar-text-label { display: none; }
}

/* ════════════════════════════════════════════
   RISE — Sección Principal
════════════════════════════════════════════ */
#rise { padding: 80px 0; }

.rise-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 80px;
  align-items: start;
}

.rise-headline {
  font-family: var(--font-head);
  font-size: 80px;
  line-height: 0.9;
  margin-bottom: 16px;
  color: var(--white);
}
.rise-headline em {
  color: var(--red);
  font-style: normal;
}

.rise-sub {
  font-family: var(--font-mono);
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--gray);
  margin-bottom: 16px;
}

.rise-desc {
  color: var(--gray);
  font-size: 15px;
  line-height: 1.7;
  margin-bottom: 28px;
  max-width: 460px;
}

.rise-price-block {
  background: var(--red-dim);
  border: 1px solid rgba(227,30,36,0.25);
  border-left: 3px solid var(--red);
  padding: 20px 24px;
  margin-bottom: 28px;
  display: inline-block;
}
.rise-price {
  font-family: var(--font-head);
  font-size: 52px;
  color: var(--red);
  line-height: 1;
  margin-bottom: 4px;
}
.rise-price-detail {
  font-family: var(--font-mono);
  font-size: 10px;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--gray);
}

.rise-ctas {
  display: flex;
  align-items: center;
  gap: 24px;
  margin-bottom: 32px;
  flex-wrap: wrap;
}
.rise-cta-secondary {
  font-family: var(--font-mono);
  font-size: 10px;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--gray);
  text-decoration: none;
  border-bottom: 1px solid var(--gray-dim);
  padding-bottom: 2px;
  transition: color 0.1s linear, border-color 0.1s linear;
}
.rise-cta-secondary:hover { color: var(--white); border-color: var(--white); }

.rise-countdown-row {
  display: flex;
  align-items: flex-end;
  gap: 6px;
  margin-bottom: 6px;
}
.rise-cd-unit { text-align: center; }
.rise-cd-num {
  font-family: var(--font-head);
  font-size: 32px;
  color: var(--white);
  display: block;
  line-height: 1;
}
.rise-cd-label {
  font-family: var(--font-mono);
  font-size: 7px;
  letter-spacing: 1.5px;
  color: var(--gray-dim);
  text-transform: uppercase;
  display: block;
}
.rise-cd-sep {
  font-family: var(--font-head);
  font-size: 22px;
  color: var(--red);
  line-height: 1;
  margin-bottom: 12px;
}
.rise-cd-foot {
  font-family: var(--font-mono);
  font-size: 9px;
  letter-spacing: 1px;
  color: var(--gray);
  text-transform: uppercase;
}

/* Pilares */
.rise-pillar {
  display: flex;
  gap: 16px;
  padding: 20px 0;
  border-bottom: 1px solid var(--border);
  align-items: flex-start;
}
.rise-pillar:last-child { border-bottom: none; }
.rise-pillar-icon {
  color: var(--red);
  font-size: 10px;
  margin-top: 5px;
  flex-shrink: 0;
}
.rise-pillar-title {
  font-weight: 600;
  font-size: 14px;
  margin-bottom: 4px;
  color: var(--white);
}
.rise-pillar-desc {
  font-size: 13px;
  color: var(--gray);
  line-height: 1.6;
  margin: 0;
}

/* Responsive RISE */
@media (max-width: 991px) {
  .rise-layout { grid-template-columns: 1fr; gap: 40px; }
  .rise-headline { font-size: 60px; }
  .rise-desc { max-width: 100%; }
}
@media (max-width: 576px) {
  .rise-headline { font-size: 52px; }
  #rise { padding: 60px 0; }
  .rise-price { font-size: 40px; }
}
</style>
```

**Step 2: Verificar que el style block cierra correctamente**

Run: `grep -c "</style>" index.html`
Expected: `1`

**Step 3: Commit CSS**

```bash
git add index.html
git commit -m "style: add RISE announcement bar and section CSS to index"
```

---

## Task 2: HTML — Announcement Bar

**Files:**
- Modify: `index.html:2317` (insertar antes de `<nav id="navbar">`)

**Step 1: Insertar el HTML del announcement bar**

Encontrar en `index.html`:
```html
<!-- ============================================================
     1. NAVBAR
============================================================ -->
<nav id="navbar">
```

Reemplazar con:
```html
<!-- ============================================================
     RISE — Announcement Bar
============================================================ -->
<div class="rise-bar" id="riseBar">
  <div style="display:flex;align-items:center;gap:14px;">
    <span class="rise-bar-text-label">&#9670; RISE 30 D&Iacute;AS &mdash; INSCRIPCIONES ABIERTAS</span>
    <span class="rise-bar-countdown" id="riseBarCountdown">Cierra en: cargando...</span>
  </div>
  <a href="/rise-enroll.html" class="rise-bar-cta">Unirme &rarr;</a>
  <button class="rise-bar-close" id="riseBarClose" aria-label="Cerrar">&#215;</button>
</div>

<!-- ============================================================
     1. NAVBAR
============================================================ -->
<nav id="navbar">
```

**Step 2: Verificar que el bar aparece antes del nav**

Run: `grep -n "riseBar\|id=\"navbar\"" index.html | head -5`
Expected: `riseBar` aparece en línea menor a `id="navbar"`

**Step 3: Commit announcement bar HTML**

```bash
git add index.html
git commit -m "feat: add RISE announcement bar with countdown to index"
```

---

## Task 3: HTML — Sección RISE (después del Hero)

**Files:**
- Modify: `index.html:2431-2433` (insertar entre `</section>` del hero y el `<hr>`)

**Step 1: Insertar la sección RISE**

Encontrar en `index.html` (fin del hero, inicio del trust):
```html
  </div>
</section>

<hr class="section-rule" />

<!-- ============================================================
     3. TRUST / VALUE PROPOSITIONS
============================================================ -->
<section id="trust">
```

Reemplazar con:
```html
  </div>
</section>

<hr class="section-rule" />

<!-- ============================================================
     RISE — RETO 30 DÍAS
============================================================ -->
<section id="rise">
  <div class="container">
    <div class="rise-layout">

      <!-- Columna izquierda: headline + info + CTA -->
      <div class="rise-left" data-aos="fade-right">
        <span class="label-tag">// RETO MARZO 2026 &middot; CUPOS LIMITADOS</span>
        <h2 class="rise-headline">RISE.<br><em>30 D&Iacute;AS.</em></h2>
        <p class="rise-sub">Tu transformaci&oacute;n real empieza aqu&iacute;.</p>
        <p class="rise-desc">Un reto estructurado de 30 d&iacute;as con entrenamiento personalizado, gu&iacute;a de nutrici&oacute;n, h&aacute;bitos y seguimiento real. Sin adivinar. Con un plan dise&ntilde;ado para ti.</p>

        <div class="rise-price-block">
          <div class="rise-price">$99.900</div>
          <div class="rise-price-detail">COP &middot; Pago &uacute;nico &middot; 30 d&iacute;as</div>
        </div>

        <div class="rise-ctas">
          <a href="/rise-enroll.html" class="btn-red"><div class="btn-bg"></div><span class="btn-text">Unirme al Reto &rarr;</span></a>
          <a href="/rise.html" class="rise-cta-secondary">Ver Detalles</a>
        </div>

        <div class="rise-countdown-row">
          <div class="rise-cd-unit">
            <span class="rise-cd-num" id="riseSecDays">--</span>
            <span class="rise-cd-label">D&Iacute;AS</span>
          </div>
          <div class="rise-cd-sep">:</div>
          <div class="rise-cd-unit">
            <span class="rise-cd-num" id="riseSecHours">--</span>
            <span class="rise-cd-label">HORAS</span>
          </div>
          <div class="rise-cd-sep">:</div>
          <div class="rise-cd-unit">
            <span class="rise-cd-num" id="riseSecMins">--</span>
            <span class="rise-cd-label">MIN</span>
          </div>
          <div class="rise-cd-sep">:</div>
          <div class="rise-cd-unit">
            <span class="rise-cd-num" id="riseSecSecs">--</span>
            <span class="rise-cd-label">SEG</span>
          </div>
        </div>
        <p class="rise-cd-foot">Finaliza el 31 de Marzo de 2026</p>
      </div>

      <!-- Columna derecha: 4 pilares -->
      <div class="rise-right" data-aos="fade-left" data-aos-delay="100">
        <div class="rise-pillar">
          <div class="rise-pillar-icon">&#9670;</div>
          <div>
            <div class="rise-pillar-title">Entrenamiento Personalizado</div>
            <p class="rise-pillar-desc">Programa dise&ntilde;ado 1:1 para tu nivel, tu cuerpo y tus objetivos. 30 d&iacute;as de trabajo estructurado sin improvisar.</p>
          </div>
        </div>
        <div class="rise-pillar">
          <div class="rise-pillar-icon">&#9670;</div>
          <div>
            <div class="rise-pillar-title">Gu&iacute;a de Nutrici&oacute;n</div>
            <p class="rise-pillar-desc">C&oacute;mo alimentarte correctamente durante el reto. Principios claros y pr&aacute;cticos, sin dietas extremas.</p>
          </div>
        </div>
        <div class="rise-pillar">
          <div class="rise-pillar-icon">&#9670;</div>
          <div>
            <div class="rise-pillar-title">Gu&iacute;a de H&aacute;bitos</div>
            <p class="rise-pillar-desc">Sistema de h&aacute;bitos diarios para garantizar consistencia. Peque&ntilde;os cambios que generan gran impacto.</p>
          </div>
        </div>
        <div class="rise-pillar">
          <div class="rise-pillar-icon">&#9670;</div>
          <div>
            <div class="rise-pillar-title">Seguimiento del Reto</div>
            <p class="rise-pillar-desc">Revisi&oacute;n de tu progreso durante los 30 d&iacute;as. No estar&aacute;s solo en este proceso.</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<hr class="section-rule" />

<!-- ============================================================
     3. TRUST / VALUE PROPOSITIONS
============================================================ -->
<section id="trust">
```

**Step 2: Verificar que la sección existe y tiene las 4 columnas**

Run: `grep -c "rise-pillar" index.html`
Expected: `8` (4 divs × 2 referencias a la clase)

**Step 3: Commit sección RISE**

```bash
git add index.html
git commit -m "feat: add RISE section to index after hero with 4 pillars and countdown"
```

---

## Task 4: HTML — Navbar (Desktop + Mobile)

**Files:**
- Modify: `index.html:2323-2331` (desktop nav links)
- Modify: `index.html:2349-2358` (mobile nav links)

**Step 1: Agregar RISE al navbar desktop**

Encontrar:
```html
    <ul class="nav-links">
      <li><a href="metodo.html">M&eacute;todo</a></li>
      <li><a href="nosotros.html">Nosotros</a></li>
```

Reemplazar con:
```html
    <ul class="nav-links">
      <li><a href="metodo.html">M&eacute;todo</a></li>
      <li><a href="rise.html" style="color:var(--red);position:relative;font-weight:600;">RISE<span style="position:absolute;top:-6px;right:-10px;width:5px;height:5px;border-radius:50%;background:#E31E24;animation:pulse-dot 1.8s ease-in-out infinite;"></span></a></li>
      <li><a href="nosotros.html">Nosotros</a></li>
```

**Step 2: Agregar RISE al mobile menu**

Encontrar:
```html
  <ul>
    <li><a href="metodo.html" class="nav-mobile-link">M&eacute;todo</a></li>
    <li><a href="nosotros.html" class="nav-mobile-link">Nosotros</a></li>
```

Reemplazar con:
```html
  <ul>
    <li><a href="metodo.html" class="nav-mobile-link">M&eacute;todo</a></li>
    <li><a href="rise.html" class="nav-mobile-link" style="color:#E31E24;font-weight:700;">&#9670; RISE &mdash; Reto 30 D&iacute;as</a></li>
    <li><a href="nosotros.html" class="nav-mobile-link">Nosotros</a></li>
```

**Step 3: Verificar navbar**

Run: `grep -n "rise.html" index.html`
Expected: 2 líneas (una desktop, una mobile) + la del CTA de la sección = mínimo 3

**Step 4: Commit navbar**

```bash
git add index.html
git commit -m "feat: add RISE link to navbar (desktop + mobile) after Método"
```

---

## Task 5: JavaScript — Countdown + Announcement Bar Logic

**Files:**
- Modify: `index.html` (insertar antes de `</body>`)

**Step 1: Encontrar el cierre del body**

Run: `grep -n "</body>" index.html`
Expected: última línea del archivo

**Step 2: Insertar JS antes de `</body>`**

Encontrar la línea `</body>` y justo antes agregar:

```html
<!-- RISE Scripts -->
<script>
(function () {
  var DEADLINE = new Date('2026-03-31T23:59:59');

  // ── Announcement Bar ──────────────────────────
  var bar      = document.getElementById('riseBar');
  var nav      = document.getElementById('navbar');
  var closeBtn = document.getElementById('riseBarClose');
  var barCd    = document.getElementById('riseBarCountdown');

  function fmtBar() {
    var diff = DEADLINE - new Date();
    if (diff <= 0) return 'Inscripciones cerradas';
    var d = Math.floor(diff / 86400000);
    var h = Math.floor((diff % 86400000) / 3600000);
    var m = Math.floor((diff % 3600000) / 60000);
    var s = Math.floor((diff % 60000) / 1000);
    return 'Cierra en: ' + d + 'D ' +
      String(h).padStart(2,'0') + 'H ' +
      String(m).padStart(2,'0') + 'M ' +
      String(s).padStart(2,'0') + 'S';
  }

  if (bar) {
    if (sessionStorage.getItem('rise_bar_closed')) {
      bar.style.display = 'none';
    } else {
      if (nav) nav.classList.add('bar-open');
      barCd.textContent = fmtBar();
      setInterval(function () { barCd.textContent = fmtBar(); }, 1000);
      closeBtn.addEventListener('click', function () {
        bar.classList.add('hidden');
        if (nav) nav.classList.remove('bar-open');
        sessionStorage.setItem('rise_bar_closed', '1');
      });
    }
  }

  // ── Section Countdown ─────────────────────────
  var dEl = document.getElementById('riseSecDays');
  var hEl = document.getElementById('riseSecHours');
  var mEl = document.getElementById('riseSecMins');
  var sEl = document.getElementById('riseSecSecs');

  function tickSection() {
    var diff = DEADLINE - new Date();
    if (diff <= 0) {
      if (dEl) dEl.textContent = '00';
      if (hEl) hEl.textContent = '00';
      if (mEl) mEl.textContent = '00';
      if (sEl) sEl.textContent = '00';
      return;
    }
    if (dEl) dEl.textContent = Math.floor(diff / 86400000);
    if (hEl) hEl.textContent = String(Math.floor((diff % 86400000) / 3600000)).padStart(2,'0');
    if (mEl) mEl.textContent = String(Math.floor((diff % 3600000) / 60000)).padStart(2,'0');
    if (sEl) sEl.textContent = String(Math.floor((diff % 60000) / 1000)).padStart(2,'0');
  }

  if (dEl) { tickSection(); setInterval(tickSection, 1000); }
})();
</script>
```

**Step 3: Verificar que el script está antes de `</body>`**

Run: `grep -n "RISE Scripts\|</body>" index.html`
Expected: "RISE Scripts" aparece en línea anterior a `</body>`

**Step 4: Commit JS**

```bash
git add index.html
git commit -m "feat: add RISE countdown JS and announcement bar dismiss logic"
```

---

## Verificaciones Finales

Abrir `https://wellcorefitness.test` y verificar:

- [ ] Announcement bar roja aparece en la parte superior (encima del navbar)
- [ ] Countdown del bar actualiza cada segundo con días/horas/min/seg reales
- [ ] Botón X cierra el bar y el navbar sube; no reaparece al recargar la misma pestaña
- [ ] Navbar tiene "RISE" en rojo con punto pulsante, entre "Método" y "Nosotros"
- [ ] En mobile: "RISE — Reto 30 Días" aparece en el menú hamburguesa en rojo
- [ ] Sección RISE aparece inmediatamente después del hero (al hacer scroll)
- [ ] Countdown de la sección actualiza cada segundo
- [ ] CTA "Unirme al Reto →" navega a `/rise-enroll.html`
- [ ] CTA "Ver Detalles" navega a `/rise.html`
- [ ] En tablet (991px): layout pasa a 1 columna
- [ ] En mobile (576px): headline se reduce a 52px

## Archivos Modificados

- `index.html` (único archivo)
