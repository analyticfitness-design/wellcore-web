# Capa 1 — FA Icons + Content Audit: index, planes, inscripcion, rise

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Agregar Font Awesome 6.4 CDN y limpiar el contenido de las 4 páginas de conversión principal de WellCore.

**Architecture:** Modificaciones quirúrgicas a 4 archivos HTML independientes. Sin dependencias entre archivos. Cada tarea es un archivo completo. Iconos Unicode reemplazados por `<i class="fas fa-*">`. Contenido recortado al mínimo persuasivo (1 mensaje + 1 CTA por sección).

**Tech Stack:** HTML5, Font Awesome 6.4 CDN (`https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css`), CSS variables WellCore existentes

---

## Task 1: rise.html — FA Icons + Content Audit

**Files:**
- Modify: `rise.html` (532 líneas)

**Contexto:** rise.html tiene su propio header mínimo, no usa WellCore navbar. Usa emoji como íconos de features (📊, 🎯, 📚, 🍎, 👥, 📸). Muestra 6 features pero RISE solo tiene 4. Muestra precio USD + COP. Countdown sin segundos.

**Step 1: Agregar FA CDN al `<head>`**

Encontrar:
```html
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=JetBrains+Mono:wght@400;500;600&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
```

Reemplazar con:
```html
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=JetBrains+Mono:wght@400;500;600&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
```

**Step 2: Actualizar CSS del feature-icon para usar FA**

Encontrar en el `<style>`:
```css
        .feature-icon {
            font-size: 32px;
            margin-bottom: 16px;
        }
```

Reemplazar con:
```css
        .feature-icon {
            font-size: 28px;
            margin-bottom: 16px;
            color: var(--red);
            width: 48px;
            height: 48px;
            background: var(--red-dim);
            border: 1px solid rgba(227,30,36,0.2);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
```

**Step 3: Reemplazar hero-tag emoji**

Encontrar:
```html
            <div class="hero-tag">🚀 Disponible Todo Marzo</div>
            <h1>Reto RISE 30 Días</h1>
            <p>Transforma tu entrenamiento con un programa 100% personalizado. Mediciones, tracking diario, guías de hábitos y nutrición en una sola plataforma.</p>
```

Reemplazar con:
```html
            <div class="hero-tag"><i class="fas fa-fire"></i> Disponible Todo Marzo</div>
            <h1>Reto RISE 30 Días</h1>
            <p>30 días. Un plan personalizado. Resultados reales.</p>
```

**Step 4: Reemplazar features section — de 6 a 4 cards con FA**

Encontrar:
```html
    <section class="container" id="features">
        <h2 style="font-family: 'Bebas Neue', sans-serif; font-size: 36px; letter-spacing: 0.02em; margin-bottom: 48px; text-align: center;">Qué Incluye RISE</h2>

        <div class="features">
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Trazabilidad Completa</h3>
                <p>Trackea cada entrenamiento, peso, medidas corporales y hábitos diarios. Dashboard con métricas personalizadas.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🎯</div>
                <h3>Programa Personalizado</h3>
                <p>Diseñado según tu nivel, disponibilidad, equipamiento y objetivos. Gym o casa — tú eliges.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📚</div>
                <h3>Guía de Hábitos</h3>
                <p>Entrenamientos progresivos con descripción de cada ejercicio, variaciones y ajustes según tu progreso.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🍎</div>
                <h3>Guía de Nutrición</h3>
                <p>Recomendaciones nutricionales alineadas con tu plan de entrenamiento y objetivos específicos.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Comunidad RISE</h3>
                <p>Acceso a una comunidad de retadores. Comparte progreso, inspírate y motívate con otros.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📸</div>
                <h3>Antes & Después</h3>
                <p>Sistema de seguimiento visual. Captura tu progreso con fotos que demuestren la transformación.</p>
            </div>
        </div>
    </section>
```

Reemplazar con:
```html
    <section class="container" id="features">
        <div style="text-align:center;margin-bottom:48px;">
            <div style="font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:4px;color:var(--red);text-transform:uppercase;margin-bottom:12px;">// QUÉ INCLUYE</div>
            <h2 style="font-family:'Bebas Neue',sans-serif;font-size:48px;letter-spacing:0.02em;">RISE. 4 PILARES.</h2>
        </div>

        <div class="features" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-dumbbell"></i></div>
                <h3>Entrenamiento Personalizado</h3>
                <p>Programa 1:1 diseñado para tu nivel, tu cuerpo y tus objetivos. Gym o casa &mdash; tú eliges.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-utensils"></i></div>
                <h3>Guía de Nutrición</h3>
                <p>Cómo alimentarte correctamente durante el reto. Principios claros, sin dietas extremas.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-calendar-check"></i></div>
                <h3>Guía de Hábitos</h3>
                <p>Sistema de hábitos diarios para garantizar consistencia. Pequeños cambios, gran impacto.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3>Seguimiento del Reto</h3>
                <p>Revisión de tu progreso durante los 30 días. No estarás solo en este proceso.</p>
            </div>
        </div>
    </section>
```

