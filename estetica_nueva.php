<?php
require_once "includes/auth.php";
require_once "config/conexion.php";

$servicios_validos = ['Baño', 'Deslanado', 'Corte', 'Limpieza de oídos', 'Corte de uñas'];
$pagos_validos = ['Efectivo', 'Tarjeta', 'Transferencia', 'Otro'];

// El folio se asigna solo: el último número + 1 (ej. 0735 -> 0736)
function siguiente_folio($pdo) {
    $ultimo = (int)$pdo->query("SELECT MAX(CAST(folio AS UNSIGNED)) FROM hojas_estetica")->fetchColumn();
    return str_pad($ultimo + 1, 4, '0', STR_PAD_LEFT);
}

$v = [
    'paciente_id' => '', 'fecha' => date('Y-m-d'), 'hora_llegada' => '', 'hora_salida' => '',
    'detalles_corte' => '', 'observaciones' => '', 'costo_total' => '', 'a_cuenta' => '',
    'forma_pago' => 'Efectivo', 'servicios' => []
];
// Si venimos desde la ficha del paciente, ya viene elegido
if (isset($_GET['paciente_id']) && ctype_digit($_GET['paciente_id'])) { $v['paciente_id'] = $_GET['paciente_id']; }
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errores[] = "La pantalla estuvo abierta mucho tiempo. Vuelve a intentar guardar.";
    }
    foreach ($v as $campo => $_) {
        if ($campo === 'servicios') continue;
        $v[$campo] = trim($_POST[$campo] ?? '');
    }
    $v['servicios'] = array_values(array_intersect($servicios_validos, (array)($_POST['servicios'] ?? [])));
    $v['costo_total'] = str_replace(',', '.', $v['costo_total']);
    $v['a_cuenta'] = str_replace(',', '.', $v['a_cuenta']);

    // El paciente debe existir
    $paciente = null;
    if (ctype_digit($v['paciente_id'])) {
        $chk = $pdo->prepare("SELECT id, nombre FROM pacientes WHERE id = ?");
        $chk->execute([$v['paciente_id']]);
        $paciente = $chk->fetch();
    }
    if (!$paciente) { $errores[] = "Elige a qué mascota es la hoja. Si no está en la lista, regístrala primero."; }

    if (!es_fecha($v['fecha'])) { $errores[] = "La fecha no es válida. Elígela del calendario."; }
    if ($v['hora_llegada'] !== '' && !hora_valida($v['hora_llegada'])) { $errores[] = "La hora de llegada no es válida. Elígela del reloj."; }
    if ($v['hora_salida'] !== '' && !hora_valida($v['hora_salida'])) { $errores[] = "La hora de salida no es válida. Elígela del reloj."; }
    if (hora_valida($v['hora_llegada']) && hora_valida($v['hora_salida'])
        && strtotime($v['hora_salida']) <= strtotime($v['hora_llegada'])) {
        $errores[] = "La hora de salida debe ser después de la hora de llegada.";
    }
    if (largo($v['detalles_corte']) > 200) { $errores[] = "Los detalles de corte son muy largos (máximo 200 letras)."; }
    if (largo($v['observaciones']) > 500) { $errores[] = "Las observaciones son muy largas (máximo 500 letras)."; }
    if (!in_array($v['forma_pago'], $pagos_validos, true)) { $errores[] = "Elige una forma de pago de la lista."; }

    $costo = ($v['costo_total'] === '') ? 0 : $v['costo_total'];
    $cuenta = ($v['a_cuenta'] === '') ? 0 : $v['a_cuenta'];
    if (!is_numeric($costo) || $costo < 0 || $costo > 999999.99) {
        $errores[] = "El costo total debe ser un número, por ejemplo 350 o 350.50.";
    } elseif (!is_numeric($cuenta) || $cuenta < 0 || $cuenta > 999999.99) {
        $errores[] = "El anticipo debe ser un número, por ejemplo 100.";
    } elseif ($cuenta > $costo) {
        $errores[] = "El anticipo no puede ser mayor que el costo total.";
    }

    if (!$errores) {
        try {
            $folio = siguiente_folio($pdo);
            $stmt = $pdo->prepare("INSERT INTO hojas_estetica (folio, paciente_id, fecha, hora_llegada, hora_salida, servicios, detalles_corte, observaciones, costo_total, a_cuenta, forma_pago)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$folio, $paciente['id'], $v['fecha'], $v['hora_llegada'], $v['hora_salida'], implode(', ', $v['servicios']),
                $v['detalles_corte'], $v['observaciones'], $costo, $cuenta, $v['forma_pago']]);
            flash('ok', "¡Listo! Se guardó la hoja de " . $paciente['nombre'] . " con el folio #" . $folio . ".");
            header("Location: estetica.php");
            exit();
        } catch (PDOException $ex) {
            $errores[] = "No se pudo guardar por un problema del sistema. Intenta de nuevo en un momento.";
        }
    }
}

$pacientes_lista = $pdo->query("SELECT id, nombre, dueno_nombre FROM pacientes ORDER BY nombre ASC")->fetchAll();
$folio_mostrar = siguiente_folio($pdo);
require_once "includes/header.php";
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_purple.css">

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">📝</span> Hoja de estética</h1>
        <p class="subtitulo">Elige a la mascota y llena lo que se le va a hacer hoy. Los campos con <strong>*</strong> son obligatorios.</p>
    </div>
    <a href="estetica.php" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver a la lista</a>
