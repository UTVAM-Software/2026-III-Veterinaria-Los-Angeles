<?php
require_once "includes/auth.php";
require_once "config/conexion.php";
exigir('usuarios.ver');

// ----- Acciones: activar/desactivar y eliminar -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';
    $uid = $_POST['usuario_id'] ?? '';
    $permiso = ($accion === 'eliminar') ? 'usuarios.eliminar' : 'usuarios.editar';
    if (!puede($permiso)) {
        flash('error', "No tienes permiso para hacer esto.");
    } elseif (!csrf_ok()) {
        flash('error', "La pantalla estuvo abierta mucho tiempo. Intenta otra vez.");
    } elseif (!ctype_digit((string)$uid) || !in_array($accion, ['eliminar', 'alternar'], true)) {
        flash('error', "No se encontró al usuario.");
    } elseif ((int)$uid === (int)$_SESSION['usuario_id']) {
        flash('error', "No puedes desactivar ni eliminar tu propio usuario (te quedarías sin acceso).");
    } else {
        if ($accion === 'eliminar') {
            $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$uid]);
            flash('ok', "Se eliminó al usuario.");
        } else {
            $pdo->prepare("UPDATE usuarios SET activo = 1 - activo WHERE id = ?")->execute([$uid]);
            flash('ok', "Se cambió el estado del usuario.");
        }
    }
    header("Location: usuarios.php");
    exit();
}

// ----- Lista con búsqueda y páginas -----
$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE u.nombre LIKE ? OR u.usuario LIKE ? OR r.nombre LIKE ? ";
    $params = ["%$q%", "%$q%", "%$q%"];
}
$total = $pdo->prepare("SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id = u.rol_id " . $where);
$total->execute($params);
list($pagina, $paginas, $desde, $por_pagina) = paginar((int)$total->fetchColumn());
$stmt = $pdo->prepare("SELECT u.*, r.nombre AS rol_nombre FROM usuarios u JOIN roles r ON r.id = u.rol_id " . $where . "ORDER BY u.nombre ASC LIMIT $por_pagina OFFSET $desde");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

require_once "includes/header.php";
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">👥</span> Usuarios</h1>
        <p class="subtitulo">Las personas que pueden entrar al sistema. Cada una tiene un <strong>rol</strong> que decide qué puede hacer.</p>
    </div>
    <?php if (puede('usuarios.crear')): ?>
    <a href="usuario_form.php" class="boton boton-rosa boton-grande-auto"><span class="emoji">➕</span> Crear usuario</a>
    <?php endif; ?>
</div>

<form method="GET" class="buscador-form" role="search">
    <input type="text" name="q" value="<?= e($q) ?>" class="input-control" style="flex:1;" placeholder="Buscar por nombre, usuario o rol" aria-label="Buscar usuario">
    <button type="submit" class="boton boton-secundario"><span class="emoji">🔍</span> Buscar</button>
    <?php if ($q !== ''): ?><a href="usuarios.php" class="boton boton-secundario">Ver todos</a><?php endif; ?>
</form>

<div class="tabla-envoltura">
    <table class="tabla">
        <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>¿Qué quieres hacer?</th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u): $soy_yo = ((int)$u['id'] === (int)$_SESSION['usuario_id']); ?>
            <tr>
                <td>👤 <?= e($u['nombre']) ?><?= $soy_yo ? ' <small class="suave">(tú)</small>' : '' ?></td>
                <td><?= e($u['usuario']) ?></td>
                <td>🎭 <?= e($u['rol_nombre']) ?></td>
                <td><span class="etiqueta <?= $u['activo'] ? 'etiqueta-ok' : 'etiqueta-gris' ?>"><?= $u['activo'] ? '✅ Activo' : '⛔ Desactivado' ?></span></td>
                <td>
                    <div class="acciones">
                        <?php if (puede('usuarios.editar')): ?><a href="usuario_form.php?id=<?= (int)$u['id'] ?>" class="boton-texto">✏️ Editar</a><?php endif; ?>
                        <?php if (puede('usuarios.editar') && !$soy_yo): ?>
                        <form method="POST" style="margin:0;" data-confirmar="¿<?= $u['activo'] ? 'Desactivar' : 'Activar' ?> a <?= e($u['nombre']) ?>?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="alternar"><input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="boton-texto" style="border:0;background:none;cursor:pointer;"><?= $u['activo'] ? '⛔ Desactivar' : '✅ Activar' ?></button>
                        </form>
                        <?php endif; ?>
                        <?php if (puede('usuarios.eliminar') && !$soy_yo): ?>
                        <form method="POST" style="margin:0;" data-confirmar="¿Seguro que quieres eliminar a <?= e($u['nombre']) ?>? Esto no se puede deshacer.">
                            <?= csrf_field() ?><input type="hidden" name="action" value="eliminar"><input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="boton-texto" style="border:0;background:none;cursor:pointer;color:#8f1d1d;">🗑️ Eliminar</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (count($usuarios) === 0): ?>
            <tr><td colspan="5" class="vacio">No encontramos usuarios<?= $q !== '' ? ' con esa búsqueda' : '' ?>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php mostrar_paginacion($pagina, $paginas); ?>

<?php require_once "includes/footer.php"; ?>
