<?php
require_once "includes/auth.php";
require_once "config/conexion.php";
exigir('citas.editar');

$id = $_GET['id'] ?? ($_POST['id'] ?? '');
if (!ctype_digit((string)$id)) { header("Location: citas.php"); exit(); }
$s = $pdo->prepare("SELECT * FROM citas_medicas WHERE id = ?");
$s->execute([$id]);
$actual = $s->fetch();
if (!$actual) {
    flash('error', "No encontramos esa cita. Puede que ya se haya eliminado.");
    header("Location: citas.php");
    exit();
}

$estados = ['Pendiente', 'Atendida'];
$v = ['paciente_id' => (string)$actual['paciente_id'], 'fecha_cita' => $actual['fecha_cita'], 'hora_cita' => $actual['hora_cita'],
      'motivo' => $actual['motivo'], 'estatus' => $actual['estatus']];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $errores[] = "La pantalla estuvo abierta mucho tiempo. Vuelve a intentar guardar."; }
    foreach ($v as $campo => $_) { $v[$campo] = trim($_POST[$campo] ?? ''); }

    $existe = false;
    if (ctype_digit($v['paciente_id'])) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM pacientes WHERE id = ?");
        $chk->execute([$v['paciente_id']]);
        $existe = $chk->fetchColumn() > 0;
    }
    if (!$existe) { $errores[] = "Elige a qué mascota es la cita."; }
    // Al corregir una cita sí se permiten fechas pasadas (por ejemplo, una cita ya atendida)
    if (!es_fecha($v['fecha_cita'])) {
        $errores[] = "Elige la fecha de la cita en el calendario.";
    } elseif ($v['estatus'] === 'Pendiente' && $v['fecha_cita'] < date('Y-m-d')) {
        $errores[] = "Una cita pendiente no puede tener una fecha que ya pasó. Si ya se atendió, cambia el estado a \"Atendida\".";
    }
    if (!hora_valida($v['hora_cita'])) { $errores[] = "Elige la hora de la cita en el reloj."; }
    if (largo($v['motivo']) < 3) { $errores[] = "Escribe el motivo de la cita (por ejemplo: vacuna, revisión)."; }
    elseif (largo($v['motivo']) > 500) { $errores[] = "El motivo es muy largo (máximo 500 letras)."; }
    if (!in_array($v['estatus'], $estados, true)) { $errores[] = "Elige si la cita está pendiente o atendida."; }

    if (!$errores) {
        try {
            $stmt = $pdo->prepare("UPDATE citas_medicas SET paciente_id=?, fecha_cita=?, hora_cita=?, motivo=?, estatus=? WHERE id=?");
            $stmt->execute([$v['paciente_id'], $v['fecha_cita'], $v['hora_cita'], $v['motivo'], $v['estatus'], $id]);
            flash('ok', "Se actualizó la cita del " . fecha_mx($v['fecha_cita']) . ".");
            header("Location: citas.php");
            exit();
        } catch (PDOException $ex) {
            $errores[] = "No se pudo guardar por un problema del sistema. Intenta de nuevo.";
        }
    }
}

$pacientes_lista = $pdo->query("SELECT id, nombre, dueno_nombre FROM pacientes ORDER BY nombre ASC")->fetchAll();
require_once "includes/header.php";
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_purple.css">

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">✏️</span> Editar cita médica</h1>
        <p class="subtitulo">Corrige los datos de la cita. El diagnóstico se escribe en la lista de citas.</p>
    </div>
    <a href="citas.php" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver</a>
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
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <div class="form-grupo full-width">
            <label for="paciente_id">🐶 Mascota *</label>
            <select id="paciente_id" name="paciente_id" class="input-control" required data-vacio="Elige la mascota de la lista">
                <?php foreach ($pacientes_lista as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= ((string)$v['paciente_id'] === (string)$m['id']) ? 'selected' : '' ?>><?= e($m['nombre'] . ' (dueño: ' . $m['dueno_nombre'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-grupo">
            <label for="fecha_cita">📅 Fecha *</label>
            <input type="date" id="fecha_cita" name="fecha_cita" value="<?= e($v['fecha_cita']) ?>" class="input-control" required data-vacio="Elige la fecha en el calendario">
        </div>
        <div class="form-grupo">
            <label for="hora_cita">⏰ Hora *</label>
            <input type="text" id="hora_cita" name="hora_cita" value="<?= e($v['hora_cita']) ?>" class="input-control reloj-input" placeholder="Toca para elegir la hora" autocomplete="off" required data-vacio="Elige la hora en el reloj">
        </div>
        <div class="form-grupo full-width">
            <label for="motivo">📋 Motivo *</label>
            <textarea id="motivo" name="motivo" rows="2" maxlength="500" class="input-control" required minlength="3" data-vacio="Escribe el motivo de la cita"><?= e($v['motivo']) ?></textarea>
        </div>
        <div class="form-grupo">
            <label for="estatus">📌 Estado</label>
            <select id="estatus" name="estatus" class="input-control">
                <?php foreach ($estados as $es): ?>
                    <option value="<?= e($es) ?>" <?= $v['estatus'] === $es ? 'selected' : '' ?>><?= e($es) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-grupo full-width">
            <button type="submit" class="boton boton-rosa boton-grande"><span class="emoji">💾</span> Guardar cambios</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<?php require_once "includes/footer.php"; ?>
