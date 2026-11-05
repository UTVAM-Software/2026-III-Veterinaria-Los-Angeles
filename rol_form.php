<?php
require_once "includes/auth.php";
require_once "config/conexion.php";

$id = $_GET['id'] ?? ($_POST['id'] ?? '');
$editando = ctype_digit((string)$id);
exigir($editando ? 'roles.editar' : 'roles.crear');

$actual = null;
if ($editando) {
    $s = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $s->execute([$id]);
    $actual = $s->fetch();
    if (!$actual) {
        flash('error', "No encontramos ese rol.");
        header("Location: roles.php");
        exit();
    }
}

$v = ['nombre' => $actual['nombre'] ?? '', 'descripcion' => $actual['descripcion'] ?? ''];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $errores[] = "La pantalla estuvo abierta mucho tiempo. Vuelve a intentar guardar."; }
    $v['nombre'] = trim($_POST['nombre'] ?? '');
    $v['descripcion'] = trim($_POST['descripcion'] ?? '');

    if ($v['nombre'] === '' || !solo_letras($v['nombre']) || largo($v['nombre']) > 50) {
        $errores[] = "Escribe el nombre del rol usando solo letras (máximo 50). Ejemplo: Recepcionista.";
    }
    if (largo($v['descripcion']) > 200) { $errores[] = "La descripción es muy larga (máximo 200 letras)."; }
    if ($actual && $actual['protegido'] && $v['nombre'] !== $actual['nombre']) { $errores[] = "El rol protegido no puede cambiar de nombre."; }

    if (!$errores) {
        $dup = $pdo->prepare("SELECT id FROM roles WHERE nombre = ? AND id <> ? LIMIT 1");
        $dup->execute([$v['nombre'], $editando ? (int)$id : 0]);
        if ($dup->fetchColumn()) { $errores[] = "Ya existe un rol llamado \"" . $v['nombre'] . "\"."; }
    }

    if (!$errores) {
        try {
            if ($editando) {
                $pdo->prepare("UPDATE roles SET nombre=?, descripcion=? WHERE id=?")->execute([$v['nombre'], $v['descripcion'], $id]);
                flash('ok', "Se actualizó el rol " . $v['nombre'] . ".");
                header("Location: roles.php");
            } else {
                $pdo->prepare("INSERT INTO roles (nombre, descripcion) VALUES (?, ?)")->execute([$v['nombre'], $v['descripcion']]);
                flash('ok', "Se creó el rol " . $v['nombre'] . ". Ahora márcale sus permisos.");
                header("Location: permisos.php");
            }
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
        <h1><span class="emoji">🎭</span> <?= $editando ? 'Editar rol' : 'Crear rol' ?></h1>
        <p class="subtitulo">Después de crearlo, en <strong>Permisos</strong> eliges qué puede hacer.</p>
    </div>
    <a href="roles.php" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver</a>
</div>

<?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert">
        <strong>⚠️ Revisa esto antes de guardar:</strong>
        <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="tarjeta-formulario">
    <form method="POST" class="grid-form" data-evitar-doble="1">
        <?= csrf_field() ?>
        <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
        <div class="form-grupo">
            <label for="nombre">🎭 Nombre del rol *</label>
            <input type="text" id="nombre" name="nombre" value="<?= e($v['nombre']) ?>" required maxlength="50" class="input-control solo-letras"
                   <?= ($actual && $actual['protegido']) ? 'readonly' : '' ?> pattern="[A-Za-zñÑáéíóúÁÉÍÓÚüÜ\s.'\-]+" data-vacio="Escribe el nombre del rol" data-msg="Aquí solo se pueden escribir letras">
        </div>
        <div class="form-grupo full-width">
            <label for="descripcion">📋 Descripción</label>
            <input type="text" id="descripcion" name="descripcion" value="<?= e($v['descripcion']) ?>" maxlength="200" class="input-control" placeholder="Ej. Atiende la recepción y agenda citas">
        </div>
        <div class="form-grupo full-width">
            <button type="submit" class="boton boton-rosa boton-grande"><span class="emoji">💾</span> <?= $editando ? 'Guardar cambios' : 'Crear rol' ?></button>
        </div>
    </form>
</div>

<?php require_once "includes/footer.php"; ?>
