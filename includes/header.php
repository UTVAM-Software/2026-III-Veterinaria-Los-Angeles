<?php
// Iniciamos la sesión, pero solo si todavía no estaba iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- Para que la página se vea bien en el celular también -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Título que sale en la pestaña del navegador -->
    <title>Estética Canina - Los Ángeles</title>
    <!-- Fuente redondeada y bonita estilo app -->
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Conectamos el archivo de estilos -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="app">
  <!-- Barra morada de arriba con el logo y el menú -->
  <header class="encabezado-superior">
    <div class="marca">
      <div class="marca-icono">
        <!-- Aquí carga tu logo.png (si no lo encuentra, muestra una patita dibujada) -->
        <img src="logo.png" alt="Logo" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' 
        width=\'40\' height=\'40\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23652780\' stroke-width=\'2\'><path 
        d=\'M10 5.172C10 3.782 8.423 2.679 6.5 3c-2 .333-3.5 2.1-3.5 4 0 3 4 6 9 11 5-5 9-8 9-11 0-1.9-1.5-3.667-3.5-4-1.923-.321-3.5.782-3.5 2.172z
        \'/></svg>'">
      </div>
      <!-- Nombre de la veterinaria -->
      <div class="marca-texto">Veterinaria<br>Los Ángeles</div>
    </div>
    <!-- Menú (por ahora solo tiene Estética Canina) -->
    <nav class="menu-superior">
      <div class="nav-item-top">
        <span>🐶 Estética Canina</span>
      </div>
    </nav>
  </header>
  <!-- Aquí empieza el contenido de cada página (se cierra en footer.php) -->
  <main class="contenido">