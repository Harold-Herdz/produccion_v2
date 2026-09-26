<?php
/** @var mysqli $conexion */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';

$hoy = date('Y-m-d');

// Catálogos para los selectores del formulario
$operarios      = mysqli_fetch_all(obtenerOperariosActivosPlana($conexion), MYSQLI_ASSOC);
$maquinas       = obtenerMaquinasConReferencias($conexion, 'plana')['maquinas']; // solo las de Plana
$referenciasEsp = obtenerReferenciasEspOrdenadas($conexion);                     // referencias especiales
