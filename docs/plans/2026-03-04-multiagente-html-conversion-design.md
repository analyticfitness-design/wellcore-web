# Multiagente IA — HTML Conversion Design

> **Objetivo:** Convertir 5 archivos markdown de documentación a HTMLs visuales e interactivos con hub central de navegación.

---

## Architecture

**Enfoque 3: Hub Central + Documentos Especializados**

```
INDICE.html (Hub Central — punto de entrada)
    ↓
    ├→ README_INICIO_RAPIDO.html
    ├→ CHECKLIST_UNIVERSAL.html
    ├→ ESPECIFICACIONES_TECNICAS.html
    ├→ PLANTILLA_AB_TESTING.html
    └→ GUIA_COMPLETA_EDUCATIVO.html

Cada documento:
✓ Navbar breadcrumb + botón "Volver al Índice"
✓ CSS compartido (WellCore theme)
✓ Contenido específico + interactividad local
✓ Responsive (desktop, tablet, mobile)
```

---

## Design System

### Paleta WellCore
- Fondo principal: `#0a0a0a`
- Superficie: `#111113`
- Cartas: `#18181b`
- Acento rojo: `#E31E24`
- Texto primario: `#ffffff`
- Bordes sutiles: `rgba(255,255,255,0.06)`
- Gray dim: `rgba(255,255,255,0.18)`

### Tipografía
- Títulos: Bebas Neue (uppercase, letter-spacing 2px)
- Body: Inter (15px, line-height 1.6, weight 400)
- Técnica: JetBrains Mono (12px, código/valores)

