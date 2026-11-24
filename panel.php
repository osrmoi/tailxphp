<?php
require_once "includes/sesion.php";
require_once "includes/funciones.php";
requerir_login();
require_once "includes/conexion.php";

// ID del usuario logueado: va en TODAS las consultas para no ver ni tocar tareas ajenas.
$usuarioId = (int) $_SESSION['usuario_id'];

// Parámetros de la URL (todos opcionales, vienen del GET).
$categoria = (int) ($_GET['categoria'] ?? 0);
$buscar = trim($_GET['buscar'] ?? "");
$editar = (int) ($_GET['editar'] ?? 0);
$estado = $_GET['estado'] ?? "pendientes";
$estado = $estado === "terminadas" ? "terminadas" : "pendientes";

// Estado a conservar al entrar/salir de la edición dentro de la misma pestaña.
$estadoActual = [
    "buscar" => $buscar,
    "categoria" => $categoria,
    "estado" => $estado
];
$volver = "panel.php?" . http_build_query($estadoActual); // Link de "Cancelar" en la edición.
// Los links de pestaña solo llevan "estado": al cambiar de pestaña se limpian búsqueda y categoría.
$urlPendientes = "panel.php?" . http_build_query(["estado" => "pendientes"]);
$urlTerminadas = "panel.php?" . http_build_query(["estado" => "terminadas"]);
$urlLimpiar = "panel.php?" . http_build_query(["estado" => $estado]); // Limpia filtros sin cambiar de pestaña.

// Contadores: SUM(condición) cuenta filas.
$consulta = $conexion->prepare("SELECT SUM(completada = 0) AS pendientes, SUM(completada = 1) AS terminadas FROM tareas WHERE usuario_id = ?");
$consulta->bind_param("i", $usuarioId);
$consulta->execute();
$contadores = $consulta->get_result()->fetch_assoc();
$consulta->close();
// Si el usuario no tiene tareas, SUM devuelve NULL; el casteo a int lo vuelve 0.
$pendientes = (int) $contadores["pendientes"];
$terminadas = (int) $contadores["terminadas"];

// Categorías para los <select> y para el color/badge de las cards.
$categorias = [];
$resultadoCategorias = $conexion->query("SELECT id, nombre, color FROM categorias ORDER BY nombre");
while ($filaCategoria = $resultadoCategorias->fetch_assoc()) {
    $categorias[] = $filaCategoria;
}
$resultadoCategorias->free(); // Liberar el resultado ya recorrido.

// Consulta de tareas según la pestaña activa.
$completada = $estado === "terminadas" ? 1 : 0; // 0 = pendientes, 1 = terminadas.
$termino = "%$buscar%"; // Comodines para LIKE: busca el texto en cualquier posición.

// LEFT JOIN: trae nombre/color/descripción de la categoría sin perder tareas sin categoría.
// (? = '' OR ...) y (? = 0 OR ...) hacen opcionales búsqueda y filtro
// sin cambiar nunca la firma del bind_param.
$consulta = $conexion->prepare("SELECT t.id, t.titulo, t.descripcion, t.categoria_id, t.completada, c.nombre AS categoria, c.color AS categoria_color, c.descripcion AS categoria_descripcion FROM tareas t LEFT JOIN categorias c ON c.id = t.categoria_id WHERE t.usuario_id = ? AND (? = '' OR t.titulo LIKE ? OR t.descripcion LIKE ?) AND (? = 0 OR t.categoria_id = ?) AND t.completada = ? ORDER BY t.creado_en DESC");

