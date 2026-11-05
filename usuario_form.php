<?php
require_once "includes/auth.php";
require_once "config/conexion.php";

$id = $_GET['id'] ?? ($_POST['id'] ?? '');
$editando = ctype_digit((string)$id);
exigir($editando ? 'usuarios.editar' : 'usuarios.crear');

$actual = null;
if ($editando) {
    $s = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $s->execute([$id]);
    $actual = $s->fetch();
    if (!$actual) {
        flash('error', "No encontramos a ese usuario.");
        header("Location: usuarios.php");
        exit();
    }
}

$roles = $pdo->query("SELECT id, nombre FROM roles ORDER BY nombre ASC")->fetchAll();
$v = ['nombre' => $actual['nombre'] ?? '', 'usuario' => $actual['usuario'] ?? '', 'rol_id' => (string)($actual['rol_id'] ?? ''), 'activo' => (string)($actual['activo'] ?? '1')];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $errores[] = "La pantalla estuvo abierta mucho tiempo. Vuelve a intentar guardar."; }
    $v['nombre'] = trim($_POST['nombre'] ?? '');
    $v['usuario'] = trim($_POST['usuario'] ?? '');
    $v['rol_id'] = trim($_POST['rol_id'] ?? '');
    $v['activo'] = (($_POST['activo'] ?? '1') === '1') ? '1' : '0';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $soy_yo = $editando && ((int)$id === (int)$_SESSION['usuario_id']);

    if ($v['nombre'] === '' || !solo_letras($v['nombre']) || largo($v['nombre']) > 100) {
        $errores[] = "Escribe el nombre completo usando solo letras (máximo 100).";
    }
    if (!preg_match('/^[A-Za-z0-9._]{4,50}$/', $v['usuario'])) {
        $errores[] = "El usuario debe tener de 4 a 50 caracteres: letras sin acentos, números, punto o guion bajo.";
    }
    $rol_ok = false;
    if (ctype_digit($v['rol_id'])) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE id = ?");
        $chk->execute([$v['rol_id']]);
        $rol_ok = $chk->fetchColumn() > 0;
    }
    if (!$rol_ok) { $errores[] = "Elige un rol de la lista."; }
    if ($soy_yo && $v['activo'] !== '1') { $errores[] = "No puedes desactivar tu propio usuario."; }
    if ($soy_yo && $rol_ok && (int)$v['rol_id'] !== (int)$actual['rol_id']) { $errores[] = "No puedes cambiar tu propio rol (podrías quedarte sin acceso). Pídeselo a otro administrador."; }

    // Contraseña: obligatoria al crear; al editar, si se deja vacía no cambia
    if (!$editando || $password !== '') {
        if (strlen($password) < 6 || strlen($password) > 72) { $errores[] = "La contraseña debe tener de 6 a 72 caracteres."; }
        elseif ($password !== $password2) { $errores[] = "Las dos contraseñas no coinciden."; }
    }

    if (!$errores) {
        $dup = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id <> ? LIMIT 1");
        $dup->execute([$v['usuario'], $editando ? (int)$id : 0]);
        if ($dup->fetchColumn()) { $errores[] = "El usuario \"" . $v['usuario'] . "\" ya existe. Elige otro."; }
    }

    if (!$errores) {
        try {
            if ($editando) {
                if ($password !== '') {
                    $pdo->prepare("UPDATE usuarios SET nombre=?, usuario=?, rol_id=?, activo=?, password=? WHERE id=?")
                        ->execute([$v['nombre'], $v['usuario'], $v['rol_id'], $v['activo'], password_hash($password, PASSWORD_BCRYPT), $id]);
                } else {
                    $pdo->prepare("UPDATE usuarios SET nombre=?, usuario=?, rol_id=?, activo=? WHERE id=?")
                        ->execute([$v['nombre'], $v['usuario'], $v['rol_id'], $v['activo'], $id]);
                }
                flash('ok', "Se actualizó al usuario " . $v['usuario'] . ".");
            } else {
                $pdo->prepare("INSERT INTO usuarios (nombre, usuario, rol_id, activo, password) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$v['nombre'], $v['usuario'], $v['rol_id'], $v['activo'], password_hash($password, PASSWORD_BCRYPT)]);
                flash('ok', "¡Listo! Se creó al usuario " . $v['usuario'] . ".");
            }
            header("Location: usuarios.php");
            exit();
        } catch (PDOException $ex) {
            $errores[] = "No se pudo guardar por un problema del sistema. Intenta de nuevo.";
        }
    }
}

