<?php
require_once "includes/auth.php";
require_once "config/conexion.php";
exigir('estetica.ver');

$q = trim($_GET['q'] ?? '');
$sql = "SELECT h.*, p.nombre, p.raza, p.dueno_nombre, p.dueno_telefono
        FROM hojas_estetica h JOIN pacientes p ON h.paciente_id = p.id ";
$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE p.nombre LIKE ? OR p.dueno_nombre LIKE ? OR h.folio LIKE ? OR p.dueno_telefono LIKE ? ";
    $params = ["%$q%", "%$q%", "%$q%", "%$q%"];
}
$total = $pdo->prepare("SELECT COUNT(*) FROM hojas_estetica h JOIN pacientes p ON h.paciente_id = p.id " . $where);
$total->execute($params);
list($pagina, $paginas, $desde, $por_pagina) = paginar((int)$total->fetchColumn());
$stmt = $pdo->prepare($sql . $where . "ORDER BY h.id DESC LIMIT $por_pagina OFFSET $desde");
$stmt->execute($params);
$hojas = $stmt->fetchAll();

require_once "includes/header.php";
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">✂️</span> Estética Canina</h1>
        <p class="subtitulo">Cada fila es una visita a estética. Toca <strong>"Ver hoja"</strong> para ver el detalle.</p>
    </div>
    <?php if (puede('estetica.crear')): ?>
    <a href="estetica_nueva.php" class="boton boton-rosa boton-grande-auto"><span class="emoji">➕</span> Nueva hoja de estética</a>
    <?php endif; ?>
</div>

<form method="GET" class="buscador-form" role="search">
    <input type="text" name="q" value="<?= e($q) ?>" class="input-control" style="flex:1;"
           placeholder="Buscar por mascota, dueño, folio o teléfono" aria-label="Buscar hoja de estética">
    <button type="submit" class="boton boton-secundario"><span class="emoji">🔍</span> Buscar</button>
    <?php if ($q !== ''): ?><a href="estetica.php" class="boton boton-secundario">Ver todas</a><?php endif; ?>
</form>

<div class="tabla-envoltura">
    <table class="tabla">
        <thead>
            <tr><th>Folio</th><th>Fecha y hora</th><th>Mascota</th><th>Dueño y teléfono</th><th>Servicio</th><th>Total y saldo</th><th>¿Qué quieres hacer?</th></tr>
        </thead>
        <tbody>
            <?php foreach ($hojas as $h): ?>
            <tr>
                <td><strong>#<?= e($h['folio']) ?></strong></td>
                <td><?= fecha_mx($h['fecha']) ?><br><small class="suave"><?= e($h['hora_llegada']) ?></small></td>
                <td class="nombre-mascota"><span class="emoji">🐾</span> <a href="paciente_detalle.php?id=<?= (int)$h['paciente_id'] ?>"><?= e($h['nombre']) ?></a><br><small class="suave normal"><?= e($h['raza']) ?></small></td>
                <td>👤 <?= e($h['dueno_nombre']) ?><br><small class="suave">📞 <?= telefono_mx($h['dueno_telefono']) ?></small></td>
                <td><?= e($h['servicios'] ?: 'Sin servicio marcado') ?></td>
                <td><strong><?= dinero($h['costo_total']) ?></strong><br>
                    <?php $debe = saldo_pendiente($h['costo_total'], $h['a_cuenta']); ?>
                    <small class="suave"><?= $debe > 0 ? 'Debe ' . dinero($debe) : '✅ Pagada' ?></small></td>
                <td><div class="acciones">
                    <a href="estetica_detalle.php?id=<?= (int)$h['id'] ?>" class="boton-texto"><span class="emoji">👁️</span> Ver hoja</a>
                    <?php if (puede('estetica.editar')): ?><a href="estetica_nueva.php?id=<?= (int)$h['id'] ?>" class="boton-texto">✏️ Editar</a><?php endif; ?>
                </div></td>
            </tr>
            <?php endforeach; ?>
            <?php if (count($hojas) === 0): ?>
            <tr><td colspan="7" class="vacio">
                <?php if ($q !== ''): ?>
                    No encontramos hojas con "<strong><?= e($q) ?></strong>". Prueba con otra palabra o toca "Ver todas".
                <?php else: ?>
                    Todavía no hay hojas de estética. Toca <strong>"Nueva hoja de estética"</strong> para empezar.
                <?php endif; ?>
            </td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php mostrar_paginacion($pagina, $paginas); ?>

<?php require_once "includes/footer.php"; ?>
