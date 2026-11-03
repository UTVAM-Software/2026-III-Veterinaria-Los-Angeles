<?php
// Conexión a la base de datos
require_once "config/conexion.php";

// Este bloque solo se ejecuta cuando se envía el formulario (método POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Guardamos cada dato del formulario en una variable (trim quita espacios de más)
    // Si el folio viene vacío, se pone 0731
    $folio = trim($_POST['folio'] ?: '0731');
    $fecha = trim($_POST['fecha']);
    $hora_llegada = trim($_POST['hora_llegada']);
    $hora_salida = trim($_POST['hora_salida']);
    $nombre = trim($_POST['nombre']);
    $raza = trim($_POST['raza']);
    $sexo = trim($_POST['sexo']);
    $color = trim($_POST['color']);
    $edad = trim($_POST['edad']);
    $dueno_nombre = trim($_POST['dueno_nombre']);
    $dueno_telefono = trim($_POST['dueno_telefono']);
    $domicilio = trim($_POST['domicilio']);
    
    // Los servicios vienen como lista (checkboxes), los juntamos en un solo texto separado por comas
    $servicios_arr = $_POST['servicios'] ?? [];
    $servicios = implode(', ', $servicios_arr);

    $detalles_corte = trim($_POST['detalles_corte']);
    $observaciones = trim($_POST['observaciones']);
    // Si no escriben costo o anticipo, se guarda 0
    $costo_total = trim($_POST['costo_total'] ?: 0);
    $a_cuenta = trim($_POST['a_cuenta'] ?: 0);
    $forma_pago = trim($_POST['forma_pago']);

    // Procesamiento seguro de la fotografía
    $foto_nombre = null;
    // Revisamos que sí se haya subido una foto y que no haya dado error
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        // Sacamos la extensión del archivo (jpg, png, etc.)
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        // Le ponemos un nombre nuevo con la hora actual para que no se repita
        $foto_nombre = 'mascota_' . time() . '.' . $ext;
        
        // Asegurarnos de que la carpeta uploads exista físicamente
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }
        
        // Movemos la foto a la carpeta uploads
        move_uploaded_file($_FILES['foto']['tmp_name'], 'uploads/' . $foto_nombre);
    }

    // Preparamos el INSERT para guardar la mascota (los ? se llenan con los datos de abajo)
    $stmt = $pdo->prepare("INSERT INTO mascotas (folio, fecha, hora_llegada, hora_salida, nombre, raza, sexo, color, 
    edad, dueno_nombre, dueno_telefono, domicilio, servicios, detalles_corte, observaciones, costo_total, a_cuenta, forma_pago, foto) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Ejecutamos el INSERT con todos los datos, en el mismo orden de arriba
    $stmt->execute([
        $folio, $fecha, $hora_llegada, $hora_salida, $nombre, $raza, $sexo, $color, $edad, 
        $dueno_nombre, $dueno_telefono, $domicilio, $servicios, $detalles_corte, 
        $observaciones, $costo_total, $a_cuenta, $forma_pago, $foto_nombre
    ]);

    // Ya guardado, regresamos a la lista de mascotas
    header("Location: mascotas.php");
    exit();
}

// Traemos el encabezado (menú de arriba)
require_once "includes/header.php";
?>

<!-- Título de la página y botón para volver -->
<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">📝</span> Hoja de Recepción - Estética Canina</h1>
        <p class="subtitulo">Registro detallado del paciente con fotografía</p>
    </div>
    <a href="mascotas.php" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver</a>
</div>

