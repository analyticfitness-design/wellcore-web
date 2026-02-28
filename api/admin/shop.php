<?php
// GET    /api/admin/shop              → dashboard stats
// GET    /api/admin/shop?orders=1     → all orders with items
// GET    /api/admin/shop?order_id=X   → single order detail
// PUT    /api/admin/shop              → update order status {order_id, status, tracking_code?}
// POST   /api/admin/shop              → create or update product
// DELETE /api/admin/shop?product_id=X → deactivate product (soft delete)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST', 'PUT', 'DELETE');
$admin = authenticateAdmin();
$db    = getDB();

// ── DELETE — deactivate product ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $productId = (int)($_GET['product_id'] ?? 0);
    if (!$productId) respondError('product_id requerido', 422);

    $stmt = $db->prepare("SELECT id, name FROM shop_products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) respondError('Producto no encontrado', 404);

    $db->prepare("UPDATE shop_products SET active = FALSE WHERE id = ?")->execute([$productId]);
    respond(['ok' => true, 'message' => "Producto '{$product['name']}' desactivado"]);
}

// ── PUT — update order status ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body        = getJsonBody();
    $orderId     = (int)($body['order_id'] ?? 0);
    $status      = trim($body['status']   ?? '');
    $trackingCode = trim($body['tracking_code'] ?? '');

    if (!$orderId) respondError('order_id requerido', 422);

    $allowedStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $allowedStatuses, true)) {
        respondError('Status invalido. Valores: ' . implode(', ', $allowedStatuses), 422);
    }

    $stmt = $db->prepare("SELECT id, order_code FROM shop_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    if (!$stmt->fetch()) respondError('Orden no encontrada', 404);

    $updateSql = "UPDATE shop_orders SET status = ?";
    $updateParams = [$status];

    if ($trackingCode) {
        $updateSql .= ", tracking_code = ?";
        $updateParams[] = $trackingCode;
    }

    $updateSql .= " WHERE id = ?";
    $updateParams[] = $orderId;

    $db->prepare($updateSql)->execute($updateParams);
    respond(['ok' => true, 'message' => 'Estado de orden actualizado']);
}

// ── POST — create or update product ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getJsonBody();

    $name        = trim($body['name']        ?? '');
    $slug        = trim($body['slug']        ?? '');
    $description = trim($body['description'] ?? '');
    $imageUrl    = trim($body['image_url']   ?? '');
    $imageAlt    = trim($body['image_alt']   ?? '');
    $servings    = trim($body['servings']    ?? '');
    $weight      = trim($body['weight']      ?? '');
    $priceCop    = isset($body['price_cop']) ? (int)$body['price_cop'] : 0;
    $comparePrice = isset($body['compare_price']) ? (int)$body['compare_price'] : null;
    $brandId     = isset($body['brand_id'])    ? (int)$body['brand_id']    : null;
    $categoryId  = isset($body['category_id']) ? (int)$body['category_id'] : null;
    $stock       = isset($body['stock'])       ? (int)$body['stock']       : 0;
    $featured    = isset($body['featured'])    ? (bool)$body['featured']   : false;
    $active      = isset($body['active'])      ? (bool)$body['active']     : true;
    $flavors     = isset($body['flavors'])     && is_array($body['flavors']) ? $body['flavors'] : null;
    $tags        = isset($body['tags'])        && is_array($body['tags'])    ? $body['tags']    : null;

    if (!$name)  respondError('name requerido', 422);
    if (!$slug)  respondError('slug requerido', 422);
    if ($priceCop < 1) respondError('price_cop debe ser mayor a 0', 422);

    // Auto-determine stock_status from stock level
    $stockStatus = 'in_stock';
    if ($stock === 0) {
        $stockStatus = 'out_of_stock';
    } elseif ($stock <= 5) {
        $stockStatus = 'low_stock';
    }

    $flavorsJson = $flavors !== null ? json_encode($flavors) : null;
    $tagsJson    = $tags    !== null ? json_encode($tags)    : null;

    // Check if updating existing product (slug already exists)
    $existing = $db->prepare("SELECT id FROM shop_products WHERE slug = ?");
    $existing->execute([$slug]);
    $existingProduct = $existing->fetch();

    if ($existingProduct) {
        // Update
        $stmt = $db->prepare("
            UPDATE shop_products SET
                name          = ?,
                brand_id      = ?,
                category_id   = ?,
                description   = ?,
                price_cop     = ?,
                compare_price = ?,
                image_url     = ?,
                image_alt     = ?,
                servings      = ?,
                weight        = ?,
                flavors       = ?,
                tags          = ?,
                stock         = ?,
                stock_status  = ?,
                featured      = ?,
                active        = ?
            WHERE slug = ?
        ");
        $stmt->execute([
            $name, $brandId, $categoryId, $description,
            $priceCop, $comparePrice, $imageUrl, $imageAlt,
            $servings, $weight, $flavorsJson, $tagsJson,
            $stock, $stockStatus, $featured, $active,
            $slug,
        ]);
        respond(['ok' => true, 'message' => 'Producto actualizado', 'product_id' => $existingProduct['id']]);
    } else {
        // Create
        try {
            $stmt = $db->prepare("
                INSERT INTO shop_products
                    (slug, name, brand_id, category_id, description,
                     price_cop, compare_price, image_url, image_alt,
                     servings, weight, flavors, tags,
                     stock, stock_status, featured, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $slug, $name, $brandId, $categoryId, $description,
                $priceCop, $comparePrice, $imageUrl, $imageAlt,
                $servings, $weight, $flavorsJson, $tagsJson,
                $stock, $stockStatus, $featured, $active,
            ]);
            respond(['ok' => true, 'message' => 'Producto creado', 'product_id' => (int)$db->lastInsertId()], 201);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                respondError('Ya existe un producto con ese slug', 409);
            }
            respondError('Error al crear producto', 500);
        }
    }
}

