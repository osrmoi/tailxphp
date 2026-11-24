<?php
require_once "includes/sesion.php";
require_once "includes/funciones.php";
require_once "includes/conexion.php";

// Limpiar variables de mensaje y usuario para que no queden valores de otra petición.
$mensaje = "";
$usuario = "";

// Solo procesar el formulario si llegó por POST.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recoger los datos del formulario.
    $usuario = trim($_POST["usuario"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmacion = $_POST["confirmacion"] ?? "";

    // Ningún campo puede quedar vacío.
    if ($usuario === "" || $password === "" || $confirmacion === "") {
        $mensaje = "Todos los campos son obligatorios.";

    // La confirmación debe coincidir.
    } elseif ($password !== $confirmacion) {
        $mensaje = "Las contraseñas no coinciden.";

    // Comprobar que el usuario no exista.
    } else {
        // Consulta preparada.
        $consulta = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $consulta->bind_param("s", $usuario);
        $consulta->execute();
        $consulta->store_result(); // Traer el resultado a memoria para usar num_rows.
        $existe = $consulta->num_rows > 0;
        $consulta->close();

        if ($existe) {
            $mensaje = "Ese usuario ya está en uso.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // INSERT preparado con dos marcadores (usuario y hash).
            $consulta = $conexion->prepare("INSERT INTO usuarios (usuario, password) VALUES (?, ?)");
            $consulta->bind_param("ss", $usuario, $hash);

            // Ejecutar e intervenir según éxito o error.
            if ($consulta->execute()) {
                $consulta->close();
                $conexion->close();
                redirect("index.php?registro=ok"); // PRG: vuelve al login con mensaje de éxito.
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

        <!-- Mensaje de error: solo se muestra si $mensaje no está vacío. -->
        <?php if ($mensaje !== ""): ?>
            <p class="mb-4 text-sm text-red-600"><?= h($mensaje) ?></p>
        <?php endif; ?>

        <!-- Formulario: se envía a esta misma página por POST. -->
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
