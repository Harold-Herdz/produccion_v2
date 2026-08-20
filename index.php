<?php
// Iniciar sesión
session_start();
// Importar authMiddleware.php
require_once("auth/authMiddleware.php");
// Importar config.php
require_once __DIR__ . '/includes/config.php';
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
<title>Control de Producción</title>
    <!-- Ícono y estilos -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/index.css">

</head>

<body class="index-body">
    <!-- Contenedor principal -->
    <div class="glass-container">

        <!-- Título -->
        <h1>Seleccionar Modulo</h1>

        <!-- Tarjetas de módulos — redirige según rol del usuario -->
        <div class="areas">
     
            <!-- Módulo Sellado -->
            <a href="<?php 
                echo ($_SESSION['rol'] == 'admin')
                    ? BASE_URL . '/modules/sellado/views/dashboard.php'
                    : BASE_URL . '/modules/sellado/views/history.php';
            ?>" class="area-card">
                <h2>Sellado</h2>
                <p>Control de producción <br>de las selladoras</p>
            </a>

            <!-- Módulo Rollos -->
            <a href="<?php
                echo ($_SESSION['rol'] == 'admin')
                    ? BASE_URL . '/modules/rollo/views/dashboard.php'
                    : BASE_URL . '/modules/rollo/views/history.php';
            ?>" class="area-card">
                <h2>Rollos</h2>
                <p>Control de peso de <br>rollos y retales</p>
            </a>

            <!-- Módulo Máquina Plana -->
            <a href="<?php
                echo ($_SESSION['rol'] == 'admin')
                    ? BASE_URL . '/modules/plana/views/dashboard.php'
                    : BASE_URL . '/modules/plana/views/history.php';
            ?>" class="area-card">
                <h2>Máquina <br>Plana</h2>
                <p>Control de producción <br>de máquina plana</p>
            </a>

            <!-- Módulo Extrusión -->
            <a href="<?php
                echo ($_SESSION['rol'] == 'admin')
                    ? BASE_URL . '/modules/extrusion/views/dashboard.php'
                    : BASE_URL . '/modules/extrusion/views/history.php';
            ?>" class="area-card">
                <h2>Extrusión</h2>
                <p>Control de producción <br>de las extrusoras</p>
            </a>

        </div>

    </div>

</body>
</html>


