<?php
// GET  /api/admin/clients           → list all clients
// GET  /api/admin/clients?id=X      → single client with full data
// POST /api/admin/clients           → create new client
// PUT  /api/admin/clients?id=X      → update client

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET','POST','PUT');
$admin = authenticateAdmin();
$db = getDB();

// GET single client
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("
        SELECT c.*, p.edad, p.peso, p.altura, p.objetivo, p.ciudad,
               p.whatsapp, p.nivel, p.lugar_entreno, p.dias_disponibles,
               p.restricciones, p.macros,
               (SELECT COUNT(*) FROM checkins WHERE client_id = c.id AND coach_reply IS NULL) as pending_checkins,
               (SELECT COUNT(*) FROM training_logs WHERE client_id = c.id AND completed = 1 AND log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as workouts_month,
               (SELECT log_date FROM metrics WHERE client_id = c.id ORDER BY log_date DESC LIMIT 1) as last_metric_date
        FROM clients c LEFT JOIN client_profiles p ON p.client_id = c.id
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $client = $stmt->fetch();
    if (!$client) respondError('Cliente no encontrado', 404);

    // Decode JSON
    $client['dias_disponibles'] = json_decode($client['dias_disponibles'] ?? '[]');
    $client['macros'] = json_decode($client['macros'] ?? 'null');

    // Get recent metrics
    $stmt2 = $db->prepare("SELECT * FROM metrics WHERE client_id = ? ORDER BY log_date DESC LIMIT 3");
    $stmt2->execute([$id]);
    $client['recent_metrics'] = $stmt2->fetchAll();

    // Get pending check-ins
    $stmt3 = $db->prepare("SELECT * FROM checkins WHERE client_id = ? ORDER BY checkin_date DESC LIMIT 5");
    $stmt3->execute([$id]);
    $client['checkins'] = $stmt3->fetchAll();

    respond(['client' => $client]);
}

// GET list
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = $_GET['search'] ?? '';
    $plan   = $_GET['plan']   ?? '';
    $status = $_GET['status'] ?? '';
    $limit  = min(100, max(1, (int) ($_GET['limit']  ?? 25)));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));

    $where  = " WHERE 1=1";
    $params = [];

    if ($search) {
        $where .= " AND (c.name LIKE ? OR c.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($plan)   { $where .= " AND c.plan = ?";   $params[] = $plan; }
    if ($status) { $where .= " AND c.status = ?"; $params[] = $status; }

    // Total count (for pagination UI)
    $countStmt = $db->prepare("SELECT COUNT(*) FROM clients c $where");
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();

    // MRR (siempre sobre todos los activos, sin paginacion)
    $mrrStmt = $db->prepare("
        SELECT COALESCE(SUM(CASE plan WHEN 'elite' THEN 150 WHEN 'metodo' THEN 120 WHEN 'esencial' THEN 95 ELSE 0 END), 0)
        FROM clients WHERE status = 'activo'
    ");
    $mrrStmt->execute();
    $mrr = (int) $mrrStmt->fetchColumn();

    // Paginated results
    $sql = "SELECT c.id, c.client_code, c.name, c.email, c.plan, c.status,
                   c.fecha_inicio, c.created_at,
                   p.objetivo, p.peso,
                   (SELECT COUNT(*) FROM checkins WHERE client_id = c.id AND coach_reply IS NULL) as pending_checkins,
                   COALESCE(DATEDIFF(CURDATE(), c.fecha_inicio) DIV 7, 0) as weeks_active
            FROM clients c LEFT JOIN client_profiles p ON p.client_id = c.id
            $where
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $clients = $stmt->fetchAll();

    respond([
        'clients' => $clients,
        'total'   => $totalCount,
        'limit'   => $limit,
        'offset'  => $offset,
        'mrr'     => $mrr,
    ]);
}

// POST — create client
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body  = getJsonBody();
    $name  = trim($body['name']  ?? '');
    $email = strtolower(trim($body['email'] ?? ''));
    $pass  = $body['password'] ?? bin2hex(random_bytes(4));  // auto-generate if not provided
    $plan  = $body['plan']   ?? 'esencial';
    $status = $body['status'] ?? 'activo';

    if (!$name || !$email) respondError('Nombre y email requeridos', 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respondError('Email inválido', 422);

    // Auto-generate client code (race-safe)
    $maxNum = $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(client_code, 5) AS UNSIGNED)), 0) FROM clients")->fetchColumn();
    $code   = 'cli-' . str_pad((int)$maxNum + 1, 4, '0', STR_PAD_LEFT);

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

    try {
        $stmt = $db->prepare("
            INSERT INTO clients (client_code, name, email, password_hash, plan, status, fecha_inicio)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([$code, $name, $email, $hash, $plan, $status]);
        $id = $db->lastInsertId();

        // Create default profile
        $db->prepare("INSERT INTO client_profiles (client_id) VALUES (?)")->execute([$id]);

        respond([
            'message'        => 'Cliente creado correctamente',
            'client_id'      => $id,
            'client_code'    => $code,
            'temp_password'  => $pass,  // show only once
        ], 201);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) {
            respondError('Ya existe un cliente con ese email', 409);
        }
        respondError('Error al crear cliente', 500);
    }
}

// PUT — update client
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondError('ID requerido', 422);

    $body    = getJsonBody();
    $allowed = ['name', 'plan', 'status', 'password', 'fecha_inicio'];
    $validPlans    = ['esencial', 'metodo', 'elite', 'rise', 'presencial'];
    $validStatuses = ['activo', 'inactivo', 'pendiente'];
    $fields  = [];
    $values  = [];

    foreach ($allowed as $f) {
        if (array_key_exists($f, $body)) {
            if ($f === 'plan' && !in_array($body[$f], $validPlans, true)) {
                respondError('Plan invalido. Valores: esencial, metodo, elite', 422);
            }
            if ($f === 'status' && !in_array($body[$f], $validStatuses, true)) {
                respondError('Status invalido. Valores: activo, inactivo, pendiente', 422);
            }
            if ($f === 'fecha_inicio' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $body[$f])) {
                respondError('fecha_inicio debe ser formato YYYY-MM-DD', 422);
            }

            // Special handling for password: hash it
            if ($f === 'password') {
                $pass = trim($body[$f] ?? '');
                if (empty($pass)) {
                    respondError('Contraseña no puede estar vacía', 422);
                }
                $fields[] = 'password_hash = ?';
                $values[] = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            } else {
                $fields[] = "$f = ?";
                $values[] = $body[$f];
            }
        }
    }

    // dashboard_video_url goes to client_profiles (separate table)
    $videoUrl = null;
    if (array_key_exists('dashboard_video_url', $body)) {
        $videoUrl = $body['dashboard_video_url'] ? trim($body['dashboard_video_url']) : null;
    }

    if (empty($fields) && $videoUrl === null) respondError('Nada que actualizar', 422);

    if (!empty($fields)) {
        $values[] = $id;
        $db->prepare("UPDATE clients SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
    }

    if ($videoUrl !== null) {
        $chk = $db->prepare("SELECT id FROM client_profiles WHERE client_id = ?");
        $chk->execute([$id]);
        if ($chk->fetchColumn()) {
            $db->prepare("UPDATE client_profiles SET dashboard_video_url = ? WHERE client_id = ?")->execute([$videoUrl ?: null, $id]);
        } else {
            $db->prepare("INSERT INTO client_profiles (client_id, dashboard_video_url) VALUES (?, ?)")->execute([$id, $videoUrl ?: null]);
        }
    }

    respond(['message' => 'Cliente actualizado']);
}
