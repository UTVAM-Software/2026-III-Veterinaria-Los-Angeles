-- MIGRACIÓN ENTREGABLE 3: pasa una base 'veterinaria' que YA tienes a la nueva estructura SIN borrar pacientes, hojas ni citas.
-- Impórtala UNA sola vez en phpMyAdmin (selecciona la base 'veterinaria' antes de importar).
USE veterinaria;

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

-- usuarios: agregamos rol_id y activo, pasamos el rol viejo (texto) al nuevo (número) y quitamos la columna vieja
ALTER TABLE `usuarios` ADD COLUMN `rol_id` int(11) NULL AFTER `nombre`, ADD COLUMN `activo` tinyint(1) NOT NULL DEFAULT 1;
UPDATE `usuarios` u JOIN `roles` r ON r.nombre = IF(u.rol = 'veterinario', 'Veterinario', 'Asistente') SET u.rol_id = r.id;
ALTER TABLE `usuarios` MODIFY `rol_id` int(11) NOT NULL, DROP COLUMN `rol`,
  ADD CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- Un administrador para poder entrar a gestionar usuarios, roles y permisos (contraseña: 123456)
INSERT INTO `usuarios` (`usuario`, `password`, `nombre`, `rol_id`)
SELECT 'admin', '$2b$12$T3QinYNj9p1JBZ2Y8/yPEeo06SIWPvFSYaUm2/DOa1PPufua9lK.a', 'Administrador del sistema', id FROM roles WHERE nombre = 'Administrador';
