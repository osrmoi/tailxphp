<?php
require_once "includes/sesion.php";
require_once "includes/funciones.php";
require_once "includes/conexion.php";

$mensaje = "";
$color = "red";
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
        $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();

        if ($existe) {
            $mensaje = "Ese usuario ya está registrado.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $usuario, $hash);
            if ($stmt->execute()) {
                $mensaje = "Usuario registrado correctamente. Ya puedes iniciar sesión.";
                $color = "green";
                $usuario = "";
            } else {
                $mensaje = "Error al registrar: " . $stmt->error;
            }
            $stmt->close();
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
    <title>Registro</title>
</head>
<body>
    <h1>Registro de usuario</h1>
    <?php if ($mensaje !== ""): ?>
        <p style="color:<?= $color ?>"><?= h($mensaje) ?></p>
    <?php endif; ?>
    <form method="POST" action="registro.php">
        <label>Usuario:<br><input type="text" name="usuario" value="<?= h($usuario) ?>" required></label><br><br>
        <label>Contraseña:<br><input type="password" name="password" required></label><br><br>
        <label>Confirmación de contraseña:<br><input type="password" name="confirmacion" required></label><br><br>
        <button type="submit">Registrarse</button>
    </form>
    <br>
    <a href="index.php">Volver al inicio de sesión</a>
</body>
</html>
