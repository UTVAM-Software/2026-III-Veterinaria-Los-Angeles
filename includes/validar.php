<?php
// ===== Funciones de ayuda para validar datos y mostrar mensajes =====

// Escribe un texto de forma segura en la pantalla (evita que alguien meta código raro)
function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

// Convierte 2026-11-04 en 04/11/2026 (más natural para leer)
function fecha_mx($fecha) {
    if (!$fecha) return '-';
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d ? $d->format('d/m/Y') : e($fecha);
}

// ¿La fecha existe de verdad? (rechaza 2026-02-31, por ejemplo)
function es_fecha($fecha) {
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d && $d->format('Y-m-d') === $fecha;
}

// Solo letras (con acentos y ñ), espacios, punto, apóstrofe y guion
function solo_letras($texto) {
    return (bool)preg_match('/^[\p{L}][\p{L} .\'-]*$/u', $texto);
}

// Hora con el formato que da el reloj: 09:30 AM
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
