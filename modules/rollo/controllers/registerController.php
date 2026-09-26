<?php
/** @var mysqli $conexion */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';

$hoy = date('Y-m-d');

// Catálogos del formulario
$operarios   = mysqli_fetch_all(obtenerOperariosActivosRollo($conexion), MYSQLI_ASSOC);
$datosMaquina = obtenerMaquinasConReferencias($conexion, 'rollo');
$maquinas     = $datosMaquina['maquinas'];
$mapaReferenciasMaquina = $datosMaquina['mapaJs'];
$colores     = obtenerColoresOrdenados($conexion);
