<?php
require_once "includes/auth.php";
require_once "config/conexion.php";

$q = trim($_GET['q'] ?? '');
$sql = "SELECT p.*,
          (SELECT COUNT(*) FROM hojas_estetica h WHERE h.paciente_id = p.id) AS n_hojas,
          (SELECT COUNT(*) FROM citas_medicas c WHERE c.paciente_id = p.id) AS n_citas
        FROM pacientes p ";
if ($q !== '') {
    $stmt = $pdo->prepare($sql . "WHERE p.nombre LIKE ? OR p.dueno_nombre LIKE ? OR p.dueno_telefono LIKE ? OR p.raza LIKE ? ORDER BY p.nombre ASC");
    $stmt->execute(["%$q%", "%$q%", "%$q%", "%$q%"]);
} else {
    $stmt = $pdo->query($sql . "ORDER BY p.nombre ASC");
}
$pacientes = $stmt->fetchAll();

require_once "includes/header.php";
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">🐕</span> Pacientes</h1>
        <p class="subtitulo">Aquí se registra cada mascota <strong>una sola vez</strong>. Después se le puede hacer una hoja de estética, una cita médica, o las dos.</p>
    </div>
    <a href="paciente_nuevo.php" class="boton boton-rosa boton-grande-auto"><span class="emoji">➕</span> Registrar paciente nuevo</a>
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
                <td>👤 <?= e($p['dueno_nombre']) ?><br><small class="suave">📞 <?= e($p['dueno_telefono']) ?></small></td>
                <td><small>✂️ <?= (int)$p['n_hojas'] ?> de estética<br>🩺 <?= (int)$p['n_citas'] ?> cita(s) médica(s)</small></td>
                <td>
                    <div class="acciones">
                        <a href="paciente_detalle.php?id=<?= (int)$p['id'] ?>" class="boton-texto">👁️ Ver ficha</a>
                        <a href="estetica_nueva.php?paciente_id=<?= (int)$p['id'] ?>" class="boton-texto">✂️ Estética</a>
                        <a href="citas.php?paciente_id=<?= (int)$p['id'] ?>#nueva-cita" class="boton-texto">🩺 Cita</a>
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

<?php require_once "includes/footer.php"; ?>
