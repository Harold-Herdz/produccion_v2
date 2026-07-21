<?php
/** @var mysqli $conexion */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar proteger.php
require_once dirname(__DIR__, 3) . '/auth/proteger.php';
// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';

// Producción total y bultos por referencia
$sql = "SELECT r.nombre_referencia, 
            SUM(p.total_plana) totales, 
            SUM(p.bultos_plana) bultos
        FROM PRODUCCION_PLANA p
        LEFT JOIN REFERENCIAS r ON p.id_referencia = r.id_referencia
        GROUP BY p.id_referencia, r.nombre_referencia
        ORDER BY totales DESC";
$res = mysqli_query($conexion, $sql);

// Recopilar referencias, totales y bultos
$referencias = [];
$totales = [];
$bultos = [];
while($row = mysqli_fetch_assoc($res)){
    $referencias[] = $row['nombre_referencia'];
    $totales[] = $row['totales'];
    $bultos[] = $row['bultos'];
}

// Devolver datos como JSON
header('Content-Type: application/json');
echo json_encode([
    'referencias' => $referencias,
    'totales' => $totales,
    'bultos' => $bultos
]);