**Step 5: Limpiar sección de pricing — quitar USD, mostrar solo COP**

Encontrar:
```html
            <div class="price-display">
                <div class="price-amount">$33</div>
                <div class="price-currency">USD / $99,900 COP</div>
            </div>
```

Reemplazar con:
```html
            <div class="price-display">
                <div class="price-amount">$99.900</div>
                <div class="price-currency">COP &middot; Pago único &middot; 30 días</div>
            </div>
```

**Step 6: Limpiar info-grid — de 6 a 4 preguntas**

Encontrar toda la sección `<!-- Info Section -->`:
```html
    <!-- Info Section -->
    <section class="container">
        <div class="info-grid">
            <div class="info-item">
                <h3>¿Quién puede apuntarse?</h3>
                <p>Todos los niveles. Principiantes, intermedios o avanzados. El programa se adapta a ti.</p>
            </div>

            <div class="info-item">
                <h3>¿Dónde entreno?</h3>
                <p>Gym, casa o ambos. Personalizado según tu equipamiento y disponibilidad.</p>
            </div>

            <div class="info-item">
                <h3>¿Qué pasa después?</h3>
                <p>Al completar RISE, acceso a planes continuos. Seguimiento permanente de tu progreso.</p>
            </div>

            <div class="info-item">
                <h3>¿Necesito equipamiento?</h3>
                <p>No obligatorio. Opciones para gym completo, mancuernas, bandas, o solo peso corporal.</p>
            </div>

            <div class="info-item">
                <h3>¿Hay soporte?</h3>
                <p>Comunidad activa, guías detalladas, y actualizaciones de programa según tu feedback.</p>
            </div>

            <div class="info-item">
                <h3>¿Es realmente personalizado?</h3>
                <p>Sí. Intake detallado captura tus medidas, objetivos, equipamiento, horarios y limitaciones.</p>
            </div>
        </div>
    </section>
```

Reemplazar con:
```html
    <!-- Info Section -->
    <section class="container">
        <div style="text-align:center;margin-bottom:48px;">
            <div style="font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:4px;color:var(--red);text-transform:uppercase;margin-bottom:12px;">// PREGUNTAS FRECUENTES</div>
            <h2 style="font-family:'Bebas Neue',sans-serif;font-size:40px;letter-spacing:0.02em;">LO QUE NECESITAS SABER.</h2>
        </div>
        <div class="info-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
            <div class="info-item">
                <h3><i class="fas fa-user-check" style="color:var(--red);margin-right:8px;"></i>¿Quién puede apuntarse?</h3>
                <p>Todos los niveles. El programa se adapta a ti.</p>
            </div>

            <div class="info-item">
                <h3><i class="fas fa-map-marker-alt" style="color:var(--red);margin-right:8px;"></i>¿Dónde entreno?</h3>
                <p>Gym, casa o ambos. Sin equipamiento obligatorio.</p>
            </div>

            <div class="info-item">
                <h3><i class="fas fa-fingerprint" style="color:var(--red);margin-right:8px;"></i>¿Es personalizado de verdad?</h3>
                <p>Sí. Tu intake captura medidas, objetivos, horarios y limitaciones.</p>
            </div>

            <div class="info-item">
                <h3><i class="fas fa-arrow-right" style="color:var(--red);margin-right:8px;"></i>¿Qué pasa después?</h3>
                <p>Al completar RISE, accedes a nuestros planes mensuales 1:1.</p>
            </div>
        </div>
    </section>
```

**Step 7: Agregar segundos al countdown JS**

Encontrar:
```javascript
        function updateCountdown() {
            const now = new Date();
            const end = new Date(now.getFullYear(), 2, 31, 23, 59, 59);

            if (now > end) {
                document.getElementById('days').textContent = '0';
                document.getElementById('hours').textContent = '0';
                document.getElementById('minutes').textContent = '0';
                return;
            }

            const diff = end - now;
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

            document.getElementById('days').textContent = days;
            document.getElementById('hours').textContent = hours;
            document.getElementById('minutes').textContent = minutes;
        }

        updateCountdown();
        setInterval(updateCountdown, 60000);
```

