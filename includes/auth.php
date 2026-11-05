<?php
// Este archivo se pone al INICIO de cada página que necesita haber iniciado sesión.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/validar.php';

// Si no ha iniciado sesión, lo mandamos a la pantalla de entrada
if (!isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}

// "Llave secreta" de la sesión: evita que otra página engañe al sistema para borrar o guardar cosas
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function csrf_ok() {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}
