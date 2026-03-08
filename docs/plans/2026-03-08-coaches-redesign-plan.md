# Plan: Rediseño Completo coaches.html
**Fecha:** 2026-03-08 | **Estado:** EN EJECUCIÓN

## Objetivo
Convertir coaches.html de página de reclutamiento básico a página de pitch completo para coaches/influencers LATAM que quieran escalar con un equipo profesional (WellCore).

## Investigación de Mercado (Completada)
- Mercado fitness online LATAM: $6.1B proyectado 2030, CAGR 26.4%
- Techo coach solo: $1.5K–$8K/mes | Con equipo WellCore: $25K–$80K+/mes
- 32.8% de coaches tienen burnout personal
- Solo 44% de clientes permanece 24 meses sin sistemas de retención
- Coaches que sistematizan y delegan reportan 3–10x más ingresos en 12–18 meses
- Aumento de retención del 5% = +25–95% en rentabilidad

## Secciones a Implementar

### 1. Hero (YA IMPLEMENTADO ✅)
- Título: "EL EQUIPO QUE NINGÚN COACH PUEDE SOLO"
- Stats: 5 ROLES / 3 MODELOS / 60% PARA TI / 0 COSTO PLATAFORMA
- CTAs: #modelos y #aplicar

### 2. Pain Points Section (reemplaza `#beneficios` why-section)
- ID: `#problema`
- Clase: `.pain-section`
- 6 cards:
  1. EL TECHO DEL TIEMPO — 20-40 clientes max solo
  2. BURNOUT REAL — 32.8% de coaches
  3. CLIENTES QUE SE VAN — solo 44% a 24 meses
  4. CONTENIDO SIN IMPACTO — sin editor = invisible
  5. COBRAS MUY POCO — $50-$150 vs $200-$500 posible
  6. UN SOLO INGRESO — churn = crisis
- Market stats bar: $6.1B / 26.4% CAGR / $8K techo solo / 3-10x con equipo

### 3. Work Models Section (reemplaza `#planes` plans-section)
- ID: `#modelos`
- Clase: `.models-section`
- 3 cards:
  1. COACH WELLCORE — 60% comisión, clientes bajo marca WellCore
  2. WHITE-LABEL ⭐ — tu marca, infraestructura WellCore (más solicitado)
  3. INFLUENCER/CREADOR — monetiza audiencia 50k+ seguidores

### 4. Team Ecosystem Section (reemplaza `#guia` guide-section)
- ID: `#equipo`
- Clase: `.team-section`
- 5 roles en grid: Estratega / Desarrollador / COACH (centro) / Editor / Nutricionista
- Income comparison bars: Solo sin sistemas / Solo + herramientas / Coach equipo parcial / Coach WellCore

### 5. Form Update
- Agregar campo "Tipo de perfil" (radio): Coach / Influencer / Marca
- Incluir perfil en payload JSON al submit
- Mantener todos los campos existentes

## Archivos Modificados
- `coaches.html` — archivo principal (único)

## Checklist de Implementación
- [ ] CSS: `.pain-section`, `.pain-grid`, `.pain-card`
- [ ] CSS: `.market-stats`, `.market-stat`
- [ ] CSS: `.models-section`, `.models-grid`, `.model-card`
- [ ] CSS: `.team-section`, `.team-grid`, `.team-role`
- [ ] CSS: `.income-bars`, `.income-bar`, `.income-fill`
- [ ] CSS: responsive media queries para nuevas secciones
- [ ] HTML: Reemplazar why-section con pain-section
- [ ] HTML: Reemplazar plans-section con models-section (id="modelos")
- [ ] HTML: Reemplazar guide-section con team-section (id="equipo")
- [ ] HTML: Agregar "tipo de perfil" al form
- [ ] JS: Smooth scroll para #modelos y #equipo
- [ ] JS: Actualizar intersection observer targets
- [ ] JS: Incluir campo perfil en payload

## Mantenido sin cambios
- Video section (#video) — producción audiovisual — CONSERVAR
- Application form (#aplicar) — CONSERVAR estructura, solo agregar campo
- Contract modal — CONSERVAR completo
- Footer — CONSERVAR
- Navbar + mobile nav — CONSERVAR

## Notas de Diseño
- Solo color rojo (#E31E24) como acento — sin azul/cyan
- Pain cards: números grandes en rojo translúcido como fondo visual
- Team grid: coach al centro resaltado en rojo sólido
- Income bars: animadas con CSS, doradas para el modelo WellCore
- Market stats: borde superior rojo 3px — datos de fuentes reales
