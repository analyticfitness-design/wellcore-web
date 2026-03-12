<?php
ini_set('display_errors', '0');
error_reporting(0);
if (($_GET['key'] ?? '') !== 'wc_preview_2026') { http_response_code(403); exit; }

require_once __DIR__ . '/../emails/templates.php';

$plan = $_GET['plan'] ?? 'presencial';
$code = $_GET['code'] ?? 'abc123abc123abc123abc123abc123ab';
$html = email_invitation('Daniel', $plan, 'male', 'Mensaje de prueba', $code);

// Output the raw HTML so we can see rendering
header('Content-Type: text/html; charset=utf-8');
echo $html;
