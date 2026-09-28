USE veterinaria_db;

INSERT INTO usuario (nombre, contrasena, rol) VALUES
('Carlos Mendoza', 'admin123', 'admin'),
('Ana Gutierrez', 'asistente123', 'asistente');

INSERT INTO dueno (nombre, telefono, correo) VALUES
('Juan Perez', '5551234567', 'juan.perez@email.com'),
('Maria Lopez', '5559876543', 'maria.lopez@email.com');

INSERT INTO mascota (id_dueno, nombre, especie, raza, edad, sexo, peso, color) VALUES
(1, 'Firulais', 'Perro', 'Labrador', 3, 'Macho', 25.50, 'Dorado'),
(2, 'Michi', 'Gato', 'Mestizo', 2, 'Hembra', 4.20, 'Blanco');

INSERT INTO proveedor (nombre, tipo) VALUES
('Distribuidora Veterinaria', 'Medicamentos'),
('PetSupply', 'Alimentos');

INSERT INTO producto (id_proveedor, nombre, precio, existencias) VALUES
(1, 'Desparasitante Canino', 150.00, 50),
(2, 'Alimento para Gato 2kg', 320.50, 20);

INSERT INTO visita (id_mascota, id_usuario, fecha, diagnostico, cantidad_pagada) VALUES
(1, 1, '2026-09-15', 'Revision general y vacuna anual.', 450.00);

INSERT INTO gasto (id_usuario, tipo, monto) VALUES
(1, 'Mantenimiento', 1200.00);

INSERT INTO venta (id_usuario, fecha, total) VALUES
(2, '2026-09-16', 470.50);

INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario) VALUES
(1, 1, 1, 150.00),
(1, 2, 1, 320.50);