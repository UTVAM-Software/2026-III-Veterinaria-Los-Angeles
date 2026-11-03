<?php
// Traemos el archivo que conecta con la base de datos
require_once "config/conexion.php";
// Traemos el encabezado (menú de arriba)
require_once "includes/header.php";

// Guardamos lo que el usuario escriba en el buscador (si no escribió nada, queda vacío)
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q !== '') {
    // Si buscaron algo, filtramos por nombre, dueño, folio o teléfono
    $stmt = $pdo->prepare("SELECT * FROM mascotas WHERE nombre LIKE ? OR dueno_nombre LIKE ? OR folio LIKE ? OR 
    dueno_telefono LIKE ? ORDER BY id DESC");
    $stmt->execute(["%$q%", "%$q%", "%$q%", "%$q%"]);
} else {
    // Si no hay búsqueda, mostramos todos los registros (del más nuevo al más viejo)
    $stmt = $pdo->query("SELECT * FROM mascotas ORDER BY id DESC");
}
// Guardamos todos los resultados en una lista para usarla más abajo
$mascotas = $stmt->fetchAll();
?>

<!-- Título de la página y botón para registrar una mascota nueva -->
<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">🐕‍🦺</span> Lista de Pacientes y Recepción</h1>
        <p class="subtitulo">Estética Canina "Los Ángeles"</p>
    </div>
    <a href="mascota_nueva.php" class="boton boton-rosa">
        <span class="emoji">📋</span> Nueva Hoja de Recepción
    </a>
</div>

<!-- Formulario de Búsqueda -->
<form method="GET" class="buscador-form">
    <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Buscar por nombre, dueño, folio o teléfono..." 
    class="input-control">
    <button type="submit" class="boton boton-secundario">
        <span class="emoji">🔍</span> Buscar
    </button>
    <!-- Este botón solo sale si se hizo una búsqueda, sirve para limpiar el filtro -->
    <?php if ($q !== ''): ?>
        <a href="mascotas.php" class="boton boton-secundario" style="background:var(--rosita-pastel);">Ver todos</a>
    <?php endif; ?>
</form>

<!-- Tabla con la lista de pacientes -->
<div class="tabla-envoltura">
    <table class="tabla">
        <thead>
            <tr>
                <th>Folio</th>
                <th>Fecha / Hora</th>
                <th>Mascota</th>
                <th>Dueño / Tel</th>
                <th>Servicio</th>
                <th>Total</th>
                <th>Ficha</th>
            </tr>
        </thead>
        <tbody>
            <!-- Con el foreach se crea una fila por cada mascota -->
            <?php foreach ($mascotas as $m): ?>
            <tr>
                <td><strong>#<?php echo htmlspecialchars($m['folio'] ?? '0730'); ?></strong></td>
                <td><?php echo htmlspecialchars($m['fecha'] ?? '-'); ?><br><small style="color:var(--texto-suave);"><?php 
                echo htmlspecialchars($m['hora_llegada']); ?></small></td>
                <td class="nombre-mascota"><span class="emoji">🐾</span> <?php echo htmlspecialchars($m['nombre']); ?><br><small s
                tyle="font-weight:normal; color:var(--texto-suave);"><?php echo htmlspecialchars($m['raza']); ?></small></td>
                <td>👤 <?php echo htmlspecialchars($m['dueno_nombre']); ?><br><small style="color:var(--texto-suave);">📞 <?php 
                echo htmlspecialchars($m['dueno_telefono']); ?></small></td>
                <td><?php echo htmlspecialchars($m['servicios'] ?? 'Estética'); ?></td>
                <td><strong>$<?php echo number_format($m['costo_total'] ?? 0, 2); ?></strong></td>
                <!-- Este link lleva a la ficha completa de la mascota usando su id -->
                <td><a href="mascota_detalle.php?id=<?php echo $m['id']; ?>" class="boton-texto"><span class="emoji">👁️</span> Ver Ficha</a></td>
            </tr>
            <?php endforeach; ?>
            <!-- Si no hay ninguna mascota en la lista, mostramos este mensaje -->
            <?php if (count($mascotas) === 0): ?>
            <tr>
                <td colspan="7" style="text-align:center; padding:30px; color:var(--texto-suave);">No se encontraron resultados para la búsqueda.
                    
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Traemos el pie de página -->
<?php require_once "includes/footer.php"; ?>