<div class="tarjeta-formulario">
    <!-- enctype multipart/form-data es necesario para poder subir la foto -->
    <form method="POST" enctype="multipart/form-data" class="grid-form">
        <!-- Datos de la hoja: folio, fecha y horas -->
        <div class="form-grupo">
            <label>🏷️ Folio</label>
            <input type="text" name="folio" value="0735" class="input-control">
        </div>
        <div class="form-grupo">
            <label>📅 Fecha</label>
            <!-- date('Y-m-d') pone la fecha de hoy por defecto -->
            <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" class="input-control">
        </div>
        <div class="form-grupo">
            <label>⏰ Hora de Llegada</label>
            <input type="text" name="hora_llegada" placeholder="Ej. 09:00 AM" class="input-control">
        </div>
        <div class="form-grupo">
            <label>⏰ Hora Aprox. Salida</label>
            <input type="text" name="hora_salida" placeholder="Ej. 11:30 AM" class="input-control">
        </div>

        <!-- Datos de la mascota (los campos con required son obligatorios) -->
        <div class="form-grupo">
            <label>🐶 Mascota *</label>
            <input type="text" name="nombre" required placeholder="Nombre del perrito" class="input-control">
        </div>
        <div class="form-grupo">
            <label>📸 Fotografía del Paciente</label>
            <!-- accept="image/*" hace que solo se puedan elegir imágenes -->
            <input type="file" name="foto" accept="image/*" class="input-control" style="padding: 10px;">
        </div>
        <div class="form-grupo">
            <label>✂️ Raza</label>
            <input type="text" name="raza" placeholder="Ej. Poodle" class="input-control">
        </div>
        <div class="form-grupo">
            <label>Sexo</label>
            <select name="sexo" class="input-control">
                <option value="Macho">Macho</option>
                <option value="Hembra">Hembra</option>
            </select>
        </div>
        <div class="form-grupo">
            <label>🎨 Color</label>
            <input type="text" name="color" placeholder="Ej. Blanco / Café" class="input-control">
        </div>
        <div class="form-grupo">
            <label>🎂 Edad</label>
            <input type="text" name="edad" placeholder="Ej. 2 años" class="input-control">
        </div>
        <!-- Datos del dueño -->
        <div class="form-grupo">
            <label>👤 Dueño *</label>
            <input type="text" name="dueno_nombre" required placeholder="Nombre completo" class="input-control">
        </div>
        <div class="form-grupo">
            <label>📞 Teléfono</label>
            <input type="text" name="dueno_telefono" placeholder="Teléfono de contacto" class="input-control">
        </div>
        <div class="form-grupo full-width">
            <label>🏠 Domicilio</label>
            <input type="text" name="domicilio" placeholder="Dirección del propietario" class="input-control">
        </div>

        <!-- Servicios: se pueden marcar varios (por eso el name lleva [] ) -->
        <div class="form-grupo full-width" style="background:var(--morado-suave); padding:16px; border-radius:12px;">
            <label style="color:var(--morado-principal); margin-bottom:10px; display:block;">✨ Tipo de Servicio (Marcar los que apliquen)

            </label>
            <div style="display:flex; gap:20px; flex-wrap:wrap;">
                <label><input type="checkbox" name="servicios[]" value="Baño"> Baño</label>
                <label><input type="checkbox" name="servicios[]" value="Deslanado"> Deslanado</label>
                <label><input type="checkbox" name="servicios[]" value="Corte"> Corte</label>
                <label><input type="checkbox" name="servicios[]" value="Limpieza de oídos"> Limpieza de oídos</label>
                <label><input type="checkbox" name="servicios[]" value="Corte de uñas"> Corte de uñas</label>
            </div>
        </div>

        <!-- Cómo se quiere el corte -->
        <div class="form-grupo full-width">
            <label>✂️ Detalles de Corte</label>
            <input type="text" name="detalles_corte" placeholder="Ej. Navaja #2, Tijera en cara" class="input-control">
        </div>

        <!-- Notas extra sobre la mascota -->
        <div class="form-grupo full-width">
            <label>📋 Observaciones</label>
            <textarea name="observaciones" rows="2" class="input-control" placeholder="Comportamiento, alergias..."></textarea>
        </div>

        <!-- Costos (step 0.01 permite poner centavos) -->
        <div class="form-grupo">
            <label>💲 Costo Total ($)</label>
            <input type="number" step="0.01" name="costo_total" placeholder="0.00" class="input-control">
        </div>
        <div class="form-grupo">
            <label>💵 A Cuenta / Anticipo ($)</label>
            <input type="number" step="0.01" name="a_cuenta" placeholder="0.00" class="input-control">
        </div>
        <div class="form-grupo full-width">
            <label>💳 Forma de Pago</label>
            <select name="forma_pago" class="input-control">
                <option value="Efectivo">Efectivo</option>
                <option value="Tarjeta">Tarjeta</option>
                <option value="Transferencia">Transferencia</option>
                <option value="Otro">Otro</option>
            </select>
        </div>

        <!-- Botón para guardar todo -->
        <div class="form-grupo full-width" style="margin-top: 10px;">
            <button type="submit" class="boton boton-rosa" style="width:100%; padding:14px; font-size:18px;"><span class="emoji">✨</span> 
            Guardar Hoja de Recepción</button>
        </div>
    </form>
</div>

<!-- Traemos el pie de página -->
<?php require_once "includes/footer.php"; ?>