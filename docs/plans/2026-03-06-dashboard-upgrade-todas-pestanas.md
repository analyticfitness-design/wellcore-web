# Dashboard Upgrade — Todas las Pestanas al Nivel de Mi Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Elevar la calidad visual e interactiva de TODAS las secciones del portal cliente (`cliente.html`) para que sean consistentes con el nuevo estilo de las 6 pestanas de "Mi Plan" (cards colapsables, KPIs mejorados, progressive disclosure, bordes con accent colors, micro-animaciones).

**Architecture:** Todas las modificaciones en `cliente.html`. No se crean archivos nuevos. Se mantiene el patron de CSS variables existente (--red, --accent, --surface, --border, etc.). El enfoque es mejorar la UI existente sin romper funcionalidad.

**Tech Stack:** HTML/CSS/JS inline en cliente.html. FontAwesome 6.4 para iconos. Fuentes: Bebas Neue (titulos), JetBrains Mono (labels), Inter (body).

---

## Analisis del Estado Actual

### Secciones que YA tienen el nuevo estilo:
- **Mi Plan** (6 tabs): Cards interactivas, KPI bars, colapsables, Coach Tips, locked overlays con blur

### Secciones que NECESITAN upgrade:
| # | Seccion | Lineas | Problemas |
|---|---------|--------|-----------|
| 1 | **Dashboard** | 1453-1524 | KPIs basicos, progress ring estatico, timeline plana, activity feed simple |
| 2 | **Seguimiento** | 1618-1756 | Registro semanal monotoño, log pesos funcional pero sin polish |
| 3 | **Check-in** | 1760-1831 | Locked overlay viejo (sin demo content), form basico |
| 4 | **Fotos Progreso** | 1834-2031 | Ya tiene buen contenido pero cards sin hover states ni accent borders |
| 5 | **Biblioteca** | 2034-2040 | Casi vacia, solo un grid JS |
| 6 | **Nutricion IA** | 2140-2223 | Funcional pero sin el polish visual de Mi Plan |
| 7 | **Soporte** | 2043-2137 | SLA card basica, contact cards sin accent, FAQ funcional |
| 8 | **Mi Perfil** | 2226+ | Formulario basico, avatar simple |

---

## Principios de Diseno (consistentes con Mi Plan)

1. **Cards con border-left accent**: var(--red), #00D9FF, #FFD700 segun contexto
2. **Section headers**: JetBrains Mono, .8rem, uppercase, letter-spacing .1em, color var(--gray)
3. **KPIs mejorados**: Icono + label + valor + progress bar + subtexto comparativo
4. **Hover states**: border-color var(--red), box-shadow 0 4px 24px rgba(0,0,0,0.25), translateY(-2px)
5. **Separadores visuales**: `//` en color #00D9FF antes de subtitulos
6. **Empty states**: Icono grande con opacity .4 + texto descriptivo
7. **Collapsible sections**: Click to expand con chevron animado
8. **Badges informativos**: Plan level, status, contadores

---

## Task 1: Dashboard — KPIs Mejorados + Welcome Card

**Files:**
- Modify: `cliente.html` HTML sec-dashboard (~lines 1453-1524)
- Modify: `cliente.html` JS que carga KPIs (~line 2500)

**Step 1: Agregar Welcome Card antes de KPIs**

Reemplazar el h1 "Dashboard" con una welcome card personalizada:
- Saludo por nombre + hora del dia ("Buenos dias, Carlos")
- Resumen rapido: plan activo (badge color), semana actual, proximo objetivo
- Fecha actual en JetBrains Mono

**Step 2: Redisenar KPI Grid (4 cards)**

Actualizar las 4 KPI cards existentes:
- **Plan Activo**: Icono `fa-crown` para Elite, `fa-bolt` para Metodo, `fa-dumbbell` para Esencial. Badge con color del plan (red=Elite, accent=Metodo, gray=Esencial)
- **Semanas Activo**: Icono `fa-calendar-check`, progress bar mostrando % del programa completado
- **Adherencia**: Icono `fa-fire`, barra de progreso con color dinamico (verde >80%, amarillo 50-80%, rojo <50%)
- **Proxima Entrega**: Icono `fa-clock`, countdown visual, subtexto "en X dias"

Cada card: border-left 3px solid color, icono en background sutil

**Step 3: Mejorar Progress Ring Section**

