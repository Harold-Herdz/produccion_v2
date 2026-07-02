<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar produccionModel.php
require_once dirname(__DIR__) . '/models/produccionModel.php';

// Obtener ID del registro a editar
$id = $_GET['id'];
$fila = obtenerProduccionPorId($conexion, $id);

// Cargar catálogos para los selectores del formulario
$maquinas = obtenerMaquinas($conexion);

$referencias = obtenerReferencias($conexion);

$colores = obtenerColores($conexion);