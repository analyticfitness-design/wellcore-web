<?php
/**
 * WellCore — Admin: System Health Check
 * GET /api/admin/health
 *
 * Returns connectivity status for MySQL, Dify, Ollama, n8n.
 * Only accessible to admin/jefe/superadmin roles.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET');
$admin = authenticateAdmin();
if (!in_array($admin['role'], ['admin', 'jefe', 'superadmin'], true)) {
    respondError('No autorizado', 403);
}

$health = [];

// 1. MySQL
try {
    $db = getDB();
    $stmt = $db->query('SELECT 1');
    $stmt->fetch();
    $health['mysql'] = ['status' => 'ok', 'label' => 'Conectado'];
} catch (\Throwable $e) {
    $health['mysql'] = ['status' => 'error', 'label' => 'Error: ' . $e->getMessage()];
}

// 2. Dify
$difyUrl = defined('DIFY_URL') ? DIFY_URL : '';
$difyKey = defined('DIFY_API_KEY') ? DIFY_API_KEY : '';
if ($difyUrl && $difyKey) {
    $ch = curl_init($difyUrl . '/v1/parameters');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $difyKey],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code >= 200 && $code < 500 && $resp !== false) {
        $health['dify'] = ['status' => 'ok', 'label' => 'Conectado'];
    } elseif ($err) {
        $health['dify'] = ['status' => 'error', 'label' => 'Sin conexion: ' . $err];
    } else {
        $health['dify'] = ['status' => 'warn', 'label' => 'HTTP ' . $code];
    }
} else {
    $health['dify'] = ['status' => 'off', 'label' => 'No configurado'];
}

// 3. Ollama (check tags endpoint)
$ollamaUrl = 'http://localhost:11434';
$ch = curl_init($ollamaUrl . '/api/tags');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 3,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($code === 200 && $resp) {
    $data = json_decode($resp, true);
    $models = isset($data['models']) ? count($data['models']) : 0;
    $health['ollama'] = ['status' => 'ok', 'label' => $models . ' modelo(s) disponible(s)'];
} elseif ($err) {
    $health['ollama'] = ['status' => 'error', 'label' => 'Sin conexion'];
} else {
    $health['ollama'] = ['status' => 'error', 'label' => 'HTTP ' . $code];
}

// 4. n8n
$n8nUrl = 'http://localhost:5678';
$ch = curl_init($n8nUrl . '/healthz');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 3,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($code === 200) {
    $health['n8n'] = ['status' => 'ok', 'label' => 'Activo'];
} elseif ($err) {
    $health['n8n'] = ['status' => 'error', 'label' => 'Sin conexion'];
} else {
    $health['n8n'] = ['status' => 'warn', 'label' => 'HTTP ' . $code];
}

respond([
    'ok'     => true,
    'health' => $health,
    'ts'     => date('Y-m-d H:i:s'),
]);
