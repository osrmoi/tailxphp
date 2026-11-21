<?php
require_once "includes/sesion.php";
require_once "includes/funciones.php";
requerir_login();
require_once "includes/conexion.php";

$usuarioId = (int) $_SESSION['usuario_id'];

$categoriaCookie = (int) ($_COOKIE['categoria_recordada'] ?? 0);
if ($categoriaCookie > 0) {
    $stmt = $conexion->prepare("SELECT id FROM categorias WHERE id = ?");
    $stmt->bind_param("i", $categoriaCookie);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $categoriaCookie = 0;
        setcookie("categoria_recordada", "", time() - 3600, "/");
    }
    $stmt->close();
}

$categoria = isset($_GET['categoria']) ? (int) $_GET['categoria'] : $categoriaCookie;
$buscar = trim($_GET['buscar'] ?? "");
$editar = (int) ($_GET['editar'] ?? 0);

if (isset($_GET['categoria']) && isset($_COOKIE['usuario_recordado'])) {
    setcookie("categoria_recordada", (string) $categoria, time() + 7*24*3600, "/");
}

$estado = ["buscar" => $buscar, "categoria" => $categoria];
$volver = "panel.php?" . http_build_query($estado);

$stmt = $conexion->prepare("SELECT COUNT(*) AS total, SUM(completada = 0) AS pendientes, SUM(completada = 1) AS completadas FROM tareas WHERE usuario_id = ?");
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$contadores = $stmt->get_result()->fetch_assoc();
$stmt->close();

$categorias = [];
$resultadoCategorias = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre");
while ($filaCategoria = $resultadoCategorias->fetch_assoc()) {
    $categorias[] = $filaCategoria;
}
$resultadoCategorias->free();

$termino = "%$buscar%";
$stmt = $conexion->prepare("SELECT t.id, t.titulo, t.descripcion, t.categoria_id, t.completada, c.nombre AS categoria FROM tareas t LEFT JOIN categorias c ON c.id = t.categoria_id WHERE t.usuario_id = ? AND (? = '' OR t.titulo LIKE ? OR t.descripcion LIKE ?) AND (? = 0 OR t.categoria_id = ?) ORDER BY t.completada ASC, t.creado_en DESC");
$stmt->bind_param("isssii", $usuarioId, $buscar, $termino, $termino, $categoria, $categoria);
$stmt->execute();
$resultado = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de tareas</title>
</head>
<body>
    <h1>Bienvenido, <?= h($_SESSION['usuario']) ?></h1>
    <p><a href="logout.php">Cerrar sesión</a></p>

    <h2>Resumen</h2>
    <p>Total: <?= (int) $contadores["total"] ?> | Pendientes: <?= (int) $contadores["pendientes"] ?> | Completadas: <?= (int) $contadores["completadas"] ?></p>

    <h2>Buscar y filtrar</h2>
    <form method="GET" action="panel.php">
        <label for="buscar">Buscar:</label>
        <input type="text" id="buscar" name="buscar" value="<?= h($buscar) ?>" placeholder="Título o descripción">
        <label for="categoria">Categoría:</label>
        <select id="categoria" name="categoria">
            <option value="0" <?= $categoria === 0 ? 'selected' : '' ?>>Todas</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= (int) $cat["id"] ?>" <?= $categoria === (int) $cat["id"] ? 'selected' : '' ?>><?= h($cat["nombre"]) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Buscar</button>
        <a href="panel.php?buscar=&amp;categoria=0">Limpiar</a>
    </form>

    <h2>Nueva tarea</h2>
    <form method="POST" action="acciones.php">
        <input type="hidden" name="accion" value="crear">
        <input type="hidden" name="buscar" value="<?= h($buscar) ?>">
        <input type="hidden" name="categoria" value="<?= $categoria ?>">
        <label>Título:<br><input type="text" name="titulo" required></label><br><br>
        <label>Descripción:<br><textarea name="descripcion"></textarea></label><br><br>
        <label>Categoría:<br>
            <select name="categoria_id">
                <option value="0">Sin categoría</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= (int) $cat["id"] ?>"><?= h($cat["nombre"]) ?></option>
                <?php endforeach; ?>
            </select>
        </label><br><br>
        <button type="submit">Agregar tarea</button>
    </form>

    <h2>Tareas</h2>
    <table border="1" cellpadding="10">
        <tr>
            <th>Título</th>
            <th>Descripción</th>
            <th>Categoría</th>
            <th>Completada</th>
            <th>Acciones</th>
        </tr>
        <?php if ($resultado->num_rows === 0): ?>
            <tr>
                <td colspan="5"><?= ($buscar !== "" || $categoria > 0) ? "No se encontraron tareas." : "No hay tareas registradas." ?></td>
            </tr>
        <?php else: ?>
            <?php while ($fila = $resultado->fetch_assoc()): ?>
                <?php if ((int) $fila["id"] === $editar): ?>
                    <tr>
                        <td colspan="5">
                            <form method="POST" action="acciones.php">
                                <input type="hidden" name="accion" value="actualizar">
                                <input type="hidden" name="id" value="<?= (int) $fila["id"] ?>">
                                <input type="hidden" name="buscar" value="<?= h($buscar) ?>">
                                <input type="hidden" name="categoria" value="<?= $categoria ?>">
                                <label>Título:<br><input type="text" name="titulo" value="<?= h($fila["titulo"]) ?>" required></label><br><br>
                                <label>Descripción:<br><textarea name="descripcion"><?= h($fila["descripcion"]) ?></textarea></label><br><br>
                                <label>Categoría:<br>
                                    <select name="categoria_id">
                                        <option value="0" <?= (int) $fila["categoria_id"] === 0 ? 'selected' : '' ?>>Sin categoría</option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?= (int) $cat["id"] ?>" <?= (int) $fila["categoria_id"] === (int) $cat["id"] ? 'selected' : '' ?>><?= h($cat["nombre"]) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label><br><br>
                                <button type="submit">Guardar</button>
                                <a href="<?= h($volver) ?>">Cancelar</a>
                            </form>
                        </td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td><?= h($fila["titulo"]) ?></td>
                        <td><?= h($fila["descripcion"]) ?></td>
                        <td><?= $fila["categoria"] !== null ? h($fila["categoria"]) : "Sin categoría" ?></td>
                        <td><?= $fila["completada"] ? "Sí" : "No" ?></td>
                        <td>
                            <a href="panel.php?<?= h(http_build_query(["editar" => (int) $fila["id"]] + $estado)) ?>">Editar</a>
                            <form method="POST" action="acciones.php" style="display:inline">
                                <input type="hidden" name="accion" value="completar">
                                <input type="hidden" name="id" value="<?= (int) $fila["id"] ?>">
                                <input type="hidden" name="buscar" value="<?= h($buscar) ?>">
                                <input type="hidden" name="categoria" value="<?= $categoria ?>">
                                <button type="submit">Completar</button>
                            </form>
                            <form method="POST" action="acciones.php" style="display:inline">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $fila["id"] ?>">
                                <input type="hidden" name="buscar" value="<?= h($buscar) ?>">
                                <input type="hidden" name="categoria" value="<?= $categoria ?>">
                                <button type="submit" onclick="return confirm('¿Eliminar esta tarea?')">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endwhile; ?>
        <?php endif; ?>
    </table>
<?php
$resultado->free();
$stmt->close();
$conexion->close();
?>
</body>
</html>
