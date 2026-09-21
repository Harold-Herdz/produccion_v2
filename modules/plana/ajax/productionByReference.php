<?php
/** @var mysqli $conexion */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar authMiddleware.php
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';

// Producción total y bultos por referencia especial
$sql = "SELECT r.nombre_referencia_esp,
            SUM(p.peso_total) totales,
            SUM(p.bultos) bultos
        FROM PRODUCCION_PLANA p
        LEFT JOIN REFERENCIAS_ESP r ON p.id_referencia_esp = r.id_referencia_esp
        GROUP BY p.id_referencia_esp, r.nombre_referencia_esp
        ORDER BY totales DESC";
$res = mysqli_query($conexion, $sql);

// Recopilar referencias, totales y bultos
$referencias = [];
$totales = [];
$bultos = [];
while($row = mysqli_fetch_assoc($res)){
    $referencias[] = $row['nombre_referencia_esp'];
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
