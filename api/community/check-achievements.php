<?php
/**
 * Check & Award Achievements
 * POST — Checks all applicable achievements for the authenticated client.
 * Called after key actions (checkin, photo, measurement, community_post, pr, photo_review, nutrition).
 * Body: { "trigger": "checkin"|"photo"|"measurement"|"community_post"|"pr"|"photo_review"|"nutrition" }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$client = authenticateClient();
$db = getDB();
$cid  = (int)$client['id'];
$plan = $client['plan'] ?? 'esencial';

$body = getJsonBody();
$trigger = $body['trigger'] ?? '';

$awarded = [];

// Helper: award achievement if not already earned
function awardIfNew(PDO $db, int $clientId, string $type, string $title, string $desc, string $icon, string $audience = 'all'): ?array {
    $check = $db->prepare("SELECT id FROM achievements WHERE client_id = ? AND achievement_type = ?");
    $check->execute([$clientId, $type]);
    if ($check->fetch()) return null;

    $db->prepare("
        INSERT INTO achievements (client_id, achievement_type, title, description, icon)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$clientId, $type, $title, $desc, $icon]);
    $achievementId = (int)$db->lastInsertId();

    $postContent = "desbloqueó el logro: \"$title\" — $desc";
    $db->prepare("
        INSERT INTO community_posts (client_id, content, post_type, achievement_id, audience)
        VALUES (?, ?, 'achievement', ?, ?)
    ")->execute([$clientId, $postContent, $achievementId, $audience]);

    return [
        'achievement_type' => $type,
        'title'            => $title,
        'description'      => $desc,
        'icon'             => $icon,
    ];
}

// ── Datos base del cliente ────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT subscription_start, fecha_inicio FROM clients WHERE id = ?");
$stmt->execute([$cid]);
$clientRow  = $stmt->fetch();
$startDate  = $clientRow['subscription_start'] ?? $clientRow['fecha_inicio'] ?? null;
$daysActive = $startDate ? (int)((time() - strtotime($startDate)) / 86400) : 0;

// ── Conteos de actividad ──────────────────────────────────────────────────────
$checkinStmt = $db->prepare("SELECT COUNT(*) FROM checkins WHERE client_id = ?");
$checkinStmt->execute([$cid]);
$checkinCount = (int)$checkinStmt->fetchColumn();

$communityStmt = $db->prepare("SELECT COUNT(*) FROM community_posts WHERE client_id = ? AND post_type != 'achievement'");
$communityStmt->execute([$cid]);
$communityCount = (int)$communityStmt->fetchColumn();

$prCount = 0;
try {
    $prStmt = $db->prepare("SELECT COUNT(*) FROM personal_records WHERE client_id = ?");
    $prStmt->execute([$cid]);
    $prCount = (int)$prStmt->fetchColumn();
} catch (Exception $e) { /* tabla puede no existir aún */ }

$photoReviewCount = 0;
try {
    $prStmt2 = $db->prepare("SELECT COUNT(*) FROM photo_reviews WHERE client_id = ?");
    $prStmt2->execute([$cid]);
    $photoReviewCount = (int)$prStmt2->fetchColumn();
} catch (Exception $e) { /* tabla puede no existir aún */ }

$nutritionCount = 0;
try {
    $nutStmt = $db->prepare("SELECT COUNT(*) FROM nutrition_usage WHERE client_id = ?");
    $nutStmt->execute([$cid]);
    $nutritionCount = (int)$nutStmt->fetchColumn();
} catch (Exception $e) { /* tabla puede no existir */ }

