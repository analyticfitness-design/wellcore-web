<?php
// GET /api/shop/categories — list all active categories with product counts

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';

requireMethod('GET');
$db = getDB();

$stmt = $db->query("
    SELECT
        c.id, c.name, c.slug, c.icon, c.sort_order,
        COUNT(p.id) AS product_count
    FROM shop_categories c
    LEFT JOIN shop_products p
           ON p.category_id = c.id
          AND p.active = TRUE
    WHERE c.active = TRUE
    GROUP BY c.id, c.name, c.slug, c.icon, c.sort_order
    ORDER BY c.sort_order ASC, c.name ASC
");

$categories = $stmt->fetchAll();

respond([
    'ok'         => true,
    'categories' => $categories,
]);
