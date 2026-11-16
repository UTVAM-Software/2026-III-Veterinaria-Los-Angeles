<?php
// Las credenciales vienen de config/conexion.php (ese archivo NO se sube a GitHub).
require_once __DIR__ . '/config/conexion.php';

// Devuelve la conexion $pdo que crea conexion.php
function conectar() {
    global $pdo;
    return $pdo;
}
function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function fecha_mx($fecha) {
    if (!$fecha) return '-';
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d ? $d->format('d/m/Y') : e($fecha);
}

function es_fecha($fecha) {
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d && $d->format('Y-m-d') === $fecha;
}

function solo_letras($texto) {
    return (bool)preg_match('/^[\p{L}][\p{L} .\'-]*$/u', $texto);
}

function hora_valida($hora) {
    return (bool)preg_match('/^(0?[1-9]|1[0-2]):[0-5][0-9] (AM|PM)$/i', $hora);
}

// Largo de un texto contando letras (no bytes)
function largo($texto) {
    return mb_strlen($texto, 'UTF-8');
}

// Guarda un aviso para mostrarlo en la siguiente pantalla
function flash($tipo, $mensaje) {
    $_SESSION['flash'] = [$tipo, $mensaje];
}

// Muestra el aviso guardado (una sola vez) y lo borra
function mostrar_flash() {
    if (empty($_SESSION['flash'])) return;
    list($tipo, $mensaje) = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $icono = ($tipo === 'ok') ? '✅' : '⚠️';
    echo '<div class="alerta alerta-' . e($tipo) . '" role="status">' . $icono . ' ' . e($mensaje) . '</div>';
}

// Revisa la foto subida (opcional). Devuelve [nombre_nuevo, ruta_temporal] o [null, null].
// Si hay un problema, agrega el aviso a $errores.
function procesar_foto(&$errores) {
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "No se pudo subir la foto. Intenta con otra o déjala vacía.";
        return [null, null];
    }
    if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
        $errores[] = "La foto pesa más de 5 MB. Elige una más pequeña.";
        return [null, null];
    }
    $info = @getimagesize($_FILES['foto']['tmp_name']);
    $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!$info || !isset($tipos[$info['mime']])) {
        $errores[] = "La foto debe ser una imagen (JPG, PNG, WEBP o GIF).";
        return [null, null];
    }
    return ['mascota_' . bin2hex(random_bytes(6)) . '.' . $tipos[$info['mime']], $_FILES['foto']['tmp_name']];
}

// ===== Cálculos y formato (lógica de negocio) =====

// Teléfono de 10 números con formato legible: 7791234567 -> 779 123 4567
function telefono_mx($tel) {
    $t = preg_replace('/\D/', '', (string)$tel);
    return strlen($t) === 10 ? substr($t, 0, 3) . ' ' . substr($t, 3, 3) . ' ' . substr($t, 6) : e($tel);
}

// Dinero con signo y dos decimales: 350 -> $350.00
function dinero($monto) {
    return '$' . number_format((float)$monto, 2);
}

// Lo que todavía debe el cliente (nunca sale negativo)
function saldo_pendiente($total, $a_cuenta) {
    return max(0, (float)$total - (float)$a_cuenta);
}

// ===== Paginación (para que las listas largas no se hagan eternas) =====

// Devuelve [pagina_actual, total_de_paginas, desde_cual_registro, cuantos_por_pagina]
function paginar($total_registros, $por_pagina = 10) {
    $paginas = max(1, (int)ceil($total_registros / $por_pagina));
    $p = $_GET['pagina'] ?? '1';
    $pagina = (ctype_digit((string)$p) && (int)$p > 0) ? (int)$p : 1;
    $pagina = min($pagina, $paginas);
    return [$pagina, $paginas, ($pagina - 1) * $por_pagina, $por_pagina];
}

// Dibuja los botones "Anterior / 1 2 3 / Siguiente" conservando la búsqueda que ya hizo
function mostrar_paginacion($pagina, $paginas) {
    if ($paginas <= 1) return;
    $enlace = function ($n) {
        return e('index.php' . '?' . http_build_query(array_merge($_GET, ['pagina' => $n])));
    };
    echo '<nav class="paginacion" aria-label="Páginas">';
    if ($pagina > 1) echo '<a href="' . $enlace($pagina - 1) . '" class="boton boton-secundario">⬅️ Anterior</a>';
    for ($i = 1; $i <= $paginas; $i++) {
        if ($i === $pagina) echo '<span class="pagina-actual">' . $i . '</span>';
        else echo '<a href="' . $enlace($i) . '" class="boton boton-secundario">' . $i . '</a>';
    }
    if ($pagina < $paginas) echo '<a href="' . $enlace($pagina + 1) . '" class="boton boton-secundario">Siguiente ➡️</a>';
    echo '</nav>';
}

// Carpeta de fotos: se crea sola y se le pone la misma protección que tenía uploads/.htaccess y uploads/index.php
function preparar_uploads() {
    if (!is_dir('uploads')) { mkdir('uploads', 0755, true); }
    if (!file_exists('uploads/.htaccess')) {
        @file_put_contents('uploads/.htaccess', "# Por seguridad: aquí nunca se ejecuta código, solo se muestran imágenes\nphp_flag engine off\nOptions -Indexes\n");
    }
    if (!file_exists('uploads/index.php')) {
        @file_put_contents('uploads/index.php', "<?php\n// Carpeta de fotos de pacientes. Este archivo evita que se pueda ver la lista de archivos.\nhttp_response_code(403);\n");
    }
}

// El folio se asigna solo: el último número + 1 (ej. 0735 -> 0736)
function siguiente_folio($pdo) {
    $ultimo = (int)$pdo->query("SELECT MAX(CAST(folio AS UNSIGNED)) FROM hojas_estetica")->fetchColumn();
    return str_pad($ultimo + 1, 4, '0', STR_PAD_LEFT);
}