// ── Logros universales por tiempo ─────────────────────────────────────────────
if ($daysActive >= 7) {
    $r = awardIfNew($db, $cid, 'first_week', 'Primera Semana', '7 días activo en el programa', 'calendar-week');
    if ($r) $awarded[] = $r;
}
if ($daysActive >= 30) {
    $r = awardIfNew($db, $cid, '30_days', '30 Días Activo', 'Un mes completo en el programa', 'medal');
    if ($r) $awarded[] = $r;
}
if ($daysActive >= 90) {
    $r = awardIfNew($db, $cid, '90_days', '3 Meses Fuerte', '90 días de constancia continua', 'award');
    if ($r) $awarded[] = $r;
}
if ($daysActive >= 180) {
    $r = awardIfNew($db, $cid, '180_days', '6 Meses Inquebrantable', 'Medio año transformando tu cuerpo', 'mountain-sun');
    if ($r) $awarded[] = $r;
}
if ($daysActive >= 365) {
    $r = awardIfNew($db, $cid, '365_days', 'Un Año de Transformación', 'Un año completo de compromiso y resultados', 'crown');
    if ($r) $awarded[] = $r;
}

// ── Logros universales por check-in count ─────────────────────────────────────
if ($checkinCount >= 4) {
    $r = awardIfNew($db, $cid, 'streak_4', 'Mes de Constancia', '4 check-ins completados', 'calendar-check');
    if ($r) $awarded[] = $r;
}
if ($checkinCount >= 7) {
    $r = awardIfNew($db, $cid, 'streak_7', 'Racha Imparable', '7 check-ins — semana tras semana', 'fire');
    if ($r) $awarded[] = $r;
}
if ($checkinCount >= 12) {
    $r = awardIfNew($db, $cid, 'streak_12', 'Trimestre de Hierro', '12 check-ins consecutivos sin fallar', 'shield-halved');
    if ($r) $awarded[] = $r;
}
if ($checkinCount >= 26) {
    $r = awardIfNew($db, $cid, 'streak_26', 'Medio Año en Llamas', '26 check-ins — mitad del año completada', 'fire-flame-curved');
    if ($r) $awarded[] = $r;
}
if ($checkinCount >= 52) {
    $r = awardIfNew($db, $cid, 'streak_52', 'Leyenda WellCore', '52 check-ins — un año entero de disciplina', 'infinity');
    if ($r) $awarded[] = $r;
}

// ── Logros universales por comunidad ─────────────────────────────────────────
if ($communityCount >= 5) {
    $r = awardIfNew($db, $cid, 'community_5', 'Voz Constante', '5 posts compartidos en la comunidad', 'microphone');
    if ($r) $awarded[] = $r;
}
if ($communityCount >= 10) {
    $r = awardIfNew($db, $cid, 'community_10', 'Pilar de la Comunidad', '10 publicaciones — eres referente aquí', 'users');
    if ($r) $awarded[] = $r;
}
if ($communityCount >= 25) {
    $r = awardIfNew($db, $cid, 'community_25', 'Embajador WellCore', '25 posts — la comunidad te sigue', 'star');
    if ($r) $awarded[] = $r;
}

// ── Logros universales por récords personales ─────────────────────────────────
if ($prCount >= 1) {
    $r = awardIfNew($db, $cid, 'first_pr', 'Primer Récord Personal', 'Registraste tu primer PR en el sistema', 'dumbbell');
    if ($r) $awarded[] = $r;
}
if ($prCount >= 3) {
    $r = awardIfNew($db, $cid, 'pr_3', 'Colección de Poder', '3 récords personales registrados', 'chart-line');
    if ($r) $awarded[] = $r;
}
if ($prCount >= 5) {
    $r = awardIfNew($db, $cid, 'pr_5', 'Arsenal Deportivo', '5 récords personales — datos reales de fuerza', 'bolt');
    if ($r) $awarded[] = $r;
}
if ($prCount >= 8) {
    $r = awardIfNew($db, $cid, 'pr_8', 'Máquina de PRs', '8 ejercicios con récord registrado', 'rocket');
    if ($r) $awarded[] = $r;
}

