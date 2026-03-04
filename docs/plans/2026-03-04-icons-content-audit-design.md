# WellCore — Font Awesome Icons + Content Audit Design

> Diseño aprobado el 2026-03-04

## Objetivo

Unificar la iconografía de todo el proyecto WellCore usando Font Awesome 6.4 CDN y limpiar el contenido de cada página para que cada sección tenga UN mensaje y UN CTA claro. Foco en persuasión a la compra/inscripción.

## Principios Globales

### Iconografía
| Unicode actual | Reemplazo FA | Clase CSS | Uso |
|---|---|---|---|
| `&#10003;` | `fa-check` | `text-green` | Listas de features en planes |
| `&#9670;` | `fa-chevron-right` | `text-red` | Bullets de sección |
| `&#9733;` | `fa-star` | `text-yellow` | Ratings/highlights |
| `&#9660;` | `fa-chevron-down` | — | Dropdowns/FAQ acordeón |
| — | Iconos específicos | — | Tarjetas, proceso, pilares |

### Carga de Font Awesome
- **Método:** CDN link en `<head>` de cada página individualmente
- **URL:** `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css`
- **Páginas que ya lo tienen:** `rise-dashboard.html`, `cliente.html` (ya tienen FA)

### Regla de Contenido (aplica a todas las páginas)
- Cada sección = 1 headline + máx. 1 párrafo corto + 1 CTA
- Párrafos > 3 líneas → convertir en lista con ícono FA
- Máx. 6 items por lista de features
- Headlines: punchy, 5 palabras o menos para secciones de impacto
- Eliminar: texto duplicado entre páginas, explicaciones técnicas largas, repetición de beneficios

---

## Capa 1 — Conversión Principal

### index.html
- Agregar FA CDN en `<head>`
- Trust section: `&#10003;` → `fa-check`
- Sección RISE (ya implementada): revisar íconos si hay
- Hero stats: agregar iconos de apoyo
- CTA final: `fa-arrow-right` en botones

### planes.html
- Agregar FA CDN en `<head>`
- Reemplazar `&#10003;` (67 refs) → `<i class="fas fa-check text-green"></i>`
- Reemplazar `&#9733;` (47 refs) en features destacados → `fa-star`
- Reemplazar `&#10005;` (5 refs, features no incluidos) → `fa-times` en gris
- Iconos por plan: Esencial `fa-seedling`, Método `fa-chart-line`, Élite `fa-crown`
- Limpiar listas a máx. 6 items por plan (eliminar los menos diferenciadores)
- 1 CTA claro por plan

### inscripcion.html
- Agregar FA CDN en `<head>`
- Steps del formulario con iconos: `fa-user`, `fa-file-alt`, `fa-check-circle`
- Campos con íconos: `fa-user`, `fa-envelope`, `fa-phone`, `fa-lock`
- Eliminar texto explicativo que duplica `proceso.html`
- Simplificar a: header corto + form + 1 trust bar

### rise.html
- Agregar FA CDN en `<head>`
- Corregir features a los 4 reales (actualmente muestra 6 pero RISE solo tiene 4):
  1. Entrenamiento Personalizado → `fa-dumbbell`
  2. Guía de Nutrición → `fa-utensils`
  3. Guía de Hábitos → `fa-calendar-check`
  4. Seguimiento → `fa-chart-bar`
- Eliminar "Trazabilidad Completa", "Comunidad RISE", "Antes & Después" (no son features actuales)
- Limpiar FAQ a 4 preguntas esenciales (eliminar 2 menos relevantes)
- `fa-chevron-down` en acordeón FAQ
- Precio destacado con `fa-tag`

---

## Capa 2 — Educación y Método

### metodo.html
- Agregar FA CDN en `<head>`
- 5 pilares con iconos propios (definir en implementación según contenido real)
- Sección "por qué fallan otros": `fa-times-circle` rojo en cada punto
- Sección evidencia científica: `fa-flask` o `fa-microscope`
- Comparativa vs otros métodos: `fa-check` (WellCore) / `fa-times` (genérico)
- Máx. 1 párrafo por pilar (recortar)
- CTA al final de cada sección importante

