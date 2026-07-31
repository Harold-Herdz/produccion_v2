<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar produccionModel.php
require_once dirname(__DIR__) . '/models/produccionModel.php';

// Obtener ID del registro a editar
$id = $_GET['id'];
$fila = obtenerRegistroExtrusionPorId($conexion, $id);

// Cargar catálogos para los selectores del formulario
$maquinas = obtenerMaquinasExtrusion($conexion);

$turnos_ext = obtenerTurnosExtrusion($conexion);

$operadores_ext = obtenerOperadoresExtrusion($conexion);

$referencias = obtenerReferenciasExtrusion($conexion);

$colores = obtenerColoresExtrusion($conexion);