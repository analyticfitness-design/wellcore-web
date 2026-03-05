<?php
// WellCore — Migracion: columnas source + client_id en tabla tickets
// GET /api/setup/migrate-rise-tickets.php  (requiere admin token)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireSetupAuth();

header('Content-Type: application/json');
$db  = getDB();
$ran = [];

try { $db->exec("ALTER TABLE tickets ADD COLUMN source ENUM('coach','rise') NOT NULL DEFAULT 'coach' AFTER coach_id"); $ran[] = 'ADD source'; } catch (\Throwable $e) { $ran[] = 'source ya existe'; }
try { $db->exec("ALTER TABLE tickets ADD COLUMN client_id INT DEFAULT NULL AFTER source"); $ran[] = 'ADD client_id'; } catch (\Throwable $e) { $ran[] = 'client_id ya existe'; }
try { $db->exec("ALTER TABLE tickets MODIFY COLUMN coach_id VARCHAR(60) DEFAULT NULL"); $ran[] = 'coach_id nullable'; } catch (\Throwable $e) { $ran[] = 'coach_id: ' . $e->getMessage(); }
try {
    $db->exec("ALTER TABLE tickets MODIFY COLUMN ticket_type ENUM(
        'rutina_nueva','cambio_rutina','nutricion','habitos','invitacion_cliente',
        'ajuste_entrenamiento','consulta_nutricion','problema_acceso','solicitud_especial','otro'
    ) NOT NULL");
    $ran[] = 'ticket_type ENUM ampliado';
} catch (\Throwable $e) { $ran[] = 'ticket_type: ' . $e->getMessage(); }

echo json_encode(['ok' => true, 'steps' => $ran], JSON_PRETTY_PRINT);
