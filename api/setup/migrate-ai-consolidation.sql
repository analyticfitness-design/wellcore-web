-- WellCore AI Consolidation Migration
-- Run once on production to fix schema issues
-- Safe to re-run (uses IF NOT EXISTS / IGNORE patterns)

-- 1. Add 'rise' to assigned_plans.plan_type ENUM if missing
ALTER TABLE assigned_plans
  MODIFY COLUMN plan_type ENUM('entrenamiento','nutricion','habitos','rise') NOT NULL;

-- 2. Add ai_generation_id column if missing
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assigned_plans' AND COLUMN_NAME = 'ai_generation_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE assigned_plans ADD COLUMN ai_generation_id INT DEFAULT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Change ai_generations.type from ENUM to VARCHAR(30) to avoid future breaks
ALTER TABLE ai_generations
  MODIFY COLUMN type VARCHAR(30) NOT NULL DEFAULT 'entrenamiento';

-- 4. Add 'generating' to ai_generations.status ENUM if missing
ALTER TABLE ai_generations
  MODIFY COLUMN status ENUM('queued','pending','generating','completed','failed','approved','rejected') DEFAULT 'pending';

-- 5. Add 'rise' to clients.plan ENUM if missing
ALTER TABLE clients
  MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise') DEFAULT 'esencial';
