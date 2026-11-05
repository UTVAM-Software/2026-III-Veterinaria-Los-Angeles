<?php
// Datos de conexión protegidos mediante constantes de configuración
define('DB_HOST', 'localhost');
define('DB_NAME', 'veterinaria');
define('DB_USER', 'root');
define('DB_PASS', ''); // En entorno local se mantiene vacío por defecto

try {
    // Creamos la conexión PDO utilizando las constantes definidas
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Control de errores seguro
    die("Error de conexión al servidor de base de datos.");
}
?>