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
DROP TABLE IF EXISTS `rol_permiso`;
DROP TABLE IF EXISTS `permisos`;
DROP TABLE IF EXISTS `roles`;

-- ROLES (perfiles). "protegido" = rol con acceso total que no se puede borrar ni limitar (evita quedarse sin administrador).
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `protegido` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PERMISOS (las "llaves"). "sistema" = permisos que el código ya usa; no se pueden borrar desde la app.
CREATE TABLE IF NOT EXISTS `permisos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `modulo` varchar(30) NOT NULL,
  `sistema` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qué permisos trae cada rol (el "llavero")
CREATE TABLE IF NOT EXISTS `rol_permiso` (
  `rol_id` int(11) NOT NULL,
  `permiso_id` int(11) NOT NULL,
  PRIMARY KEY (`rol_id`, `permiso_id`),
  CONSTRAINT `fk_rp_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rp_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`nombre`, `descripcion`, `protegido`) VALUES
('Administrador', 'Acceso total al sistema', 1),
('Veterinario', 'Atiende pacientes, estética y citas; escribe diagnósticos', 0),
('Asistente', 'Registra pacientes, hojas de estética y citas', 0);

INSERT INTO `permisos` (`clave`, `nombre`, `modulo`, `sistema`) VALUES
('pacientes.ver', 'Ver pacientes', 'pacientes', 1),
('pacientes.crear', 'Registrar pacientes', 'pacientes', 1),
('pacientes.editar', 'Corregir datos de pacientes', 'pacientes', 1),
('pacientes.eliminar', 'Eliminar pacientes', 'pacientes', 1),
('estetica.ver', 'Ver hojas de estética', 'estetica', 1),
('estetica.crear', 'Crear hojas de estética', 'estetica', 1),
('estetica.editar', 'Editar hojas de estética', 'estetica', 1),
('estetica.eliminar', 'Eliminar hojas de estética', 'estetica', 1),
('citas.ver', 'Ver citas médicas', 'citas', 1),
('citas.crear', 'Agendar citas médicas', 'citas', 1),
('citas.editar', 'Editar citas médicas', 'citas', 1),
('citas.eliminar', 'Eliminar citas médicas', 'citas', 1),
('citas.diagnostico', 'Escribir diagnósticos', 'citas', 1),
('usuarios.ver', 'Ver usuarios', 'usuarios', 1),
('usuarios.crear', 'Crear usuarios', 'usuarios', 1),
('usuarios.editar', 'Editar usuarios', 'usuarios', 1),
('usuarios.eliminar', 'Eliminar usuarios', 'usuarios', 1),
('roles.ver', 'Ver roles', 'roles', 1),
('roles.crear', 'Crear roles', 'roles', 1),
('roles.editar', 'Editar roles', 'roles', 1),
('roles.eliminar', 'Eliminar roles', 'roles', 1),
('permisos.gestionar', 'Crear permisos y asignarlos a los roles', 'permisos', 1);

-- Veterinario: todo lo de pacientes, estética y citas (incluye diagnóstico)
INSERT INTO `rol_permiso` (`rol_id`, `permiso_id`)
SELECT r.id, p.id FROM roles r JOIN permisos p ON p.modulo IN ('pacientes','estetica','citas') WHERE r.nombre = 'Veterinario';

-- Asistente: ver, crear y editar (sin eliminar ni diagnóstico)
INSERT INTO `rol_permiso` (`rol_id`, `permiso_id`)
SELECT r.id, p.id FROM roles r JOIN permisos p
  ON p.clave IN ('pacientes.ver','pacientes.crear','pacientes.editar','estetica.ver','estetica.crear','estetica.editar','citas.ver','citas.crear','citas.editar')
WHERE r.nombre = 'Asistente';

-- usuarios: cada usuario tiene UN rol (rol_id) y puede estar activo o desactivado
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios de prueba. Contraseña: 123456 (encriptada con BCRYPT)
INSERT INTO `usuarios` (`usuario`, `password`, `nombre`, `rol_id`)
SELECT 'admin', '$2b$12$T3QinYNj9p1JBZ2Y8/yPEeo06SIWPvFSYaUm2/DOa1PPufua9lK.a', 'Administrador del sistema', id FROM roles WHERE nombre = 'Administrador';
INSERT INTO `usuarios` (`usuario`, `password`, `nombre`, `rol_id`)
SELECT 'veterinario', '$2b$12$T3QinYNj9p1JBZ2Y8/yPEeo06SIWPvFSYaUm2/DOa1PPufua9lK.a', 'Dr. Andrés Zamora Aguilar', id FROM roles WHERE nombre = 'Veterinario';
INSERT INTO `usuarios` (`usuario`, `password`, `nombre`, `rol_id`)
SELECT 'asistente', '$2b$12$T3QinYNj9p1JBZ2Y8/yPEeo06SIWPvFSYaUm2/DOa1PPufua9lK.a', 'Alondra', id FROM roles WHERE nombre = 'Asistente';

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
