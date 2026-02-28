<?php
/**
 * WellCore Fitness — Admin AI Stats
 * GET /api/admin/ai-stats
 *
 * Devuelve agregados del panel IA:
 *   total, queued, pending, completed, approved, failed,
 *   cost_month (USD estimado), tickets_with_draft
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logger.php';
logStart();

requireMethod('GET');
$admin = authenticateAdmin();
$db    = getDB();

// ── Conteos de ai_generations ─────────────────────────────────
$counts = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'queued')    AS queued,
        SUM(status = 'pending')   AS pending,
        SUM(status = 'completed') AS completed,
        SUM(status = 'approved')  AS approved,
        SUM(status = 'failed')    AS failed,
        SUM(status IN ('queued','pending','completed','approved','failed','rejected')) AS all_types
    FROM ai_generations
")->fetch(PDO::FETCH_ASSOC);

// ── Costo mes actual ─────────────────────────────────────────
$costRow = $db->query("
    SELECT
        COALESCE(SUM(prompt_tokens), 0)     AS inp,
        COALESCE(SUM(completion_tokens), 0) AS out
    FROM ai_generations
    WHERE MONTH(created_at) = MONTH(CURDATE())
      AND YEAR(created_at)  = YEAR(CURDATE())
")->fetch(PDO::FETCH_ASSOC);

// Precios claude-opus-4-6: $15/M input, $75/M output
$costMonth = round(
    ($costRow['inp'] / 1_000_000 * 15.0) +
    ($costRow['out'] / 1_000_000 * 75.0),
    4
);

// ── Tickets con borrador IA listo ─────────────────────────────
$ticketsDraft = 0;
try {
    $ticketsDraft = (int) $db->query("
        SELECT COUNT(*) FROM tickets WHERE ai_status = 'ready'
    ")->fetchColumn();
} catch (\Throwable $ignore) {}

// ── Generaciones mes actual por tipo ─────────────────────────
$byType = $db->query("
    SELECT type, COUNT(*) AS cnt
    FROM ai_generations
    WHERE MONTH(created_at) = MONTH(CURDATE())
      AND YEAR(created_at)  = YEAR(CURDATE())
    GROUP BY type
")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Últimas 8 generaciones con nombre de cliente ─────────────
$recent = $db->query("
    SELECT g.id, g.client_id, c.name AS client_name, g.type, g.model,
           g.prompt_tokens, g.completion_tokens, g.status, g.created_at
    FROM ai_generations g
    LEFT JOIN clients c ON c.id = g.client_id
    ORDER BY g.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// ── Tokens mes actual ────────────────────────────────────────
$tokensMonth = (int) ($costRow['inp'] ?? 0) + (int) ($costRow['out'] ?? 0);

respond([
    'stats' => [
        'total'          => (int) ($counts['total']     ?? 0),
        'queued'         => (int) ($counts['queued']    ?? 0),
        'pending'        => (int) ($counts['pending']   ?? 0),
        'completed'      => (int) ($counts['completed'] ?? 0),
        'approved'       => (int) ($counts['approved']  ?? 0),
        'failed'         => (int) ($counts['failed']    ?? 0),
        'cost_month_usd' => $costMonth,
        'tokens_month'   => $tokensMonth,
        'tickets_draft'  => $ticketsDraft,
        'by_type_month'  => $byType,
    ],
    'recent' => $recent,
]);
