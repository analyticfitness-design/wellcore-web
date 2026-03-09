-- Migration 013: Add body-composition columns to biometric_logs
-- La tabla fue creada con columnas de wearable (steps/heart_rate/calories/source)
-- Este migration agrega las columnas de composición corporal manual que usa la API.
--
-- Uses stored procedures to safely skip if column already exists
-- (compatible with MySQL 8.0+ and 9.x which lack IF NOT EXISTS on ALTER TABLE).

DROP PROCEDURE IF EXISTS _mig013_add_weight_kg;
DELIMITER $$
CREATE PROCEDURE _mig013_add_weight_kg()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'biometric_logs'
          AND COLUMN_NAME  = 'weight_kg'
    ) THEN
        ALTER TABLE biometric_logs ADD COLUMN weight_kg DECIMAL(5,2) DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig013_add_weight_kg();
DROP PROCEDURE IF EXISTS _mig013_add_weight_kg;

DROP PROCEDURE IF EXISTS _mig013_add_body_fat_pct;
DELIMITER $$
CREATE PROCEDURE _mig013_add_body_fat_pct()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'biometric_logs'
          AND COLUMN_NAME  = 'body_fat_pct'
    ) THEN
        ALTER TABLE biometric_logs ADD COLUMN body_fat_pct DECIMAL(4,1) DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig013_add_body_fat_pct();
DROP PROCEDURE IF EXISTS _mig013_add_body_fat_pct;

DROP PROCEDURE IF EXISTS _mig013_add_waist_cm;
DELIMITER $$
CREATE PROCEDURE _mig013_add_waist_cm()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'biometric_logs'
          AND COLUMN_NAME  = 'waist_cm'
    ) THEN
        ALTER TABLE biometric_logs ADD COLUMN waist_cm DECIMAL(5,1) DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig013_add_waist_cm();
DROP PROCEDURE IF EXISTS _mig013_add_waist_cm;

DROP PROCEDURE IF EXISTS _mig013_add_hip_cm;
DELIMITER $$
CREATE PROCEDURE _mig013_add_hip_cm()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'biometric_logs'
          AND COLUMN_NAME  = 'hip_cm'
    ) THEN
        ALTER TABLE biometric_logs ADD COLUMN hip_cm DECIMAL(5,1) DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig013_add_hip_cm();
DROP PROCEDURE IF EXISTS _mig013_add_hip_cm;

DROP PROCEDURE IF EXISTS _mig013_add_energy_level;
DELIMITER $$
CREATE PROCEDURE _mig013_add_energy_level()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'biometric_logs'
          AND COLUMN_NAME  = 'energy_level'
    ) THEN
        ALTER TABLE biometric_logs ADD COLUMN energy_level TINYINT DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig013_add_energy_level();
DROP PROCEDURE IF EXISTS _mig013_add_energy_level;

DROP PROCEDURE IF EXISTS _mig013_add_notes;
DELIMITER $$
CREATE PROCEDURE _mig013_add_notes()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'biometric_logs'
          AND COLUMN_NAME  = 'notes'
    ) THEN
        ALTER TABLE biometric_logs ADD COLUMN notes TEXT DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig013_add_notes();
DROP PROCEDURE IF EXISTS _mig013_add_notes;

DROP PROCEDURE IF EXISTS _mig013_add_updated_at;
DELIMITER $$
CREATE PROCEDURE _mig013_add_updated_at()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'biometric_logs'
          AND COLUMN_NAME  = 'updated_at'
    ) THEN
        ALTER TABLE biometric_logs
            ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
    END IF;
END$$
DELIMITER ;
CALL _mig013_add_updated_at();
DROP PROCEDURE IF EXISTS _mig013_add_updated_at;