// =====================================================================
// 3. SESIÓN, PERMISOS Y CSRF
// =====================================================================
// Se llama al INICIO de cada página que necesita haber iniciado sesión (antes lo hacía includes/auth.php).
function autenticar() {
    global $pdo;
    // Si no ha iniciado sesión, lo mandamos a la pantalla de entrada
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: index.php?p=login");
        exit();
    }

    // En cada pantalla se vuelve a leer al usuario y su rol de la base de datos.
    // Así, si el administrador cambia un permiso o desactiva a alguien, se nota de inmediato (sin esperar a que cierre sesión).
    $stmt = $pdo->prepare("SELECT u.id, u.nombre, u.activo, r.id AS rol_id, r.nombre AS rol_nombre, r.protegido
                           FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $yo = $stmt->fetch();
    if (!$yo || !$yo['activo']) {
        $_SESSION = [];
        session_destroy();
        header("Location: index.php?p=login");
        exit();
    }
    $_SESSION['usuario_nombre'] = $yo['nombre'];
    $_SESSION['rol_id'] = (int)$yo['rol_id'];
    $_SESSION['rol_nombre'] = $yo['rol_nombre'];

    // El rol "protegido" (Administrador) tiene TODAS las llaves; los demás, solo las que tengan marcadas.
    $GLOBALS['es_admin'] = ((int)$yo['protegido'] === 1);
    $GLOBALS['mis_permisos'] = [];
    if (!$GLOBALS['es_admin']) {
        $stmt = $pdo->prepare("SELECT p.clave FROM rol_permiso rp JOIN permisos p ON p.id = rp.permiso_id WHERE rp.rol_id = ?");
        $stmt->execute([$yo['rol_id']]);
        $GLOBALS['mis_permisos'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
}

// ¿Este usuario tiene la llave (permiso) para hacer algo? Ejemplo: puede('pacientes.eliminar')
function puede($clave) {
    return $GLOBALS['es_admin'] || in_array($clave, $GLOBALS['mis_permisos'], true);
}

// Pone al inicio de una página: si no tiene la llave, lo manda a la pantalla de "sin permiso"
function exigir($clave) {
    if (!puede($clave)) {
        header("Location: index.php?p=sin_permiso");
        exit();
    }
}

// Primera pantalla a la que sí puede entrar (para después del login)
function pagina_inicio() {
    $opciones = ['pacientes.ver' => 'index.php?p=pacientes', 'estetica.ver' => 'index.php?p=estetica', 'citas.ver' => 'index.php?p=citas',
                 'usuarios.ver' => 'index.php?p=usuarios', 'roles.ver' => 'index.php?p=roles', 'permisos.gestionar' => 'index.php?p=permisos'];
    foreach ($opciones as $clave => $pagina) {
        if (puede($clave)) return $pagina;
    }
    return 'index.php?p=sin_permiso';
}

// "Llave secreta" de la sesión: evita que otra página engañe al sistema para borrar o guardar cosas
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function csrf_ok() {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

// =====================================================================
// 4. PLANTILLA, CSS Y JAVASCRIPT
// =====================================================================
function vista_header() {

// Para saber en qué página estamos y marcar el botón del menú
$pagina_actual = $GLOBALS['ruta'];
$en_pacientes = in_array($pagina_actual, ['pacientes', 'paciente_nuevo', 'paciente_detalle']);
$en_estetica = in_array($pagina_actual, ['estetica', 'estetica_nueva', 'estetica_detalle']);
$en_citas = in_array($pagina_actual, ['citas', 'cita_editar']);
$en_usuarios = in_array($pagina_actual, ['usuarios', 'usuario_form']);
$en_roles = in_array($pagina_actual, ['roles', 'rol_form']);
$en_permisos = ($pagina_actual === 'permisos');
$rol_texto = $_SESSION['rol_nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estética Canina - Los Ángeles</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;600;700&display=swap" rel="stylesheet">
    <style><?php echo css_app(); ?></style>
</head>
<body>
<div class="app">
  <header class="encabezado-superior">
    <div class="marca">
      <div class="marca-icono">
        <img src="LOGO.png" alt="Logo de la veterinaria">
      </div>
      <div class="marca-texto">Veterinaria<br>Los Ángeles</div>
    </div>
    <!-- Menú: tres botones grandes y claros -->
    <nav class="menu-superior" aria-label="Menú principal">
      <?php if (puede('pacientes.ver')): ?><a href="index.php?p=pacientes" class="nav-item-top <?= $en_pacientes ? 'activo' : '' ?>">🐕 Pacientes</a><?php endif; ?>
      <?php if (puede('estetica.ver')): ?><a href="index.php?p=estetica" class="nav-item-top <?= $en_estetica ? 'activo' : '' ?>">✂️ Estética Canina</a><?php endif; ?>
      <?php if (puede('citas.ver')): ?><a href="index.php?p=citas" class="nav-item-top <?= $en_citas ? 'activo' : '' ?>">🩺 Citas Médicas</a><?php endif; ?>
      <?php if (puede('usuarios.ver')): ?><a href="index.php?p=usuarios" class="nav-item-top <?= $en_usuarios ? 'activo' : '' ?>">👥 Usuarios</a><?php endif; ?>
      <?php if (puede('roles.ver')): ?><a href="index.php?p=roles" class="nav-item-top <?= $en_roles ? 'activo' : '' ?>">🎭 Roles</a><?php endif; ?>
      <?php if (puede('permisos.gestionar')): ?><a href="index.php?p=permisos" class="nav-item-top <?= $en_permisos ? 'activo' : '' ?>">🔑 Permisos</a><?php endif; ?>
    </nav>
    <!-- Quién está usando el sistema y botón para salir -->
    <div class="usuario-caja">
      <span>👤 <?= e($_SESSION['usuario_nombre']) ?><br><small><?= e($rol_texto) ?></small></span>
      <a href="index.php?p=logout" class="nav-item-top salir">Cerrar sesión</a>
    </div>
  </header>
  <main class="contenido">
<?php mostrar_flash(); ?>
<?php
}

function vista_footer() {
?>
  </main>
</div>
<script><?php echo js_app(); ?></script>
</body>
</html><?php
}


function css_app() {
    return <<<'CSS'
/* ===== Colores y fuente que usamos en todo el sistema ===== */
:root {
  --morado-principal: #652780;
  --morado-oscuro: #4B1D61;
  --morado-suave: #F3ECF8;
  --turquesa-principal: #3E8B80;
  --turquesa-suave: #E8F5F3;
  --rosita-principal: #D86B9D;
  --rosita-pastel: #FCEBF3;
  --texto-oscuro: #2D2A3F;
  --texto-suave: #625B75;
  --fondo-general: #FAF8FC;
  --superficie-blanca: #FFFFFF;
  --borde-suave: #EAE3F0;
  --fuente-principal: 'Fredoka', 'Segoe UI', sans-serif;
}

/* Para que el padding y el borde no agranden las cajas */
* { box-sizing: border-box; }

/* ===== Estilos generales de la página ===== */
body {
  margin: 0;
  font-family: var(--fuente-principal);
  background-color: var(--fondo-general);
  color: var(--texto-oscuro);
  font-size: 17px;
}

/* Títulos en color morado */
h1, h2 { color: var(--morado-principal); margin: 0; }
/* Los links sin subrayado y del mismo color del texto */
a { color: inherit; text-decoration: none; }

/* Contenedor principal de toda la página */
.app { display: flex; flex-direction: column; min-height: 100vh; }

/* ===== Encabezado de arriba (logo y menú) ===== */
.encabezado-superior {
  background: linear-gradient(135deg, var(--morado-oscuro) 0%, var(--morado-principal) 100%);
  color: #ffffff;
  padding: 16px 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Logo y nombre de la veterinaria */
.marca { display: flex; align-items: center; gap: 14px; }
/* Círculo blanco donde va el logo */
.marca-icono {
  width: 52px; height: 52px; border-radius: 50%; background: #ffffff; padding: 4px;
  display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.marca-icono img { width: 100%; height: 100%; object-fit: contain; border-radius: 50%; }
.marca-texto { font-size: 22px; font-weight: 800; line-height: 1.1; }

/* Menú de arriba */
.menu-superior { display: flex; gap: 10px; }
.nav-item-top {
  cursor: pointer;
  display: flex; align-items: center; gap: 8px; padding: 8px 18px;
  border-radius: 14px; background: var(--rosita-pastel); color: var(--morado-oscuro); font-weight: 700;
}

/* ===== Contenido de cada página ===== */
/* Lo centramos y le ponemos un ancho máximo */
.contenido { padding: 32px 40px 60px; max-width: 1100px; margin: 0 auto; width: 100%; }

/* Título de cada página con su botón a un lado */
.encabezado-pagina { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
.subtitulo { color: var(--texto-suave); font-size: 16px; margin-top: 4px; }

/* Los emojis se ven más grandes */
.emoji { font-size: 1.4em; vertical-align: -2px; }

/* ===== Botones ===== */
.boton {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 12px 24px; border-radius: 14px; font-size: 16px; font-weight: 700; border: none; cursor: pointer;
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}
/* Botón blanco con borde */
.boton-secundario { background: var(--superficie-blanca); color: var(--texto-oscuro); border: 2px solid var(--borde-suave); }
/* Botón rosa (el principal) y su color al pasar el mouse */
.boton-rosa { background: var(--rosita-principal); color: #ffffff; }
.boton-rosa:hover { background: #c25788; }
/* Botón chiquito verde para el link "Ver Ficha" */
.boton-texto { color: var(--turquesa-principal); font-weight: 700; background: var(--turquesa-suave); padding: 6px 14px; border-radius: 10px; }

/* ===== Buscador y campos de texto ===== */
.buscador-form { margin-bottom: 24px; display: flex; gap: 12px; }
.input-control {
  width: 100%; padding: 14px 18px; border-radius: 14px; border: 2px solid var(--borde-suave);
  font-size: 16px; font-family: inherit; outline: none; background: var(--superficie-blanca);
}
/* Cuando se hace clic en el campo, el borde se pone morado */
.input-control:focus { border-color: var(--morado-principal); }

/* ===== Tabla de pacientes ===== */
.tabla-envoltura {
  background: var(--superficie-blanca); border-radius: 20px; border: 2px solid var(--borde-suave);
  overflow: hidden; box-shadow: 0 8px 24px rgba(101, 39, 128, 0.08);
}
table.tabla { width: 100%; border-collapse: collapse; }
/* Encabezados de la tabla */
.tabla th {
  text-align: left; font-size: 15px; font-weight: 700; color: var(--morado-principal);
  background: var(--morado-suave); padding: 16px 22px; border-bottom: 2px solid var(--borde-suave);
}
/* Celdas de la tabla */
.tabla td { padding: 18px 22px; border-bottom: 1px solid var(--borde-suave); font-size: 16px; }
.nombre-mascota { font-weight: 700; font-size: 18px; color: var(--morado-oscuro); }

/* ===== Tarjetas del formulario y de la ficha ===== */
.tarjeta-formulario, .tarjeta-detalle {
  background: var(--superficie-blanca); border-radius: 20px; padding: 32px;
  border: 2px solid var(--borde-suave); box-shadow: 0 8px 24px rgba(101, 39, 128, 0.08);
}
/* El formulario se acomoda en 2 columnas */
.grid-form { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
/* Los campos con esta clase ocupan las 2 columnas */
.full-width { grid-column: span 2; }
.form-grupo label { display: block; font-weight: 700; margin-bottom: 8px; color: var(--morado-oscuro); }

/* ===== Ficha de la mascota (detalle) ===== */
/* Los datos también van en 2 columnas */
.detalle-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
/* Título chiquito de cada dato */
.detalle-item .label { display: block; font-size: 14px; color: var(--texto-suave); font-weight: 700; }
/* El dato en sí, más grande */
.detalle-item .valor { font-size: 20px; font-weight: 700; color: var(--morado-oscuro); }
/* Cajita para los textos largos (corte y observaciones) */
.valor-box {
  background: var(--fondo-general); padding: 18px; border-radius: 14px;
  border: 2px solid var(--borde-suave); margin-top: 8px; font-size: 16px;
}

/* =====================================================================
   MEJORAS PARA FACILITAR EL USO (letras más grandes, avisos claros, etc.)
   ===================================================================== */
body { font-size: 18px; line-height: 1.45; }
.suave { color: var(--texto-suave); }
.normal { font-weight: normal; }
.vacio { text-align: center; padding: 30px; color: var(--texto-suave); }

/* Campos y botones más grandes y fáciles de tocar */
.input-control { font-size: 18px; min-height: 52px; }
textarea.input-control { min-height: 90px; }
.input-control:focus { box-shadow: 0 0 0 4px rgba(101, 39, 128, 0.15); }
.input-control[readonly] { background: var(--morado-suave); color: var(--texto-suave); }
.input-control:invalid:not(:placeholder-shown):not(:focus) { border-color: #e04b4b; }
.boton { font-size: 18px; min-height: 48px; }
.boton:focus-visible, .input-control:focus-visible, a:focus-visible { outline: 3px solid var(--morado-principal); outline-offset: 2px; }
.boton-grande { width: 100%; padding: 16px; font-size: 20px; }
.boton-grande-auto { padding: 16px 28px; font-size: 19px; }
.boton-peligro { background: #d64545; color: #fff; }
.boton-peligro:hover { background: #b83232; }
.boton[disabled] { opacity: .6; cursor: wait; }

/* Menú de arriba */
.encabezado-superior { flex-wrap: wrap; gap: 14px; }
.nav-item-top {
  cursor: pointer; text-decoration: none; transition: transform .1s; }
.nav-item-top:hover { transform: translateY(-1px); }
.nav-item-top.activo { background: var(--rosita-principal); color: #fff; }
.nav-item-top.salir { background: transparent; color: #fff; border: 2px solid rgba(255,255,255,.6); font-weight: 600; }
.usuario-caja { display: flex; align-items: center; gap: 14px; text-align: right; font-size: 15px; line-height: 1.2; }
.usuario-caja small { opacity: .85; }

/* Avisos: verde = todo bien, rojo = hay que corregir algo */
.alerta { padding: 16px 20px; border-radius: 14px; margin-bottom: 22px; font-weight: 600; border: 2px solid; }
.alerta ul { margin: 8px 0 0; padding-left: 22px; font-weight: 500; }
.alerta li { margin-bottom: 4px; }
.alerta-ok { background: #e6f6ec; color: #17623a; border-color: #9fd9b6; }
.alerta-error { background: #fdecec; color: #8f1d1d; border-color: #f2a8a8; }

/* Formulario por secciones */
.form-secciones { display: grid; gap: 24px; }
.seccion { border: 2px solid var(--borde-suave); border-radius: 16px; padding: 20px 22px 24px; margin: 0; }
.seccion legend { font-weight: 700; font-size: 20px; color: var(--morado-principal); padding: 0 10px; }
.ayuda { display: block; color: var(--texto-suave); font-size: 14px; margin-top: 6px; }
p.ayuda { font-size: 16px; margin: 0 0 12px; }
.input-archivo { padding: 10px; }

/* Servicios como botones grandes para marcar */
.servicios-lista { display: flex; flex-wrap: wrap; gap: 12px; }
.servicio-opcion {
  display: inline-flex; align-items: center; gap: 10px; padding: 12px 18px; cursor: pointer;
  background: var(--morado-suave); border: 2px solid var(--borde-suave); border-radius: 14px; font-weight: 600;
}
.servicio-opcion input { width: 22px; height: 22px; accent-color: var(--morado-principal); }
.servicio-opcion:has(input:checked) { background: var(--turquesa-suave); border-color: var(--turquesa-principal); }

/* Etiquetas de estado de las citas */
.etiqueta { display: inline-block; padding: 4px 12px; border-radius: 999px; font-weight: 700; font-size: 15px; }
.etiqueta-ok { background: #e6f6ec; color: #17623a; }
.etiqueta-pendiente { background: #fff1de; color: #a35a00; }
.separador { margin: 15px 0; border: 0; border-top: 1px solid var(--borde-suave); }

/* Ficha de la mascota */
.acciones { display: flex; gap: 10px; flex-wrap: wrap; }
.foto-caja { flex: 1; min-width: 250px; text-align: center; background: var(--morado-suave); padding: 20px; border-radius: 16px; }
.foto-mascota { width: 100%; max-height: 280px; object-fit: cover; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.valor.chico { font-size: 17px; }

/* Pantalla de entrada */
.pagina-login { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: var(--fondo-general); padding: 20px; }
.login-caja { max-width: 440px; width: 100%; text-align: center; }
.campo-login { text-align: left; margin-bottom: 18px; }
.mostrar-clave { display: flex !important; align-items: center; gap: 8px; margin-top: 10px; font-weight: 500 !important; font-size: 16px; }

/* Celular y tablet */
@media (max-width: 760px) {
  .contenido { padding: 20px 16px 50px; }
  .encabezado-pagina { flex-direction: column; align-items: flex-start; gap: 14px; }
  .grid-form, .detalle-grid { grid-template-columns: 1fr; }
  .full-width { grid-column: span 1; }
  .tabla-envoltura { overflow-x: auto; }
  .buscador-form { flex-wrap: wrap; }
  .usuario-caja { text-align: left; }
}


/* ===== Entregable 3: paginación, usuarios, roles y permisos ===== */
.menu-superior { flex-wrap: wrap; }
.paginacion { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; align-items: center; margin: 24px 0; }
.pagina-actual { background: var(--morado-principal); color: #fff; font-weight: 700; padding: 10px 16px; border-radius: 12px; }
.etiqueta-gris { background: #eee9f2; color: var(--texto-suave); }
.matriz-permisos { width: 100%; border-collapse: collapse; background: var(--superficie-blanca); }
.matriz-permisos th, .matriz-permisos td { padding: 10px 14px; border-bottom: 1px solid var(--borde-suave); text-align: center; }
.matriz-permisos th:first-child, .matriz-permisos td:first-child { text-align: left; }
.matriz-permisos tr.fila-modulo td { background: var(--morado-suave); color: var(--morado-oscuro); font-weight: 700; text-align: left; text-transform: capitalize; }
.matriz-permisos input[type=checkbox] { width: 22px; height: 22px; accent-color: var(--morado-principal); cursor: pointer; }
.matriz-permisos input[type=checkbox]:disabled { cursor: not-allowed; opacity: .6; }
.matriz-permisos small { color: var(--texto-suave); font-weight: 400; }
.boton-pequeno { padding: 6px 12px; font-size: 14px; }
CSS;
}

function js_app() {
    return <<<'JS'
// ============================================================
// Ayudas para que los formularios sean fáciles de usar
// (el servidor también revisa todo; esto es solo para avisar rápido)
// ============================================================
document.addEventListener('DOMContentLoaded', function () {

    // 1) Campos "solo letras": borra lo que no sea letra mientras se escribe
    document.querySelectorAll('.solo-letras').forEach(function (campo) {
        campo.addEventListener('input', function () {
            this.value = this.value.replace(/[^A-Za-zñÑáéíóúÁÉÍÓÚüÜ\s.'\-]/g, '');
        });
    });

    // 2) Campos "solo números": borra lo que no sea número
    document.querySelectorAll('.solo-numeros').forEach(function (campo) {
        campo.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });

    // 3) Mensajes de error en español sencillo (en vez de los del navegador)
    document.querySelectorAll('input, textarea, select').forEach(function (campo) {
        campo.addEventListener('invalid', function () {
            var v = this.validity;
            if (v.valueMissing) {
                this.setCustomValidity(this.dataset.vacio || 'Este dato es obligatorio');
            } else if (v.rangeUnderflow || v.rangeOverflow || v.badInput || v.patternMismatch || v.tooShort) {
                this.setCustomValidity(this.dataset.msg || 'Revisa este dato, no está escrito correctamente');
            }
        });
        campo.addEventListener('input', function () { this.setCustomValidity(''); });
        campo.addEventListener('change', function () { this.setCustomValidity(''); });
    });

    // 4) Reloj para elegir la hora (si cargó la librería)
    if (window.flatpickr && document.querySelector('.reloj-input')) {
        flatpickr('.reloj-input', { enableTime: true, noCalendar: true, dateFormat: 'h:i K', time_24hr: false, allowInput: false });
    }

    // 5) Preguntar antes de acciones delicadas (por ejemplo, eliminar)
    document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
        form.addEventListener('submit', function (evento) {
            if (!window.confirm(form.dataset.confirmar)) { evento.preventDefault(); }
        });
    });

    // 6) Evitar que se guarde dos veces si le dan doble clic al botón
    document.querySelectorAll('form[data-evitar-doble]').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) { return; }
            var boton = form.querySelector('button[type="submit"]');
            if (boton) {
                setTimeout(function () { boton.disabled = true; boton.textContent = 'Guardando... un momento'; }, 0);
            }
        });
    });

    // 7) Mostrar u ocultar la contraseña en el login
    var ver = document.getElementById('ver-clave');
    var clave = document.getElementById('password');
    if (ver && clave) {
        ver.addEventListener('change', function () { clave.type = ver.checked ? 'text' : 'password'; });
    }

    // 8) Calcular cuánto falta por pagar mientras se escribe
    var costo = document.getElementById('costo_total');
    var anticipo = document.getElementById('a_cuenta');
    var aviso = document.getElementById('resta-pago');
    if (costo && anticipo && aviso) {
        var calcular = function () {
            var t = parseFloat(costo.value) || 0;
            var a = parseFloat(anticipo.value) || 0;
            if (a > t) { aviso.textContent = '⚠️ El anticipo no puede ser mayor que el costo total.'; aviso.style.color = '#8f1d1d'; }
            else if (t > 0) { aviso.textContent = 'Falta por pagar: $' + (t - a).toFixed(2); aviso.style.color = ''; }
            else { aviso.textContent = ''; }
        };
        costo.addEventListener('input', calcular);
        anticipo.addEventListener('input', calcular);
        calcular();
    }

    // 9) Tabla de permisos: "marcar todo" de un rol
    document.querySelectorAll('.marcar-columna').forEach(function (maestro) {
        maestro.addEventListener('change', function () {
            document.querySelectorAll('input[name^="perm["][data-rol="' + maestro.dataset.rol + '"]').forEach(function (c) { c.checked = maestro.checked; });
        });
    });
});
JS;
}

// =====================================================================
// 5. PÁGINAS  (cada una es la misma pantalla de antes, ahora como función)
// =====================================================================
function pg_login() {
    global $pdo;

// Si ya había iniciado sesión, lo mandamos directo al sistema
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario === '' || $password === '') {
        $error = "Escribe tu usuario y tu contraseña para entrar.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND activo = 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        // Se compara la contraseña escrita contra la encriptada (BCRYPT) guardada en la base de datos
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true); // sesión nueva por seguridad
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_nombre'] = $user['nombre'];
            header("Location: index.php");
            exit();
        } else {
            // Mismo mensaje para usuario o contraseña mal: no damos pistas a extraños
            $error = "El usuario o la contraseña no son correctos. Revisa que estén bien escritos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Estética Canina</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;600;700&display=swap" rel="stylesheet">
    <style><?php echo css_app(); ?></style>
</head>
<body class="pagina-login">

<div class="tarjeta-formulario login-caja">
    <h2>🐾 Bienvenida(o)</h2>
    <p class="subtitulo">Sistema de la Veterinaria y Estética Canina "Los Ángeles"</p>

    <?php if ($error !== ''): ?>
        <div class="alerta alerta-error" role="alert">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <form action="index.php?p=login" method="POST" autocomplete="on">
        <div class="form-grupo campo-login">
            <label for="usuario">👤 Usuario</label>
            <input type="text" id="usuario" name="usuario" class="input-control" value="<?= e($_POST['usuario'] ?? '') ?>"
                   placeholder="Escribe tu usuario" required autofocus
                   data-vacio="Escribe tu usuario">
        </div>
        <div class="form-grupo campo-login">
            <label for="password">🔒 Contraseña</label>
            <input type="password" id="password" name="password" class="input-control"
                   placeholder="Escribe tu contraseña" required data-vacio="Escribe tu contraseña">
            <label class="mostrar-clave"><input type="checkbox" id="ver-clave"> Mostrar la contraseña mientras escribo</label>
        </div>
        <button type="submit" class="boton boton-rosa boton-grande">Entrar al sistema</button>
    </form>
</div>

<script><?php echo js_app(); ?></script>
</body>
</html>
<?php
}

function pg_logout() {
    // Cierra la sesión y regresa a la pantalla de entrada
    $_SESSION = [];
    session_destroy();
    header("Location: index.php?p=login");
    exit();
}

function pg_pacientes() {
    global $pdo;
exigir('pacientes.ver');

$q = trim($_GET['q'] ?? '');
$sql = "SELECT p.*,
          (SELECT COUNT(*) FROM hojas_estetica h WHERE h.paciente_id = p.id) AS n_hojas,
          (SELECT COUNT(*) FROM citas_medicas c WHERE c.paciente_id = p.id) AS n_citas
        FROM pacientes p ";
$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE p.nombre LIKE ? OR p.dueno_nombre LIKE ? OR p.dueno_telefono LIKE ? OR p.raza LIKE ? ";
    $params = ["%$q%", "%$q%", "%$q%", "%$q%"];
}
$total = $pdo->prepare("SELECT COUNT(*) FROM pacientes p " . $where);
$total->execute($params);
list($pagina, $paginas, $desde, $por_pagina) = paginar((int)$total->fetchColumn());
$stmt = $pdo->prepare($sql . $where . "ORDER BY p.nombre ASC LIMIT $por_pagina OFFSET $desde");
$stmt->execute($params);
$pacientes = $stmt->fetchAll();

vista_header();
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">🐕</span> Pacientes</h1>
        <p class="subtitulo">Aquí se registra cada mascota <strong>una sola vez</strong>. Después se le puede hacer una hoja de estética, una cita médica, o las dos.</p>
    </div>
    <?php if (puede('pacientes.crear')): ?>
    <a href="index.php?p=paciente_nuevo" class="boton boton-rosa boton-grande-auto"><span class="emoji">➕</span> Registrar paciente nuevo</a>
    <?php endif; ?>
</div>

<form method="GET" class="buscador-form" role="search">
    <input type="hidden" name="p" value="pacientes">
    <input type="text" name="q" value="<?= e($q) ?>" class="input-control" style="flex:1;"
           placeholder="Buscar por nombre de la mascota, del dueño, teléfono o raza" aria-label="Buscar paciente">
    <button type="submit" class="boton boton-secundario"><span class="emoji">🔍</span> Buscar</button>
    <?php if ($q !== ''): ?><a href="index.php?p=pacientes" class="boton boton-secundario">Ver todos</a><?php endif; ?>
</form>

<div class="tabla-envoltura">
    <table class="tabla">
        <thead>
            <tr><th>Mascota</th><th>Dueño y teléfono</th><th>Historial</th><th>¿Qué quieres hacer?</th></tr>
        </thead>
        <tbody>
            <?php foreach ($pacientes as $p): ?>
            <tr>
                <td class="nombre-mascota"><span class="emoji">🐾</span> <?= e($p['nombre']) ?><br><small class="suave normal"><?= e($p['raza'] ?: 'Sin raza') ?></small></td>
                <td>👤 <?= e($p['dueno_nombre']) ?><br><small class="suave">📞 <?= telefono_mx($p['dueno_telefono']) ?></small></td>
                <td><small>✂️ <?= (int)$p['n_hojas'] ?> de estética<br>🩺 <?= (int)$p['n_citas'] ?> cita(s) médica(s)</small></td>
                <td>
                    <div class="acciones">
                        <a href="index.php?p=paciente_detalle&id=<?= (int)$p['id'] ?>" class="boton-texto">👁️ Ver ficha</a>
                        <?php if (puede('pacientes.editar')): ?><a href="index.php?p=paciente_nuevo&id=<?= (int)$p['id'] ?>" class="boton-texto">✏️ Editar</a><?php endif; ?>
                        <?php if (puede('estetica.crear')): ?><a href="index.php?p=estetica_nueva&paciente_id=<?= (int)$p['id'] ?>" class="boton-texto">✂️ Estética</a><?php endif; ?>
                        <?php if (puede('citas.crear')): ?><a href="index.php?p=citas&paciente_id=<?= (int)$p['id'] ?>#nueva-cita" class="boton-texto">🩺 Cita</a><?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (count($pacientes) === 0): ?>
            <tr><td colspan="4" class="vacio">
                <?php if ($q !== ''): ?>
                    No encontramos ningún paciente con "<strong><?= e($q) ?></strong>". Prueba con otra palabra o toca "Ver todos".
                <?php else: ?>
                    Todavía no hay pacientes. Toca <strong>"Registrar paciente nuevo"</strong> para empezar.
                <?php endif; ?>
            </td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php mostrar_paginacion($pagina, $paginas); ?>

<?php vista_footer(); ?>
<?php
}

function pg_paciente_nuevo() {
    global $pdo;

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
        header("Location: index.php?p=pacientes");
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
                preparar_uploads();
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
            if (!$editando && $para === 'estetica') { header("Location: index.php?p=estetica_nueva&paciente_id=" . $nuevo_id); }
            elseif (!$editando && $para === 'cita') { header("Location: index.php?p=citas&paciente_id=" . $nuevo_id . "#nueva-cita"); }
            else { header("Location: index.php?p=paciente_detalle&id=" . $nuevo_id); }
            exit();
        }
    }
}

vista_header();
$volver = $editando ? "index.php?p=paciente_detalle&id=" . (int)$id : "index.php?p=pacientes";
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

<?php vista_footer(); ?>
<?php
}

function pg_paciente_detalle() {
    global $pdo;
exigir('pacientes.ver');

$id = $_GET['id'] ?? ($_POST['id'] ?? '');
if (!ctype_digit((string)$id)) {
    header("Location: index.php?p=pacientes");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM pacientes WHERE id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) {
    flash('error', "No encontramos a ese paciente. Puede que ya se haya eliminado.");
    header("Location: index.php?p=pacientes");
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
    if (!puede('pacientes.eliminar')) {
        flash('error', "No tienes permiso para eliminar pacientes.");
    } elseif (!csrf_ok()) {
        flash('error', "La pantalla estuvo abierta mucho tiempo. Intenta otra vez.");
    } elseif (count($hojas) > 0 || count($citas) > 0) {
        flash('error', $p['nombre'] . " no se puede eliminar porque tiene historial (" . count($hojas) . " hoja(s) de estética y " . count($citas) . " cita(s) médica(s)). Esto protege los expedientes.");
    } else {
        try {
            $pdo->prepare("DELETE FROM pacientes WHERE id = ?")->execute([$id]);
            if (!empty($p['foto']) && file_exists('uploads/' . basename($p['foto']))) { unlink('uploads/' . basename($p['foto'])); }
            flash('ok', "Se eliminó a " . $p['nombre'] . " de la lista de pacientes.");
            header("Location: index.php?p=pacientes");
            exit();
        } catch (PDOException $ex) {
            flash('error', "No se pudo eliminar. Intenta de nuevo.");
        }
    }
    header("Location: index.php?p=paciente_detalle&id=" . (int)$id);
    exit();
}

vista_header();
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">📋</span> <?= e($p['nombre']) ?></h1>
        <p class="subtitulo">Ficha del paciente</p>
    </div>
    <div class="acciones">
        <a href="index.php?p=pacientes" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver</a>
        <?php if (puede('pacientes.editar')): ?><a href="index.php?p=paciente_nuevo&id=<?= (int)$p['id'] ?>" class="boton boton-secundario"><span class="emoji">✏️</span> Corregir datos</a><?php endif; ?>
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
            <span class="valor chico"><?= e($p['dueno_nombre']) ?> (📞 <?= telefono_mx($p['dueno_telefono']) ?>)</span>
        </div>
        <div class="detalle-item full-width">
            <span class="label">🏠 Domicilio</span>
            <span class="valor chico"><?= e($p['domicilio'] ?: 'No registrado') ?></span>
        </div>
        <div class="detalle-item full-width">
            <span class="label">➕ Nuevo registro para <?= e($p['nombre']) ?></span>
            <div class="acciones" style="margin-top:8px;">
                <?php if (puede('estetica.crear')): ?><a href="index.php?p=estetica_nueva&paciente_id=<?= (int)$p['id'] ?>" class="boton boton-rosa"><span class="emoji">✂️</span> Hoja de estética</a><?php endif; ?>
                <?php if (puede('citas.crear')): ?><a href="index.php?p=citas&paciente_id=<?= (int)$p['id'] ?>#nueva-cita" class="boton boton-rosa"><span class="emoji">🩺</span> Cita médica</a><?php endif; ?>
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
            <p><a href="index.php?p=estetica_detalle&id=<?= (int)$h['id'] ?>" class="boton-texto">#<?= e($h['folio']) ?></a>
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
<?php if (puede('pacientes.eliminar')): ?>
<div style="margin-top:28px;">
    <form method="POST" data-confirmar="¿Seguro que quieres eliminar a <?= e($p['nombre']) ?> de la lista de pacientes? Esto no se puede deshacer.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="eliminar">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button type="submit" class="boton boton-peligro"><span class="emoji">🗑️</span> Eliminar paciente</button>
        <small class="ayuda">Solo se puede eliminar si no tiene hojas de estética ni citas médicas.</small>
    </form>
</div>
<?php endif; ?>

<?php vista_footer(); ?>
<?php
}

function pg_estetica() {
    global $pdo;
exigir('estetica.ver');

$q = trim($_GET['q'] ?? '');
$sql = "SELECT h.*, p.nombre, p.raza, p.dueno_nombre, p.dueno_telefono
        FROM hojas_estetica h JOIN pacientes p ON h.paciente_id = p.id ";
$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE p.nombre LIKE ? OR p.dueno_nombre LIKE ? OR h.folio LIKE ? OR p.dueno_telefono LIKE ? ";
    $params = ["%$q%", "%$q%", "%$q%", "%$q%"];
}
$total = $pdo->prepare("SELECT COUNT(*) FROM hojas_estetica h JOIN pacientes p ON h.paciente_id = p.id " . $where);
$total->execute($params);
list($pagina, $paginas, $desde, $por_pagina) = paginar((int)$total->fetchColumn());
$stmt = $pdo->prepare($sql . $where . "ORDER BY h.id DESC LIMIT $por_pagina OFFSET $desde");
$stmt->execute($params);
$hojas = $stmt->fetchAll();

vista_header();
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">✂️</span> Estética Canina</h1>
        <p class="subtitulo">Cada fila es una visita a estética. Toca <strong>"Ver hoja"</strong> para ver el detalle.</p>
    </div>
    <?php if (puede('estetica.crear')): ?>
    <a href="index.php?p=estetica_nueva" class="boton boton-rosa boton-grande-auto"><span class="emoji">➕</span> Nueva hoja de estética</a>
    <?php endif; ?>
</div>

<form method="GET" class="buscador-form" role="search">
    <input type="hidden" name="p" value="estetica">
    <input type="text" name="q" value="<?= e($q) ?>" class="input-control" style="flex:1;"
           placeholder="Buscar por mascota, dueño, folio o teléfono" aria-label="Buscar hoja de estética">
    <button type="submit" class="boton boton-secundario"><span class="emoji">🔍</span> Buscar</button>
    <?php if ($q !== ''): ?><a href="index.php?p=estetica" class="boton boton-secundario">Ver todas</a><?php endif; ?>
</form>

<div class="tabla-envoltura">
    <table class="tabla">
        <thead>
            <tr><th>Folio</th><th>Fecha y hora</th><th>Mascota</th><th>Dueño y teléfono</th><th>Servicio</th><th>Total y saldo</th><th>¿Qué quieres hacer?</th></tr>
        </thead>
        <tbody>
            <?php foreach ($hojas as $h): ?>
            <tr>
                <td><strong>#<?= e($h['folio']) ?></strong></td>
                <td><?= fecha_mx($h['fecha']) ?><br><small class="suave"><?= e($h['hora_llegada']) ?></small></td>
                <td class="nombre-mascota"><span class="emoji">🐾</span> <a href="index.php?p=paciente_detalle&id=<?= (int)$h['paciente_id'] ?>"><?= e($h['nombre']) ?></a><br><small class="suave normal"><?= e($h['raza']) ?></small></td>
                <td>👤 <?= e($h['dueno_nombre']) ?><br><small class="suave">📞 <?= telefono_mx($h['dueno_telefono']) ?></small></td>
                <td><?= e($h['servicios'] ?: 'Sin servicio marcado') ?></td>
                <td><strong><?= dinero($h['costo_total']) ?></strong><br>
                    <?php $debe = saldo_pendiente($h['costo_total'], $h['a_cuenta']); ?>
                    <small class="suave"><?= $debe > 0 ? 'Debe ' . dinero($debe) : '✅ Pagada' ?></small></td>
                <td><div class="acciones">
                    <a href="index.php?p=estetica_detalle&id=<?= (int)$h['id'] ?>" class="boton-texto"><span class="emoji">👁️</span> Ver hoja</a>
                    <?php if (puede('estetica.editar')): ?><a href="index.php?p=estetica_nueva&id=<?= (int)$h['id'] ?>" class="boton-texto">✏️ Editar</a><?php endif; ?>
                </div></td>
            </tr>
            <?php endforeach; ?>
            <?php if (count($hojas) === 0): ?>
            <tr><td colspan="7" class="vacio">
                <?php if ($q !== ''): ?>
                    No encontramos hojas con "<strong><?= e($q) ?></strong>". Prueba con otra palabra o toca "Ver todas".
                <?php else: ?>
                    Todavía no hay hojas de estética. Toca <strong>"Nueva hoja de estética"</strong> para empezar.
                <?php endif; ?>
            </td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php mostrar_paginacion($pagina, $paginas); ?>

<?php vista_footer(); ?>
<?php
}

function pg_estetica_nueva() {
    global $pdo;

// Si viene un id, estamos EDITANDO una hoja que ya existe
$id = $_GET['id'] ?? ($_POST['id'] ?? '');
$editando = ctype_digit((string)$id);
exigir($editando ? 'estetica.editar' : 'estetica.crear');
$actual = null;
if ($editando) {
    $s = $pdo->prepare("SELECT * FROM hojas_estetica WHERE id = ?");
    $s->execute([$id]);
    $actual = $s->fetch();
    if (!$actual) {
        flash('error', "No encontramos esa hoja. Puede que ya se haya eliminado.");
        header("Location: index.php?p=estetica");
        exit();
    }
}

$servicios_validos = ['Baño', 'Deslanado', 'Corte', 'Limpieza de oídos', 'Corte de uñas'];
$pagos_validos = ['Efectivo', 'Tarjeta', 'Transferencia', 'Otro'];


$v = [
    'paciente_id' => '', 'fecha' => date('Y-m-d'), 'hora_llegada' => '', 'hora_salida' => '',
    'detalles_corte' => '', 'observaciones' => '', 'costo_total' => '', 'a_cuenta' => '',
    'forma_pago' => 'Efectivo', 'servicios' => []
];
if ($actual) {
    foreach ($v as $campo => $_) {
        if ($campo === 'servicios') { $v['servicios'] = array_values(array_intersect($servicios_validos, array_map('trim', explode(',', (string)$actual['servicios'])))); }
        else { $v[$campo] = (string)($actual[$campo] ?? ''); }
    }
    $v['costo_total'] = rtrim(rtrim($v['costo_total'], '0'), '.');
    $v['a_cuenta'] = rtrim(rtrim($v['a_cuenta'], '0'), '.');
}
// Si venimos desde la ficha del paciente, ya viene elegido
if (!$editando && isset($_GET['paciente_id']) && ctype_digit($_GET['paciente_id'])) { $v['paciente_id'] = $_GET['paciente_id']; }
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
            if ($editando) {
                // El folio NO cambia al editar
                $stmt = $pdo->prepare("UPDATE hojas_estetica SET paciente_id=?, fecha=?, hora_llegada=?, hora_salida=?, servicios=?, detalles_corte=?, observaciones=?, costo_total=?, a_cuenta=?, forma_pago=? WHERE id=?");
                $stmt->execute([$paciente['id'], $v['fecha'], $v['hora_llegada'], $v['hora_salida'], implode(', ', $v['servicios']),
                    $v['detalles_corte'], $v['observaciones'], $costo, $cuenta, $v['forma_pago'], $id]);
                flash('ok', "Se actualizó la hoja #" . $actual['folio'] . " de " . $paciente['nombre'] . ".");
                header("Location: index.php?p=estetica_detalle&id=" . (int)$id);
                exit();
            }
            $folio = siguiente_folio($pdo);
            $stmt = $pdo->prepare("INSERT INTO hojas_estetica (folio, paciente_id, fecha, hora_llegada, hora_salida, servicios, detalles_corte, observaciones, costo_total, a_cuenta, forma_pago)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$folio, $paciente['id'], $v['fecha'], $v['hora_llegada'], $v['hora_salida'], implode(', ', $v['servicios']),
                $v['detalles_corte'], $v['observaciones'], $costo, $cuenta, $v['forma_pago']]);
            flash('ok', "¡Listo! Se guardó la hoja de " . $paciente['nombre'] . " con el folio #" . $folio . ".");
            header("Location: index.php?p=estetica");
            exit();
        } catch (PDOException $ex) {
            $errores[] = "No se pudo guardar por un problema del sistema. Intenta de nuevo en un momento.";
        }
    }
}

$pacientes_lista = $pdo->query("SELECT id, nombre, dueno_nombre FROM pacientes ORDER BY nombre ASC")->fetchAll();
$folio_mostrar = $editando ? $actual['folio'] : siguiente_folio($pdo);
vista_header();
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_purple.css">

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">📝</span> <?= $editando ? 'Editar hoja de estética' : 'Hoja de estética' ?></h1>
        <p class="subtitulo">Elige a la mascota y llena lo que se le va a hacer hoy. Los campos con <strong>*</strong> son obligatorios.</p>
    </div>
    <a href="<?= $editando ? 'index.php?p=estetica_detalle&id=' . (int)$id : 'index.php?p=estetica' ?>" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver</a>
</div>

<?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert">
        <strong>⚠️ Revisa esto antes de guardar:</strong>
        <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="tarjeta-formulario">
<?php if (count($pacientes_lista) === 0): ?>
    <p class="vacio">Para hacer una hoja primero hay que <a href="index.php?p=paciente_nuevo&para=estetica" class="boton-texto">registrar al paciente</a>.</p>
<?php else: ?>
    <form method="POST" class="form-secciones" data-evitar-doble="1">
        <?= csrf_field() ?>
        <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

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
                    <small class="ayuda">Sus datos y los del dueño ya están guardados. ¿No aparece? <a href="index.php?p=paciente_nuevo&para=estetica" class="boton-texto">Registrar paciente nuevo</a></small>
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

        <button type="submit" class="boton boton-rosa boton-grande"><span class="emoji">💾</span> <?= $editando ? 'Guardar cambios' : 'Guardar hoja de estética' ?></button>
    </form>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<?php vista_footer(); ?>
<?php
}

function pg_estetica_detalle() {
    global $pdo;
exigir('estetica.ver');

$id = $_GET['id'] ?? ($_POST['id'] ?? '');
if (!ctype_digit((string)$id)) {
    header("Location: index.php?p=estetica");
    exit();
}

// Eliminar SOLO esta hoja (el paciente y sus citas médicas no se tocan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'eliminar') {
    if (!puede('estetica.eliminar')) {
        flash('error', "No tienes permiso para eliminar hojas de estética.");
        header("Location: index.php?p=estetica_detalle&id=" . (int)$id);
        exit();
    }
    if (!csrf_ok()) {
        flash('error', "La pantalla estuvo abierta mucho tiempo. Intenta otra vez.");
        header("Location: index.php?p=estetica_detalle&id=" . (int)$id);
        exit();
    }
    $pdo->prepare("DELETE FROM hojas_estetica WHERE id = ?")->execute([$id]);
    flash('ok', "Se eliminó la hoja de estética. El paciente sigue registrado.");
    header("Location: index.php?p=estetica");
    exit();
}

$stmt = $pdo->prepare("SELECT h.*, p.nombre, p.raza, p.sexo, p.color, p.edad, p.dueno_nombre, p.dueno_telefono, p.domicilio, p.foto
                       FROM hojas_estetica h JOIN pacientes p ON h.paciente_id = p.id WHERE h.id = ?");
$stmt->execute([$id]);
$h = $stmt->fetch();
if (!$h) {
    flash('error', "No encontramos esa hoja. Puede que ya se haya eliminado.");
    header("Location: index.php?p=estetica");
    exit();
}

$total = (float)($h['costo_total'] ?? 0);
$cuenta = (float)($h['a_cuenta'] ?? 0);
$resta = saldo_pendiente($total, $cuenta);

vista_header();
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">✂️</span> Hoja de <?= e($h['nombre']) ?></h1>
        <p class="subtitulo">Folio #<?= e($h['folio']) ?> · Fecha: <?= fecha_mx($h['fecha']) ?></p>
    </div>
    <div class="acciones">
        <a href="index.php?p=estetica" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver a la lista</a>
        <?php if (puede('estetica.editar')): ?><a href="index.php?p=estetica_nueva&id=<?= (int)$h['id'] ?>" class="boton boton-secundario"><span class="emoji">✏️</span> Editar hoja</a><?php endif; ?>
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
        <a href="index.php?p=paciente_detalle&id=<?= (int)$h['paciente_id'] ?>" class="boton-texto">Ver ficha completa del paciente</a>
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

<?php vista_footer(); ?>
<?php
}

function pg_citas() {
    global $pdo;
exigir('citas.ver');

$errores = [];
$v = ['paciente_id' => '', 'fecha_cita' => '', 'hora_cita' => '', 'motivo' => ''];
// Si venimos desde la ficha del paciente, ya viene elegido
if (isset($_GET['paciente_id']) && ctype_digit($_GET['paciente_id'])) { $v['paciente_id'] = $_GET['paciente_id']; }

// ----- Registrar una cita nueva -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'crear_cita') {
    if (!puede('citas.crear')) {
        $errores[] = "No tienes permiso para agendar citas.";
    } elseif (!csrf_ok()) {
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
            header("Location: index.php?p=citas");
            exit();
        } catch (PDOException $ex) {
            $errores[] = "No se pudo guardar la cita por un problema del sistema. Intenta de nuevo.";
        }
    }
}

// ----- Guardar diagnóstico (solo el veterinario) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'guardar_diagnostico') {
    if (!puede('citas.diagnostico')) {
        flash('error', "No tienes permiso para escribir diagnósticos.");
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
    header("Location: index.php?p=citas");
    exit();
}

// ----- Eliminar una cita -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'eliminar_cita') {
    if (!puede('citas.eliminar')) {
        flash('error', "No tienes permiso para eliminar citas.");
    } elseif (!csrf_ok()) {
        flash('error', "La pantalla estuvo abierta mucho tiempo. Intenta otra vez.");
    } elseif (!ctype_digit((string)($_POST['cita_id'] ?? ''))) {
        flash('error', "No se encontró la cita.");
    } else {
        $pdo->prepare("DELETE FROM citas_medicas WHERE id = ?")->execute([$_POST['cita_id']]);
        flash('ok', "Se eliminó la cita. El paciente sigue registrado.");
    }
    header("Location: index.php?p=citas");
    exit();
}

// ----- Buscar citas (las pendientes salen primero) + filtro por estado + páginas -----
$busqueda = trim($_GET['busqueda'] ?? '');
$estado = $_GET['estado'] ?? '';
if (!in_array($estado, ['Pendiente', 'Atendida'], true)) { $estado = ''; }
$sql = "SELECT c.*, p.nombre AS mascota_nombre, p.dueno_nombre, p.dueno_telefono
        FROM citas_medicas c JOIN pacientes p ON c.paciente_id = p.id ";
$condiciones = [];
$params = [];
if ($busqueda !== '') {
    $condiciones[] = "(p.nombre LIKE ? OR p.dueno_nombre LIKE ? OR c.motivo LIKE ?)";
    array_push($params, "%$busqueda%", "%$busqueda%", "%$busqueda%");
}
if ($estado !== '') { $condiciones[] = "c.estatus = ?"; $params[] = $estado; }
$where = $condiciones ? "WHERE " . implode(' AND ', $condiciones) . " " : "";
$total = $pdo->prepare("SELECT COUNT(*) FROM citas_medicas c JOIN pacientes p ON c.paciente_id = p.id " . $where);
$total->execute($params);
list($pagina, $paginas, $desde, $por_pagina) = paginar((int)$total->fetchColumn(), 8);
$stmt = $pdo->prepare($sql . $where . "ORDER BY (c.estatus = 'Atendida'), c.fecha_cita ASC LIMIT $por_pagina OFFSET $desde");
$stmt->execute($params);
$citas = $stmt->fetchAll();

$pacientes_lista = $pdo->query("SELECT id, nombre, dueno_nombre FROM pacientes ORDER BY nombre ASC")->fetchAll();

vista_header();
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
<?php if (puede('citas.crear')): ?>
<div class="tarjeta-formulario" id="nueva-cita" style="margin-bottom:30px;">
    <h3 style="margin-top:0;">📅 Agendar una cita nueva</h3>
    <?php if (count($pacientes_lista) === 0): ?>
        <p class="vacio">Para agendar una cita primero hay que <a href="index.php?p=paciente_nuevo&para=cita" class="boton-texto">registrar al paciente</a>.</p>
    <?php else: ?>
    <form action="index.php?p=citas" method="POST" class="grid-form" data-evitar-doble="1">
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
            <small class="ayuda">Sus datos ya están guardados. ¿No aparece? <a href="index.php?p=paciente_nuevo&para=cita" class="boton-texto">Registrar paciente nuevo</a></small>
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

<?php endif; ?>

<!-- Buscador de citas -->
<form method="GET" class="buscador-form" role="search">
    <input type="hidden" name="p" value="citas">
    <input type="text" name="busqueda" value="<?= e($busqueda) ?>" class="input-control" style="flex:1;"
           placeholder="Buscar cita por mascota, dueño o motivo" aria-label="Buscar cita">
    <select name="estado" class="input-control" style="max-width:200px;" aria-label="Filtrar por estado">
        <option value="">Todas</option>
        <option value="Pendiente" <?= $estado === 'Pendiente' ? 'selected' : '' ?>>⏳ Pendientes</option>
        <option value="Atendida" <?= $estado === 'Atendida' ? 'selected' : '' ?>>✅ Atendidas</option>
    </select>
    <button type="submit" class="boton boton-secundario"><span class="emoji">🔍</span> Buscar</button>
    <?php if ($busqueda !== '' || $estado !== ''): ?><a href="index.php?p=citas" class="boton boton-secundario">Ver todas</a><?php endif; ?>
</form>

<h3>📋 Citas y expedientes</h3>
<div style="display:grid; gap:20px; margin-top:15px;">
    <?php if (empty($citas)): ?>
        <p class="vacio">
            <?= ($busqueda !== '' || $estado !== '') ? 'No encontramos citas con esa búsqueda.' : 'Todavía no hay citas registradas.' ?>
        </p>
    <?php else: foreach ($citas as $c): $atendida = ($c['estatus'] === 'Atendida'); ?>
        <div class="tarjeta-formulario" style="padding:20px;">
            <h4 style="margin-top:0;">🐾 <a href="index.php?p=paciente_detalle&id=<?= (int)$c['paciente_id'] ?>"><?= e($c['mascota_nombre']) ?></a> · Dueño: <?= e($c['dueno_nombre']) ?> (📞 <?= telefono_mx($c['dueno_telefono']) ?>)</h4>
            <p><strong>Cuándo:</strong> <?= fecha_mx($c['fecha_cita']) ?> a las <?= e($c['hora_cita']) ?></p>
            <p><strong>Motivo:</strong> <?= e($c['motivo']) ?></p>
            <p><strong>Estado:</strong> <span class="etiqueta <?= $atendida ? 'etiqueta-ok' : 'etiqueta-pendiente' ?>"><?= $atendida ? '✅ Atendida' : '⏳ Pendiente' ?></span></p>
            <?php if (puede('citas.editar') || puede('citas.eliminar')): ?>
            <div class="acciones" style="margin-bottom:6px;">
                <?php if (puede('citas.editar')): ?><a href="index.php?p=cita_editar&id=<?= (int)$c['id'] ?>" class="boton boton-secundario boton-pequeno">✏️ Editar cita</a><?php endif; ?>
                <?php if (puede('citas.eliminar')): ?>
                <form method="POST" data-confirmar="¿Seguro que quieres eliminar esta cita? Esto no se puede deshacer." style="margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="eliminar_cita">
                    <input type="hidden" name="cita_id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="boton boton-peligro boton-pequeno">🗑️ Eliminar</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <hr class="separador">
            <h5 style="margin:0 0 8px;">🩺 Diagnóstico del veterinario</h5>
            <?php if (puede('citas.diagnostico')): ?>
                <form action="index.php?p=citas" method="POST" data-evitar-doble="1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="guardar_diagnostico">
                    <input type="hidden" name="cita_id" value="<?= (int)$c['id'] ?>">
                    <textarea name="diagnostico" rows="3" maxlength="2000" class="input-control" required data-vacio="Escribe el diagnóstico antes de guardar"
                              placeholder="Escribe aquí el diagnóstico y el tratamiento..."><?= e($c['diagnostico'] ?? '') ?></textarea>
                    <button type="submit" class="boton boton-rosa" style="margin-top:10px;">💾 Guardar diagnóstico</button>
                </form>
            <?php else: ?>
                <div class="valor-box">
                    <?= !empty($c['diagnostico']) ? nl2br(e($c['diagnostico'])) : '<em>Todavía no hay diagnóstico. Solo quien tenga permiso de diagnóstico puede escribirlo.</em>' ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php mostrar_paginacion($pagina, $paginas); ?>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<?php vista_footer(); ?>
<?php
}

function pg_cita_editar() {
    global $pdo;
exigir('citas.editar');

$id = $_GET['id'] ?? ($_POST['id'] ?? '');
if (!ctype_digit((string)$id)) { header("Location: index.php?p=citas"); exit(); }
$s = $pdo->prepare("SELECT * FROM citas_medicas WHERE id = ?");
$s->execute([$id]);
$actual = $s->fetch();
if (!$actual) {
    flash('error', "No encontramos esa cita. Puede que ya se haya eliminado.");
    header("Location: index.php?p=citas");
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
    if (!es_fecha($v['fecha_cita'])) { $errores[] = "Elige la fecha de la cita en el calendario."; }
    if (!hora_valida($v['hora_cita'])) { $errores[] = "Elige la hora de la cita en el reloj."; }
    if (largo($v['motivo']) < 3) { $errores[] = "Escribe el motivo de la cita (por ejemplo: vacuna, revisión)."; }
    elseif (largo($v['motivo']) > 500) { $errores[] = "El motivo es muy largo (máximo 500 letras)."; }
    if (!in_array($v['estatus'], $estados, true)) { $errores[] = "Elige si la cita está pendiente o atendida."; }

    if (!$errores) {
        try {
            $stmt = $pdo->prepare("UPDATE citas_medicas SET paciente_id=?, fecha_cita=?, hora_cita=?, motivo=?, estatus=? WHERE id=?");
            $stmt->execute([$v['paciente_id'], $v['fecha_cita'], $v['hora_cita'], $v['motivo'], $v['estatus'], $id]);
            flash('ok', "Se actualizó la cita del " . fecha_mx($v['fecha_cita']) . ".");
            header("Location: index.php?p=citas");
            exit();
        } catch (PDOException $ex) {
            $errores[] = "No se pudo guardar por un problema del sistema. Intenta de nuevo.";
        }
    }
}

$pacientes_lista = $pdo->query("SELECT id, nombre, dueno_nombre FROM pacientes ORDER BY nombre ASC")->fetchAll();
vista_header();
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_purple.css">

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">✏️</span> Editar cita médica</h1>
        <p class="subtitulo">Corrige los datos de la cita. El diagnóstico se escribe en la lista de citas.</p>
    </div>
    <a href="index.php?p=citas" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver</a>
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

<?php vista_footer(); ?>
<?php
}

function pg_usuarios() {
    global $pdo;
exigir('usuarios.ver');

// ----- Acciones: activar/desactivar y eliminar -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';
    $uid = $_POST['usuario_id'] ?? '';
    $permiso = ($accion === 'eliminar') ? 'usuarios.eliminar' : 'usuarios.editar';
    if (!puede($permiso)) {
        flash('error', "No tienes permiso para hacer esto.");
    } elseif (!csrf_ok()) {
        flash('error', "La pantalla estuvo abierta mucho tiempo. Intenta otra vez.");
    } elseif (!ctype_digit((string)$uid) || !in_array($accion, ['eliminar', 'alternar'], true)) {
        flash('error', "No se encontró al usuario.");
    } elseif ((int)$uid === (int)$_SESSION['usuario_id']) {
        flash('error', "No puedes desactivar ni eliminar tu propio usuario (te quedarías sin acceso).");
    } else {
        if ($accion === 'eliminar') {
            $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$uid]);
            flash('ok', "Se eliminó al usuario.");
        } else {
            $pdo->prepare("UPDATE usuarios SET activo = 1 - activo WHERE id = ?")->execute([$uid]);
            flash('ok', "Se cambió el estado del usuario.");
        }
    }
    header("Location: index.php?p=usuarios");
    exit();
}

// ----- Lista con búsqueda y páginas -----
$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($q !== '') {
    $where = "WHERE u.nombre LIKE ? OR u.usuario LIKE ? OR r.nombre LIKE ? ";
    $params = ["%$q%", "%$q%", "%$q%"];
}
$total = $pdo->prepare("SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id = u.rol_id " . $where);
$total->execute($params);
list($pagina, $paginas, $desde, $por_pagina) = paginar((int)$total->fetchColumn());
$stmt = $pdo->prepare("SELECT u.*, r.nombre AS rol_nombre FROM usuarios u JOIN roles r ON r.id = u.rol_id " . $where . "ORDER BY u.nombre ASC LIMIT $por_pagina OFFSET $desde");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

vista_header();
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">👥</span> Usuarios</h1>
        <p class="subtitulo">Las personas que pueden entrar al sistema. Cada una tiene un <strong>rol</strong> que decide qué puede hacer.</p>
    </div>
    <?php if (puede('usuarios.crear')): ?>
    <a href="index.php?p=usuario_form" class="boton boton-rosa boton-grande-auto"><span class="emoji">➕</span> Crear usuario</a>
    <?php endif; ?>
</div>

<form method="GET" class="buscador-form" role="search">
    <input type="hidden" name="p" value="usuarios">
    <input type="text" name="q" value="<?= e($q) ?>" class="input-control" style="flex:1;" placeholder="Buscar por nombre, usuario o rol" aria-label="Buscar usuario">
    <button type="submit" class="boton boton-secundario"><span class="emoji">🔍</span> Buscar</button>
    <?php if ($q !== ''): ?><a href="index.php?p=usuarios" class="boton boton-secundario">Ver todos</a><?php endif; ?>
</form>

<div class="tabla-envoltura">
    <table class="tabla">
        <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>¿Qué quieres hacer?</th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u): $soy_yo = ((int)$u['id'] === (int)$_SESSION['usuario_id']); ?>
            <tr>
                <td>👤 <?= e($u['nombre']) ?><?= $soy_yo ? ' <small class="suave">(tú)</small>' : '' ?></td>
                <td><?= e($u['usuario']) ?></td>
                <td>🎭 <?= e($u['rol_nombre']) ?></td>
                <td><span class="etiqueta <?= $u['activo'] ? 'etiqueta-ok' : 'etiqueta-gris' ?>"><?= $u['activo'] ? '✅ Activo' : '⛔ Desactivado' ?></span></td>
                <td>
                    <div class="acciones">
                        <?php if (puede('usuarios.editar')): ?><a href="index.php?p=usuario_form&id=<?= (int)$u['id'] ?>" class="boton-texto">✏️ Editar</a><?php endif; ?>
                        <?php if (puede('usuarios.editar') && !$soy_yo): ?>
                        <form method="POST" style="margin:0;" data-confirmar="¿<?= $u['activo'] ? 'Desactivar' : 'Activar' ?> a <?= e($u['nombre']) ?>?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="alternar"><input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="boton-texto" style="border:0;background:none;cursor:pointer;"><?= $u['activo'] ? '⛔ Desactivar' : '✅ Activar' ?></button>
                        </form>
                        <?php endif; ?>
                        <?php if (puede('usuarios.eliminar') && !$soy_yo): ?>
                        <form method="POST" style="margin:0;" data-confirmar="¿Seguro que quieres eliminar a <?= e($u['nombre']) ?>? Esto no se puede deshacer.">
                            <?= csrf_field() ?><input type="hidden" name="action" value="eliminar"><input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="boton-texto" style="border:0;background:none;cursor:pointer;color:#8f1d1d;">🗑️ Eliminar</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (count($usuarios) === 0): ?>
            <tr><td colspan="5" class="vacio">No encontramos usuarios<?= $q !== '' ? ' con esa búsqueda' : '' ?>.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php mostrar_paginacion($pagina, $paginas); ?>

<?php vista_footer(); ?>
<?php
}

