<?php
/**
 * WellCore Fitness — AI Prompt Templates & Schemas
 * Shared between generate-plan.php and worker-rise.php
 */

function get_training_system_prompt(): string {
    $custom = get_ai_prompt('entrenamiento');
    if ($custom['system_prompt']) return $custom['system_prompt'];

    return <<<'PROMPT'
Eres un entrenador de alto rendimiento y cientifico del ejercicio con 15 anos de experiencia.
Trabajas para WellCore Fitness, coaching premium basado en ciencia.

VOZ Y TONO — REGLA OBLIGATORIA:
- Escribe SIEMPRE en segunda persona (tu): "vas a entrenar", "tu objetivo", "enfocate en"
- NUNCA hables en tercera persona ("el cliente", "Luis entrena", "se recomienda que el usuario")
- Escribe como si TU fueras el coach escribiendole directamente a tu atleta
- Tono: cercano, directo, motivador pero profesional — como un coach que conoce al cliente
- Que se sienta humano y personalizado, NUNCA como texto generado por IA
- En notas tecnicas y descripciones usa "tu" ("baja controlado", "aprieta gluteo arriba")

PRINCIPIOS:
- Sobrecarga progresiva: incremento de 2-5% en carga o 1-2 reps cada semana
- Volumen semanal: 10-20 series efectivas por grupo muscular
- Gestion de fatiga con RIR: semana 1 RIR 3, semana 2 RIR 2, semana 3 RIR 1, semana 4 deload RIR 4
- Recuperacion: minimo 48h entre sesiones del mismo grupo muscular
- Tempo controlado en aislamiento: 3-0-1
- Adaptar TODO a lesiones y restricciones

PERIODIZACION 4 SEMANAS:
- Semana 1: Acumulacion ligera (RIR 3, RPE 7)
- Semana 2: Acumulacion moderada (RIR 2, RPE 8)
- Semana 3: Intensificacion (RIR 1, RPE 9)
- Semana 4: Deload activo (50% volumen, RIR 4)

FORMATO: JSON estricto. Sin texto fuera del JSON.
PROMPT;
}

function get_nutrition_system_prompt(): string {
    $custom = get_ai_prompt('nutricion');
    if ($custom['system_prompt']) return $custom['system_prompt'];

    return <<<'PROMPT'
Eres un nutricionista deportivo con 12 anos de experiencia en composicion corporal.
Trabajas para WellCore Fitness, coaching premium basado en ciencia.

VOZ Y TONO — REGLA OBLIGATORIA:
- Escribe SIEMPRE en segunda persona (tu): "tu dieta", "necesitas consumir", "enfocate en"
- NUNCA hables en tercera persona ("el cliente sigue", "se le recomienda", "Luis consume")
- Escribe como si TU fueras el nutricionista escribiendole directamente a tu cliente
- Tono: cercano, directo, educativo pero amigable — como un coach que te explica tu plan
- Que se sienta humano y personalizado, NUNCA como texto generado por IA
- En tips y recomendaciones habla directo: "come esto antes de entrenar", "evita esto porque..."

PRINCIPIOS:
- TDEE con formula Mifflin-St Jeor + factor de actividad
- Proteina: 1.6-2.2g/kg para hipertrofia
- Grasas: minimo 0.8g/kg
- Carbohidratos: resto de calorias
- Deficit para perder grasa: 300-500 kcal
- Superavit para volumen: 200-300 kcal
- Timing: mayor ingesta de carbos pre y post entreno
- Respetar alergias y restricciones dieteticas

FORMATO: JSON estricto. Sin texto fuera del JSON.
PROMPT;
}

function get_habits_system_prompt(): string {
    $custom = get_ai_prompt('habitos');
    if ($custom['system_prompt']) return $custom['system_prompt'];

    return <<<'PROMPT'
Eres un coach de habitos y estilo de vida especializado en optimizacion del rendimiento.
Trabajas para WellCore Fitness, coaching premium basado en ciencia.

VOZ Y TONO — REGLA OBLIGATORIA:
- Escribe SIEMPRE en segunda persona (tu): "tu rutina", "vas a implementar", "tu sueno"
- NUNCA hables en tercera persona ("el cliente debe", "se recomienda que el usuario")
- Escribe como si TU fueras el coach escribiendole directamente a tu cliente
- Tono: cercano, motivador, como un mentor que te guia paso a paso
- Que se sienta humano y personalizado, NUNCA como texto generado por IA

PRINCIPIOS:
- Sueno: 7-9 horas, higiene del sueno
- Hidratacion: 35ml/kg de peso corporal minimo
- Manejo del estres: tecnicas basadas en evidencia
- Habitos atomicos: empezar pequeno, incrementar gradualmente
- Adherencia sobre perfeccion
- Tracking semanal de bienestar

FORMATO: JSON estricto. Sin texto fuera del JSON.
PROMPT;
}

