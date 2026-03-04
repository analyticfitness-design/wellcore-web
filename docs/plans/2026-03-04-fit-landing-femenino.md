# WellCore Femenino — fit.html + Identidad Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Construir `fit.html` — landing femenino de WellCore con Silvia como imagen, Rose Glow, y módulo de identidad de marca femenina en `E:\00_WELLCORE_FITNESS_ENTERPRISE\02_Identidad_Corporativa\BRANDING DE MUJER`.

**Architecture:** HTML estático standalone con CSS inline (igual que index.html). Reutiliza CDNs existentes (Bootstrap, AOS, Font Awesome, Google Fonts). Fotos de Silvia desde `images/silvia/`. El módulo de identidad es HTML visual interactivo + Markdown de protocolo, guardados en carpeta corporativa externa.

**Tech Stack:** HTML5, CSS3 (variables + radial-gradient rose glow), Bootstrap 5.3, Font Awesome 6.4, AOS, Bebas Neue + Inter (Google Fonts), imágenes PNG de Silvia con fondo recortado.

**Rose Glow Palette:**
- `--rose: #DC3C64` — acento rose/magenta (solo en glow, nunca en texto ni botones)
- `--red: #E31E24` — rojo WellCore original (CTAs, texto destacado, borders)
- Rose glow: `radial-gradient(ellipse 55% 90% at 75% 60%, rgba(220,60,100,0.28) 0%, rgba(227,30,36,0.12) 40%, transparent 75%)`
- Silvia filter: `drop-shadow(-30px 0 60px rgba(220,60,100,0.4))`

**Fotos de Silvia disponibles en `images/silvia/`:**
- `IMG_2470.JPG-3.png` — de espaldas, body shorts marrón (HERO principal)
- `IMG_2471.JPG4.png` — de frente, body shorts marrón, fondo azul (sección Silvia)
- `IMG_0815-2.JPG3.png` — Under Armour bra, fondo oscuro con destellos naranjas
- `IMG_2370.JPG2.png` — leggings negros, vista frontal
- `IMG_0815-2.JPG2.png` — variante Under Armour
- `IMG_2470.JPG-4.png`, `IMG_2470.JPG-5.png` — variantes body shorts
- `IMG_2471.JPG2.png` — otra variante frontal

---

## Task 1: Hero + Estructura Base de fit.html

**Files:**
- Create: `fit.html`

**Contexto:** El hero usa la misma base que index.html pero con Silvia full-height a la derecha, Rose Glow (radial gradient), y copy femenino. La foto `IMG_2470.JPG-3.png` (Silvia de espaldas mirando a cámara) es la imagen del hero.

**Paso 1: Crear fit.html con head, CSS base y navbar**

El head debe incluir exactamente los mismos CDNs que index.html:
```html
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>WellCore Fitness — Coaching Online para Mujeres | Silvia</title>
  <meta name="description" content="Coaching online 1:1 para mujeres. Entrenamiento y nutrición basados en ciencia, diseñados para tu cuerpo. Con Silvia — WellCore Fitness."/>
  <!-- NO indexar este landing: es para redes sociales -->
  <meta name="robots" content="noindex, nofollow"/>

  <!-- Font Awesome 6.4 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
  <!-- AOS -->
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css"/>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>

  <style>
    :root {
      --bg:       #0a0a0a;
      --surface:  #111113;
      --card:     #18181b;
      --border:   rgba(255,255,255,0.06);
      --red:      #E31E24;
      --red-dim:  rgba(227,30,36,0.12);
      --rose:     #DC3C64;
      --rose-dim: rgba(220,60,100,0.12);
      --white:    #ffffff;
      --gray:     rgba(255,255,255,0.45);
      --font-head:'Bebas Neue', sans-serif;
      --font-body:'Inter', sans-serif;
    }
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    html { scroll-behavior:smooth; }
    body { background:var(--bg); color:var(--white); font-family:var(--font-body); overflow-x:hidden; }

    /* NAVBAR — idéntica al estándar WellCore */
    /* [copiar navbar exacta de index.html incluyendo RISE link con pulse dot] */
  </style>
</head>
```

