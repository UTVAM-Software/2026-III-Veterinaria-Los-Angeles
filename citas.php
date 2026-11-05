<?php
require_once "includes/auth.php";
require_once "config/conexion.php";

$errores = [];
$v = ['paciente_id' => '', 'fecha_cita' => '', 'hora_cita' => '', 'motivo' => ''];
// Si venimos desde la ficha del paciente, ya viene elegido
if (isset($_GET['paciente_id']) && ctype_digit($_GET['paciente_id'])) { $v['paciente_id'] = $_GET['paciente_id']; }

// ----- Registrar una cita nueva -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'crear_cita') {
    if (!csrf_ok()) {
        $errores[] = "La pantalla estuvo abierta mucho tiempo. Vuelve a intentar guardar.";
    }
    foreach ($v as $campo => $_) { $v[$campo] = trim($_POST[$campo] ?? ''); }

    // El paciente debe existir de verdad
    $existe = false;
    if (ctype_digit($v['paciente_id'])) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM pacientes WHERE id = ?");
        $chk->execute([$v['paciente_id']]);
        $existe = $chk->fetchColumn() > 0;
    }
    if (!$existe) { $errores[] = "Elige a qué mascota es la cita. Si no está en la lista, regístrala primero."; }

    if (!es_fecha($v['fecha_cita'])) {
        $errores[] = "Elige la fecha de la cita en el calendario.";
    } elseif ($v['fecha_cita'] < date('Y-m-d')) {
        $errores[] = "La fecha de la cita no puede ser un día que ya pasó.";
    }
    if (!hora_valida($v['hora_cita'])) { $errores[] = "Elige la hora de la cita en el reloj."; }
    if (largo($v['motivo']) < 3) {
        $errores[] = "Escribe el motivo de la cita (por ejemplo: vacuna, revisión).";
    } elseif (largo($v['motivo']) > 500) {
        $errores[] = "El motivo es muy largo (máximo 500 letras).";
    }

    if (!$errores) {
        try {
            $stmt = $pdo->prepare("INSERT INTO citas_medicas (paciente_id, fecha_cita, hora_cita, motivo, agendado_por) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$v['paciente_id'], $v['fecha_cita'], $v['hora_cita'], $v['motivo'], $_SESSION['usuario_nombre']]);
            flash('ok', "La cita quedó guardada para el " . fecha_mx($v['fecha_cita']) . " a las " . $v['hora_cita'] . ".");
            header("Location: citas.php");
            exit();
        } catch (PDOException $ex) {
            $errores[] = "No se pudo guardar la cita por un problema del sistema. Intenta de nuevo.";
        }
    }
}

// ----- Guardar diagnóstico (solo el veterinario) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'guardar_diagnostico') {
    if ($_SESSION['rol'] !== 'veterinario') {
        flash('error', "Solo el veterinario puede escribir el diagnóstico.");
    } elseif (!csrf_ok()) {
        flash('error', "La pantalla estuvo abierta mucho tiempo. Intenta guardar otra vez.");
    } else {
        $cita_id = $_POST['cita_id'] ?? '';
        $diagnostico = trim($_POST['diagnostico'] ?? '');
        if (!ctype_digit((string)$cita_id)) {
            flash('error', "No se encontró la cita.");
        } elseif ($diagnostico === '') {
            flash('error', "Escribe el diagnóstico antes de guardar.");
        } elseif (largo($diagnostico) > 2000) {
            flash('error', "El diagnóstico es muy largo (máximo 2000 letras).");
        } else {
            $stmt = $pdo->prepare("UPDATE citas_medicas SET diagnostico = ?, estatus = 'Atendida' WHERE id = ?");
            $stmt->execute([$diagnostico, $cita_id]);
            flash('ok', "El diagnóstico se guardó y la cita quedó como atendida.");
        }
    }
    header("Location: citas.php");
    exit();
}

// ----- Buscar citas (las pendientes salen primero) -----
$busqueda = trim($_GET['busqueda'] ?? '');
$sql = "SELECT c.*, p.nombre AS mascota_nombre, p.dueno_nombre, p.dueno_telefono
        FROM citas_medicas c JOIN pacientes p ON c.paciente_id = p.id ";
