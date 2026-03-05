<?php
/**
 * ============================================================
 * WELLCORE FITNESS — WOMPI CONFIGURACION CENTRALIZADA
 * ============================================================
 * Credenciales se leen desde api/.env
 * Documentacion: https://docs.wompi.co
 * Panel: https://comercios.wompi.co
 * ============================================================
 */

if (defined('WOMPI_CONFIG_LOADED')) return;
define('WOMPI_CONFIG_LOADED', true);

require_once __DIR__ . '/../config/env.php';

// -------------------------------------------------------
// CREDENCIALES DESDE .env
// -------------------------------------------------------
define('WOMPI_PUBLIC_KEY',        env('WOMPI_PUBLIC_KEY',        ''));
define('WOMPI_PRIVATE_KEY',       env('WOMPI_PRIVATE_KEY',       ''));
define('WOMPI_EVENTS_KEY',        env('WOMPI_EVENTS_KEY',        ''));
define('WOMPI_INTEGRITY_SECRET',  env('WOMPI_INTEGRITY_SECRET',  ''));
define('WOMPI_SANDBOX',           env('WOMPI_SANDBOX', 'true') === 'true');

// -------------------------------------------------------
// ENDPOINTS DE API WOMPI
// -------------------------------------------------------

/** URL API Sandbox */
define('WOMPI_API_URL_SANDBOX', 'https://sandbox.wompi.co/v1');

/** URL API Produccion */
define('WOMPI_API_URL_PROD', 'https://production.wompi.co/v1');

/** URL Widget/Checkout Sandbox */
define('WOMPI_WIDGET_URL_SANDBOX', 'https://checkout.wompi.co/widget.js');

/** URL Widget/Checkout Produccion */
define('WOMPI_WIDGET_URL_PROD', 'https://checkout.wompi.co/widget.js');

// -------------------------------------------------------
// URLS DEL SITIO
// -------------------------------------------------------
define('SITE_URL', 'https://wellcorefitness.com');

/** URL de redirect al cliente tras el pago */
define('WOMPI_REDIRECT_URL', SITE_URL . '/pago-exitoso.html');

/** URL de confirmacion (webhook server-to-server) */
define('WOMPI_WEBHOOK_URL', SITE_URL . '/api/wompi/webhook.php');

// -------------------------------------------------------
// PLANES WELLCORE — PRECIOS EN CENTAVOS COP
// -------------------------------------------------------
// Wompi trabaja en centavos: $399.000 COP = 39900000 centavos
define('WELLCORE_PLANS', [
    'esencial' => [
        'name'              => 'WellCore Esencial',
        'display'           => 'ESENCIAL',
        'amount_cop'        => 399000,
        'amount_in_cents'   => 39900000,
        'currency'          => 'COP',
        'description'       => 'WellCore Fitness - Plan Esencial ($399.000 COP/mes)',
    ],
    'metodo' => [
        'name'              => 'WellCore Metodo',
        'display'           => 'METODO',
        'amount_cop'        => 504000,
        'amount_in_cents'   => 50400000,
        'currency'          => 'COP',
        'description'       => 'WellCore Fitness - Plan Metodo ($504.000 COP/mes)',
    ],
    'elite' => [
        'name'              => 'WellCore Elite',
        'display'           => 'ELITE',
        'amount_cop'        => 630000,
        'amount_in_cents'   => 63000000,
        'currency'          => 'COP',
        'description'       => 'WellCore Fitness - Plan Elite ($630.000 COP/mes)',
    ],
    'rise' => [
        'name'              => 'WellCore RISE 30 Días',
        'display'           => 'RISE',
        'amount_cop'        => 99900,
        'amount_in_cents'   => 9990000,
        'currency'          => 'COP',
        'description'       => 'WellCore RISE - Reto 30 Días (~$25 USD)',
    ],
]);

// -------------------------------------------------------
// HELPERS
// -------------------------------------------------------

/**
 * Retorna la URL base de la API segun modo (sandbox/prod).
 */
function wompi_api_url(): string {
    return WOMPI_SANDBOX ? WOMPI_API_URL_SANDBOX : WOMPI_API_URL_PROD;
}

/**
 * Retorna la URL del widget JS.
 */
function wompi_widget_url(): string {
    return WOMPI_SANDBOX ? WOMPI_WIDGET_URL_SANDBOX : WOMPI_WIDGET_URL_PROD;
}

/**
 * Calcula el hash de integridad para el widget de Wompi.
 * Formula: SHA256(reference + amountInCents + currency + integritySecret)
 *
 * @param string $reference      Codigo de referencia de la orden
 * @param int    $amountInCents  Monto en centavos COP
 * @param string $currency       Moneda (COP)
 * @return string Hash SHA256 hexadecimal
 */
function wompi_integrity_hash(string $reference, int $amountInCents, string $currency = 'COP'): string {
    $raw = $reference . $amountInCents . $currency . WOMPI_INTEGRITY_SECRET;
    return hash('sha256', $raw);
}