// ── GET — single order detail ───────────────────────────────────────────────
if (isset($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];
    if (!$orderId) respondError('order_id invalido', 422);

    $stmt = $db->prepare("
        SELECT
            o.id, o.order_code, o.guest_name, o.guest_email, o.guest_phone,
            o.guest_city, o.guest_address, o.guest_notes,
            o.subtotal_cop, o.shipping_cop, o.total_cop,
            o.status, o.payment_method, o.payment_ref, o.tracking_code,
            o.created_at, o.updated_at,
            c.name AS client_name, c.email AS client_email
        FROM shop_orders o
        LEFT JOIN clients c ON c.id = o.client_id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) respondError('Orden no encontrada', 404);

    $stmtItems = $db->prepare("
        SELECT oi.product_name, oi.variant, oi.quantity, oi.unit_price,
               p.slug AS product_slug, p.image_url AS product_image
        FROM shop_order_items oi
        LEFT JOIN shop_products p ON p.id = oi.product_id
        WHERE oi.order_id = ?
    ");
    $stmtItems->execute([$orderId]);
    $order['items'] = $stmtItems->fetchAll();

    respond(['ok' => true, 'order' => $order]);
}

// ── GET — all orders ────────────────────────────────────────────────────────
if (isset($_GET['orders'])) {
    $statusFilter = trim($_GET['status'] ?? '');

    $sql = "
        SELECT
            o.id, o.order_code,
            COALESCE(o.guest_name, c.name) AS customer_name,
            COALESCE(o.guest_email, c.email) AS customer_email,
            o.guest_city, o.total_cop, o.status,
            o.tracking_code, o.created_at
        FROM shop_orders o
        LEFT JOIN clients c ON c.id = o.client_id
        WHERE 1=1
    ";
    $params = [];

    if ($statusFilter) {
        $sql .= " AND o.status = ?";
        $params[] = $statusFilter;
    }

    $sql .= " ORDER BY o.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Attach items to each order
    $stmtItems = $db->prepare("
        SELECT order_id, product_name, variant, quantity, unit_price
        FROM shop_order_items
        WHERE order_id = ?
    ");
    foreach ($orders as &$ord) {
        $stmtItems->execute([$ord['id']]);
        $ord['items'] = $stmtItems->fetchAll();
    }
    unset($ord);

    respond(['ok' => true, 'orders' => $orders, 'total' => count($orders)]);
}

// ── GET — dashboard stats (default) ────────────────────────────────────────
$totalProducts = (int)$db->query(
    "SELECT COUNT(*) FROM shop_products WHERE active = TRUE"
)->fetchColumn();

$totalOrders = (int)$db->query(
    "SELECT COUNT(*) FROM shop_orders"
)->fetchColumn();

$revenue = (int)($db->query(
    "SELECT COALESCE(SUM(total_cop), 0) FROM shop_orders WHERE status IN ('confirmed','shipped','delivered')"
)->fetchColumn() ?? 0);

$pendingOrders = (int)$db->query(
    "SELECT COUNT(*) FROM shop_orders WHERE status = 'pending'"
)->fetchColumn();

$recentOrders = $db->query("
    SELECT
        o.order_code,
        COALESCE(o.guest_name, c.name) AS customer_name,
        o.total_cop, o.status, o.created_at
    FROM shop_orders o
    LEFT JOIN clients c ON c.id = o.client_id
    ORDER BY o.created_at DESC
    LIMIT 10
")->fetchAll();

$topProducts = $db->query("
    SELECT p.name, p.slug, p.price_cop, p.views,
           b.name AS brand_name,
           COUNT(oi.id) AS times_ordered
    FROM shop_products p
    LEFT JOIN shop_order_items oi ON oi.product_id = p.id
    LEFT JOIN shop_brands b ON b.id = p.brand_id
    WHERE p.active = TRUE
    GROUP BY p.id, p.name, p.slug, p.price_cop, p.views, b.name
    ORDER BY times_ordered DESC, p.views DESC
    LIMIT 5
")->fetchAll();

respond([
    'ok'             => true,
    'total_products' => $totalProducts,
    'total_orders'   => $totalOrders,
    'pending_orders' => $pendingOrders,
    'revenue_cop'    => $revenue,
    'recent_orders'  => $recentOrders,
    'top_products'   => $topProducts,
]);