### Reglas
- Sin gradientes
- Bordes sutiles para separación
- Hover effects en rojo (#E31E24)
- Transiciones smooth (200ms)

---

## Files to Create

| Archivo | Descripción | Interactividad |
|---------|-------------|----------------|
| **INDICE.html** | Hub central con 5 tarjetas | Links a documentos |
| **README_INICIO_RAPIDO.html** | Guía de inicio paso-a-paso | Sidebar TOC, accordions expandibles |
| **CHECKLIST_UNIVERSAL.html** | Checklist pre-publicación | ✅ Checkboxes funcionales, localStorage, progress bar |
| **ESPECIFICACIONES_TECNICAS.html** | Specs por plataforma | Tabs, calculadora Premiere, copy-to-clipboard |
| **PLANTILLA_AB_TESTING.html** | Template de tracking A/B | 📊 Tabla editable, cálculos automáticos, gráficos |
| **GUIA_COMPLETA_EDUCATIVO.html** | Guía 10 pasos educativo | Timeline visual, pasos expandibles, copy prompts |

---

## Component Specifications

### INDICE.html (Hub Central)
**Estructura:**
- Hero section: Logo + título + descripción breve
- Grid 2x3 de tarjetas (5 documentos)
- Cada tarjeta: Ícono, título, duración estimada, descripción breve, botón CTA
- Sección de atajo rápido (botones por tipo contenido)
- Footer: Link a DISEÑO_SISTEMA_MULTIAGENTE.html

**Interactividad:**
- Tarjetas clickeables → abren respectivos HTML
- Hover effect rojo en tarjetas
- Links de atajo rápido pre-navegados

---

### README_INICIO_RAPIDO.html
**Estructura:**
- Breadcrumb nav + botón Volver
- Sidebar: Tabla de contenidos con links internos
- Main content: 6 pasos/secciones
- Cada sección expandible (accordion)
- Timeline visual mostrando flujo completo

**Interactividad:**
- Sidebar TOC con scroll tracking (destaca sección activa)
- Accordions: expand/collapse suave
- Botones "Ir a Checklist", "Ir a Specs", etc.
- Smooth scroll a secciones

---

### CHECKLIST_UNIVERSAL.html
**Estructura:**
- Breadcrumb nav + botón Volver
- Progress indicator (0-100%)
- 6 secciones principales, cada una colapsable
- Checkboxes para cada item
- Status badge: "En Progreso" / "Listo para Publicar"
- Botones: "Descargar Checklist", "Reiniciar"

**Interactividad:**
- ✅ Checkboxes funcionales (marca/desmarca)
- 💾 localStorage persiste valores entre refreshes
- 📊 Progress bar se actualiza en tiempo real
- 🎯 Red flags destacados si faltan items críticos
- 📋 Descargar como JSON o TXT

---

### ESPECIFICACIONES_TECNICAS.html
**Estructura:**
- Breadcrumb nav + botón Volver
- Tabs horizontales: TikTok, Reels, YouTube, Shorts, Feed, Pinterest, WhatsApp
- Para cada plataforma: Grid de specs (resolución, duración, codec, bitrate, audio)
- Sección "Calculadora de Preset Premiere"
- Tabla comparativa de todas las plataformas

**Interactividad:**
- Tabs: cambio dinámico de contenido
- Copy-to-clipboard: hex codes, resoluciones
- Calculadora: input plataforma → sugiere preset export
- Tabla: sorteable/filtrable

---

### PLANTILLA_AB_TESTING.html
**Estructura:**
- Breadcrumb nav + botón Volver
- Tabla editable (filas: Fecha, Tipo, Tema, Versión, Hook, Caption, Plataforma, Likes, Comments, Shares, Saves, CTR, Ganador, Notas)
- Gráfico comparativo A vs B (Chart.js)
- Resumen estadístico: Media, % diferencia, ganador claro
- Botones: "Agregar fila", "Exportar CSV", "Importar datos"
- Plantilla semanal pre-llenada

**Interactividad:**
- 📝 Tabla completamente editable (inline editing)
- 🧮 Cálculos automáticos:
  - CTR = (Engagement / Impressions) × 100
  - Engagement = Likes + Comments + Shares + Saves
  - % diferencia entre A y B
  - Auto-detecta ganador
- 💾 localStorage salva datos
- 📈 Gráfico Chart.js actualiza en tiempo real
- 📥 Exportar CSV para Google Sheets

---

### GUIA_COMPLETA_EDUCATIVO.html
**Estructura:**
- Breadcrumb nav + botón Volver
- Timeline visual: 10 pasos numerados
- Cada paso: Card desplegable con detalles
- Para cada paso: Tiempo estimado, agente responsable, archivos, descripción
- Botones de acción: "Copy prompt", "Abrir ChatGPT", "Abrir Runway", etc.
- Checklist de verificación por paso

**Interactividad:**
- Timeline pasos: expandir/contraer (accordion)
- Copy-to-clipboard: prompts específicos
- Botones "Abrir en X" → links pre-configurados a herramientas
- Progress visual: marcar pasos completados
- Scroll highlighting: destaca paso activo

---

## Tech Stack

- **HTML5:** Semántico, accesible
- **CSS3:** Grid/Flexbox, variables CSS, responsive
- **JavaScript Vanilla:** Interactividad, localStorage, cálculos
- **Chart.js:** Gráficos A/B testing (CDN)
- **No frameworks:** Arquitectura simple, sin dependencias externas

---

## Responsive Design

| Breakpoint | Comportamiento |
|------------|---|
| **Desktop (1200px+)** | Layout completo, sidebar visible |
| **Tablet (768px-1199px)** | Sidebar colapsado, grid adaptado |
| **Mobile (< 768px)** | Full-width, menú burger si aplica, single column |

---

## Color Scheme & Interactions

**Hover States:**
- Cartas: border #E31E24 + background #18181b
- Links: color #E31E24
- Buttons: background #E31E24, text white
- Checkboxes: border #E31E24 cuando checked

**Active States:**
- Tabs activos: borde inferior #E31E24
- Sidebar TOC: item activo #E31E24
- Breadcrumb actual: color #E31E24

**Transitions:**
- Todos los cambios: 200ms ease-out
- Accordions: max-height smooth (300ms)

---

## Implementation Notes

1. **CSS Compartido:** Crear `styles-shared.css` con variables WellCore + base styles
2. **Originalidad:** Mantener archivos .md intactos en su ubicación original
3. **Ubicación de HTMLs:** Crear en `C:\Users\GODSF\Desktop\...\Contenido IA GENERATIVO para redes sociales\`
4. **Standalone:** Cada HTML es completamente funcional independientemente
5. **No build process:** Vanilla JS, no necesita compilación

---

## Success Criteria

✅ INDICE.html carga correctamente y enlaza a todos los documentos
✅ Cada HTML es responsive (desktop, tablet, mobile)
✅ Checkboxes funcionales persisten en localStorage (README)
✅ AB Testing tabla editable con cálculos automáticos
✅ Todos los copys a clipboard funcionan
✅ Navegación breadcrumb + botón volver consistente
✅ Diseño WellCore consistente (colores, tipografía, spacing)
✅ Sin gradientes, sin excesos visuales

---

**Status:** Diseño aprobado. Listo para implementación (Enfoque 1 primero, luego Enfoque 2).
