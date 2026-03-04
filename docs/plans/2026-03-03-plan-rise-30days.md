# RISE 30-Day Challenge Implementation Plan

> **Para Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans para implementar este plan tarea por tarea.

**Objetivo:** Crear e integrar el plan RISE (30-day fitness challenge) con todas las capacidades: super admin, inscripción pública, invitación de CEO, tracking de entrenamientos, guías de hábitos y nutrición.

**Arquitectura:**
- Nuevo enum 'rise' en tabla clients.plan
- Tablas rise_programs (programas personalizados) y rise_daily_logs (tracking diario)
- Endpoints REST para inscripción y consulta de detalles
- Super admin Daniel Esparza con acceso completo
- Formulario público de inscripción
- Sistema de invitaciones para CEO

**Tech Stack:** MySQL, PHP 7+, REST API, HTML5, JavaScript ES6

---

## Task 1: Actualizar Schema SQL

**Archivos:**
- Crear: `api/setup/alter-add-rise-plan.sql`
- Modificar: `api/setup/schema.sql`
- Test: `api/setup/test-schema.sql`

**Step 1: Escribir SQL para agregar 'rise' enum**

```sql
-- alter-add-rise-plan.sql
ALTER TABLE clients MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise') DEFAULT 'esencial';
ALTER TABLE payments MODIFY COLUMN plan_type ENUM('esencial','metodo','elite','rise') DEFAULT 'esencial';
ALTER TABLE invitations MODIFY COLUMN plan_for ENUM('esencial','metodo','elite','rise');
```

**Step 2: Crear tablas rise_programs y rise_daily_logs**

```sql
-- Continuación en alter-add-rise-plan.sql
CREATE TABLE IF NOT EXISTS rise_programs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    experience_level ENUM('principiante','intermedio','avanzado') NOT NULL,
    training_location ENUM('gym','home','hybrid') NOT NULL,
    gender ENUM('male','female','other') NOT NULL,
    status ENUM('active','completed','paused','cancelled') DEFAULT 'active',
    personalized_program LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client (client_id),
    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS rise_daily_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rise_program_id INT NOT NULL,
    log_date DATE NOT NULL,
    workout_completed BOOLEAN DEFAULT FALSE,
    workout_notes TEXT,
    habits_completed INT DEFAULT 0,
    nutrition_adherence ENUM('excellent','good','fair','poor') DEFAULT 'fair',
    mood_level INT COMMENT '1-10',
    energy_level INT COMMENT '1-10',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rise_program_id) REFERENCES rise_programs(id) ON DELETE CASCADE,
    INDEX idx_program (rise_program_id),
    INDEX idx_date (log_date)
);
```

**Step 3: Verificar archivos SQL**

Run: `cat api/setup/alter-add-rise-plan.sql`
Expected: Ver sentencias ALTER TABLE y CREATE TABLE completas

**Step 4: Ejecutar migración en BD**

Run: `mysql -h 127.0.0.1 -u root -pQY@P6Ak2? wellcore_fitness < api/setup/alter-add-rise-plan.sql`
Expected: Sin errores, schema actualizado

**Step 5: Commit**

```bash
git add api/setup/alter-add-rise-plan.sql
git commit -m "feat: add rise plan schema with enrollment and tracking tables"
```

---

## Task 2: Crear Super Admin (Daniel Esparza)

**Archivos:**
- Crear: `api/setup/create-superadmin.php`
- Modificar: `api/auth/login.php` (verificar soporte para superadmin)

**Step 1: Escribir script para crear super admin**

```php
<?php
// create-superadmin.php
require_once __DIR__ . '/../config/database.php';

$username = 'daniel.esparza';
$email = 'analyticfitness@gmail.com';
$password = 'RISE2026Admin!SuperPower';
$name = 'Daniel Esparza - CEO';
$role = 'superadmin';

// Hash password
$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $stmt = $pdo->prepare("
        INSERT INTO admins (username, password_hash, role, name, email, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    $result = $stmt->execute([$username, $password_hash, $role, $name, $email]);

    if ($result) {
        echo "✅ Super Admin creado exitosamente:\n";
        echo "Usuario: $username\n";
        echo "Email: $email\n";
        echo "Contraseña: $password\n";
        echo "Rol: $role\n";
        echo "\n⚠️ GUARDA ESTAS CREDENCIALES EN LUGAR SEGURO\n";
    } else {
        echo "❌ Error al crear super admin\n";
    }
} catch (PDOException $e) {
    echo "❌ Error DB: " . $e->getMessage() . "\n";
}
?>
```

