<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar produccionModel.php
require_once dirname(__DIR__) . '/models/historyModel.php';

// ID a editar
$id = $_GET['id'];
$fila = obtenerRegistroPlanaPorId($conexion, $id);

// Catálogos del formulario
$maquinas = obtenerMaquinasPlana($conexion);

$operarios = obtenerOperariosPlana($conexion);

$referencias = obtenerReferenciasPlana($conexion);