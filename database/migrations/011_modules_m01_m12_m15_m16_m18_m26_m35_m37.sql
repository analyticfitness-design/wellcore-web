-- M01: evitar duplicados de triggers enviados por dia
CREATE TABLE IF NOT EXISTS auto_message_log (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id    INT UNSIGNED NOT NULL,
  trigger_type VARCHAR(60)  NOT NULL,
  channel      ENUM('email','notification') DEFAULT 'notification',
  sent_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- M12: datos biometricos/wearable por dia
CREATE TABLE IF NOT EXISTS biometric_logs (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id    INT UNSIGNED NOT NULL,
  log_date     DATE NOT NULL,
  steps        INT UNSIGNED DEFAULT NULL,
  sleep_hours  DECIMAL(4,2) DEFAULT NULL,
  heart_rate   SMALLINT UNSIGNED DEFAULT NULL,
  calories     SMALLINT UNSIGNED DEFAULT NULL,
  source       VARCHAR(40) DEFAULT 'manual',
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_client_date (client_id, log_date),
  INDEX idx_client_date (client_id, log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- M15: plantillas de programas por coach
CREATE TABLE IF NOT EXISTS plan_templates (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coach_id     INT UNSIGNED NOT NULL,
  name         VARCHAR(160) NOT NULL,
  plan_type    ENUM('entrenamiento','nutricion','habitos','suplementacion','ciclo') NOT NULL,
  methodology  VARCHAR(255) DEFAULT NULL,
  description  TEXT DEFAULT NULL,
  content_json LONGTEXT NOT NULL,
  ai_generated TINYINT(1) DEFAULT 0,
  is_public    TINYINT(1) DEFAULT 0,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_coach (coach_id),
  INDEX idx_type (plan_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- M16: tokens de tarjeta tokenizados por Wompi
CREATE TABLE IF NOT EXISTS payment_methods (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id    INT UNSIGNED NOT NULL,
  token_id     VARCHAR(255) NOT NULL,
  brand        VARCHAR(30)  DEFAULT NULL,
  last4        CHAR(4)      DEFAULT NULL,
  exp_month    TINYINT UNSIGNED DEFAULT NULL,
  exp_year     SMALLINT UNSIGNED DEFAULT NULL,
  active       TINYINT(1) DEFAULT 1,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_token (token_id),
  INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- M16: log de intentos de cobro automatico
CREATE TABLE IF NOT EXISTS auto_charge_log (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id    INT UNSIGNED NOT NULL,
  amount_cents BIGINT NOT NULL,
  currency     CHAR(3) DEFAULT 'COP',
  wompi_ref    VARCHAR(100) DEFAULT NULL,
  wompi_txn_id VARCHAR(100) DEFAULT NULL,
  status       ENUM('pending','approved','declined','error') DEFAULT 'pending',
  attempt_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  resolved_at  DATETIME DEFAULT NULL,
  INDEX idx_client (client_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- M18: notas privadas del coach por cliente
CREATE TABLE IF NOT EXISTS coach_notes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coach_id     INT UNSIGNED NOT NULL,
  client_id    INT UNSIGNED NOT NULL,
  note         TEXT NOT NULL,
  note_type    ENUM('general','seguimiento','alerta','logro') DEFAULT 'general',
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client (client_id),
  INDEX idx_coach_client (coach_id, client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- M26: retos grupales
CREATE TABLE IF NOT EXISTS challenges (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(160) NOT NULL,
  description    TEXT,
  challenge_type ENUM('checkins','steps','workouts','habits','custom') DEFAULT 'checkins',
  target_value   INT UNSIGNED NOT NULL DEFAULT 1,
  unit           VARCHAR(40) DEFAULT NULL,
  start_date     DATE NOT NULL,
  end_date       DATE NOT NULL,
  plan_access    SET('esencial','metodo','elite','rise') DEFAULT 'esencial,metodo,elite,rise',
  badge_icon     VARCHAR(80) DEFAULT 'trophy',
  created_by     INT UNSIGNED NOT NULL,
  active         TINYINT(1) DEFAULT 1,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- M26: participantes y progreso en challenges
CREATE TABLE IF NOT EXISTS challenge_participants (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  challenge_id   INT UNSIGNED NOT NULL,
  client_id      INT UNSIGNED NOT NULL,
  current_value  INT UNSIGNED DEFAULT 0,
  completed      TINYINT(1) DEFAULT 0,
  completed_at   DATETIME DEFAULT NULL,
  joined_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_challenge_client (challenge_id, client_id),
  INDEX idx_challenge (challenge_id),
  INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- M35: suscripciones push VAPID por cliente
CREATE TABLE IF NOT EXISTS push_subscriptions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id    INT UNSIGNED NOT NULL,
  endpoint     TEXT NOT NULL,
  p256dh       TEXT NOT NULL,
  auth_key     TEXT NOT NULL,
  user_agent   VARCHAR(255) DEFAULT NULL,
  active       TINYINT(1) DEFAULT 1,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client (client_id),
  INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- M37: contenido de la academia
CREATE TABLE IF NOT EXISTS academy_content (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255) NOT NULL,
  category     VARCHAR(80) NOT NULL,
  content_type ENUM('video','pdf','article','guide') DEFAULT 'article',
  audience     ENUM('client','coach','both') DEFAULT 'client',
  plan_access  SET('esencial','metodo','elite','rise','coach') DEFAULT 'esencial,metodo,elite,rise',
  thumbnail_url VARCHAR(500) DEFAULT NULL,
  content_url  VARCHAR(500) DEFAULT NULL,
  body_html    LONGTEXT DEFAULT NULL,
  description  TEXT DEFAULT NULL,
  sort_order   SMALLINT UNSIGNED DEFAULT 0,
  active       TINYINT(1) DEFAULT 1,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audience_active (audience, active),
  INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