// "isssiii": i=usuarioId, s=buscar, s=término, s=término, i=categoría, i=categoría, i=completada.
$consulta->bind_param("isssiii", $usuarioId, $buscar, $termino, $termino, $categoria, $categoria, $completada);
$consulta->execute();
$resultadoTareas = $consulta->get_result(); // Resultado para recorrer con fetch_assoc().
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
<!-- pb-24 deja espacio abajo para los botones fijos. -->
<body class="min-h-screen bg-slate-50 pb-24 text-slate-800">
    <!-- Contenedor centrado con ancho máximo. -->
    <div class="mx-auto max-w-3xl px-4 py-8">
        <!-- Cabecera -->
        <header class="mb-6">
            <h1 class="text-3xl font-bold">Hola, <?= h($_SESSION['usuario']) ?></h1>
            <p class="mt-1 text-slate-500">Tienes <span class="font-semibold text-slate-800"><?= $pendientes ?></span> tareas pendientes.</p>
        </header>

        <!-- Barra de búsqueda y filtros: se envía por GET a esta misma página. -->
        <form method="GET" action="panel.php" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow">
            <!-- El hidden mantiene la pestaña activa al buscar. -->
            <input type="hidden" name="estado" value="<?= h($estado) ?>">
            <!-- Input de búsqueda: el valor se conserva al recargar/filtrar. -->
            <div class="min-w-[180px] flex-1">
                <label for="buscar" class="mb-1 block text-sm font-medium">Buscar</label>
                <input type="text" id="buscar" name="buscar" value="<?= h($buscar) ?>" placeholder="Título o descripción" class="w-full rounded border border-slate-300 px-3 py-2">
            </div>
            <!-- Select de categorías: "Todas" vale 0 (no filtra). -->
            <div>
                <label for="categoria" class="mb-1 block text-sm font-medium">Categoría</label>
                <select id="categoria" name="categoria" class="rounded border border-slate-300 px-3 py-2">
                    <option value="0" <?= $categoria === 0 ? 'selected' : '' ?>>Todas</option>
                    <!-- Una opción por categoría; selected marca la elegida. -->
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int) $cat["id"] ?>" <?= $categoria === (int) $cat["id"] ? 'selected' : '' ?>><?= h($cat["nombre"]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-white hover:bg-slate-700">Buscar</button>
            <!-- Limpiar borra búsqueda y categoría pero conserva la pestaña. -->
            <a href="<?= h($urlLimpiar) ?>" class="px-2 py-2 text-sm text-slate-500 hover:text-slate-700">Limpiar</a>
        </form>

        <!-- El formulario de alta SOLO existe en la pestaña Pendientes. -->
        <?php if ($estado === "pendientes"): ?>
            <!-- Empieza oculto: el botón "+" alterna la clase hidden. -->
            <div id="nueva-tarea" class="mb-6 hidden rounded-xl bg-white p-4 shadow">
                <h2 class="mb-3 font-semibold">Nueva tarea</h2>
                <form method="POST" action="acciones.php" autocomplete="off">
                    <!-- Hidden: acción para acciones.php + estado de la vista para volver igual. -->
                    <input type="hidden" name="accion" value="crear">
                    <input type="hidden" name="buscar" value="<?= h($buscar) ?>">
                    <input type="hidden" name="categoria" value="<?= $categoria ?>">
                    <input type="hidden" name="estado" value="<?= h($estado) ?>">

                    <input type="text" name="titulo" placeholder="Título" class="w-full rounded border border-slate-300 px-3 py-2" required>
                    <textarea name="descripcion" placeholder="Descripción" rows="2" class="mt-2 w-full rounded border border-slate-300 px-3 py-2"></textarea>

                    <!-- "categoria_id" es la categoría de la tarea; el hidden "categoria" es solo el filtro de la vista. -->
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

        <p class="mb-2 text-sm text-slate-500 text-center">Haz clic sobre la tarea para editarla</p>

        <!-- Pestañas: la activa lleva borde azul y negrita. Los links solo cambian "estado". -->
        <div class="mb-4 flex gap-2 border-b border-slate-200">
            <a href="<?= h($urlPendientes) ?>" class="px-4 py-2 <?= $estado === 'pendientes' ? 'border-b-2 border-blue-600 font-semibold text-blue-600' : 'text-slate-500 hover:text-slate-700' ?>">Pendientes (<?= $pendientes ?>)</a>
            <a href="<?= h($urlTerminadas) ?>" class="px-4 py-2 <?= $estado === 'terminadas' ? 'border-b-2 border-blue-600 font-semibold text-blue-600' : 'text-slate-500 hover:text-slate-700' ?>">Terminadas (<?= $terminadas ?>)</a>
        </div>

        <!-- Listado de tareas. -->
        <div class="space-y-3">
            <!-- Sin resultados: el mensaje cambia si hay filtros activos. -->
            <?php if ($resultadoTareas->num_rows === 0): ?>
                <p class="rounded-xl bg-white p-6 text-center text-slate-500 shadow"><?= ($buscar !== "" || $categoria > 0) ? "No se encontraron tareas." : "No hay tareas en esta lista." ?></p>
            <?php else: ?>
                <!-- Una iteración por tarea del usuario. -->
                <?php while ($tarea = $resultadoTareas->fetch_assoc()): ?>
                    <!-- Si esta tarea es la que viene en ?editar=ID, se pinta su formulario. -->
                    <?php if ((int) $tarea["id"] === $editar): ?>
                        <!-- Card en modo edición: la misma card se convierte en form. -->
                        <div class="rounded-xl bg-white p-4 shadow">
                            <form method="POST" action="acciones.php" autocomplete="off">
                                <!-- Hidden: id de la tarea + estado de la vista para volver igual. -->
                                <input type="hidden" name="id" value="<?= (int) $tarea["id"] ?>">
                                <input type="hidden" name="buscar" value="<?= h($buscar) ?>">
                                <input type="hidden" name="categoria" value="<?= $categoria ?>">
                                <input type="hidden" name="estado" value="<?= h($estado) ?>">
                                
                                <!-- Campos precargados con los valores actuales de la tarea. -->
                                <input type="text" name="titulo" value="<?= h($tarea["titulo"]) ?>" class="w-full rounded border border-slate-300 px-3 py-2 font-bold" required>
                                <textarea name="descripcion" rows="2" class="mt-2 w-full rounded border border-slate-300 px-3 py-2"><?= h($tarea["descripcion"]) ?></textarea>
                                <select name="categoria_id" class="mt-2 rounded border border-slate-300 px-3 py-2">
                                    <option value="0" <?= (int) $tarea["categoria_id"] === 0 ? 'selected' : '' ?>>Sin categoría</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= (int) $cat["id"] ?>" <?= (int) $tarea["categoria_id"] === (int) $cat["id"] ? 'selected' : '' ?>><?= h($cat["nombre"]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <!-- Guardar y Eliminar comparten formulario: el name="accion" del botón decide. -->
                                <div class="mt-4 flex items-center gap-3">
                                    <button type="submit" name="accion" value="actualizar" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Guardar</button>
                                    <!-- Cancelar no envía nada: vuelve a la URL sin ?editar. -->
                                    <a href="<?= h($volver) ?>" class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
                                    <!-- Eliminar pide confirmación. -->
                                    <button type="submit" name="accion" value="eliminar" onclick="return confirm('¿Eliminar esta tarea?')" class="ml-auto text-red-600 hover:text-red-800" title="Eliminar">
                                        <i data-lucide="trash-2" class="h-5 w-5"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Card en modo lectura: borde izquierdo del color de la categoría. -->
                        <div class="flex items-start gap-4 rounded-xl border-l-4 bg-white p-4 shadow" style="border-left-color: <?= h($tarea["categoria_color"]) ?>">
                            <!-- Todo el texto (título + descripción + badge) es un link que abre la edición. -->
                            <a href="panel.php?<?= h(http_build_query(["editar" => (int) $tarea["id"]] + $estadoActual)) ?>" class="min-w-0 flex-1">
                                <h3 class="font-bold"><?= h($tarea["titulo"]) ?></h3>
                                <!-- Descripción recortada a 2 líneas con line-clamp-2; el texto completo se ve al editar. -->
                                <?php if ($tarea["descripcion"] !== null && $tarea["descripcion"] !== ""): ?>
                                    <p class="mt-1 text-sm text-slate-600 line-clamp-2"><?= h($tarea["descripcion"]) ?></p>
                                <?php endif; ?>
                                <!-- Badge con el color de la categoría; title = descripción en tooltip. -->
                                <?php if ($tarea["categoria"] !== null): ?>
                                    <span class="mt-2 inline-block rounded-full px-2 py-1 text-xs" title="<?= h($tarea["categoria_descripcion"]) ?>" style="background-color: <?= h($tarea["categoria_color"]) ?>; color: #fff"><?= h($tarea["categoria"]) ?></span>
                                <?php endif; ?>
                            </a>
                            <!-- Checkbox: fuera del <a> para no solaparse con el clic de edición. -->
                            <form method="POST" action="acciones.php" class="shrink-0">
                                <!-- Hidden: acción "completar", id y estado para volver a la misma pestaña. -->
                                <input type="hidden" name="accion" value="completar">
                                <input type="hidden" name="id" value="<?= (int) $tarea["id"] ?>">
                                <input type="hidden" name="buscar" value="<?= h($buscar) ?>">
                                <input type="hidden" name="categoria" value="<?= $categoria ?>">
                                <input type="hidden" name="estado" value="<?= h($estado) ?>">

                                <!-- onchange envía el form solo; "checked" refleja si está terminada. -->
                                <input type="checkbox" onchange="this.form.submit()" class="h-5 w-5 cursor-pointer rounded-full border-slate-300 text-blue-600 focus:ring-blue-600" <?= $tarea["completada"] ? 'checked' : '' ?> aria-label="Marcar como completada">
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- Botón flotante "+" solo en Pendientes: muestra/oculta el form de alta. -->
        <?php if ($estado === "pendientes"): ?>
            <button type="button" onclick="toggleNueva()" class="fixed bottom-4 right-4 z-50 rounded-full bg-blue-600 p-4 text-white shadow-lg hover:bg-blue-700" title="Nueva tarea">
                <i data-lucide="plus" class="h-5 w-5"></i>
            </button>
        <?php endif; ?>

        <!-- Logout fijo abajo izquierda, siempre visible, con hover rojo. -->
        <a href="logout.php" class="fixed bottom-4 left-4 z-50 rounded-full bg-white p-3 text-slate-600 shadow-lg hover:text-red-600" title="Cerrar sesión">
            <i data-lucide="door-open" class="h-5 w-5"></i>
        </a>
    </div>
<?php
// Liberar resultados y cerrar recursos al terminar de pintar la página.
$resultadoTareas->free();
$consulta->close();
$conexion->close();
?>
    <script>
        // Alterna la clase "hidden" del formulario de nueva tarea.
        function toggleNueva() {
            document.getElementById('nueva-tarea').classList.toggle('hidden');
        }
        // Convierte los <i data-lucide> en <svg>.
        lucide.createIcons();
    </script>
</body>
</html>
