<?php
/**
 * WellCore Fitness — AI Configuration
 * Reads from api/.env via env() helper
 */

require_once __DIR__ . '/env.php';

define('CLAUDE_API_KEY',    env('CLAUDE_API_KEY', ''));
define('CLAUDE_MODEL',      env('CLAUDE_MODEL', 'claude-haiku-4-5-20251001'));
define('CLAUDE_MAX_TOKENS', 4096);
define('CLAUDE_BASE_URL',   'https://api.anthropic.com');
define('CLAUDE_API_VERSION','2023-06-01');

define('AI_ENABLED',        true);
define('AI_RATE_LIMIT_PER_HOUR', 10);

// Haiku 4.5: $0.80 input / $4.00 output por millon de tokens (vs Opus: $15/$75)
define('AI_COST_INPUT_PER_MILLION',  0.80);
define('AI_COST_OUTPUT_PER_MILLION', 4.00);

define('AI_ROUTER_URL',     env('AI_ROUTER_URL', 'http://localhost:8000'));
define('AI_ROUTER_TIMEOUT', (int) env('AI_ROUTER_TIMEOUT', '60'));

define('DIFY_URL',     env('DIFY_URL', 'http://localhost:3000'));
define('DIFY_API_KEY', env('DIFY_API_KEY', ''));

define('DTAI_URL',     env('DTAI_URL', 'http://localhost:8001'));
