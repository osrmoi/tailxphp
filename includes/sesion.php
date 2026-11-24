<?php
// La sesión se inicia aquí: al incluir este archivo ya está disponible.
session_start();

// Llamarla en las vistas protegidas: si no hay sesión, redirige al login y corta con exit.
function requerir_login() {
    if (!isset($_SESSION['usuario'])) {
        header("Location: index.php");
        exit;
    }
}
