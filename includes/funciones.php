<?php
// Atajo de htmlspecialchars: se usa en toda salida HTML para evitar XSS.
function h($valor) {
    return htmlspecialchars($valor ?? "", ENT_QUOTES, "utf-8");
}

// Centraliza header + exit: si se olvida el exit, el código posterior sigue ejecutándose.
function redirect($url) {
    header("Location: " . $url);
    exit;
}

// Centraliza el borrado de preferencias: hoy solo es el usuario recordado,
// pero si mañana se agrega otra, se borra aquí.
function olvidar_preferencias() {
    setcookie("usuario_recordado", "", time() - 3600, "/");
}
