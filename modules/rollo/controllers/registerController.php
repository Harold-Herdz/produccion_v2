<?php
/** @var mysqli $conexion */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';

asegurarTablaLogsRollo($conexion);

$hoy = date('Y-m-d');

// Catálogos para los selectores del formulario
$operarios   = mysqli_fetch_all(obtenerOperariosActivosRollo($conexion), MYSQLI_ASSOC);
$maquinas    = mysqli_fetch_all(obtenerMaquinasActivasRollo($conexion), MYSQLI_ASSOC);
$referencias = mysqli_fetch_all(obtenerReferenciasActivasRollo($conexion), MYSQLI_ASSOC);
$colores     = mysqli_fetch_all(obtenerColoresActivosRollo($conexion), MYSQLI_ASSOC);