### proceso.html
- Agregar FA CDN en `<head>`
- 4 fases con iconos: `fa-search` (Diagnóstico), `fa-pencil-ruler` (Diseño), `fa-fire` (Ejecución), `fa-trophy` (Resultados)
- Sección semanas: bullets con `fa-calendar-week`
- FAQ: `fa-chevron-down` en acordeón
- Reducir cada fase a: título + 2-3 bullets + ningún párrafo largo

### coaches.html
- Agregar FA CDN en `<head>`
- Cards de coach: `fa-instagram` para IG, `fa-certificate` para certificaciones
- Stats con iconos relevantes
- CTA a postularse o contactar

### faq.html
- Agregar FA CDN en `<head>`
- `fa-chevron-down` → `fa-chevron-up` (toggle) en cada acordeón
- `fa-question-circle` en cada pregunta
- Agrupar preguntas por categoría con headers de sección
- Eliminar preguntas redundantes o que se responden mejor en otras páginas

---

## Capa 3 — Funnel RISE

### rise-enroll.html
- Agregar FA CDN si no lo tiene
- Progress indicator Step 1/3 con `fa-circle` filled/empty
- Campos con iconos: `fa-user`, `fa-envelope`, `fa-phone`, `fa-city`
- Simplificar: solo datos imprescindibles para el registro
- Trust bar: `fa-lock` "Datos seguros"

### rise-intake.html
- Agregar FA CDN si no lo tiene
- Dividir secciones largas con iconos de categoría: `fa-user-circle` (Perfil), `fa-dumbbell` (Entrenamiento), `fa-utensils` (Nutrición)
- Progress indicator Step 2/3
- Reducir campos opcionales — mantener solo los que el coach necesita

### rise-payment.html
- Agregar FA CDN si no lo tiene
- Progress indicator Step 3/3
- Trust badges: `fa-lock` (Pago seguro), `fa-shield-alt` (Protección), `fa-credit-card` (Múltiples métodos)
- Resumen del pedido limpio con precio y qué incluye (4 items con FA)
- 1 botón CTA grande y claro

### rise-dashboard.html
- Ya tiene FA 6.4 — revisar íconos inconsistentes
- Asegurar coherencia con el resto del funnel

---

## Capa 4 — Portales

### login.html
- Agregar FA CDN en `<head>`
- Campos: `fa-user`, `fa-lock`, `fa-eye`/`fa-eye-slash` (toggle password)
- Tabs cliente/admin con iconos: `fa-user-circle`, `fa-user-shield`
- Error/success messages con `fa-exclamation-circle` / `fa-check-circle`

### cliente.html
- Ya puede tener FA — revisar y completar
- Sidebar items con íconos FA consistentes
- Cards de secciones con iconos
- Notificaciones con `fa-bell`

### admin.html
- Revisar si tiene FA
- KPIs con iconos: `fa-users`, `fa-dollar-sign`, `fa-chart-bar`, `fa-check-square`
- Acciones de tabla con `fa-eye`, `fa-edit`, `fa-trash`
- Sidebar con iconos de sección

---

## Archivos a Modificar

Total: ~15 archivos HTML

**Capa 1 (4):** index.html, planes.html, inscripcion.html, rise.html
**Capa 2 (4):** metodo.html, proceso.html, coaches.html, faq.html
**Capa 3 (4):** rise-enroll.html, rise-intake.html, rise-payment.html, rise-dashboard.html
**Capa 4 (3):** login.html, cliente.html, admin.html

## Orden de Implementación

Capa 1 → revisión → Capa 2 → revisión → Capa 3 → revisión → Capa 4 → revisión final

Cada capa tiene su propio plan de implementación generado por `writing-plans`.
