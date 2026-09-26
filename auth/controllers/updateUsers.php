<?php
/** @var mysqli $conexion */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar authMiddleware.php
require_once dirname(__DIR__) . '/authMiddleware.php';
// Importar conexion.php
require_once dirname(__DIR__, 2) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 2) . '/includes/config.php';
// Importar passwords.php (cifrado de contraseñas)
require_once dirname(__DIR__) . '/shared/passwords.php';

// Datos del formulario
$id = (int)($_POST['id_usuario'] ?? 0);
$usuario = trim($_POST['usuario'] ?? '');
$contrasena = trim($_POST['contrasena'] ?? '');
$rol = trim($_POST['rol'] ?? '');
$estado = (int)($_POST['estado'] ?? 1);

// Sanear datos
$usuario = mysqli_real_escape_string($conexion, $usuario);
$rol = mysqli_real_escape_string($conexion, $rol);

// Sin cambiar contraseña si viene vacía
if(empty($contrasena)){
    $sql = "UPDATE USUARIOS
            SET
                usuario = '$usuario',
                rol = '$rol',
                estado = $estado
            WHERE id_usuario = $id
            ";

}else{
    // Actualizar incluyendo nueva contraseña
    $contrasena = mysqli_real_escape_string($conexion, cifrarContrasena($contrasena));
    $sql = "UPDATE USUARIOS
            SET
                usuario = '$usuario',
                contrasena = '$contrasena',
                rol = '$rol',
                estado = $estado
            WHERE id_usuario = $id
            ";
}

mysqli_query($conexion, $sql);

// Redirigir al Usuarios
header("Location: " . BASE_URL . "/auth/views/users.php");
exit;