-- WellCore v8 — Migration 009: Tabla de referidos
-- Run: php database/run_migration.php 009_referrals.sql

CREATE TABLE IF NOT EXISTS referrals (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referrer_id     INT UNSIGNED NOT NULL,
  referred_email  VARCHAR(255) NOT NULL,
  referred_id     INT UNSIGNED DEFAULT NULL,
  status          ENUM('pending','registered','converted') DEFAULT 'pending',
  reward_granted  TINYINT(1) DEFAULT 0,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  converted_at    DATETIME DEFAULT NULL,
  INDEX idx_referrer (referrer_id),
  INDEX idx_email (referred_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
