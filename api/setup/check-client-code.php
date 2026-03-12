<?php
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
if (($_GET['key'] ?? '') !== 'wc_diag_cc_2026') { http_response_code(403); echo '{"error":"forbidden"}'; exit; }

require_once __DIR__ . '/../config/database.php';
$db = getDB();

// Check column definition
$stmt = $db->prepare("SELECT COLUMN_NAME, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'client_code'");
$stmt->execute();
$col = $stmt->fetch(PDO::FETCH_ASSOC);

// Check MAX value
$maxVal = $db->query("SELECT MAX(client_code) FROM clients")->fetchColumn();
$maxNum = $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(client_code, 5) AS UNSIGNED)), 0) FROM clients")->fetchColumn();
$nextCode = 'cli-' . str_pad((int)$maxNum + 1, 4, '0', STR_PAD_LEFT);

// All client codes
$codes = $db->query("SELECT id, client_code FROM clients ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['col' => $col, 'max_val' => $maxVal, 'max_num' => $maxNum, 'next_code' => $nextCode, 'next_len' => strlen($nextCode), 'all_codes' => $codes], JSON_PRETTY_PRINT);
