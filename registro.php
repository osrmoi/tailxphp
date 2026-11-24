<?php
require_once "includes/sesion.php";
require_once "includes/funciones.php";
require_once "includes/conexion.php";

$mensaje = "";
$usuario = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST["usuario"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmacion = $_POST["confirmacion"] ?? "";

    if ($usuario === "" || $password === "" || $confirmacion === "") {
        $mensaje = "Todos los campos son obligatorios.";
    } elseif ($password !== $confirmacion) {
        $mensaje = "Las contraseñas no coinciden.";
    } else {
        $consulta = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $consulta->bind_param("s", $usuario);
        $consulta->execute();
        $consulta->store_result();
        $existe = $consulta->num_rows > 0;
        $consulta->close();

        if ($existe) {
            $mensaje = "Ese usuario ya está registrado.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $consulta = $conexion->prepare("INSERT INTO usuarios (usuario, password) VALUES (?, ?)");
            $consulta->bind_param("ss", $usuario, $hash);
            if ($consulta->execute()) {
                $consulta->close();
                $conexion->close();
                redirect("index.php?registro=ok");
            } else {
                $mensaje = "Error al registrar: " . $consulta->error;
            }
            $consulta->close();
        }
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
    <title>Registro</title>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="w-full max-w-sm rounded-xl bg-white p-8 shadow">
        <h1 class="mb-6 text-2xl font-bold">Registro de usuario</h1>
        <?php if ($mensaje !== ""): ?>
            <p class="mb-4 text-sm text-red-600"><?= h($mensaje) ?></p>
        <?php endif; ?>
        <form method="POST" action="registro.php">
            <label class="block text-sm font-medium">Usuario
                <input type="text" name="usuario" value="<?= h($usuario) ?>" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" required>
            </label>
            <label class="mt-4 block text-sm font-medium">Contraseña
                <input type="password" name="password" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" required>
            </label>
            <label class="mt-4 block text-sm font-medium">Confirmación de contraseña
                <input type="password" name="confirmacion" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" required>
            </label>
            <button type="submit" class="mt-6 w-full rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Registrarse</button>
        </form>
        <a href="index.php" class="mt-4 block text-center text-sm text-slate-500 hover:text-slate-700">Volver al inicio de sesión</a>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
