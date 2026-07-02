<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar dashboardModel.php
require_once dirname(__DIR__) . '/models/dashboardModel.php';

// Catálogos de meses y semanas
$meses = [
    1 => 'Enero',    2 => 'Febrero',   3 => 'Marzo',
    4 => 'Abril',    5 => 'Mayo',      6 => 'Junio',
    7 => 'Julio',    8 => 'Agosto',    9 => 'Septiembre',
    10 => 'Octubre', 11 => 'Noviembre',12 => 'Diciembre'
];
$semanas = [
    1 => 'Semana 1', 2 => 'Semana 2', 3 => 'Semana 3',
    4 => 'Semana 4', 5 => 'Semana 5'
];

// Fechas de referencia
$semana_actual = date('W', strtotime('this week'));
$mes_actual = date('n');
$mes_anterior = date('n', strtotime('-1 month'));
// Meses a comparar
$mes1 = $_GET['mes1'] ?? $mes_anterior;
$mes2 = $_GET['mes2'] ?? $mes_actual;

// Total histórico de producción
$total = obtenerTotalHistoricoSellado($conexion);
// Producción de la semana y del mes actual
$semana = obtenerProduccionSemanaSellado($conexion);
$mes = obtenerProduccionMesSellado($conexion);
// Top operario del mes actual
$top_operario = obtenerTopOperarioSellado($conexion);

// Totales de los meses
$total_mes1 = obtenerTotalMesSellado($conexion,$mes1);
$total_mes2 = obtenerTotalMesSellado($conexion,$mes2);

// Mejor y peor día de los meses
$dias_mes1 = obtenerMejorPeorDiaMesSellado($conexion,$mes1);
$mejor_dia_mes1 = $dias_mes1['mejor'];
$peor_dia_mes1 = $dias_mes1['peor'];
$dias_mes2 = obtenerMejorPeorDiaMesSellado($conexion,$mes2);
$mejor_dia_mes2 = $dias_mes2['mejor'];
$peor_dia_mes2 = $dias_mes2['peor'];

// Top operario de los meses
$top_operario_mes1 = obtenerTopOperarioMesSellado($conexion,$mes1);
$top_operario_mes2 = obtenerTopOperarioMesSellado($conexion,$mes2);

// Filtros de fecha para tablas
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
// Tablas de producción por fecha y operario
$res_tabla_fecha = obtenerTablaFechasSellado($conexion,$desde,$hasta);
$res_tabla_operario = obtenerTablaOperariosSellado($conexion,$desde,$hasta);

// Diferencia y porcentaje de variación entre meses
$diferencia = ($total_mes2 ?? 0) - ($total_mes1 ?? 0);
$porcentaje = ($total_mes1 > 0) ? (($diferencia / $total_mes1) * 100) : 0;

// Promedio diario de los meses
$total_dias_mes1 = date('t');
$promedio_mes1 = ($total_mes2 > 0) ? ($total_mes2 / $total_dias_mes1) : 0;
$total_dias_mes2 = date('t', strtotime('-1 month'));
$promedio_mes2 = ($total_mes1 > 0) ? ($total_mes1 / $total_dias_mes2) : 0;

// Última fecha de importación
$ultima_fecha = obtenerUltimaImportacionSellado($conexion);