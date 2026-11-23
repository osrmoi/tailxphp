<?php
function h($valor) {
    return htmlspecialchars($valor ?? "", ENT_QUOTES, "utf-8");
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function olvidar_preferencias() {
    setcookie("usuario_recordado", "", time() - 3600, "/");
}