**Paso 2: Agregar la sección hero**

```html
<!-- HERO -->
<section id="hero-fit" style="position:relative;min-height:100vh;background:var(--bg);display:flex;align-items:center;overflow:hidden;">

  <!-- Rose Glow overlay -->
  <div style="position:absolute;inset:0;background:radial-gradient(ellipse 55% 90% at 75% 60%, rgba(220,60,100,0.28) 0%, rgba(227,30,36,0.12) 40%, transparent 75%);pointer-events:none;z-index:1;"></div>

  <!-- Silvia photo -->
  <img src="images/silvia/IMG_2470.JPG-3.png" alt="Silvia — WellCore Fitness"
    style="position:absolute;right:0;bottom:0;height:100%;width:auto;object-fit:contain;object-position:bottom right;filter:drop-shadow(-30px 0 60px rgba(220,60,100,0.4));z-index:2;">

  <!-- Content -->
  <div style="position:relative;z-index:3;padding:80px 64px;max-width:560px;">
    <div style="font-size:10px;letter-spacing:4px;text-transform:uppercase;color:var(--red);display:flex;align-items:center;gap:10px;margin-bottom:20px;">
      <span style="width:28px;height:1px;background:var(--red);display:inline-block;"></span>
      WellCore Fitness · Para Ellas
    </div>
    <h1 style="font-family:var(--font-head);font-size:clamp(64px,9vw,108px);line-height:0.92;color:var(--white);letter-spacing:1px;margin-bottom:24px;">
      SIN<br>DOGMAS.<br>SIN<br><span style="color:var(--red);">ATAJOS.</span>
    </h1>
    <p style="font-size:16px;color:var(--gray);line-height:1.75;margin-bottom:36px;max-width:400px;">
      Coaching 1:1 diseñado para la mujer que quiere resultados reales. Sin dietas extremas, sin entrenamientos genéricos. Solo ciencia aplicada a tu cuerpo.
    </p>
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
      <a href="inscripcion.html" style="display:inline-block;background:var(--red);color:#fff;font-weight:700;font-size:11px;letter-spacing:3px;text-transform:uppercase;padding:16px 40px;text-decoration:none;">
        EMPEZAR MI PROCESO →
      </a>
      <a href="planes.html" style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:var(--gray);text-decoration:none;border-bottom:1px solid rgba(255,255,255,0.2);padding-bottom:2px;">
        Ver planes
      </a>
    </div>
    <!-- Stats -->
    <div style="display:flex;gap:40px;margin-top:56px;padding-top:40px;border-top:1px solid rgba(255,255,255,0.07);">
      <div>
        <div style="font-family:var(--font-head);font-size:36px;color:var(--red);line-height:1;">200+</div>
        <div style="font-size:9px;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.3);margin-top:4px;">Mujeres transformadas</div>
      </div>
      <div>
        <div style="font-family:var(--font-head);font-size:36px;color:var(--red);line-height:1;">1:1</div>
        <div style="font-size:9px;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.3);margin-top:4px;">Atención personalizada</div>
      </div>
      <div>
        <div style="font-family:var(--font-head);font-size:36px;color:var(--red);line-height:1;">100%</div>
        <div style="font-size:9px;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.3);margin-top:4px;">Basado en ciencia</div>
      </div>
    </div>
  </div>
</section>
```

**Paso 3: Verificar visualmente**
Navegar a `https://wellcorefitness.test/fit.html`
Verificar: Silvia visible a la derecha, glow rosa activo, texto legible, CTA rojo

**Paso 4: Commit**
```bash
git add fit.html images/silvia/
git commit -m "feat: fit.html hero section - female landing with Silvia + rose glow"
```

---

## Task 2: Sección "Silvia te acompaña" (Coach Intro)

**Files:**
- Modify: `fit.html` (agregar después del hero)

**Contexto:** Sección de 2 columnas. Izquierda: foto `IMG_2471.JPG4.png` (Silvia de frente, cuerpo completo). Derecha: texto personal de coach. El tono es íntimo y empático — *"yo también estuve donde estás"*.

