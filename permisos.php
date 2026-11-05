<?php
require_once "includes/auth.php";
require_once "config/conexion.php";
exigir('permisos.gestionar');

$errores = [];
$nuevo = ['clave' => '', 'nombre' => '', 'modulo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';
    if (!csrf_ok()) {
        flash('error', "La pantalla estuvo abierta mucho tiempo. Intenta otra vez.");
        header("Location: permisos.php");
        exit();
    }

    // ----- Guardar la matriz: qué permisos tiene cada rol -----
    if ($accion === 'guardar_matriz') {
        $roles_edit = $pdo->query("SELECT id FROM roles WHERE protegido = 0")->fetchAll(PDO::FETCH_COLUMN);
        $permisos_ok = $pdo->query("SELECT id FROM permisos")->fetchAll(PDO::FETCH_COLUMN);
        $marcados = (array)($_POST['perm'] ?? []);
        try {
            $pdo->beginTransaction();
            $borrar = $pdo->prepare("DELETE FROM rol_permiso WHERE rol_id = ?");
            $poner = $pdo->prepare("INSERT INTO rol_permiso (rol_id, permiso_id) VALUES (?, ?)");
            foreach ($roles_edit as $rid) {
                $borrar->execute([$rid]);
                foreach ((array)($marcados[$rid] ?? []) as $pid) {
                    if (in_array($pid, $permisos_ok)) { $poner->execute([$rid, $pid]); }
                }
            }
            $pdo->commit();
            flash('ok', "Listo, se guardaron los permisos de los roles.");
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', "No se pudieron guardar los permisos. Intenta de nuevo.");
        }
        header("Location: permisos.php");
        exit();
    }

    // ----- Crear un permiso nuevo -----
    if ($accion === 'crear_permiso') {
        foreach ($nuevo as $c => $_) { $nuevo[$c] = trim($_POST[$c] ?? ''); }
        if (!preg_match('/^[a-z_]{2,25}\.[a-z_]{2,25}$/', $nuevo['clave'])) {
            $errores[] = "La clave se escribe así: modulo.accion, solo con minúsculas (por ejemplo: reportes.ver).";
        }
        if ($nuevo['nombre'] === '' || largo($nuevo['nombre']) > 100) { $errores[] = "Escribe el nombre del permiso (máximo 100 letras)."; }
        if (!preg_match('/^[a-z_]{2,30}$/', $nuevo['modulo'])) { $errores[] = "El módulo va en minúsculas y sin espacios (por ejemplo: reportes)."; }
        if (!$errores) {
            $dup = $pdo->prepare("SELECT COUNT(*) FROM permisos WHERE clave = ?");
            $dup->execute([$nuevo['clave']]);
            if ($dup->fetchColumn() > 0) { $errores[] = "Ya existe un permiso con la clave \"" . $nuevo['clave'] . "\"."; }
        }
        if (!$errores) {
            $pdo->prepare("INSERT INTO permisos (clave, nombre, modulo, sistema) VALUES (?, ?, ?, 0)")->execute([$nuevo['clave'], $nuevo['nombre'], $nuevo['modulo']]);
            flash('ok', "Se creó el permiso " . $nuevo['clave'] . ". Ya puedes marcarlo en la tabla.");
            header("Location: permisos.php");
            exit();
        }
    }

    // ----- Eliminar un permiso creado por nosotros (los del sistema no se tocan) -----
    if ($accion === 'eliminar_permiso') {
        $pid = $_POST['permiso_id'] ?? '';
        $s = ctype_digit((string)$pid) ? $pdo->prepare("SELECT * FROM permisos WHERE id = ?") : null;
        if ($s) { $s->execute([$pid]); $p = $s->fetch(); }
        if (empty($p)) { flash('error', "No se encontró el permiso."); }
        elseif ($p['sistema']) { flash('error', "El permiso " . $p['clave'] . " lo usa el sistema y no se puede eliminar."); }
        else {
            $pdo->prepare("DELETE FROM permisos WHERE id = ?")->execute([$pid]);
            flash('ok', "Se eliminó el permiso " . $p['clave'] . ".");
        }
        header("Location: permisos.php");
        exit();
    }
}

// ----- Datos para dibujar la tabla -----
$roles = $pdo->query("SELECT * FROM roles ORDER BY protegido DESC, nombre ASC")->fetchAll();
$permisos = $pdo->query("SELECT * FROM permisos ORDER BY modulo ASC, id ASC")->fetchAll();
$asignados = [];
foreach ($pdo->query("SELECT rol_id, permiso_id FROM rol_permiso")->fetchAll() as $a) { $asignados[$a['rol_id']][$a['permiso_id']] = true; }

require_once "includes/header.php";
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">🔑</span> Permisos</h1>
        <p class="subtitulo">Marca con una palomita qué puede hacer cada rol. El rol <strong>Administrador</strong> siempre tiene todo.</p>
    </div>