// ── ESENCIAL ─────────────────────────────────────────────────────────────────
if ($plan === 'esencial') {
    if ($daysActive >= 30) {
        $r = awardIfNew($db, $cid, 'esencial_1month', 'Esencial: Mes Uno', 'Completaste tu primer mes en plan Esencial', 'seedling');
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 90) {
        $r = awardIfNew($db, $cid, 'esencial_3months', 'Esencial Comprometido', '3 meses firme en plan Esencial', 'heart');
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 180) {
        $r = awardIfNew($db, $cid, 'esencial_6months', 'Esencial Legado', '6 meses en Esencial — base sólida construida', 'gem');
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 365) {
        $r = awardIfNew($db, $cid, 'esencial_year', 'Un Año Esencial', 'Un año completo en plan Esencial', 'trophy');
        if ($r) $awarded[] = $r;
    }
    if ($checkinCount >= 4) {
        $r = awardIfNew($db, $cid, 'esencial_streak_4', 'Disciplina Esencial', '4 semanas seguidas con check-in en Esencial', 'calendar-check');
        if ($r) $awarded[] = $r;
    }
    if ($prCount >= 1) {
        $r = awardIfNew($db, $cid, 'esencial_pr_first', 'Fuerza Base', 'Tu primer récord personal como miembro Esencial', 'dumbbell');
        if ($r) $awarded[] = $r;
    }
    if ($communityCount >= 5) {
        $r = awardIfNew($db, $cid, 'esencial_community', 'Esencial con Voz', '5 posts en la comunidad siendo Esencial', 'comments');
        if ($r) $awarded[] = $r;
    }
}

// ── MÉTODO ────────────────────────────────────────────────────────────────────
if ($plan === 'metodo') {
    if ($daysActive >= 30) {
        $r = awardIfNew($db, $cid, 'metodo_1month', 'Método: Primer Mes', 'Un mes aplicando el Método WellCore', 'compass');
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 90) {
        $r = awardIfNew($db, $cid, 'metodo_3months', 'Método Dominado', '3 meses con el Método — ya es parte de ti', 'award');
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 180) {
        $r = awardIfNew($db, $cid, 'metodo_6months', 'Método Veterano', '6 meses en Método — disciplina probada', 'medal');
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 365) {
        $r = awardIfNew($db, $cid, 'metodo_year', 'Método Elite Ready', 'Un año en Método — estás listo para más', 'trophy');
        if ($r) $awarded[] = $r;
    }
    if ($prCount >= 1) {
        $r = awardIfNew($db, $cid, 'metodo_pr_first', 'PR del Método', 'Primer récord personal en plan Método', 'dumbbell');
        if ($r) $awarded[] = $r;
    }
    if ($prCount >= 5) {
        $r = awardIfNew($db, $cid, 'metodo_pr_5', 'Fuerza Método', '5 PRs registrados en plan Método', 'chart-line');
        if ($r) $awarded[] = $r;
    }
    if ($checkinCount >= 8) {
        $r = awardIfNew($db, $cid, 'metodo_streak_8', 'Disciplina Método', '8 check-ins seguidos aplicando el Método', 'shield-halved');
        if ($r) $awarded[] = $r;
    }
    if ($communityCount >= 5) {
        $r = awardIfNew($db, $cid, 'metodo_community', 'Método en Comunidad', '5 posts compartidos siendo miembro Método', 'comments');
        if ($r) $awarded[] = $r;
    }
    // Logro combinado: 90 días + 5 PRs + 5 posts
    if ($daysActive >= 90 && $prCount >= 5 && $communityCount >= 5) {
        $r = awardIfNew($db, $cid, 'metodo_complete', 'Método Certificado', '90 días + 5 PRs + 5 posts en Método', 'star');
        if ($r) $awarded[] = $r;
    }
}

