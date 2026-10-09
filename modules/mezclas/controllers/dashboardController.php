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

// Total de mezclas registradas
$total = obtenerTotalHistoricoMezcla($conexion);
// Mezclas semana y mes
$semana = obtenerMezclasSemanaMezcla($conexion);
$mes = obtenerMezclasMesMezcla($conexion);
// Referencia más mezclada y cantidad de referencias distintas
$referencia_top = obtenerReferenciaMasMezcladaMezcla($conexion);
$referencias_distintas = obtenerReferenciasDistintasMezcla($conexion);

// Mezclas de los meses a comparar
$registros_mes1 = obtenerMezclasMesPuntualMezcla($conexion,$mes1);
$registros_mes2 = obtenerMezclasMesPuntualMezcla($conexion,$mes2);
// Referencia más mezclada de cada mes
$referencia_mes1 = obtenerReferenciaMasMezcladaMesMezcla($conexion,$mes1);
$referencia_mes2 = obtenerReferenciaMasMezcladaMesMezcla($conexion,$mes2);

// Filtros de fecha para tablas
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
// Tablas por fecha y referencia
$res_tabla_fecha = obtenerTablaFechasMezcla($conexion,$desde,$hasta);
$res_tabla_referencia = obtenerTablaReferenciasMezcla($conexion,$desde,$hasta);

// Variación entre meses (cantidad de mezclas registradas)
$diferencia = $registros_mes2 - $registros_mes1;
$porcentaje = ($registros_mes1 > 0) ? (($diferencia / $registros_mes1) * 100) : 0;
