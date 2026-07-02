<?php
// Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Variables de la base de datos
if ($_SERVER['HTTP_HOST'] === 'localhost') {
    $host     = "localhost";
    $usuario  = "root";
    $password = "";
    $database = "control_produccion";
} else {
    $host     = "sql301.infinityfree.com";
    $usuario  = "if0_42142771";
    $password = "plastypet12";
    $database = "if0_42142771_produccion";
}

// Conectar a la base de datos
$conexion = mysqli_connect($host, $usuario, $password, $database);

// Verificar conexión
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Establecer codificación de caracteres
mysqli_set_charset($conexion, "utf8mb4");
?>