function pg_usuario_form() {
    global $pdo;

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
        header("Location: index.php?p=usuarios");
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
            header("Location: index.php?p=usuarios");
            exit();
        } catch (PDOException $ex) {
            $errores[] = "No se pudo guardar por un problema del sistema. Intenta de nuevo.";
        }
    }
}

vista_header();
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">👥</span> <?= $editando ? 'Editar usuario' : 'Crear usuario' ?></h1>
        <p class="subtitulo">Los campos con <strong>*</strong> son obligatorios.</p>
    </div>
    <a href="index.php?p=usuarios" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver</a>
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

<?php vista_footer(); ?>
<?php
}

function pg_roles() {
    global $pdo;
exigir('roles.ver');

// ----- Eliminar rol: no se puede si es protegido o si todavía tiene usuarios -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'eliminar') {
    $rid = $_POST['rol_id'] ?? '';
    if (!puede('roles.eliminar')) {
        flash('error', "No tienes permiso para eliminar roles.");
    } elseif (!csrf_ok()) {
        flash('error', "La pantalla estuvo abierta mucho tiempo. Intenta otra vez.");
    } elseif (!ctype_digit((string)$rid)) {
        flash('error', "No se encontró el rol.");
    } else {
        $s = $pdo->prepare("SELECT r.*, (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id) AS n_usuarios FROM roles r WHERE r.id = ?");
        $s->execute([$rid]);
        $r = $s->fetch();
        if (!$r) {
            flash('error', "No se encontró el rol.");
        } elseif ($r['protegido']) {
            flash('error', "El rol " . $r['nombre'] . " está protegido y no se puede eliminar.");
        } elseif ($r['n_usuarios'] > 0) {
            flash('error', "No se puede eliminar " . $r['nombre'] . " porque tiene " . $r['n_usuarios'] . " usuario(s). Cámbiales el rol primero.");
        } else {
            $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$rid]);
            flash('ok', "Se eliminó el rol " . $r['nombre'] . ".");
        }
    }
    header("Location: index.php?p=roles");
    exit();
}

