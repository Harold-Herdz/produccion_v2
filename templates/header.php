<?php
// Obtener rol del usuario en sesión
$rol = $_SESSION['rol'] ?? 'Sin rol';
// Importar config.php
require_once dirname(__DIR__) . '/includes/config.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
<title>Control Producción</title>
    <!-- Ícono y estilos -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/logo-plastypetco.png">    
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">

</head>

<body>
    <!-- Barra de navegación -->
    <div class="navbar">

        <!-- Logo -->
        <div class="navbar-logo">
            <a href="<?= BASE_URL ?>/index.php">
                <img src="<?= BASE_URL ?>/assets/img/logo-plastypetco.png" alt="Logo Plastypetco">
            </a>
        </div>
        
        <!-- Título -->
        <h1>Control Producción</h1>

        <!-- Rol y botón de cerrar sesión -->
        <div class="cerrar-sesion">
            <a id="btnRol" href="<?php
                echo ($_SESSION['rol'] == 'admin')
                    ? BASE_URL . '/auth/views/usuarios.php'
                    : '';
            ?>"><h2>Rol: <?php echo $rol ?></h2></a>
            <a id="btnCerrar" href="<?= BASE_URL ?>/auth/controllers/logout.php">Cerrar Sesión</a>
        </div>
        
    </div>
    <?php if($_SESSION['rol'] == 'admin'){ ?>

<?php } ?>

<!-- Contenido principal -->
<div class="contenido">