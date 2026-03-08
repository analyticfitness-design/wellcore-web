# Plan de Implementación — Pendientes WellCore
**Fecha:** 2026-03-08 | **Estado:** EN CURSO

---

## ESTADO ACTUAL DE PRODUCCIÓN

Producción se actualizó con `git pull` (hasta commit `1a63b75`).
Rebuild Docker disparado desde EasyPanel para incluir commits posteriores.

### Commits pendientes de confirmar en prod
| Commit | Descripción |
|--------|-------------|
| `c4d1abd` | Remove botones Imprimir/PDF de 6 planes demo |
| `99989e0` | Icono PLAN METODO: fa-chart-line → fa-crown |
| `(pendiente)` | rise.html padding 200px → 260px overlap fix |

---

## TODAS LAS TAREAS — Estado

### ✅ COMPLETADO (en repo + prod)

| Tarea | Commit | Descripción |
|-------|--------|-------------|
| coaches.html rediseño | `47b3551` | Pitch page para coaches/influencers LATAM con estudio de mercado, mockups, modelos A/B/C/D |
| Carlos Mendez eliminado de nosotros.html | `1e00e88` | Equipo real: Daniel Esparza + Equipo WellCore |
| Fotos muestran caras (no torsos) | `0b6d78b` | object-position: 50% 30% en nosotros + proceso |
| Chatbot badge rojo (era cyan) | `d92ee1f` | WC.green #00D9FF → #E31E24 |
| rise.html padding mobile | `6274fc3` | 160px → 200px para no solapar navbar |
| Botones WhatsApp eliminados | `6274fc3` | planes.html y metodo.html — verde WA → rojo |
| index.html stats reales | `dbbd6a7` | "94% Adherencia / 1:1 Coaching Directo / 100% Personalizado" |
| index.html mockup plataforma coaches | `dbbd6a7` | UI mock en sección ¿ERES COACH? |
| rise.html dashboards reales | `1a63b75` | Steps 02/03/05/06 con imágenes reales del dashboard |
| Botones Imprimir/PDF eliminados | `c4d1abd` | 6 archivos planes/demo-*.html — solo queda "Enviar al correo" |
| Icono PLAN METODO | `99989e0` | fa-chart-line → fa-crown en planes.html |
| rise.html padding → 260px | *(a commitear)* | Más margen para promo bar expandida |

### 🔲 PENDIENTE

| Tarea | Prioridad | Notas |
|-------|-----------|-------|
| Confirmar rebuild Docker completado | ALTA | Verificar en wellcorefitness.com |
| Verificar coaches.html en producción | MEDIA | Confirmar que CM (Carlos Mendez) ya no aparece |
| Verificar rise.html overlap en móvil | MEDIA | Con promo bar abierta |
| Formulario de aplicación para coaches | MEDIA | Google Form enlazado desde coaches.html |
| Media kit coaches PDF/landing | MEDIA | Ver plan expansión coaches |

---

## Tienda — Productos

Los productos solicitados para eliminar **NO EXISTEN** en el código local:
- ~~Guía nutrición WellCore~~ — nunca estuvo
- ~~Pack de recetas fitness~~ — nunca estuvo
- ~~Guantes de entrenamiento~~ — nunca estuvo
- ~~Foam roller~~ — nunca estuvo
- ~~Shaker WellCore~~ — nunca estuvo
- ~~Bandas de resistencia x5~~ — nunca estuvo

Productos actuales en tienda.html (MACROBLENDS): CR2 Creatina + Greens Mix.

---

## Rise.html — Overlap Fix

La promo bar expandida (con botón INSCRIBIRME visible) suma ~120px + navbar ~60px = ~180px total.
- Antes: 200px padding → margen de solo 20px (insuficiente en algunos dispositivos)
- Ahora: 260px padding → margen de 80px ✅

---

## Coaches.html — Contenido Actual

Commit `47b3551` transformó coaches.html en pitch page completa con:
1. Hero: "¿ERES COACH, CREADOR O PROFESIONAL DEL FITNESS?"
2. Estudio de mercado LATAM (tamaño, problema, benchmarks)
3. Roles que WellCore necesita: Coach, Editor, Dev, Estratega
4. Mockup del panel de coach (CSS, sin imágenes)
5. Modelos de trabajo A/B/C/D (revenue share / certificado / creador / freelance)
6. CTA formulario de aplicación

Si la página aún muestra "Carlos Mendez" en producción → es caché del navegador.
Solución: Chrome → Ajustes → Privacidad → Borrar datos de navegación → Imágenes en caché.

---

## Cómo hacer Git Pull en Producción

### Opción 1 — EasyPanel Rebuild (más fácil)
1. Ir a https://panel.wellcorefitness.com/projects/wellcorefitness/box/wellcorefitness
2. Click "Reconstruir imagen de Docker"
3. Esperar 2-3 min hasta que el estado vuelva a "running"

### Opción 2 — IDE Terminal
1. Abrir https://wellcorefitness-wellcorefitness-ide.v9xcpt.easypanel.host
2. F1 → "Terminal: New Terminal"
3. `cd /code && git pull origin main`

---

*Documento generado: 2026-03-08 | WellCore Fitness*
