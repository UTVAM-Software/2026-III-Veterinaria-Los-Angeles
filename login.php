<?php
session_start();
require_once "config/conexion.php";
require_once "includes/validar.php";

// Si ya había iniciado sesión, lo mandamos directo al sistema
if (isset($_SESSION['rol'])) {
    header("Location: pacientes.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario === '' || $password === '') {
        $error = "Escribe tu usuario y tu contraseña para entrar.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        // Se compara la contraseña escrita contra la encriptada (BCRYPT) guardada en la base de datos
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true); // sesión nueva por seguridad
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_nombre'] = $user['nombre'];
            $_SESSION['rol'] = $user['rol'];
            header("Location: pacientes.php");
            exit();
        } else {
            // Mismo mensaje para usuario o contraseña mal: no damos pistas a extraños
            $error = "El usuario o la contraseña no son correctos. Revisa que estén bien escritos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Estética Canina</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="pagina-login">

<div class="tarjeta-formulario login-caja">
    <h2>🐾 Bienvenida(o)</h2>
    <p class="subtitulo">Sistema de la Veterinaria y Estética Canina "Los Ángeles"</p>

    <?php if ($error !== ''): ?>
        <div class="alerta alerta-error" role="alert">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST" autocomplete="on">
        <div class="form-grupo campo-login">
            <label for="usuario">👤 Usuario</label>
            <input type="text" id="usuario" name="usuario" class="input-control" value="<?= e($_POST['usuario'] ?? '') ?>"
                   placeholder="Escribe tu usuario" required autofocus
                   data-vacio="Escribe tu usuario">
        </div>
        <div class="form-grupo campo-login">
            <label for="password">🔒 Contraseña</label>
            <input type="password" id="password" name="password" class="input-control"
                   placeholder="Escribe tu contraseña" required data-vacio="Escribe tu contraseña">
            <label class="mostrar-clave"><input type="checkbox" id="ver-clave"> Mostrar la contraseña mientras escribo</label>
        </div>
        <button type="submit" class="boton boton-rosa boton-grande">Entrar al sistema</button>
    </form>
</div>

<script src="app.js"></script>
</body>
</html>
