<?php
// Conexión a la base de datos
require_once "config/conexion.php";

// Sacamos el id de la mascota que viene en la dirección (?id=...)
$id = $_GET['id'] ?? null;
// Si no mandaron ningún id, regresamos a la lista
if (!$id) {
    header("Location: mascotas.php");
    exit();
}

// Si se solicitó eliminar el registro
if (isset($_GET['action']) && $_GET['action'] === 'eliminar') {
    // Primero buscamos el nombre de la foto de esa mascota
    $stmt_foto = $pdo->prepare("SELECT foto FROM mascotas WHERE id = ?");
    $stmt_foto->execute([$id]);
    $m_foto = $stmt_foto->fetch();
    // Si tiene foto y el archivo existe en la carpeta uploads, lo borramos
    if ($m_foto && !empty($m_foto['foto']) && file_exists('uploads/' . $m_foto['foto'])) {
        unlink('uploads/' . $m_foto['foto']);
    }

    // Borramos el registro de la base de datos
    $stmt_del = $pdo->prepare("DELETE FROM mascotas WHERE id = ?");
    $stmt_del->execute([$id]);

    // Regresamos a la lista
    header("Location: mascotas.php");
    exit();
}

// Buscamos los datos de la mascota con ese id
$stmt = $pdo->prepare("SELECT * FROM mascotas WHERE id = ?");
$stmt->execute([$id]);
$mascota = $stmt->fetch();

// Si no existe esa mascota, regresamos a la lista
if (!$mascota) {
    header("Location: mascotas.php");
    exit();
}

// Traemos el encabezado (menú de arriba)
require_once "includes/header.php";
?>

<!-- Título con el nombre de la mascota, el folio y la fecha -->
<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">🐾</span> Ficha de <?php echo htmlspecialchars($mascota['nombre']); ?></h1>
        <p class="subtitulo">Folio: #<?php echo htmlspecialchars($mascota['folio']); ?> | 
        Fecha: <?php echo htmlspecialchars($mascota['fecha']); ?></p>
    </div>
    <div style="display: flex; gap: 10px;">
        <!-- Botón para regresar a la lista -->
        <a href="mascotas.php" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver</a>
        <!-- Botón para eliminar, antes pregunta si de verdad se quiere borrar -->
        <a href="mascota_detalle.php?id=<?php echo $mascota['id']; ?>&action=eliminar" class="boton" 
        style="background: #ff6b6b; color: white;" 
        onclick="return confirm('¿Estás segura de que deseas eliminar este registro?');"><span class="emoji">🗑️</span> 
        Eliminar Paciente</a>
    </div>
</div>

<div class="tarjeta-detalle" style="display: flex; gap: 30px; align-items: flex-start; flex-wrap: wrap;">
    <!-- Contenedor de la Fotografía -->
    <div style="flex: 1; min-width: 250px; text-align: center; background: var(--morado-suave); padding: 20px; border-radius: 16px;">
        <!-- Si la mascota tiene foto la mostramos, si no, ponemos un perrito y un mensaje -->
        <?php if (!empty($mascota['foto']) && file_exists('uploads/' . $mascota['foto'])): ?>
            <img src="uploads/<?php echo htmlspecialchars($mascota['foto']); ?>" alt="Foto de la mascota" style="width: 100%; max-height: 
            280px; object-fit: cover; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <?php else: ?>
            <div style="padding: 60px 20px; font-size: 60px;">🐶</div>
            <p style="color: var(--texto-suave); font-weight: bold;">Sin fotografía registrada</p>
        <?php endif; ?>
        <h3 style="margin-top: 15px; color: var(--morado-principal);"><?php echo htmlspecialchars($mascota['nombre']); ?></h3>
        <!-- Si no tiene raza escrita, ponemos "Sin Raza" -->
        <p style="color: var(--texto-suave);"><?php echo htmlspecialchars($mascota['raza'] ?: 'Sin Raza'); ?></p>
    </div>

    <!-- Contenedor de los Detalles -->
    <div style="flex: 2; min-width: 300px;" class="detalle-grid">
        <!-- Horas de llegada y salida -->
        <div class="detalle-item">
            <span class="label">⏰ Horarios</span>
            <span class="valor" style="font-size:16px;">Llegada: <?php echo htmlspecialchars($mascota['hora_llegada'] ?: 'N/A'); ?> 
            | Salida: <?php echo htmlspecialchars($mascota['hora_salida'] ?: 'N/A'); ?></span>
        </div>
        <!-- Sexo y color -->
        <div class="detalle-item">
            <span class="label">✂️ Sexo / Color</span>
            <span class="valor" style="font-size:16px;"><?php echo htmlspecialchars($mascota['sexo'] . ' / ' . ($mascota['color'] ?: 
            'No especificado')); ?></span>
        </div>
        <!-- Nombre y teléfono del dueño -->
        <div class="detalle-item">
            <span class="label">👤 Dueño y Contacto</span>
            <span class="valor" style="font-size:16px;"><?php echo htmlspecialchars($mascota['dueno_nombre']); ?> (📞 <?php echo 
            htmlspecialchars($mascota['dueno_telefono'] ?: 'Sin teléfono'); ?>)</span>
        </div>
        <!-- Dirección del dueño -->
        <div class="detalle-item">
            <span class="label">🏠 Domicilio</span>
            <span class="valor" style="font-size:16px;"><?php echo htmlspecialchars($mascota['domicilio'] ?: 'No registrado'); ?></span>
        </div>
        <!-- Servicios que pidieron -->
        <div class="detalle-item full-width">
            <span class="label">✨ Servicios Solicitados</span>
            <span class="valor" style="font-size:16px; color:var(--turquesa-principal);"><?php echo htmlspecialchars($mascota['servicios'] ?: 
            'Ninguno especificado'); ?></span>
        </div>
        <!-- Cómo quieren el corte -->
        <div class="detalle-item full-width">
            <span class="label">✂️ Detalles de Corte</span>
            <div class="valor-box"><?php echo htmlspecialchars($mascota['detalles_corte'] ?: 'Sin especificaciones.'); ?></div>
        </div>
        <!-- Observaciones (nl2br sirve para que se vean los saltos de línea) -->
        <div class="detalle-item full-width">
            <span class="label">📋 Observaciones</span>
            <div class="valor-box"><?php echo nl2br(htmlspecialchars($mascota['observaciones'] ?: 'Sin observaciones.')); ?></div>
        </div>
        <!-- Total y anticipo, con 2 decimales -->
        <div class="detalle-item">
            <span class="label">💲 Costos y Anticipo</span>
            <span class="valor">Total: $<?php echo number_format($mascota['costo_total'] ?? 0, 2); ?> 
            | A cuenta: $<?php echo number_format($mascota['a_cuenta'] ?? 0, 2); ?></span>
        </div>
        <!-- Forma de pago (si no hay, ponemos Efectivo) -->
        <div class="detalle-item">
            <span class="label">💳 Forma de Pago</span>
            <span class="valor"><?php echo htmlspecialchars($mascota['forma_pago'] ?: 'Efectivo'); ?></span>
        </div>
    </div>
</div>

<!-- Traemos el pie de página -->
<?php require_once "includes/footer.php"; ?>