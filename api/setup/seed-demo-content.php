<?php
/**
 * WellCore Fitness — Seed de Contenido Demo
 * ===========================================
 * Inserta contenido inicial para todas las features del portal cliente:
 *   - coach_audio (5 episodios)
 *   - coach_video_tips (5 videos)
 *   - academy_content (6 piezas)
 *   - challenges (2 retos)
 *   - coach_community_posts (5 posts)
 *   - coach_availability (horario semanal)
 *   - Asigna coach_id=1 a clientes sin coach
 *   - Inserta XP inicial a clientes demo
 *
 * EJECUCIÓN: php /code/api/setup/seed-demo-content.php
 * Solo inserta si la tabla está vacía (idempotente).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db = getDB();
$ok = 0;
$skip = 0;
$errors = [];

function seedTable(PDO $db, string $table, string $checkSql, callable $insertFn): void {
    global $ok, $skip, $errors;
    try {
        $count = (int)$db->query($checkSql)->fetchColumn();
        if ($count > 0) {
            echo "  SKIP  $table ($count rows ya existen)\n";
            $skip++;
            return;
        }
        $insertFn($db);
        echo "  OK    $table\n";
        $ok++;
    } catch (\PDOException $e) {
        $errors[] = "$table: " . $e->getMessage();
        echo "  ERROR $table: " . $e->getMessage() . "\n";
    }
}

$coachId = 3; // coachsilvia — coach real asignado a los clientes demo (id=3 en admins)
$now     = date('Y-m-d H:i:s');
$today   = date('Y-m-d');

echo "\n=== WellCore Seed Demo Content ===\n";
echo "Coach ID usado: $coachId\n\n";

// ─── 1. ASIGNAR COACH A CLIENTES SIN COACH ─────────────────────────────────
echo "--- Asignando coach_id a clientes ---\n";
try {
    $upd = $db->prepare("UPDATE clients SET coach_id = ? WHERE coach_id IS NULL OR coach_id = 0");
    $upd->execute([$coachId]);
    $updated = $upd->rowCount();
    echo "  OK    $updated clientes actualizados con coach_id=$coachId\n";
} catch (\PDOException $e) {
    // La columna puede no existir en todos los entornos
    echo "  INFO  coach_id: " . $e->getMessage() . "\n";
}

// ─── 2. AUDIO COACHING ─────────────────────────────────────────────────────
echo "\n--- coach_audio ---\n";
seedTable(
    $db,
    'coach_audio',
    "SELECT COUNT(*) FROM coach_audio",
    function (PDO $db) use ($coachId, $now) {
        $audios = [
            [
                'title'       => 'Bienvenido al Sistema WellCore',
                'audio_url'   => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
                'duration_sec'=> 487,
                'category'    => 'motivacion',
                'plan_access' => null,
                'sort_order'  => 1,
            ],
            [
                'title'       => 'La Trampa de la Motivación',
                'audio_url'   => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
                'duration_sec'=> 613,
                'category'    => 'mindset',
                'plan_access' => null,
                'sort_order'  => 2,
            ],
            [
                'title'       => 'El Poder del Check-in Diario',
                'audio_url'   => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3',
                'duration_sec'=> 521,
                'category'    => 'habitos',
                'plan_access' => null,
                'sort_order'  => 3,
            ],
            [
                'title'       => 'Cómo Leer Tu Propio Cuerpo',
                'audio_url'   => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-4.mp3',
                'duration_sec'=> 732,
                'category'    => 'tecnica',
                'plan_access' => json_encode(['metodo', 'elite']),
                'sort_order'  => 4,
            ],
            [
                'title'       => 'La Ciencia de la Constancia',
                'audio_url'   => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-5.mp3',
                'duration_sec'=> 668,
                'category'    => 'mindset',
                'plan_access' => null,
                'sort_order'  => 5,
            ],
        ];

        $stmt = $db->prepare("
            INSERT INTO coach_audio (coach_id, title, audio_url, duration_sec, category, plan_access, sort_order, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");
        foreach ($audios as $a) {
            $stmt->execute([$GLOBALS['coachId'], $a['title'], $a['audio_url'], $a['duration_sec'], $a['category'], $a['plan_access'], $a['sort_order'], $GLOBALS['now']]);
        }
    }
);

// ─── 3. VIDEO TIPS ─────────────────────────────────────────────────────────
echo "\n--- coach_video_tips ---\n";
seedTable(
    $db,
    'coach_video_tips',
    "SELECT COUNT(*) FROM coach_video_tips",
    function (PDO $db) use ($coachId, $now) {
        $tips = [
            [
                'title'         => 'Sentadilla perfecta: los 3 puntos de control',
                'video_url'     => 'https://www.youtube.com/watch?v=ultWZbUMPL8',
                'thumbnail_url' => 'https://img.youtube.com/vi/ultWZbUMPL8/hqdefault.jpg',
                'duration_sec'  => 95,
                'sort_order'    => 1,
            ],
            [
                'title'         => '3 errores que arruinan tu progreso con la proteína',
                'video_url'     => 'https://www.youtube.com/watch?v=GhPkkovbzF4',
                'thumbnail_url' => 'https://img.youtube.com/vi/GhPkkovbzF4/hqdefault.jpg',
                'duration_sec'  => 118,
                'sort_order'    => 2,
            ],
            [
                'title'         => 'Por qué el descanso ES el entrenamiento',
                'video_url'     => 'https://www.youtube.com/watch?v=QH2-TGUlwu4',
                'thumbnail_url' => 'https://img.youtube.com/vi/QH2-TGUlwu4/hqdefault.jpg',
                'duration_sec'  => 105,
                'sort_order'    => 3,
            ],
            [
                'title'         => 'Hip Thrust: activación máxima de glúteos',
                'video_url'     => 'https://www.youtube.com/watch?v=SEdqd1n0cvg',
                'thumbnail_url' => 'https://img.youtube.com/vi/SEdqd1n0cvg/hqdefault.jpg',
                'duration_sec'  => 130,
                'sort_order'    => 4,
            ],
            [
                'title'         => 'Mentalidad de atleta: el sistema de identidad',
                'video_url'     => 'https://www.youtube.com/watch?v=1eTXRLJnOtI',
                'thumbnail_url' => 'https://img.youtube.com/vi/1eTXRLJnOtI/hqdefault.jpg',
                'duration_sec'  => 128,
                'sort_order'    => 5,
            ],
        ];

        $stmt = $db->prepare("
            INSERT INTO coach_video_tips (coach_id, title, video_url, thumbnail_url, duration_sec, sort_order, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?)
        ");
        foreach ($tips as $t) {
            $stmt->execute([$GLOBALS['coachId'], $t['title'], $t['video_url'], $t['thumbnail_url'], $t['duration_sec'], $t['sort_order'], $GLOBALS['now']]);
        }
    }
);

// ─── 4. ACADEMIA ──────────────────────────────────────────────────────────
echo "\n--- academy_content ---\n";
seedTable(
    $db,
    'academy_content',
    "SELECT COUNT(*) FROM academy_content",
    function (PDO $db) use ($now) {
        $items = [
            [
                'title'        => 'Tour del Portal: Cómo Sacarle el Máximo a WellCore',
                'category'     => 'onboarding',
                'content_type' => 'video',
                'plan_access'  => 'esencial,metodo,elite,rise',
                'content_url'  => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'thumbnail_url'=> 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                'description'  => 'Aprende a usar cada sección del portal para maximizar tu progreso desde el día 1.',
                'sort_order'   => 1,
            ],
            [
                'title'        => 'Guía de Macros para Tu Plan',
                'category'     => 'nutricion',
                'content_type' => 'pdf',
                'plan_access'  => 'metodo,elite',
                'content_url'  => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'thumbnail_url'=> null,
                'description'  => 'Calcula tus macronutrientes ideales según tu objetivo: pérdida de grasa, ganancia muscular o mantenimiento.',
                'sort_order'   => 2,
            ],
            [
                'title'        => 'Hipertrofia 101: Cómo Funciona el Crecimiento Muscular',
                'category'     => 'entrenamiento',
                'content_type' => 'video',
                'plan_access'  => 'metodo,elite',
                'content_url'  => 'https://www.youtube.com/watch?v=2tM1LFFxeKg',
                'thumbnail_url'=> 'https://img.youtube.com/vi/2tM1LFFxeKg/hqdefault.jpg',
                'description'  => 'Todo lo que necesitas saber sobre síntesis proteica, tensión mecánica y volumen de entrenamiento efectivo.',
                'sort_order'   => 3,
            ],
            [
                'title'        => 'Ciencia del Sueño y Recuperación Muscular',
                'category'     => 'recuperacion',
                'content_type' => 'article',
                'plan_access'  => 'metodo,elite',
                'content_url'  => null,
                'thumbnail_url'=> null,
                'description'  => 'El sueño es cuando realmente creces. Aprende a optimizar tus 7-9 horas para máxima recuperación.',
                'body_html'    => '<h2>¿Por qué el sueño es tu mejor suplemento?</h2><p>Durante el sueño profundo (fase N3), el cuerpo libera la mayor concentración de hormona de crecimiento del día. Un estudio de la Universidad de Chicago demostró que reducir el sueño de 8.5h a 5.5h en atletas redujo la síntesis proteica muscular en un <strong>18%</strong>.</p><h3>Fases del sueño y su rol</h3><ul><li><strong>N1-N2 (sueño ligero):</strong> Consolidación de memoria motriz — fundamental para técnica de levantamiento</li><li><strong>N3 (sueño profundo):</strong> Pico de GH, reparación tisular, síntesis de glucógeno</li><li><strong>REM:</strong> Procesamiento emocional, motivación, adherencia al programa</li></ul><h3>Protocolo de higiene del sueño</h3><ol><li>Misma hora de acostarte todos los días (±30 min)</li><li>Temperatura ambiente: 18-20°C</li><li>Sin pantallas 60 min antes (o modo oscuro + f.lux)</li><li>Magnesio glicinato 300mg antes de dormir</li><li>Proteína de digestión lenta (caseína) si entrenas 2x/día</li></ol>',
                'sort_order'   => 4,
            ],
            [
                'title'        => 'Los 5 Movimientos Fundamentales del Fitness',
                'category'     => 'entrenamiento',
                'content_type' => 'guide',
                'plan_access'  => 'esencial,metodo,elite,rise',
                'content_url'  => null,
                'thumbnail_url'=> null,
                'description'  => 'Domina estos 5 patrones de movimiento y podrás entrenar cualquier músculo de forma segura y eficiente.',
                'body_html'    => '<h2>Los 5 Patrones Fundamentales</h2><p>Todo entrenamiento de fuerza se basa en 5 patrones. Mástralos y tendrás una base sólida para toda la vida.</p><h3>1. Sentadilla (Squat)</h3><p><strong>Músculos:</strong> Cuádriceps, glúteos, isquiotibiales, core<br><strong>Progresión:</strong> Goblet squat → Sentadilla con barra alta → Front squat<br><strong>Clave técnica:</strong> Rodillas alineadas con punta de pie, pecho arriba, talones pegados al suelo</p><h3>2. Bisagra de Cadera (Hip Hinge)</h3><p><strong>Músculos:</strong> Glúteos, isquiotibiales, erector espinal<br><strong>Progresión:</strong> Romanian deadlift → Peso muerto convencional → Trap bar deadlift<br><strong>Clave técnica:</strong> Empuja las caderas hacia atrás (no hacia abajo), espalda neutral</p><h3>3. Empuje Horizontal (Push)</h3><p><strong>Músculos:</strong> Pectoral, deltoides anterior, tríceps<br><strong>Progresión:</strong> Push-up → Press con mancuernas → Press con barra<br><strong>Clave técnica:</strong> Omóplatos retraídos y deprimidos, muñecas neutras</p><h3>4. Tracción Horizontal (Pull)</h3><p><strong>Músculos:</strong> Dorsal, romboides, bíceps<br><strong>Progresión:</strong> Face pull → Remo con cable → Remo con barra<br><strong>Clave técnica:</strong> Tira con los codos, no con las manos</p><h3>5. Cargada sobre Cabeza (Carry/Press)</h3><p><strong>Músculos:</strong> Deltoides, trapecio, core<br><strong>Progresión:</strong> Press con mancuernas → Arnold press → Press militar<br><strong>Clave técnica:</strong> Core activado, no hiperextiendas la lumbar</p>',
                'sort_order'   => 5,
            ],
            [
                'title'        => 'Plan de Alimentación: Semana Tipo para Pérdida de Grasa',
                'category'     => 'nutricion',
                'content_type' => 'pdf',
                'plan_access'  => 'metodo,elite',
                'content_url'  => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'thumbnail_url'=> null,
                'description'  => 'Una semana completa de comidas balanceadas: 7 desayunos, 7 almuerzos, 7 cenas y snacks opcionales.',
                'sort_order'   => 6,
            ],
        ];

        $stmt = $db->prepare("
            INSERT INTO academy_content
                (title, category, content_type, audience, plan_access, content_url, thumbnail_url, description, body_html, sort_order, active, created_at)
            VALUES (?, ?, ?, 'client', ?, ?, ?, ?, ?, ?, 1, ?)
        ");
        foreach ($items as $i) {
            $stmt->execute([
                $i['title'],
                $i['category'],
                $i['content_type'],
                $i['plan_access'],
                $i['content_url'] ?? null,
                $i['thumbnail_url'] ?? null,
                $i['description'],
                $i['body_html'] ?? null,
                $i['sort_order'],
                $GLOBALS['now'],
            ]);
        }
    }
);

// ─── 5. CHALLENGES (RETOS) ────────────────────────────────────────────────
echo "\n--- challenges ---\n";
seedTable(
    $db,
    'challenges',
    "SELECT COUNT(*) FROM challenges",
    function (PDO $db) use ($today, $now) {
        $end1  = date('Y-m-d', strtotime($today . ' +14 days'));
        $end2  = date('Y-m-d', strtotime($today . ' +28 days'));

        $challenges = [
            [
                'name'           => 'Semana de Fuego 🔥',
                'description'    => '¡Haz check-in 7 días consecutivos sin fallar! El reto más básico pero el más importante. Quienes lo completan tienen 3x más probabilidad de seguir activos al mes.',
                'challenge_type' => 'checkins',
                'target_value'   => 7,
                'unit'           => 'check-ins',
                'start_date'     => $today,
                'end_date'       => $end1,
                'plan_access'    => 'esencial,metodo,elite,rise',
                'badge_icon'     => 'fire',
                'created_by'     => 1,
            ],
            [
                'name'           => '20 Check-ins del Mes 💪',
                'description'    => 'Completa 20 check-ins en 28 días. No tiene que ser todos los días — solo demuestra que el entrenamiento es parte de tu vida, no un evento aislado.',
                'challenge_type' => 'checkins',
                'target_value'   => 20,
                'unit'           => 'check-ins',
                'start_date'     => $today,
                'end_date'       => $end2,
                'plan_access'    => 'metodo,elite',
                'badge_icon'     => 'medal',
                'created_by'     => 1,
            ],
        ];

        $stmt = $db->prepare("
            INSERT INTO challenges (title, description, challenge_type, goal_value, unit, start_date, end_date, plan_access, badge_icon, created_by, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");
        foreach ($challenges as $c) {
            $stmt->execute([$c['name'], $c['description'], $c['challenge_type'], $c['target_value'], $c['unit'], $c['start_date'], $c['end_date'], $c['plan_access'], $c['badge_icon'], $c['created_by'], $GLOBALS['now']]);
        }
    }
);

// ─── 6. COMMUNITY POSTS ───────────────────────────────────────────────────
echo "\n--- coach_community_posts ---\n";
try {
    $existingPosts = (int)$db->query("SELECT COUNT(*) FROM coach_community_posts")->fetchColumn();
    if ($existingPosts >= 5) {
        echo "  SKIP  coach_community_posts ($existingPosts rows ya existen)\n";
    } else {
        $posts = [
            ['¡Bienvenidos a la comunidad WellCore! 🔴 Este es el espacio donde compartimos progreso, dudas y logros. Regla #1: Aquí celebramos, no comparamos. ¿Quién está listo para los próximos 160 días?', 'post', date('Y-m-d H:i:s', strtotime('-4 days'))],
            ['💡 TIP: El músculo no se construye en el gym — se construye mientras duermes. Si entrenas duro pero duermes 5 horas, estás dejando el 40% de tu progreso sobre la mesa. Prioridad #1: 7-9 horas de sueño. ¿Cuántas horas duermes normalmente?', 'tip', date('Y-m-d H:i:s', strtotime('-3 days'))],
            ['🏆 ¿Ya hiciste tu check-in hoy? Los clientes que hacen check-in los primeros 7 días tienen 78% más retención al mes. El hábito se construye en los primeros días. ¡Hoy cuenta! Reto activo: Semana de Fuego 🔥', 'post', date('Y-m-d H:i:s', strtotime('-2 days'))],
            ['🔬 DATO: La proteína no es solo para "ponerse grande". 1.6-2.2g/kg de peso corporal: ✅ Reduce el hambre 60% ✅ Preserva músculo en déficit ✅ Acelera el metabolismo. ¿Cuánta proteína comes al día? Sé honesto 👇', 'tip', date('Y-m-d H:i:s', strtotime('-1 day'))],
            ['📸 Las fotos de progreso son TU herramienta, no una comparación. Sube tu foto inicial en la sección Fotos del portal — en 30 días verás el cambio aunque la báscula no lo muestre. El cuerpo cambia antes de que los números cambien.', 'achievement', date('Y-m-d H:i:s', strtotime('-2 hours'))],
        ];
        $stmt = $db->prepare("INSERT INTO coach_community_posts (coach_id, content, type, likes, created_at) VALUES (?, ?, ?, 0, ?)");
        foreach ($posts as $p) {
            $stmt->execute([$coachId, $p[0], $p[1], $p[2]]);
        }
        echo "  OK    coach_community_posts (" . count($posts) . " posts)\n";
        $ok++;
    }
} catch (\PDOException $e) {
    $errors[] = "coach_community_posts: " . $e->getMessage();
    echo "  ERROR coach_community_posts: " . $e->getMessage() . "\n";
}

// ─── 7. COACH AVAILABILITY ────────────────────────────────────────────────
echo "\n--- coach_availability ---\n";
seedTable(
    $db,
    'coach_availability',
    "SELECT COUNT(*) FROM coach_availability",
    function (PDO $db) use ($coachId) {
        // Lunes-Viernes 8am-12pm y 2pm-6pm
        $slots = [
            ['day' => 1, 'start' => '08:00:00', 'end' => '12:00:00'], // Lunes AM
            ['day' => 1, 'start' => '14:00:00', 'end' => '18:00:00'], // Lunes PM
            ['day' => 2, 'start' => '08:00:00', 'end' => '12:00:00'], // Martes AM
            ['day' => 2, 'start' => '14:00:00', 'end' => '18:00:00'], // Martes PM
            ['day' => 3, 'start' => '08:00:00', 'end' => '12:00:00'], // Miércoles AM
            ['day' => 4, 'start' => '08:00:00', 'end' => '12:00:00'], // Jueves AM
            ['day' => 4, 'start' => '14:00:00', 'end' => '18:00:00'], // Jueves PM
            ['day' => 5, 'start' => '08:00:00', 'end' => '12:00:00'], // Viernes AM
        ];

        $stmt = $db->prepare("
            INSERT INTO coach_availability (coach_id, day_of_week, time_start, time_end, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        foreach ($slots as $s) {
            $stmt->execute([$GLOBALS['coachId'], $s['day'], $s['start'], $s['end']]);
        }
    }
);

// ─── 8. XP INICIAL PARA CLIENTES DEMO ─────────────────────────────────────
echo "\n--- client_xp (demo) ---\n";
try {
    $clients = $db->query("SELECT id, client_code FROM clients WHERE status = 'activo' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $xpData  = [
        ['xp' => 850,  'level' => 3, 'streak' => 12, 'date' => date('Y-m-d', strtotime('-1 day'))],
        ['xp' => 1450, 'level' => 4, 'streak' => 21, 'date' => date('Y-m-d', strtotime('-1 day'))],
        ['xp' => 320,  'level' => 1, 'streak' => 3,  'date' => date('Y-m-d', strtotime('-1 day'))],
        ['xp' => 650,  'level' => 2, 'streak' => 7,  'date' => date('Y-m-d', strtotime('-1 day'))],
        ['xp' => 200,  'level' => 1, 'streak' => 1,  'date' => date('Y-m-d')],
    ];
    $stmt = $db->prepare("
        INSERT IGNORE INTO client_xp (client_id, xp_total, level, streak_days, streak_last_date)
        VALUES (?, ?, ?, ?, ?)
    ");
    $i = 0;
    foreach ($clients as $c) {
        $d = $xpData[$i % count($xpData)];
        $stmt->execute([$c['client_code'], $d['xp'], $d['level'], $d['streak'], $d['date']]);
        $i++;
    }
    echo "  OK    client_xp ({$i} clientes)\n";
} catch (\PDOException $e) {
    echo "  ERROR client_xp: " . $e->getMessage() . "\n";
}

// ─── RESUMEN ──────────────────────────────────────────────────────────────
echo "\n========================================\n";
echo "Completado: $ok tablas sembradas\n";
echo "Saltados:   $skip (ya tenían datos)\n";
echo "Errores:    " . count($errors) . "\n";
if ($errors) {
    foreach ($errors as $e) echo "  - $e\n";
}
echo "Fecha:      " . date('Y-m-d H:i:s T') . "\n";
echo "========================================\n\n";
