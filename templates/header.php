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
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">    
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">

</head>

<body>
    <!-- Barra de navegación -->
    <div class="navbar">

        <!-- Logo -->
        <div class="navbar-logo">
            <a href="<?= BASE_URL ?>/index.php">
                <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo Plastypetco">
            </a>
            <!-- Botón de menú rápido -->
            <button class="menu-toggle" onclick="toggleMenu()" aria-label="Abrir menú">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

        <!-- Título -->
        <h1>Control Producción</h1>

        <!-- Rol -->
        <div class="rol">
            <a href="<?php
                    echo ($_SESSION['rol'] == 'admin')
                        ? BASE_URL . '/auth/views/users.php'
                        : '';
                ?>"><h2>Rol: <?php echo $rol ?></h2>
            </a>
        </div>

    </div>

    <!-- Menú lateral de acceso rápido -->
    <nav id="sideMenu" class="side-menu">
        <div class="side-menu-content">
            <p class="side-menu-title">Módulos</p>
            
            <!-- Modulo Sellado -->
            <div class="side-menu-module">
                <span class="side-menu-module-name">Sellado</span>
                <?php if($_SESSION['rol'] == 'admin'){ ?>
                    <a href="<?= BASE_URL ?>/modules/sellado/views/dashboard.php">Dashboard</a>
                <?php } ?>
                <a href="<?= BASE_URL ?>/modules/sellado/views/history.php">Historial</a>
            </div>

            <!-- Modulo Rollos -->
            <div class="side-menu-module">
                <span class="side-menu-module-name">Rollos</span>
                <?php if($_SESSION['rol'] == 'admin'){ ?>
                    <a href="<?= BASE_URL ?>/modules/rollo/views/dashboard.php">Dashboard</a>
                <?php } ?>
                <a href="<?= BASE_URL ?>/modules/rollo/views/history.php">Historial</a>
            </div>

            <!-- Modulo Máquina Plana -->
            <div class="side-menu-module">
                <span class="side-menu-module-name">Máquina Plana</span>
                <?php if($_SESSION['rol'] == 'admin'){ ?>
                    <a href="<?= BASE_URL ?>/modules/plana/views/dashboard.php">Dashboard</a>
                <?php } ?>
                <a href="<?= BASE_URL ?>/modules/plana/views/history.php">Historial</a>
            </div>

            <!-- Modulo Extrusión -->
            <div class="side-menu-module">
                <span class="side-menu-module-name">Extrusión</span>
                <?php if($_SESSION['rol'] == 'admin'){ ?>
                    <a href="<?= BASE_URL ?>/modules/extrusion/views/dashboard.php">Dashboard</a>
                <?php } ?>
                <a href="<?= BASE_URL ?>/modules/extrusion/views/history.php">Historial</a>
            </div>

            <?php if($_SESSION['rol'] == 'admin'){ ?>
            <!-- Sección de Catálogos (solo administradores) -->
            <p class="side-menu-title">Catálogos</p>

            <!-- Módulo Catálogos -->
            <div class="side-menu-module">
                <span class="side-menu-module-name">Catálogos</span>
                <a href="<?= BASE_URL ?>/modules/catalogs/views/catalogs.php">Administrar</a>
            </div>
            <?php } ?>
        </div>

        <div class="side-menu-bottom">
            <!-- Botón de cerrar sesión -->
            <div class="cerrar-sesion">
                <a id="btnCerrar" href="<?= BASE_URL ?>/auth/controllers/logout.php">Cerrar Sesión</a>
            </div>
            <!-- Nombre de la empresa -->
            <div class="footer-left">
                <strong>Plastypetco</strong><span>&nbsp;&copy; 2026</span>
            </div>
        </div>
    </nav>

    <script>
        function toggleMenu() {
            document.getElementById('sideMenu').classList.toggle('open');
            document.body.classList.toggle('menu-open');
        }
    </script>

    <?php if($_SESSION['rol'] == 'admin'){ ?>

<?php } ?>

<!-- Contenido principal -->
<div class="contenido">