**Código a agregar:**
```html
<!-- SILVIA SECTION -->
<section style="padding:100px 0;background:var(--surface);position:relative;overflow:hidden;">
  <!-- subtle rose accent line top -->
  <div style="position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(220,60,100,0.4),transparent);"></div>

  <div class="container">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:80px;align-items:center;" class="row-silvia">

      <!-- Foto -->
      <div style="position:relative;">
        <div style="position:absolute;inset:-20px;background:radial-gradient(ellipse at center, rgba(220,60,100,0.12) 0%, transparent 70%);pointer-events:none;"></div>
        <img src="images/silvia/IMG_2471.JPG4.png" alt="Silvia — Coach WellCore"
          style="width:100%;max-width:420px;display:block;margin:0 auto;filter:drop-shadow(0 20px 60px rgba(220,60,100,0.25));">
      </div>

      <!-- Texto -->
      <div>
        <div style="font-size:9px;letter-spacing:4px;text-transform:uppercase;color:var(--rose);margin-bottom:16px;">Tu coach</div>
        <h2 style="font-family:var(--font-head);font-size:clamp(48px,6vw,72px);line-height:0.95;color:var(--white);margin-bottom:28px;">
          YO TAMBIÉN<br>ESTUVE<br><span style="color:var(--red);">AHÍ.</span>
        </h2>
        <p style="font-size:16px;color:var(--gray);line-height:1.8;margin-bottom:20px;">
          Sé lo que es buscar resultados y no ver cambios. Probar todo y sentir que tu cuerpo no responde. La diferencia no eres tú — es el método.
        </p>
        <p style="font-size:16px;color:var(--gray);line-height:1.8;margin-bottom:32px;">
          Como coach certificada de WellCore, diseño programas que respetan cómo funciona el cuerpo femenino de verdad: tu ciclo, tus hormonas, tu estilo de vida.
        </p>
        <!-- Credencial chip -->
        <div style="display:inline-flex;align-items:center;gap:10px;border:1px solid rgba(220,60,100,0.3);padding:10px 20px;background:rgba(220,60,100,0.06);">
          <i class="fas fa-certificate" style="color:var(--rose);font-size:14px;"></i>
          <span style="font-size:10px;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,0.6);">Coach Certificada WellCore</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Mobile responsive -->
  <style>
    @media(max-width:768px){
      .row-silvia { grid-template-columns:1fr !important; gap:40px !important; }
    }
  </style>
</section>
```

**Verificar:** Foto de Silvia de frente visible, texto legible, chip de credencial con border rosa

**Commit:**
```bash
git add fit.html
git commit -m "feat: fit.html Silvia coach intro section"
```

---

## Task 3: Sección "Para Ti, Específicamente" (4 Benefit Cards)

**Files:**
- Modify: `fit.html`

**Contexto:** 4 tarjetas en grid 2x2 con objetivos femeninos específicos. Cada card tiene icono FA, título en Bebas Neue, y descripción corta. El borde activo es rose/rojo.

