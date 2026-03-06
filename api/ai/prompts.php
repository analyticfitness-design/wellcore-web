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

REGLAS:
- 4 semanas con progresion: S1=acumulacion RIR3, S2=acumulacion RIR2, S3=intensificacion RIR1, S4=deload RIR4
- Adapta TODOS los ejercicios al lugar declarado (gym/casa/hibrido) y equipo disponible
- Respeta ESTRICTAMENTE lesiones y ejercicios a evitar
- Cardio: obligatorio si el objetivo incluye perdida de grasa o el cliente ya hace cardio (3x/sem Zona2 o HIIT segun nivel); incluir aunque sea opcional para todos los demas
- Tips de nutricion: principios educativos sin gramajes ni menu rigido — cerrar recomendando Asesoria Nutricional WellCore
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
        'resumen_cliente'     => 'Breve descripcion del perfil y objetivo en el reto',
        'objetivo_30_dias'    => 'Que lograra este cliente en 30 dias con adherencia',
        'incluye_cardio'      => true,
        'razon_cardio'        => 'Por que se incluye o no el cardio para este cliente',
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
                        'notas'     => 'Notas tecnicas y de ejecucion',
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
            'principio_base'              => 'El principio nutricional mas importante para este cliente',
            'proteina'                    => 'Por que la proteina es clave y como incluirla (sin gramajes exactos)',
            'hidratacion'                 => 'Meta de agua diaria y por que importa en el reto',
            'distribucion_comidas'        => 'Cuantas comidas y con que logica distribuirlas',
            'alimentos_aliados'           => ['Alimento 1', 'Alimento 2', 'Alimento 3'],
            'alimentos_reducir'           => ['Alimento a reducir 1', 'Alimento a reducir 2'],
            'pre_entreno'                 => 'Que comer 1-2h antes de entrenar',
            'post_entreno'                => 'Que comer en los 60min post-entrenamiento',
            'respeto_dieta_cliente'       => 'Como adaptar estos tips a la dieta/preferencia del cliente',
            'nota_asesoria_nutricional'   => 'Para maximizar tus resultados con un plan 100% personalizado — macros exactos, ajustes semanales y seguimiento real — te recomendamos la Asesoria Nutricional WellCore al finalizar el reto.',
        ],
        'progresion_semanal'  => 'Como debe escalar el cliente semana a semana (volumen, intensidad, cardio)',
        'indicadores_progreso' => ['Que medir semana a semana para saber que va bien'],
        'nota_coach'          => 'Mensaje motivacional y consejo clave del coach para el reto',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    return $schemas[$type] ?? $schemas['entrenamiento'];
}
