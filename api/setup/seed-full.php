<?php
/**
 * WellCore Fitness — Seed completo para desarrollo local
 * Puebla TODAS las tablas con datos realistas para testing.
 *
 * CLI: php api/setup/seed-full.php
 * HTTP: GET /api/setup/seed-full.php (requires admin auth or CLI)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db = getDB();
$results = [];

function log_result(string $label, int $count = 0): void {
    global $results;
    $results[] = ['label' => $label, 'count' => $count];
    if (php_sapi_name() === 'cli') {
        echo "  [OK] $label" . ($count ? " ($count rows)" : "") . "\n";
    }
}

echo php_sapi_name() === 'cli' ? "\n  WELLCORE — SEED COMPLETO\n  ========================\n\n" : '';

// ── Get existing client IDs ──────────────────────────────────────────────────
$clients = $db->query("SELECT id, client_code, name, plan FROM clients ORDER BY id")->fetchAll();
if (empty($clients)) {
    echo "  ERROR: No hay clientes en la BD. Ejecuta seed.php primero.\n";
    exit(1);
}

$clientIds = array_column($clients, 'id');
$adminIds  = $db->query("SELECT id FROM admins")->fetchAll(PDO::FETCH_COLUMN);

// ── 1. Training Logs (ultimas 8 semanas) ─────────────────────────────────────
$db->query("DELETE FROM training_logs");
$stmt = $db->prepare("
    INSERT INTO training_logs (client_id, log_date, completed, year_num, week_num)
    VALUES (?, ?, ?, ?, ?)
");
$count = 0;
foreach ($clientIds as $idx => $cid) {
    $daysPerWeek = [5, 4, 3][$idx] ?? 3;
    for ($w = 0; $w < 8; $w++) {
        $weekStart = strtotime("-{$w} weeks monday");
        $allDays = [0, 1, 2, 3, 4, 5, 6];
        shuffle($allDays);
        $trainDays = array_slice($allDays, 0, $daysPerWeek);
        foreach ($trainDays as $dayOffset) {
            $date = date('Y-m-d', $weekStart + ($dayOffset * 86400));
            if ($date > date('Y-m-d')) continue;
            $year = (int)date('Y', strtotime($date));
            $week = (int)date('W', strtotime($date));
            $stmt->execute([$cid, $date, 1, $year, $week]);
            $count++;
        }
    }
}
log_result('Training logs (8 semanas)', $count);

// ── 2. Metrics (ultimos 3 meses, semanal) ───────────────────────────────────
$db->query("DELETE FROM metrics");
$stmt = $db->prepare("
    INSERT INTO metrics (client_id, log_date, peso, porcentaje_musculo, porcentaje_grasa, notas)
    VALUES (?, ?, ?, ?, ?, ?)
");
$count = 0;
$baseMetrics = [
    $clientIds[0] => ['peso' => 78.5, 'musculo' => 38.2, 'grasa' => 18.5],
    $clientIds[1] => ['peso' => 62.0, 'musculo' => 30.5, 'grasa' => 24.0],
    $clientIds[2] => ['peso' => 85.0, 'musculo' => 36.0, 'grasa' => 22.0],
];
foreach ($clientIds as $cid) {
    $base = $baseMetrics[$cid] ?? ['peso' => 75.0, 'musculo' => 35.0, 'grasa' => 20.0];
    for ($w = 12; $w >= 0; $w--) {
        $date = date('Y-m-d', strtotime("-{$w} weeks"));
        $progress = (12 - $w) / 12;
        $variation = (mt_rand(-5, 5)) / 10;
        $peso    = round($base['peso']    - ($progress * 2.5) + $variation, 2);
        $musculo = round($base['musculo'] + ($progress * 1.5) + $variation, 2);
        $grasa   = round($base['grasa']   - ($progress * 2.0) + $variation, 2);
        $notas = $w === 0 ? 'Medicion mas reciente' : null;
        $stmt->execute([$cid, $date, $peso, $musculo, $grasa, $notas]);
        $count++;
    }
}
log_result('Metrics (13 semanas x 3 clientes)', $count);

// ── 3. Checkins (ultimas 6 semanas, solo Elite = client 3) ───────────────────
$db->query("DELETE FROM checkins");
$stmt = $db->prepare("
    INSERT INTO checkins (client_id, week_label, checkin_date, bienestar, dias_entrenados, nutricion, comentario, coach_reply, replied_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$count = 0;
$eliteId = $clientIds[2];
$bienestarValues = [7, 8, 6, 8, 9, 7];
$diasValues      = [4, 5, 3, 5, 4, 3];
$nutriValues     = ['Si', 'Parcial', 'No', 'Si', 'Si', 'Parcial'];
$comentarios     = [
    'Buena semana, logre hacer todas las series.',
    'Viaje de trabajo, no pude entrenar jueves.',
    'Semana dificil, mucho estres en el trabajo.',
    'Me siento fuerte, subi peso en sentadilla.',
    'Excelente semana, mejor adherencia nutricional.',
    'Empezando a notar cambios visibles.',
];
$replies = [
    'Excelente! Sigue asi con la consistencia.',
    'No te preocupes, lo importante es retomar. Ajustamos para la siguiente.',
    null,
    'Muy bien! Subimos 2.5kg en sentadilla la proxima.',
    null,
    null,
];
for ($w = 5; $w >= 0; $w--) {
    $date = date('Y-m-d', strtotime("-{$w} weeks friday"));
    $weekLabel = date('o', strtotime($date)) . '-W' . str_pad(date('W', strtotime($date)), 2, '0', STR_PAD_LEFT);
    $reply = $replies[$w] ?? null;
    $repliedAt = $reply ? date('Y-m-d H:i:s', strtotime($date) + 86400) : null;
    $stmt->execute([$eliteId, $weekLabel, $date, $bienestarValues[$w], $diasValues[$w], $nutriValues[$w], $comentarios[$w], $reply, $repliedAt]);
    $count++;
}
log_result('Checkins (6 semanas Elite)', $count);

// ── 4. Assigned Plans ────────────────────────────────────────────────────────
$db->query("DELETE FROM assigned_plans");
$stmt = $db->prepare("
    INSERT INTO assigned_plans (client_id, plan_type, content, version, assigned_by, valid_from, active)
    VALUES (?, ?, ?, ?, ?, ?, 1)
");
$count = 0;
$assignedBy = $adminIds[0] ?? 1;
foreach ($clientIds as $i => $cid) {
    $stmt->execute([$cid, 'entrenamiento', '<h2>Plan de Entrenamiento - Semana Actual</h2><p>Push/Pull/Legs - 4 dias por semana</p><ul><li>Dia 1: Push (pecho, hombro, triceps)</li><li>Dia 2: Pull (espalda, biceps)</li><li>Dia 3: Pierna (sentadilla, peso muerto)</li><li>Dia 4: Upper (compuestos)</li></ul>', 1, $assignedBy, date('Y-m-d', strtotime('-4 weeks'))]);
    $count++;

    if ($i >= 1) {
        $kcal = $i === 1 ? 1800 : 2400;
        $prot = round($kcal * 0.3 / 4);
        $carbs = round($kcal * 0.4 / 4);
        $fat = round($kcal * 0.3 / 9);
        $stmt->execute([$cid, 'nutricion', "<h2>Plan Nutricional</h2><p>Objetivo: {$kcal} kcal/dia</p><ul><li>Proteina: {$prot}g</li><li>Carbos: {$carbs}g</li><li>Grasas: {$fat}g</li></ul>", 1, $assignedBy, date('Y-m-d', strtotime('-3 weeks'))]);
        $count++;
    }

    if ($i === 2) {
        $stmt->execute([$cid, 'habitos', '<h2>Plan de Habitos</h2><ul><li>Dormir 7-8h minimo</li><li>10,000 pasos diarios</li><li>Hidratacion: 3L agua/dia</li><li>Meditacion 10 min/dia</li><li>Sin pantallas 1h antes de dormir</li></ul>', 1, $assignedBy, date('Y-m-d', strtotime('-2 weeks'))]);
        $count++;
    }
}
log_result('Assigned plans', $count);

// ── 5. Weight Logs (ultimas 4 semanas) ───────────────────────────────────────
$db->query("DELETE FROM weight_logs");
$stmt = $db->prepare("
    INSERT INTO weight_logs (id, client_id, exercise, weight_kg, `sets`, reps, rpe, notes, week_number, `year`, `date`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$count = 0;
$exercises = [
    ['Sentadilla', 80, 4, 8],
    ['Press Banca', 60, 4, 8],
    ['Peso Muerto', 100, 3, 5],
    ['Press Militar', 40, 3, 10],
    ['Remo con Barra', 65, 4, 8],
    ['Curl Biceps', 15, 3, 12],
];
foreach ($clientIds as $idx => $cid) {
    $multiplier = ($idx === 1) ? 0.6 : 1.0;
    for ($w = 3; $w >= 0; $w--) {
        $weekDate = strtotime("-{$w} weeks");
        $weekNum  = (int)date('W', $weekDate);
        $yearNum  = (int)date('Y', $weekDate);
        foreach ($exercises as $ex) {
            $progression = (3 - $w) * 2.5;
            $weight = round(($ex[1] * $multiplier) + $progression, 1);
            $rpe    = round(7 + (mt_rand(0, 15) / 10), 1);
            $id     = 'w-' . bin2hex(random_bytes(4));
            $date   = date('Y-m-d H:i:s', $weekDate + (mt_rand(0, 4) * 86400));
            $stmt->execute([$id, (string)$cid, $ex[0], $weight, $ex[2], $ex[3], $rpe, null, $weekNum, $yearNum, $date]);
            $count++;
        }
    }
}
log_result('Weight logs (4 semanas x 6 ejercicios x 3 clientes)', $count);

// ── 6. Shop Categories & Brands ──────────────────────────────────────────────
$db->query("DELETE FROM shop_order_items");
$db->query("DELETE FROM shop_orders");
$db->query("DELETE FROM shop_analytics");
$db->query("DELETE FROM shop_products");
$db->query("DELETE FROM shop_categories");
$db->query("DELETE FROM shop_brands");

$db->query("INSERT INTO shop_categories (name, slug, icon, sort_order) VALUES
    ('Proteina', 'proteina', '//P', 1),
    ('Creatina', 'creatina', '//C', 2),
    ('Pre-Entreno', 'pre-entreno', '//PE', 3),
    ('Vitaminas', 'vitaminas', '//V', 4),
    ('Accesorios', 'accesorios', '//A', 5)
");
log_result('Shop categories', 5);

$db->query("INSERT INTO shop_brands (name, slug, logo_url) VALUES
    ('Optimum Nutrition', 'optimum-nutrition', '/images/brands/on.png'),
    ('MyProtein', 'myprotein', '/images/brands/myprotein.png'),
    ('MuscleTech', 'muscletech', '/images/brands/muscletech.png'),
    ('BSN', 'bsn', '/images/brands/bsn.png'),
    ('Universal', 'universal', '/images/brands/universal.png')
");
log_result('Shop brands', 5);

// Query real IDs (auto_increment may not start at 1)
$catIds = $db->query("SELECT slug, id FROM shop_categories")->fetchAll(PDO::FETCH_KEY_PAIR);
$brIds  = $db->query("SELECT slug, id FROM shop_brands")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── 7. Shop Products ─────────────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO shop_products (slug, name, brand_id, category_id, description, price_cop, compare_price, image_url, image_alt, servings, weight, flavors, tags, stock, stock_status, featured)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$products = [
    ['gold-standard-whey', 'Gold Standard 100% Whey', $brIds['optimum-nutrition'], $catIds['proteina'], 'Proteina whey de alta calidad. 24g proteina por scoop, baja en grasa y azucar.', 189900, 219900, '/images/shop/on-whey.jpg', 'Gold Standard Whey Protein', '30', '907g', '["Chocolate","Vainilla","Fresa"]', '["bestseller","proteina"]', 15, 'in_stock', 1],
    ['myprotein-impact-whey', 'Impact Whey Protein', $brIds['myprotein'], $catIds['proteina'], 'Whey protein de alta calidad con 21g de proteina por porcion.', 149900, 179900, '/images/shop/myprotein-whey.jpg', 'MyProtein Impact Whey', '40', '1kg', '["Chocolate","Natural","Cookies"]', '["proteina"]', 20, 'in_stock', 0],
    ['muscletech-creatine', 'Platinum 100% Creatine', $brIds['muscletech'], $catIds['creatina'], 'Creatina monohidratada pura. 5g por porcion. Sin sabor.', 89900, 109900, '/images/shop/mt-creatine.jpg', 'MuscleTech Creatine', '80', '400g', 'null', '["creatina","bestseller"]', 25, 'in_stock', 1],
    ['bsn-no-xplode', 'N.O.-XPLODE Pre-Workout', $brIds['bsn'], $catIds['pre-entreno'], 'Pre-entreno con cafeina, beta-alanina y creatina.', 159900, 189900, '/images/shop/bsn-noxplode.jpg', 'BSN NO Xplode', '30', '555g', '["Fruit Punch","Blue Razz"]', '["pre-entreno"]', 10, 'in_stock', 0],
    ['on-creatine-caps', 'Creatine Capsules', $brIds['optimum-nutrition'], $catIds['creatina'], 'Creatina monohidratada en capsulas.', 99900, null, '/images/shop/on-creatine-caps.jpg', 'ON Creatine Capsules', '100', '200 caps', 'null', '["creatina"]', 18, 'in_stock', 0],
    ['universal-animal-pak', 'Animal Pak Multivitamin', $brIds['universal'], $catIds['vitaminas'], 'Paquete completo de vitaminas, minerales y aminoacidos para atletas.', 199900, 239900, '/images/shop/animal-pak.jpg', 'Universal Animal Pak', '44', '44 paks', 'null', '["vitaminas","bestseller"]', 8, 'in_stock', 1],
    ['myprotein-pre', 'THE Pre-Workout', $brIds['myprotein'], $catIds['pre-entreno'], 'Pre-entreno con 150mg cafeina, citrulina y beta-alanina.', 129900, null, '/images/shop/myprotein-pre.jpg', 'MyProtein Pre Workout', '30', '255g', '["Cola","Naranja"]', '["pre-entreno"]', 12, 'in_stock', 0],
    ['bsn-syntha-6', 'Syntha-6 Protein', $brIds['bsn'], $catIds['proteina'], 'Mezcla de 6 proteinas de liberacion sostenida. Sabor premium.', 179900, 209900, '/images/shop/bsn-syntha6.jpg', 'BSN Syntha 6 Protein', '28', '1.32kg', '["Chocolate","Vainilla","Cookies"]', '["proteina"]', 6, 'low_stock', 0],
];
foreach ($products as $p) {
    $stmt->execute($p);
}
log_result('Shop products', count($products));

// ── 8. Payments (3 aprobados) ────────────────────────────────────────────────
$db->query("DELETE FROM payments");
$stmt = $db->prepare("
    INSERT INTO payments (client_id, email, wompi_reference, wompi_transaction_id, plan, amount_cop, currency, status, buyer_name, created_at)
    VALUES (?, ?, ?, ?, ?, ?, 'COP', 'approved', ?, ?)
");
$stmt->execute([$clientIds[0], 'carlos@wellcore.com', 'WC-esencial-' . (time() - 2592000), 'tx-001', 'esencial', 399000, 'Carlos Demo', date('Y-m-d H:i:s', time() - 2592000)]);
$stmt->execute([$clientIds[1], 'sofia@wellcore.com', 'WC-metodo-' . (time() - 1296000), 'tx-002', 'metodo', 504000, 'Sofia Demo', date('Y-m-d H:i:s', time() - 1296000)]);
$stmt->execute([$clientIds[2], 'andres@wellcore.com', 'WC-elite-' . (time() - 604800), 'tx-003', 'elite', 630000, 'Andres Demo', date('Y-m-d H:i:s', time() - 604800)]);
log_result('Payments', 3);

// ── 9. Invitations (3 pendientes) ────────────────────────────────────────────
$db->query("DELETE FROM invitations");
$stmt = $db->prepare("
    INSERT INTO invitations (code, plan, email_hint, note, status, created_by, created_at, expires_at)
    VALUES (?, ?, ?, ?, 'pending', ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
");
$invAssignedBy = $adminIds[0] ?? 1;
$stmt->execute(['BETA-' . strtoupper(bin2hex(random_bytes(4))), 'esencial', 'juan@gmail.com', 'Invitacion beta tester', $invAssignedBy]);
$stmt->execute(['BETA-' . strtoupper(bin2hex(random_bytes(4))), 'metodo', 'maria@gmail.com', 'Amiga de Sofia', $invAssignedBy]);
$stmt->execute(['BETA-' . strtoupper(bin2hex(random_bytes(4))), 'elite', 'pedro@hotmail.com', 'Coach referido', $invAssignedBy]);
log_result('Invitations', 3);

// ── 10. Update client profiles with realistic data ───────────────────────────
$profileStmt = $db->prepare("UPDATE client_profiles SET edad=?, peso=?, altura=?, objetivo=?, ciudad=?, nivel=?, lugar_entreno='gym', dias_disponibles=?, macros=? WHERE client_id=?");
$profileStmt->execute([28, 78.50, 175.00, 'Ganar masa muscular', 'Bogota', 'intermedio', '[1,3,5]', '{"kcal":2400,"proteina":180,"carbos":240,"grasas":80}', $clientIds[0]]);
$profileStmt->execute([25, 62.00, 163.00, 'Perder grasa y tonificar', 'Medellin', 'principiante', '[0,2,4]', '{"kcal":1800,"proteina":120,"carbos":180,"grasas":60}', $clientIds[1]]);
$profileStmt->execute([32, 85.00, 180.00, 'Recomposicion corporal', 'Cali', 'avanzado', '[0,1,2,3,4]', '{"kcal":2800,"proteina":210,"carbos":280,"grasas":93}', $clientIds[2]]);
log_result('Client profiles actualizados', 3);

// ── Resumen ──────────────────────────────────────────────────────────────────
$totalRows = array_sum(array_column($results, 'count'));
echo php_sapi_name() === 'cli'
    ? "\n  TOTAL: $totalRows registros insertados en " . count($results) . " operaciones.\n\n"
    : '';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'operations' => $results, 'total_rows' => $totalRows], JSON_PRETTY_PRINT);
}
