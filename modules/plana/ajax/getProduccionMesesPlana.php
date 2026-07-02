<?php
/** @var mysqli $conexion */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar proteger.php
require_once dirname(__DIR__, 3) . '/auth/proteger.php';
// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';

// Obtener filtro de año
$filtros = [
    "anio" => $_GET['anio'] ?? date('Y')
];
$anio = $filtros['anio'];

// Producción total agrupada por mes
$sql = "SELECT 
            MONTH(fecha_plana) mes, 
            SUM(total_plana) totales
        FROM PRODUCCION_PLANA
        WHERE YEAR(fecha_plana) = $anio
        GROUP BY MONTH(fecha_plana)
        ORDER BY MONTH(fecha_plana) ASC";
$res = mysqli_query($conexion, $sql);

// Recopilar meses y totales
$meses = [];
$totales = [];
while($row = mysqli_fetch_assoc($res)){
    $meses[] = $row['mes'];
    $totales[] = $row['totales'];
}

// Devolver datos como JSON
header('Content-Type: application/json');
echo json_encode([
    'meses' => $meses,
    'totales' => $totales
]);
