-- Creación de la base de datos oficial
CREATE DATABASE IF NOT EXISTS `veterinaria` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Le decimos que vamos a usar esa base de datos
USE `veterinaria`;

-- Eliminamos la tabla si ya existe para empezar limpios
DROP TABLE IF EXISTS `mascotas`;

-- Tabla donde se guardan las mascotas (pacientes) con los datos de su hoja de recepción
CREATE TABLE `mascotas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,         -- número único de cada mascota, se pone solo
  `folio` VARCHAR(20) DEFAULT NULL,            -- número de folio de la hoja
  `fecha` DATE DEFAULT NULL,                   -- fecha de la visita
  `hora_llegada` VARCHAR(20) DEFAULT NULL,
  `hora_salida` VARCHAR(20) DEFAULT NULL,
  `nombre` VARCHAR(100) NOT NULL,              -- nombre de la mascota (obligatorio)
  `raza` VARCHAR(100) DEFAULT NULL,
  `sexo` VARCHAR(20) DEFAULT NULL,
  `color` VARCHAR(50) DEFAULT NULL,
  `edad` VARCHAR(50) DEFAULT NULL,
  `dueno_nombre` VARCHAR(100) NOT NULL,        -- nombre del dueño (obligatorio)
  `dueno_telefono` VARCHAR(50) DEFAULT NULL,
  `domicilio` VARCHAR(255) DEFAULT NULL,
  `servicios` VARCHAR(255) DEFAULT NULL,       -- servicios pedidos, separados por comas
  `detalles_corte` TEXT DEFAULT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `costo_total` DECIMAL(10,2) DEFAULT 0.00,    -- precio total del servicio
  `a_cuenta` DECIMAL(10,2) DEFAULT 0.00,       -- anticipo que dejaron
  `forma_pago` VARCHAR(50) DEFAULT NULL
);

-- Ejemplo: una mascota de prueba para ver que todo funcione
INSERT INTO `mascotas` (`folio`, `fecha`, `hora_llegada`, `hora_salida`, `nombre`, 
`raza`, `sexo`, `color`, `edad`, `dueno_nombre`, `dueno_telefono`, `domicilio`, `servicios`, `detalles_corte`, 
`observaciones`, `costo_total`, `a_cuenta`, `forma_pago`) VALUES
('0730', '2026-06-06', '09:00 AM', '11:30 AM', 'Luna', 'Labrador', 'Hembra', 'Blanco', '2 años', 'María Rodríguez', 
'7791359705', 'Av. Central #123', 'Baño, Corte', 'Tijera #2', 'Muy amigable, sensible a las orejas.', 350.00, 200.00, 'Efectivo');