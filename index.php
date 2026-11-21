<?php
require_once "includes/sesion.php";
require_once "includes/funciones.php";

if (isset($_SESSION['usuario'])) {
    redirect("panel.php");
}

if (isset($_GET['olvidar'])) {
    olvidar_preferencias();
    redirect("index.php");
}

$usuario_cookie = $_COOKIE['usuario_recordado'] ?? '';
$recordar = $_SERVER["REQUEST_METHOD"] === "POST" ? isset($_POST["recordar"]) : $usuario_cookie !== "";

require_once "includes/conexion.php";

$error = "";
$usuario = $usuario_cookie;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST["usuario"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conexion->prepare("SELECT id, password FROM usuarios WHERE usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $stmt->bind_result($id, $hash);
    $stmt->fetch();
    $stmt->close();

    if ($hash && password_verify($password, $hash)) {
        $_SESSION['usuario'] = $usuario;
        $_SESSION['usuario_id'] = $id;

        if ($recordar) {
            setcookie("usuario_recordado", $usuario, time() + 7*24*3600, "/");
        } else {
            olvidar_preferencias();
        }

        $conexion->close();
        redirect("panel.php");
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
    <title>Login</title>
</head>
<body>
    <h1>Iniciar sesión</h1>
    <form method="POST">
        <label>Usuario:<br><input type="text" name="usuario" value="<?= h($usuario) ?>" required></label><br><br>
        <label>Contraseña:<br><input type="password" name="password" required></label><br><br>
        <label><input type="checkbox" name="recordar" <?= $recordar ? 'checked' : '' ?>> Recordar usuario</label><br><br>
        <button type="submit">Iniciar sesión</button>
    </form>
    <br>
    <?php if ($error !== ""): ?>
        <p style="color:red"><?= h($error) ?></p>
    <?php endif; ?>
    <a href="registro.php">Registrarse</a>
    <?php if ($usuario_cookie): ?>
        <br><br>
        <a href="index.php?olvidar=1">Olvidar usuario prellenado</a>
    <?php endif; ?>
</body>
</html>
