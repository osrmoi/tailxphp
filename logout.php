<?php
require_once "includes/sesion.php";
require_once "includes/funciones.php";
session_destroy();
redirect("index.php");
