<?php
// POST /api/auth/logout
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$token = getTokenFromHeader();
if (!$token) {
    respondError('Token requerido para cerrar sesion', 401);
}
revokeToken($token);
respond(['message' => 'Sesion cerrada correctamente']);
