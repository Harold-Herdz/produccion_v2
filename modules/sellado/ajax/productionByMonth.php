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

// Producción total agrupada por mes
$sql = "SELECT 
            MONTH(fecha_sellado) mes,
            SUM(paquetes_total) total
        FROM PRODUCCION_SELLADO
        WHERE YEAR(fecha_sellado) = '$anio'
        GROUP BY MONTH(fecha_sellado)
        ORDER BY mes";
$res = mysqli_query($conexion,$sql);

// Recopilar meses y totales
$meses = [];
$totales = [];
while($row = mysqli_fetch_assoc($res)){
    $meses[] = $row['mes'];
    $totales[] = $row['total'];
}

// Devolver datos como JSON
header('Content-Type: application/json');
echo json_encode([
    "meses"=>$meses,
    "totales"=>$totales
]);