<?php
/**
 * WellCore Fitness — Migracion v3: Shop Seed (Accesorios + Digital)
 * ============================================================
 * Agrega categorias "accesorios" y "digital" con productos reales.
 * Idempotente: seguro de ejecutar multiples veces.
 *
 * ACCESO: https://wellcorefitness.com/api/setup/migrate-v3-shop-seed.php?secret=WC_SHOP_SEED_V3_2026
 * ============================================================
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

$db      = getDB();
$results = [];
$errors  = [];

// ── 1. ENSURE TABLES EXIST ─────────────────────────────────────────────────
// (same DDL as seed-shop.php — safe to re-run)

$db->exec("
    CREATE TABLE IF NOT EXISTS shop_categories (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug        VARCHAR(80)  NOT NULL UNIQUE,
        name        VARCHAR(120) NOT NULL,
        icon        VARCHAR(50)  NULL,
        sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
        active      TINYINT(1)   NOT NULL DEFAULT 1,
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
    CREATE TABLE IF NOT EXISTS shop_brands (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug        VARCHAR(80)  NOT NULL UNIQUE,
        name        VARCHAR(120) NOT NULL,
        logo_url    VARCHAR(500) NULL,
        active      TINYINT(1)   NOT NULL DEFAULT 1,
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
    CREATE TABLE IF NOT EXISTS shop_products (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug            VARCHAR(120)     NOT NULL UNIQUE,
        name            VARCHAR(200)     NOT NULL,
        brand_id        INT UNSIGNED     NULL,
        category_id     INT UNSIGNED     NULL,
        description     TEXT             NULL,
        price_cop       INT UNSIGNED     NOT NULL,
        compare_price   INT UNSIGNED     NULL,
        image_url       VARCHAR(500)     NULL,
        image_alt       VARCHAR(200)     NULL,
        servings        VARCHAR(60)      NULL,
        weight          VARCHAR(60)      NULL,
        flavors         JSON             NULL,
        tags            JSON             NULL,
        stock_status    ENUM('in_stock','low_stock','out_of_stock') NOT NULL DEFAULT 'in_stock',
        featured        TINYINT(1)       NOT NULL DEFAULT 0,
        active          TINYINT(1)       NOT NULL DEFAULT 1,
        views           INT UNSIGNED     NOT NULL DEFAULT 0,
        created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_sp_brand    FOREIGN KEY (brand_id)    REFERENCES shop_brands(id)     ON DELETE SET NULL,
        CONSTRAINT fk_sp_category FOREIGN KEY (category_id) REFERENCES shop_categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$results[] = 'Tables verified: OK';

// ── 2. CATEGORIES — add "digital" (INSERT IGNORE) ──────────────────────────

$newCategories = [
    ['accesorios', 'Accesorios',         9],
    ['digital',    'Productos Digitales', 10],
];

$catInserted = 0;
$stmtCat = $db->prepare(
    "INSERT IGNORE INTO shop_categories (slug, name, sort_order) VALUES (?, ?, ?)"
);
foreach ($newCategories as [$slug, $name, $order]) {
    try {
        $stmtCat->execute([$slug, $name, $order]);
        if ($stmtCat->rowCount() > 0) $catInserted++;
    } catch (PDOException $e) {
        $errors[] = "[category:$slug] " . $e->getMessage();
    }
}
$results[] = "Categories seeded: $catInserted new / " . count($newCategories) . ' attempted';

// ── 3. BRAND — ensure "wellcore" exists ─────────────────────────────────────

$stmtBrand = $db->prepare(
    "INSERT IGNORE INTO shop_brands (slug, name, logo_url) VALUES (?, ?, NULL)"
);
try {
    $stmtBrand->execute(['wellcore', 'WELLCORE FITNESS']);
} catch (PDOException $e) {
    $errors[] = "[brand:wellcore] " . $e->getMessage();
}
$results[] = 'Brand "wellcore" ensured';

// ── 4. PRODUCTS — Accesorios + Digital ──────────────────────────────────────
// Format: [slug, name, brand_slug, category_slug, description, price_cop,
//          image_url, servings, weight, flavors_json, stock_qty, featured]

$products = [

    // ── ACCESORIOS ──────────────────────────────────────────────
    [
        'bandas-resistencia-x5',
        'Bandas de Resistencia Set x5',
        'wellcore',
        'accesorios',
        'Set de 5 bandas elasticas de latex premium con 5 niveles de resistencia progresivos: extra ligera (5 lb), ligera (10 lb), media (15 lb), fuerte (25 lb) y extra fuerte (35 lb). Ideales para calentamiento, activacion glute, rehabilitacion, entrenamiento en casa o como complemento en gym. Incluyen bolsa de transporte. Material duradero con resistencia al desgarre. Codificadas por color para identificacion rapida del nivel.',
        44900,
        'images/shop/bandas-resistencia.png',
        null,
        '5 bandas',
        null,
        30,
        false
    ],
    [
        'shaker-wellcore-700ml',
        'Shaker WellCore 700ml',
        'wellcore',
        'accesorios',
        'Mezclador de 700ml con compartimento inferior para guardar scoops de proteina o suplementos en capsulas. Tapa a prueba de fugas con sello de silicona y mecanismo de cierre seguro. Bola mezcladora de acero inoxidable para disolucion perfecta sin grumos. Libre de BPA, apto para lavavajillas. Marcas de medida en ml y oz impresas en el vaso. Diseno WellCore con acabado mate.',
        24900,
        'images/shop/shaker-wellcore.png',
        null,
        '700ml',
        null,
        50,
        false
    ],
    [
        'guantes-entrenamiento',
        'Guantes de Entrenamiento',
        'wellcore',
        'accesorios',
        'Guantes de entrenamiento con palma de microfibra de agarre reforzado y respaldo de malla transpirable para ventilacion optima. Cierre de velcro ajustable en la muneca para soporte adicional. Costuras dobles en zonas de alto desgaste. Protegen contra callosidades sin perder sensibilidad en el agarre. Disponibles en tallas S, M, L y XL. Lavables a mano.',
        29900,
        'images/shop/guantes-entrenamiento.png',
        null,
        null,
        '["S","M","L","XL"]',
        25,
        false
    ],
    [
        'foam-roller-45cm',
        'Foam Roller 45cm',
        'wellcore',
        'accesorios',
        'Rodillo de espuma de alta densidad de 45cm para liberacion miofascial, recuperacion muscular y movilidad articular. La textura con relieves de masaje facilita el trabajo sobre puntos gatillo y adhesiones fasciales. Ideal para usar como warm-up, cool-down o en dias de recuperacion activa. Soporta hasta 150kg de peso. Estudios muestran que el foam rolling reduce DOMS y mejora el rango de movimiento sin afectar el rendimiento posterior.',
        39900,
        'images/shop/foam-roller.png',
        null,
        '45cm',
        null,
        20,
        false
    ],

    // ── DIGITAL ─────────────────────────────────────────────────
    [
        'guia-nutricion-wellcore',
        'Guia Nutricion WellCore PDF',
        'wellcore',
        'digital',
        'Guia digital de nutricion de 85 paginas con plan nutricional personalizable por fase (deficit, mantenimiento, superavit). Incluye calculadora de macros paso a paso, 4 plantillas de menu semanal por objetivo, lista de compras optimizada, tablas de equivalencias de alimentos y seccion de FAQ nutricional. Basada en la misma metodologia que usan los coaches WellCore con sus clientes. Formato PDF descargable, acceso inmediato tras la compra. Compatible con cualquier dispositivo.',
        19900,
        'images/shop/guia-nutricion.png',
        null,
        'PDF 85 pags',
        null,
        999,
        true
    ],
    [
        'pack-recetas-fitness-50',
        'Pack Recetas Fitness 50+',
        'wellcore',
        'digital',
        'Compilacion de mas de 50 recetas altas en proteina disenadas por el equipo de nutricion WellCore. Cada receta incluye macros exactos por porcion (calorias, proteina, carbohidratos, grasas), tiempo de preparacion, ingredientes accesibles en Latinoamerica y foto de referencia. Categorias: desayunos, almuerzos, cenas, snacks y postres fitness. Todas las recetas se preparan en menos de 30 minutos. Formato PDF descargable, acceso inmediato.',
        14900,
        'images/shop/pack-recetas.png',
        null,
        'PDF 50+ recetas',
        null,
        999,
        false
    ],
];

// Resolve foreign key lookup maps
$brandMap = [];
foreach ($db->query("SELECT id, slug FROM shop_brands")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $brandMap[$row['slug']] = (int)$row['id'];
}

$categoryMap = [];
foreach ($db->query("SELECT id, slug FROM shop_categories")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $categoryMap[$row['slug']] = (int)$row['id'];
}

$prodInserted = 0;
$prodUpdated  = 0;

$stmtProd = $db->prepare("
    INSERT INTO shop_products
        (slug, name, brand_id, category_id, description, price_cop,
         image_url, servings, weight, flavors, stock_status, featured, active)
    VALUES
        (:slug, :name, :brand_id, :category_id, :description, :price_cop,
         :image_url, :servings, :weight, :flavors, :stock_status, :featured, 1)
    ON DUPLICATE KEY UPDATE
        name         = VALUES(name),
        brand_id     = VALUES(brand_id),
        category_id  = VALUES(category_id),
        description  = VALUES(description),
        price_cop    = VALUES(price_cop),
        image_url    = VALUES(image_url),
        servings     = VALUES(servings),
        weight       = VALUES(weight),
        flavors      = VALUES(flavors),
        stock_status = VALUES(stock_status),
        featured     = VALUES(featured),
        active       = 1
");

foreach ($products as [
    $slug, $name, $brandSlug, $categorySlug,
    $description, $priceCop,
    $imageUrl, $servings, $weight, $flavors,
    $stockQty, $featured,
]) {
    $brandId    = isset($brandSlug)    ? ($brandMap[$brandSlug]    ?? null) : null;
    $categoryId = isset($categorySlug) ? ($categoryMap[$categorySlug] ?? null) : null;

    if ($stockQty > 5) {
        $stockStatus = 'in_stock';
    } elseif ($stockQty > 0) {
        $stockStatus = 'low_stock';
    } else {
        $stockStatus = 'out_of_stock';
    }

    try {
        $stmtProd->execute([
            ':slug'         => $slug,
            ':name'         => $name,
            ':brand_id'     => $brandId,
            ':category_id'  => $categoryId,
            ':description'  => $description,
            ':price_cop'    => $priceCop,
            ':image_url'    => $imageUrl,
            ':servings'     => $servings,
            ':weight'       => $weight,
            ':flavors'      => $flavors,
            ':stock_status' => $stockStatus,
            ':featured'     => $featured ? 1 : 0,
        ]);
        $rc = $stmtProd->rowCount();
        if ($rc === 1)     $prodInserted++;
        elseif ($rc >= 2)  $prodUpdated++;
    } catch (PDOException $e) {
        $errors[] = "[product:$slug] " . $e->getMessage();
    }
}

$results[] = "Products inserted: $prodInserted, updated: $prodUpdated, attempted: " . count($products);

// ── 5. FINAL COUNTS ─────────────────────────────────────────────────────────

$totalCats   = (int)$db->query("SELECT COUNT(*) FROM shop_categories")->fetchColumn();
$totalBrands = (int)$db->query("SELECT COUNT(*) FROM shop_brands")->fetchColumn();
$totalProds  = (int)$db->query("SELECT COUNT(*) FROM shop_products WHERE active = 1")->fetchColumn();
$accCount    = (int)$db->query("SELECT COUNT(*) FROM shop_products p JOIN shop_categories c ON c.id = p.category_id WHERE c.slug = 'accesorios' AND p.active = 1")->fetchColumn();
$digCount    = (int)$db->query("SELECT COUNT(*) FROM shop_products p JOIN shop_categories c ON c.id = p.category_id WHERE c.slug = 'digital' AND p.active = 1")->fetchColumn();

// ── OUTPUT ───────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WellCore — Shop Seed v3 (Accesorios + Digital)</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            background: #0a0a0a;
            color: #e0e0e0;
            padding: 40px 24px;
            min-height: 100vh;
        }
        .container { max-width: 820px; margin: 0 auto; }
        h1 {
            font-family: 'Bebas Neue', Impact, sans-serif;
            font-size: 2.4rem;
            letter-spacing: 0.08em;
            color: #fff;
            border-left: 4px solid #E31E24;
            padding-left: 16px;
            margin-bottom: 8px;
        }
        .subtitle { color: #888; font-size: 0.8rem; margin-bottom: 32px; padding-left: 20px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: #111113;
            border: 1px solid #222;
            border-top: 3px solid #E31E24;
            padding: 20px 16px;
        }
        .stat-value { font-size: 2rem; font-weight: 700; color: #E31E24; line-height: 1; }
        .stat-label { font-size: 0.7rem; color: #888; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.1em; }
        .section { margin-bottom: 24px; }
        .section-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #E31E24;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #222;
        }
        .log-list { list-style: none; }
        .log-list li {
            font-size: 0.8rem;
            padding: 6px 10px;
            border-left: 2px solid #333;
            margin-bottom: 4px;
            color: #b0b0b0;
        }
        .log-list li::before { content: '// '; color: #00D9FF; }
        .error-list li { border-left-color: #E31E24; color: #ff8080; }
        .error-list li::before { content: 'ERR '; color: #E31E24; }
        .badge-ok  { display: inline-block; background: #00D9FF; color: #000; font-size: 0.65rem; font-weight: 700; padding: 2px 8px; margin-left: 8px; }
        .badge-err { display: inline-block; background: #E31E24; color: #fff; font-size: 0.65rem; font-weight: 700; padding: 2px 8px; margin-left: 8px; }
    </style>
</head>
<body>
<div class="container">

    <h1>SHOP SEED V3 — ACCESORIOS + DIGITAL</h1>
    <p class="subtitle"><?= date('Y-m-d H:i:s') ?> &mdash; Database: <?= DB_NAME ?></p>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $totalCats ?></div>
            <div class="stat-label">Categorias</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalBrands ?></div>
            <div class="stat-label">Marcas</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalProds ?></div>
            <div class="stat-label">Productos Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $accCount ?></div>
            <div class="stat-label">Accesorios</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $digCount ?></div>
            <div class="stat-label">Digitales</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">
            Resultados
            <?php if (empty($errors)): ?>
                <span class="badge-ok">OK</span>
            <?php else: ?>
                <span class="badge-err"><?= count($errors) ?> ERRORES</span>
            <?php endif; ?>
        </div>
        <ul class="log-list">
            <?php foreach ($results as $line): ?>
                <li><?= htmlspecialchars($line) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="section">
        <div class="section-title">Errores</div>
        <ul class="log-list error-list">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
