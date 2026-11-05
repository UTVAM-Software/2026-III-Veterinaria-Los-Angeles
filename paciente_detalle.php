<?php
require_once "includes/auth.php";
require_once "config/conexion.php";

$id = $_GET['id'] ?? ($_POST['id'] ?? '');
if (!ctype_digit((string)$id)) {
    header("Location: pacientes.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM pacientes WHERE id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) {
    flash('error', "No encontramos a ese paciente. Puede que ya se haya eliminado.");
    header("Location: pacientes.php");
    exit();
}

$hojas = $pdo->prepare("SELECT * FROM hojas_estetica WHERE paciente_id = ? ORDER BY fecha DESC, id DESC");
$hojas->execute([$id]);
$hojas = $hojas->fetchAll();

$citas = $pdo->prepare("SELECT * FROM citas_medicas WHERE paciente_id = ? ORDER BY fecha_cita DESC, id DESC");
$citas->execute([$id]);
$citas = $citas->fetchAll();

// Eliminar paciente: solo si no tiene historial (así nunca se pierde un expediente por error)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'eliminar') {
    if (!csrf_ok()) {
        flash('error', "La pantalla estuvo abierta mucho tiempo. Intenta otra vez.");
    } elseif (count($hojas) > 0 || count($citas) > 0) {
        flash('error', $p['nombre'] . " no se puede eliminar porque tiene historial (" . count($hojas) . " hoja(s) de estética y " . count($citas) . " cita(s) médica(s)). Esto protege los expedientes.");
    } else {
        try {
            $pdo->prepare("DELETE FROM pacientes WHERE id = ?")->execute([$id]);
            if (!empty($p['foto']) && file_exists('uploads/' . basename($p['foto']))) { unlink('uploads/' . basename($p['foto'])); }
            flash('ok', "Se eliminó a " . $p['nombre'] . " de la lista de pacientes.");
            header("Location: pacientes.php");
            exit();
        } catch (PDOException $ex) {
            flash('error', "No se pudo eliminar. Intenta de nuevo.");
        }
    }
    header("Location: paciente_detalle.php?id=" . (int)$id);
    exit();
}

require_once "includes/header.php";
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">📋</span> <?= e($p['nombre']) ?></h1>
        <p class="subtitulo">Ficha del paciente</p>
    </div>
    <div class="acciones">
        <a href="pacientes.php" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver</a>
        <a href="paciente_nuevo.php?id=<?= (int)$p['id'] ?>" class="boton boton-secundario"><span class="emoji">✏️</span> Corregir datos</a>
    </div>
</div>

<div class="tarjeta-detalle" style="display:flex; gap:30px; align-items:flex-start; flex-wrap:wrap; margin-bottom:24px;">
    <div class="foto-caja">
        <?php if (!empty($p['foto']) && file_exists('uploads/' . basename($p['foto']))): ?>
            <img src="uploads/<?= e(basename($p['foto'])) ?>" alt="Foto de <?= e($p['nombre']) ?>" class="foto-mascota">
        <?php else: ?>
            <div style="padding:60px 20px; font-size:60px;">🐶</div>
            <p class="suave" style="font-weight:bold;">Sin fotografía registrada</p>
        <?php endif; ?>
        <h3 style="margin-top:15px; color:var(--morado-principal);"><?= e($p['nombre']) ?></h3>
        <p class="suave"><?= e($p['raza'] ?: 'Sin raza registrada') ?></p>
    </div>
    <div style="flex:2; min-width:300px;" class="detalle-grid">
        <div class="detalle-item">
            <span class="label">⚥ Sexo, color y edad</span>
            <span class="valor chico"><?= e($p['sexo'] ?: 'Sin dato') ?> · <?= e($p['color'] ?: 'Color no anotado') ?> · <?= e($p['edad'] ?: 'Edad no anotada') ?></span>
        </div>
        <div class="detalle-item">
            <span class="label">👤 Dueño y contacto</span>
            <span class="valor chico"><?= e($p['dueno_nombre']) ?> (📞 <?= e($p['dueno_telefono']) ?>)</span>
        </div>
        <div class="detalle-item full-width">
            <span class="label">🏠 Domicilio</span>
            <span class="valor chico"><?= e($p['domicilio'] ?: 'No registrado') ?></span>
        </div>
        <div class="detalle-item full-width">
            <span class="label">➕ Nuevo registro para <?= e($p['nombre']) ?></span>
            <div class="acciones" style="margin-top:8px;">
                <a href="estetica_nueva.php?paciente_id=<?= (int)$p['id'] ?>" class="boton boton-rosa"><span class="emoji">✂️</span> Hoja de estética</a>
                <a href="citas.php?paciente_id=<?= (int)$p['id'] ?>#nueva-cita" class="boton boton-rosa"><span class="emoji">🩺</span> Cita médica</a>
            </div>
        </div>
    </div>
</div>

<div class="grid-form" style="align-items:start;">
    <div class="tarjeta-formulario">
        <h3 style="margin-top:0;">✂️ Historial de estética</h3>
        <?php if (!$hojas): ?>
            <p class="suave">Todavía no ha venido a estética.</p>
        <?php else: foreach ($hojas as $h): ?>
            <p><a href="estetica_detalle.php?id=<?= (int)$h['id'] ?>" class="boton-texto">#<?= e($h['folio']) ?></a>
               <?= fecha_mx($h['fecha']) ?> · <?= e($h['servicios'] ?: 'Sin servicio marcado') ?></p>
        <?php endforeach; endif; ?>
    </div>
    <div class="tarjeta-formulario">
        <h3 style="margin-top:0;">🩺 Historial médico</h3>
        <?php if (!$citas): ?>
            <p class="suave">Todavía no tiene citas médicas.</p>
        <?php else: foreach ($citas as $c): $atendida = ($c['estatus'] === 'Atendida'); ?>
            <p><strong><?= fecha_mx($c['fecha_cita']) ?></strong> · <?= e($c['motivo']) ?>
               <span class="etiqueta <?= $atendida ? 'etiqueta-ok' : 'etiqueta-pendiente' ?>"><?= $atendida ? '✅ Atendida' : '⏳ Pendiente' ?></span></p>
        <?php endforeach; ?>
            <p class="ayuda">El diagnóstico se ve en el módulo de Citas Médicas.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Eliminar: solo si no tiene historial -->
<div style="margin-top:28px;">
    <form method="POST" data-confirmar="¿Seguro que quieres eliminar a <?= e($p['nombre']) ?> de la lista de pacientes? Esto no se puede deshacer.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="eliminar">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button type="submit" class="boton boton-peligro"><span class="emoji">🗑️</span> Eliminar paciente</button>
        <small class="ayuda">Solo se puede eliminar si no tiene hojas de estética ni citas médicas.</small>
    </form>
</div>

<?php require_once "includes/footer.php"; ?>
