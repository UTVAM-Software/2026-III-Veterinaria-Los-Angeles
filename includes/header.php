<?php
// Pide haber iniciado sesión (también arranca la sesión)
require_once __DIR__ . '/auth.php';
// Para saber en qué página estamos y marcar el botón del menú
$pagina_actual = basename($_SERVER['PHP_SELF']);
$en_pacientes = in_array($pagina_actual, ['pacientes.php', 'paciente_nuevo.php', 'paciente_detalle.php']);
$en_estetica = in_array($pagina_actual, ['estetica.php', 'estetica_nueva.php', 'estetica_detalle.php']);
$en_citas = in_array($pagina_actual, ['citas.php', 'cita_editar.php']);
$en_usuarios = in_array($pagina_actual, ['usuarios.php', 'usuario_form.php']);
$en_roles = in_array($pagina_actual, ['roles.php', 'rol_form.php']);
$en_permisos = ($pagina_actual === 'permisos.php');
$rol_texto = $_SESSION['rol_nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estética Canina - Los Ángeles</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="app">
  <header class="encabezado-superior">
    <div class="marca">
      <div class="marca-icono">
        <img src="LOGO.png" alt="Logo de la veterinaria">
      </div>
      <div class="marca-texto">Veterinaria<br>Los Ángeles</div>
    </div>
    <!-- Menú: tres botones grandes y claros -->
    <nav class="menu-superior" aria-label="Menú principal">
      <?php if (puede('pacientes.ver')): ?><a href="pacientes.php" class="nav-item-top <?= $en_pacientes ? 'activo' : '' ?>">🐕 Pacientes</a><?php endif; ?>
      <?php if (puede('estetica.ver')): ?><a href="estetica.php" class="nav-item-top <?= $en_estetica ? 'activo' : '' ?>">✂️ Estética Canina</a><?php endif; ?>
      <?php if (puede('citas.ver')): ?><a href="citas.php" class="nav-item-top <?= $en_citas ? 'activo' : '' ?>">🩺 Citas Médicas</a><?php endif; ?>
      <?php if (puede('usuarios.ver')): ?><a href="usuarios.php" class="nav-item-top <?= $en_usuarios ? 'activo' : '' ?>">👥 Usuarios</a><?php endif; ?>
      <?php if (puede('roles.ver')): ?><a href="roles.php" class="nav-item-top <?= $en_roles ? 'activo' : '' ?>">🎭 Roles</a><?php endif; ?>
      <?php if (puede('permisos.gestionar')): ?><a href="permisos.php" class="nav-item-top <?= $en_permisos ? 'activo' : '' ?>">🔑 Permisos</a><?php endif; ?>
    </nav>
    <!-- Quién está usando el sistema y botón para salir -->
    <div class="usuario-caja">
      <span>👤 <?= e($_SESSION['usuario_nombre']) ?><br><small><?= e($rol_texto) ?></small></span>
      <a href="logout.php" class="nav-item-top salir">Cerrar sesión</a>
    </div>
  </header>
  <main class="contenido">
<?php mostrar_flash(); ?>
