<?php
require_once "includes/auth.php";
require_once "config/conexion.php";
exigir('roles.ver');

// ----- Eliminar rol: no se puede si es protegido o si todavía tiene usuarios -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'eliminar') {
    $rid = $_POST['rol_id'] ?? '';
    if (!puede('roles.eliminar')) {
        flash('error', "No tienes permiso para eliminar roles.");
    } elseif (!csrf_ok()) {
        flash('error', "La pantalla estuvo abierta mucho tiempo. Intenta otra vez.");
    } elseif (!ctype_digit((string)$rid)) {
        flash('error', "No se encontró el rol.");
    } else {
        $s = $pdo->prepare("SELECT r.*, (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id) AS n_usuarios FROM roles r WHERE r.id = ?");
        $s->execute([$rid]);
        $r = $s->fetch();
        if (!$r) {
            flash('error', "No se encontró el rol.");
        } elseif ($r['protegido']) {
            flash('error', "El rol " . $r['nombre'] . " está protegido y no se puede eliminar.");
        } elseif ($r['n_usuarios'] > 0) {
            flash('error', "No se puede eliminar " . $r['nombre'] . " porque tiene " . $r['n_usuarios'] . " usuario(s). Cámbiales el rol primero.");
        } else {
            $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$rid]);
            flash('ok', "Se eliminó el rol " . $r['nombre'] . ".");
        }
    }
    header("Location: roles.php");
    exit();
}

$total_permisos = (int)$pdo->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
$total = (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
list($pagina, $paginas, $desde, $por_pagina) = paginar($total);
$roles = $pdo->query("SELECT r.*,
        (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id) AS n_usuarios,
        (SELECT COUNT(*) FROM rol_permiso rp WHERE rp.rol_id = r.id) AS n_permisos
        FROM roles r ORDER BY r.protegido DESC, r.nombre ASC LIMIT $por_pagina OFFSET $desde")->fetchAll();

require_once "includes/header.php";
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">🎭</span> Roles</h1>
        <p class="subtitulo">Un rol es un "llavero": junta los permisos que tendrá cada tipo de usuario.</p>
    </div>
    <?php if (puede('roles.crear')): ?>
    <a href="rol_form.php" class="boton boton-rosa boton-grande-auto"><span class="emoji">➕</span> Crear rol</a>
    <?php endif; ?>
</div>

<div class="tabla-envoltura">
    <table class="tabla">
        <thead><tr><th>Rol</th><th>Descripción</th><th>Usuarios</th><th>Permisos</th><th>¿Qué quieres hacer?</th></tr></thead>
        <tbody>
        <?php foreach ($roles as $r): ?>
            <tr>
                <td><strong>🎭 <?= e($r['nombre']) ?></strong><?= $r['protegido'] ? '<br><small class="suave">🔒 Protegido</small>' : '' ?></td>
                <td><?= e($r['descripcion'] ?: '—') ?></td>
                <td><?= (int)$r['n_usuarios'] ?></td>
                <td><?= $r['protegido'] ? 'Todos' : (int)$r['n_permisos'] . ' de ' . $total_permisos ?></td>
                <td>
                    <div class="acciones">
                        <?php if (puede('roles.editar')): ?><a href="rol_form.php?id=<?= (int)$r['id'] ?>" class="boton-texto">✏️ Editar</a><?php endif; ?>
                        <?php if (puede('permisos.gestionar') && !$r['protegido']): ?><a href="permisos.php" class="boton-texto">🔑 Permisos</a><?php endif; ?>
                        <?php if (puede('roles.eliminar') && !$r['protegido']): ?>
                        <form method="POST" style="margin:0;" data-confirmar="¿Seguro que quieres eliminar el rol <?= e($r['nombre']) ?>? Esto no se puede deshacer.">
                            <?= csrf_field() ?><input type="hidden" name="action" value="eliminar"><input type="hidden" name="rol_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="boton-texto" style="border:0;background:none;cursor:pointer;color:#8f1d1d;">🗑️ Eliminar</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php mostrar_paginacion($pagina, $paginas); ?>

<?php require_once "includes/footer.php"; ?>
