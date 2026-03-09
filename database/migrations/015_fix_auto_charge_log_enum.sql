-- Migration 015: Add 'success' and 'failed' to auto_charge_log.status ENUM
-- Los valores 'success' y 'failed' eran escritos por el código pero no estaban en el ENUM
-- MySQL silently coerce to '' en modo no-strict, o error en strict mode

DROP PROCEDURE IF EXISTS _mig015_fix_enum;
DELIMITER $$
CREATE PROCEDURE _mig015_fix_enum()
BEGIN
    -- Verificar si 'success' ya está en el ENUM (MySQL 8+)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'auto_charge_log'
          AND COLUMN_NAME = 'status'
          AND COLUMN_TYPE LIKE '%success%'
    ) THEN
        ALTER TABLE auto_charge_log
            MODIFY COLUMN status
            ENUM('pending','success','failed','approved','declined','error')
            DEFAULT 'pending';
    END IF;
END$$
DELIMITER ;
CALL _mig015_fix_enum();
DROP PROCEDURE IF EXISTS _mig015_fix_enum;
