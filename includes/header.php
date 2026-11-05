<?php
// Pide haber iniciado sesión (también arranca la sesión)
require_once __DIR__ . '/auth.php';
// Para saber en qué página estamos y marcar el botón del menú
$pagina_actual = basename($_SERVER['PHP_SELF']);
$en_pacientes = in_array($pagina_actual, ['pacientes.php', 'paciente_nuevo.php', 'paciente_detalle.php']);
$en_estetica = in_array($pagina_actual, ['estetica.php', 'estetica_nueva.php', 'estetica_detalle.php']);
$en_citas = ($pagina_actual === 'citas.php');
$rol_texto = ($_SESSION['rol'] === 'veterinario') ? 'Veterinario' : 'Asistente';
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
      <a href="pacientes.php" class="nav-item-top <?= $en_pacientes ? 'activo' : '' ?>">🐕 Pacientes</a>
      <a href="estetica.php" class="nav-item-top <?= $en_estetica ? 'activo' : '' ?>">✂️ Estética Canina</a>
      <a href="citas.php" class="nav-item-top <?= $en_citas ? 'activo' : '' ?>">🩺 Citas Médicas</a>
    </nav>
    <!-- Quién está usando el sistema y botón para salir -->
    <div class="usuario-caja">
      <span>👤 <?= e($_SESSION['usuario_nombre']) ?><br><small><?= e($rol_texto) ?></small></span>
      <a href="logout.php" class="nav-item-top salir">Cerrar sesión</a>
    </div>
  </header>
  <main class="contenido">
<?php mostrar_flash(); ?>