Reemplazar con:
```javascript
        function updateCountdown() {
            const now = new Date();
            const end = new Date('2026-03-31T23:59:59');

            if (now > end) {
                ['days','hours','minutes','seconds'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = '00';
                });
                return;
            }

            const diff = end - now;
            document.getElementById('days').textContent = Math.floor(diff / 86400000);
            document.getElementById('hours').textContent = String(Math.floor((diff % 86400000) / 3600000)).padStart(2,'0');
            document.getElementById('minutes').textContent = String(Math.floor((diff % 3600000) / 60000)).padStart(2,'0');
            const secEl = document.getElementById('seconds');
            if (secEl) secEl.textContent = String(Math.floor((diff % 60000) / 1000)).padStart(2,'0');
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
```

También agregar el elemento seconds al countdown HTML. Encontrar:
```html
                    <div class="countdown-item">
                        <div class="countdown-value" id="minutes">0</div>
                        <div class="countdown-unit">Minutos</div>
                    </div>
                </div>
            </div>
```

Reemplazar con:
```html
                    <div class="countdown-item">
                        <div class="countdown-value" id="minutes">0</div>
                        <div class="countdown-unit">Minutos</div>
                    </div>
                    <div class="countdown-item">
                        <div class="countdown-value" id="seconds">0</div>
                        <div class="countdown-unit">Segundos</div>
                    </div>
                </div>
            </div>
```

Y actualizar el CSS del countdown para 4 columnas:
```css
        .countdown-timer {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }
```

**Step 8: Verificar**

```bash
grep -c "fas fa-" C:/Users/GODSF/Herd/wellcorefitness/rise.html
```
Expected: al menos 8 (4 feature icons + 1 hero + 4 info icons)

```bash
grep -c "feature-card" C:/Users/GODSF/Herd/wellcorefitness/rise.html
```
Expected: 8 (4 cards × 2 referencias cada una)

**Step 9: Commit**

```bash
cd C:/Users/GODSF/Herd/wellcorefitness && git add rise.html && git commit -m "feat: FA icons + content audit on rise.html — 4 real features, clean content"
```

---

## Task 2: planes.html — FA Icons en plans y trust strip

**Files:**
- Modify: `planes.html` (1698 líneas)

**Contexto:** planes.html usa `&#10003;` (67 veces), `&#9733;` (47 veces), `&#10005;` (5 veces). Ya tiene `wellcore-v5.css`. Los trust-item en hero usan `&#10003;` inline con color cyan.

**Step 1: Agregar FA CDN**

Encontrar en `<head>`:
```html
  <!-- v5 Design System -->
  <link rel="stylesheet" href="css/wellcore-v5.css">
```

Reemplazar con:
```html
  <!-- Font Awesome 6.4 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- v5 Design System -->
  <link rel="stylesheet" href="css/wellcore-v5.css">
```

**Step 2: Reemplazar trust-strip checkmarks (4 items en el hero)**

Encontrar:
```html
          <div class="trust-item"><span style="color:#00D9FF;font-weight:700;">&#10003;</span> Pago seguro Wompi</div>
          <div class="trust-item"><span style="color:#00D9FF;font-weight:700;">&#10003;</span> Sin contrato</div>
          <div class="trust-item"><span style="color:#00D9FF;font-weight:700;">&#10003;</span> Entrega en 48h</div>
          <div class="trust-item"><span style="color:#00D9FF;font-weight:700;">&#10003;</span> Soporte real</div>
```

Reemplazar con:
```html
          <div class="trust-item"><i class="fas fa-lock" style="color:#00D9FF;"></i> Pago seguro Wompi</div>
          <div class="trust-item"><i class="fas fa-times-circle" style="color:#00D9FF;"></i> Sin contrato</div>
          <div class="trust-item"><i class="fas fa-bolt" style="color:#00D9FF;"></i> Entrega en 48h</div>
          <div class="trust-item"><i class="fas fa-headset" style="color:#00D9FF;"></i> Soporte real</div>
```

