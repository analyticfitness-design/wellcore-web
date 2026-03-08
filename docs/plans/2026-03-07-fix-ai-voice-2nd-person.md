# Plan: Corregir Voz IA a Segunda Persona (Tu) en Toda la Plataforma

**Fecha:** 2026-03-07
**Prioridad:** CRITICA — los clientes ven texto que parece generado por IA
**Estado:** DIAGNOSTICADO, pendiente de ejecucion

---

## Diagnostico

### Causa Raiz
La tabla `ai_prompts` en la BD tiene 5 prompts customizados que **sobreescriben** los prompts del codigo (que SI tienen la regla de segunda persona). Los prompts de la BD son genericos y cortos (~130 chars) sin ninguna instruccion de voz/tono.

### Prompts afectados en BD (tabla `ai_prompts`)

| Tipo | Largo | Tiene regla "tu" |
|------|-------|------------------|
| entrenamiento | 144b | NO |
| nutricion | 147b | NO |
| habitos | 125b | NO |
| ticket_response | 132b | NO |
| analisis | 128b | NO |
| rise | (no existe en BD, usa codigo) | SI (codigo) |

### Clientes RISE activos con planes renderizados

| ID | Nombre | Plan ID | Version | Hay que arreglar |
|----|--------|---------|---------|------------------|
| 10 | Nathalia Roa | 1 | v1 | SI |
| 11 | Cesar Jose Luna | 5 | v2 | SI |
| 15 | Silvia Carvajal | 3 | v2 | SI |
| 16 | Luis Eduardo Angarita Avila | 6 | v1 | SI |

---

## Plan de Implementacion (7 Fases)

### FASE 1: Arreglar prompts en BD (evita que se generen mas planes en 3ra persona)

**Accion:** UPDATE de los 5 registros en `ai_prompts` para agregar la regla de voz "tu"

```sql
UPDATE ai_prompts SET system_prompt = CONCAT(system_prompt, '

VOZ Y TONO — REGLA OBLIGATORIA:
- Escribe SIEMPRE en segunda persona (tu): "vas a entrenar", "tu objetivo", "enfocate en"
- NUNCA hables en tercera persona ("el cliente", "se recomienda que el usuario")
- Escribe como si TU fueras el coach escribiendole directamente a tu atleta
- Tono: cercano, directo, motivador pero profesional
- Que se sienta humano y personalizado, NUNCA como texto generado por IA')
WHERE type IN ('entrenamiento', 'nutricion', 'habitos', 'ticket_response', 'analisis');
```

**Verificacion:** Consultar `ai_prompts` y confirmar que todos los prompts tengan la regla.

### FASE 2: Descargar los 4 planes JSON desde `ai_generations.parsed_json`

Para cada plan activo:
1. Obtener `ai_generation_id` del `assigned_plans`
2. Descargar `parsed_json` de `ai_generations`
3. Guardar localmente como `fix-plans/plan-{ID}-{nombre}.json`

**Endpoint:** `GET /api/admin/tmp-diag.php?a=plan_detail&id={X}` — pero el content es HTML.
**Alternativa:** Consultar directo de `ai_generations.parsed_json` via SQL.

### FASE 3: Editar cada JSON con Claude Opus

Para cada plan JSON:
1. Identificar TODOS los campos de texto que estan en 3ra persona
2. Re-escribir cada campo a 2da persona (tu), manteniendo el contenido tecnico intacto
3. Campos tipicos a revisar:
   - `resumen_cliente`
   - `objetivo_30_dias`
   - `razon_cardio`
   - `nota_coach`
   - `tips_nutricion.*` (todos los subcampos)
   - `progresion_semanal`
   - `indicadores_progreso`
   - Notas de ejercicios individuales
   - Calentamientos y vueltas a la calma
4. Validar que el JSON siga siendo valido y completo

### FASE 4: Subir los JSONs corregidos a `assigned_plans.content`

Para cada plan:
1. `POST /api/admin/tmp-diag.php?a=update_plan` con `{id: X, content: {...}}`
2. Verificar con `GET ?a=plan_detail&id=X`

### FASE 5: Re-renderizar cada plan HTML

Para cada plan:
1. `POST /api/ai/render-plan.php` con `{plan_id: X}` (token admin)
2. Verificar el HTML generado en produccion
3. Confirmar que no haya texto en 3ra persona
4. Confirmar que los videos esten mapeados correctamente

### FASE 6: Verificacion visual

Para cada cliente:
1. Login como el cliente (impersonacion admin)
2. Navegar al dashboard RISE
3. Revisar seccion "Mi Programa"
4. Verificar tabs: Entrenamiento, Nutricion, etc.
5. Confirmar que TODO el texto sea en "tu"

### FASE 7: Limpieza

1. Eliminar archivos temporales del servidor:
   - `tmp-diag.php`
   - `tmp-silvia-fix.php`
   - `tmp-query-client.php`
   - Cualquier otro `tmp-*.php`
2. Verificar que los prompts de BD esten correctos
3. Testear una nueva generacion completa para confirmar que futuras generaciones salen bien

---

## Datos Tecnicos Necesarios

### Endpoints disponibles
- **Login admin:** `POST /api/auth/login.php` con `{type: "admin", username: "daniel.esparza", password: "..."}`
- **Generaciones por cliente:** `GET /api/admin/ai-generations.php?client_id=X&status=approved`
- **Detalle generacion:** `GET /api/admin/ai-generation.php?id=X`
- **Render plan:** `POST /api/ai/render-plan.php` con `{plan_id: X}` (token admin)
- **Diagnostico planes:** `GET /api/admin/tmp-diag.php?a=plans` / `?a=plan_detail&id=X` / `?a=rise_clients`

### Flujo de render-plan.php
1. Lee `assigned_plans.content` (JSON)
2. Si content es HTML (ya renderizado), busca JSON en `ai_generations.parsed_json`
3. Genera HTML con videos mapeados
4. Guarda HTML en `assigned_plans.content` (sobreescribe el JSON)
5. Activa el plan (`active = 1`)

### Nota importante sobre re-render
Al re-renderizar, el JSON en `assigned_plans.content` ya fue sobreescrito por HTML. Hay que:
1. Primero escribir el JSON corregido en `assigned_plans.content`
2. Luego llamar a render-plan.php que lo lee, genera HTML y sobreescribe

---

## Estimacion

- Fase 1: 2 minutos (1 SQL UPDATE)
- Fase 2: 5 minutos (4 consultas)
- Fase 3: 15-20 minutos (edicion manual de 4 JSONs con Claude)
- Fase 4: 5 minutos (4 UPDATEs)
- Fase 5: 5 minutos (4 renders)
- Fase 6: 10 minutos (verificacion visual)
- Fase 7: 5 minutos (limpieza)

**Total estimado: 45-55 minutos en una sesion**
