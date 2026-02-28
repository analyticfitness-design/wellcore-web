<?php
// WellCore — Email SMTP configuration
// Reads from api/.env via env() helper

require_once __DIR__ . '/env.php';

define('SMTP_HOST',      env('SMTP_HOST', 'mail.privateemail.com'));
define('SMTP_PORT',      (int) env('SMTP_PORT', '587'));
define('SMTP_USER',      env('SMTP_USER', 'info@wellcorefitness.com'));
define('SMTP_PASS',      env('SMTP_PASS', ''));
define('SMTP_FROM',      env('SMTP_FROM', 'info@wellcorefitness.com'));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'WellCore Fitness'));
define('SMTP_TIMEOUT',   30);