- Agregar subtexto con comparacion vs semana anterior
- Color del ring segun progreso (red <50%, accent 50-80%, green >80%)
- Texto "semana X de Y" dinamico

**Step 4: Mejorar Timeline Phases**

- Agregar iconos FA a cada fase (fa-stethoscope, fa-file-alt, fa-running, fa-trophy)
- Fase activa con animacion pulse sutil
- Agregar tooltip con fechas de cada fase

**Step 5: Mejorar Activity Feed**

- Cada item con icono contextual (fa-dumbbell para training, fa-check-circle para checkin, fa-camera para fotos, fa-utensils para nutricion)
- Hover state con background sutil
- Timestamp relativo ("hace 2 horas", "ayer", etc.)
- Limite visible de 5 items + "Ver mas" expandible

**Step 6: Agregar Quick Actions Bar**

Cards horizontales debajo del feed:
- "Registrar Entrenamiento" → showSection('seguimiento')
- "Subir Fotos" → showSection('fotos')
- "Ver Mi Plan" → showSection('plan')
Cada card: icono + label + flecha, hover scale

**Step 7: Commit**

```bash
git add cliente.html
git commit -m "feat: dashboard redesign — welcome card, enhanced KPIs, quick actions"
```

---

## Task 2: Seguimiento — Visual Upgrade + Micro-interacciones

**Files:**
- Modify: `cliente.html` HTML sec-seguimiento (~lines 1618-1756)
- Modify: `cliente.html` CSS para nuevos estilos

**Step 1: Mejorar header de seccion**

Agregar subtitulo descriptivo: "Registra tu entrenamiento y visualiza tu progreso"

**Step 2: Redisenar Week Grid**

- Cada dia como card individual con border-left color (completado=green, hoy=accent, pendiente=border)
- Icono animado al completar (checkmark con bounce)
- Resumen semanal mejorado con progress bar circular mini + porcentaje

**Step 3: Mejorar Log de Pesos**

- Agregar badge "PR" (Personal Record) cuando un peso supere el anterior
- Cada fila de ejercicio con border-left accent cuando tiene datos
- Boton guardar con feedback visual (animacion de confirmacion)

**Step 4: Mejorar Progresion Semana a Semana**

- Cards de progresion con icono de tendencia (flecha arriba/abajo/igual)
- Comparacion visual: barra de progreso mostrando cambio porcentual
- Color-coded: verde mejora, rojo retroceso, gris sin cambio

**Step 5: Agregar Mini Chart (opcional)**

- Si hay datos de al menos 3 semanas, mostrar mini grafico sparkline del ejercicio seleccionado
- Usar CSS/SVG simple (no librerias externas)

**Step 6: Commit**

```bash
git add cliente.html
git commit -m "feat: seguimiento visual upgrade — day cards, PR badges, trend indicators"
```

---

## Task 3: Check-in — Locked Overlay Mejorado + Form Polish

**Files:**
- Modify: `cliente.html` HTML sec-checkin (~lines 1760-1831)

**Step 1: Actualizar locked overlay al nuevo estilo**

Reemplazar overlay viejo con el sistema nuevo (buildLockedOverlay pattern):
- Contenido demo detras con blur (formulario simulado)
- Lock icon + titulo "Check-in Semanal Personalizado"
- Features list: "Reporta bienestar semanal", "Feedback del coach", "Ajustes automaticos al plan", "Historial de progreso"
- Boton "Mejorar a Elite" con CTA

**Step 2: Mejorar formulario check-in (Elite)**

- Bienestar slider con emoji visual (1=triste, 5=neutral, 10=feliz)
- Campos con iconos contextuales
- Seccion "Notas" con placeholder motivacional
- Preview del resumen antes de enviar

**Step 3: Mejorar historial de check-ins**

- Cada check-in como card colapsable con fecha + KPIs resumen
- Color-coding segun bienestar (verde alto, amarillo medio, rojo bajo)
- Boton "Ver respuesta del coach" expandible

**Step 4: Commit**

```bash
git add cliente.html
git commit -m "feat: check-in upgrade — new locked preview, form polish, history cards"
```

---

## Task 4: Fotos Progreso — Cards Mejoradas + Transiciones

**Files:**
- Modify: `cliente.html` HTML sec-fotos (~lines 1834-2031)

**Step 1: Mejorar Upload Area**

