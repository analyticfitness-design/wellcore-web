<?php
// POST /api/shop/orders          → guest checkout — create order
// GET  /api/shop/orders?code=WC-X → check order status by code

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';

requireMethod('GET', 'POST');
$db = getDB();

// ── GET — order status by code ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $code = trim($_GET['code'] ?? '');
    if (!$code) respondError('Codigo de orden requerido', 422);

    $stmt = $db->prepare("
        SELECT
            o.order_code, o.guest_name, o.guest_email, o.guest_city,
            o.subtotal_cop, o.shipping_cop, o.total_cop,
            o.status, o.tracking_code, o.created_at
        FROM shop_orders o
        WHERE o.order_code = ?
    ");
    $stmt->execute([$code]);
    $order = $stmt->fetch();

    if (!$order) respondError('Orden no encontrada', 404);

    // Fetch items
    $stmt2 = $db->prepare("
        SELECT product_name, variant, quantity, unit_price
        FROM shop_order_items
        WHERE order_id = (SELECT id FROM shop_orders WHERE order_code = ?)
    ");
    $stmt2->execute([$code]);
    $order['items'] = $stmt2->fetchAll();

    respond(['ok' => true, 'order' => $order]);
}

// ── POST — create order ─────────────────────────────────────────────────────
$body = getJsonBody();

$items   = $body['items']   ?? [];
$name    = trim($body['name']    ?? '');
$email   = strtolower(trim($body['email']   ?? ''));
$phone   = trim($body['phone']   ?? '');
$city    = trim($body['city']    ?? '');
$address = trim($body['address'] ?? '');
$notes   = trim($body['notes']   ?? '');

// Validate required fields
if (empty($items) || !is_array($items)) respondError('Items requeridos', 422);
if (!$name)    respondError('Nombre requerido', 422);
if (!$email)   respondError('Email requerido', 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respondError('Email invalido', 422);
if (!$phone)   respondError('Telefono requerido', 422);
if (!$city)    respondError('Ciudad requerida', 422);
if (!$address) respondError('Direccion requerida', 422);

// Validate items structure
foreach ($items as $i => $item) {
    if (empty($item['product_id']) || !is_numeric($item['product_id'])) {
        respondError("Item #$i: product_id invalido", 422);
    }
    $qty = (int)($item['quantity'] ?? 1);
    if ($qty < 1 || $qty > 99) {
        respondError("Item #$i: quantity debe ser entre 1 y 99", 422);
    }
}

// Validate all product_ids exist, are active and have prices
$productIds = array_unique(array_map(fn($i) => (int)$i['product_id'], $items));
$placeholders = implode(',', array_fill(0, count($productIds), '?'));

$stmt = $db->prepare("
    SELECT id, name, price_cop, stock_status, active
    FROM shop_products
    WHERE id IN ($placeholders)
");
$stmt->execute($productIds);
$dbProducts = $stmt->fetchAll();

// Index by id for quick lookup
$productMap = [];
foreach ($dbProducts as $prod) {
    $productMap[(int)$prod['id']] = $prod;
}

// Verify all requested products exist and are available
foreach ($items as $i => $item) {
    $pid = (int)$item['product_id'];
    if (!isset($productMap[$pid])) {
        respondError("Producto ID $pid no encontrado", 422);
    }
    if (!$productMap[$pid]['active']) {
        respondError("Producto '{$productMap[$pid]['name']}' no disponible", 422);
    }
    if ($productMap[$pid]['stock_status'] === 'out_of_stock') {
        respondError("Producto '{$productMap[$pid]['name']}' agotado", 422);
    }
}

// Calculate totals from DB prices (never trust client-side prices)
$subtotal = 0;
foreach ($items as $item) {
    $pid      = (int)$item['product_id'];
    $qty      = (int)($item['quantity'] ?? 1);
    $subtotal += $productMap[$pid]['price_cop'] * $qty;
}

$shipping = 0;  // Free shipping — adjust logic here if needed
$total    = $subtotal + $shipping;

// Generate unique order code: WC-XXXXXX (6 random hex chars, uppercase)
$orderCode = 'WC-' . strtoupper(bin2hex(random_bytes(3)));

// Insert order + items in a transaction
try {
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO shop_orders
            (order_code, guest_name, guest_email, guest_phone,
             guest_city, guest_address, guest_notes,
             subtotal_cop, shipping_cop, total_cop, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $orderCode, $name, $email, $phone,
        $city, $address, $notes,
        $subtotal, $shipping, $total,
    ]);
    $orderId = (int)$db->lastInsertId();

    $stmtItem = $db->prepare("
        INSERT INTO shop_order_items
            (order_id, product_id, product_name, variant, quantity, unit_price)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $pid     = (int)$item['product_id'];
        $qty     = (int)($item['quantity'] ?? 1);
        $variant = trim($item['variant'] ?? '');
        $stmtItem->execute([
            $orderId,
            $pid,
            $productMap[$pid]['name'],
            $variant ?: null,
            $qty,
            $productMap[$pid]['price_cop'],
        ]);
    }

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    respondError('Error al crear la orden', 500);
}

respond([
    'ok'         => true,
    'order_code' => $orderCode,
    'total_cop'  => $total,
    'message'    => 'Orden creada correctamente',
], 201);
