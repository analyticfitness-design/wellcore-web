-- M02: RPE en check-in
ALTER TABLE checkins ADD COLUMN IF NOT EXISTS rpe TINYINT UNSIGNED DEFAULT NULL COMMENT '1-10 RPE scale';

-- M06: Habit tracking
CREATE TABLE IF NOT EXISTS habit_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id   INT UNSIGNED NOT NULL,
  log_date    DATE NOT NULL,
  habit_type  ENUM('agua','sueno','nutricion','estres') NOT NULL,
  value       TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=completado',
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_client_date_habit (client_id, log_date, habit_type),
  INDEX idx_client_date (client_id, log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