- Slots de foto con hover state mejorado (border accent + scale sutil)
- Indicador visual de slots completos vs pendientes
- Progress indicator durante upload

**Step 2: Mejorar Photo Gallery**

- Cards con fecha prominente y badge de posicion (FRENTE/PERFIL/ESPALDA)
- Hover: zoom sutil + overlay con fecha
- Grid responsive mejorado

**Step 3: Mejorar Comparativa**

- Slider before/after si hay 2+ registros
- Badges con cambio de fecha (ej: "15 dias de diferencia")
- Header con accent border-left

**Step 4: Mejorar Review del Coach section**

- Card con accent border segun estado (pendiente=amarillo, completado=verde, sin review=gris)
- Reviews anteriores como timeline vertical con linea conectora

**Step 5: Commit**

```bash
git add cliente.html
git commit -m "feat: fotos progreso — upload hover states, gallery cards, review timeline"
```

---

## Task 5: Biblioteca — Grid Mejorado + Categorias

**Files:**
- Modify: `cliente.html` HTML sec-biblioteca (~lines 2034-2040)
- Modify: `cliente.html` JS renderBiblioteca()

**Step 1: Agregar subtitulo y descripcion**

"Todos tus documentos, planes y recursos en un solo lugar"

**Step 2: Mejorar cards de archivos**

- Icono segun tipo de archivo (fa-file-pdf para PDF, fa-dumbbell para entrenamiento, fa-utensils para nutricion)
- Border-left con color segun tipo
- Hover state consistente con Mi Plan
- Badge "NUEVO" si fue subido en los ultimos 7 dias

**Step 3: Empty state mejorado**

Si no hay archivos:
- Icono grande fa-folder-open con opacity
- Texto: "Tu coach subira aqui tus documentos y planes"
- Subtexto: "Los archivos aparecen automaticamente cuando tu coach los prepara"

**Step 4: Commit**

```bash
git add cliente.html
git commit -m "feat: biblioteca — file type icons, hover states, improved empty state"
```

---

## Task 6: Nutricion IA — Consistencia Visual

**Files:**
- Modify: `cliente.html` HTML sec-nutricion (~lines 2140-2223)

**Step 1: Mejorar KPIs de nutricion**

- Agregar iconos (fa-fire para calorias, fa-drumstick-bite para proteina, fa-bread-slice para carbos, fa-utensils para comidas)
- Border-left accent por macro
- Subtexto con target ("de 2800 kcal objetivo")

**Step 2: Mejorar area de registro**

- Slot de foto con estilo consistente con fotos de progreso
- Select de tipo de comida con iconos
- Boton analizar con estilo primary mejorado (gradient sutil)

**Step 3: Mejorar resultado de analisis**

- Card con estructura clara: Nombre comida + Macros como badges + Coach notes
- Rating visual (estrellas o porcentaje de calidad nutricional)

**Step 4: Mejorar historial**

- Cada entrada como card colapsable con resumen (icono comida + nombre + kcal total)
- Timeline visual con hora del registro
- Badge de calidad nutricional

**Step 5: Commit**

```bash
git add cliente.html
git commit -m "feat: nutricion IA — consistent card styles, macro icons, history timeline"
```

---

## Task 7: Soporte — Cards Mejoradas + SLA Visual

**Files:**
- Modify: `cliente.html` HTML sec-soporte (~lines 2043-2137)

**Step 1: Mejorar SLA Card**

- Icono segun plan (fa-bolt para Elite, fa-clock para Metodo, fa-hourglass para Esencial)
- Background gradient sutil segun plan
- Comparacion: "Respuesta promedio: 12h" (datos demo)

**Step 2: Mejorar Contact Cards**

- WhatsApp: border-left verde #25D366, icono fab fa-whatsapp
- Email: border-left accent #00D9FF, icono fas fa-envelope
- Hover state consistente
- Agregar icono de chat widget si esta disponible

**Step 3: Mejorar FAQ**

- Cada pregunta con icono contextual
- Animacion de expansion suave (height transition)
- Border-left accent en item expandido

**Step 4: Commit**

```bash
git add cliente.html
git commit -m "feat: soporte — SLA visual upgrade, contact cards, FAQ animations"
```

---

## Task 8: Mi Perfil — Rediseno Completo

**Files:**
- Modify: `cliente.html` HTML sec-perfil (~line 2226+)

