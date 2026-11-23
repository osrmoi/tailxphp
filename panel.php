<?php
require_once "includes/sesion.php";
require_once "includes/funciones.php";
requerir_login();
require_once "includes/conexion.php";

$usuarioId = (int) $_SESSION['usuario_id'];

$categoria = (int) ($_GET['categoria'] ?? 0);
$buscar = trim($_GET['buscar'] ?? "");
$editar = (int) ($_GET['editar'] ?? 0);
$estado = $_GET['estado'] ?? "pendientes";
$estado = $estado === "terminadas" ? "terminadas" : "pendientes";

$estadoActual = [
    "buscar" => $buscar,
    "categoria" => $categoria,
    "estado" => $estado
];
$volver = "panel.php?" . http_build_query($estadoActual);
$urlPendientes = "panel.php?" . http_build_query(["estado" => "pendientes"]);
$urlTerminadas = "panel.php?" . http_build_query(["estado" => "terminadas"]);
$urlLimpiar = "panel.php?" . http_build_query(["estado" => $estado]);

$stmt = $conexion->prepare("SELECT SUM(completada = 0) AS pendientes, SUM(completada = 1) AS terminadas FROM tareas WHERE usuario_id = ?");
$stmt->bind_param("i", $usuarioId);
$stmt->execute();
$contadores = $stmt->get_result()->fetch_assoc();
$stmt->close();
$pendientes = (int) $contadores["pendientes"];
$terminadas = (int) $contadores["terminadas"];

$categorias = [];
$resultadoCategorias = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre");
while ($filaCategoria = $resultadoCategorias->fetch_assoc()) {
    $categorias[] = $filaCategoria;
}
$resultadoCategorias->free();

