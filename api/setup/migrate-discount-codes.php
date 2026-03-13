<?php
/**
 * WellCore Fitness — Migración: Códigos de Descuento
 * ====================================================
 * Tabla para códigos de descuento de un solo uso o múltiples usos.
 * Uso: php migrate-discount-codes.php
 */

require_once __DIR__ . '/../config/database.php';

$db = getDB();

$queries = [
    // Tabla principal de códigos
    "CREATE TABLE IF NOT EXISTS discount_codes (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code            VARCHAR(50) NOT NULL UNIQUE,
        description     VARCHAR(255) DEFAULT '',
        discount_type   ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
        discount_value  DECIMAL(10,2) NOT NULL,
        applies_to      VARCHAR(255) DEFAULT NULL COMMENT 'null=todos, o CSV de planes: esencial,metodo,elite',
        max_uses        INT UNSIGNED DEFAULT 1 COMMENT '0=ilimitado',
        times_used      INT UNSIGNED DEFAULT 0,
        min_amount_cop  INT UNSIGNED DEFAULT 0,
        starts_at       DATETIME DEFAULT NULL,
        expires_at      DATETIME DEFAULT NULL,
        is_active       BOOLEAN DEFAULT TRUE,
        created_by      INT UNSIGNED DEFAULT NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_code (code),
        INDEX idx_active (is_active, expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Log de uso de códigos
    "CREATE TABLE IF NOT EXISTS discount_code_usage (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        discount_code_id INT UNSIGNED NOT NULL,
        buyer_email     VARCHAR(255) NOT NULL,
        reference_code  VARCHAR(100) NOT NULL,
        plan            VARCHAR(50) NOT NULL,
        original_amount INT UNSIGNED NOT NULL COMMENT 'Monto original en centavos COP',
        discount_amount INT UNSIGNED NOT NULL COMMENT 'Descuento aplicado en centavos COP',
        final_amount    INT UNSIGNED NOT NULL COMMENT 'Monto final en centavos COP',
        payment_status  ENUM('pending','approved','declined','voided','error') DEFAULT 'pending',
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (discount_code_id) REFERENCES discount_codes(id) ON DELETE CASCADE,
        INDEX idx_email (buyer_email),
        INDEX idx_reference (reference_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

$ok = 0;
$skip = 0;
foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        $ok++;
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
        echo "OK: {$m[1]}\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'already exists')) {
            $skip++;
            preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
            echo "SKIP: {$m[1]} (ya existe)\n";
        } else {
            echo "ERROR: {$e->getMessage()}\n";
        }
    }
}

echo "\nMigracion completada: $ok creadas, $skip existentes\n";
