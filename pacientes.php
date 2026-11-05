<?php
require_once "includes/auth.php";
require_once "config/conexion.php";
exigir('pacientes.ver');

$q = trim($_GET['q'] ?? '');
$sql = "SELECT p.*,
          (SELECT COUNT(*) FROM hojas_estetica h WHERE h.paciente_id = p.id) AS n_hojas,
          (SELECT COUNT(*) FROM citas_medicas c WHERE c.paciente_id = p.id) AS n_citas
        FROM pacientes p ";
$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE p.nombre LIKE ? OR p.dueno_nombre LIKE ? OR p.dueno_telefono LIKE ? OR p.raza LIKE ? ";
    $params = ["%$q%", "%$q%", "%$q%", "%$q%"];
}
$total = $pdo->prepare("SELECT COUNT(*) FROM pacientes p " . $where);
$total->execute($params);
list($pagina, $paginas, $desde, $por_pagina) = paginar((int)$total->fetchColumn());
$stmt = $pdo->prepare($sql . $where . "ORDER BY p.nombre ASC LIMIT $por_pagina OFFSET $desde");
$stmt->execute($params);
$pacientes = $stmt->fetchAll();

require_once "includes/header.php";
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">🐕</span> Pacientes</h1>
        <p class="subtitulo">Aquí se registra cada mascota <strong>una sola vez</strong>. Después se le puede hacer una hoja de estética, una cita médica, o las dos.</p>
    </div>
    <?php if (puede('pacientes.crear')): ?>
    <a href="paciente_nuevo.php" class="boton boton-rosa boton-grande-auto"><span class="emoji">➕</span> Registrar paciente nuevo</a>
    <?php endif; ?>
</div>

<form method="GET" class="buscador-form" role="search">
    <input type="text" name="q" value="<?= e($q) ?>" class="input-control" style="flex:1;"
           placeholder="Buscar por nombre de la mascota, del dueño, teléfono o raza" aria-label="Buscar paciente">
    <button type="submit" class="boton boton-secundario"><span class="emoji">🔍</span> Buscar</button>
    <?php if ($q !== ''): ?><a href="pacientes.php" class="boton boton-secundario">Ver todos</a><?php endif; ?>
</form>

<div class="tabla-envoltura">
    <table class="tabla">
        <thead>
            <tr><th>Mascota</th><th>Dueño y teléfono</th><th>Historial</th><th>¿Qué quieres hacer?</th></tr>
        </thead>
        <tbody>
            <?php foreach ($pacientes as $p): ?>
            <tr>
                <td class="nombre-mascota"><span class="emoji">🐾</span> <?= e($p['nombre']) ?><br><small class="suave normal"><?= e($p['raza'] ?: 'Sin raza') ?></small></td>
                <td>👤 <?= e($p['dueno_nombre']) ?><br><small class="suave">📞 <?= telefono_mx($p['dueno_telefono']) ?></small></td>
                <td><small>✂️ <?= (int)$p['n_hojas'] ?> de estética<br>🩺 <?= (int)$p['n_citas'] ?> cita(s) médica(s)</small></td>
                <td>
                    <div class="acciones">
                        <a href="paciente_detalle.php?id=<?= (int)$p['id'] ?>" class="boton-texto">👁️ Ver ficha</a>
                        <?php if (puede('pacientes.editar')): ?><a href="paciente_nuevo.php?id=<?= (int)$p['id'] ?>" class="boton-texto">✏️ Editar</a><?php endif; ?>
                        <?php if (puede('estetica.crear')): ?><a href="estetica_nueva.php?paciente_id=<?= (int)$p['id'] ?>" class="boton-texto">✂️ Estética</a><?php endif; ?>
                        <?php if (puede('citas.crear')): ?><a href="citas.php?paciente_id=<?= (int)$p['id'] ?>#nueva-cita" class="boton-texto">🩺 Cita</a><?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (count($pacientes) === 0): ?>
            <tr><td colspan="4" class="vacio">
                <?php if ($q !== ''): ?>
                    No encontramos ningún paciente con "<strong><?= e($q) ?></strong>". Prueba con otra palabra o toca "Ver todos".
                <?php else: ?>
                    Todavía no hay pacientes. Toca <strong>"Registrar paciente nuevo"</strong> para empezar.
                <?php endif; ?>
            </td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php mostrar_paginacion($pagina, $paginas); ?>

<?php require_once "includes/footer.php"; ?>