// ── ELITE ─────────────────────────────────────────────────────────────────────
if ($plan === 'elite') {
    // Welcome inmediato (día 0)
    $r = awardIfNew($db, $cid, 'elite_welcome', 'Bienvenido a Elite', 'Accediste al plan más completo de WellCore', 'crown');
    if ($r) $awarded[] = $r;

    if ($daysActive >= 30) {
        $r = awardIfNew($db, $cid, 'elite_1month', 'Élite: Primer Mes', 'Un mes en el nivel más alto de WellCore', 'star');
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 90) {
        $r = awardIfNew($db, $cid, 'elite_3months', 'Élite Confirmado', '3 meses en Elite — compromiso total', 'trophy');
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 180) {
        $r = awardIfNew($db, $cid, 'elite_6months', 'Veterano de Élite', '6 meses en Elite — disciplina y resultados', 'gem');
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 365) {
        $r = awardIfNew($db, $cid, 'elite_year', 'Un Año de Élite', 'Un año en Elite — pocos llegan aquí', 'infinity');
        if ($r) $awarded[] = $r;
    }
    // Nutrición
    if ($nutritionCount >= 7) {
        $r = awardIfNew($db, $cid, 'elite_nutrition_streak', 'Nutrición al Día', '7 días usando el análisis nutricional', 'apple-whole');
        if ($r) $awarded[] = $r;
    }
    if ($nutritionCount >= 30) {
        $r = awardIfNew($db, $cid, 'elite_nutrition_30', 'Maestro Nutricional', '30 análisis nutricionales completados', 'utensils');
        if ($r) $awarded[] = $r;
    }
    // Photo reviews con IA
    if ($photoReviewCount >= 1) {
        $r = awardIfNew($db, $cid, 'elite_ai_review_first', 'Análisis Inteligente', 'Tu primera foto analizada con inteligencia artificial', 'robot');
        if ($r) $awarded[] = $r;
    }
    if ($photoReviewCount >= 5) {
        $r = awardIfNew($db, $cid, 'elite_ai_review_5', 'Archivo Inteligente', '5 revisiones de progreso con IA', 'layer-group');
        if ($r) $awarded[] = $r;
    }
    if ($photoReviewCount >= 10) {
        $r = awardIfNew($db, $cid, 'elite_ai_review_10', 'Laboratorio Personal', '10 análisis de foto con IA — datos reales', 'brain');
        if ($r) $awarded[] = $r;
    }
    // PRs elite
    if ($prCount >= 5) {
        $r = awardIfNew($db, $cid, 'elite_pr_beast', 'Bestia Élite', '5 récords personales siendo miembro Elite', 'hand-fist');
        if ($r) $awarded[] = $r;
    }
    if ($prCount >= 8) {
        $r = awardIfNew($db, $cid, 'elite_pr_complete', 'Base de Datos de Fuerza', '8 ejercicios con récord registrado en Elite', 'chart-line');
        if ($r) $awarded[] = $r;
    }
    // Comunidad
    if ($communityCount >= 10) {
        $r = awardIfNew($db, $cid, 'elite_community_leader', 'Líder Élite', '10 posts en la comunidad siendo Elite', 'users');
        if ($r) $awarded[] = $r;
    }
    // Logro combinado all-star
    if ($daysActive >= 90 && $prCount >= 5 && $photoReviewCount >= 5 && $communityCount >= 5) {
        $r = awardIfNew($db, $cid, 'elite_allstar', 'All-Star WellCore', '3 meses + 5 PRs + 5 análisis AI + 5 posts', 'rocket');
        if ($r) $awarded[] = $r;
    }
}

// ── RISE ──────────────────────────────────────────────────────────────────────
if ($plan === 'rise') {
    $audience = 'rise';
    if ($daysActive >= 7) {
        $r = awardIfNew($db, $cid, 'rise_day7', 'RISE Día 7', 'Primera semana del reto completada', 'bolt', $audience);
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 15) {
        $r = awardIfNew($db, $cid, 'rise_day15', 'RISE Medio Camino', 'Llegaste a la mitad del reto', 'flag-checkered', $audience);
        if ($r) $awarded[] = $r;
    }
    if ($daysActive >= 30) {
        $r = awardIfNew($db, $cid, 'rise_day30', 'RISE Completado', 'Completaste los 30 días del reto', 'trophy', $audience);
        if ($r) $awarded[] = $r;
    }
    if ($checkinCount >= 4) {
        $r = awardIfNew($db, $cid, 'rise_streak_4', 'RISE Consistencia', '4 check-ins en el reto — disciplina real', 'fire', $audience);
        if ($r) $awarded[] = $r;
    }
}

