<?php
require_once "includes/conexion.php";

$categorias = [
    ["Trabajo", "Tareas del ámbito laboral", "#2563eb"],
    ["Escuela", "Estudio y tareas escolares", "#7c3aed"],
    ["Hogar", "Tareas del hogar", "#059669"],
    ["Personal", "Asuntos personales", "#db2777"],
    ["Salud", "Cuidado personal y salud", "#dc2626"],
    ["Finanzas", "Pagos, presupuestos y ahorro", "#ca8a04"],
    ["Compras", "Listas de compras", "#ea580c"],
    ["Proyectos", "Proyectos y objetivos", "#0891b2"],
    ["Ocio", "Tiempo libre y entretenimiento", "#4f46e5"],
];

$consulta = $conexion->prepare("INSERT INTO categorias (nombre, descripcion, color) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), color = VALUES(color)");
foreach ($categorias as $categoria) {
    $nombre = $categoria[0];
    $descripcion = $categoria[1];
    $color = $categoria[2];
    $consulta->bind_param("sss", $nombre, $descripcion, $color);
    $consulta->execute();
}
$consulta->close();

$hash = password_hash("1234", PASSWORD_DEFAULT);
$consulta = $conexion->prepare("INSERT IGNORE INTO usuarios (usuario, password) VALUES (?, ?)");
foreach (["user1", "user2"] as $usuario) {
    $consulta->bind_param("ss", $usuario, $hash);
    $consulta->execute();
}
$consulta->close();

$conexion->close();

echo "Categorías y usuarios sembrados correctamente.";
