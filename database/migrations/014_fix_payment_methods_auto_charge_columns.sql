-- Migration 014: Fix column name mismatches in payment_methods and auto_charge_log
-- Aligns DB schema with what M16 PHP code actually references.
--
-- payment_methods (migration 011 created): brand, last4, active
-- PHP (webhook.php, charge-token.php, auto-renewal.php) uses: card_brand, last_four, is_active, card_holder
--
-- auto_charge_log (migration 011 created): wompi_ref, wompi_txn_id (no payment_method_id, no error_message)
-- PHP (auto-renewal.php, charge-token.php) writes: payment_method_id, wompi_transaction_id, error_message
--
-- Uses stored procedures for idempotency (compatible with MySQL 8.0+ / 9.x
-- which lack IF NOT EXISTS on ALTER TABLE column operations).

-- ==============================================================
-- TABLE: payment_methods
-- ==============================================================

-- 1. Rename brand -> card_brand (if brand exists and card_brand does not)
DROP PROCEDURE IF EXISTS _mig014_pm_brand;
DELIMITER $$
CREATE PROCEDURE _mig014_pm_brand()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_methods'
          AND COLUMN_NAME  = 'brand'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_methods'
          AND COLUMN_NAME  = 'card_brand'
    ) THEN
        ALTER TABLE payment_methods
            CHANGE COLUMN brand card_brand VARCHAR(30) DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig014_pm_brand();
DROP PROCEDURE IF EXISTS _mig014_pm_brand;

-- 2. Rename last4 -> last_four (if last4 exists and last_four does not)
DROP PROCEDURE IF EXISTS _mig014_pm_last4;
DELIMITER $$
CREATE PROCEDURE _mig014_pm_last4()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_methods'
          AND COLUMN_NAME  = 'last4'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_methods'
          AND COLUMN_NAME  = 'last_four'
    ) THEN
        ALTER TABLE payment_methods
            CHANGE COLUMN last4 last_four CHAR(4) DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig014_pm_last4();
DROP PROCEDURE IF EXISTS _mig014_pm_last4;

-- 3. Rename active -> is_active (if active exists and is_active does not)
DROP PROCEDURE IF EXISTS _mig014_pm_active;
DELIMITER $$
CREATE PROCEDURE _mig014_pm_active()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_methods'
          AND COLUMN_NAME  = 'active'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_methods'
          AND COLUMN_NAME  = 'is_active'
    ) THEN
        ALTER TABLE payment_methods
            CHANGE COLUMN active is_active TINYINT(1) DEFAULT 1;
    END IF;
END$$
DELIMITER ;
CALL _mig014_pm_active();
DROP PROCEDURE IF EXISTS _mig014_pm_active;

-- 4. Add card_holder (if not exists)
DROP PROCEDURE IF EXISTS _mig014_pm_card_holder;
DELIMITER $$
CREATE PROCEDURE _mig014_pm_card_holder()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'payment_methods'
          AND COLUMN_NAME  = 'card_holder'
    ) THEN
        ALTER TABLE payment_methods
            ADD COLUMN card_holder VARCHAR(100) DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig014_pm_card_holder();
DROP PROCEDURE IF EXISTS _mig014_pm_card_holder;

-- ==============================================================
-- TABLE: auto_charge_log
-- ==============================================================

-- 5. Rename wompi_txn_id -> wompi_transaction_id (if wompi_txn_id exists and wompi_transaction_id does not)
DROP PROCEDURE IF EXISTS _mig014_acl_txn_id;
DELIMITER $$
CREATE PROCEDURE _mig014_acl_txn_id()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'auto_charge_log'
          AND COLUMN_NAME  = 'wompi_txn_id'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'auto_charge_log'
          AND COLUMN_NAME  = 'wompi_transaction_id'
    ) THEN
        ALTER TABLE auto_charge_log
            CHANGE COLUMN wompi_txn_id wompi_transaction_id VARCHAR(100) DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig014_acl_txn_id();
DROP PROCEDURE IF EXISTS _mig014_acl_txn_id;

-- 6. Rename wompi_ref -> reference (if wompi_ref exists and reference does not)
--    The PHP does not write wompi_ref; keeping column as 'reference' avoids dead columns.
DROP PROCEDURE IF EXISTS _mig014_acl_ref;
DELIMITER $$
CREATE PROCEDURE _mig014_acl_ref()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'auto_charge_log'
          AND COLUMN_NAME  = 'wompi_ref'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'auto_charge_log'
          AND COLUMN_NAME  = 'reference'
    ) THEN
        ALTER TABLE auto_charge_log
            CHANGE COLUMN wompi_ref reference VARCHAR(100) DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig014_acl_ref();
DROP PROCEDURE IF EXISTS _mig014_acl_ref;

-- 7. Add payment_method_id (if not exists)
DROP PROCEDURE IF EXISTS _mig014_acl_pm_id;
DELIMITER $$
CREATE PROCEDURE _mig014_acl_pm_id()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'auto_charge_log'
          AND COLUMN_NAME  = 'payment_method_id'
    ) THEN
        ALTER TABLE auto_charge_log
            ADD COLUMN payment_method_id INT DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig014_acl_pm_id();
DROP PROCEDURE IF EXISTS _mig014_acl_pm_id;

-- 8. Add error_message (if not exists)
DROP PROCEDURE IF EXISTS _mig014_acl_error;
DELIMITER $$
CREATE PROCEDURE _mig014_acl_error()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'auto_charge_log'
          AND COLUMN_NAME  = 'error_message'
    ) THEN
        ALTER TABLE auto_charge_log
            ADD COLUMN error_message TEXT DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig014_acl_error();
DROP PROCEDURE IF EXISTS _mig014_acl_error;
