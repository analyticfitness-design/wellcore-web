-- WellCore v8 — Migration 010: Columnas de perfil en clients
-- Run: php database/run_migration.php 010_client_profile_fields.sql

-- NOTA: MySQL no soporta ADD COLUMN IF NOT EXISTS.
-- Usar run_migration.php o ejecutar manualmente verificando columnas existentes.
-- En producción (EasyPanel console), copiar el bloque ALTER relevant:

ALTER TABLE clients
  ADD COLUMN avatar_url    VARCHAR(500) DEFAULT NULL,
  ADD COLUMN bio           TEXT DEFAULT NULL,
  ADD COLUMN city          VARCHAR(100) DEFAULT NULL,
  ADD COLUMN birth_date    DATE DEFAULT NULL,
  ADD COLUMN referral_code VARCHAR(20) DEFAULT NULL,
  ADD COLUMN referred_by   INT UNSIGNED DEFAULT NULL;

ALTER TABLE clients
  ADD UNIQUE INDEX idx_referral_code (referral_code);
