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
                CONCAT('Sem ', WEEK(fecha_rollo, 1)) fecha,
                SUM(total_rollo) total,
                SUM(retal_rollo) retal
            FROM PRODUCCION_ROLLO
            WHERE YEAR(fecha_rollo) = YEAR(CURDATE())
            GROUP BY WEEK(fecha_rollo, 1), CONCAT('Sem ', WEEK(fecha_rollo, 1))
            ORDER BY WEEK(fecha_rollo, 1) ASC";
}else{
    // Todos los días del mes
    if($semana == ""){
        $sql = "SELECT 
                    DATE(fecha_rollo) fecha,
                    SUM(total_rollo) total,
                    SUM(retal_rollo) retal
                FROM PRODUCCION_ROLLO
                WHERE MONTH(fecha_rollo) = $mes
                AND YEAR(fecha_rollo) = YEAR(CURDATE())
                GROUP BY DATE(fecha_rollo)";
    }else{
        // Rango de días de la semana seleccionada
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;

        $sql = "SELECT 
                    DATE(fecha_rollo) fecha,
                    SUM(total_rollo) total,
                    SUM(retal_rollo) retal
                FROM PRODUCCION_ROLLO
                WHERE MONTH(fecha_rollo) = $mes
                AND DAY(fecha_rollo) BETWEEN $inicio AND $fin
                AND YEAR(fecha_rollo) = YEAR(CURDATE())
                GROUP BY DATE(fecha_rollo)";
    }
}
$res = mysqli_query($conexion,$sql);

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
   CONSULTA POR MÁQUINA
===================== */
// Mostrar por año
if($tipo === "anio") {
    // Máquinas del año ordenadas por total
    $sql_maquinas = "SELECT m.nombre_maquina, 
                        SUM(r.total_rollo) total
                    FROM PRODUCCION_ROLLO r
                    LEFT JOIN MAQUINAS m 
                        ON r.id_maquina = m.id_maquina
                    WHERE YEAR(r.fecha_rollo) = YEAR(CURDATE())
                    GROUP BY m.nombre_maquina
                    ORDER BY total DESC";
}else{
    // Máquinas del mes
    if($semana == ""){
        $sql_maquinas = "SELECT 
                            m.nombre_maquina, 
                            SUM(r.total_rollo) total
                        FROM PRODUCCION_ROLLO r
                        LEFT JOIN MAQUINAS m 
                            ON r.id_maquina = m.id_maquina
                        WHERE MONTH(r.fecha_rollo) = $mes
                        AND YEAR(r.fecha_rollo) = YEAR(CURDATE())
                        GROUP BY m.nombre_maquina
                        ORDER BY total DESC";
    }else{
        // Máquinas filtradas por semana
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;

        $sql_maquinas = "SELECT m.nombre_maquina, 
                            SUM(r.total_rollo) total
                        FROM PRODUCCION_ROLLO r
                        LEFT JOIN MAQUINAS m 
                            ON r.id_maquina = m.id_maquina
                        WHERE MONTH(r.fecha_rollo) = $mes
                        AND DAY(r.fecha_rollo) BETWEEN $inicio AND $fin
                        AND YEAR(r.fecha_rollo) = YEAR(CURDATE())
                        GROUP BY m.nombre_maquina
                        ORDER BY total DESC";
    }
}
$res2 = mysqli_query($conexion,$sql_maquinas);

// Recopilar máquinas y sus totales
$maquinas = [];
$totales_maquinas = [];
while($row = mysqli_fetch_assoc($res2)){
    $maquinas[] = $row['nombre_maquina'];
    $totales_maquinas[] = $row['total'];
}

// Devolver datos como JSON
header('Content-Type: application/json');
echo json_encode([
    "fechas"=>$fechas,
    "totales"=>$totales,
    "retales"=>$retales,
    "maquinas"=>$maquinas,
    "totales_maquinas"=>$totales_maquinas
]);