**Step 2: Ejecutar script para crear super admin**

Run: `php api/setup/create-superadmin.php`
Expected: Output confirmando creación con credenciales

**Step 3: Verificar en BD**

Run: `mysql -h 127.0.0.1 -u root -pQY@P6Ak2? wellcore_fitness -e "SELECT id, username, role, email FROM admins WHERE role='superadmin';"`
Expected: Fila con daniel.esparza, superadmin, analyticfitness@gmail.com

**Step 4: Commit**

```bash
git add api/setup/create-superadmin.php
git commit -m "feat: create superadmin account for CEO Daniel Esparza"
```

---

## Task 3: Crear Endpoints de Inscripción (/api/rise/)

**Archivos:**
- Crear: `api/rise/enroll.php`
- Crear: `api/rise/get-details.php`
- Test: Manual con curl o formulario

**Step 1: Crear /api/rise/enroll.php**

```php
<?php
// api/rise/enroll.php - Endpoint público de inscripción
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validar inputs
$required = ['email', 'name', 'password', 'experience_level', 'training_location', 'gender'];
foreach ($required as $field) {
    if (!isset($input[$field]) || trim($input[$field]) === '') {
        http_response_code(400);
        echo json_encode(['error' => "Campo requerido: $field"]);
        exit;
    }
}

$email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
$name = htmlspecialchars($input['name'], ENT_QUOTES, 'UTF-8');
$password = $input['password'];
$experience_level = $input['experience_level'];
$training_location = $input['training_location'];
$gender = $input['gender'];

// Validar que no exista cliente
try {
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Email ya registrado']);
        exit;
    }

    // Crear cliente con plan rise
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $client_code = 'rise-' . strtoupper(bin2hex(random_bytes(4)));

    $stmt = $pdo->prepare("
        INSERT INTO clients (client_code, name, email, password_hash, plan, status, created_at)
        VALUES (?, ?, ?, ?, 'rise', 'active', NOW())
    ");

    $stmt->execute([$client_code, $name, $email, $password_hash]);
    $client_id = $pdo->lastInsertId();

    // Crear programa RISE
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime('+30 days'));

    $stmt = $pdo->prepare("
        INSERT INTO rise_programs
        (client_id, start_date, end_date, experience_level, training_location, gender, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')
    ");

    $stmt->execute([$client_id, $start_date, $end_date, $experience_level, $training_location, $gender]);
    $program_id = $pdo->lastInsertId();

    // Respuesta exitosa
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Inscripción exitosa al reto RISE',
        'client' => [
            'id' => $client_id,
            'code' => $client_code,
            'name' => $name,
            'email' => $email,
            'plan' => 'rise'
        ],
        'program' => [
            'id' => $program_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'duration_days' => 30
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en base de datos']);
}
?>
```

**Step 2: Crear /api/rise/get-details.php**

```php
<?php
// api/rise/get-details.php - Obtener detalles del programa RISE
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$program_id = isset($_GET['program_id']) ? intval($_GET['program_id']) : null;
if (!$program_id) {
    http_response_code(400);
    echo json_encode(['error' => 'program_id requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            rp.id, rp.client_id, rp.start_date, rp.end_date,
            rp.experience_level, rp.training_location, rp.gender, rp.status,
            c.name, c.email,
            (SELECT COUNT(*) FROM rise_daily_logs WHERE rise_program_id = rp.id AND workout_completed = TRUE) as workouts_completed,
            (SELECT COUNT(DISTINCT log_date) FROM rise_daily_logs WHERE rise_program_id = rp.id) as days_logged
        FROM rise_programs rp
        JOIN clients c ON rp.client_id = c.id
        WHERE rp.id = ?
    ");

    $stmt->execute([$program_id]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$program) {
        http_response_code(404);
        echo json_encode(['error' => 'Programa no encontrado']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'program' => $program
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en base de datos']);
}
?>
```

**Step 3: Probar endpoints**

Run (enroll):
```bash
curl -k -X POST https://wellcorefitness.test/api/rise/enroll.php \
  -H "Content-Type: application/json" \
  -d '{
    "email":"test.rise@example.com",
    "name":"Test Usuario RISE",
    "password":"TestRISE2026!",
    "experience_level":"intermedio",
    "training_location":"gym",
    "gender":"male"
  }'
```
Expected: HTTP 201, respuesta con client_id y program_id

**Step 4: Commit**