$total_permisos = (int)$pdo->query("SELECT COUNT(*) FROM permisos")->fetchColumn();
$total = (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
list($pagina, $paginas, $desde, $por_pagina) = paginar($total);
$roles = $pdo->query("SELECT r.*,
        (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id) AS n_usuarios,
        (SELECT COUNT(*) FROM rol_permiso rp WHERE rp.rol_id = r.id) AS n_permisos
        FROM roles r ORDER BY r.protegido DESC, r.nombre ASC LIMIT $por_pagina OFFSET $desde")->fetchAll();

vista_header();
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">🎭</span> Roles</h1>
        <p class="subtitulo">Un rol es un "llavero": junta los permisos que tendrá cada tipo de usuario.</p>
    </div>
    <?php if (puede('roles.crear')): ?>
    <a href="index.php?p=rol_form" class="boton boton-rosa boton-grande-auto"><span class="emoji">➕</span> Crear rol</a>
    <?php endif; ?>
</div>

<div class="tabla-envoltura">
    <table class="tabla">
        <thead><tr><th>Rol</th><th>Descripción</th><th>Usuarios</th><th>Permisos</th><th>¿Qué quieres hacer?</th></tr></thead>
        <tbody>
        <?php foreach ($roles as $r): ?>
            <tr>
                <td><strong>🎭 <?= e($r['nombre']) ?></strong><?= $r['protegido'] ? '<br><small class="suave">🔒 Protegido</small>' : '' ?></td>
                <td><?= e($r['descripcion'] ?: '—') ?></td>
                <td><?= (int)$r['n_usuarios'] ?></td>
                <td><?= $r['protegido'] ? 'Todos' : (int)$r['n_permisos'] . ' de ' . $total_permisos ?></td>
                <td>
                    <div class="acciones">
                        <?php if (puede('roles.editar')): ?><a href="index.php?p=rol_form&id=<?= (int)$r['id'] ?>" class="boton-texto">✏️ Editar</a><?php endif; ?>
                        <?php if (puede('permisos.gestionar') && !$r['protegido']): ?><a href="index.php?p=permisos" class="boton-texto">🔑 Permisos</a><?php endif; ?>
                        <?php if (puede('roles.eliminar') && !$r['protegido']): ?>
                        <form method="POST" style="margin:0;" data-confirmar="¿Seguro que quieres eliminar el rol <?= e($r['nombre']) ?>? Esto no se puede deshacer.">
                            <?= csrf_field() ?><input type="hidden" name="action" value="eliminar"><input type="hidden" name="rol_id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="boton-texto" style="border:0;background:none;cursor:pointer;color:#8f1d1d;">🗑️ Eliminar</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php mostrar_paginacion($pagina, $paginas); ?>

<?php vista_footer(); ?>
<?php
}

function pg_rol_form() {
    global $pdo;

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
        header("Location: index.php?p=roles");
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
                header("Location: index.php?p=roles");
            } else {
                $pdo->prepare("INSERT INTO roles (nombre, descripcion) VALUES (?, ?)")->execute([$v['nombre'], $v['descripcion']]);
                flash('ok', "Se creó el rol " . $v['nombre'] . ". Ahora márcale sus permisos.");
                header("Location: index.php?p=permisos");
            }
            exit();
        } catch (PDOException $ex) {
            $errores[] = "No se pudo guardar por un problema del sistema. Intenta de nuevo.";
        }
    }
}

