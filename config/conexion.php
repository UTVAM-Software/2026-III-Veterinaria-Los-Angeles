<?php
// Datos para conectarnos a la base de datos
$host = "localhost";    // el servidor (nuestra propia computadora)
$db   = "veterinaria";  // nombre de la base de datos
$user = "root";         // usuario de MySQL
$pass = "";             // contraseña (vacía porque es la de por defecto)

try {
    // Creamos el puente de conexión
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    // Con esto, si hay un error en una consulta nos manda el mensaje del error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Si no se pudo conectar, detenemos todo y mostramos el error
    die("Error de conexión: " . $e->getMessage());
}
?>