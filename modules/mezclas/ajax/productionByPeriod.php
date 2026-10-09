<?php
/** @var mysqli $conexion */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar authMiddleware.php
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';

// Obtener filtros
$filtros = [
    "tipo" => $_GET['tipo'] ?? 'mes',
    "mes" => $_GET['mes'] ?? date('m'),
    "semana" => $_GET['semana'] ?? ''
];
$tipo = $filtros['tipo'];
$mes = $filtros['mes'];
$semana = $filtros['semana'];

/* =====================
   CONSULTA POR FECHA
===================== */
// Mostrar por año
if($tipo === "anio"){
    // Agrupado por semana del año
    $sql = "SELECT
                CONCAT('Sem ', WEEK(fecha_mezcla, 1)) fecha,
                COUNT(*) total
            FROM PRODUCCION_MEZCLA
            WHERE YEAR(fecha_mezcla) = YEAR(CURDATE())
            GROUP BY WEEK(fecha_mezcla, 1), CONCAT('Sem ', WEEK(fecha_mezcla, 1))
            ORDER BY WEEK(fecha_mezcla, 1) ASC";
}else{
    // Todos los días del mes
    if($semana == ""){
        $sql = "SELECT
                    DATE(fecha_mezcla) fecha,
                    COUNT(*) total
                FROM PRODUCCION_MEZCLA
                WHERE MONTH(fecha_mezcla) = $mes
                AND YEAR(fecha_mezcla) = YEAR(CURDATE())
                GROUP BY DATE(fecha_mezcla)";
    }else{
        // Rango de la semana
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;

        $sql = "SELECT
                    DATE(fecha_mezcla) fecha,
                    COUNT(*) total
                FROM PRODUCCION_MEZCLA
                WHERE MONTH(fecha_mezcla) = $mes
                AND DAY(fecha_mezcla) BETWEEN $inicio AND $fin
                AND YEAR(fecha_mezcla) = YEAR(CURDATE())
                GROUP BY DATE(fecha_mezcla)";
    }
}
$res = mysqli_query($conexion,$sql);

// Recopilar fechas y totales
$fechas = [];
$totales = [];
while($row = mysqli_fetch_assoc($res)){
    $fechas[] = $row['fecha'];
    $totales[] = $row['total'];
}

/* =====================
   CONSULTA POR REFERENCIA
===================== */
// Mostrar por año
if($tipo === "anio") {
    // Referencias del año
    $sql_referencias = "SELECT r.nombre_referencia,
                        COUNT(*) total
                    FROM PRODUCCION_MEZCLA p
                    LEFT JOIN REFERENCIAS r
                        ON p.id_referencia = r.id_referencia
                    WHERE YEAR(p.fecha_mezcla) = YEAR(CURDATE())
                    GROUP BY r.nombre_referencia
                    ORDER BY total DESC";
}else{
    // Referencias del mes
    if($semana == ""){
        $sql_referencias = "SELECT
                            r.nombre_referencia,
                            COUNT(*) total
                        FROM PRODUCCION_MEZCLA p
                        LEFT JOIN REFERENCIAS r
                            ON p.id_referencia = r.id_referencia
                        WHERE MONTH(p.fecha_mezcla) = $mes
                        AND YEAR(p.fecha_mezcla) = YEAR(CURDATE())
                        GROUP BY r.nombre_referencia
                        ORDER BY total DESC";
    }else{
        // Referencias filtradas por semana
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;

        $sql_referencias = "SELECT r.nombre_referencia,
                            COUNT(*) total
                        FROM PRODUCCION_MEZCLA p
                        LEFT JOIN REFERENCIAS r
                            ON p.id_referencia = r.id_referencia
                        WHERE MONTH(p.fecha_mezcla) = $mes
                        AND DAY(p.fecha_mezcla) BETWEEN $inicio AND $fin
                        AND YEAR(p.fecha_mezcla) = YEAR(CURDATE())
                        GROUP BY r.nombre_referencia
                        ORDER BY total DESC";
    }
}
$res2 = mysqli_query($conexion,$sql_referencias);

// Recopilar referencias y sus totales
$referencias = [];
$totales_referencias = [];
while($row = mysqli_fetch_assoc($res2)){
    $referencias[] = $row['nombre_referencia'];
    $totales_referencias[] = $row['total'];
}

// Devolver datos como JSON
header('Content-Type: application/json');
echo json_encode([
    "fechas"=>$fechas,
    "totales"=>$totales,
    "referencias"=>$referencias,
    "totales_referencias"=>$totales_referencias
]);
