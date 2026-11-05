<?php
require_once "includes/auth.php";
require_once "includes/header.php";
?>

<div class="tarjeta-formulario" style="text-align:center; padding:50px 20px;">
    <div style="font-size:60px;">🔒</div>
    <h1>No tienes permiso para esta pantalla</h1>
    <p class="suave">Si crees que es un error, pídele al administrador que revise los permisos de tu rol (<strong><?= e($_SESSION['rol_nombre']) ?></strong>).</p>
    <?php $inicio = pagina_inicio(); if ($inicio !== 'sin_permiso.php'): ?>
        <a href="<?= e($inicio) ?>" class="boton boton-rosa boton-grande-auto">⬅️ Volver al inicio</a>
    <?php endif; ?>
</div>

<?php require_once "includes/footer.php"; ?>
