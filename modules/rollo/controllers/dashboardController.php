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
$total = obtenerTotalHistoricoRollo($conexion);
// Producción de la semana y del mes actual
$semana = obtenerProduccionSemanaRollo($conexion);
$mes = obtenerProduccionMesRollo($conexion);
// Top máquina del mes actual
$top_maquina = obtenerTopMaquinaRollo($conexion);

// Totales de los meses
$total_mes1 = obtenerTotalMesRollo($conexion,$mes1);
$total_mes2 = obtenerTotalMesRollo($conexion,$mes2);

// Resumenes de los meses
$resumen_mes1 = obtenerResumenMesRollo($conexion,$mes1);
$bruto_mes1 = $resumen_mes1['bruto'];
$retal_mes1 = $resumen_mes1['retal'];
$neto_mes1 = $resumen_mes1['neto'];
$resumen_mes2 = obtenerResumenMesRollo($conexion,$mes2);
$bruto_mes2 = $resumen_mes2['bruto'];
$retal_mes2 = $resumen_mes2['retal'];
$neto_mes2 = $resumen_mes2['neto'];

// Mejor y peor día de los meses
$dias_mes1 = obtenerMejorPeorDiaMesRollo($conexion,$mes1);
$mejor_dia_mes1 = $dias_mes1['mejor'];
$peor_dia_mes1 = $dias_mes1['peor'];
$dias_mes2 = obtenerMejorPeorDiaMesRollo($conexion,$mes2);
$mejor_dia_mes2 = $dias_mes2['mejor'];
$peor_dia_mes2 = $dias_mes2['peor'];

// Top máquina de los meses
$top_maquina_mes1 = obtenerTopMaquinaMesRollo($conexion,$mes1);
$top_maquina_mes2 = obtenerTopMaquinaMesRollo($conexion,$mes2);

// Filtros de fecha para tablas
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
// Tablas de producción por fecha y máquina
$res_tabla_fecha = obtenerTablaFechasRollo($conexion,$desde,$hasta);
$res_tabla_maquina = obtenerTablaMaquinasRollo($conexion,$desde,$hasta);

// Diferencia y porcentaje de variación entre meses
$diferencia = ($total_mes2 ?? 0) - ($total_mes1 ?? 0);
$porcentaje = ($total_mes1 > 0) ? (($diferencia / $total_mes1) * 100) : 0;

// Calculo de eficiencia de los meses
$eficiencia_mes1 = ($bruto_mes1 !== null && $bruto_mes1 > 0) ? (($neto_mes1 / $bruto_mes1) * 100) : null;
$eficiencia_mes2 = ($bruto_mes2 !== null && $bruto_mes2 > 0) ? (($neto_mes2 / $bruto_mes2) * 100) : null;

// Última fecha de importación
$ultima_fecha = obtenerUltimaImportacionRollo($conexion);