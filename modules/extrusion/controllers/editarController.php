<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';

// Importar historialModel.php
require_once dirname(__DIR__) . '/models/historialModel.php';

// Obtener ID del registro
$id = $_GET['id'] ?? 0;

// Obtener registro
$fila = obtenerRegistroExtrusionPorId($conexion, $id);

// Cargar catálogos
$maquinas = obtenerMaquinasExtrusion($conexion);

$turnos_ext = obtenerTurnosExtrusion($conexion);

$operadores_ext = obtenerOperadoresExtrusion($conexion);

$referencias = obtenerReferenciasExtrusion($conexion);

$colores = obtenerColoresExtrusion($conexion);