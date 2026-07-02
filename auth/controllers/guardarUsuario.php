<?php
/** @var mysqli $conexion */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar proteger.php
require_once dirname(__DIR__) . '/proteger.php';
// Importar conexion.php
require_once dirname(__DIR__, 2) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 2) . '/includes/config.php';

// Obtener y limpiar datos del formulario
$usuario = trim($_POST['usuario'] ?? '');
$contrasena = trim($_POST['contrasena'] ?? '');
$rol = trim($_POST['rol'] ?? '');

// Validar campos vacíos
if(
    empty($usuario) ||
    empty($contrasena) ||
    empty($rol)
){
    header("Location: " . BASE_URL . "/auth/views/crearUsuario.php");
    exit;
}

// Sanear datos antes de insertar
$usuario = mysqli_real_escape_string($conexion, $usuario);
$contrasena = mysqli_real_escape_string($conexion, $contrasena);
$rol = mysqli_real_escape_string($conexion, $rol);

// Insertar nuevo usuario
$sql = "INSERT INTO USUARIOS
            (usuario,
            contrasena,
            rol)
        VALUES
            ('$usuario',
            '$contrasena',
            '$rol')";

mysqli_query($conexion, $sql);

// Redirigir al Usuarios
header("Location: " . BASE_URL . "/auth/views/usuarios.php");
exit;