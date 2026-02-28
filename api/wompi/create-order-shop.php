<?php
/**
 * ============================================================
 * WELLCORE FITNESS — WOMPI CREATE ORDER SHOP (TIENDA)
 * ============================================================
 * POST /api/wompi/create-order-shop.php
 *
 * Genera la referencia, calcula el total del carrito,
 * el hash de integridad y guarda la orden pendiente.
 *
 * REQUEST (JSON):
 *   cart             array   [ { id, name, price (COP), qty, flavor? } ]
 *   buyer_name       string
 *   buyer_email      string
 *   buyer_phone      string
 *   shipping_address string
 *   city             string
 *   country          string
 *
 * RESPONSE (JSON):
 *   ok               bool
 *   order_id         string  WCS-{timestamp}
 *   reference        string  igual que order_id
 *   amount_in_cents  int     total en centavos COP
 *   currency         string  COP
 *   integrity_hash   string  SHA256 para el widget
 *   widget_url       string
 *   public_key       string
 *   redirect_url     string
 *   subtotal         int     COP
 *   shipping         int     COP
 *   total            int     COP
 * ============================================================
 */

$allowedOrigins = [
    'https://wellcorefitness.com',
    'https://www.wellcorefitness.com',
    'http://172.17.216.45:8080',
    'http://172.17.216.45:8082',
    'http://localhost:8080',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/rate-limit.php';

// -------------------------------------------------------
// CONSTANTES TIENDA
// -------------------------------------------------------
define('SHOP_SHIPPING_FREE_THRESHOLD', 200000); // COP — envio gratis si subtotal >= esto
define('SHOP_SHIPPING_COST',           15000);  // COP — costo envio
define('SHOP_MAX_ITEMS_PER_PRODUCT',   10);
define('SHOP_MAX_CART_ITEMS',          20);

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function respondError(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// -------------------------------------------------------
// RATE LIMITING: 10 ordenes por IP por hora
// -------------------------------------------------------
$ip = get_client_ip();
if (!rate_limit_check($ip, 'create_order_shop', 10, 3600)) {
    respondError('Demasiadas solicitudes. Intenta en unos minutos.', 429);
}

// -------------------------------------------------------
// LEER INPUT
// -------------------------------------------------------
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    respondError('JSON invalido.');
}

$cart            = $body['cart']             ?? [];
$buyerName       = sanitize($body['buyer_name']        ?? '');
$buyerEmail      = strtolower(trim($body['buyer_email']     ?? ''));
$buyerPhone      = sanitize($body['buyer_phone']       ?? '');
$shippingAddress = sanitize($body['shipping_address']  ?? '');
$city            = sanitize($body['city']              ?? '');
$country         = sanitize($body['country']           ?? 'Colombia');

// -------------------------------------------------------
// VALIDACIONES
// -------------------------------------------------------
if (!is_array($cart) || empty($cart)) {
    respondError('El carrito esta vacio.');
}

if (count($cart) > SHOP_MAX_CART_ITEMS) {
    respondError('Demasiados items en el carrito.');
}

if (empty($buyerName) || strlen($buyerName) < 3) {
    respondError('Nombre requerido (minimo 3 caracteres).');
}

if (empty($buyerEmail) || !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    respondError('Email invalido.');
}

if (empty($shippingAddress)) {
    respondError('Direccion de envio requerida.');
}

if (empty($city)) {
    respondError('Ciudad requerida.');
}

// -------------------------------------------------------
// CALCULAR SUBTOTAL Y VALIDAR CARRITO
// -------------------------------------------------------
$subtotal   = 0;
$cleanCart  = [];

foreach ($cart as $item) {
    if (!is_array($item)) continue;

    $itemId    = sanitize((string) ($item['id']     ?? ''));
    $itemName  = sanitize((string) ($item['name']   ?? 'Producto'));
    $itemPrice = (int)    abs((float) ($item['price'] ?? 0));
    $itemQty   = max(1, min((int) ($item['qty'] ?? 1), SHOP_MAX_ITEMS_PER_PRODUCT));
    $itemFlavor= sanitize((string) ($item['flavor'] ?? ''));

    if (empty($itemId) || $itemPrice <= 0) continue;

    $subtotal += $itemPrice * $itemQty;
    $cleanCart[] = [
        'id'     => $itemId,
        'name'   => $itemName,
        'price'  => $itemPrice,
        'qty'    => $itemQty,
        'flavor' => $itemFlavor,
    ];
}

if (empty($cleanCart)) {
    respondError('Carrito sin productos validos.');
}

$shipping = $subtotal >= SHOP_SHIPPING_FREE_THRESHOLD ? 0 : SHOP_SHIPPING_COST;
$total    = $subtotal + $shipping;

if ($total <= 0) {
    respondError('Total de orden invalido.');
}

// -------------------------------------------------------
// GENERAR REFERENCIA UNICA DE ORDEN
// -------------------------------------------------------
$orderId   = 'WCS-' . round(microtime(true) * 1000);
$reference = $orderId;

// -------------------------------------------------------
// WOMPI: MONTO EN CENTAVOS
// COP ya viene en pesos enteros, Wompi necesita centavos
// $399.000 COP = 39.900.000 centavos
// -------------------------------------------------------
$amountInCents = $total * 100;

// -------------------------------------------------------
// CALCULAR HASH DE INTEGRIDAD
// SHA256(reference + amountInCents + currency + integritySecret)
// -------------------------------------------------------
$integrityHash = wompi_integrity_hash($reference, $amountInCents, 'COP');

// -------------------------------------------------------
// GUARDAR ORDEN PENDIENTE EN JSON
// -------------------------------------------------------
$ordersFile = __DIR__ . '/data/orders.json';
$dir        = dirname($ordersFile);
if (!is_dir($dir)) mkdir($dir, 0755, true);

$orders = [];
if (file_exists($ordersFile)) {
    $raw    = file_get_contents($ordersFile);
    $orders = json_decode($raw, true) ?? [];
}

$newOrder = [
    'order_id'         => $orderId,
    'reference'        => $reference,
    'cart'             => $cleanCart,
    'subtotal'         => $subtotal,
    'shipping'         => $shipping,
    'total'            => $total,
    'amount_in_cents'  => $amountInCents,
    'currency'         => 'COP',
    'buyer_name'       => $buyerName,
    'buyer_email'      => $buyerEmail,
    'buyer_phone'      => $buyerPhone,
    'shipping_address' => $shippingAddress,
    'city'             => $city,
    'country'          => $country,
    'status'           => 'pending',
    'wompi_transaction_id' => null,
    'created_at'       => date('c'),
    'updated_at'       => date('c'),
];

$orders[] = $newOrder;
file_put_contents($ordersFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

// -------------------------------------------------------
// RESPUESTA AL FRONTEND
// -------------------------------------------------------
http_response_code(200);
echo json_encode([
    'ok'              => true,
    'order_id'        => $orderId,
    'reference'       => $reference,
    'amount_in_cents' => $amountInCents,
    'currency'        => 'COP',
    'integrity_hash'  => $integrityHash,
    'widget_url'      => wompi_widget_url(),
    'public_key'      => WOMPI_PUBLIC_KEY,
    'redirect_url'    => SITE_URL . '/pago-exitoso.html',
    'subtotal'        => $subtotal,
    'shipping'        => $shipping,
    'total'           => $total,
    'sandbox'         => WOMPI_SANDBOX,
]);
