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
// Columnas de categoría (se grafican las 6 por separado)
$sumaCategorias = "SUM(alta_retal) alta_retal, SUM(baja) baja, SUM(refiltrado) refiltrado,
                    SUM(soplado) soplado, SUM(torta) torta, SUM(limpieza) limpieza, SUM(total) total";

// Mostrar por semana
if($tipo == "semana"){
    // Todas las semanas del mes
    if($semana == ""){
        $sql = "SELECT DATE(fecha_peletizado) fecha, $sumaCategorias
                FROM PRODUCCION_PELETIZADO
                WHERE MONTH(fecha_peletizado) = $mes
                AND YEAR(fecha_peletizado) = YEAR(CURDATE())
                GROUP BY DATE(fecha_peletizado)";
    } else {
        // Rango de la semana
        $inicio = (($semana - 1) * 7) + 1;
        $fin = $semana * 7;
        $sql = "SELECT DATE(fecha_peletizado) fecha, $sumaCategorias
                FROM PRODUCCION_PELETIZADO
                WHERE MONTH(fecha_peletizado) = $mes
                AND DAY(fecha_peletizado) BETWEEN $inicio AND $fin
                AND YEAR(fecha_peletizado) = YEAR(CURDATE())
                GROUP BY DATE(fecha_peletizado)";
    }
// Mostrar por año
} elseif($tipo === 'anio') {
    // Agrupado por semana del año
    $sql = "SELECT CONCAT('Sem ', WEEK(fecha_peletizado, 1)) fecha, $sumaCategorias
            FROM PRODUCCION_PELETIZADO
            WHERE YEAR(fecha_peletizado) = YEAR(CURDATE())
            GROUP BY WEEK(fecha_peletizado, 1), CONCAT('Sem ', WEEK(fecha_peletizado, 1))
            ORDER BY WEEK(fecha_peletizado, 1) ASC";
}else{
    // Agrupado por día del mes
    $sql = "SELECT DATE(fecha_peletizado) fecha, $sumaCategorias
            FROM PRODUCCION_PELETIZADO
            WHERE MONTH(fecha_peletizado) = $mes
            AND YEAR(fecha_peletizado) = YEAR(CURDATE())
            GROUP BY DATE(fecha_peletizado)";
}
$res = mysqli_query($conexion, $sql);

// Recopilar fechas y las 6 categorías
$fechas = [];
$cat = ['alta_retal' => [], 'baja' => [], 'refiltrado' => [], 'soplado' => [], 'torta' => [], 'limpieza' => []];
$totales = [];
while($row = mysqli_fetch_assoc($res)){
    $fechas[] = $row['fecha'];
    foreach($cat as $k => $v){ $cat[$k][] = $row[$k]; }
    $totales[] = $row['total'];
}

/* =====================
   CONSULTA POR OPERARIO
   (suma apariciones como operario 1 y operario 2)
===================== */
if($tipo == "semana" && $semana != ""){
    $inicio = (($semana - 1) * 7) + 1;
    $fin = $semana * 7;
    $where2 = "WHERE MONTH(fecha_peletizado) = $mes
            AND DAY(fecha_peletizado) BETWEEN $inicio AND $fin
            AND YEAR(fecha_peletizado) = YEAR(CURDATE())";
}elseif($tipo === 'anio'){
    $where2 = "WHERE YEAR(fecha_peletizado) = YEAR(CURDATE())";
}else{
    $where2 = "WHERE MONTH(fecha_peletizado) = $mes
            AND YEAR(fecha_peletizado) = YEAR(CURDATE())";
}
$sql2 = "SELECT o.nombre_operario, SUM(u.total) total
         FROM (
             SELECT id_operario id_op, total FROM PRODUCCION_PELETIZADO $where2
             UNION ALL
             SELECT id_operario2 id_op, total FROM PRODUCCION_PELETIZADO $where2
         ) u
         LEFT JOIN OPERARIOS o ON o.id_operario = u.id_op
         WHERE u.id_op IS NOT NULL
         GROUP BY u.id_op
         ORDER BY total DESC
         LIMIT 10";
$res2 = mysqli_query($conexion, $sql2);

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
    "fechas" => $fechas,
    "totales" => $totales,
    "categorias" => $cat,
    "operarios" => $operarios,
    "totales_operarios" => $totales_op
]);
