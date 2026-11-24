<?php
// Conexión a la BD (no hace falta sesión: es un script de desarrollo).
require_once "includes/conexion.php";

// Categorías sembradas: nombre, descripción y color (el color se usa en el panel).
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

// ON DUPLICATE KEY UPDATE hace el seeder idempotente: si el nombre ya existe, actualiza descripción y color.
$consulta = $conexion->prepare("INSERT INTO categorias (nombre, descripcion, color) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), color = VALUES(color)");
foreach ($categorias as $categoria) {
    // Desempaquetar en variables.
    $nombre = $categoria[0];
    $descripcion = $categoria[1];
    $color = $categoria[2];
    $consulta->bind_param("sss", $nombre, $descripcion, $color);
    $consulta->execute();
}
$consulta->close();

// Contraseña de prueba.
$hash = password_hash("12341234", PASSWORD_DEFAULT);
// INSERT IGNORE: si el usuario ya existe, no le sobrescribe la contraseña.
$consulta = $conexion->prepare("INSERT IGNORE INTO usuarios (usuario, password) VALUES (?, ?)");
foreach (["user1", "user2"] as $usuario) {
    $consulta->bind_param("ss", $usuario, $hash);
    $consulta->execute();
}
$consulta->close();

$conexion->close();

// Confirmación en pantalla de que terminó.
echo "Categorías y usuarios sembrados correctamente.";