</div>

<?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert">
        <strong>⚠️ Revisa esto antes de guardar:</strong>
        <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="POST" data-evitar-doble="1">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="guardar_matriz">
    <div class="tabla-envoltura">
        <table class="matriz-permisos">
            <thead>
                <tr>
                    <th>Permiso</th>
                    <?php foreach ($roles as $r): ?>
                        <th>🎭 <?= e($r['nombre']) ?><?= $r['protegido'] ? '<br><small>🔒 siempre todo</small>' : '<br><label><small><input type="checkbox" class="marcar-columna" data-rol="' . (int)$r['id'] . '"> marcar todo</small></label>' ?></th>
                    <?php endforeach; ?>
                    <th>Quitar</th>
                </tr>
            </thead>
            <tbody>
            <?php $modulo_actual = ''; foreach ($permisos as $p): ?>
                <?php if ($p['modulo'] !== $modulo_actual): $modulo_actual = $p['modulo']; ?>
                    <tr class="fila-modulo"><td colspan="<?= count($roles) + 2 ?>">📁 <?= e($p['modulo']) ?></td></tr>
                <?php endif; ?>
                <tr>
                    <td><?= e($p['nombre']) ?><br><small><?= e($p['clave']) ?></small></td>
                    <?php foreach ($roles as $r): ?>
                        <td>
                            <?php if ($r['protegido']): ?>
                                <input type="checkbox" checked disabled aria-label="<?= e($r['nombre'] . ': ' . $p['nombre']) ?>">
                            <?php else: ?>
                                <input type="checkbox" name="perm[<?= (int)$r['id'] ?>][]" value="<?= (int)$p['id'] ?>" data-rol="<?= (int)$r['id'] ?>"
                                       <?= !empty($asignados[$r['id']][$p['id']]) ? 'checked' : '' ?> aria-label="<?= e($r['nombre'] . ': ' . $p['nombre']) ?>">
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td>
                        <?php if (!$p['sistema']): ?>
                            <button type="submit" form="form-borrar-<?= (int)$p['id'] ?>" class="boton-texto" style="border:0;background:none;cursor:pointer;color:#8f1d1d;">🗑️</button>
                        <?php else: ?><small>🔒</small><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="submit" class="boton boton-rosa boton-grande" style="margin-top:18px;"><span class="emoji">💾</span> Guardar permisos</button>
</form>

<!-- Formularios de borrar (van fuera de la tabla para no mezclarse con el de guardar) -->
<?php foreach ($permisos as $p): if ($p['sistema']) continue; ?>
    <form method="POST" id="form-borrar-<?= (int)$p['id'] ?>" data-confirmar="¿Eliminar el permiso <?= e($p['clave']) ?>? Se quitará de todos los roles.">
        <?= csrf_field() ?><input type="hidden" name="action" value="eliminar_permiso"><input type="hidden" name="permiso_id" value="<?= (int)$p['id'] ?>">
    </form>
<?php endforeach; ?>

<div class="tarjeta-formulario" style="margin-top:30px;">
    <h3 style="margin-top:0;">➕ Crear un permiso nuevo</h3>
    <p class="ayuda">Un permiso nuevo aparece en la tabla de arriba para marcarlo. Para que realmente bloquee algo, la pantalla debe preguntar por su clave con <code>exigir('clave')</code> o <code>puede('clave')</code>.</p>
    <form method="POST" class="grid-form" data-evitar-doble="1">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="crear_permiso">
        <div class="form-grupo">
            <label for="clave">🔑 Clave *</label>
            <input type="text" id="clave" name="clave" value="<?= e($nuevo['clave']) ?>" required maxlength="51" class="input-control" placeholder="reportes.ver"
                   pattern="[a-z_]{2,25}\.[a-z_]{2,25}" data-vacio="Escribe la clave" data-msg="Formato: modulo.accion, en minúsculas">
        </div>
        <div class="form-grupo">
            <label for="nombre">🏷️ Nombre *</label>
            <input type="text" id="nombre" name="nombre" value="<?= e($nuevo['nombre']) ?>" required maxlength="100" class="input-control" placeholder="Ver reportes" data-vacio="Escribe el nombre">
        </div>
        <div class="form-grupo">
            <label for="modulo">📁 Módulo *</label>
            <input type="text" id="modulo" name="modulo" value="<?= e($nuevo['modulo']) ?>" required maxlength="30" class="input-control" placeholder="reportes"
                   pattern="[a-z_]{2,30}" data-vacio="Escribe el módulo" data-msg="Solo minúsculas, sin espacios">
        </div>
        <div class="form-grupo full-width">
            <button type="submit" class="boton boton-secundario boton-grande"><span class="emoji">➕</span> Crear permiso</button>
        </div>
    </form>
</div>

<?php require_once "includes/footer.php"; ?>
