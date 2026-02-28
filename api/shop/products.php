<?php
// GET  /api/shop/products              → list products (filters: category, brand, search, sort, featured)
// GET  /api/shop/products?slug=X       → single product detail (increments view count)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';

requireMethod('GET');
$db = getDB();

// ── Single product by slug ──────────────────────────────────────────────────
if (isset($_GET['slug'])) {
    $slug = trim($_GET['slug']);
    if (!$slug) respondError('Slug requerido', 422);

    $stmt = $db->prepare("
        SELECT
            p.id, p.slug, p.name,
            b.name  AS brand_name,  b.slug AS brand_slug,
            c.name  AS category_name, c.slug AS category_slug,
            p.description, p.price_cop, p.compare_price,
            p.image_url, p.image_alt,
            p.servings, p.weight, p.flavors, p.tags,
            p.stock_status, p.featured, p.views
        FROM shop_products p
        LEFT JOIN shop_brands     b ON b.id = p.brand_id
        LEFT JOIN shop_categories c ON c.id = p.category_id
        WHERE p.slug = ? AND p.active = TRUE
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();

    if (!$product) respondError('Producto no encontrado', 404);

    // Increment view counter (fire-and-forget, ignore errors)
    try {
        $db->prepare("UPDATE shop_products SET views = views + 1 WHERE slug = ?")->execute([$slug]);
    } catch (PDOException $e) {
        // Non-critical — continue
    }

    // Decode JSON columns
    $product['flavors'] = json_decode($product['flavors'] ?? 'null');
    $product['tags']    = json_decode($product['tags']    ?? 'null');

    respond(['ok' => true, 'product' => $product]);
}

// ── Product list with optional filters ─────────────────────────────────────
$category = trim($_GET['category'] ?? '');
$brand    = trim($_GET['brand']    ?? '');
$search   = trim($_GET['search']   ?? '');
$sort     = trim($_GET['sort']     ?? 'newest');
$featured = isset($_GET['featured']) ? (bool)$_GET['featured'] : false;

$sql = "
    SELECT
        p.id, p.slug, p.name,
        b.name  AS brand_name,  b.slug AS brand_slug,
        c.name  AS category_name, c.slug AS category_slug,
        p.description, p.price_cop, p.compare_price,
        p.image_url, p.image_alt,
        p.servings, p.weight, p.flavors, p.tags,
        p.stock_status, p.featured
    FROM shop_products p
    LEFT JOIN shop_brands     b ON b.id = p.brand_id
    LEFT JOIN shop_categories c ON c.id = p.category_id
    WHERE p.active = TRUE
";
$params = [];

if ($category) {
    $sql .= " AND c.slug = ?";
    $params[] = $category;
}
if ($brand) {
    $sql .= " AND b.slug = ?";
    $params[] = $brand;
}
if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR b.name LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($featured) {
    $sql .= " AND p.featured = TRUE";
}

switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.price_cop ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price_cop DESC";
        break;
    default:
        $sql .= " ORDER BY p.created_at DESC";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Decode JSON columns for each product
foreach ($products as &$p) {
    $p['flavors'] = json_decode($p['flavors'] ?? 'null');
    $p['tags']    = json_decode($p['tags']    ?? 'null');
}
unset($p);

// Fetch active categories (for frontend filter menus)
$cats = $db->query("
    SELECT c.id, c.name, c.slug, c.icon,
           COUNT(p.id) AS product_count
    FROM shop_categories c
    LEFT JOIN shop_products p ON p.category_id = c.id AND p.active = TRUE
    WHERE c.active = TRUE
    GROUP BY c.id, c.name, c.slug, c.icon
    ORDER BY c.sort_order ASC, c.name ASC
")->fetchAll();

// Fetch active brands (for frontend filter menus)
$brands = $db->query("
    SELECT b.id, b.name, b.slug, b.logo_url
    FROM shop_brands b
    WHERE b.active = TRUE
    ORDER BY b.name ASC
")->fetchAll();

respond([
    'ok'         => true,
    'products'   => $products,
    'total'      => count($products),
    'categories' => $cats,
    'brands'     => $brands,
]);
