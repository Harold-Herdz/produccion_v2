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
                CONCAT('Sem ', WEEK(fecha_extrusion, 1)) fecha, 
                SUM(total_extrusion) total, 
                SUM(rollos_extrusion) rollos
            FROM PRODUCCION_EXTRSION
            WHERE YEAR(fecha_extrusion)=YEAR(CURDATE())
            GROUP BY WEEK(fecha_extrusion, 1), CONCAT('Sem ', WEEK(fecha_extrusion, 1))
            ORDER BY WEEK(fecha_extrusion, 1) ASC";
}else{
    // Rango de días de la semana seleccionada
    if($semana != ""){
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;

        $where = "WHERE MONTH(fecha_extrusion) = $mes
                AND DAY(fecha_extrusion) BETWEEN $inicio AND $fin
                AND YEAR(fecha_extrusion)=YEAR(CURDATE())";
    }else{
        // Todos los días del mes
        $where = "WHERE MONTH(fecha_extrusion) = $mes
                AND YEAR(fecha_extrusion)=YEAR(CURDATE())";
    }
    $sql = "SELECT DATE(fecha_extrusion) fecha, 
                SUM(total_extrusion) total, 
                SUM(rollos_extrusion) rollos
            FROM PRODUCCION_EXTRUSION
            $where
            GROUP BY DATE(fecha_extrusion)";
}
$res = mysqli_query($conexion, $sql);

// Recopilar fechas, totales y retales
$fechas = [];
$totales = [];
$rollos = [];
while($row = mysqli_fetch_assoc($res)){
    $fechas[] = $row['fecha'];
    $totales[] = $row['total'];
    $rollos[] = $row['rollos'];
}

/* =====================
   CONSULTA POR MÁQUINA
===================== */
// Mostrar por año
if($tipo == "anio"){
    // Máquinas del año ordenadas por total
    $sql2 = "SELECT m.nombre_maquina, 
                SUM(e.total_extrusion) total
            FROM PRODUCCION_EXTRUSION e
            LEFT JOIN MAQUINAS m 
            ON e.id_maquina=m.id_maquina
            WHERE YEAR(e.fecha_plana)=YEAR(CURDATE())
            GROUP BY e.id_maquina
            ORDER BY total DESC";
}else{
    // Máquinas del mes
    if($semana == ""){
        $sql2 = "SELECT m.nombre_maquina, SUM(e.total_extrusion) total
                        FROM PRODUCCION_EXTRUSION e
                        LEFT JOIN MAQUINAS m 
                        ON e.id_maquina = m.id_maquina
                        WHERE MONTH(e.fecha_extrusion) = $mes
                        AND YEAR(e.fecha_extrusion) = YEAR(CURDATE())
                        GROUP BY m.nombre_maquina
                        ORDER BY total DESC";
    }else{
        // Máquinas filtradas por semana
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;

        $sql2 = "SELECT m.nombre_maquina, SUM(e.total_extrusion) total
                        FROM PRODUCCION_EXTRUSION e
                        LEFT JOIN MAQUINAS m 
                        ON e.id_maquina = m.id_maquina
                        WHERE MONTH(e.fecha_extrusion) = $mes
                        AND DAY(e.fecha_extrusion) BETWEEN $inicio AND $fin
                        AND YEAR(e.fecha_extrusion) = YEAR(CURDATE())
                        GROUP BY m.nombre_maquina
                        ORDER BY total DESC";
    }
}
$res2 = mysqli_query($conexion, $sql2);

// Recopilar operarios y bultos
$maquinas = [];
$totales_maquinas = [];
while($row = mysqli_fetch_assoc($res2)){
    $maquinas[] = $row['nombre_maquina'];
    $totales_maquinas[] = $row['total'];
}

// Devolver datos como JSON
header('Content-Type: application/json');
echo json_encode([
    "fechas" => $fechas,
    "totales" => $totales,
    "rollos" => $rollos,
    "maquinas" => $maquinas,
    "totales_maquinas"=>$totales_maquinas
]);
