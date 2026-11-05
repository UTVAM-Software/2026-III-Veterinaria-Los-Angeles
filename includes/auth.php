<?php
// Este archivo se pone al INICIO de cada página que necesita haber iniciado sesión.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/validar.php';
require_once __DIR__ . '/../config/conexion.php';

// Si no ha iniciado sesión, lo mandamos a la pantalla de entrada
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
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
    header("Location: login.php");
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

// ¿Este usuario tiene la llave (permiso) para hacer algo? Ejemplo: puede('pacientes.eliminar')
function puede($clave) {
    return $GLOBALS['es_admin'] || in_array($clave, $GLOBALS['mis_permisos'], true);
}

// Pone al inicio de una página: si no tiene la llave, lo manda a la pantalla de "sin permiso"
function exigir($clave) {
    if (!puede($clave)) {
        header("Location: sin_permiso.php");
        exit();
    }
}

// Primera pantalla a la que sí puede entrar (para después del login)
function pagina_inicio() {
    $opciones = ['pacientes.ver' => 'pacientes.php', 'estetica.ver' => 'estetica.php', 'citas.ver' => 'citas.php',
                 'usuarios.ver' => 'usuarios.php', 'roles.ver' => 'roles.php', 'permisos.gestionar' => 'permisos.php'];
    foreach ($opciones as $clave => $pagina) {
        if (puede($clave)) return $pagina;
    }
    return 'sin_permiso.php';
}

// "Llave secreta" de la sesión: evita que otra página engañe al sistema para borrar o guardar cosas
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function csrf_ok() {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}