vista_header();
?>

<div class="encabezado-pagina">
    <div>
        <h1><span class="emoji">🎭</span> <?= $editando ? 'Editar rol' : 'Crear rol' ?></h1>
        <p class="subtitulo">Después de crearlo, en <strong>Permisos</strong> eliges qué puede hacer.</p>
    </div>
    <a href="index.php?p=roles" class="boton boton-secundario"><span class="emoji">⬅️</span> Volver</a>
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

<?php vista_footer(); ?>
<?php
}

function pg_permisos() {
    global $pdo;
exigir('permisos.gestionar');

$errores = [];
$nuevo = ['clave' => '', 'nombre' => '', 'modulo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';
    if (!csrf_ok()) {
        flash('error', "La pantalla estuvo abierta mucho tiempo. Intenta otra vez.");
        header("Location: index.php?p=permisos");
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
        header("Location: index.php?p=permisos");
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
            header("Location: index.php?p=permisos");
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
        header("Location: index.php?p=permisos");
        exit();
    }
}

// ----- Datos para dibujar la tabla -----
$roles = $pdo->query("SELECT * FROM roles ORDER BY protegido DESC, nombre ASC")->fetchAll();
$permisos = $pdo->query("SELECT * FROM permisos ORDER BY modulo ASC, id ASC")->fetchAll();
$asignados = [];
foreach ($pdo->query("SELECT rol_id, permiso_id FROM rol_permiso")->fetchAll() as $a) { $asignados[$a['rol_id']][$a['permiso_id']] = true; }

vista_header();
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

<?php vista_footer(); ?>
<?php
}

