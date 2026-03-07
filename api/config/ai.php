<?php
/**
 * WellCore Fitness — AI Configuration
 * Reads from api/.env via env() helper
 */

require_once __DIR__ . '/env.php';

define('CLAUDE_API_KEY',    env('CLAUDE_API_KEY', ''));
define('CLAUDE_MODEL',      env('CLAUDE_MODEL', 'claude-sonnet-4-6'));
define('CLAUDE_MAX_TOKENS', 32000);
define('CLAUDE_BASE_URL',   'https://api.anthropic.com');
define('CLAUDE_API_VERSION','2023-06-01');

define('AI_ENABLED',        true);
define('AI_RATE_LIMIT_PER_HOUR', 30);

// Sonnet 4.6: $3.00 input / $15.00 output por millon de tokens
define('AI_COST_INPUT_PER_MILLION',  3.00);
define('AI_COST_OUTPUT_PER_MILLION', 15.00);

define('AI_ROUTER_URL',     env('AI_ROUTER_URL', 'http://localhost:8000'));
define('AI_ROUTER_TIMEOUT', (int) env('AI_ROUTER_TIMEOUT', '60'));

define('DIFY_URL',     env('DIFY_URL', 'http://localhost:3000'));
define('DIFY_API_KEY', env('DIFY_API_KEY', ''));

define('DTAI_URL',     env('DTAI_URL', 'http://localhost:8001'));
