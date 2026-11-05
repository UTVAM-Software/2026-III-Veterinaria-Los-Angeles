<?php
// Cierra la sesión y regresa a la pantalla de entrada
session_start();
$_SESSION = [];
session_destroy();
header("Location: login.php");
exit();
