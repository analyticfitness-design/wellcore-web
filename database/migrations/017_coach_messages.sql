-- Migration 017: Coach Messages (mensajería coach ↔ cliente)
CREATE TABLE IF NOT EXISTS coach_messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coach_id   INT UNSIGNED NOT NULL,
  client_id  INT UNSIGNED NOT NULL,
  message    TEXT NOT NULL,
  direction  ENUM('coach_to_client','client_to_coach') NOT NULL,
  read_at    DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_thread (coach_id, client_id, created_at),
  INDEX idx_unread (coach_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
