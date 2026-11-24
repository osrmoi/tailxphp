<?php
require_once "includes/sesion.php";
require_once "includes/funciones.php";

// Destruir los datos de la sesión.
session_destroy();
redirect("index.php");
