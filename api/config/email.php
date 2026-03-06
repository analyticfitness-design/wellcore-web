<?php
// WellCore — Mailjet API configuration
// Reads from api/.env via env() helper, falls back to hardcoded defaults

require_once __DIR__ . '/env.php';

define('MAILJET_API_KEY',    env('MAILJET_API_KEY',    'c81bdac1248c50e373b811a663cb1f1a'));
define('MAILJET_SECRET_KEY', env('MAILJET_SECRET_KEY', 'b506208ce8ab895f36d0795287158a46'));
define('MAILJET_ENDPOINT',   'https://api.mailjet.com/v3.1/send');
define('MAIL_FROM_EMAIL',    env('MAIL_FROM_EMAIL', 'info@wellcorefitness.com'));
define('MAIL_FROM_NAME',     env('MAIL_FROM_NAME',  'WellCore Fitness'));
