-- Migration 012: Add UNIQUE constraint to auto_message_log
-- Prevents duplicate trigger emails at the database level.
-- date_sent column is a plain DATE (populated on insert via CURDATE())
-- so no generated-column syntax needed and no MySQL version edge cases.
--
-- Uses stored procedures to safely skip if column/key already exists
-- (compatible with MySQL 8.0+ and 9.x which lack IF NOT EXISTS on ALTER TABLE).

DROP PROCEDURE IF EXISTS _mig012_add_date_sent;
DELIMITER $$
CREATE PROCEDURE _mig012_add_date_sent()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'auto_message_log'
          AND COLUMN_NAME  = 'date_sent'
    ) THEN
        ALTER TABLE auto_message_log
            ADD COLUMN date_sent DATE DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig012_add_date_sent();
DROP PROCEDURE IF EXISTS _mig012_add_date_sent;

-- Backfill existing rows
UPDATE auto_message_log SET date_sent = DATE(sent_at) WHERE date_sent IS NULL;

DROP PROCEDURE IF EXISTS _mig012_add_unique;
DELIMITER $$
CREATE PROCEDURE _mig012_add_unique()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'auto_message_log'
          AND INDEX_NAME   = 'uq_client_trigger_day'
    ) THEN
        ALTER TABLE auto_message_log
            ADD UNIQUE KEY uq_client_trigger_day (client_id, trigger_type, date_sent);
    END IF;
END$$
DELIMITER ;
CALL _mig012_add_unique();
DROP PROCEDURE IF EXISTS _mig012_add_unique;
