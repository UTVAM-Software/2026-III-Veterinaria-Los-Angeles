<?php
// Página principal: manda a la primera pantalla a la que el usuario tiene permiso (si no hay sesión, lo manda al login)
require_once "includes/auth.php";
header("Location: " . pagina_inicio());
exit();
