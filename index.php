<?php
// Iniciar sesión
session_start();
// Importar authMiddleware.php
require_once("auth/authMiddleware.php");
// Importar config.php
require_once __DIR__ . '/includes/config.php';
// Importar header.php
include __DIR__ . '/templates/header.php';

$esAdmin = ($_SESSION['rol'] == 'admin');
?>

<?php if ($esAdmin) { ?>
<!-- =====================================================
     INICIO (solo admin): panel general de todos los módulos
===================================================== -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/home.css">

<div class="container" id="homePanel"
     data-url="<?= BASE_URL ?>/modules/home/ajax/homeData.php"
     data-base="<?= BASE_URL ?>"
     data-usuario="<?= htmlspecialchars($_SESSION['usuario'] ?? '') ?>">

    <h2 class="titulo-vista">Panel General</h2>

    <!-- Acceso directo a módulos -->
    <div class="home-nav" id="homeNav"></div>

    <!-- Resumen general (3 y 3) -->
    <div class="home-resumen" id="homeResumen"></div>

    <!-- Catálogos (tablas maestras) -->
    <section class="home-modulo" id="homeCatalogos"></section>

    <!-- Sección por módulo -->
    <div id="homeModulos"></div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>/modules/home/scripts/home.js"></script>

<?php } else { ?>
<!-- Selector para no admin -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/index.css">

<!-- Contenedor principal -->
<div class="index-centro">
    <div class="glass-container">

        <!-- Título -->
        <h1>Seleccionar Modulo</h1>

        <!-- Tarjetas de módulos -->
        <div class="areas">

            <!-- Módulo Sellado -->
            <a href="<?= BASE_URL ?>/modules/sellado/views/history.php" class="area-card">
                <h2>Sellado</h2>
                <p>Control de producción <br>de las selladoras</p>
            </a>

            <!-- Módulo Rollos -->
            <a href="<?= BASE_URL ?>/modules/rollo/views/history.php" class="area-card">
                <h2>Rollos</h2>
                <p>Control de peso de <br>rollos y retales</p>
            </a>

            <!-- Módulo Máquina Plana -->
            <a href="<?= BASE_URL ?>/modules/plana/views/history.php" class="area-card">
                <h2>Máquina <br>Plana</h2>
                <p>Control de producción <br>de máquina plana</p>
            </a>

            <!-- Módulo Extrusión -->
            <a href="<?= BASE_URL ?>/modules/extrusion/views/history.php" class="area-card">
                <h2>Extrusión</h2>
                <p>Control de producción <br>de las extrusoras</p>
            </a>

        </div>

    </div>
</div>
<?php } ?>

<?php include __DIR__ . '/templates/footer.php'; ?>