**Step 1: Mejorar avatar y header**

- Avatar con ring de color segun plan (red Elite, accent Metodo, gray Esencial)
- Badge de plan debajo del nombre
- Fecha de inicio + dias activo

**Step 2: Mejorar formulario de perfil**

- Campos agrupados en cards (Datos Personales, Objetivos, Contacto)
- Cada grupo colapsable
- Iconos en cada campo
- Save button con feedback visual

**Step 3: Agregar seccion "Mi Plan" resumen**

- Card con features del plan actual
- CTA de upgrade si no es Elite
- Comparacion visual de lo que incluye cada plan (mini version)

**Step 4: Commit**

```bash
git add cliente.html
git commit -m "feat: perfil — avatar ring, grouped cards, plan summary"
```

---

## Task 9: CSS Global — Nuevas Utilidades + Animaciones

**Files:**
- Modify: `cliente.html` CSS section (~lines 10-200)

**Step 1: Agregar CSS utilities para consistencia**

```css
/* Accent borders */
.accent-left-red { border-left: 3px solid var(--red) }
.accent-left-cyan { border-left: 3px solid #00D9FF }
.accent-left-green { border-left: 3px solid var(--green) }
.accent-left-gold { border-left: 3px solid var(--gold) }

/* Card hover standard */
.card-hover:hover { border-color: var(--red); box-shadow: 0 4px 24px rgba(0,0,0,0.25); transform: translateY(-2px) }

/* Section subtitle */
.section-subtitle { font-family:'JetBrains Mono',monospace; font-size:.8rem; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--gray); margin-bottom:16px }

/* Pulse animation for active elements */
@keyframes pulse-subtle { 0%,100%{opacity:1} 50%{opacity:.7} }
.pulse-subtle { animation: pulse-subtle 2s ease-in-out infinite }
```

**Step 2: Actualizar media queries mobile**

Verificar que todas las nuevas cards se apilen correctamente en mobile (<768px).

**Step 3: Commit**

```bash
git add cliente.html
git commit -m "style: global CSS utilities — accent borders, card hover, section subtitle"
```

---

## Task 10: Bottom Nav — Sincronizacion + Visual Polish

**Files:**
- Modify: `cliente.html` HTML bottom-nav (~line 2340+)
- Modify: `cliente.html` CSS bnav styles

**Step 1: Verificar que bottom nav refleja correctamente la seccion activa**

**Step 2: Agregar badge de notificacion en bottom nav**

- Indicador rojo si hay checkin pendiente (Elite)
- Indicador si hay nuevos archivos en biblioteca

**Step 3: Commit**

```bash
git add cliente.html
git commit -m "feat: bottom nav — notification badges, active state sync"
```

---

## Task 11: Descripciones Grandes + Marcos Premium bajo Títulos

**Files:**
- Modify: `cliente.html` — todas las secciones del dashboard
- Modify: `rise-dashboard.html` — todas las pestañas

**Contexto:** Los dashboards deben tener descripciones claras debajo de cada título de sección con fuente más grande y legible (pensando en usuarios que usan gafas o señoras mayores). Agregar marcos con opacidad de colores amigables que mantengan la identidad WellCore premium.

**Step 1: Agregar CSS para section-description**

```css
.section-desc {
  font-size: 1.05rem;
  line-height: 1.7;
  color: rgba(255,255,255,0.65);
  max-width: 640px;
  margin-bottom: 24px;
  font-weight: 300;
}
.section-desc-box {
  background: linear-gradient(135deg, rgba(200,16,46,0.06), rgba(0,217,255,0.04));
  border: 1px solid rgba(255,255,255,0.06);
  border-left: 3px solid var(--red);
  border-radius: var(--radius-sm);
  padding: 16px 20px;
  margin-bottom: 24px;
}
.section-desc-box p {
  font-size: 1rem;
  line-height: 1.7;
  color: rgba(255,255,255,0.7);
  margin: 0;
}
.section-desc-box .desc-highlight {
  color: var(--white);
  font-weight: 500;
}
```

**Step 2: Agregar descripciones a cada sección de cliente.html**

Después de cada `<h1 class="section-title">`, agregar una descripción dentro de `.section-desc-box`:

