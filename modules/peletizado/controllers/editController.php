<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar historyModel.php
require_once dirname(__DIR__) . '/models/historyModel.php';

// ID a editar
$id = $_GET['id'];
$fila = obtenerRegistroPeletizadoPorId($conexion, $id);

// Catálogos del formulario
$maquinas = obtenerMaquinasPeletizado($conexion);

$turnos = obtenerTurnosPeletizado($conexion);

$operarios = obtenerOperariosPeletizado($conexion);

$operarios2 = obtenerOperariosPeletizado($conexion);

$colores = obtenerColoresPeletizado($conexion);
