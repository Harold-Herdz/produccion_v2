<?php
/** @var mysqli $conexion */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar authMiddleware.php
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';

// Obtener filtro de año
$filtros = [
    "anio" => $_GET['anio'] ?? date('Y')
];
$anio = $filtros['anio'];

// Total de producción en el año
$sql = "SELECT SUM(total_extrusion) total
        FROM PRODUCCION_EXTRUSION
        WHERE YEAR(fecha_extrusion) = $anio";
$res = mysqli_query($conexion, $sql);
$row = mysqli_fetch_assoc($res);

// Devolver total como JSON
header('Content-Type: application/json');
echo json_encode([
    'total' => $row['total'] ?? 0
]);