- **Dashboard**: "Tu resumen personal. Aquí ves tu plan activo, progreso semanal y actividad reciente."
- **Mi Plan**: "Tu programa completo de entrenamiento, nutrición, hábitos y más. Cada pestaña está diseñada para ti."
- **Seguimiento**: "Registra tu entrenamiento diario y visualiza cómo progresas semana a semana."
- **Check-in**: "Tu reporte semanal para que tu coach pueda ajustar tu programa según tus resultados."
- **Fotos Progreso**: "Documenta tu transformación con fotos periódicas. Tu coach las usa para evaluar tu avance."
- **Biblioteca**: "Todos tus documentos, planes y recursos organizados en un solo lugar."
- **Nutrición IA**: "Analiza tus comidas con inteligencia artificial. Toma una foto o describe tu plato."
- **Soporte**: "¿Necesitas ayuda? Contacta directamente a tu coach por WhatsApp o email."
- **Mi Perfil**: "Tus datos personales, objetivos y configuración de cuenta."

**Step 3: Agregar descripciones a rise-dashboard.html**

Mismo patrón para cada pestaña del RISE dashboard:
- **Mi Progreso**: "Tu avance en el reto de 30 días. Cada día cuenta."
- **Mi Rutina**: "Tu programa de entrenamiento personalizado para el reto."
- **Nutrición**: "Tu guía nutricional para maximizar resultados durante el reto."
- **Hábitos**: "Construye hábitos diarios que transforman tu cuerpo y mente."
- **Fotos**: "Documenta tu transformación durante los 30 días del reto."

**Step 4: Verificar legibilidad en mobile**

Font-size mínimo de 15px para descripciones en viewport < 768px.

**Step 5: Commit**

```bash
git add cliente.html rise-dashboard.html
git commit -m "feat: section descriptions with premium frames — improved readability"
```

---

## Task 12: Fuente General Más Grande + Accesibilidad Visual

**Files:**
- Modify: `cliente.html` CSS section
- Modify: `rise-dashboard.html` CSS section

**Step 1: Aumentar tamaño base de fuente en dashboards**

- Body font-size base: de implícito 16px a 16px (mantener) pero aumentar todos los `.kpi-label`, `.feed-item span`, labels y texto de cards a mínimo `.88rem` (antes muchos eran `.68rem` o `.72rem`)
- Labels JetBrains Mono: de `.68rem` a `.78rem`
- Card text: de `.855rem` a `.95rem`
- KPI values: mantener Bebas Neue grande
- Subtexto/meta: de `.7rem` a `.78rem`

**Step 2: Mejorar contraste**

- `var(--gray)` opacity actual: 0.48 → mejorar a 0.58 para textos descriptivos
- Agregar `--text-readable: rgba(255,255,255,0.72)` para texto de contenido (no labels)

**Step 3: Aplicar a rise-dashboard.html**

Mismos cambios de tamaño para mantener consistencia entre los dos dashboards.

**Step 4: Commit**

```bash
git add cliente.html rise-dashboard.html
git commit -m "style: increase font sizes and contrast for readability across dashboards"
```

---

## Task 13: Integration Test + Deploy

**Step 1: Test con Playwright**

1. Login como Elite → verificar todas las secciones con nuevo estilo
2. Login como Esencial → verificar locked overlays, features limitadas
3. Test mobile responsive (360px viewport)

**Step 2: Deploy**

```bash
git push origin main
```

Deploy en Easypanel: `cd /code && git pull origin main`

---

## Task 14: Auditoria Visual + Bug Fixing

**Step 1: Screenshots de cada seccion en desktop y mobile**
**Step 2: Verificar consistencia de colores, fuentes, spacing**
**Step 3: Fix cualquier overflow, z-index, o responsive issue**
**Step 4: Verificar que no hay regresiones en funcionalidad existente**

```bash
git add cliente.html
git commit -m "fix: visual audit — consistency fixes across all dashboard sections"
git push origin main
```

---

## Orden de Ejecución

- **Task 9** (CSS global) va primero — establece las utilidades que usan las demás tareas
- **Tasks 1-8** son independientes pero se recomiendan en orden
- **Task 10** después de las secciones
- **Tasks 11-12** (descripciones + fuentes) son críticas para accesibilidad
- **Tasks 13-14** al final (test + audit)

## Estimación

~600-900 líneas de cambios en cliente.html + rise-dashboard.html (CSS + HTML + JS).
Scope controlado: solo mejoras visuales y de accesibilidad, sin nuevas APIs ni endpoints.
