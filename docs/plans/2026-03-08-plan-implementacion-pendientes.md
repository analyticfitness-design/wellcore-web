# Plan de Implementación — Pendientes WellCore
**Fecha:** 2026-03-08 | **Estado:** EN CURSO

---

## PROBLEMA CRÍTICO: Producción NO tiene los cambios

**Todos los issues que ves en wellcorefitness.com son de producción.**
El repositorio local está correcto y actualizado.

### Comando para resolver TODO de una vez:
```bash
# En el servidor de producción:
cd /ruta/wellcorefitness
git pull origin main
```

---

## Cambios ya en el repo (listos para git pull)

| Commit | Descripción | Resuelve |
|--------|-------------|---------|
| `d92ee1f` | chatbot badge cyan → rojo | Badge confundido con WhatsApp |
| `1e00e88` | nosotros equipo real + fotos caras + rise overlap + tienda overlap | Múltiples issues |
| `fec0f77` | planes.html garantía visual | Visual |
| `407174a` | planes.html garantía tipográfica | Visual |
| `d30c20a` | iOS overflow-x fix | Mobile scroll |
| `47b3551` | coaches.html rediseño completo | Expansión coaches |
| *nuevo* | rise.html padding 200px mobile | Overlap promo bar (más seguro) |
| *nuevo* | planes.html quitar botones WA | No color verde WA |
| *nuevo* | metodo.html quitar botón WA | No WA directo |

---

## Fixes por página (detalle)

### `/nosotros.html` ✅ (commit 1e00e88)
- [x] Eliminado Carlos Mendez (CM) — era personaje falso
- [x] Eliminada Ana Rodriguez (AR) — era personaje falso
- [x] Equipo reemplazado: Daniel Esparza + Equipo WellCore
- [x] Sin clase `coach-avatar green` ni `coach-stat-num green` (sin cyan)
- [x] Foto `object-position: 50% 30%` — muestra caras, no torsos
- [x] Sección "NSCA/PN" — en LOCAL ya dice "Formados para Aplicar Ciencia"

### `/proceso.html` ✅ (commit 1e00e88)
- [x] Foto "CUATRO FASES. UN PROCESO." → `object-position: 50% 30%`

### `/rise.html` ✅ (commit 1e00e88 + nuevo)
- [x] `.platform-section` mobile padding: `160px → 200px`
- [x] Previene overlap de "PLATAFORMA RISE" badge con promo bar + navbar

### `/tienda.html` ✅ (commit 1e00e88 + no existen en local)
- [x] Hero del suplemento: `background-size:45%` + `background-position:center 10%`
- [x] Productos inexistentes en local: guía nutrición, pack recetas, guantes,
      foam roller, shaker, bandas de resistencia — no están en el catalogo local

### `/planes.html` ✅ (nuevos cambios)
- [x] Quitado botón "Preguntanos por WhatsApp" (verde) → reemplazado con CTA rojo
- [x] Quitado "Habla con el coach primero" con ícono WA → apunta a inscripcion.html

### `/metodo.html` ✅ (nuevo cambio)
- [x] Quitado "WhatsApp al Coach" → reemplazado con "Hablar con un Coach" rojo

### `/inscripcion.html` ✅ (sesiones anteriores)
- [x] Botón SELECCIONAR: cambia a rojo (`#E31E24`) al hacer tap

### `js/chat-widget.js` ✅ (commit d92ee1f)
- [x] `WC.green = '#00D9FF'` → `WC.green = '#E31E24'`
- [x] Badge de notificación del chatbot: ya no parece WhatsApp

### `/coaches.html` ✅ (commit 47b3551)
- [x] Rediseño completo como pitch page para coaches/influencers LATAM
- [x] Estudio de mercado inline + propuestas de colaboración

---

## No Blue Rule — Estado

Archivos que TENÍAN cyan/azul → corregidos:
- `nosotros.html`: clases `.green` (cyan `#00D9FF`) → eliminadas
- `chat-widget.js`: `WC.green = '#00D9FF'` → `#E31E24`
- CSS global `wellcore-v6.css` / `wellcore-v5.css`: `--accent` es rojo `#E31E24`

---

## Expansión Coaches — Tareas de Esta Semana

Basado en el rediseño de coaches.html, los siguientes pasos para expandir:

### Semana 1 (8-14 Mar):
1. **Definir perfiles buscados**: Entrenador certificado, editor de video, programador/dev
2. **Crear formulario de aplicación**: Google Form o Typeform enlazado desde coaches.html
3. **Establecer modelo de trabajo**: Freelance por proyecto vs. Revenue share vs. Sueldo fijo

### Semana 2 (15-21 Mar):
1. **Outreach en redes**: DM a coaches con 5k-50k seguidores en LATAM (nicho fitness)
2. **Crear media kit**: PDF/Landing con qué ofrece WellCore a coaches
3. **Reuniones de exploración**: 2-3 calls de 30 min con candidatos

### Modelos de colaboración propuestos (en coaches.html):
- **Modelo A**: Coach asociado — usa plataforma WellCore, revenue share 60/40
- **Modelo B**: Coach certificado WellCore — formación + clientes asignados
- **Modelo C**: Coach creador — produce contenido bajo marca WellCore, pago mensual

---

## Cómo verificar que git pull funcionó

Después de hacer `git pull` en producción, verificar en el browser:

| URL | Qué verificar |
|-----|---------------|
| /nosotros.html | No ver CM (Carlos Mendez) ni AR (Ana Rodriguez) |
| /nosotros.html | Fotos muestran caras (no torsos) |
| /rise.html (móvil) | Badge "PLATAFORMA RISE" no queda debajo del navbar |
| /tienda.html (móvil) | Imagen suplemento no tapa el texto |
| Chatbot (cualquier página) | Badge es rojo, no cyan |
| /planes.html | Sin botones verdes de WhatsApp |
| /inscripcion.html | Al tap en "SELECCIONAR" → botón se pone rojo |
