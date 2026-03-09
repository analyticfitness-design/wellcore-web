-- WellCore v8 — Migration 008: Tabla de notificaciones
-- Run: php database/run_migration.php 008_notifications.sql

CREATE TABLE IF NOT EXISTS notifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_type   ENUM('client','admin') NOT NULL DEFAULT 'client',
  user_id     INT UNSIGNED NOT NULL,
  type        VARCHAR(60) NOT NULL,
  title       VARCHAR(160) NOT NULL,
  body        TEXT,
  link        VARCHAR(255),
  read_at     DATETIME DEFAULT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_type, user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
