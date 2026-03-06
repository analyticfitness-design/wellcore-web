# Task 8: Preview Bloqueado + Nutricion/Habitos Interactivos + Pestanas Elite

## Fecha: 2026-03-06

## Resumen
Redisenar el sistema de pestanas en "Mi Plan" de cliente.html:
- 6 pestanas totales (antes 3)
- Preview bloqueado con blur reducido y contenido demo visible
- Nutricion y Habitos interactivos (mismo patron que Entrenamiento)
- 3 pestanas nuevas: Suplementacion, Ciclo Hormonal, Bloodwork

## Tabla de Acceso por Plan

| # | Pestana | Min Plan | tabLevel |
|---|---------|----------|----------|
| 1 | Entrenamiento | Esencial | 1 |
| 2 | Nutricion | Metodo | 2 |
| 3 | Habitos | Esencial | 1 |
| 4 | Suplementacion | Esencial | 1 |
| 5 | Ciclo Hormonal | Elite | 3 |
| 6 | Bloodwork | Elite | 3 |

Tips del Coach (Haiku): solo Metodo y Elite.

## Preview Bloqueado (Opcion A)

CSS actualizado:
- background: rgba(10,10,10,0.65) (antes 0.92)
- backdrop-filter: blur(3px) (antes 4px)
- Contenido demo rico visible detras
- Overlay: icono candado, titulo, features list (3-4 bullets), boton "Mejorar Plan"
- Contenido detras NO interactivo — HTML estatico demo

## Nutricion Interactiva (Metodo + Elite)

Toggle "Vista Interactiva" vs "PDF" (mismo patron entrenamiento).

Vista Interactiva:
- KPI bar: Calorias, Proteina, Carbos, Grasas vs objetivo
- Cards por comida: Desayuno, Media Manana, Almuerzo, Merienda, Cena, Pre-Entreno, Post-Entreno
- Cada card expandible: alimentos + cantidades + macros
- Boton Coach Tip (Metodo/Elite)
- NUTRITION_DATA hardcoded, reemplazable por API

## Habitos Interactiva (Esencial+)

Vista Interactiva:
- Barra de progreso del dia
- Cards por categoria: Entrenamiento, Nutricion, Recuperacion, Mindset
- Cada habito: checkbox + nombre + tip coach (Metodo/Elite)
- Streak visual (racha dias consecutivos)
- HABITS_DATA hardcoded, reemplazable por API

## Suplementacion (Esencial+)

- Cards/tabla de suplementos: nombre, dosis, timing, notas
- Categorias: Rendimiento, Salud General, Recuperacion
- SUPPLEMENTS_DATA hardcoded
- Demo: 8-10 suplementos tipicos

## Ciclo Hormonal (Elite)

- Timeline visual con fases (Carga, Mantenimiento, PCT, Descanso)
- Tabla de compuestos: nombre, dosis, frecuencia, semanas
- Seccion monitoreo: labs recomendados y cuando
- Banner advertencia medica permanente
- CYCLE_DATA hardcoded

## Bloodwork (Elite)

- Cards por marcador: valor + rango visual (verde/amarillo/rojo)
- Categorias: Hormonal, Hepatico, Lipidico, Renal, Hematologico
- Historial simplificado (tendencia)
- BLOODWORK_DATA hardcoded

## Cambios Tecnicos

- tabLevel: { entrenamiento:1, nutricion:2, habitos:1, suplementacion:1, ciclo:3, bloodwork:3 }
- CSS .locked-overlay actualizado (menos opacidad, menos blur)
- Contenido demo estatico por tab bloqueada
- NUTRITION_DATA, HABITS_DATA, SUPPLEMENTS_DATA, CYCLE_DATA, BLOODWORK_DATA
- RISE dashboard: sin cambios (mantiene su version simple de habitos)
