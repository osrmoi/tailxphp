<?php
// Includes: sesión y guard de login (es un controlador protegido), funciones (redirect) y conexión.
require_once "includes/sesion.php";
require_once "includes/funciones.php";
requerir_login();
require_once "includes/conexion.php";

// ID de la sesión: todas las consultas lo usan para no tocar tareas de otros usuarios.
$usuarioId = (int) $_SESSION['usuario_id'];
// Acción a ejecutar y ID de la tarea sobre la que se aplica.
$accion = $_POST["accion"] ?? "";
$id = (int) ($_POST["id"] ?? 0);
// Estado de la vista (búsqueda, categoría y pestaña) para conservarlo al volver al panel.
$buscar = trim($_POST["buscar"] ?? "");
$categoriaFiltro = (int) ($_POST["categoria"] ?? 0);
$estado = $_POST["estado"] ?? "pendientes";
$estado = $estado === "terminadas" ? "terminadas" : "pendientes"; // Solo dos valores posibles.

// Crear y actualizar comparten los campos del formulario.
if ($accion === "crear" || $accion === "actualizar") {
    // Recoger los datos de la tarea.
    $titulo = trim($_POST["titulo"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");
    $categoriaTarea = (int) ($_POST["categoria_id"] ?? 0);
    // "Sin categoría" se guarda como NULL, no como 0.
    $categoriaId = $categoriaTarea > 0 ? $categoriaTarea : null;

    // Si se eligió categoría, verificar que exista; si no, dejar NULL.
    if ($categoriaId !== null) {
        $consulta = $conexion->prepare("SELECT id FROM categorias WHERE id = ?");
        $consulta->bind_param("i", $categoriaId);
        $consulta->execute();
        $consulta->store_result();
        if ($consulta->num_rows === 0) {
            $categoriaId = null;
        }
        $consulta->close();
    }

    // No se guarda una tarea sin título.
    if ($titulo !== "") {
        if ($accion === "crear") {
            // Alta: la tarea nace pendiente (completada = 0).
            $consulta = $conexion->prepare("INSERT INTO tareas (usuario_id, categoria_id, titulo, descripcion, completada) VALUES (?, ?, ?, ?, 0)");
            $consulta->bind_param("iiss", $usuarioId, $categoriaId, $titulo, $descripcion);
        } else {
            // Edición: el WHERE incluye usuario_id para no editar tareas ajenas.
            $consulta = $conexion->prepare("UPDATE tareas SET titulo = ?, descripcion = ?, categoria_id = ? WHERE id = ? AND usuario_id = ?");
            $consulta->bind_param("ssiii", $titulo, $descripcion, $categoriaId, $id, $usuarioId);
        }
        $consulta->execute();
        $consulta->close();
    }
} elseif ($accion === "eliminar") {
    // Borrar solo si la tarea pertenece al usuario de la sesión.
    $consulta = $conexion->prepare("DELETE FROM tareas WHERE id = ? AND usuario_id = ?");
    $consulta->bind_param("ii", $id, $usuarioId);
    $consulta->execute();
    $consulta->close();
} elseif ($accion === "completar") {
    // NOT completada alterna entre pendiente y terminada.
    $consulta = $conexion->prepare("UPDATE tareas SET completada = NOT completada WHERE id = ? AND usuario_id = ?");
    $consulta->bind_param("ii", $id, $usuarioId);
    $consulta->execute();
    $consulta->close();
}

$conexion->close();
// PRG: volver al panel reconstruyendo la URL con los filtros y la pestaña actuales.
redirect("panel.php?" . http_build_query([
    "buscar" => $buscar,
    "categoria" => $categoriaFiltro,
    "estado" => $estado
]));
