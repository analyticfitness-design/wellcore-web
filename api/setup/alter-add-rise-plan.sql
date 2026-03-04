-- alter-add-rise-plan.sql
-- Migración: Agregar plan RISE al sistema WellCore Fitness
-- Fecha: 2026-03-03

-- Agregar 'rise' al enum de plan en clientes
ALTER TABLE clients MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise') DEFAULT 'esencial';

-- Agregar 'rise' al enum de tipo de plan en pagos
ALTER TABLE payments MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise');

-- Agregar 'rise' al enum de plan en invitaciones
ALTER TABLE invitations MODIFY COLUMN plan ENUM('esencial','metodo','elite','rise');

-- Tabla de programas RISE personalizados
CREATE TABLE IF NOT EXISTS rise_programs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT UNSIGNED NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    experience_level ENUM('principiante','intermedio','avanzado') NOT NULL,
    training_location ENUM('gym','home','hybrid') NOT NULL,
    gender ENUM('male','female','other') NOT NULL,
    status ENUM('active','completed','paused','cancelled') DEFAULT 'active',
    personalized_program LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client (client_id),
    INDEX idx_status (status)
);

-- Tabla de logs diarios del reto RISE
CREATE TABLE IF NOT EXISTS rise_daily_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rise_program_id INT NOT NULL,
    log_date DATE NOT NULL,
    workout_completed BOOLEAN DEFAULT FALSE,
    workout_notes TEXT,
    habits_completed INT DEFAULT 0,
    nutrition_adherence ENUM('excellent','good','fair','poor') DEFAULT 'fair',
    mood_level INT COMMENT '1-10',
    energy_level INT COMMENT '1-10',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rise_program_id) REFERENCES rise_programs(id) ON DELETE CASCADE,
    INDEX idx_program (rise_program_id),
    INDEX idx_date (log_date)
);
