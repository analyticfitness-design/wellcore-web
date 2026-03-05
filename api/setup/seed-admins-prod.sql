-- ================================================================
-- SEED: Administradores de producción — WellCore Fitness
-- Ejecutar en Easypanel MySQL console o phpMyAdmin
-- Fecha: Marzo 2026
-- ================================================================

-- Insertar superadmin principal (daniel.esparza)
INSERT IGNORE INTO admins (username, password_hash, name, role, status, created_at)
VALUES (
  'daniel.esparza',
  '$2y$12$HvPSaJh/Zy3A7Kaz5ie8E.yk0XPCUM93XGPJvuZSFHn57N6orab3e',
  'Daniel Esparza - CEO',
  'superadmin',
  'activo',
  NOW()
);

-- Admin genérico
INSERT IGNORE INTO admins (username, password_hash, name, role, status, created_at)
VALUES (
  'admin',
  '$2y$12$wmEZkXFxELKqW/8PuLLzluCRYubVT2RydGcaAzwvUcTRD8A3pdJc.',
  'Administrador',
  'admin',
  'activo',
  NOW()
);

-- Coach
INSERT IGNORE INTO admins (username, password_hash, name, role, status, created_at)
VALUES (
  'coach',
  '$2y$12$exQWhiu1TGq6H9Vy7O0Wz.6R4UR0u6CbKALs.RDLR7L9sRbrKwMkC',
  'Coach WellCore',
  'coach',
  'activo',
  NOW()
);

-- Verificar resultado
SELECT id, username, name, role, status FROM admins ORDER BY id;
