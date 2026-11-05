# 🐾 Estética Canina Los Ángeles

Sistema web modular para la gestión de pacientes, estética canina y citas médicas.

## Cómo está organizado
* **Pacientes:** cada mascota (y su dueño) se registra una sola vez.
* **Estética Canina:** cada visita es una hoja que apunta a un paciente.
* **Citas Médicas:** cada cita apunta a un paciente (no necesita pasar por estética).
Los módulos son independientes entre sí; solo comparten al paciente.

## Tecnologías
* **Backend:** PHP (PDO). **Base de datos:** MySQL. **Frontend:** HTML5, CSS3 y JavaScript.

## Cómo instalarlo (WAMP)
1. Copia la carpeta a `C:\wamp64\www\`.
2. En phpMyAdmin, sin seleccionar ninguna base, importa `schema.sql` (crea la base `veterinaria`).
3. Abre `http://localhost/trabajo_majo/login.php`. Usuarios de prueba: `veterinario` y `asistente`, contraseña `123456` (BCRYPT).
4. La carpeta `uploads/` debe poder escribirse (fotos).
