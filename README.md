# 🐾 Estética Canina Los Ángeles

Sistema web modular para la gestión de pacientes, estética canina y citas médicas.

## Cómo está organizado (Entregable 3)
* **Administración:** Usuarios (crear, listar, editar, activar/desactivar, eliminar), Roles y Permisos (tabla con palomitas para dar o quitar permisos a cada rol, y crear permisos nuevos), todo desde la app.
* **CRUD completo** en pacientes, hojas de estética, citas médicas y usuarios. Listas con búsqueda y paginación.
* Cada pantalla y cada botón revisan el permiso del usuario (`puede('clave')` / `exigir('clave')` en `includes/auth.php`). El rol **Administrador** está protegido: siempre tiene todo y no se puede borrar.

## Cómo está organizado
* **Pacientes:** cada mascota (y su dueño) se registra una sola vez.
* **Estética Canina:** cada visita es una hoja que apunta a un paciente.
* **Citas Médicas:** cada cita apunta a un paciente (no necesita pasar por estética).
Los módulos son independientes entre sí; solo comparten al paciente.

## Tecnologías
* **Backend:** PHP (PDO). **Base de datos:** MySQL. **Frontend:** HTML5, CSS3 y JavaScript.

## Cómo instalarlo (WAMP)
1. Copia la carpeta a `C:\wamp64\www\`.
2. En phpMyAdmin, sin seleccionar ninguna base, importa `schema.sql` (crea la base `veterinaria` desde cero; **borra los datos anteriores**).
   * Si ya tienes datos y no quieres perderlos: selecciona la base `veterinaria` e importa **solo una vez** `migracion_entregable3.sql`.
3. Abre `http://localhost/trabajo_majo/login.php`. Usuarios de prueba: `admin`, `veterinario` y `asistente`, contraseña `123456` (BCRYPT).
4. La carpeta `uploads/` debe poder escribirse (fotos).

## Archivos nuevos del Entregable 3
`usuarios.php`, `usuario_form.php`, `roles.php`, `rol_form.php`, `permisos.php`, `cita_editar.php`, `sin_permiso.php`, `migracion_entregable3.sql`.

## Archivos modificados
`schema.sql`, `includes/auth.php`, `includes/validar.php`, `includes/header.php`, `login.php`, `index.php`, `pacientes.php`, `paciente_detalle.php`, `paciente_nuevo.php`, `estetica.php`, `estetica_detalle.php`, `estetica_nueva.php` (ahora también edita), `citas.php`, `app.js`, `styles.css`.