function pg_sin_permiso() {
    global $pdo;

vista_header();
?>

<div class="tarjeta-formulario" style="text-align:center; padding:50px 20px;">
    <div style="font-size:60px;">🔒</div>
    <h1>No tienes permiso para esta pantalla</h1>
    <p class="suave">Si crees que es un error, pídele al administrador que revise los permisos de tu rol (<strong><?= e($_SESSION['rol_nombre']) ?></strong>).</p>
    <?php $inicio = pagina_inicio(); if ($inicio !== 'index.php?p=sin_permiso'): ?>
        <a href="<?= e($inicio) ?>" class="boton boton-rosa boton-grande-auto">⬅️ Volver al inicio</a>
    <?php endif; ?>
</div>

<?php vista_footer(); ?>
<?php
}


// =====================================================================
// 6. ENRUTADOR   index.php?p=<pagina>
// =====================================================================
session_start();
$pdo = conectar();

$ruta = $_GET['p'] ?? '';
$GLOBALS['ruta'] = $ruta;
$publicas = ['login', 'logout'];
$privadas = ['pacientes', 'paciente_nuevo', 'paciente_detalle', 'estetica', 'estetica_nueva', 'estetica_detalle', 'citas', 'cita_editar', 'usuarios', 'usuario_form', 'roles', 'rol_form', 'permisos', 'sin_permiso'];

if ($ruta === '') {
    // Página principal: manda a la primera pantalla a la que el usuario tiene permiso (sin sesión, al login)
    autenticar();
    header("Location: " . pagina_inicio());
    exit();
}
if (in_array($ruta, $publicas, true)) {
    call_user_func('pg_' . $ruta);
    exit();
}
if (in_array($ruta, $privadas, true)) {
    autenticar();
    call_user_func('pg_' . $ruta);
    exit();
}
// Ruta desconocida: se manda al inicio
header("Location: index.php");
exit();
