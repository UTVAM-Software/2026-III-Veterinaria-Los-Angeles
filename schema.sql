-- Crear la base de datos si no existe (solo se usa esta base, no toca otras)
DROP DATABASE IF EXISTS veterinaria;
CREATE DATABASE IF NOT EXISTS veterinaria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE veterinaria;

-- Se borran en este orden por las relaciones entre tablas
DROP TABLE IF EXISTS `citas_medicas`;
DROP TABLE IF EXISTS `hojas_estetica`;
DROP TABLE IF EXISTS `mascotas`;
DROP TABLE IF EXISTS `pacientes`;
DROP TABLE IF EXISTS `usuarios`;

-- usuarios (roles y nombres)
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `rol` enum('veterinario','asistente') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios de prueba. Contraseña: 123456 (encriptada con BCRYPT)
INSERT INTO `usuarios` (`usuario`, `password`, `nombre`, `rol`) VALUES
('veterinario', '$2b$12$T3QinYNj9p1JBZ2Y8/yPEeo06SIWPvFSYaUm2/DOa1PPufua9lK.a', 'Dr. Andrés Zamora Aguilar', 'veterinario'),
('asistente', '$2b$12$T3QinYNj9p1JBZ2Y8/yPEeo06SIWPvFSYaUm2/DOa1PPufua9lK.a', 'Alondra', 'asistente');

-- PACIENTES: la mascota y su dueño, se registran UNA sola vez.
-- Es independiente: puede tener hojas de estética, citas médicas, las dos o ninguna.
CREATE TABLE `pacientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `raza` varchar(50) DEFAULT NULL,
  `sexo` varchar(20) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `edad` varchar(20) DEFAULT NULL,
  `dueno_nombre` varchar(100) NOT NULL,
  `dueno_telefono` varchar(20) NOT NULL,
  `domicilio` varchar(200) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `creado` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MÓDULO ESTÉTICA: cada visita a estética es una hoja que apunta a un paciente.
-- ON DELETE RESTRICT = no se puede borrar un paciente que ya tiene hojas (protege el historial).
CREATE TABLE `hojas_estetica` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folio` varchar(20) NOT NULL,
  `paciente_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_llegada` varchar(20) DEFAULT NULL,
  `hora_salida` varchar(20) DEFAULT NULL,
  `servicios` text DEFAULT NULL,
  `detalles_corte` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `costo_total` decimal(10,2) DEFAULT 0.00,
  `a_cuenta` decimal(10,2) DEFAULT 0.00,
  `forma_pago` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  CONSTRAINT `fk_hojas_pacientes` FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MÓDULO MÉDICO: cada cita apunta a un paciente (sin necesidad de pasar por estética).
CREATE TABLE `citas_medicas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paciente_id` int(11) NOT NULL,
  `fecha_cita` date NOT NULL,
  `hora_cita` varchar(20) NOT NULL,
  `motivo` text NOT NULL,
  `diagnostico` text DEFAULT NULL,
  `estatus` enum('Pendiente','Atendida') DEFAULT 'Pendiente',
  `agendado_por` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  CONSTRAINT `fk_citas_pacientes` FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
