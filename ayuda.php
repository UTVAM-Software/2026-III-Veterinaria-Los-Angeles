<?php
require_once "includes/auth.php";
require_once "config/conexion.php";
require_once "includes/header.php";
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">❓</span> Ayuda</h1>
        <p class="subtitulo">Guía rápida de uso del sistema para el personal de la clínica.</p>
    </div>
</div>

<div class="tarjeta-formulario">
    <h3 style="margin-top:0;">🐾 Pacientes</h3>
    <p>En "Pacientes" se registra cada mascota junto con los datos de su dueño. Desde la ficha de cada paciente se pueden ver sus hojas de estética y sus citas médicas anteriores.</p>

    <h3>✂️ Estética Canina</h3>
    <p>Cada visita de estética se registra como una hoja nueva, siempre ligada a un paciente que ya exista en el sistema.</p>

    <h3>🩺 Citas Médicas</h3>
    <p>Una cita se agenda eligiendo al paciente, la fecha y la hora. Una cita marcada como "Pendiente" no puede quedar con una fecha que ya pasó; si la cita ya se atendió, se cambia su estado a "Atendida" y ahí sí se puede corregir la fecha si hace falta.</p>

    <h3>🔑 Si no puedes ver una pantalla</h3>
    <p>Cada usuario solo ve las secciones para las que tiene permiso. Si te falta acceso a algo, pídele a un Administrador que te lo asigne desde "Permisos".</p>
</div>

<?php require_once "includes/footer.php"; ?>