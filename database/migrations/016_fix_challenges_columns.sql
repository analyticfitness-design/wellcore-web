-- Migration 016: Fix column name mismatches in challenges and challenge_participants
-- Aligns DB schema with what M26 PHP code actually references.
--
-- challenges (migration 011 created): name, active, target_value, ENUM('checkins','steps','workouts','habits','custom')
-- PHP (admin-manage.php, leaderboard.php, progress.php) uses: title, is_active, goal_value,
--     ENUM('steps','checkins','weight_loss','streak')
--
-- challenge_participants (migration 011 created): current_value, completed (TINYINT), completed_at
-- PHP (progress.php, leaderboard.php) uses: progress, rank, completed_at
--
-- Uses stored procedures for idempotency (compatible with MySQL 8.0+ / 9.x
-- which lack IF NOT EXISTS on ALTER TABLE column operations).

-- ==============================================================
-- TABLE: challenges
-- ==============================================================

-- 1. Rename name -> title (if name exists and title does not)
DROP PROCEDURE IF EXISTS _mig016_ch_name;
DELIMITER $$
CREATE PROCEDURE _mig016_ch_name()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'challenges'
          AND COLUMN_NAME  = 'name'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'challenges'
          AND COLUMN_NAME  = 'title'
    ) THEN
        ALTER TABLE challenges
            CHANGE COLUMN name title VARCHAR(160) NOT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig016_ch_name();
DROP PROCEDURE IF EXISTS _mig016_ch_name;

-- 2. Rename active -> is_active (if active exists and is_active does not)
DROP PROCEDURE IF EXISTS _mig016_ch_active;
DELIMITER $$
CREATE PROCEDURE _mig016_ch_active()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'challenges'
          AND COLUMN_NAME  = 'active'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'challenges'
          AND COLUMN_NAME  = 'is_active'
    ) THEN
        ALTER TABLE challenges
            CHANGE COLUMN active is_active TINYINT(1) DEFAULT 1;
    END IF;
END$$
DELIMITER ;
CALL _mig016_ch_active();
DROP PROCEDURE IF EXISTS _mig016_ch_active;

-- 3. Rename target_value -> goal_value (if target_value exists and goal_value does not)
DROP PROCEDURE IF EXISTS _mig016_ch_target;
DELIMITER $$
CREATE PROCEDURE _mig016_ch_target()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'challenges'
          AND COLUMN_NAME  = 'target_value'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'challenges'
          AND COLUMN_NAME  = 'goal_value'
    ) THEN
        ALTER TABLE challenges
            CHANGE COLUMN target_value goal_value INT UNSIGNED NOT NULL DEFAULT 1;
    END IF;
END$$
DELIMITER ;
CALL _mig016_ch_target();
DROP PROCEDURE IF EXISTS _mig016_ch_target;

-- 4. Fix challenge_type ENUM: add weight_loss and streak, remove workouts/habits/custom
--    Only run if the current ENUM does NOT already include 'weight_loss'
DROP PROCEDURE IF EXISTS _mig016_ch_type_enum;
DELIMITER $$
CREATE PROCEDURE _mig016_ch_type_enum()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA  = DATABASE()
          AND TABLE_NAME    = 'challenges'
          AND COLUMN_NAME   = 'challenge_type'
          AND COLUMN_TYPE LIKE "%'weight_loss'%"
    ) THEN
        ALTER TABLE challenges
            MODIFY COLUMN challenge_type ENUM('steps','checkins','weight_loss','streak') NOT NULL DEFAULT 'checkins';
    END IF;
END$$
DELIMITER ;
CALL _mig016_ch_type_enum();
DROP PROCEDURE IF EXISTS _mig016_ch_type_enum;

-- ==============================================================
-- TABLE: challenge_participants
-- ==============================================================

-- 5. Rename current_value -> progress (if current_value exists and progress does not)
DROP PROCEDURE IF EXISTS _mig016_cp_current_value;
DELIMITER $$
CREATE PROCEDURE _mig016_cp_current_value()
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'challenge_participants'
          AND COLUMN_NAME  = 'current_value'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'challenge_participants'
          AND COLUMN_NAME  = 'progress'
    ) THEN
        ALTER TABLE challenge_participants
            CHANGE COLUMN current_value progress DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0;
    END IF;
END$$
DELIMITER ;
CALL _mig016_cp_current_value();
DROP PROCEDURE IF EXISTS _mig016_cp_current_value;

-- 6. Add rank column (if not exists)
DROP PROCEDURE IF EXISTS _mig016_cp_rank;
DELIMITER $$
CREATE PROCEDURE _mig016_cp_rank()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'challenge_participants'
          AND COLUMN_NAME  = 'rank'
    ) THEN
        ALTER TABLE challenge_participants
            ADD COLUMN rank INT DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig016_cp_rank();
DROP PROCEDURE IF EXISTS _mig016_cp_rank;

-- 7. Add completed_at (if completed TINYINT exists but completed_at does not)
--    Note: migration 011 already added completed_at, but guard with idempotent check anyway.
DROP PROCEDURE IF EXISTS _mig016_cp_completed_at;
DELIMITER $$
CREATE PROCEDURE _mig016_cp_completed_at()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'challenge_participants'
          AND COLUMN_NAME  = 'completed_at'
    ) THEN
        ALTER TABLE challenge_participants
            ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL;
    END IF;
END$$
DELIMITER ;
CALL _mig016_cp_completed_at();
DROP PROCEDURE IF EXISTS _mig016_cp_completed_at;