**Step 3: Reemplazar `pc-check` (&#10003;) en listas de features de todos los planes**

Usar replace_all: buscar `<span class="pc-check">&#10003;</span>` y reemplazar con `<i class="fas fa-check pc-check"></i>`.

Esto afecta las ~22 líneas de features. Ejecutar:
```bash
sed -i 's/<span class="pc-check">&#10003;<\/span>/<i class="fas fa-check pc-check"><\/i>/g' C:/Users/GODSF/Herd/wellcorefitness/planes.html
```

**Step 4: Reemplazar `pc-x` (&#10005;) en features no incluidos**

```bash
sed -i 's/<span class="pc-x">&#10005;<\/span>/<i class="fas fa-times pc-x"><\/i>/g' C:/Users/GODSF/Herd/wellcorefitness/planes.html
```

**Step 5: Reemplazar estrella popular bar**

Encontrar:
```html
          <div class="pc-popular-bar">&#9733; El plan mas elegido por nuestros clientes</div>
```

Reemplazar con:
```html
          <div class="pc-popular-bar"><i class="fas fa-star"></i> El plan más elegido por nuestros clientes</div>
```

**Step 6: Reemplazar social-proof checkmark en plan Método**

Encontrar:
```html
            <span style="color:#00D9FF;font-weight:700;">&#10003;</span>
            <span>Elegido por el <strong>+60%</strong> de nuestros clientes activos</span>
```

Reemplazar con:
```html
            <i class="fas fa-users" style="color:#00D9FF;"></i>
            <span>Elegido por el <strong>+60%</strong> de nuestros clientes activos</span>
```

**Step 7: Agregar iconos a nombres de planes (plan headers)**

Encontrar:
```html
          <div class="pc-plan">PLAN ESENCIAL</div>
          <div class="pc-name">ESENCIAL</div>
```

Reemplazar con:
```html
          <div class="pc-plan"><i class="fas fa-seedling" style="color:var(--green,#22C55E);margin-right:6px;"></i>PLAN ESENCIAL</div>
          <div class="pc-name">ESENCIAL</div>
```

Encontrar:
```html
          <div class="pc-plan">PLAN METODO</div>
          <div class="pc-name">METODO</div>
```

Reemplazar con:
```html
          <div class="pc-plan"><i class="fas fa-chart-line" style="color:#E31E24;margin-right:6px;"></i>PLAN METODO</div>
          <div class="pc-name">METODO</div>
```

Encontrar:
```html
          <div class="pc-plan">PLAN ELITE</div>
          <div class="pc-name">ELITE</div>
```

Reemplazar con:
```html
          <div class="pc-plan"><i class="fas fa-crown" style="color:#FFD700;margin-right:6px;"></i>PLAN ELITE</div>
          <div class="pc-name">ELITE</div>
```

**Step 8: Verificar**

```bash
grep -c "fas fa-" C:/Users/GODSF/Herd/wellcorefitness/planes.html
```
Expected: al menos 30 (22 checks + 5 times + trust + popular + social + plan icons)

```bash
grep -c "&#10003;" C:/Users/GODSF/Herd/wellcorefitness/planes.html
```
Expected: 0 (todos reemplazados)

**Step 9: Commit**

```bash
cd C:/Users/GODSF/Herd/wellcorefitness && git add planes.html && git commit -m "feat: replace unicode icons with FA in planes.html — checks, stars, plan icons"
```

---

## Task 3: index.html — FA CDN + Trust Checkmarks

**Files:**
- Modify: `index.html` (3813 líneas)

**Contexto:** index.html usa `&#10003;` en dos lugares: `cta-trust-item` (3 refs al final) y trust section. No tiene FA. Ya tiene la sección RISE implementada.

**Step 1: Agregar FA CDN en `<head>`**

Encontrar en `<head>`:
```html
  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
```

Reemplazar con:
```html
  <!-- Font Awesome 6.4 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
```

**Step 2: Reemplazar trust items del CTA final**

Encontrar:
```html
        <div class="cta-trust-item"><span style="color:#00D9FF;font-weight:700;">&#10003;</span> Seguimiento 1-1 real</div>
        <div class="cta-trust-item"><span style="color:#00D9FF;font-weight:700;">&#10003;</span> Sin contratos forzados</div>
        <div class="cta-trust-item"><span style="color:#00D9FF;font-weight:700;">&#10003;</span> Fase beta &mdash; acceso fundador</div>
```

Reemplazar con:
```html
        <div class="cta-trust-item"><i class="fas fa-user-check" style="color:#00D9FF;"></i> Seguimiento 1-1 real</div>
        <div class="cta-trust-item"><i class="fas fa-times-circle" style="color:#00D9FF;"></i> Sin contratos forzados</div>
        <div class="cta-trust-item"><i class="fas fa-rocket" style="color:#00D9FF;"></i> Fase beta &mdash; acceso fundador</div>
```

**Step 3: Agregar icono al hero-badge**

Encontrar:
```html
    <div class="hero-badge">
      <div class="badge-dot"></div>
      BETA &middot; ACCESO FUNDADOR
    </div>
```

Reemplazar con:
```html
    <div class="hero-badge">
      <div class="badge-dot"></div>
      <i class="fas fa-shield-alt" style="font-size:9px;margin-right:4px;"></i>BETA &middot; ACCESO FUNDADOR
    </div>
```

**Step 4: Agregar iconos a hero-stats**

Encontrar:
```html
      <div class="hero-stats">
        <div class="hero-stat">
          <span class="hero-stat-num">1:1</span>
          <span class="hero-stat-label">Atenci&oacute;n Directa</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num">SEMANAL</span>
          <span class="hero-stat-label">Ajuste de Plan</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num red">BETA</span>
          <span class="hero-stat-label">Acceso Fundador</span>
        </div>
      </div>
```

Reemplazar con:
```html
      <div class="hero-stats">
        <div class="hero-stat">
          <span class="hero-stat-num">1:1</span>
          <span class="hero-stat-label"><i class="fas fa-comments" style="color:var(--red);margin-right:4px;font-size:9px;"></i>Atenci&oacute;n Directa</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num">SEMANAL</span>
          <span class="hero-stat-label"><i class="fas fa-sync-alt" style="color:var(--red);margin-right:4px;font-size:9px;"></i>Ajuste de Plan</span>
        </div>
        <div class="hero-stat">
          <span class="hero-stat-num red">BETA</span>
          <span class="hero-stat-label"><i class="fas fa-rocket" style="color:var(--red);margin-right:4px;font-size:9px;"></i>Acceso Fundador</span>
        </div>
      </div>
```

**Step 5: Verificar**

```bash
grep -c "fas fa-" C:/Users/GODSF/Herd/wellcorefitness/index.html
```
Expected: al menos 15 (RISE section ya tenía algunos + los nuevos)

```bash
grep -c "&#10003;" C:/Users/GODSF/Herd/wellcorefitness/index.html
```
Expected: 0

**Step 6: Commit**

```bash
cd C:/Users/GODSF/Herd/wellcorefitness && git add index.html && git commit -m "feat: add FA CDN and replace unicode icons in index.html"
```

---

## Task 4: inscripcion.html — Activar FA + Form Icons

**Files:**
- Modify: `inscripcion.html` (1108 líneas)

**Contexto:** inscripcion.html tiene `<!-- Font Awesome 6 -->` comentado (placeholder vacío) en el head. Tiene `wellcore-v5.css`. El archivo tiene un form de inscripción multi-paso.

**Step 1: Activar FA CDN (reemplazar el comentario placeholder)**

Encontrar:
```html
  <!-- Font Awesome 6 -->
```

Reemplazar con:
```html
  <!-- Font Awesome 6.4 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
```

**Step 2: Leer el formulario para identificar campos con label**

Ejecutar para entender la estructura del form:
```bash
grep -n "<label\|<input\|<select\|step\|&#10003;\|&#9670;" C:/Users/GODSF/Herd/wellcorefitness/inscripcion.html | head -40
```

Con base en el output, agregar iconos `<i class="fas fa-*">` a los labels/steps del form. Los iconos estándar para campos:
- Nombre: `fa-user`
- Email: `fa-envelope`
- Teléfono: `fa-phone`
- Plan: `fa-tag`
- Objetivo: `fa-bullseye`
- Nivel: `fa-signal`

**Step 3: Reemplazar cualquier `&#10003;` existente**

```bash
grep -c "&#10003;\|&#9670;\|&#9733;" C:/Users/GODSF/Herd/wellcorefitness/inscripcion.html
```

Si hay ocurrencias, reemplazarlas con FA equivalente:
- `&#10003;` → `<i class="fas fa-check"></i>`
- `&#9670;` → `<i class="fas fa-chevron-right"></i>`

**Step 4: Verificar FA activo**

```bash
grep -c "font-awesome/6.4.0" C:/Users/GODSF/Herd/wellcorefitness/inscripcion.html
```
Expected: 1

**Step 5: Commit**

```bash
cd C:/Users/GODSF/Herd/wellcorefitness && git add inscripcion.html && git commit -m "feat: activate FA CDN and add form field icons in inscripcion.html"
```
