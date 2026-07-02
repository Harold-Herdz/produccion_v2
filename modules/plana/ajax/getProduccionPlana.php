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
$tipo = $_GET['tipo'] ?? 'semana';
$mes = $_GET['mes'] ?? date('m');
$semana = $_GET['semana'] ?? '';

/* =====================
   CONSULTA POR FECHA
===================== */
// Mostrar por año
if($tipo === "anio"){
    // Agrupado por semana del año
    $sql = "SELECT 
                CONCAT('Sem ', WEEK(fecha_plana, 1)) fecha, 
                SUM(total_plana) total, 
                SUM(retal_plana) retal
            FROM PRODUCCION_PLANA
            WHERE YEAR(fecha_plana)=YEAR(CURDATE())
            GROUP BY WEEK(fecha_plana, 1), CONCAT('Sem ', WEEK(fecha_plana, 1))
            ORDER BY WEEK(fecha_plana, 1) ASC";
}else{
    // Rango de días de la semana seleccionada
    if($semana != ""){
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;

        $where = "WHERE MONTH(fecha_plana) = $mes
                AND DAY(fecha_plana) BETWEEN $inicio AND $fin
                AND YEAR(fecha_plana)=YEAR(CURDATE())";
    }else{
        // Todos los días del mes
        $where = "WHERE MONTH(fecha_plana) = $mes
                AND YEAR(fecha_plana)=YEAR(CURDATE())";
    }
    $sql = "SELECT DATE(fecha_plana) fecha, 
                SUM(total_plana) total, 
                SUM(retal_plana) retal
            FROM PRODUCCION_PLANA
            $where
            GROUP BY DATE(fecha_plana)";
}
$res = mysqli_query($conexion, $sql);

// Recopilar fechas, totales y retales
$fechas = [];
$totales = [];
$retales = [];
while($row = mysqli_fetch_assoc($res)){
    $fechas[] = $row['fecha'];
    $totales[] = $row['total'];
    $retales[] = $row['retal'];
}

/* =====================
   CONSULTA POR OPERARIO
===================== */
// Mostrar por año
if($tipo == "anio"){
    // Top 10 operarios del año
    $sql2 = "SELECT o.nombre_operario, 
                SUM(p.bultos_plana) total
            FROM PRODUCCION_PLANA p
            LEFT JOIN OPERARIOS o ON p.id_operario=o.id_operario
            WHERE YEAR(p.fecha_plana)=YEAR(CURDATE())
            GROUP BY p.id_operario
            ORDER BY total DESC
            LIMIT 10";
}else{
    // Operarios filtrados por semana
    if($semana != ""){
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;

        $where2 = "WHERE MONTH(p.fecha_plana) = $mes
                AND DAY(p.fecha_plana) BETWEEN $inicio AND $fin
                AND YEAR(p.fecha_plana)=YEAR(CURDATE())";
    }else{
        // Operarios del mes
        $where2 = "WHERE MONTH(p.fecha_plana) = $mes
                AND YEAR(p.fecha_plana)=YEAR(CURDATE())";
    }
    $sql2 = "SELECT o.nombre_operario, 
                SUM(p.bultos_plana) total
            FROM PRODUCCION_PLANA p
            LEFT JOIN OPERARIOS o ON p.id_operario=o.id_operario
            $where2
            GROUP BY p.id_operario
            ORDER BY total DESC
            LIMIT 10";
}
$res2 = mysqli_query($conexion, $sql2);

// Recopilar operarios y bultos
$operarios = [];
$bultos = [];
while($row = mysqli_fetch_assoc($res2)){
    $operarios[] = $row['nombre'];
    $bultos[] = $row['total'];
}

// Devolver datos como JSON
header('Content-Type: application/json');
echo json_encode([
    "fechas" => $fechas,
    "totales" => $totales,
    "retales" => $retales,
    "operarios" => $operarios,
    "bultos_operarios" => $bultos
]);
