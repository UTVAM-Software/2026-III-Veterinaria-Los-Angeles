<?php
require_once "includes/auth.php";
require_once "config/conexion.php";

$sexos_validos = ['Macho', 'Hembra'];

// Si viene un id, estamos EDITANDO a un paciente que ya existe
$id = $_GET['id'] ?? ($_POST['id'] ?? '');
$editando = ctype_digit((string)$id);
exigir($editando ? 'pacientes.editar' : 'pacientes.crear');
$actual = null;
if ($editando) {
    $s = $pdo->prepare("SELECT * FROM pacientes WHERE id = ?");
    $s->execute([$id]);
    $actual = $s->fetch();
    if (!$actual) {
        flash('error', "No encontramos a ese paciente.");
        header("Location: pacientes.php");
        exit();
    }
}

// ¿Para qué se está registrando? (así después lo mandamos directo a la hoja o a la cita)
$para = $_GET['para'] ?? ($_POST['para'] ?? '');
if (!in_array($para, ['estetica', 'cita'], true)) { $para = ''; }

$campos = ['nombre', 'raza', 'sexo', 'color', 'edad', 'dueno_nombre', 'dueno_telefono', 'domicilio'];
$v = [];
foreach ($campos as $c) { $v[$c] = $actual[$c] ?? ''; }
if ($v['sexo'] === '') { $v['sexo'] = 'Macho'; }
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errores[] = "La pantalla estuvo abierta mucho tiempo. Vuelve a intentar guardar.";
    }
    foreach ($campos as $c) { $v[$c] = trim($_POST[$c] ?? ''); }

    if ($v['nombre'] === '' || !solo_letras($v['nombre']) || largo($v['nombre']) > 50) {
        $errores[] = "Escribe el nombre de la mascota usando solo letras (máximo 50).";
    }
    if ($v['raza'] !== '' && (!solo_letras($v['raza']) || largo($v['raza']) > 50)) {
        $errores[] = "La raza debe llevar solo letras (máximo 50).";
    }
    if (!in_array($v['sexo'], $sexos_validos, true)) {
        $errores[] = "Elige si la mascota es macho o hembra.";
    }
    if ($v['color'] !== '' && (!solo_letras($v['color']) || largo($v['color']) > 50)) {
        $errores[] = "El color debe llevar solo letras (por ejemplo: Blanco con café).";
    }
    if ($v['edad'] !== '' && (!preg_match('/^[\p{L}\p{N} .,]+$/u', $v['edad']) || largo($v['edad']) > 20)) {
        $errores[] = "La edad se escribe como: 2 años, o 6 meses (máximo 20 letras).";
    }
    if ($v['dueno_nombre'] === '' || !solo_letras($v['dueno_nombre']) || largo($v['dueno_nombre']) > 100) {
        $errores[] = "Escribe el nombre del dueño usando solo letras.";
    }
    if (!preg_match('/^[0-9]{10}$/', $v['dueno_telefono'])) {
        $errores[] = "El teléfono debe tener 10 números, sin espacios ni guiones (ejemplo: 7791234567).";
    }
    if (largo($v['domicilio']) > 200) {
        $errores[] = "El domicilio es muy largo (máximo 200 letras).";
    }

    // Evitar registrar dos veces a la misma mascota (mismo nombre y mismo teléfono)
    if (!$errores) {
        $dup = $pdo->prepare("SELECT id FROM pacientes WHERE nombre = ? AND dueno_telefono = ? AND id <> ? LIMIT 1");
        $dup->execute([$v['nombre'], $v['dueno_telefono'], $editando ? (int)$id : 0]);
        $ya = $dup->fetchColumn();
        if ($ya) {
            $errores[] = $v['nombre'] . " ya está registrado con ese teléfono. Búscalo en la lista de pacientes para no repetirlo.";
        }
    }

    list($foto_nombre, $foto_tmp) = procesar_foto($errores);

    if (!$errores) {
        $nuevo_id = 0;
        try {
            if ($foto_nombre) {
                if (!is_dir('uploads')) { mkdir('uploads', 0755, true); }
                move_uploaded_file($foto_tmp, 'uploads/' . $foto_nombre);
            }
            if ($editando) {
                $stmt = $pdo->prepare("UPDATE pacientes SET nombre=?, raza=?, sexo=?, color=?, edad=?, dueno_nombre=?, dueno_telefono=?, domicilio=?, foto=? WHERE id=?");
                $stmt->execute([$v['nombre'], $v['raza'], $v['sexo'], $v['color'], $v['edad'], $v['dueno_nombre'], $v['dueno_telefono'], $v['domicilio'],
                    $foto_nombre ?: $actual['foto'], $id]);
                if ($foto_nombre && !empty($actual['foto']) && file_exists('uploads/' . basename($actual['foto']))) {
                    unlink('uploads/' . basename($actual['foto']));
                }
                $nuevo_id = (int)$id;
                flash('ok', "Se actualizaron los datos de " . $v['nombre'] . ".");
            } else {
                $stmt = $pdo->prepare("INSERT INTO pacientes (nombre, raza, sexo, color, edad, dueno_nombre, dueno_telefono, domicilio, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$v['nombre'], $v['raza'], $v['sexo'], $v['color'], $v['edad'], $v['dueno_nombre'], $v['dueno_telefono'], $v['domicilio'], $foto_nombre]);
                $nuevo_id = (int)$pdo->lastInsertId();
                flash('ok', "¡Listo! " . $v['nombre'] . " quedó registrado como paciente.");
            }
        } catch (PDOException $ex) {
            if ($foto_nombre && file_exists('uploads/' . $foto_nombre)) { unlink('uploads/' . $foto_nombre); }
            $errores[] = "No se pudo guardar por un problema del sistema. Intenta de nuevo en un momento.";
            $nuevo_id = 0;
        }
        if ($nuevo_id) {
            if (!$editando && $para === 'estetica') { header("Location: estetica_nueva.php?paciente_id=" . $nuevo_id); }
            elseif (!$editando && $para === 'cita') { header("Location: citas.php?paciente_id=" . $nuevo_id . "#nueva-cita"); }
            else { header("Location: paciente_detalle.php?id=" . $nuevo_id); }
            exit();
        }
    }
}

