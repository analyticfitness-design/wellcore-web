<?php
/**
 * Achievements API
 * GET — List earned + locked achievements for current client
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$client = authenticateClient();
$db = getDB();
$cid = (int)$client['id'];
$plan = $client['plan'] ?? 'esencial';

// Get earned achievements
$stmt = $db->prepare("
    SELECT achievement_type, title, description, icon, earned_at
    FROM achievements WHERE client_id = ? ORDER BY earned_at DESC
");
$stmt->execute([$cid]);
$earned = $stmt->fetchAll();
$earnedTypes = array_column($earned, 'achievement_type');

// ── Universal (all plans) ────────────────────────────────────────────────────
$allAchievements = [
    // Engagement & tiempo
    ['type' => 'first_checkin',  'title' => 'Primer Check-in',          'desc' => 'Enviaste tu primer check-in semanal',         'icon' => 'clipboard-check'],
    ['type' => 'first_week',     'title' => 'Primera Semana',           'desc' => '7 días activo en el programa',                'icon' => 'calendar-week'],
    ['type' => '30_days',        'title' => '30 Días Activo',           'desc' => 'Un mes completo en el programa',              'icon' => 'medal'],
    ['type' => '90_days',        'title' => '3 Meses Fuerte',           'desc' => '90 días de constancia continua',              'icon' => 'award'],
    ['type' => '180_days',       'title' => '6 Meses Inquebrantable',   'desc' => 'Medio año transformando tu cuerpo',           'icon' => 'mountain-sun'],
    ['type' => '365_days',       'title' => 'Un Año de Transformación', 'desc' => 'Un año completo de compromiso y resultados',  'icon' => 'crown'],
    // Check-in streaks
    ['type' => 'streak_4',       'title' => 'Mes de Constancia',        'desc' => '4 check-ins completados',                    'icon' => 'calendar-check'],
    ['type' => 'streak_7',       'title' => 'Racha Imparable',          'desc' => '7 check-ins — semana tras semana',            'icon' => 'fire'],
    ['type' => 'streak_12',      'title' => 'Trimestre de Hierro',      'desc' => '12 check-ins consecutivos sin fallar',        'icon' => 'shield-halved'],
    ['type' => 'streak_26',      'title' => 'Medio Año en Llamas',      'desc' => '26 check-ins — mitad del año completada',     'icon' => 'fire-flame-curved'],
    ['type' => 'streak_52',      'title' => 'Leyenda WellCore',         'desc' => '52 check-ins — un año entero de disciplina',  'icon' => 'infinity'],
    // Fotos de progreso
    ['type' => 'first_photo',    'title' => 'Primera Foto',             'desc' => 'Subiste tu primera foto de progreso',         'icon' => 'camera'],
    // Comunidad
    ['type' => 'first_community','title' => 'Voz de la Comunidad',      'desc' => 'Publicaste en la comunidad por primera vez',  'icon' => 'comments'],
    ['type' => 'community_5',    'title' => 'Voz Constante',            'desc' => '5 posts compartidos en la comunidad',         'icon' => 'microphone'],
    ['type' => 'community_10',   'title' => 'Pilar de la Comunidad',    'desc' => '10 publicaciones — eres referente aquí',      'icon' => 'users'],
    ['type' => 'community_25',   'title' => 'Embajador WellCore',       'desc' => '25 posts — la comunidad te sigue',            'icon' => 'star'],
    // Récords personales
    ['type' => 'first_pr',       'title' => 'Primer Récord Personal',   'desc' => 'Registraste tu primer PR en el sistema',      'icon' => 'dumbbell'],
    ['type' => 'pr_3',           'title' => 'Colección de Poder',       'desc' => '3 récords personales registrados',            'icon' => 'chart-line'],
    ['type' => 'pr_5',           'title' => 'Arsenal Deportivo',        'desc' => '5 récords personales — datos reales de fuerza','icon' => 'bolt'],
    ['type' => 'pr_8',           'title' => 'Máquina de PRs',           'desc' => '8 ejercicios con récord registrado',          'icon' => 'rocket'],
];

// ── Esencial ─────────────────────────────────────────────────────────────────
if ($plan === 'esencial') {
    $allAchievements = array_merge($allAchievements, [
        ['type' => 'esencial_1month',   'title' => 'Esencial: Mes Uno',       'desc' => 'Completaste tu primer mes en plan Esencial',   'icon' => 'seedling'],
        ['type' => 'esencial_3months',  'title' => 'Esencial Comprometido',   'desc' => '3 meses firme en plan Esencial',              'icon' => 'heart'],
        ['type' => 'esencial_6months',  'title' => 'Esencial Legado',         'desc' => '6 meses en Esencial — base sólida construida', 'icon' => 'gem'],
        ['type' => 'esencial_year',     'title' => 'Un Año Esencial',         'desc' => 'Un año completo en plan Esencial',             'icon' => 'trophy'],
        ['type' => 'esencial_streak_4', 'title' => 'Disciplina Esencial',     'desc' => '4 semanas seguidas con check-in en Esencial',  'icon' => 'calendar-check'],
        ['type' => 'esencial_pr_first', 'title' => 'Fuerza Base',             'desc' => 'Tu primer récord personal como miembro Esencial','icon' => 'dumbbell'],
        ['type' => 'esencial_community','title' => 'Esencial con Voz',        'desc' => '5 posts en la comunidad siendo Esencial',      'icon' => 'comments'],
    ]);
}

// ── Método ────────────────────────────────────────────────────────────────────
if ($plan === 'metodo') {
    $allAchievements = array_merge($allAchievements, [
        ['type' => 'metodo_1month',     'title' => 'Método: Primer Mes',      'desc' => 'Un mes aplicando el Método WellCore',          'icon' => 'compass'],
        ['type' => 'metodo_3months',    'title' => 'Método Dominado',         'desc' => '3 meses con el Método — ya es parte de ti',    'icon' => 'award'],
        ['type' => 'metodo_6months',    'title' => 'Método Veterano',         'desc' => '6 meses en Método — disciplina probada',       'icon' => 'medal'],
        ['type' => 'metodo_year',       'title' => 'Método Elite Ready',      'desc' => 'Un año en Método — estás listo para más',      'icon' => 'trophy'],
        ['type' => 'metodo_pr_first',   'title' => 'PR del Método',           'desc' => 'Primer récord personal en plan Método',        'icon' => 'dumbbell'],
        ['type' => 'metodo_pr_5',       'title' => 'Fuerza Método',           'desc' => '5 PRs registrados en plan Método',             'icon' => 'chart-line'],
        ['type' => 'metodo_streak_8',   'title' => 'Disciplina Método',       'desc' => '8 check-ins seguidos aplicando el Método',     'icon' => 'shield-halved'],
        ['type' => 'metodo_community',  'title' => 'Método en Comunidad',     'desc' => '5 posts compartidos siendo miembro Método',    'icon' => 'comments'],
        ['type' => 'metodo_complete',   'title' => 'Método Certificado',      'desc' => '90 días + 5 PRs + 5 posts en Método',          'icon' => 'star'],
    ]);
}

// ── Elite ─────────────────────────────────────────────────────────────────────
if ($plan === 'elite') {
    $allAchievements = array_merge($allAchievements, [
        ['type' => 'elite_welcome',         'title' => 'Bienvenido a Elite',      'desc' => 'Accediste al plan más completo de WellCore',    'icon' => 'crown'],
        ['type' => 'elite_1month',          'title' => 'Élite: Primer Mes',       'desc' => 'Un mes en el nivel más alto de WellCore',       'icon' => 'star'],
        ['type' => 'elite_3months',         'title' => 'Élite Confirmado',        'desc' => '3 meses en Elite — compromiso total',           'icon' => 'trophy'],
        ['type' => 'elite_6months',         'title' => 'Veterano de Élite',        'desc' => '6 meses en Elite — disciplina y resultados',    'icon' => 'gem'],
        ['type' => 'elite_year',            'title' => 'Un Año de Élite',         'desc' => 'Un año en Elite — pocos llegan aquí',           'icon' => 'infinity'],
        ['type' => 'elite_nutrition_streak','title' => 'Nutrición al Día',        'desc' => '7 días usando el análisis nutricional',         'icon' => 'apple-whole'],
        ['type' => 'elite_nutrition_30',    'title' => 'Maestro Nutricional',     'desc' => '30 análisis nutricionales completados',         'icon' => 'utensils'],
        ['type' => 'elite_ai_review_first', 'title' => 'Análisis Inteligente',    'desc' => 'Tu primera foto analizada con inteligencia artificial','icon' => 'robot'],
        ['type' => 'elite_ai_review_5',     'title' => 'Archivo Inteligente',     'desc' => '5 revisiones de progreso con IA',               'icon' => 'layer-group'],
        ['type' => 'elite_ai_review_10',    'title' => 'Laboratorio Personal',    'desc' => '10 análisis de foto con IA — datos reales',     'icon' => 'brain'],
        ['type' => 'elite_pr_beast',        'title' => 'Bestia Élite',            'desc' => '5 récords personales siendo miembro Elite',     'icon' => 'hand-fist'],
        ['type' => 'elite_pr_complete',     'title' => 'Base de Datos de Fuerza', 'desc' => '8 ejercicios con récord registrado en Elite',   'icon' => 'chart-line'],
        ['type' => 'elite_community_leader','title' => 'Líder Élite',             'desc' => '10 posts en la comunidad siendo Elite',         'icon' => 'users'],
        ['type' => 'elite_allstar',         'title' => 'All-Star WellCore',       'desc' => '3 meses + 5 PRs + 5 análisis AI + 5 posts',    'icon' => 'rocket'],
    ]);
}

// ── RISE ──────────────────────────────────────────────────────────────────────
if ($plan === 'rise') {
    $allAchievements = array_merge($allAchievements, [
        ['type' => 'rise_day7',              'title' => 'RISE Día 7',              'desc' => 'Primera semana del reto completada',         'icon' => 'bolt'],
        ['type' => 'rise_day15',             'title' => 'RISE Medio Camino',       'desc' => 'Llegaste a la mitad del reto',               'icon' => 'flag-checkered'],
        ['type' => 'rise_day30',             'title' => 'RISE Completado',         'desc' => 'Completaste los 30 días del reto',           'icon' => 'trophy'],
        ['type' => 'rise_first_measurement', 'title' => 'Primera Medición RISE',   'desc' => 'Registraste tu primera medición',            'icon' => 'weight-scale'],
        ['type' => 'rise_streak_4',          'title' => 'RISE Consistencia',       'desc' => '4 check-ins en el reto — disciplina real',   'icon' => 'fire'],
        ['type' => 'rise_community',         'title' => 'RISE Comunidad',          'desc' => '3 posts en la comunidad del reto',           'icon' => 'comments'],
    ]);
}

// ── Build locked list ─────────────────────────────────────────────────────────
$locked = [];
foreach ($allAchievements as $a) {
    if (!in_array($a['type'], $earnedTypes, true)) {
        $locked[] = [
            'achievement_type' => $a['type'],
            'title'            => $a['title'],
            'description'      => $a['desc'],
            'icon'             => $a['icon'],
            'locked'           => true,
        ];
    }
}

respond([
    'earned'         => $earned,
    'locked'         => $locked,
    'total_earned'   => count($earned),
    'total_possible' => count($earned) + count($locked),
]);