</div>

<?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert">
        <strong>⚠️ Revisa esto antes de guardar:</strong>
        <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="tarjeta-formulario">
<?php if (count($pacientes_lista) === 0): ?>
    <p class="vacio">Para hacer una hoja primero hay que <a href="paciente_nuevo.php?para=estetica" class="boton-texto">registrar al paciente</a>.</p>
<?php else: ?>
    <form method="POST" class="form-secciones" data-evitar-doble="1">
        <?= csrf_field() ?>

        <fieldset class="seccion">
            <legend>1️⃣ ¿Para quién es?</legend>
            <div class="grid-form">
                <div class="form-grupo full-width">
                    <label for="paciente_id">🐶 Mascota *</label>
                    <select id="paciente_id" name="paciente_id" class="input-control" required data-vacio="Elige la mascota de la lista">
                        <option value="">Toca aquí para elegir...</option>
                        <?php foreach ($pacientes_lista as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= ((string)$v['paciente_id'] === (string)$p['id']) ? 'selected' : '' ?>><?= e($p['nombre'] . ' (dueño: ' . $p['dueno_nombre'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="ayuda">Sus datos y los del dueño ya están guardados. ¿No aparece? <a href="paciente_nuevo.php?para=estetica" class="boton-texto">Registrar paciente nuevo</a></small>
                </div>
                <div class="form-grupo">
                    <label for="folio">🏷️ Folio</label>
                    <input type="text" id="folio" value="<?= e($folio_mostrar) ?>" class="input-control" readonly>
                    <small class="ayuda">El número se pone solo.</small>
                </div>
                <div class="form-grupo">
                    <label for="fecha">📅 Fecha *</label>
                    <input type="date" id="fecha" name="fecha" value="<?= e($v['fecha']) ?>" class="input-control" required data-vacio="Elige la fecha en el calendario">
                </div>
                <div class="form-grupo">
                    <label for="hora_llegada">⏰ Hora de llegada</label>
                    <input type="text" id="hora_llegada" name="hora_llegada" value="<?= e($v['hora_llegada']) ?>" class="input-control reloj-input" placeholder="Toca para elegir la hora" autocomplete="off">
                </div>
                <div class="form-grupo">
                    <label for="hora_salida">⏰ Hora aproximada de salida</label>
                    <input type="text" id="hora_salida" name="hora_salida" value="<?= e($v['hora_salida']) ?>" class="input-control reloj-input" placeholder="Toca para elegir la hora" autocomplete="off">
                </div>
            </div>
        </fieldset>

        <fieldset class="seccion">
            <legend>2️⃣ ¿Qué se le va a hacer?</legend>
            <p class="ayuda">Toca los cuadritos de lo que pidió el dueño.</p>
            <div class="servicios-lista">
                <?php foreach ($servicios_validos as $sv): ?>
                    <label class="servicio-opcion">
                        <input type="checkbox" name="servicios[]" value="<?= e($sv) ?>" <?= in_array($sv, $v['servicios'], true) ? 'checked' : '' ?>>
                        <span><?= e($sv) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="grid-form" style="margin-top:16px;">
                <div class="form-grupo full-width">
                    <label for="detalles_corte">✂️ Cómo quiere el corte</label>
                    <input type="text" id="detalles_corte" name="detalles_corte" value="<?= e($v['detalles_corte']) ?>" maxlength="200" placeholder="Ej. Navaja #2, tijera en la cara" class="input-control">
                </div>
                <div class="form-grupo full-width">
                    <label for="observaciones">📋 Observaciones</label>
                    <textarea id="observaciones" name="observaciones" rows="2" maxlength="500" class="input-control" placeholder="Ej. Se pone nervioso, es alérgico a algún shampoo..."><?= e($v['observaciones']) ?></textarea>
                </div>
            </div>
        </fieldset>

        <fieldset class="seccion">
            <legend>3️⃣ Cobro</legend>
            <div class="grid-form">
                <div class="form-grupo">
                    <label for="costo_total">💲 Costo total ($)</label>
                    <input type="number" id="costo_total" name="costo_total" value="<?= e($v['costo_total']) ?>" step="0.01" min="0" max="999999.99" inputmode="decimal" placeholder="0.00"
                           data-msg="Escribe solo números. Ejemplo: 350 o 350.50" class="input-control">
                </div>
                <div class="form-grupo">
                    <label for="a_cuenta">💵 Anticipo ($)</label>
                    <input type="number" id="a_cuenta" name="a_cuenta" value="<?= e($v['a_cuenta']) ?>" step="0.01" min="0" max="999999.99" inputmode="decimal" placeholder="0.00"
                           data-msg="Escribe solo números. Ejemplo: 100" class="input-control">
                    <small class="ayuda" id="resta-pago"></small>
                </div>
                <div class="form-grupo full-width">
                    <label for="forma_pago">💳 Forma de pago</label>
                    <select id="forma_pago" name="forma_pago" class="input-control">
                        <?php foreach ($pagos_validos as $pg): ?>
                            <option value="<?= e($pg) ?>" <?= $v['forma_pago'] === $pg ? 'selected' : '' ?>><?= e($pg) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <button type="submit" class="boton boton-rosa boton-grande"><span class="emoji">💾</span> Guardar hoja de estética</button>
    </form>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<?php require_once "includes/footer.php"; ?>
