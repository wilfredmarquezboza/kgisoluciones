-- KGI Soluciones: datos iniciales (MySQL / MariaDB)
-- Requisito: las tablas ya existen (php spark migrate  o  database/esquema.sql).
-- Usuario inicial: admin@kgisoluciones.com  /  Admin123!   (cámbiala al primer ingreso)

SET NAMES utf8mb4;
SET @ahora = NOW();

-- Perfil de administración (es_admin = 1: acceso total)
INSERT INTO perfiles (id, nombre, descripcion, es_admin, created_at, updated_at) VALUES
  (1, 'Administrador', 'Administrador', 1, @ahora, @ahora);

-- Usuario administrador (contraseña cifrada con bcrypt: Admin123!)
INSERT INTO usuarios (id, perfil_id, nombres, email, password, activo, created_at, updated_at) VALUES
  (1, 1, 'Administrador', 'admin@kgisoluciones.com', '$2y$10$uzjIH9kCGIhLsoG1.hcW0uC5SA38PxmPqOl.Q48Bzu3uzTVEUUjBy', 1, @ahora, @ahora);

-- Configuraciones
INSERT INTO configuraciones (clave, valor, created_at, updated_at) VALUES
  ('SMTP_HOST',         'ssl://smtp.gmail.com', @ahora, @ahora),
  ('SMTP_PORT',         '465', @ahora, @ahora),
  ('SMTP_USER',         '', @ahora, @ahora),
  ('SMTP_PASS',         '', @ahora, @ahora),
  ('SMTP_FROM',         'KGI Soluciones', @ahora, @ahora),
  ('ANIO',              YEAR(@ahora), @ahora, @ahora),
  ('RUTA_ARCHIVO',      '/var/www/kgisoluciones.com/public_html/sistema/', @ahora, @ahora),
  ('SERVIDOR',          'https://kgisoluciones.com/sistema/', @ahora, @ahora),
  ('AUDITORIA',         'true', @ahora, @ahora),
  ('IGV',               '18', @ahora, @ahora),
  ('DETRACCION',        '12', @ahora, @ahora),
  ('AVISOS_EMAILS',     '', @ahora, @ahora),
  ('AVISOS_DIAS_ANTES', '3', @ahora, @ahora);

-- Clientes
INSERT INTO clientes (id, nombre, created_at, updated_at) VALUES
  (1, 'Edifica', @ahora, @ahora),
  (2, 'Grupo LAR', @ahora, @ahora),
  (3, 'GRV', @ahora, @ahora),
  (4, 'Resemin', @ahora, @ahora),
  (5, 'TM Gestión Inmobiliaria', @ahora, @ahora),
  (6, 'UPLA - Universidad Peruana de los Andes', @ahora, @ahora);

-- Proyectos (sin monto: aún no participan en Control de facturas)
INSERT INTO proyectos (id, cliente_id, departamento, nombre, created_at, updated_at) VALUES
  (1,  NULL, 'Ancash',   'Proyecto inmobiliario Condominio Club Playa Castillo de Arena', @ahora, @ahora),
  (2,  NULL, 'Ancash',   'Habilitación urbana Condominio Baiona', @ahora, @ahora),
  (3,  NULL, 'Ancash',   'Planeamiento integral La Gramita', @ahora, @ahora),
  (4,  NULL, 'Arequipa', 'Habilitación urbana Condominio Tolosa', @ahora, @ahora),
  (5,  NULL, 'Arequipa', 'Proyecto inmobiliario Home', @ahora, @ahora),
  (6,  NULL, 'Cusco',    NULL, @ahora, @ahora),
  (7,  NULL, 'Ica',      NULL, @ahora, @ahora),
  (8,  NULL, 'Junín',    'Diagnóstico de derechos Campus Universitario', @ahora, @ahora),
  (9,  NULL, 'Junín',    'Habilitación urbana Vista Hermosa', @ahora, @ahora),
  (10, NULL, 'Junín',    'Diagnóstico de derechos Trancapampa', @ahora, @ahora);
