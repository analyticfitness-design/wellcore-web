<?php
declare(strict_types=1);
/**
 * WellCore Fitness — RISE Onboarding
 * ============================================================
 * POST /api/admin/rise-onboarding
 *
 * Crea un cliente del RETO RISE y asigna sus planes personalizados.
 * Soporta 3 escenarios de enrollment:
 *   scenario: "platform"   — pagó en la plataforma (datos del formulario)
 *   scenario: "external"   — pagó por fuera (Nequi/efectivo), admin envía datos
 *   scenario: "invitation" — admin lo invita y asigna el plan
 *
 * Body JSON:
 * {
 *   "scenario":     "platform|external|invitation",
 *   "name":         "Silvia Carvajal",
 *   "email":        "silvia@example.com",
 *   "phone":        "+57 300 123 4567",
 *   "gender":       "mujer|hombre",
 *   "nivel":        "principiante|intermedio|avanzado",
 *   "lugar":        "gym|casa|ambos",
 *   "fecha_inicio": "2026-03-01",
 *   "coach":        "silvia|dann",
 *   "plan_entrenamiento": "<html>...</html>",  (opcional — HTML completo)
 *   "plan_nutricion":     "<html>...</html>",  (opcional — HTML o texto)
 *   "plan_habitos":       "<html>...</html>",  (opcional — HTML o texto)
 *   "intake": {                               (opcional — datos del formulario)
 *     "edad": 28, "peso": 62, "altura": 163,
 *     "objetivo": "Perder grasa y ganar glúteos",
 *     "cintura": 63, "cadera": 81, "pecho": 79,
 *     "dias_semana": 5, "duracion_sesion": 45,
 *     "hora_preferida": "tarde"
 *   }
 * }
 *
 * Response: { client_id, client_code, temp_password, message }
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');
$admin = authenticateAdmin();
$db    = getDB();

$body = getJsonBody();

// ── Validación de campos requeridos ──────────────────────────
$name       = trim($body['name']       ?? '');
$email      = strtolower(trim($body['email']     ?? ''));
$phone      = trim($body['phone']      ?? '');
$gender     = $body['gender']    ?? 'mujer';
$nivel      = $body['nivel']     ?? 'principiante';
$lugar      = $body['lugar']     ?? 'gym';
$fechaInicio = $body['fecha_inicio'] ?? date('Y-m-d');
$coach      = $body['coach']     ?? ($gender === 'mujer' ? 'silvia' : 'dann');
$scenario   = $body['scenario']  ?? 'external';
$intake     = $body['intake']    ?? [];

$planEntrenamiento = $body['plan_entrenamiento'] ?? null;
$planNutricion     = $body['plan_nutricion']     ?? null;
$planHabitos       = $body['plan_habitos']        ?? null;

if (!$name || !$email) {
    respondError('name y email son requeridos', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondError('email inválido', 422);
}
if (!in_array($gender, ['mujer', 'hombre'], true)) {
    respondError("gender debe ser 'mujer' o 'hombre'", 422);
}
if (!in_array($nivel, ['principiante', 'intermedio', 'avanzado'], true)) {
    respondError("nivel debe ser principiante|intermedio|avanzado", 422);
}

// ── Verificar si el cliente ya existe ────────────────────────
$existing = $db->prepare("SELECT id, client_code FROM clients WHERE email = ? LIMIT 1");
$existing->execute([$email]);
$existingClient = $existing->fetch();

if ($existingClient) {
    // Cliente ya existe — solo actualizar plan si se proveen
    $clientId   = (int)$existingClient['id'];
    $clientCode = $existingClient['client_code'];
    $tempPassword = null;
    $isNew = false;
} else {
    // ── Crear nuevo cliente ───────────────────────────────────
    $isNew = true;
    // Generar código y contraseña temporal
    $clientCode   = 'RISE-' . strtoupper(substr(md5($email . time()), 0, 6));
    $tempPassword = 'Rise' . rand(1000, 9999) . '!';
    $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    // Primer nombre para el código
    $firstName = explode(' ', $name)[0];

    $stmt = $db->prepare("
        INSERT INTO clients (client_code, name, email, password_hash, plan, status, fecha_inicio)
        VALUES (?, ?, ?, ?, 'rise', 'activo', ?)
    ");
    $stmt->execute([$clientCode, $name, $email, $passwordHash, $fechaInicio]);
    $clientId = (int)$db->lastInsertId();

    // Crear perfil base
    $objetivo = $intake['objetivo'] ?? 'Completar el RETO RISE 30 días';
    $peso     = $intake['peso']     ?? null;
    $altura   = $intake['altura']   ?? null;
    $edad     = $intake['edad']     ?? null;

    $profileStmt = $db->prepare("
        INSERT INTO client_profiles
            (client_id, objetivo, nivel, lugar_entreno, rise_start_date, rise_gender, rise_coach, edad, peso, altura, macros)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $macros = null;
    $profileStmt->execute([
        $clientId, $objetivo, $nivel, $lugar,
        $fechaInicio, $gender, $coach,
        $edad, $peso, $altura, $macros
    ]);
}

// ── Función helper: asignar plan ─────────────────────────────
function assignPlan(PDO $db, int $clientId, string $planType, string $content, int $adminId): void {
    // Desactivar plan anterior del mismo tipo
    $db->prepare("UPDATE assigned_plans SET active = 0 WHERE client_id = ? AND plan_type = ?")->execute([$clientId, $planType]);

    // Siguiente versión
    $ver = $db->prepare("SELECT COALESCE(MAX(version), 0) + 1 FROM assigned_plans WHERE client_id = ? AND plan_type = ?");
    $ver->execute([$clientId, $planType]);
    $version = (int)$ver->fetchColumn();

    $db->prepare("
        INSERT INTO assigned_plans (client_id, plan_type, content, version, assigned_by, valid_from, active)
        VALUES (?, ?, ?, ?, ?, CURDATE(), 1)
    ")->execute([$clientId, $planType, $content, $version, $adminId]);
}

// ── Asignar planes si se proveen ─────────────────────────────
$plansAssigned = [];

if ($planEntrenamiento) {
    assignPlan($db, $clientId, 'entrenamiento', $planEntrenamiento, (int)$admin['id']);
    $plansAssigned[] = 'entrenamiento';

    // La vigencia empieza al DÍA SIGUIENTE de subir el plan
    // Así el cliente tiene tiempo de revisarlo antes de que comience el conteo
    $startDate = date('Y-m-d', strtotime('+1 day'));
    $db->prepare("
        UPDATE client_profiles
        SET rise_start_date = ?
        WHERE client_id = ?
    ")->execute([$startDate, $clientId]);
}

if ($planNutricion) {
    assignPlan($db, $clientId, 'nutricion', $planNutricion, (int)$admin['id']);
    $plansAssigned[] = 'nutricion';
}

if ($planHabitos) {
    assignPlan($db, $clientId, 'habitos', $planHabitos, (int)$admin['id']);
    $plansAssigned[] = 'habitos';
}

// ── Respuesta ─────────────────────────────────────────────────
$response = [
    'ok'            => true,
    'client_id'     => $clientId,
    'client_code'   => $clientCode,
    'is_new_client' => $isNew,
    'plans_assigned'=> $plansAssigned,
    'message'       => $isNew
        ? "Cliente RISE '{$name}' creado con código {$clientCode}. Planes asignados: " . implode(', ', $plansAssigned ?: ['ninguno aún'])
        : "Cliente '{$name}' (existente) actualizado. Planes asignados: " . implode(', ', $plansAssigned ?: ['ninguno']),
    'next_steps'    => [
        '1. Ejecutar migrate-rise.php si aún no se ha hecho',
        "2. Enviar credenciales al cliente: email={$email}, contraseña temporal={$tempPassword}",
        '3. El cliente accede a: wellcorefitness.com/rise-dashboard.html',
        '4. Cambiar contraseña en el primer login',
    ],
];

if ($tempPassword) {
    $response['temp_password'] = $tempPassword;
    $response['login_url']     = 'https://wellcorefitness.com/login.html';
}

respond($response, $isNew ? 201 : 200);
