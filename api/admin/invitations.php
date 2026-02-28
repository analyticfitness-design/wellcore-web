<?php
// GET    /api/admin/invitations           → list all invitations
// GET    /api/admin/invitations?id=X      → single invitation
// POST   /api/admin/invitations           → create new invitation
// DELETE /api/admin/invitations?id=X      → cancel (expire) invitation

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('GET', 'POST', 'DELETE');
$admin = authenticateAdmin();
$db = getDB();

// GET single invitation
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("
        SELECT i.*, a.username AS created_by_name, c.name AS used_by_name, c.email AS used_by_email
        FROM invitations i
        LEFT JOIN admins a ON a.id = i.created_by
        LEFT JOIN clients c ON c.id = i.used_by
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $inv = $stmt->fetch();
    if (!$inv) respondError('Invitacion no encontrada', 404);
    respond(['invitation' => $inv]);
}

// GET list
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? '';

    $sql = "SELECT i.*, a.username AS created_by_name, c.name AS used_by_name
            FROM invitations i
            LEFT JOIN admins a ON a.id = i.created_by
            LEFT JOIN clients c ON c.id = i.used_by
            WHERE 1=1";
    $params = [];

    if ($status) {
        $sql .= " AND i.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY i.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $invitations = $stmt->fetchAll();

    // Stats
    $total = count($invitations);
    $pending = 0;
    $used = 0;
    $expired = 0;
    foreach ($invitations as $inv) {
        if ($inv['status'] === 'pending') $pending++;
        elseif ($inv['status'] === 'used') $used++;
        else $expired++;
    }

    respond([
        'invitations' => $invitations,
        'total'       => $total,
        'pending'     => $pending,
        'used'        => $used,
        'expired'     => $expired,
    ]);
}

// POST — create invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getJsonBody();
    $plan = $body['plan'] ?? '';
    $emailHint = trim($body['email_hint'] ?? '');
    $note = trim($body['note'] ?? '');
    $expiresDays = (int)($body['expires_days'] ?? 30);

    $validPlans = ['esencial', 'metodo', 'elite'];
    if (!in_array($plan, $validPlans, true)) {
        respondError('Plan debe ser: esencial, metodo o elite', 422);
    }

    if ($expiresDays < 1 || $expiresDays > 365) {
        respondError('Dias de expiracion debe ser entre 1 y 365', 422);
    }

    // Generate unique 16-char hex code (32 chars)
    $code = bin2hex(random_bytes(16));
    $expiresAt = date('Y-m-d H:i:s', time() + ($expiresDays * 86400));

    try {
        $stmt = $db->prepare("
            INSERT INTO invitations (code, plan, email_hint, note, status, created_by, expires_at)
            VALUES (?, ?, ?, ?, 'pending', ?, ?)
        ");
        $stmt->execute([
            $code,
            $plan,
            $emailHint ?: null,
            $note ?: null,
            $admin['id'],
            $expiresAt,
        ]);
        $id = $db->lastInsertId();

        respond([
            'message'   => 'Invitacion creada',
            'id'        => $id,
            'code'      => $code,
            'plan'      => $plan,
            'expires_at' => $expiresAt,
        ], 201);
    } catch (PDOException $e) {
        respondError('Error al crear invitacion', 500);
    }
}

// DELETE — cancel/expire invitation
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondError('ID requerido', 422);

    $stmt = $db->prepare("SELECT status FROM invitations WHERE id = ?");
    $stmt->execute([$id]);
    $inv = $stmt->fetch();

    if (!$inv) respondError('Invitacion no encontrada', 404);
    if ($inv['status'] !== 'pending') respondError('Solo se pueden cancelar invitaciones pendientes', 422);

    $db->prepare("UPDATE invitations SET status = 'expired' WHERE id = ?")->execute([$id]);
    respond(['message' => 'Invitacion cancelada']);
}
