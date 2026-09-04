<?php
// Iniciar sesión
session_start();
// Importar authMiddleware.php
require_once("auth/authMiddleware.php");
// Importar config.php
require_once __DIR__ . '/includes/config.php';
// Importar header.php
include __DIR__ . '/templates/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/index.css">

<!-- Contenedor principal -->
<div class="index-centro">
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
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>

