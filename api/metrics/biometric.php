<?php
/**
 * M12 — Biometric Daily Log API
 * GET  /api/metrics/biometric.php?date=YYYY-MM-DD[&history=true]
 * POST /api/metrics/biometric.php  (JSON body)
 */

require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../config/database.php';

$client = authenticateClient();
$clientId = (int) $client['id'];

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $db = getDB();

    // history=true → return last 7 records for sparkline
    if (!empty($_GET['history'])) {
        $stmt = $db->prepare(
            "SELECT log_date, weight_kg, body_fat_pct, energy_level
             FROM biometric_logs
             WHERE client_id = ?
             ORDER BY log_date DESC
             LIMIT 7"
        );
        $stmt->execute([$clientId]);
        $rows = $stmt->fetchAll();
        // Return in ascending date order for charts
        $rows = array_reverse($rows);
        respond(['data' => $rows]);
    }

    // Single day: default to today
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        respondError('Invalid date format. Use YYYY-MM-DD', 400);
    }

    $stmt = $db->prepare(
        "SELECT id, client_id, log_date, weight_kg, body_fat_pct,
                waist_cm, hip_cm, sleep_hours, energy_level, notes,
                created_at, updated_at
         FROM biometric_logs
         WHERE client_id = ? AND log_date = ?"
    );
    $stmt->execute([$clientId, $date]);
    $row = $stmt->fetch();

    respond(['data' => $row ?: null]);
}

// ── POST ─────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body = getJsonBody();

    // Date
    $date = $body['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        respondError('Invalid date format. Use YYYY-MM-DD', 400);
    }

    // Helper: validate nullable numeric range
    $errors = [];

    $weight_kg     = isset($body['weight_kg'])     ? (float) $body['weight_kg']     : null;
    $body_fat_pct  = isset($body['body_fat_pct'])  ? (float) $body['body_fat_pct']  : null;
    $waist_cm      = isset($body['waist_cm'])       ? (float) $body['waist_cm']      : null;
    $hip_cm        = isset($body['hip_cm'])         ? (float) $body['hip_cm']        : null;
    $sleep_hours   = isset($body['sleep_hours'])    ? (float) $body['sleep_hours']   : null;
    $energy_level  = isset($body['energy_level'])   ? (int)   $body['energy_level']  : null;
    $notes         = isset($body['notes'])          ? trim((string) $body['notes'])  : null;

    // Validate non-null fields
    if ($weight_kg !== null && ($weight_kg < 30 || $weight_kg > 300)) {
        $errors[] = 'weight_kg must be between 30 and 300';
    }
    if ($body_fat_pct !== null && ($body_fat_pct < 1 || $body_fat_pct > 60)) {
        $errors[] = 'body_fat_pct must be between 1 and 60';
    }
    if ($waist_cm !== null && ($waist_cm < 40 || $waist_cm > 200)) {
        $errors[] = 'waist_cm must be between 40 and 200';
    }
    if ($hip_cm !== null && ($hip_cm < 40 || $hip_cm > 200)) {
        $errors[] = 'hip_cm must be between 40 and 200';
    }
    if ($sleep_hours !== null && ($sleep_hours < 0 || $sleep_hours > 24)) {
        $errors[] = 'sleep_hours must be between 0 and 24';
    }
    if ($energy_level !== null && ($energy_level < 1 || $energy_level > 5)) {
        $errors[] = 'energy_level must be an integer between 1 and 5';
    }

    if (!empty($errors)) {
        respondError(implode('; ', $errors), 422);
    }

    // Ensure at least one field is being saved
    $hasAny = ($weight_kg !== null || $body_fat_pct !== null || $waist_cm !== null
               || $hip_cm !== null || $sleep_hours !== null || $energy_level !== null
               || ($notes !== null && $notes !== ''));
    if (!$hasAny) {
        respondError('At least one metric field must be provided', 422);
    }

    $db = getDB();

    // INSERT … ON DUPLICATE KEY UPDATE using COALESCE to preserve existing values
    // when the incoming field is NULL (meaning "do not overwrite")
    $stmt = $db->prepare("
        INSERT INTO biometric_logs
            (client_id, log_date, weight_kg, body_fat_pct, waist_cm, hip_cm, sleep_hours, energy_level, notes)
        VALUES
            (:cid, :date, :wkg, :bfp, :wst, :hip, :slp, :nrg, :nts)
        ON DUPLICATE KEY UPDATE
            weight_kg    = COALESCE(:wkg2,  weight_kg),
            body_fat_pct = COALESCE(:bfp2,  body_fat_pct),
            waist_cm     = COALESCE(:wst2,  waist_cm),
            hip_cm       = COALESCE(:hip2,  hip_cm),
            sleep_hours  = COALESCE(:slp2,  sleep_hours),
            energy_level = COALESCE(:nrg2,  energy_level),
            notes        = COALESCE(:nts2,  notes)
    ");

    $stmt->execute([
        ':cid'  => $clientId,
        ':date' => $date,
        ':wkg'  => $weight_kg,
        ':bfp'  => $body_fat_pct,
        ':wst'  => $waist_cm,
        ':hip'  => $hip_cm,
        ':slp'  => $sleep_hours,
        ':nrg'  => $energy_level,
        ':nts'  => ($notes !== '' ? $notes : null),
        // Duplicate params for ON DUPLICATE KEY UPDATE
        ':wkg2' => $weight_kg,
        ':bfp2' => $body_fat_pct,
        ':wst2' => $waist_cm,
        ':hip2' => $hip_cm,
        ':slp2' => $sleep_hours,
        ':nrg2' => $energy_level,
        ':nts2' => ($notes !== '' ? $notes : null),
    ]);

    // Fetch and return the saved record
    $sel = $db->prepare(
        "SELECT id, client_id, log_date, weight_kg, body_fat_pct,
                waist_cm, hip_cm, sleep_hours, energy_level, notes,
                created_at, updated_at
         FROM biometric_logs
         WHERE client_id = ? AND log_date = ?"
    );
    $sel->execute([$clientId, $date]);
    $saved = $sel->fetch();

    respond(['success' => true, 'data' => $saved], 200);
}

// ── Other methods ─────────────────────────────────────────────────────────────
respondError('Method not allowed', 405);
