<?php
session_start();

function requerir_login() {
    if (!isset($_SESSION['usuario'])) {
        header("Location: index.php");
        exit;
    }
}
