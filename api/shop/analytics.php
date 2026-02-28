<?php
// POST /api/shop/analytics — log a shop analytics event (fire-and-forget, no auth)
// Body: {event_type: 'view'|'add_to_cart'|'checkout', product_id?, session_id?}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';

requireMethod('POST');

$body       = getJsonBody();
$eventType  = trim($body['event_type'] ?? '');
$productId  = isset($body['product_id']) ? (int)$body['product_id'] : null;
$sessionId  = trim($body['session_id']  ?? '');
$metadata   = isset($body['metadata']) && is_array($body['metadata']) ? $body['metadata'] : null;

$allowedEvents = ['view', 'add_to_cart', 'checkout', 'purchase'];
if (!in_array($eventType, $allowedEvents, true)) {
    respondError('event_type invalido. Valores: ' . implode(', ', $allowedEvents), 422);
}

// Sanitize session_id (max 64 chars, alphanumeric + dash/underscore)
if ($sessionId) {
    $sessionId = substr(preg_replace('/[^a-zA-Z0-9\-_]/', '', $sessionId), 0, 64);
}

try {
    $db = getDB();

    // If product_id is provided, verify it exists (non-critical — skip on error)
    if ($productId) {
        $check = $db->prepare("SELECT id FROM shop_products WHERE id = ? AND active = TRUE LIMIT 1");
        $check->execute([$productId]);
        if (!$check->fetch()) {
            $productId = null;  // Invalid product — log event without product link
        }
    }

    $stmt = $db->prepare("
        INSERT INTO shop_analytics (event_type, product_id, session_id, metadata)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $eventType,
        $productId ?: null,
        $sessionId ?: null,
        $metadata ? json_encode($metadata) : null,
    ]);
} catch (PDOException $e) {
    // Analytics is non-critical — return ok even on DB error
    respond(['ok' => true]);
}

respond(['ok' => true]);