```bash
git add api/rise/enroll.php api/rise/get-details.php
git commit -m "feat: add RISE enrollment and details API endpoints"
```

---

## Task 4: Crear Formulario Público de Inscripción

**Archivos:**
- Crear: `rise-enroll.html`

**Step 1: Crear formulario HTML**

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscribirse al Reto RISE - WellCore Fitness</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .message {
            margin-top: 20px;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            display: none;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            display: block;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            display: block;
        }
        .loading {
            display: none;
            text-align: center;
            color: #667eea;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Reto RISE</h1>
            <p>30 días transformando tu entrenamiento • $99,900 COP / $33 USD</p>
        </div>

        <form id="enrollForm">
            <div class="form-group">
                <label for="name">Nombre Completo *</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña *</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="experience">Experiencia *</label>
                    <select id="experience" name="experience_level" required>
                        <option value="">Selecciona</option>
                        <option value="principiante">Principiante</option>
                        <option value="intermedio">Intermedio</option>
                        <option value="avanzado">Avanzado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="location">Dónde entrenas *</label>
                    <select id="location" name="training_location" required>
                        <option value="">Selecciona</option>
                        <option value="gym">Gimnasio</option>
                        <option value="home">Casa</option>
                        <option value="hybrid">Ambos</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="gender">Género *</label>
                <select id="gender" name="gender" required>
                    <option value="">Selecciona</option>
                    <option value="male">Masculino</option>
                    <option value="female">Femenino</option>
                    <option value="other">Otro</option>
                </select>
            </div>

            <button type="submit" id="submitBtn">Inscribirse al Reto</button>
            <div class="loading" id="loading">Procesando inscripción...</div>
            <div class="message" id="message"></div>
        </form>
    </div>

    <script>
        document.getElementById('enrollForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const form = document.getElementById('enrollForm');
            const submitBtn = document.getElementById('submitBtn');
            const loading = document.getElementById('loading');
            const message = document.getElementById('message');

            submitBtn.disabled = true;
            loading.style.display = 'block';
            message.style.display = 'none';

            const data = {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
                experience_level: document.getElementById('experience').value,
                training_location: document.getElementById('location').value,
                gender: document.getElementById('gender').value
            };

            try {
                const response = await fetch('/api/rise/enroll.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    message.className = 'message success';
                    message.textContent = '✅ Inscripción exitosa. Accede con tu email y contraseña en login.html';
                    message.style.display = 'block';
                    form.reset();
                } else {
                    message.className = 'message error';
                    message.textContent = '❌ ' + (result.error || 'Error en inscripción');
                    message.style.display = 'block';
                }
            } catch (error) {
                message.className = 'message error';
                message.textContent = '❌ Error de conexión';
                message.style.display = 'block';
            } finally {
                submitBtn.disabled = false;
                loading.style.display = 'none';
            }
        });
    </script>
</body>
</html>
```

**Step 2: Verificar formulario**

Run: `ls -la rise-enroll.html`
Expected: Archivo existe en raíz

**Step 3: Probar en navegador**

Navigate: `https://wellcorefitness.test/rise-enroll.html`
Expected: Formulario carga con campos para inscripción

**Step 4: Commit**

```bash
git add rise-enroll.html
git commit -m "feat: create public RISE challenge enrollment form"
```

---

## Task 4.5: Crear Formulario de INTAKE Completo (Post-Inscripción)

**Archivos:**
- Crear: `rise-intake.html`
- Test: Completar formulario y verificar almacenamiento

**Step 1: Crear formulario INTAKE basado en estructura Tally**

Este formulario captura información detallada DESPUÉS de que el usuario se inscribe en rise-enroll.html.

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intake RISE - Información Personalizada</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            margin-bottom: 30px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.3s;
        }
        .section { margin-bottom: 30px; }
        .section h3 {
            color: #333;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        .checkbox-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
        }
        .checkbox-item input {
            width: auto;
            margin-right: 8px;
        }
        .range-input {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #e0e0e0;
            outline: none;
            -webkit-appearance: none;
        }
        .range-input::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
        }
        .range-input::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
            border: none;
        }
        .range-value {
            text-align: center;
            color: #667eea;
            font-weight: 600;
            margin-top: 8px;
        }
        .file-input-wrapper {
            position: relative;
        }
        .file-input-wrapper input[type="file"] {
            display: none;
        }
        .file-input-label {
            display: block;
            padding: 20px;
            border: 2px dashed #667eea;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            background: #f8f9ff;
            transition: all 0.3s;
        }
        .file-input-label:hover {
            border-color: #764ba2;
            background: #f0f2ff;
        }
        .button-group {
            display: flex;
            gap: 12px;
            justify-content: space-between;
            margin-top: 30px;
        }
        button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .message {
            margin-top: 20px;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            display: none;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            display: block;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            display: block;
        }
        .hidden { display: none !important; }
    </style>
