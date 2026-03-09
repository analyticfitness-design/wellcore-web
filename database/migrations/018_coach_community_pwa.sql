-- Migration 018: Coach Community posts + PWA white-label config
CREATE TABLE IF NOT EXISTS coach_community_posts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coach_id   INT UNSIGNED NOT NULL,
  content    TEXT NOT NULL,
  type       ENUM('post','tip','achievement') DEFAULT 'post',
  likes      INT UNSIGNED DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_feed (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coach_pwa_config (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coach_id   INT UNSIGNED NOT NULL UNIQUE,
  app_name   VARCHAR(60) NOT NULL DEFAULT 'Mi App Fitness',
  icon_url   VARCHAR(255),
  color      VARCHAR(7) DEFAULT '#E31E24',
  subdomain  VARCHAR(40),
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_coach (coach_id),
  INDEX idx_subdomain (subdomain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
