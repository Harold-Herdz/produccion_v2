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
    <?php if($_SESSION['rol'] == 'admin'){ ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/notificaciones.css">
    <?php } ?>

    <script src="<?= BASE_URL ?>/assets/js/mantenerScroll.js"></script>

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
            <?php if($_SESSION['rol'] == 'admin'){ ?>
            <!-- Campanita: valores de catálogo escritos a mano pendientes de revisión -->
            <div class="campana-wrap">
                <button type="button" id="campanaBtn" class="campana-btn" data-url="<?= BASE_URL ?>/modules/catalogs/controllers/pendientesController.php" aria-label="Notificaciones">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    <span id="campanaBadge" class="campana-badge" hidden>0</span>
                </button>
                <div id="campanaPanel" class="campana-panel" hidden>
                    <div class="campana-panel-header">
                        <span>Notificaciones</span>
                        <button type="button" onclick="document.getElementById('campanaPanel').hidden = true;">&times;</button>
                    </div>
                    <div id="campanaPanelBody" class="campana-panel-body"></div>
                </div>
            </div>
            <?php } ?>
            <a href="<?php
                    echo ($_SESSION['rol'] == 'admin')
                        ? BASE_URL . '/auth/views/users.php'
                        : '';
                ?>"><h2>Rol: <?php echo $rol ?></h2>
            </a>
        </div>

    </div>

    <?php if($_SESSION['rol'] == 'admin'){ ?>
    <script src="<?= BASE_URL ?>/assets/js/notificaciones.js"></script>
    <?php } ?>

    <!-- Menú lateral de acceso rápido -->
    <nav id="sideMenu" class="side-menu">
        <div class="side-menu-content">
            <?php if($_SESSION['rol'] == 'admin'){ ?>

            <!-- Inicio: panel general -->
            <div class="side-menu-module">
                <a class="side-menu-inicio" href="<?= BASE_URL ?>/index.php">Inicio</a>
            </div>

            <!-- Módulo Catálogos -->
            <div class="side-menu-module">
                <span class="side-menu-module-name">Catálogos</span>
                <a href="<?= BASE_URL ?>/modules/catalogs/views/catalogs.php">Maestros</a>
                <a href="<?= BASE_URL ?>/modules/catalogs/views/relations.php">Relaciones</a>
            </div>
            <?php } ?>

            <p class="side-menu-title">Módulos</p>
            
            <!-- Modulo Sellado -->
            <div class="side-menu-module">
                <span class="side-menu-module-name">Sellado</span>
                <?php if($_SESSION['rol'] == 'admin'){ ?>
                    <a href="<?= BASE_URL ?>/modules/sellado/views/dashboard.php">Panel</a>
                <?php } ?>
                <a href="<?= BASE_URL ?>/modules/sellado/views/register.php">Planilla</a>
                <a href="<?= BASE_URL ?>/modules/sellado/views/history.php">Historial</a>
            </div>

            <!-- Modulo Rollos -->
            <div class="side-menu-module">
                <span class="side-menu-module-name">Rollos</span>
                <?php if($_SESSION['rol'] == 'admin'){ ?>
                    <a href="<?= BASE_URL ?>/modules/rollo/views/dashboard.php">Panel</a>
                <?php } ?>
                <a href="<?= BASE_URL ?>/modules/rollo/views/register.php">Formulario</a>
                <a href="<?= BASE_URL ?>/modules/rollo/views/history.php">Historial</a>
            </div>

            <!-- Modulo Máquina Plana -->
            <div class="side-menu-module">
                <span class="side-menu-module-name">Máquina Plana</span>
                <?php if($_SESSION['rol'] == 'admin'){ ?>
                    <a href="<?= BASE_URL ?>/modules/plana/views/dashboard.php">Panel</a>
                <?php } ?>
                <a href="<?= BASE_URL ?>/modules/plana/views/register.php">Formulario</a>
                <a href="<?= BASE_URL ?>/modules/plana/views/history.php">Historial</a>
            </div>

            <!-- Modulo Extrusión -->
            <div class="side-menu-module">
                <span class="side-menu-module-name">Extrusión</span>
                <?php if($_SESSION['rol'] == 'admin'){ ?>
                    <a href="<?= BASE_URL ?>/modules/extrusion/views/dashboard.php">Panel</a>
                <?php } ?>
                <a href="<?= BASE_URL ?>/modules/extrusion/views/register.php">Planilla</a>
                <a href="<?= BASE_URL ?>/modules/extrusion/views/history.php">Historial</a>
            </div>
        </div>

        <div class="side-menu-bottom">
            <!-- Botón de cerrar sesión -->
            <div class="cerrar-sesion">
                <a id="btnCerrar" href="<?= BASE_URL ?>/auth/controllers/logout.php">Cerrar Sesión</a>
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