// ── Trigger-based ─────────────────────────────────────────────────────────────

if ($trigger === 'checkin') {
    $r = awardIfNew($db, $cid, 'first_checkin', 'Primer Check-in', 'Enviaste tu primer check-in semanal', 'clipboard-check');
    if ($r) $awarded[] = $r;
}

if ($trigger === 'photo') {
    $r = awardIfNew($db, $cid, 'first_photo', 'Primera Foto', 'Subiste tu primera foto de progreso', 'camera');
    if ($r) $awarded[] = $r;
}

if ($trigger === 'measurement' && $plan === 'rise') {
    $r = awardIfNew($db, $cid, 'rise_first_measurement', 'Primera Medición RISE', 'Registraste tu primera medición', 'weight-scale', 'rise');
    if ($r) $awarded[] = $r;
}

if ($trigger === 'community_post') {
    $r = awardIfNew($db, $cid, 'first_community', 'Voz de la Comunidad', 'Publicaste en la comunidad por primera vez', 'comments');
    if ($r) $awarded[] = $r;
    if ($plan === 'rise' && $communityCount >= 3) {
        $r = awardIfNew($db, $cid, 'rise_community', 'RISE Comunidad', '3 posts en la comunidad del reto', 'comments', 'rise');
        if ($r) $awarded[] = $r;
    }
}

if ($trigger === 'photo_review' && $plan === 'elite') {
    // Re-check photo review counts (may have just incremented)
    try {
        $prRecheck = $db->prepare("SELECT COUNT(*) FROM photo_reviews WHERE client_id = ?");
        $prRecheck->execute([$cid]);
        $freshCount = (int)$prRecheck->fetchColumn();
        if ($freshCount >= 1) {
            $r = awardIfNew($db, $cid, 'elite_ai_review_first', 'Análisis Inteligente', 'Tu primera foto analizada con inteligencia artificial', 'robot');
            if ($r) $awarded[] = $r;
        }
        if ($freshCount >= 5) {
            $r = awardIfNew($db, $cid, 'elite_ai_review_5', 'Archivo Inteligente', '5 revisiones de progreso con IA', 'layer-group');
            if ($r) $awarded[] = $r;
        }
        if ($freshCount >= 10) {
            $r = awardIfNew($db, $cid, 'elite_ai_review_10', 'Laboratorio Personal', '10 análisis de foto con IA — datos reales', 'brain');
            if ($r) $awarded[] = $r;
        }
    } catch (Exception $e) { /* tabla puede no existir */ }
}

if ($trigger === 'nutrition' && $plan === 'elite') {
    try {
        $nutRecheck = $db->prepare("SELECT COUNT(*) FROM nutrition_usage WHERE client_id = ?");
        $nutRecheck->execute([$cid]);
        $freshNut = (int)$nutRecheck->fetchColumn();
        if ($freshNut >= 7) {
            $r = awardIfNew($db, $cid, 'elite_nutrition_streak', 'Nutrición al Día', '7 días usando el análisis nutricional', 'apple-whole');
            if ($r) $awarded[] = $r;
        }
        if ($freshNut >= 30) {
            $r = awardIfNew($db, $cid, 'elite_nutrition_30', 'Maestro Nutricional', '30 análisis nutricionales completados', 'utensils');
            if ($r) $awarded[] = $r;
        }
    } catch (Exception $e) { /* tabla puede no existir */ }
}

respond([
    'ok'      => true,
    'awarded' => $awarded,
    'count'   => count($awarded),
]);
