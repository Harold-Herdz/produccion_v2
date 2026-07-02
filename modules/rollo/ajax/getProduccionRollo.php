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
// Mostrar por año
if($tipo === "anio"){
    // Agrupado por semana del año
    $sql = "SELECT 
                CONCAT('Sem ', WEEK(fecha_roll, 1)) fecha,
                SUM(total_roll) total,
                SUM(retal_roll) retal
            FROM PRODUCCION_ROLLO
            WHERE YEAR(fecha_roll) = YEAR(CURDATE())
            GROUP BY WEEK(fecha_roll, 1), CONCAT('Sem ', WEEK(fecha_roll, 1))
            ORDER BY WEEK(fecha_roll, 1) ASC";
}else{
    // Todos los días del mes
    if($semana == ""){
        $sql = "SELECT 
                    DATE(fecha_roll) fecha,
                    SUM(total_roll) total,
                    SUM(retal_roll) retal
                FROM PRODUCCION_ROLLO
                WHERE MONTH(fecha_roll) = $mes
                AND YEAR(fecha_roll) = YEAR(CURDATE())
                GROUP BY DATE(fecha_roll)";
    }else{
        // Rango de días de la semana seleccionada
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;

        $sql = "SELECT 
                    DATE(fecha_roll) fecha,
                    SUM(total_roll) total,
                    SUM(retal_roll) retal
                FROM PRODUCCION_ROLLO
                WHERE MONTH(fecha_roll) = $mes
                AND DAY(fecha_roll) BETWEEN $inicio AND $fin
                AND YEAR(fecha_roll) = YEAR(CURDATE())
                GROUP BY DATE(fecha_roll)";
    }
}
$res = mysqli_query($conexion,$sql);

// Recopilar fechas, totales y retales
$fechas = [];
$totales = [];
$retales = [];
$operarios = [];
$totales_operarios = [];
while($row = mysqli_fetch_assoc($res)){
    $fechas[] = $row['fecha'];
    $totales[] = $row['total'];
    $retales[] = $row['retal'];
}

/* =====================
   CONSULTA POR MÁQUINA
===================== */
// Mostrar por año
if($tipo === "anio") {
    // Máquinas del año ordenadas por total
    $sql_maquinas = "SELECT m.nombre_maquina, 
                        SUM(r.total_roll) total
                    FROM PRODUCCION_ROLLO r
                    LEFT JOIN MAQUINAS m 
                        ON r.id_maquina = m.id_maquina
                    WHERE YEAR(r.fecha_roll) = YEAR(CURDATE())
                    GROUP BY m.nombre_maquina
                    ORDER BY total DESC";
}else{
    // Máquinas del mes
    if($semana == ""){
        $sql_maquinas = "SELECT 
                            m.nombre_maquina, 
                            SUM(r.total_roll) total
                        FROM PRODUCCION_ROLLO r
                        LEFT JOIN MAQUINAS m 
                            ON r.id_maquina = m.id_maquina
                        WHERE MONTH(r.fecha_roll) = $mes
                        AND YEAR(r.fecha_roll) = YEAR(CURDATE())
                        GROUP BY m.nombre_maquina
                        ORDER BY total DESC";
    }else{
        // Máquinas filtradas por semana
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;

        $sql_maquinas = "SELECT m.nombre_maquina, 
                            SUM(r.total_roll) total
                        FROM PRODUCCION_ROLLO r
                        LEFT JOIN MAQUINAS m 
                            ON r.id_maquina = m.id_maquina
                        WHERE MONTH(r.fecha_roll) = $mes
                        AND DAY(r.fecha_roll) BETWEEN $inicio AND $fin
                        AND YEAR(r.fecha_roll) = YEAR(CURDATE())
                        GROUP BY m.nombre_maquina
                        ORDER BY total DESC";
    }
}
$res2 = mysqli_query($conexion,$sql_maquinas);

// Recopilar máquinas y sus totales
while($row = mysqli_fetch_assoc($res2)){
    $operarios[] = $row['nombre_maquina'];
    $totales_operarios[] = $row['total'];
}

// Devolver datos como JSON
header('Content-Type: application/json');
echo json_encode([
    "fechas"=>$fechas,
    "totales"=>$totales,
    "retales"=>$retales,
    "operarios"=>$operarios,
    "totales_operarios"=>$totales_operarios
]);