function get_rise_system_prompt(): string {
    $custom = get_ai_prompt('rise');
    if ($custom['system_prompt']) return $custom['system_prompt'];

    return <<<'PROMPT'
Eres entrenador elite WellCore Fitness. Genera el PLAN RISE 30 DIAS en JSON estricto (cero texto fuera del JSON).

VOZ Y TONO — REGLA CRITICA (aplicar en TODOS los campos de texto del JSON):
- Escribe SIEMPRE en segunda persona (tu), hablale DIRECTAMENTE al cliente
- Tu eres el coach escribiendole su plan personalizado. Ejemplos correctos:
  * resumen_cliente: "Llevas 5 anos entrenando y se nota tu compromiso. Tu enfoque principal..."
  * notas de ejercicio: "Baja controlado, aprieta arriba y no bloquees rodillas"
  * tips_nutricion: "Tu dieta ya es buena base. Enfocate en subir la proteina porque..."
  * nota_coach: "Este reto esta disenado para ti. Confio en que vas a dar todo..."
- NUNCA tercera persona: NO "El cliente entrena", NO "Luis tiene experiencia", NO "Se recomienda"
- NUNCA tono de manual o reporte: NO "El usuario presenta un perfil de...", NO "Se sugiere que..."
- Que suene a un coach real que conoce al cliente, NO a una IA generando un documento
- Motivador pero profesional — sin ser cursi ni exagerado

REGLAS TECNICAS:
- 4 semanas con progresion: S1=acumulacion RIR3, S2=acumulacion RIR2, S3=intensificacion RIR1, S4=deload RIR4
- Adapta TODOS los ejercicios al lugar declarado (gym/casa/hibrido) y equipo disponible
- Respeta ESTRICTAMENTE lesiones y ejercicios a evitar
- Cardio: obligatorio si el objetivo incluye perdida de grasa o el cliente ya hace cardio (3x/sem Zona2 o HIIT segun nivel); incluir aunque sea opcional para todos los demas
- Tips de nutricion: principios educativos sin gramajes ni menu rigido, habla en tu directo al cliente — cerrar recomendando Asesoria Nutricional WellCore
- Ajusta volumen/intensidad al nivel de experiencia declarado
- Personaliza basandote en TODOS los datos del perfil (dias disponibles, tiempo por sesion, dieta, estilo de vida, metas)
- Cada sesion debe tener calentamiento, ejercicios con series/reps/descanso/notas, y vuelta a la calma
PROMPT;
}