**Código:**
```html
<!-- PARA TI SECTION -->
<section style="padding:100px 0;background:var(--bg);" data-aos="fade-up">
  <div class="container">

    <!-- Header -->
    <div style="text-align:center;margin-bottom:64px;">
      <div style="font-size:9px;letter-spacing:4px;text-transform:uppercase;color:var(--rose);margin-bottom:12px;">Diseñado para ti</div>
      <h2 style="font-family:var(--font-head);font-size:clamp(48px,7vw,80px);line-height:0.95;color:var(--white);">
        TU CUERPO<br><span style="color:var(--red);">TIENE REGLAS.</span>
      </h2>
      <p style="font-size:15px;color:var(--gray);margin-top:16px;max-width:480px;margin-left:auto;margin-right:auto;line-height:1.7;">
        Tu programa considera lo que los planes genéricos ignoran.
      </p>
    </div>

    <!-- Grid 2x2 -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px;" class="benefits-grid">

      <!-- Card 1 -->
      <div style="background:var(--surface);padding:48px 40px;border-left:2px solid var(--rose);position:relative;overflow:hidden;">
        <div style="position:absolute;top:0;right:0;width:120px;height:120px;background:radial-gradient(circle,rgba(220,60,100,0.08),transparent);pointer-events:none;"></div>
        <i class="fas fa-person-dress" style="font-size:28px;color:var(--rose);margin-bottom:20px;display:block;"></i>
        <h3 style="font-family:var(--font-head);font-size:32px;color:var(--white);letter-spacing:1px;margin-bottom:12px;">GLÚTEOS Y CURVAS</h3>
        <p style="font-size:14px;color:var(--gray);line-height:1.7;">Hipertrofia orientada a la forma femenina. Rutinas que construyen donde quieres construir.</p>
      </div>

      <!-- Card 2 -->
      <div style="background:var(--surface);padding:48px 40px;border-left:2px solid rgba(255,255,255,0.06);position:relative;overflow:hidden;">
        <div style="position:absolute;top:0;right:0;width:120px;height:120px;background:radial-gradient(circle,rgba(227,30,36,0.06),transparent);pointer-events:none;"></div>
        <i class="fas fa-fire-flame-curved" style="font-size:28px;color:var(--red);margin-bottom:20px;display:block;"></i>
        <h3 style="font-family:var(--font-head);font-size:32px;color:var(--white);letter-spacing:1px;margin-bottom:12px;">DEFINICIÓN SIN PERDER CURVAS</h3>
        <p style="font-size:14px;color:var(--gray);line-height:1.7;">Déficit calculado para quemar grasa preservando el músculo que construiste. Sin "adelgazar todo".</p>
      </div>

      <!-- Card 3 -->
      <div style="background:var(--surface);padding:48px 40px;border-left:2px solid rgba(255,255,255,0.06);position:relative;overflow:hidden;">
        <i class="fas fa-apple-whole" style="font-size:28px;color:var(--red);margin-bottom:20px;display:block;"></i>
        <h3 style="font-family:var(--font-head);font-size:32px;color:var(--white);letter-spacing:1px;margin-bottom:12px;">NUTRICIÓN SIN OBSESIÓN</h3>
        <p style="font-size:14px;color:var(--gray);line-height:1.7;">Aprendes a comer para rendir y verte bien. Sin contar cada caloría. Sin eliminar grupos de alimentos.</p>
      </div>

      <!-- Card 4 -->
      <div style="background:var(--surface);padding:48px 40px;border-left:2px solid rgba(220,60,100,0.2);position:relative;overflow:hidden;">
        <div style="position:absolute;top:0;right:0;width:120px;height:120px;background:radial-gradient(circle,rgba(220,60,100,0.06),transparent);pointer-events:none;"></div>
        <i class="fas fa-circle-nodes" style="font-size:28px;color:var(--rose);margin-bottom:20px;display:block;"></i>
        <h3 style="font-family:var(--font-head);font-size:32px;color:var(--white);letter-spacing:1px;margin-bottom:12px;">RESPETA TU CICLO</h3>
        <p style="font-size:14px;color:var(--gray);line-height:1.7;">Tu entrenamiento y nutrición se adaptan a las fases de tu ciclo menstrual. Ciencia, no suposiciones.</p>
      </div>
    </div>

    <style>
      @media(max-width:768px){
        .benefits-grid { grid-template-columns:1fr !important; }
      }
    </style>
  </div>
</section>
```

**Commit:**
```bash
git add fit.html
git commit -m "feat: fit.html benefit cards section - female-specific goals"
```

---

## Task 4: Sección Planes (Copy Femenino)

**Files:**
- Modify: `fit.html`

**Contexto:** Mismos 3 planes (Esencial $95, Método $120, Elite $150) que el resto del sitio. Copy del header reescrito para mujer. Las cards son las mismas de index.html pero con sub-headline diferente. Enlaza a `planes.html` y `inscripcion.html`.

