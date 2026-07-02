<?php
// Iniciar sesión
session_start();
// Importar proteger.php
require_once("auth/proteger.php");
// Importar config.php
require_once __DIR__ . '/includes/config.php';
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
<title>Control de Producción</title>
    <!-- Ícono y estilos -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/logo-plastypetco.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/index.css">

</head>

<body class="index-body">
    <!-- Contenedor principal -->
    <div class="glass-container">

        <!-- Título -->
        <h1>Control de Producción</h1>

        <!-- Tarjetas de módulos — redirige según rol del usuario -->
        <div class="areas">
     
            <!-- Módulo Sellado -->
            <a href="<?php 
                echo ($_SESSION['rol'] == 'admin')
                    ? BASE_URL . '/modules/sellado/views/dashboard.php'
                    : BASE_URL . '/modules/sellado/views/lista.php';
            ?>" class="area-card">
                <h2>Sellado</h2>
                <p>Control de paquetes <br>producidos</p>
            </a>

            <!-- Módulo Rollos -->
            <a href="<?php
                echo ($_SESSION['rol'] == 'admin')
                    ? BASE_URL . '/modules/rollo/views/dashboard.php'
                    : BASE_URL . '/modules/rollo/views/lista.php';
            ?>" class="area-card">
                <h2>Rollos</h2>
                <p>Control de rollos <br>producidos</p>
            </a>

            <!-- Módulo Máquina Plana -->
            <a href="<?php
                echo ($_SESSION['rol'] == 'admin')
                    ? BASE_URL . '/modules/plana/views/dashboard.php'
                    : BASE_URL . '/modules/plana/views/lista.php';
            ?>" class="area-card">
                <h2>Máquina Plana</h2>
                <p>Control producción <br>máquina plana</p>
            </a>

        </div>

    </div>

</body>
</html>


