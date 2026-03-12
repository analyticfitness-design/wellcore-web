<?php
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

// Temporary diagnostic — remove after use
$secret = $_GET['key'] ?? '';
if ($secret !== 'wc_diag_2026_check') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$db = getDB();

$stmt = $db->query("SELECT id, code, plan, email_hint, status, created_at, expires_at FROM invitations ORDER BY id DESC LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok' => true, 'count' => count($rows), 'invitations' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