**Header de la sección:**
```html
<section style="padding:100px 0;background:var(--surface);">
  <div class="container">
    <div style="text-align:center;margin-bottom:64px;">
      <div style="font-size:9px;letter-spacing:4px;text-transform:uppercase;color:var(--rose);margin-bottom:12px;">Inversión en ti</div>
      <h2 style="font-family:var(--font-head);font-size:clamp(48px,7vw,80px);line-height:0.95;color:var(--white);">
        ELIGE TU<br><span style="color:var(--red);">NIVEL DE ACOMPAÑAMIENTO.</span>
      </h2>
      <p style="font-size:15px;color:var(--gray);margin-top:16px;max-width:500px;margin-left:auto;margin-right:auto;line-height:1.7;">
        Cada plan incluye entrenamiento + nutrición + acompañamiento 1:1. Diseñado para el cuerpo femenino.
      </p>
    </div>
    <!-- 3 plan cards: Esencial $95 | Método $120 (destacado) | Elite $150 -->
    <!-- Copiar cards de planes.html pero con CTA -> inscripcion.html -->
  </div>
</section>
```

**Las 3 cards de planes** (simplificadas para este landing):
- **ESENCIAL** — $95/mes — Entrenamiento · Nutrición · Check-in semanal
- **MÉTODO** *(Más elegido)* — $120/mes — Todo lo anterior + Videollamada mensual + Plan completo
- **ELITE** — $150/mes — Todo + Acompañamiento diario + Ajustes en tiempo real

Cada card tiene: precio, 4-5 features con `fa-check`, botón rojo → `inscripcion.html?plan=X`

**Commit:**
```bash
git add fit.html
git commit -m "feat: fit.html plans section with feminine copy"
```

---

## Task 5: Sección Testimonios Femeninos

**Files:**
- Modify: `fit.html`

**Contexto:** 3 testimonios de mujeres en cards oscuras. Nombres ficticios de ejemplo (el cliente los reemplazará con reales). Estructura: foto avatar placeholder (iniciales), nombre, resultado cuantificado, cita.

```html
<!-- TESTIMONIOS -->
<section style="padding:100px 0;background:var(--bg);" data-aos="fade-up">
  <div class="container">
    <div style="text-align:center;margin-bottom:64px;">
      <div style="font-size:9px;letter-spacing:4px;text-transform:uppercase;color:var(--rose);margin-bottom:12px;">Resultados reales</div>
      <h2 style="font-family:var(--font-head);font-size:clamp(48px,7vw,80px);line-height:0.95;color:var(--white);">
        ELLAS YA<br><span style="color:var(--red);">EMPEZARON.</span>
      </h2>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;" class="testimonials-grid">

      <!-- Testimonio 1 -->
      <div style="background:var(--surface);padding:36px;border:1px solid var(--border);position:relative;">
        <div style="position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--rose),transparent);"></div>
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:24px;">
          <div style="width:48px;height:48px;background:rgba(220,60,100,0.15);border:1px solid rgba(220,60,100,0.3);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:20px;color:var(--rose);">V</div>
          <div>
            <div style="font-weight:600;font-size:14px;">Valentina R.</div>
            <div style="font-size:11px;color:var(--rose);letter-spacing:1px;">–8 kg · 4 meses</div>
          </div>
        </div>
        <p style="font-size:14px;color:var(--gray);line-height:1.8;font-style:italic;">"Por primera vez entendí cómo comer sin restricción y seguir perdiendo grasa. El plan respeta mi ciclo y eso cambió todo."</p>
      </div>

      <!-- Testimonio 2 -->
      <div style="background:var(--surface);padding:36px;border:1px solid var(--border);position:relative;">
        <div style="position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--red),transparent);"></div>
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:24px;">
          <div style="width:48px;height:48px;background:rgba(227,30,36,0.1);border:1px solid rgba(227,30,36,0.2);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:20px;color:var(--red);">C</div>
          <div>
            <div style="font-weight:600;font-size:14px;">Camila T.</div>
            <div style="font-size:11px;color:var(--red);letter-spacing:1px;">+4 kg músculo · 6 meses</div>
          </div>
        </div>
        <p style="font-size:14px;color:var(--gray);line-height:1.8;font-style:italic;">"Quería glúteos y curvas reales, no solo perder peso. Silvia diseñó exactamente eso. Los resultados hablan solos."</p>
      </div>

      <!-- Testimonio 3 -->
      <div style="background:var(--surface);padding:36px;border:1px solid var(--border);position:relative;">
        <div style="position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--rose),transparent);"></div>
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:24px;">
          <div style="width:48px;height:48px;background:rgba(220,60,100,0.15);border:1px solid rgba(220,60,100,0.3);display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:20px;color:var(--rose);">S</div>
          <div>
            <div style="font-weight:600;font-size:14px;">Sara M.</div>
            <div style="font-size:11px;color:var(--rose);letter-spacing:1px;">Tonificación completa · 3 meses</div>
          </div>
        </div>
        <p style="font-size:14px;color:var(--gray);line-height:1.8;font-style:italic;">"Había probado de todo. La diferencia es que aquí te explican el por qué de cada decisión. Ya no me pierdo en redes."</p>
      </div>
    </div>

    <style>
      @media(max-width:768px){
        .testimonials-grid { grid-template-columns:1fr !important; }
      }
    </style>
  </div>
</section>
```

