<?php
require_once "includes/sesion.php";
require_once "includes/funciones.php";
requerir_login();
require_once "includes/conexion.php";

$usuarioId = (int) $_SESSION['usuario_id'];
$accion = $_POST["accion"] ?? "";
$id = (int) ($_POST["id"] ?? 0);
$buscar = trim($_POST["buscar"] ?? "");
$categoriaFiltro = (int) ($_POST["categoria"] ?? 0);
$estado = $_POST["estado"] ?? "pendientes";
$estado = $estado === "terminadas" ? "terminadas" : "pendientes";

if ($accion === "crear" || $accion === "actualizar") {
    $titulo = trim($_POST["titulo"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");
    $categoriaTarea = (int) ($_POST["categoria_id"] ?? 0);
    $categoriaId = $categoriaTarea > 0 ? $categoriaTarea : null;

    if ($categoriaId !== null) {
        $stmt = $conexion->prepare("SELECT id FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $categoriaId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $categoriaId = null;
        }
        $stmt->close();
    }

    if ($titulo !== "") {
        if ($accion === "crear") {
            $stmt = $conexion->prepare("INSERT INTO tareas (usuario_id, categoria_id, titulo, descripcion, completada) VALUES (?, ?, ?, ?, 0)");
            $stmt->bind_param("iiss", $usuarioId, $categoriaId, $titulo, $descripcion);
        } else {
            $stmt = $conexion->prepare("UPDATE tareas SET titulo = ?, descripcion = ?, categoria_id = ? WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param("ssiii", $titulo, $descripcion, $categoriaId, $id, $usuarioId);
        }
        $stmt->execute();
        $stmt->close();
    }
} elseif ($accion === "eliminar") {
    $stmt = $conexion->prepare("DELETE FROM tareas WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $id, $usuarioId);
    $stmt->execute();
    $stmt->close();
} elseif ($accion === "completar") {
    $stmt = $conexion->prepare("UPDATE tareas SET completada = NOT completada WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $id, $usuarioId);
    $stmt->execute();
    $stmt->close();
}

$conexion->close();
redirect("panel.php?" . http_build_query([
    "buscar" => $buscar,
    "categoria" => $categoriaFiltro,
    "estado" => $estado
]));
