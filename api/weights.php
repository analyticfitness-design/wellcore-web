<?php
// WellCore Fitness — Weight Log API
// POST /api/weights.php          → save a weight entry
// GET  /api/weights.php?client_id=X                         → all entries for client
// GET  /api/weights.php?client_id=X&exercise=Sentadilla     → entries for one exercise

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/cors.php';
require_once __DIR__ . '/includes/response.php';
require_once __DIR__ . '/includes/auth.php';

requireMethod('GET', 'POST');

// ─── Storage helpers (JSON file fallback when DB not available) ───────────────
define('WL_JSON', __DIR__ . '/data/weights.json');

function wlReadJson(): array {
    if (!file_exists(WL_JSON)) return [];
    $raw = file_get_contents(WL_JSON);
    if ($raw === false || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function wlWriteJson(array $data): void {
    file_put_contents(WL_JSON, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function wlGenerateId(): string {
    return 'w-' . substr(bin2hex(random_bytes(6)), 0, 8);
}

// ─── Authenticate ────────────────────────────────────────────────────────────
$clientId = null;

try {
    $client = authenticateClient();
    $clientId = $client['id'];
} catch (Throwable $e) {
    respondError('Authentication required', 503);
}

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $reqClientId = filter_var($_GET['client_id'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
    $reqExercise = filter_var($_GET['exercise'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);

    if (!$reqClientId) {
        respondError('client_id is required', 400);
    }

    // Authorise: users can only read their own data
    if ((string)$client['id'] !== (string)$reqClientId) {
        respondError('Forbidden', 403);
    }

    {
        // ── MySQL path ──────────────────────────────────────────────────────────
        try {
            $db = getDB();
            if ($reqExercise) {
                $stmt = $db->prepare("
                    SELECT * FROM weight_logs
                    WHERE client_id = ? AND exercise = ?
                    ORDER BY `year` DESC, week_number DESC, `date` DESC
                ");
                $stmt->execute([$reqClientId, $reqExercise]);
            } else {
                $stmt = $db->prepare("
                    SELECT * FROM weight_logs
                    WHERE client_id = ?
                    ORDER BY `year` DESC, week_number DESC, `date` DESC
                ");
                $stmt->execute([$reqClientId]);
            }
            respond(['entries' => $stmt->fetchAll()]);
        } catch (Throwable $e) {
            // Fall through to JSON
        }
    }

    // ── JSON fallback path ──────────────────────────────────────────────────────
    $all = wlReadJson();
    $filtered = array_values(array_filter($all, function($row) use ($reqClientId, $reqExercise) {
        if ((string)$row['client_id'] !== (string)$reqClientId) return false;
        if ($reqExercise && $row['exercise'] !== $reqExercise) return false;
        return true;
    }));
    // Sort: newest first
    usort($filtered, function($a, $b) {
        return strcmp($b['date'] ?? '', $a['date'] ?? '');
    });
    respond(['entries' => $filtered]);
}

// ─── POST ─────────────────────────────────────────────────────────────────────
$body = getJsonBody();

// Validate & sanitise inputs
$exercise  = filter_var($body['exercise']  ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
$weight_kg = filter_var($body['weight_kg'] ?? 0,  FILTER_VALIDATE_FLOAT);
$sets      = filter_var($body['sets']      ?? 0,  FILTER_VALIDATE_INT);
$reps      = filter_var($body['reps']      ?? 0,  FILTER_VALIDATE_INT);
$rpe       = isset($body['rpe']) && $body['rpe'] !== null && $body['rpe'] !== ''
             ? filter_var($body['rpe'], FILTER_VALIDATE_FLOAT)
             : null;
$notes     = filter_var($body['notes']     ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
$week      = filter_var($body['week']      ?? (int)date('W'), FILTER_VALIDATE_INT);
$year      = filter_var($body['year']      ?? (int)date('Y'), FILTER_VALIDATE_INT);
$date      = $body['date'] ?? date('Y-m-d H:i:s');

// Basic validation
if (!$exercise) { respondError('exercise is required', 422); }
if ($weight_kg === false || $weight_kg <= 0) { respondError('weight_kg must be a positive number', 422); }
if ($sets === false || $sets < 1 || $sets > 99) { respondError('sets must be between 1 and 99', 422); }
if ($reps === false || $reps < 1 || $reps > 999) { respondError('reps must be between 1 and 999', 422); }
if ($rpe !== null && ($rpe < 1 || $rpe > 10)) { respondError('rpe must be between 1 and 10', 422); }
if ($week === false || $week < 1 || $week > 53) { respondError('week must be between 1 and 53', 422); }
if ($year === false || $year < 2024 || $year > 2050) { respondError('year is invalid', 422); }

// Validate date format — store as MySQL datetime
$parsedDate = date('Y-m-d H:i:s', strtotime($date));
if (!$parsedDate) { $parsedDate = date('Y-m-d H:i:s'); }

$entry = [
    'id'         => wlGenerateId(),
    'client_id'  => (string)$clientId,
    'exercise'   => $exercise,
    'weight_kg'  => (float)$weight_kg,
    'sets'       => (int)$sets,
    'reps'       => (int)$reps,
    'rpe'        => $rpe !== null ? (float)$rpe : null,
    'notes'      => $notes,
    'week'       => (int)$week,
    'year'       => (int)$year,
    'date'       => $parsedDate,
];

// ── MySQL path ──────────────────────────────────────────────────────────────
try {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO weight_logs
            (id, client_id, exercise, weight_kg, `sets`, reps, rpe, notes, week_number, `year`, `date`)
        VALUES
            (:id, :client_id, :exercise, :weight_kg, :sets, :reps, :rpe, :notes, :week, :year, :date)
    ");
    $stmt->execute([
        ':id'        => $entry['id'],
        ':client_id' => $entry['client_id'],
        ':exercise'  => $entry['exercise'],
        ':weight_kg' => $entry['weight_kg'],
        ':sets'      => $entry['sets'],
        ':reps'      => $entry['reps'],
        ':rpe'       => $entry['rpe'],
        ':notes'     => $entry['notes'],
        ':week'      => $entry['week'],
        ':year'      => $entry['year'],
        ':date'      => $entry['date'],
    ]);
    // Also write to JSON as audit trail
    $all = wlReadJson();
    $all[] = $entry;
    wlWriteJson($all);
    respond(['message' => 'Registro guardado', 'entry' => $entry], 201);
} catch (Throwable $e) {
    error_log('[WellCore] weight_logs DB error: ' . $e->getMessage());
    respondError('Error guardando registro', 500);
}
