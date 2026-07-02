<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar produccionModel.php
require_once dirname(__DIR__) . '/models/produccionModel.php';

// Obtener ID del registro a eliminar
$id = intval($_GET['id'] ?? 0);

// Eliminar registro
eliminarProduccion($conexion, $id);

// Redirigir al Lista
header("Location: " . BASE_URL . "/modules/sellado/views/lista.php");
exit;