**Commit:**
```bash
git add fit.html
git commit -m "feat: fit.html female testimonials section"
```

---

## Task 6: CTA Final + Footer

**Files:**
- Modify: `fit.html` (cerrar página)

**Contexto:** CTA final con foto de Silvia de fondo (blur + overlay oscuro), headline grande, botón rojo. Luego footer simple con links de navegación.

```html
<!-- CTA FINAL -->
<section style="padding:120px 0;background:var(--surface);position:relative;overflow:hidden;text-align:center;">
  <!-- Rose glow background -->
  <div style="position:absolute;inset:0;background:radial-gradient(ellipse 70% 80% at 50% 50%, rgba(220,60,100,0.10) 0%, transparent 65%);pointer-events:none;"></div>

  <div class="container" style="position:relative;z-index:2;">
    <div style="font-size:9px;letter-spacing:4px;text-transform:uppercase;color:var(--rose);margin-bottom:16px;">¿Lista?</div>
    <h2 style="font-family:var(--font-head);font-size:clamp(56px,10vw,120px);line-height:0.9;color:var(--white);margin-bottom:24px;">
      TU CUERPO.<br>TU PROCESO.<br><span style="color:var(--red);">TU MOMENTO.</span>
    </h2>
    <p style="font-size:16px;color:var(--gray);max-width:440px;margin:0 auto 40px;line-height:1.7;">
      El primer paso es el más difícil. El resto lo hacemos juntas.
    </p>
    <a href="inscripcion.html" style="display:inline-block;background:var(--red);color:#fff;font-weight:700;font-size:12px;letter-spacing:3px;text-transform:uppercase;padding:18px 56px;text-decoration:none;">
      EMPEZAR AHORA →
    </a>
    <div style="margin-top:20px;">
      <a href="planes.html" style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.3);text-decoration:none;">
        Ver todos los planes
      </a>
    </div>
  </div>
</section>

<!-- FOOTER simple -->
<footer style="padding:40px;text-align:center;border-top:1px solid var(--border);">
  <div style="font-family:var(--font-head);font-size:24px;letter-spacing:3px;margin-bottom:16px;">
    WELL<span style="color:var(--red);">CORE</span>
  </div>
  <div style="display:flex;justify-content:center;gap:24px;flex-wrap:wrap;margin-bottom:16px;">
    <a href="metodo.html" style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gray);text-decoration:none;">Método</a>
    <a href="planes.html" style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gray);text-decoration:none;">Planes</a>
    <a href="inscripcion.html" style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gray);text-decoration:none;">Inscripción</a>
    <a href="index.html" style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--gray);text-decoration:none;">Sitio Principal</a>
  </div>
  <div style="font-size:11px;color:rgba(255,255,255,0.2);">© 2026 WellCore Fitness · Coaching 1:1 basado en ciencia</div>
</footer>
```