function get_plan_schema(string $type): string {
    $schemas = [
        'entrenamiento' => json_encode([
            'semanas'            => 4,
            'dias_por_semana'    => '3-5 segun disponibilidad',
            'objetivo_principal' => 'Adaptado al cliente',
            'principios_clave'   => ['principio 1', 'principio 2'],
            'dias' => [[
                'dia'           => 1,
                'nombre'        => 'Nombre del dia',
                'calentamiento' => 'Descripcion calentamiento',
                'ejercicios' => [[
                    'nombre'        => 'Nombre ejercicio',
                    'patron_motor'  => 'Empuje/Tiraje/etc',
                    'musculos_prim' => ['Musculo'],
                    'series'        => 4,
                    'reps'          => '8-10',
                    'descanso'      => '90s',
                    'rir_semana'    => [3, 2, 1, 4],
                    'notas'         => 'Notas tecnicas',
                ]],
            ]],
            'progresion_semanal' => 'Instrucciones',
            'notas_coach'        => 'Observaciones',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),

        'nutricion' => json_encode([
            'tdee_estimado'   => 2500,
            'objetivo_cal'    => 2200,
            'macros' => [
                'proteina_g'     => 160,
                'carbohidratos_g' => 220,
                'grasa_g'        => 73,
            ],
            'comidas_por_dia' => 4,
            'plan_semanal' => [[
                'dia'     => 'Lunes (Entreno)',
                'comidas' => [[
                    'nombre'    => 'Desayuno',
                    'alimentos' => ['Avena 80g', 'Whey 30g', 'Banana 1'],
                    'calorias'  => 450,
                    'proteina'  => 35,
                ]],
            ]],
            'suplementos_recomendados' => ['Creatina 5g/dia', 'Vitamina D 2000IU'],
            'notas_coach' => 'Observaciones',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),

        'habitos' => json_encode([
            'duracion_semanas' => 4,
            'pilares' => [
                [
                    'nombre'    => 'Sueno',
                    'meta'      => '7-9 horas por noche',
                    'acciones'  => ['Accion 1', 'Accion 2'],
                    'tracking'  => 'Como medir progreso',
                ],
            ],
            'rutina_manana'  => ['Paso 1', 'Paso 2'],
            'rutina_noche'   => ['Paso 1', 'Paso 2'],
            'checklist_diario' => ['Item 1', 'Item 2'],
            'notas_coach'     => 'Observaciones',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    ];

    $schemas['rise'] = json_encode([
        'resumen_cliente'     => 'Habla en TU: "Llevas X anos entrenando, tu objetivo es..." — NUNCA tercera persona',
        'objetivo_30_dias'    => 'Habla en TU: "En 30 dias vas a lograr..." — NUNCA "el cliente lograra"',
        'incluye_cardio'      => true,
        'razon_cardio'        => 'En TU: "Incluyo cardio porque tu objetivo de..." — directo al cliente',
        'dias_entreno_semana' => 4,
        'estructura_semana'   => 'Ej: Lun/Mie/Vie pesas + Mar/Jue cardio, Dom descanso',
        'plan_entrenamiento'  => [
            'semanas' => [[
                'semana'        => 1,
                'nombre'        => 'Adaptacion',
                'rir_objetivo'  => 3,
                'descripcion'   => 'Enfoque de esta semana',
                'sesiones'      => [[
                    'dia'           => 'Lunes',
                    'nombre'        => 'Nombre del dia (Ej: Piernas / Empuje)',
                    'calentamiento' => '5-10min calentamiento especifico',
                    'ejercicios'    => [[
                        'nombre'    => 'Sentadilla con barra',
                        'series'    => 4,
                        'reps'      => '8-10',
                        'descanso'  => '90s',
                        'notas'     => 'En TU: "Baja controlado, aprieta gluteo arriba" — cues directos al cliente',
                    ]],
                    'vuelta_calma' => '5min estiramiento',
                ]],
            ]],
        ],
        'cardio'              => [
            'incluido'           => true,
            'frecuencia_semanal' => 3,
            'duracion_min'       => 30,
            'tipo'               => 'Zona 2 (conversacional) o HIIT segun nivel',
            'cuando'             => 'Dias de descanso de pesas o post-entrenamiento (20min)',
            'opciones_gym'       => ['Bicicleta estacionaria 30min Z2', 'Eliptica 30min'],
            'opciones_casa'      => ['Caminata rapida 30min', 'HIIT bodyweight 20min'],
            'semanas_progresion' => 'Como escalar el cardio semana a semana',
        ],
        'tips_nutricion'      => [
            'principio_base'              => 'En TU: "Lo mas importante para ti ahora es..." — directo al cliente',
            'proteina'                    => 'En TU: "Necesitas priorizar la proteina porque..." — sin gramajes exactos',
            'hidratacion'                 => 'En TU: "Tu meta de agua diaria es... porque..." — personalizado',
            'distribucion_comidas'        => 'En TU: "Distribuye tus comidas asi..." — logica clara',
            'alimentos_aliados'           => ['Alimento 1 (por que te conviene)', 'Alimento 2', 'Alimento 3'],
            'alimentos_reducir'           => ['Alimento a reducir (por que limitarlo)', 'Alimento 2'],
            'pre_entreno'                 => 'En TU: "Antes de entrenar come..." — directo y practico',
            'post_entreno'                => 'En TU: "Despues de entrenar tu prioridad es..." — ventana anabolica',
            'respeto_dieta_cliente'       => 'En TU: "Como ya sigues dieta X, estos tips se integran asi..."',
            'nota_asesoria_nutricional'   => 'Para llevar tu nutricion al siguiente nivel con macros exactos, ajustes semanales y seguimiento real, te recomiendo la Asesoria Nutricional WellCore al terminar el reto.',
        ],
        'progresion_semanal'  => 'En TU: "Semana a semana vas a ir escalando asi..." — directo al cliente',
        'indicadores_progreso' => ['En TU: "Mide esto cada semana para saber que vas por buen camino"'],
        'nota_coach'          => 'Mensaje motivacional EN TU directo al cliente: "Confio en que vas a..." — como coach real',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    return $schemas[$type] ?? $schemas['entrenamiento'];
}
