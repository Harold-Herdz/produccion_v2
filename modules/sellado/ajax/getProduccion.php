<?php
/** @var mysqli $conexion */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar proteger.php
require_once dirname(__DIR__, 3) . '/auth/proteger.php';
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
// Mostrar por semana
if($tipo == "semana"){
    // Todas las semanas del mes
    if($semana == ""){
        $sql = "SELECT DATE(fecha_paq) fecha, SUM(paquetes_total) total
                FROM PRODUCCION_SELLADO
                WHERE MONTH(fecha_paq) = $mes
                AND YEAR(fecha_paq) = YEAR(CURDATE())
                GROUP BY DATE(fecha_paq)";
    } else {
        // Rango de días de la semana seleccionada
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;
        $sql = "SELECT DATE(fecha_paq) fecha, SUM(paquetes_total) total
                FROM PRODUCCION_SELLADO
                WHERE MONTH(fecha_paq) = $mes
                AND DAY(fecha_paq) BETWEEN $inicio AND $fin
                AND YEAR(fecha_paq) = YEAR(CURDATE())
                GROUP BY DATE(fecha_paq)";
    }
// Mostrar por año
} elseif($tipo === 'anio') {
    // Agrupado por semana del año
    $sql = "SELECT CONCAT('Sem ', WEEK(fecha_paq, 1)) fecha, SUM(paquetes_total) total
            FROM PRODUCCION_SELLADO
            WHERE YEAR(fecha_paq) = YEAR(CURDATE())
            GROUP BY WEEK(fecha_paq, 1), CONCAT('Sem ', WEEK(fecha_paq, 1))
            ORDER BY WEEK(fecha_paq, 1) ASC";
}else{
    // Agrupado por día del mes
    $sql = "SELECT DATE(fecha_paq) fecha, SUM(paquetes_total) total
            FROM PRODUCCION_SELLADO
            WHERE MONTH(fecha_paq) = $mes
            AND YEAR(fecha_paq) = YEAR(CURDATE())
            GROUP BY DATE(fecha_paq)";
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
   CONSULTA POR OPERARIO
===================== */
// Mostrar por semana
if($tipo == "semana"){
    // Operarios de todas las semanas del mes
    if($semana == ""){
        $sql2 = "SELECT o.nombre_operario, SUM(p.paquetes_total) total
                 FROM PRODUCCION_SELLADO p
                 LEFT JOIN OPERARIOS o ON p.id_operario=o.id_operario
                 WHERE MONTH(p.fecha_paq) = $mes
                 AND YEAR(p.fecha_paq) = YEAR(CURDATE())
                 GROUP BY p.id_operario";

    } else {
        // Operarios por rango de días de la semana seleccionada
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;
        $sql2 = "SELECT o.nombre_operario, SUM(p.paquetes_total) total
                 FROM PRODUCCION_SELLADO p
                 LEFT JOIN OPERARIOS o ON p.id_operario=o.id_operario
                 WHERE MONTH(p.fecha_paq) = $mes
                 AND DAY(p.fecha_paq) BETWEEN $inicio AND $fin
                 AND YEAR(p.fecha_paq) = YEAR(CURDATE())
                 GROUP BY p.id_operario";
    }
// Mostrar por año
}elseif($tipo === 'anio') {
    // Agrupado del año
    $sql2 = "SELECT o.nombre_operario, SUM(p.paquetes_total) total
             FROM PRODUCCION_SELLADO p
             LEFT JOIN OPERARIOS o ON p.id_operario=o.id_operario
             WHERE YEAR(p.fecha_paq) = YEAR(CURDATE())
             GROUP BY o.nombre_operario
             ORDER BY total DESC";
}else{
    // Operarios del mes
    $sql2 = "SELECT o.nombre_operario, SUM(p.paquetes_total) total
             FROM PRODUCCION_SELLADO p
             LEFT JOIN OPERARIOS o ON p.id_operario=o.id_operario
             WHERE MONTH(p.fecha_paq) = $mes
             AND YEAR(p.fecha_paq) = YEAR(CURDATE())
             GROUP BY p.id_operario";
}
$res2 = mysqli_query($conexion,$sql2);

// Recopilar operarios y totales
$operarios = [];
$totales_op = [];
while($row = mysqli_fetch_assoc($res2)){
    $operarios[] = $row['nombre_operario'];
    $totales_op[] = $row['total'];
}

// Devolver datos como JSON
header('Content-Type: application/json');
echo json_encode([
    "fechas"=>$fechas,
    "totales"=>$totales,
    "operarios"=>$operarios,
    "totales_operarios"=>$totales_op
]);