</head>
<body>
    <div class="container">
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>

        <form id="intakeForm">
            <!-- Sección 1: Medidas Corporales -->
            <div class="section">
                <h3>📏 Medidas Corporales</h3>

                <div class="form-group">
                    <label for="cintura">Cintura (cm) *</label>
                    <input type="number" id="cintura" name="cintura" required>
                </div>

                <div class="form-group">
                    <label for="cadera">Cadera (cm) *</label>
                    <input type="number" id="cadera" name="cadera" required>
                </div>

                <div class="form-group">
                    <label for="pecho">Pecho (cm) *</label>
                    <input type="number" id="pecho" name="pecho" required>
                </div>

                <div class="form-group">
                    <label for="grasa">¿Sabes tu % de grasa corporal? *</label>
                    <select id="grasa" name="conoce_grasa" required onchange="toggleGrasaField()">
                        <option value="">Selecciona</option>
                        <option value="si">Sí lo sé</option>
                        <option value="no-exactamente">No lo sé exactamente</option>
                        <option value="nunca">Nunca me lo he medido</option>
                    </select>
                </div>

                <div class="form-group hidden" id="grasaField">
                    <label for="porcentajeGrasa">% de Grasa Corporal</label>
                    <input type="number" id="porcentajeGrasa" name="porcentaje_grasa" min="5" max="60" step="0.1">
                </div>

                <div class="form-group">
                    <label for="fotos">Fotos de Inicio (Frente, lado, espalda) *</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="fotos" name="fotos" accept="image/*" multiple required>
                        <label for="fotos" class="file-input-label">
                            📸 Sube fotos (máx. 10 MB cada una)
                        </label>
                    </div>
                </div>
            </div>

            <!-- Sección 2: Historial de Entrenamiento -->
            <div class="section">
                <h3>💪 Historial de Entrenamiento</h3>

                <div class="form-group">
                    <label for="tiempoEntrenando">¿Cuánto tiempo llevas entrenando? *</label>
                    <select id="tiempoEntrenando" name="tiempo_entrenando" required>
                        <option value="">Selecciona</option>
                        <option value="nunca">Nunca / Principiante</option>
                        <option value="menos-6">Menos de 6 meses</option>
                        <option value="6-12">6 meses - 1 año</option>
                        <option value="1-2">1 - 2 años</option>
                        <option value="mas-2">Más de 2 años</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tipo de entrenamiento previo (marca los que has hecho) *</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" name="tipo_entreno" value="pesas"> Pesas
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="tipo_entreno" value="cardio"> Cardio
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="tipo_entreno" value="funcional"> Funcional
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="tipo_entreno" value="calistenia"> Calistenia
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="tipo_entreno" value="yoga"> Yoga
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="tipo_entreno" value="deportes"> Deportes
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="tipo_entreno" value="ninguno"> Ninguno
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="ejerciciosEvitar">¿Hay ejercicios que debas evitar? (lesiones, etc) *</label>
                    <textarea id="ejerciciosEvitar" name="ejercicios_evitar" placeholder="Ej: No puedo hacer sentadillas por lesión de rodilla" required></textarea>
                </div>
            </div>

            <!-- Sección 3: Disponibilidad -->
            <div class="section">
                <h3>⏰ Disponibilidad y Equipamiento</h3>

                <div class="form-group">
                    <label for="horaPreferida">Hora preferida para entrenar *</label>
                    <select id="horaPreferida" name="hora_preferida" required>
                        <option value="">Selecciona</option>
                        <option value="manana">Mañana (5am - 9am)</option>
                        <option value="mediodía">Mediodía (9am - 1pm)</option>
                        <option value="tarde">Tarde (1pm - 6pm)</option>
                        <option value="noche">Noche (6pm - 10pm)</option>
                        <option value="variable">Variable</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="duracionSesion">Duración preferida de sesión *</label>
                    <select id="duracionSesion" name="duracion_sesion" required>
                        <option value="">Selecciona</option>
                        <option value="30">30 minutos</option>
                        <option value="45">45 minutos</option>
                        <option value="60">60 minutos</option>
                        <option value="90">90+ minutos</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>¿Qué equipamiento tienes en casa? *</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" name="equipamiento" value="mancuernas"> Mancuernas
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="equipamiento" value="barra"> Barra
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="equipamiento" value="kettlebell"> Kettlebell
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="equipamiento" value="colchoneta"> Colchoneta
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="equipamiento" value="bandas"> Bandas elásticas
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="equipamiento" value="ninguno"> Ninguno
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección 4: Alimentación -->
            <div class="section">
                <h3>🍽️ Alimentación</h3>

                <div class="form-group">
                    <label for="tipoDieta">¿Qué tipo de dieta sigues? *</label>
                    <select id="tipoDieta" name="tipo_dieta" required>
                        <option value="">Selecciona</option>
                        <option value="omnivora">Omnívora (todo)</option>
                        <option value="vegetariana">Vegetariana</option>
                        <option value="vegana">Vegana</option>
                        <option value="baja-carb">Baja en carbohidratos</option>
                        <option value="cetogenica">Cetogénica</option>
                        <option value="otra">Otra (especifica abajo)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="suplementos">¿Tomas suplementos? *</label>
                    <select id="suplementos" name="toma_suplementos" required onchange="toggleSupplementosField()">
                        <option value="">Selecciona</option>
                        <option value="si">Sí</option>
                        <option value="no">No</option>
                        <option value="a-veces">A veces</option>
                    </select>
                </div>

                <div class="form-group hidden" id="suplementosField">
                    <label for="cualesSupplementos">¿Cuáles suplementos tomas?</label>
                    <textarea id="cualesSupplementos" name="cuales_suplementos" placeholder="Ej: Proteína, creatina, multivitamínico..."></textarea>
                </div>
            </div>

            <!-- Sección 5: Estilo de Vida -->
            <div class="section">
                <h3>😴 Estilo de Vida</h3>

                <div class="form-group">
                    <label for="sueno">Horas de sueño promedio *</label>
                    <select id="sueno" name="horas_sueno" required>
                        <option value="">Selecciona</option>
                        <option value="menos-6">Menos de 6 horas</option>
                        <option value="6-7">6 - 7 horas</option>
                        <option value="7-8">7 - 8 horas</option>
                        <option value="mas-8">Más de 8 horas</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="actividad">Nivel de actividad diaria *</label>
                    <select id="actividad" name="actividad_diaria" required>
                        <option value="">Selecciona</option>
                        <option value="sedentaria">Sedentaria (poco movimiento)</option>
                        <option value="moderada">Moderada (trabajo mezclado)</option>
                        <option value="activa">Activa (trabajo de pie/movimiento)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="estres">Nivel de estrés (0 = sin estrés, 10 = muy estresado) *</label>
                    <input type="range" id="estres" name="nivel_estres" class="range-input" min="0" max="10" value="5" required oninput="updateRangeValue(this)">
                    <div class="range-value"><span id="estresValue">5</span>/10</div>
                </div>
            </div>

            <!-- Sección 6: Motivación y Compromiso -->
            <div class="section">
                <h3>🎯 Motivación y Compromiso</h3>

                <div class="form-group">
                    <label for="razon">¿Por qué te inscribiste en el reto RISE? *</label>
                    <textarea id="razon" name="razon_inscripcion" placeholder="Cuéntanos qué te motivó..." required></textarea>
                </div>

                <div class="form-group">
                    <label for="resultado">¿Cuál es tu resultado esperado en 30 días? *</label>
                    <textarea id="resultado" name="resultado_esperado" placeholder="Ej: Perder 5kg, ganar músculo, tener más energía..." required></textarea>
                </div>

                <div class="form-group">
                    <label for="compromiso">Nivel de compromiso para completar el reto (0 = bajo, 10 = máximo) *</label>
                    <input type="range" id="compromiso" name="nivel_compromiso" class="range-input" min="0" max="10" value="7" required oninput="updateRangeValue(this)">
                    <div class="range-value"><span id="compromisoValue">7</span>/10</div>
                </div>

                <div class="form-group">
                    <label for="info_adicional">¿Hay algo más que debamos saber?</label>
                    <textarea id="info_adicional" name="info_adicional" placeholder="Información adicional, alergias, notas importantes..."></textarea>
                </div>
            </div>

            <div class="button-group">
                <button type="button" class="btn-secondary" onclick="saveDraft()">Guardar Borrador</button>
                <button type="submit" class="btn-primary">Enviar Información</button>
            </div>

            <div class="message" id="message"></div>
        </form>
    </div>

    <script>
        function toggleGrasaField() {
            const field = document.getElementById('grasaField');
            const value = document.getElementById('grasa').value;
            field.classList.toggle('hidden', value !== 'si');
        }

        function toggleSupplementosField() {
            const field = document.getElementById('suplementosField');
            const value = document.getElementById('suplementos').value;
            field.classList.toggle('hidden', value === 'no');
        }

        function updateRangeValue(input) {
            const id = input.id === 'estres' ? 'estresValue' : 'compromisoValue';
            document.getElementById(id).textContent = input.value;
            updateProgress();
        }

        function updateProgress() {
            const form = document.getElementById('intakeForm');
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let filled = 0;

            inputs.forEach(input => {
                if (input.type === 'file') {
                    if (input.files.length > 0) filled++;
                } else if (input.type === 'checkbox') {
                    // Skip individual checkboxes
                } else if (input.value.trim() !== '') {
                    filled++;
                }
            });

            const progress = (filled / inputs.length) * 100;
            document.getElementById('progressFill').style.width = progress + '%';
        }

        function saveDraft() {
            const form = document.getElementById('intakeForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            localStorage.setItem('rise_intake_draft', JSON.stringify(data));

            const message = document.getElementById('message');
            message.className = 'message success';
            message.textContent = '✅ Borrador guardado localmente';
            message.style.display = 'block';

            setTimeout(() => {
                message.style.display = 'none';
            }, 3000);
        }

        document.getElementById('intakeForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const form = document.getElementById('intakeForm');
            const formData = new FormData(form);

            try {
                const response = await fetch('/api/rise/save-intake.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    const message = document.getElementById('message');
                    message.className = 'message success';
                    message.textContent = '✅ Información guardada. Tu programa personalizado está siendo creado...';
                    message.style.display = 'block';

                    localStorage.removeItem('rise_intake_draft');
                    form.reset();

                    setTimeout(() => {
                        window.location.href = '/dashboard.html';
                    }, 2000);
                } else {
                    throw new Error(result.error || 'Error al guardar');
                }
            } catch (error) {
                const message = document.getElementById('message');
                message.className = 'message error';
                message.textContent = '❌ ' + error.message;
                message.style.display = 'block';
            }
        });

        // Restaurar borrador al cargar
        window.addEventListener('load', () => {
            const draft = localStorage.getItem('rise_intake_draft');
            if (draft) {
                const data = JSON.parse(draft);
                Object.keys(data).forEach(key => {
                    const input = document.querySelector(`[name="${key}"]`);
                    if (input) {
                        if (input.type === 'checkbox') {
                            input.checked = data[key] === 'on';
                        } else {
                            input.value = data[key];
                        }
                    }
                });
            }
            updateProgress();
        });

        // Actualizar progreso mientras escriben
        document.getElementById('intakeForm').addEventListener('change', updateProgress);
        document.getElementById('intakeForm').addEventListener('input', updateProgress);
    </script>
