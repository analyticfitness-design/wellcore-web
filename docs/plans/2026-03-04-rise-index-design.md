# RISE — Integración en index.html

> Diseño aprobado el 2026-03-04

## Objetivo

Agregar el Reto RISE de manera prominente en la página principal de WellCore Fitness para maximizar inscripciones durante Marzo 2026.

## Componentes a Implementar

### 1. Announcement Bar

- Franja delgada **encima del navbar**, sticky
- Fondo `#E31E24`, texto blanco
- Countdown en tiempo real (`JetBrains Mono`) hasta `2026-03-31 23:59:59`
- CTA: "Unirme →" → `/rise-enroll.html`
- Botón X para cerrar (persiste en `sessionStorage` para no reaparecer)
- Al cerrar: la barra desaparece y el navbar sube

### 2. Sección RISE (después de `<section id="hero">`)

- Separada con `<hr class="section-rule" />` igual que las demás secciones
- Fondo `var(--surface)` con acento rojo izquierdo (border-left: 3px solid var(--red))
- Etiqueta: `// RETO MARZO 2026 · CUPOS LIMITADOS`
- Headline principal: **"RISE. 30 DÍAS."** — `Bebas Neue` ~72px desktop
- Subhead: "Tu transformación real empieza aquí."
- 4 pilares en grid horizontal (4 cols desktop, 2 tablet, 1 mobile):
  1. 🏋️ **Entrenamiento Personalizado** — Programa 1:1 diseñado para ti
  2. 🥗 **Guía de Nutrición** — Cómo alimentarte durante los 30 días
  3. ✅ **Guía de Hábitos** — Sistema diario de hábitos para el reto
  4. 📋 **Seguimiento** — Revisión de tu progreso durante el reto
- Bloque de precio: `$99.900 COP · Pago único · 30 días`
- CTA primario: `Unirme al Reto →` → `/rise-enroll.html`
- CTA secundario: `Ver Detalles` → `/rise.html`
- Countdown secundario en la parte inferior: `[27D] [14H] [32M] [09S]`
- Animaciones con AOS `data-aos="fade-up"` (ya disponible)

### 3. Navbar

- Agregar `RISE` **después de "Método"** en desktop nav
- Color rojo `var(--red)` con punto pulsante animado (igual que "Coaches")
- Link: `/rise.html`
- En mobile menu: aparece en rojo destacado, segundo ítem

## Stack Técnico

- HTML5 + CSS inline / style block existente
- WellCore CSS variables (ya definidas en index.html)
- Font Awesome 6.4 (no disponible en index — usar emojis o cargar FA)
- Bootstrap 5.3 grid (ya disponible)
- AOS animations (ya disponible)
- Vanilla JS para countdown + announcement bar dismiss
- `sessionStorage` para recordar que el usuario cerró el announcement bar

## Verificaciones

- [ ] Announcement bar aparece en desktop y mobile
- [ ] Countdown muestra días/horas/minutos/segundos correctamente
- [ ] X cierra el bar y no reaparece en el mismo tab
- [ ] Sección RISE visible inmediatamente después del hero
- [ ] Grid 4 cols → 2 cols (tablet) → 1 col (mobile)
- [ ] CTA "Unirme al Reto" navega a `/rise-enroll.html`
- [ ] Navbar tiene "RISE" entre "Método" y "Nosotros"
- [ ] Mobile menu tiene "RISE" en rojo

## Archivos a Modificar

- `index.html` únicamente