require_once "includes/header.php";
$volver = $editando ? "paciente_detalle.php?id=" . (int)$id : "pacientes.php";
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">🐕</span> <?= $editando ? 'Corregir datos del paciente' : 'Registrar paciente nuevo' ?></h1>
        <p class="subtitulo">Estos datos se escriben una sola vez. Los campos con <strong>*</strong> son obligatorios.</p>
    </div>
    <a href="<?= e($volver) ?>" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver</a>
</div>

<?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert">
        <strong>⚠️ Revisa esto antes de guardar:</strong>
        <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="tarjeta-formulario">
    <form method="POST" enctype="multipart/form-data" class="form-secciones" data-evitar-doble="1">
        <?= csrf_field() ?>
        <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
        <input type="hidden" name="para" value="<?= e($para) ?>">

        <fieldset class="seccion">
            <legend>1️⃣ Datos de la mascota</legend>
            <div class="grid-form">
                <div class="form-grupo">
                    <label for="nombre">🐶 Nombre de la mascota *</label>
                    <input type="text" id="nombre" name="nombre" value="<?= e($v['nombre']) ?>" required maxlength="50" placeholder="Ej. Max"
                           pattern="[A-Za-zñÑáéíóúÁÉÍÓÚüÜ\s.'\-]+" data-vacio="Escribe el nombre de la mascota" data-msg="Aquí solo se pueden escribir letras"
                           class="input-control solo-letras">
                    <small class="ayuda">Solo letras.</small>
                </div>
                <div class="form-grupo">
                    <label for="foto">📸 Foto (opcional)</label>
                    <input type="file" id="foto" name="foto" accept="image/*" class="input-control input-archivo">
                    <small class="ayuda"><?= $editando ? 'Si no eliges otra, se queda la foto actual. ' : '' ?>Máximo 5 MB.</small>
                </div>
                <div class="form-grupo">
                    <label for="raza">✂️ Raza</label>
                    <input type="text" id="raza" name="raza" value="<?= e($v['raza']) ?>" maxlength="50" placeholder="Ej. Poodle"
                           pattern="[A-Za-zñÑáéíóúÁÉÍÓÚüÜ\s.'\-]+" data-msg="Aquí solo se pueden escribir letras" class="input-control solo-letras">
                </div>
                <div class="form-grupo">
                    <label for="sexo">⚥ Sexo</label>
                    <select id="sexo" name="sexo" class="input-control">
                        <?php foreach ($sexos_validos as $s): ?>
                            <option value="<?= e($s) ?>" <?= $v['sexo'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grupo">
                    <label for="color">🎨 Color</label>
                    <input type="text" id="color" name="color" value="<?= e($v['color']) ?>" maxlength="50" placeholder="Ej. Blanco con café"
                           pattern="[A-Za-zñÑáéíóúÁÉÍÓÚüÜ\s.'\-]+" data-msg="Aquí solo se pueden escribir letras" class="input-control solo-letras">
                </div>
                <div class="form-grupo">
                    <label for="edad">🎂 Edad</label>
                    <input type="text" id="edad" name="edad" value="<?= e($v['edad']) ?>" maxlength="20" placeholder="Ej. 2 años o 6 meses" class="input-control">
                </div>
            </div>
        </fieldset>

        <fieldset class="seccion">
            <legend>2️⃣ Datos del dueño</legend>
            <div class="grid-form">
                <div class="form-grupo">
                    <label for="dueno_nombre">👤 Nombre del dueño *</label>
                    <input type="text" id="dueno_nombre" name="dueno_nombre" value="<?= e($v['dueno_nombre']) ?>" required maxlength="100" placeholder="Nombre completo"
                           pattern="[A-Za-zñÑáéíóúÁÉÍÓÚüÜ\s.'\-]+" data-vacio="Escribe el nombre del dueño" data-msg="Aquí solo se pueden escribir letras"
                           class="input-control solo-letras">
                </div>
                <div class="form-grupo">
                    <label for="dueno_telefono">📞 Teléfono *</label>
                    <input type="tel" id="dueno_telefono" name="dueno_telefono" value="<?= e($v['dueno_telefono']) ?>" required inputmode="numeric"
                           maxlength="10" minlength="10" pattern="[0-9]{10}" placeholder="10 números"
                           data-vacio="Escribe el teléfono del dueño" data-msg="El teléfono debe tener exactamente 10 números" class="input-control solo-numeros">
                    <small class="ayuda">10 números seguidos, sin espacios ni guiones.</small>
                </div>
                <div class="form-grupo full-width">
                    <label for="domicilio">🏠 Domicilio</label>
                    <input type="text" id="domicilio" name="domicilio" value="<?= e($v['domicilio']) ?>" maxlength="200" placeholder="Calle, número y colonia" class="input-control">
                </div>
            </div>
        </fieldset>

        <button type="submit" class="boton boton-rosa boton-grande"><span class="emoji">💾</span> <?= $editando ? 'Guardar cambios' : 'Guardar paciente' ?></button>
    </form>
</div>

<?php require_once "includes/footer.php"; ?>
