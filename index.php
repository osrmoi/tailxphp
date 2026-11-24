<?php
require_once "includes/sesion.php";
require_once "includes/funciones.php";

// Si ya hay sesión, no hace falta mostrar el login.
if (isset($_SESSION['usuario'])) {
    redirect("panel.php");
}

// Olvidar usuario prellenado: borra la cookie y recarga el login limpio.
if (isset($_GET['olvidar'])) {
    olvidar_preferencias();
    redirect("index.php");
}

// Leer la cookie para prellenar el usuario y marcar el checkbox.
$usuario_cookie = $_COOKIE['usuario_recordado'] ?? '';
$recordar = $_SERVER["REQUEST_METHOD"] === "POST" ? isset($_POST["recordar"]) : $usuario_cookie !== "";

require_once "includes/conexion.php";

// Limpiar variables de estado para la vista.
$error = "";
$registroOk = isset($_GET['registro']) && $_GET['registro'] === 'ok';
$usuario = $usuario_cookie;

// Solo procesar el formulario si llegó por POST.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recoger los datos del formulario.
    $usuario = trim($_POST["usuario"] ?? "");
    $password = $_POST["password"] ?? "";

    // Buscar el usuario y su hash con consulta preparada.
    $consulta = $conexion->prepare("SELECT id, password FROM usuarios WHERE usuario = ?");
    $consulta->bind_param("s", $usuario);
    $consulta->execute();
    $consulta->bind_result($id, $hash); // Enlaza las columnas a variables.
    $consulta->fetch();
    $consulta->close();

    // Verificar la contraseña hasheada.
    if ($hash && password_verify($password, $hash)) {
        // Guardar usuario e id en sesión; el id se usa para filtrar sus tareas.
        $_SESSION['usuario'] = $usuario;
        $_SESSION['usuario_id'] = $id;

        // Recordar usuario = cookie; si no, se borra.
        if ($recordar) {
            setcookie("usuario_recordado", $usuario, time() + 7*24*3600, "/");
        } else {
            olvidar_preferencias();
        }

        $conexion->close();
        redirect("panel.php"); // PRG: al recargar no se reenvía el POST.
    } else {
        $error = "Credenciales incorrectas";
    }
}
$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <title>Login</title>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="w-full max-w-sm rounded-xl bg-white p-8 shadow">
        <h1 class="mb-6 text-2xl font-bold">Iniciar sesión</h1>

        <!-- Mensaje de error si las credenciales son incorrectas. -->
        <?php if ($error !== ""): ?>
            <p class="mb-4 text-sm text-red-600"><?= h($error) ?></p>
        <?php endif; ?>

        <!-- Mensaje de éxito tras registrarse (viene de ?registro=ok). -->
        <?php if ($registroOk): ?>
            <p class="mb-4 text-sm text-green-600">Registro exitoso. Ya puedes iniciar sesión.</p>
        <?php endif; ?>

        <!-- Formulario de acceso: se envía a esta misma página por POST. -->
        <form method="POST">
            <!-- Usuario: se prellena con la cookie si existe. -->
            <label class="block text-sm font-medium">Usuario
                <input type="text" name="usuario" value="<?= h($usuario) ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" required>
            </label>
            <label class="mt-4 block text-sm font-medium">Contraseña
                <input type="password" name="password" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" required>
            </label>
            <!-- Recordar usuario: guarda la cookie por 7 días. -->
            <label class="mt-4 flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="recordar" class="h-4 w-4" <?= $recordar ? 'checked' : '' ?>> Recordar usuario
            </label>
            <button type="submit" class="mt-6 w-full rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Iniciar sesión</button>
        </form>

        <!-- Enlace al formulario de registro. -->
        <a href="registro.php" class="mt-4 block text-center text-sm text-blue-600 hover:underline">Registrarse</a>

        <!-- Solo aparece si hay usuario prellenado por cookie. -->
        <?php if ($usuario_cookie): ?>
            <a href="index.php?olvidar=1" class="mt-2 block text-center text-xs text-slate-400 hover:text-slate-600">Olvidar usuario prellenado</a>
        <?php endif; ?>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
