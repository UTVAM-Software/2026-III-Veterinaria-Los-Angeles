<?php
// Plantilla de configuración de base de datos
// INSTRUCCIONES: Renombra este archivo a 'conexion.php' y coloca tus credenciales locales.

define('DB_HOST', 'localhost');
define('DB_NAME', 'veterinaria');
define('DB_USER', 'TU_USUARIO');
define('DB_PASS', 'TU_PASSWORD');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión al servidor de base de datos.");
}
?>