require_once "includes/header.php";
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">👥</span> <?= $editando ? 'Editar usuario' : 'Crear usuario' ?></h1>
        <p class="subtitulo">Los campos con <strong>*</strong> son obligatorios.</p>
    </div>
    <a href="usuarios.php" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver</a>
</div>

<?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert">
        <strong>⚠️ Revisa esto antes de guardar:</strong>
        <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="tarjeta-formulario">
    <form method="POST" class="grid-form" data-evitar-doble="1" autocomplete="off">
        <?= csrf_field() ?>
        <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
        <div class="form-grupo">
            <label for="nombre">👤 Nombre completo *</label>
            <input type="text" id="nombre" name="nombre" value="<?= e($v['nombre']) ?>" required maxlength="100" class="input-control solo-letras"
                   pattern="[A-Za-zñÑáéíóúÁÉÍÓÚüÜ\s.'\-]+" data-vacio="Escribe el nombre completo" data-msg="Aquí solo se pueden escribir letras">
        </div>
        <div class="form-grupo">
            <label for="usuario">🔑 Usuario *</label>
            <input type="text" id="usuario" name="usuario" value="<?= e($v['usuario']) ?>" required minlength="4" maxlength="50" class="input-control"
                   pattern="[A-Za-z0-9._]{4,50}" data-vacio="Escribe el nombre de usuario" data-msg="De 4 a 50 caracteres: letras, números, punto o guion bajo">
            <small class="ayuda">Con este nombre entra al sistema. Sin espacios ni acentos.</small>
        </div>
        <div class="form-grupo">
            <label for="rol_id">🎭 Rol *</label>
            <select id="rol_id" name="rol_id" class="input-control" required data-vacio="Elige un rol de la lista">
                <option value="">Toca aquí para elegir...</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= (string)$v['rol_id'] === (string)$r['id'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-grupo">
            <label for="activo">📌 Estado</label>
            <select id="activo" name="activo" class="input-control">
                <option value="1" <?= $v['activo'] === '1' ? 'selected' : '' ?>>✅ Activo (puede entrar)</option>
                <option value="0" <?= $v['activo'] === '0' ? 'selected' : '' ?>>⛔ Desactivado (no puede entrar)</option>
            </select>
        </div>
        <div class="form-grupo">
            <label for="password">🔒 Contraseña <?= $editando ? '' : '*' ?></label>
            <input type="password" id="password" name="password" class="input-control" minlength="6" maxlength="72" autocomplete="new-password"
                   <?= $editando ? '' : 'required' ?> data-vacio="Escribe una contraseña" data-msg="Mínimo 6 caracteres">
            <small class="ayuda"><?= $editando ? 'Déjala vacía si no quieres cambiarla. ' : '' ?>Mínimo 6 caracteres. Se guarda encriptada (BCRYPT).</small>
        </div>
        <div class="form-grupo">
            <label for="password2">🔒 Repite la contraseña <?= $editando ? '' : '*' ?></label>
            <input type="password" id="password2" name="password2" class="input-control" minlength="6" maxlength="72" autocomplete="new-password"
                   <?= $editando ? '' : 'required' ?> data-vacio="Repite la contraseña" data-msg="Mínimo 6 caracteres">
        </div>
        <div class="form-grupo full-width">
            <button type="submit" class="boton boton-rosa boton-grande"><span class="emoji">💾</span> <?= $editando ? 'Guardar cambios' : 'Crear usuario' ?></button>
        </div>
    </form>
</div>

<?php require_once "includes/footer.php"; ?>
