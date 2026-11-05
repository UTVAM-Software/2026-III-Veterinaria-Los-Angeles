<?php
require_once "includes/auth.php";
require_once "config/conexion.php";
exigir('estetica.ver');

$id = $_GET['id'] ?? ($_POST['id'] ?? '');
if (!ctype_digit((string)$id)) {
    header("Location: estetica.php");
    exit();
}

// Eliminar SOLO esta hoja (el paciente y sus citas médicas no se tocan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'eliminar') {
    if (!puede('estetica.eliminar')) {
        flash('error', "No tienes permiso para eliminar hojas de estética.");
        header("Location: estetica_detalle.php?id=" . (int)$id);
        exit();
    }
    if (!csrf_ok()) {
        flash('error', "La pantalla estuvo abierta mucho tiempo. Intenta otra vez.");
        header("Location: estetica_detalle.php?id=" . (int)$id);
        exit();
    }
    $pdo->prepare("DELETE FROM hojas_estetica WHERE id = ?")->execute([$id]);
    flash('ok', "Se eliminó la hoja de estética. El paciente sigue registrado.");
    header("Location: estetica.php");
    exit();
}

$stmt = $pdo->prepare("SELECT h.*, p.nombre, p.raza, p.sexo, p.color, p.edad, p.dueno_nombre, p.dueno_telefono, p.domicilio, p.foto
                       FROM hojas_estetica h JOIN pacientes p ON h.paciente_id = p.id WHERE h.id = ?");
$stmt->execute([$id]);
$h = $stmt->fetch();
if (!$h) {
    flash('error', "No encontramos esa hoja. Puede que ya se haya eliminado.");
    header("Location: estetica.php");
    exit();
}

$total = (float)($h['costo_total'] ?? 0);
$cuenta = (float)($h['a_cuenta'] ?? 0);
$resta = saldo_pendiente($total, $cuenta);

require_once "includes/header.php";
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">✂️</span> Hoja de <?= e($h['nombre']) ?></h1>
        <p class="subtitulo">Folio #<?= e($h['folio']) ?> · Fecha: <?= fecha_mx($h['fecha']) ?></p>
    </div>
    <div class="acciones">
        <a href="estetica.php" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver a la lista</a>
        <?php if (puede('estetica.editar')): ?><a href="estetica_nueva.php?id=<?= (int)$h['id'] ?>" class="boton boton-secundario"><span class="emoji">✏️</span> Editar hoja</a><?php endif; ?>
        <?php if (puede('estetica.eliminar')): ?>
        <form method="POST" data-confirmar="¿Seguro que quieres eliminar esta hoja de estética? Esto no se puede deshacer. El paciente y sus citas médicas no se borran." style="margin:0;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="eliminar">
            <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
            <button type="submit" class="boton boton-peligro"><span class="emoji">🗑️</span> Eliminar hoja</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="tarjeta-detalle" style="display:flex; gap:30px; align-items:flex-start; flex-wrap:wrap;">
    <div class="foto-caja">
        <?php if (!empty($h['foto']) && file_exists('uploads/' . basename($h['foto']))): ?>
            <img src="uploads/<?= e(basename($h['foto'])) ?>" alt="Foto de <?= e($h['nombre']) ?>" class="foto-mascota">
        <?php else: ?>
            <div style="padding:60px 20px; font-size:60px;">🐶</div>
            <p class="suave" style="font-weight:bold;">Sin fotografía registrada</p>
        <?php endif; ?>
        <h3 style="margin-top:15px; color:var(--morado-principal);"><?= e($h['nombre']) ?></h3>
        <p class="suave"><?= e($h['raza'] ?: 'Sin raza registrada') ?></p>
        <a href="paciente_detalle.php?id=<?= (int)$h['paciente_id'] ?>" class="boton-texto">Ver ficha completa del paciente</a>
    </div>

    <div style="flex:2; min-width:300px;" class="detalle-grid">
        <div class="detalle-item">
            <span class="label">⏰ Horarios</span>
            <span class="valor chico">Llegada: <?= e($h['hora_llegada'] ?: 'No anotada') ?> · Salida: <?= e($h['hora_salida'] ?: 'No anotada') ?></span>
        </div>
        <div class="detalle-item">
            <span class="label">⚥ Sexo, color y edad</span>
            <span class="valor chico"><?= e($h['sexo'] ?: 'Sin dato') ?> · <?= e($h['color'] ?: 'Color no anotado') ?> · <?= e($h['edad'] ?: 'Edad no anotada') ?></span>
        </div>
        <div class="detalle-item">
            <span class="label">👤 Dueño y contacto</span>
            <span class="valor chico"><?= e($h['dueno_nombre']) ?> (📞 <?= telefono_mx($h['dueno_telefono']) ?>)</span>
        </div>
        <div class="detalle-item">
            <span class="label">🏠 Domicilio</span>
            <span class="valor chico"><?= e($h['domicilio'] ?: 'No registrado') ?></span>
        </div>
        <div class="detalle-item full-width">
            <span class="label">✨ Servicios pedidos</span>
            <span class="valor chico" style="color:var(--turquesa-principal);"><?= e($h['servicios'] ?: 'Ninguno marcado') ?></span>
        </div>
        <div class="detalle-item full-width">
            <span class="label">✂️ Cómo quiere el corte</span>
            <div class="valor-box"><?= e($h['detalles_corte'] ?: 'Sin indicaciones.') ?></div>
        </div>
        <div class="detalle-item full-width">
            <span class="label">📋 Observaciones</span>
            <div class="valor-box"><?= nl2br(e($h['observaciones'] ?: 'Sin observaciones.')) ?></div>
        </div>
        <div class="detalle-item full-width">
            <span class="label">💲 Cobro</span>
            <span class="valor">Total: <?= dinero($total) ?> · Anticipo: <?= dinero($cuenta) ?> · Falta por pagar: <?= dinero($resta) ?></span>
        </div>
        <div class="detalle-item">
            <span class="label">💳 Forma de pago</span>
            <span class="valor"><?= e($h['forma_pago'] ?: 'Efectivo') ?></span>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