if ($busqueda !== '') {
    $stmt = $pdo->prepare($sql . "WHERE p.nombre LIKE ? OR p.dueno_nombre LIKE ? OR c.motivo LIKE ?
                           ORDER BY (c.estatus = 'Atendida'), c.fecha_cita ASC");
    $stmt->execute(["%$busqueda%", "%$busqueda%", "%$busqueda%"]);
} else {
    $stmt = $pdo->query($sql . "ORDER BY (c.estatus = 'Atendida'), c.fecha_cita ASC");
}
$citas = $stmt->fetchAll();

$pacientes_lista = $pdo->query("SELECT id, nombre, dueno_nombre FROM pacientes ORDER BY nombre ASC")->fetchAll();

require_once "includes/header.php";
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_purple.css">

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">🩺</span> Citas médicas</h1>
        <p class="subtitulo">Primero se agenda la cita; el veterinario escribe el diagnóstico cuando atiende.</p>
    </div>
</div>

<?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert">
        <strong>⚠️ Revisa esto antes de guardar:</strong>
        <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<!-- Agendar cita -->
<div class="tarjeta-formulario" id="nueva-cita" style="margin-bottom:30px;">
    <h3 style="margin-top:0;">📅 Agendar una cita nueva</h3>
    <?php if (count($pacientes_lista) === 0): ?>
        <p class="vacio">Para agendar una cita primero hay que <a href="paciente_nuevo.php?para=cita" class="boton-texto">registrar al paciente</a>.</p>
    <?php else: ?>
    <form action="citas.php" method="POST" class="grid-form" data-evitar-doble="1">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="crear_cita">

        <div class="form-grupo full-width">
            <label for="paciente_id">🐶 ¿Para qué mascota? *</label>
            <select id="paciente_id" name="paciente_id" class="input-control" required data-vacio="Elige la mascota de la lista">
                <option value="">Toca aquí para elegir...</option>
                <?php foreach ($pacientes_lista as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= ((string)$v['paciente_id'] === (string)$m['id']) ? 'selected' : '' ?>><?= e($m['nombre'] . ' (dueño: ' . $m['dueno_nombre'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
            <small class="ayuda">Sus datos ya están guardados. ¿No aparece? <a href="paciente_nuevo.php?para=cita" class="boton-texto">Registrar paciente nuevo</a></small>
        </div>
        <div class="form-grupo">
            <label for="fecha_cita">📅 Fecha *</label>
            <input type="date" id="fecha_cita" name="fecha_cita" value="<?= e($v['fecha_cita']) ?>" min="<?= date('Y-m-d') ?>" class="input-control" required
                   data-vacio="Elige la fecha en el calendario" data-msg="Elige un día de hoy en adelante">
        </div>
        <div class="form-grupo">
            <label for="hora_cita">⏰ Hora *</label>
            <input type="text" id="hora_cita" name="hora_cita" value="<?= e($v['hora_cita']) ?>" class="input-control reloj-input" placeholder="Toca para elegir la hora" autocomplete="off" required
                   data-vacio="Elige la hora en el reloj">
        </div>
        <div class="form-grupo full-width">
            <label for="motivo">📋 ¿Por qué viene? *</label>
            <textarea id="motivo" name="motivo" rows="2" maxlength="500" class="input-control" required minlength="3"
                      placeholder="Ej. Vacuna, revisión general, no quiere comer..." data-vacio="Escribe el motivo de la cita"><?= e($v['motivo']) ?></textarea>
        </div>
        <div class="form-grupo full-width">
            <button type="submit" class="boton boton-rosa boton-grande"><span class="emoji">📅</span> Guardar cita</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<!-- Buscador de citas -->
<form method="GET" action="citas.php" class="buscador-form" role="search">
    <input type="text" name="busqueda" value="<?= e($busqueda) ?>" class="input-control" style="flex:1;"
           placeholder="Buscar cita por mascota, dueño o motivo" aria-label="Buscar cita">
    <button type="submit" class="boton boton-secundario"><span class="emoji">🔍</span> Buscar</button>
    <?php if ($busqueda !== ''): ?><a href="citas.php" class="boton boton-secundario">Ver todas</a><?php endif; ?>
</form>

<h3>📋 Citas y expedientes</h3>
<div style="display:grid; gap:20px; margin-top:15px;">
    <?php if (empty($citas)): ?>
        <p class="vacio">
            <?= $busqueda !== '' ? 'No encontramos citas con esa búsqueda.' : 'Todavía no hay citas registradas.' ?>
        </p>
    <?php else: foreach ($citas as $c): $atendida = ($c['estatus'] === 'Atendida'); ?>
        <div class="tarjeta-formulario" style="padding:20px;">
            <h4 style="margin-top:0;">🐾 <a href="paciente_detalle.php?id=<?= (int)$c['paciente_id'] ?>"><?= e($c['mascota_nombre']) ?></a> · Dueño: <?= e($c['dueno_nombre']) ?> (📞 <?= e($c['dueno_telefono']) ?>)</h4>
            <p><strong>Cuándo:</strong> <?= fecha_mx($c['fecha_cita']) ?> a las <?= e($c['hora_cita']) ?></p>
            <p><strong>Motivo:</strong> <?= e($c['motivo']) ?></p>
            <p><strong>Estado:</strong> <span class="etiqueta <?= $atendida ? 'etiqueta-ok' : 'etiqueta-pendiente' ?>"><?= $atendida ? '✅ Atendida' : '⏳ Pendiente' ?></span></p>
            <hr class="separador">
            <h5 style="margin:0 0 8px;">🩺 Diagnóstico del veterinario</h5>
            <?php if ($_SESSION['rol'] === 'veterinario'): ?>
                <form action="citas.php" method="POST" data-evitar-doble="1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="guardar_diagnostico">
                    <input type="hidden" name="cita_id" value="<?= (int)$c['id'] ?>">
                    <textarea name="diagnostico" rows="3" maxlength="2000" class="input-control" required data-vacio="Escribe el diagnóstico antes de guardar"
                              placeholder="Escribe aquí el diagnóstico y el tratamiento..."><?= e($c['diagnostico'] ?? '') ?></textarea>
                    <button type="submit" class="boton boton-rosa" style="margin-top:10px;">💾 Guardar diagnóstico</button>
                </form>
            <?php else: ?>
                <div class="valor-box">
                    <?= !empty($c['diagnostico']) ? nl2br(e($c['diagnostico'])) : '<em>Todavía no hay diagnóstico. Solo el veterinario puede escribirlo.</em>' ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<?php require_once "includes/footer.php"; ?>
