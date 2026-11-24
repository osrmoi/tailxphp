<?php
// Crear conexión y verificar error.
$conexion = new mysqli("localhost", "root", "", "lista");
if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}

// Setear charset para manejar acentos y caracteres especiales.
$conexion->set_charset("utf8mb4");
