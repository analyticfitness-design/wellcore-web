-- WellCore Fitness — Schema v1.0
-- Run: mysql -u root -p < setup/schema.sql

CREATE DATABASE IF NOT EXISTS wellcore_fitness CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wellcore_fitness;

-- Clients (usuarios del portal)
CREATE TABLE IF NOT EXISTS clients (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_code   VARCHAR(20) UNIQUE NOT NULL,   -- 'cli-001'
  name          VARCHAR(255) NOT NULL,
  email         VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  plan          ENUM('esencial','metodo','elite') DEFAULT 'esencial',
  status        ENUM('activo','inactivo','pendiente') DEFAULT 'pendiente',
  fecha_inicio  DATE,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Client extended profile
CREATE TABLE IF NOT EXISTS client_profiles (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id        INT UNSIGNED NOT NULL UNIQUE,
  edad             TINYINT UNSIGNED,
  peso             DECIMAL(5,2),           -- kg
  altura           DECIMAL(5,2),           -- cm
  objetivo         VARCHAR(100),
  ciudad           VARCHAR(100),
  whatsapp         VARCHAR(50),
  nivel            ENUM('principiante','intermedio','avanzado') DEFAULT 'principiante',
  lugar_entreno    ENUM('gym','casa','ambos') DEFAULT 'gym',
  dias_disponibles JSON,                   -- [0,2,4] = Lun,Mie,Vie
  restricciones    TEXT,
  macros           JSON,                   -- {kcal,proteina,carbos,grasas}
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Admins / Coaches
CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(255),
  role          ENUM('coach','admin','jefe','superadmin','coaches','clientes','coach_manager') DEFAULT 'coach',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Auth tokens (session management)
CREATE TABLE IF NOT EXISTS auth_tokens (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_type   ENUM('client','admin') NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  token       CHAR(64) UNIQUE NOT NULL,
  fingerprint CHAR(64) DEFAULT NULL,
  ip_address  VARCHAR(45) DEFAULT NULL,
  expires_at  TIMESTAMP NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token),
  INDEX idx_user (user_type, user_id)
);

-- Training logs (daily completion tracking)
CREATE TABLE IF NOT EXISTS training_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id   INT UNSIGNED NOT NULL,
  log_date    DATE NOT NULL,
  completed   BOOLEAN DEFAULT FALSE,
  year_num    SMALLINT UNSIGNED NOT NULL,
  week_num    TINYINT UNSIGNED NOT NULL,
  UNIQUE KEY unique_log (client_id, log_date),
  INDEX idx_week (client_id, year_num, week_num),
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Body metrics history
CREATE TABLE IF NOT EXISTS metrics (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id          INT UNSIGNED NOT NULL,
  log_date           DATE NOT NULL,
  peso               DECIMAL(5,2),
  porcentaje_musculo DECIMAL(5,2),
  porcentaje_grasa   DECIMAL(5,2),
  pecho              DECIMAL(5,1),
  cintura            DECIMAL(5,1),
  cadera             DECIMAL(5,1),
  muslo              DECIMAL(5,1),
  brazo              DECIMAL(5,1),
  notas              TEXT,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_metric (client_id, log_date),
  INDEX idx_client (client_id, log_date),
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Progress photos
CREATE TABLE IF NOT EXISTS progress_photos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id   INT UNSIGNED NOT NULL,
  photo_date  DATE NOT NULL,
  tipo        ENUM('frente','perfil','espalda') NOT NULL,
  filename    VARCHAR(255) NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client (client_id, photo_date),
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Weekly check-ins (Elite plan)
CREATE TABLE IF NOT EXISTS checkins (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id        INT UNSIGNED NOT NULL,
  week_label       VARCHAR(10) NOT NULL,   -- '2026-W08'
  checkin_date     DATE NOT NULL,
  bienestar        TINYINT UNSIGNED,        -- 1-10
  dias_entrenados  TINYINT UNSIGNED,        -- 0-7
  nutricion        ENUM('Si','No','Parcial'),
  comentario       TEXT,
  coach_reply      TEXT,
  replied_at       TIMESTAMP NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_checkin (client_id, week_label),
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Assigned training/nutrition plans
CREATE TABLE IF NOT EXISTS assigned_plans (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id   INT UNSIGNED NOT NULL,
  plan_type   ENUM('entrenamiento','nutricion','habitos') NOT NULL,
  content     LONGTEXT,                    -- HTML or JSON content
  version     SMALLINT UNSIGNED DEFAULT 1,
  assigned_by INT UNSIGNED,
  valid_from  DATE,
  active      BOOLEAN DEFAULT TRUE,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client (client_id, plan_type),
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- Payments
CREATE TABLE IF NOT EXISTS payments (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id             INT UNSIGNED,
  email                 VARCHAR(255),
  payu_reference        VARCHAR(100) UNIQUE,
  payu_transaction_id   VARCHAR(100),
  payu_response         JSON,
  wompi_reference       VARCHAR(100),
  wompi_transaction_id  VARCHAR(100),
  payment_method        VARCHAR(50),
  plan                  ENUM('esencial','metodo','elite','rise') NOT NULL,
  amount                DECIMAL(12,2) NOT NULL,
  currency              VARCHAR(10) DEFAULT 'COP',
  status                ENUM('pending','approved','rejected','cancelled','declined','voided','error') DEFAULT 'pending',
  buyer_name            VARCHAR(255),
  buyer_phone           VARCHAR(50),
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_reference (payu_reference),
  UNIQUE INDEX idx_wompi_ref (wompi_reference),
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);

-- Seed: default jefe admin (run reseed-admins.php for real bcrypt hash)
-- INSERT IGNORE INTO admins (username, password_hash, name, role) VALUES
--   ('CoachDann', '$2y$12$REPLACE_WITH_REAL_HASH', 'Coach Dann', 'jefe');

-- Beta Invitations
CREATE TABLE IF NOT EXISTS invitations (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(32) UNIQUE NOT NULL,
  plan        ENUM('esencial','metodo','elite') NOT NULL,
  email_hint  VARCHAR(255),
  note        VARCHAR(500),
  status      ENUM('pending','used','expired') DEFAULT 'pending',
  created_by  INT UNSIGNED,
  used_by     INT UNSIGNED,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at  TIMESTAMP NULL,
  used_at     TIMESTAMP NULL,
  INDEX idx_code (code),
  INDEX idx_status (status),
  FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
  FOREIGN KEY (used_by) REFERENCES clients(id) ON DELETE SET NULL
);

-- Note: Generate real hashes with: php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT, ['cost'=>12]);"

-- ================================================================
-- SHOP TABLES — WellCore Tienda
-- ================================================================

CREATE TABLE IF NOT EXISTS shop_categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(100) UNIQUE NOT NULL,
  icon        VARCHAR(10) DEFAULT '&#9672;',
  sort_order  TINYINT UNSIGNED DEFAULT 0,
  active      BOOLEAN DEFAULT TRUE,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS shop_brands (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(100) UNIQUE NOT NULL,
  logo_url    VARCHAR(500),
  active      BOOLEAN DEFAULT TRUE,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS shop_products (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(150) UNIQUE NOT NULL,
  name          VARCHAR(255) NOT NULL,
  brand_id      INT UNSIGNED,
  category_id   INT UNSIGNED,
  description   TEXT,
  price_cop     INT UNSIGNED NOT NULL,
  compare_price INT UNSIGNED,
  image_url     VARCHAR(500),
  image_alt     VARCHAR(255),
  servings      VARCHAR(50),
  weight        VARCHAR(50),
  flavors       JSON,
  tags          JSON,
  stock         INT UNSIGNED DEFAULT 0,
  stock_status  ENUM('in_stock','low_stock','out_of_stock') DEFAULT 'in_stock',
  featured      BOOLEAN DEFAULT FALSE,
  active        BOOLEAN DEFAULT TRUE,
  views         INT UNSIGNED DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_category (category_id),
  INDEX idx_brand (brand_id),
  INDEX idx_active (active, stock_status),
  FOREIGN KEY (brand_id) REFERENCES shop_brands(id) ON DELETE SET NULL,
  FOREIGN KEY (category_id) REFERENCES shop_categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS shop_orders (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_code      VARCHAR(20) UNIQUE NOT NULL,
  client_id       INT UNSIGNED,
  guest_name      VARCHAR(255),
  guest_email     VARCHAR(255),
  guest_phone     VARCHAR(50),
  guest_city      VARCHAR(100),
  guest_address   TEXT,
  guest_notes     TEXT,
  subtotal_cop    INT UNSIGNED NOT NULL,
  shipping_cop    INT UNSIGNED DEFAULT 0,
  total_cop       INT UNSIGNED NOT NULL,
  status          ENUM('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
  payment_method  VARCHAR(50),
  payment_ref     VARCHAR(100),
  tracking_code   VARCHAR(100),
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_client (client_id),
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS shop_order_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED,
  product_name VARCHAR(255) NOT NULL,
  variant     VARCHAR(100),
  quantity    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  unit_price  INT UNSIGNED NOT NULL,
  FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS shop_analytics (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type  ENUM('view','add_to_cart','checkout','purchase') NOT NULL,
  product_id  INT UNSIGNED,
  session_id  VARCHAR(64),
  metadata    JSON,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event (event_type, created_at),
  INDEX idx_product (product_id),
  FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE SET NULL
);

-- ================================================================
-- WEIGHT LOGS — Registro de cargas de entrenamiento
-- ================================================================

CREATE TABLE IF NOT EXISTS weight_logs (
  id           VARCHAR(20) PRIMARY KEY,           -- w-{hex8}
  client_id    VARCHAR(60) NOT NULL,
  exercise     VARCHAR(255) NOT NULL,
  weight_kg    DECIMAL(6,2) NOT NULL,
  `sets`       TINYINT UNSIGNED NOT NULL,
  reps         SMALLINT UNSIGNED NOT NULL,
  rpe          DECIMAL(3,1) DEFAULT NULL,
  notes        VARCHAR(500) DEFAULT NULL,
  week_number  TINYINT UNSIGNED NOT NULL,
  `year`       SMALLINT UNSIGNED NOT NULL,
  `date`       DATETIME NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_client   (client_id),
  INDEX idx_exercise (client_id, exercise),
  INDEX idx_week     (client_id, `year`, week_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TICKETS — Sistema de soporte coach/cliente
-- ================================================================

CREATE TABLE IF NOT EXISTS tickets (
  id               VARCHAR(60) PRIMARY KEY,          -- TKT-{timestamp}-{rand}
  coach_id         VARCHAR(60) NOT NULL,
  coach_name       VARCHAR(255) DEFAULT NULL,
  client_name      VARCHAR(255) NOT NULL,
  client_plan      VARCHAR(20) DEFAULT NULL,
  ticket_type      ENUM('rutina_nueva','cambio_rutina','nutricion','habitos','invitacion_cliente','otro') NOT NULL,
  description      TEXT NOT NULL,
  priority         ENUM('normal','alta') DEFAULT 'normal',
  status           ENUM('open','in_progress','closed') DEFAULT 'open',
  response         TEXT DEFAULT NULL,
  assigned_to      VARCHAR(100) DEFAULT NULL,
  deadline         DATETIME NOT NULL,
  resolved_at      DATETIME DEFAULT NULL,
  ai_draft         TEXT DEFAULT NULL,
  ai_status        ENUM('none','pending','ready','approved') DEFAULT 'none',
  ai_generation_id INT DEFAULT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_coach  (coach_id),
  INDEX idx_status (status),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- INSCRIPTIONS — Solicitudes de inscripcion de clientes
-- ================================================================

CREATE TABLE IF NOT EXISTS inscriptions (
  id               VARCHAR(60) PRIMARY KEY,          -- INS-{timestamp}
  status           VARCHAR(50) DEFAULT 'pending_contact',
  plan             ENUM('esencial','metodo','elite') NOT NULL,
  nombre           VARCHAR(255) NOT NULL,
  apellido         VARCHAR(255) DEFAULT NULL,
  email            VARCHAR(255) NOT NULL,
  whatsapp         VARCHAR(50) NOT NULL,
  ciudad           VARCHAR(100) DEFAULT NULL,
  pais             VARCHAR(100) DEFAULT NULL,
  edad             TINYINT UNSIGNED DEFAULT NULL,
  objetivo         TEXT DEFAULT NULL,
  experiencia      VARCHAR(100) DEFAULT NULL,
  lesion           VARCHAR(100) DEFAULT NULL,
  detalle_lesion   TEXT DEFAULT NULL,
  dias_disponibles VARCHAR(100) DEFAULT NULL,
  horario          VARCHAR(100) DEFAULT NULL,
  como_conocio     VARCHAR(100) DEFAULT NULL,
  ip_hash          VARCHAR(64) DEFAULT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email  (email),
  INDEX idx_status (status),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- COACH APPLICATIONS — Solicitudes de coaches
-- ================================================================

CREATE TABLE IF NOT EXISTS coach_applications (
  id               VARCHAR(60) PRIMARY KEY,          -- CAP-{timestamp}
  status           VARCHAR(50) DEFAULT 'pending',
  name             VARCHAR(255) NOT NULL,
  email            VARCHAR(255) NOT NULL,
  whatsapp         VARCHAR(50) NOT NULL,
  city             VARCHAR(100) DEFAULT NULL,
  bio              TEXT NOT NULL,
  experience       VARCHAR(20) NOT NULL,
  plan             VARCHAR(50) DEFAULT NULL,
  current_clients  VARCHAR(50) DEFAULT NULL,
  specializations  JSON DEFAULT NULL,
  referral         VARCHAR(255) DEFAULT NULL,
  ip_hash          VARCHAR(64) DEFAULT NULL,
  admin_notes      TEXT DEFAULT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email  (email),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- COACH PERSONALIZATION — Perfiles, logros y referidos
-- ================================================================

CREATE TABLE IF NOT EXISTS coach_profiles (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id            INT UNSIGNED NOT NULL UNIQUE,
  slug                VARCHAR(80) NOT NULL UNIQUE,
  bio                 TEXT,
  city                VARCHAR(100),
  experience          VARCHAR(20),
  specializations     JSON DEFAULT NULL,
  photo_url           VARCHAR(500),
  color_primary       VARCHAR(7) DEFAULT '#E31E24',
  logo_url            VARCHAR(500) DEFAULT NULL,
  whatsapp            VARCHAR(50),
  instagram           VARCHAR(100),
  referral_code       VARCHAR(30) UNIQUE,
  referral_commission DECIMAL(5,2) DEFAULT 5.00,
  public_visible      TINYINT(1) DEFAULT 1,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coach_achievements (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id          INT UNSIGNED NOT NULL,
  achievement_type  VARCHAR(50) NOT NULL,
  label             VARCHAR(100) NOT NULL,
  icon              VARCHAR(50) DEFAULT 'star',
  earned_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_achievement (admin_id, achievement_type),
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referral_stats (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coach_id      INT UNSIGNED NOT NULL,
  visitor_hash  VARCHAR(64),
  source_url    VARCHAR(500),
  converted     TINYINT(1) DEFAULT 0,
  conversion_id INT UNSIGNED DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (coach_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