$completada = $estado === "terminadas" ? 1 : 0;
$termino = "%$buscar%";
$stmt = $conexion->prepare("SELECT t.id, t.titulo, t.descripcion, t.categoria_id, t.completada, c.nombre AS categoria FROM tareas t LEFT JOIN categorias c ON c.id = t.categoria_id WHERE t.usuario_id = ? AND (? = '' OR t.titulo LIKE ? OR t.descripcion LIKE ?) AND (? = 0 OR t.categoria_id = ?) AND t.completada = ? ORDER BY t.creado_en DESC");
$stmt->bind_param("isssiii", $usuarioId, $buscar, $termino, $termino, $categoria, $categoria, $completada);
$stmt->execute();
$resultado = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <title>Panel de tareas</title>
</head>
<body class="min-h-screen bg-slate-50 pb-24 text-slate-800">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <header class="mb-6">
            <h1 class="text-3xl font-bold">Bienvenido, <?= h($_SESSION['usuario']) ?></h1>
            <p class="mt-1 text-slate-500">Tienes <span class="font-semibold text-slate-800"><?= $pendientes ?></span> tareas pendientes.</p>
        </header>

        <form method="GET" action="panel.php" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
            <input type="hidden" name="estado" value="<?= h($estado) ?>">
            <div class="min-w-[180px] flex-1">
                <label for="buscar" class="mb-1 block text-sm font-medium">Buscar</label>
                <input type="text" id="buscar" name="buscar" value="<?= h($buscar) ?>" placeholder="Título o descripción" class="w-full rounded border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label for="categoria" class="mb-1 block text-sm font-medium">Categoría</label>
                <select id="categoria" name="categoria" class="rounded border border-slate-300 px-3 py-2">
                    <option value="0" <?= $categoria === 0 ? 'selected' : '' ?>>Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int) $cat["id"] ?>" <?= $categoria === (int) $cat["id"] ? 'selected' : '' ?>><?= h($cat["nombre"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-white hover:bg-slate-700">Buscar</button>
            <a href="<?= h($urlLimpiar) ?>" class="px-2 py-2 text-sm text-slate-500 hover:text-slate-700">Limpiar</a>
            <?php if ($estado === "pendientes"): ?>
                <button type="button" onclick="toggleNueva()" class="ml-auto rounded-full bg-blue-600 p-3 text-white shadow hover:bg-blue-700" title="Nueva tarea">
                    <i data-lucide="plus" class="h-5 w-5"></i>
                </button>
            <?php endif; ?>
        </form>

        <?php if ($estado === "pendientes"): ?>
            <div id="nueva-tarea" class="mb-6 hidden rounded-xl bg-white p-4 shadow">
                <h2 class="mb-3 font-semibold">Nueva tarea</h2>
                <form method="POST" action="acciones.php">
                    <input type="hidden" name="accion" value="crear">
                    <input type="hidden" name="buscar" value="<?= h($buscar) ?>">
                    <input type="hidden" name="categoria" value="<?= $categoria ?>">
                    <input type="hidden" name="estado" value="<?= h($estado) ?>">
                    <input type="text" name="titulo" placeholder="Título" class="w-full rounded border border-slate-300 px-3 py-2" required>
                    <textarea name="descripcion" placeholder="Descripción" rows="2" class="mt-2 w-full rounded border border-slate-300 px-3 py-2"></textarea>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <select name="categoria_id" class="rounded border border-slate-300 px-3 py-2">
                            <option value="0">Sin categoría</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= (int) $cat["id"] ?>"><?= h($cat["nombre"]) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Guardar</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="mb-4 flex gap-2 border-b border-slate-200">
            <a href="<?= h($urlPendientes) ?>" class="px-4 py-2 <?= $estado === 'pendientes' ? 'border-b-2 border-blue-600 font-semibold text-blue-600' : 'text-slate-500 hover:text-slate-700' ?>">Pendientes (<?= $pendientes ?>)</a>
            <a href="<?= h($urlTerminadas) ?>" class="px-4 py-2 <?= $estado === 'terminadas' ? 'border-b-2 border-blue-600 font-semibold text-blue-600' : 'text-slate-500 hover:text-slate-700' ?>">Terminadas (<?= $terminadas ?>)</a>
        </div>

        <div class="space-y-3">
            <?php if ($resultado->num_rows === 0): ?>
                <p class="rounded-xl bg-white p-6 text-center text-slate-500 shadow"><?= ($buscar !== "" || $categoria > 0) ? "No se encontraron tareas." : "No hay tareas en esta lista." ?></p>
            <?php else: ?>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                    <?php if ((int) $fila["id"] === $editar): ?>
                        <div class="rounded-xl bg-white p-4 shadow">
                            <form method="POST" action="acciones.php">
                                <input type="hidden" name="id" value="<?= (int) $fila["id"] ?>">
                                <input type="hidden" name="buscar" value="<?= h($buscar) ?>">
                                <input type="hidden" name="categoria" value="<?= $categoria ?>">
                                <input type="hidden" name="estado" value="<?= h($estado) ?>">
                                <input type="text" name="titulo" value="<?= h($fila["titulo"]) ?>" class="w-full rounded border border-slate-300 px-3 py-2 font-bold" required>
                                <textarea name="descripcion" rows="2" class="mt-2 w-full rounded border border-slate-300 px-3 py-2"><?= h($fila["descripcion"]) ?></textarea>
                                <select name="categoria_id" class="mt-2 rounded border border-slate-300 px-3 py-2">
                                    <option value="0" <?= (int) $fila["categoria_id"] === 0 ? 'selected' : '' ?>>Sin categoría</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= (int) $cat["id"] ?>" <?= (int) $fila["categoria_id"] === (int) $cat["id"] ? 'selected' : '' ?>><?= h($cat["nombre"]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="mt-4 flex items-center gap-3">
                                    <button type="submit" name="accion" value="actualizar" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Guardar</button>
                                    <a href="<?= h($volver) ?>" class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
                                    <button type="submit" name="accion" value="eliminar" onclick="return confirm('¿Eliminar esta tarea?')" class="ml-auto text-red-600 hover:text-red-800" title="Eliminar">
                                        <i data-lucide="trash-2" class="h-5 w-5"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="flex items-start gap-4 rounded-xl bg-white p-4 shadow">
                            <a href="panel.php?<?= h(http_build_query(["editar" => (int) $fila["id"]] + $estadoActual)) ?>" class="min-w-0 flex-1">
                                <h3 class="font-bold"><?= h($fila["titulo"]) ?></h3>
                                <?php if ($fila["descripcion"] !== null && $fila["descripcion"] !== ""): ?>
                                    <p class="mt-1 text-sm text-slate-600 line-clamp-2"><?= h($fila["descripcion"]) ?></p>
                                <?php endif; ?>
                                <?php if ($fila["categoria"] !== null): ?>
                                    <span class="mt-2 inline-block rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600"><?= h($fila["categoria"]) ?></span>
                                <?php endif; ?>
                            </a>
                            <form method="POST" action="acciones.php" class="shrink-0">
                                <input type="hidden" name="accion" value="completar">
                                <input type="hidden" name="id" value="<?= (int) $fila["id"] ?>">
                                <input type="hidden" name="buscar" value="<?= h($buscar) ?>">
                                <input type="hidden" name="categoria" value="<?= $categoria ?>">
                                <input type="hidden" name="estado" value="<?= h($estado) ?>">
                                <input type="checkbox" onchange="this.form.submit()" class="h-5 w-5 cursor-pointer rounded-full border-slate-300 text-blue-600 focus:ring-blue-600" <?= $fila["completada"] ? 'checked' : '' ?> aria-label="Marcar como completada">
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <a href="logout.php" class="fixed bottom-4 left-4 z-50 rounded-full bg-white p-3 text-slate-600 shadow-lg hover:text-red-600" title="Cerrar sesión">
            <i data-lucide="door-open" class="h-5 w-5"></i>
        </a>
    </div>
<?php
$resultado->free();
$stmt->close();
$conexion->close();
?>
    <script>
        function toggleNueva() {
            document.getElementById('nueva-tarea').classList.toggle('hidden');
        }
        lucide.createIcons();
    </script>
</body>
</html>