**Agregar scripts al final del body:**
```html
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>AOS.init({duration:700,once:true,offset:60});</script>
```

**Verificación final de fit.html:**
- Navegar `https://wellcorefitness.test/fit.html`
- Scroll completo: hero ✓ · Silvia ✓ · benefits ✓ · plans ✓ · testimonials ✓ · CTA ✓
- Click CTA → va a inscripcion.html ✓
- Mobile (resize 390px): columnas colapsadas ✓

**Commit:**
```bash
git add fit.html
git commit -m "feat: fit.html CTA final + footer - female landing complete"
```

---

## Task 7: Módulo de Identidad Femenina WellCore

**Files:**
- Create: `E:/00_WELLCORE_FITNESS_ENTERPRISE/02_Identidad_Corporativa/BRANDING DE MUJER/wellcore-identidad-femenina.html`
- Create: `E:/00_WELLCORE_FITNESS_ENTERPRISE/02_Identidad_Corporativa/BRANDING DE MUJER/PROTOCOLO-FEMENINO.md`

**Contexto:** Documento visual interactivo (HTML) y protocolo escrito (MD) que sirven como referencia permanente para cualquier diseño o campaña dirigida a mujeres bajo la marca WellCore. Este NO va al repositorio git, es solo para la carpeta corporativa.

### wellcore-identidad-femenina.html

Estructura del documento HTML:
1. **Header** — Logo WellCore + título "Identidad Femenina"
2. **Paleta de colores** — Swatches visuales con código hex y nombre
3. **Rose Glow** — Demo visual del efecto en un panel de 300px
4. **Tipografía** — Ejemplos de Bebas Neue + Inter en diferentes tamaños
5. **Fotografía** — Guía de uso de fotos de Silvia (recortadas con fondo oscuro)
6. **Copy guidelines** — Palabras que SÍ usar / palabras que EVITAR
7. **Componentes** — Card, CTA, badge, stat — ejemplos copiables

**Paleta a documentar:**
```
#E31E24  — Rojo WellCore (primario, CTAs, texto highlight)
#DC3C64  — Rose WellCore (solo glow y acento femenino — NUNCA texto principal)
rgba(220,60,100,0.28) — Rose Glow (radial-gradient hero)
#0a0a0a  — Background oscuro principal
#111113  — Surface (tarjetas, secciones alternas)
#18181b  — Card background
rgba(255,255,255,0.45) — Texto secundario
```

**Copy guidelines (sección crítica):**
```
✅ USA: proceso · método · ciencia · resultados · tu cuerpo · tu ritmo
       juntas · acompañamiento · personalizado · diseñado para ti
       sin restricción · sin extremos · sin genéricos

❌ EVITA: transformación extrema · quemar grasa · adelgazar
          rápido · en X semanas · fuerza bruta · protocolo duro
          sin excusas · no pain no gain · [datos agresivos de pérdida]
```

### PROTOCOLO-FEMENINO.md

Contenido del markdown:
1. Contexto de marca (WellCore femenino = misma ciencia, diferente narrativa)
2. Paleta y reglas de uso
3. Tono de comunicación
4. Reglas para imágenes de Silvia
5. Checklist antes de publicar cualquier pieza femenina

**Commit (solo fit.html al repo):**
```bash
git add fit.html
git commit -m "feat: complete fit.html female landing - Silvia + rose glow WellCore"
```

El módulo de identidad NO va al git (carpeta corporativa externa).

---

## Verificación Final Completa

1. `https://wellcorefitness.test/fit.html` — todas las secciones visibles
2. `https://wellcorefitness.test/fit.html` en 390px — responsive OK
3. Click "EMPEZAR MI PROCESO" → `inscripcion.html` ✓
4. Click "Ver planes" → `planes.html` ✓
5. Abrir `E:/00_WELLCORE_FITNESS_ENTERPRISE/.../wellcore-identidad-femenina.html` en browser ✓
6. Archivo `PROTOCOLO-FEMENINO.md` legible en editor ✓