</body>
</html>
```

**Step 2: Crear endpoint PHP para guardar intake**

Crear archivo `api/rise/save-intake.php`:

```php
<?php
// api/rise/save-intake.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Obtener client_id del token
$token = $_POST['token'] ?? null;
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Token requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT client_id FROM auth_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $client_id = $stmt->fetchColumn();

    if (!$client_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido o expirado']);
        exit;
    }

    // Obtener programa RISE del cliente
    $stmt = $pdo->prepare("SELECT id FROM rise_programs WHERE client_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$client_id]);
    $program_id = $stmt->fetchColumn();

    if (!$program_id) {
        http_response_code(404);
        echo json_encode(['error' => 'Programa RISE no encontrado']);
        exit;
    }

    // Guardar datos del intake
    $intake_data = [
        'cintura' => $_POST['cintura'] ?? null,
        'cadera' => $_POST['cadera'] ?? null,
        'pecho' => $_POST['pecho'] ?? null,
        'conoce_grasa' => $_POST['conoce_grasa'] ?? null,
        'porcentaje_grasa' => $_POST['porcentaje_grasa'] ?? null,
        'tiempo_entrenando' => $_POST['tiempo_entrenando'] ?? null,
        'tipo_entreno' => implode(',', $_POST['tipo_entreno'] ?? []),
        'ejercicios_evitar' => $_POST['ejercicios_evitar'] ?? null,
        'hora_preferida' => $_POST['hora_preferida'] ?? null,
        'duracion_sesion' => $_POST['duracion_sesion'] ?? null,
        'equipamiento' => implode(',', $_POST['equipamiento'] ?? []),
        'tipo_dieta' => $_POST['tipo_dieta'] ?? null,
        'toma_suplementos' => $_POST['toma_suplementos'] ?? null,
        'cuales_suplementos' => $_POST['cuales_suplementos'] ?? null,
        'horas_sueno' => $_POST['horas_sueno'] ?? null,
        'actividad_diaria' => $_POST['actividad_diaria'] ?? null,
        'nivel_estres' => $_POST['nivel_estres'] ?? null,
        'razon_inscripcion' => $_POST['razon_inscripcion'] ?? null,
        'resultado_esperado' => $_POST['resultado_esperado'] ?? null,
        'nivel_compromiso' => $_POST['nivel_compromiso'] ?? null,
        'info_adicional' => $_POST['info_adicional'] ?? null
    ];

    // Actualizar programa con intake data
    $stmt = $pdo->prepare("
        UPDATE rise_programs
        SET personalized_program = ?
        WHERE id = ?
    ");

    $stmt->execute([json_encode($intake_data), $program_id]);

    // Procesar fotos si existen
    if (!empty($_FILES['fotos'])) {
        $upload_dir = __DIR__ . '/../../uploads/rise/' . $program_id;
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        foreach ($_FILES['fotos']['tmp_name'] as $key => $tmp) {
            $filename = time() . '_' . $key . '.' . pathinfo($_FILES['fotos']['name'][$key], PATHINFO_EXTENSION);
            move_uploaded_file($tmp, $upload_dir . '/' . $filename);
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Información de intake guardada exitosamente',
        'program_id' => $program_id
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en base de datos']);
}
?>
```

**Step 3: Verificar formulario en navegador**

Run: `ls -la rise-intake.html`
Expected: Archivo existe con toda la estructura

**Step 4: Commit**

```bash
git add rise-intake.html api/rise/save-intake.php
git commit -m "feat: add comprehensive RISE intake form post-enrollment"
```

---

## Task 5: Crear Invitación para CEO Daniel Esparza

**Archivos:**
- Crear: `api/setup/create-ceo-invitation.php`

**Step 1: Crear script de invitación**

```php
<?php
// api/setup/create-ceo-invitation.php
require_once __DIR__ . '/../config/database.php';

$ceo_email = 'analyticfitness@gmail.com';
$ceo_name = 'Daniel Esparza';
$token = bin2hex(random_bytes(32));
$expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));

try {
    // Crear invitación
    $stmt = $pdo->prepare("
        INSERT INTO invitations (email, plan_for, token, expires_at, created_at)
        VALUES (?, 'rise', ?, ?, NOW())
    ");

    $stmt->execute([$ceo_email, $token, $expires_at]);

    // URL de invitación
    $invitation_url = "https://wellcorefitness.test/api/auth/accept-invitation.php?token=$token";

    echo "✅ Invitación creada para CEO:\n";
    echo "Email: $ceo_email\n";
    echo "Nombre: $ceo_name\n";
    echo "Plan: rise (con rol superadmin)\n";
    echo "Token: $token\n";
    echo "Expira: $expires_at\n";
    echo "\nURL de Invitación:\n";
    echo "$invitation_url\n";
    echo "\nEnviar este link a: $ceo_email\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
```

**Step 2: Ejecutar script**

Run: `php api/setup/create-ceo-invitation.php`
Expected: Salida con URL de invitación

**Step 3: Verificar BD**

Run: `mysql -h 127.0.0.1 -u root -pQY@P6Ak2? wellcore_fitness -e "SELECT email, plan_for, token, expires_at FROM invitations WHERE email='analyticfitness@gmail.com';"`
Expected: Fila de invitación para CEO

**Step 4: Commit**

```bash
git add api/setup/create-ceo-invitation.php
git commit -m "feat: create superadmin invitation for CEO Daniel Esparza"
```

---

## Task 6: Actualizar Dashboard Admin para Super Admin

**Archivos:**
- Modificar: `admin.html` (agregar sección RISE)
- Modificar: `js/admin.js` (agregar lógica para super admin)

**Step 1: Agregar sección RISE a admin.html**

Agregar este nav item después del menú existente en admin.html:

```html
<li class="menu-item" onclick="showSection('rise-dashboard')">
    <span class="menu-icon">🚀</span>
    <span class="menu-label">RISE Dashboard</span>
</li>
```

**Step 2: Agregar contenido de RISE Dashboard**

Agregar en el div admin-content de admin.html:

```html
<div class="section" id="rise-dashboard" style="display:none;">
    <h2>RISE Challenge - 30 Día Reto</h2>
    <div class="dashboard-grid">
        <div class="card">
            <h3>Inscritos Totales</h3>
            <p class="metric" id="total-rise-clients">--</p>
        </div>
        <div class="card">
            <h3>En Progreso</h3>
            <p class="metric" id="active-programs">--</p>
        </div>
        <div class="card">
            <h3>Completados</h3>
            <p class="metric" id="completed-programs">--</p>
        </div>
        <div class="card">
            <h3>Ingresos RISE</h3>
            <p class="metric" id="rise-revenue">--</p>
        </div>
    </div>

    <h3 style="margin-top: 30px;">Inscritos Recientes</h3>
    <table id="rise-table" class="data-table">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Email</th>
                <th>Nivel</th>
                <th>Ubicación</th>
                <th>Estado</th>
                <th>Inscripción</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
```

**Step 3: Agregar CSS para RISE Dashboard**

```css
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.card {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.card h3 {
    color: #666;
    font-size: 14px;
    margin-bottom: 10px;
}

.metric {
    font-size: 32px;
    font-weight: 700;
    color: #333;
}
```

**Step 4: Agregar JavaScript para cargar datos RISE**

En js/admin.js, agregar función:

```javascript
function loadRiseDashboard() {
    // Fetch RISE statistics
    fetch('/api/rise/get-details.php')
        .then(r => r.json())
        .then(data => {
            // Actualizar métricas
            document.getElementById('total-rise-clients').textContent =
                data.total_clients || '0';
            document.getElementById('active-programs').textContent =
                data.active_programs || '0';
            // ... más métricas
        });
}

// Agregar listener al mostrar sección
document.addEventListener('section-changed', (e) => {
    if (e.detail === 'rise-dashboard') {
        loadRiseDashboard();
    }
});
```

**Step 5: Commit**

```bash
git add admin.html js/admin.js
git commit -m "feat: add RISE dashboard section to superadmin interface"
```

---

## Task 7: Documentación y Testing Completo

**Archivos:**
- Crear: `docs/RISE-IMPLEMENTATION.md`
- Test: Validar todo funciona end-to-end

**Step 1: Crear documentación**

Documentación completa del plan RISE incluyendo:
- Credenciales (super admin y usuarios de prueba)
- URLs de endpoints
- Flujos de usuario
- Precios y planes
- Sistema de tracking

**Step 2: Testing end-to-end**

1. Inscribir usuario nuevo via rise-enroll.html
2. Verificar cliente creado en BD con plan='rise'
3. Verificar programa creado en rise_programs
4. Login como super admin Daniel Esparza
5. Ver RISE dashboard con métricas
6. Registrar workout en daily logs

**Step 3: Verificar pagos**

Asegurar que pagos RISE se registren con:
- plan_type: 'rise'
- amount: 99900 (COP) o 33 (USD)
- status: 'pending' → 'completed'

**Step 4: Commit Final**

```bash
git add docs/RISE-IMPLEMENTATION.md
git commit -m "docs: add RISE challenge complete implementation guide"
```

---

## Detalles de Ejecución

**Datos iniciales RISE:**
- Nombre: RISE 30-Day Challenge
- Precio: $99,900 COP / $33 USD
- Duración: 30 días
- Incluye: Programa personalizado, guía de hábitos, guía de nutrición, tracking diario
- Super Admin: Daniel Esparza (analyticfitness@gmail.com)
- Formulario público: rise-enroll.html
- Estado: Listo para inscritos

**Commits Esperados:**
1. feat: add rise plan schema with enrollment and tracking tables
2. feat: create superadmin account for CEO Daniel Esparza
3. feat: add RISE enrollment and details API endpoints
4. feat: create public RISE challenge enrollment form
5. feat: create superadmin invitation for CEO Daniel Esparza
6. feat: add RISE dashboard section to superadmin interface
7. docs: add RISE challenge complete implementation guide

---

**Notas Importantes:**
- Credencial super admin debe guardarse de forma segura
- Cambiar contraseña por defecto en producción
- El formulario rise-enroll.html es público (sin autenticación)
- Cada inscripción crea automáticamente un programa de 30 días
- Daily logs se crean bajo demanda cuando el usuario registra su workout
