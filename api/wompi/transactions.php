<?php
/**
 * ============================================================
 * WELLCORE FITNESS — TRANSACTIONS LOG (WOMPI)
 * ============================================================
 * Gestiona el archivo JSON de transacciones locales.
 *
 * ESTRUCTURA de cada transaccion:
 * {
 *   "id":                   string  (UUID v4),
 *   "reference_code":       string  (WC-plan-timestamp),
 *   "plan":                 string  (esencial|metodo|elite),
 *   "amount_in_cents":      int,
 *   "amount_cop":           float,
 *   "currency":             string  (COP),
 *   "buyer_name":           string,
 *   "buyer_email":          string,
 *   "buyer_phone":          string,
 *   "status":               string  (pending|approved|declined|voided|error),
 *   "wompi_transaction_id": string,
 *   "wompi_payment_method": string,
 *   "date_created":         string  (ISO 8601),
 *   "date_updated":         string  (ISO 8601)
 * }
 * ============================================================
 */

// Usar /tmp/ para archivos transitorios (siempre writable en contenedores Docker)
define('TRANSACTIONS_FILE', sys_get_temp_dir() . '/wc_transactions.json');

function transactions_read_all(): array {
    if (!file_exists(TRANSACTIONS_FILE)) return [];
    $raw = file_get_contents(TRANSACTIONS_FILE);
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function transactions_append(array $transaction): bool {
    $dir = dirname(TRANSACTIONS_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $all   = transactions_read_all();
    $all[] = $transaction;
    return @file_put_contents(
        TRANSACTIONS_FILE,
        json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    ) !== false;
}

function transactions_find_by_reference(string $referenceCode): ?array {
    foreach (transactions_read_all() as $tx) {
        if (($tx['reference_code'] ?? '') === $referenceCode) return $tx;
    }
    return null;
}

function transactions_find_by_wompi_id(string $wompiId): ?array {
    foreach (transactions_read_all() as $tx) {
        if (($tx['wompi_transaction_id'] ?? '') === $wompiId) return $tx;
    }
    return null;
}

function transactions_update(string $referenceCode, array $updates): bool {
    $dir = dirname(TRANSACTIONS_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $all     = transactions_read_all();
    $updated = false;

    foreach ($all as &$tx) {
        if (($tx['reference_code'] ?? '') === $referenceCode) {
            foreach ($updates as $k => $v) $tx[$k] = $v;
            $tx['date_updated'] = date('c');
            $updated = true;
            break;
        }
    }
    unset($tx);

    if (!$updated) return false;

    return @file_put_contents(
        TRANSACTIONS_FILE,
        json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    ) !== false;
}

function generate_uuid(): string {
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
