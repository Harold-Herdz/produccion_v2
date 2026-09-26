<?php
/** @var mysqli $conexion */
// Iniciar la sesión si no hay una activa
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// Importar conexion.php
require_once dirname(__DIR__, 2) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 2) . '/includes/config.php';
// Importar passwords.php (cifrado de contraseñas)
require_once dirname(__DIR__) . '/shared/passwords.php';

// Datos del formulario
$usuario = trim($_POST['usuario'] ?? '');
$contrasena = trim($_POST['contrasena'] ?? '');

// Validar campos vacíos
if(empty($usuario) || empty($contrasena)){
    header("Location: " . BASE_URL . "/auth/views/login.php?error=1");
    exit;
}

// Buscar usuario en la base de datos (consulta preparada)
$stmt = mysqli_prepare($conexion, "SELECT * FROM USUARIOS WHERE usuario = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $usuario);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// Redirigir al Login si el usuario no existe
if(!$res || mysqli_num_rows($res) === 0){
    header("Location: " . BASE_URL . "/auth/views/login.php?error=1");
    exit;
}

// Obtener datos del usuario
$row = mysqli_fetch_assoc($res);

// Validar la contraseña (hash; o texto plano antiguo, que se cifra al entrar)
if(!verificarContrasena($contrasena, $row['contrasena'])){
    header("Location: " . BASE_URL . "/auth/views/login.php?error=1");
    exit;
}
if(!contrasenaEsHash($row['contrasena']) || password_needs_rehash($row['contrasena'], PASSWORD_DEFAULT)){
    $nuevo = cifrarContrasena($contrasena);
    $upd = mysqli_prepare($conexion, "UPDATE USUARIOS SET contrasena = ? WHERE id_usuario = ?");
    mysqli_stmt_bind_param($upd, 'si', $nuevo, $row['id_usuario']);
    mysqli_stmt_execute($upd);
}

// Crear sesión con datos del usuario
$_SESSION['usuario'] = $row['usuario'];
$_SESSION['rol'] = $row['rol'];
$_SESSION['id_usuario'] = $row['id_usuario'];

// Redirigir al Index
header("Location: " . BASE_URL . "/index.php");
exit;