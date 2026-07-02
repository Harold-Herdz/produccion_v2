<?php
/* =================================================
   FUNCIÓN BASE
================================================= */
// Ejecutar consulta y retornar valor 'total'
function obtenerTotalSellado($conexion, $sql){
    $res = mysqli_query($conexion,$sql);
    if(!$res){
        return 0;
    }
    $row = mysqli_fetch_assoc($res);
    return $row['total'] ?? 0;
}

/* =================================================
   CONSULTAS DE TOTALES
================================================= */
// Total histórico de producción
function obtenerTotalHistoricoSellado($conexion){
    $sql = "SELECT SUM(paquetes_total) total
            FROM PRODUCCION_SELLADO";
    return obtenerTotalSellado($conexion, $sql);
}
// Producción de la semana actual
function obtenerProduccionSemanaSellado($conexion){
    $sql = "SELECT SUM(paquetes_total) total
            FROM PRODUCCION_SELLADO
            WHERE YEARWEEK(fecha_paq, 1) = YEARWEEK(CURDATE(), 1)";
    return obtenerTotalSellado($conexion, $sql);
}
// Producción del mes actual
function obtenerProduccionMesSellado($conexion){
    $sql = "SELECT SUM(paquetes_total) total
            FROM PRODUCCION_SELLADO
            WHERE MONTH(fecha_paq) = MONTH(CURDATE())
            AND YEAR(fecha_paq) = YEAR(CURDATE())";
    return obtenerTotalSellado($conexion, $sql);
}

/* =================================================
   TOP OPERARIO (MES ACTUAL)
================================================= */
// Operario con más producción en el mes actual 
function obtenerTopOperarioSellado($conexion){
    $sql = "SELECT o.nombre_operario, IFNULL(SUM(p.paquetes_total),0) total
            FROM PRODUCCION_SELLADO p
            LEFT JOIN OPERARIOS o 
            ON p.id_operario = o.id_operario
            WHERE MONTH(p.fecha_paq)=MONTH(CURDATE())
            AND YEAR(p.fecha_paq)=YEAR(CURDATE())
            GROUP BY p.id_operario
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion,$sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        "nombre_operario" => "Sin datos",
        "total" => 0
    ];
}

/* =================================================
   TOTAL MES
================================================= */
// Total de paquetes del mes
function obtenerTotalMesSellado($conexion,$mes){
    $sql = "SELECT SUM(paquetes_total) total
            FROM PRODUCCION_SELLADO
            WHERE MONTH(fecha_paq) = $mes
            AND YEAR(fecha_paq)=YEAR(CURDATE())";
    return obtenerTotalSellado($conexion,$sql);
}

/* =================================================
   MEJOR Y PEOR DÍA
================================================= */
// Mejor y peor día de producción del mes
function obtenerMejorPeorDiaMesSellado($conexion,$mes){
    $sql = "SELECT 
                DATE(fecha_paq) fecha, SUM(paquetes_total) total
            FROM PRODUCCION_SELLADO
            WHERE MONTH(fecha_paq) = $mes
            AND YEAR(fecha_paq)=YEAR(CURDATE())
            GROUP BY DATE(fecha_paq)";
    $res = mysqli_query($conexion,$sql);
    $mejor_dia = ["fecha"=>"Sin datos","total"=>0];
    $peor_dia = ["fecha"=>"Sin datos","total"=>0];
    if($res){
        while($row = mysqli_fetch_assoc($res)){
            if($row['total'] > $mejor_dia['total']){
                $mejor_dia = $row;
            }
            if(
                $peor_dia['fecha'] == "Sin datos"
                || $row['total'] < $peor_dia['total']
            ){
                $peor_dia = $row;
            }
        }
    }
    return [
        "mejor" => $mejor_dia,
        "peor" => $peor_dia
    ];
}

/* =================================================
   TOP OPERARIO
================================================= */
// Operario con más producción en el mes
function obtenerTopOperarioMesSellado($conexion,$mes){
    $sql = "SELECT o.nombre_operario, IFNULL(SUM(p.paquetes_total),0) total
            FROM PRODUCCION_SELLADO p
            LEFT JOIN OPERARIOS o 
            ON p.id_operario = o.id_operario
            WHERE MONTH(p.fecha_paq) = $mes
            AND YEAR(p.fecha_paq)=YEAR(CURDATE())
            GROUP BY p.id_operario
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion,$sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        "nombre_operario" => "Sin datos",
        "total" => 0
    ];
}

/* =================================================
   TABLAS
================================================= */
// Producción agrupada por fecha en un rango
function obtenerTablaFechasSellado($conexion,$desde,$hasta){
    $sql = "SELECT 
            DATE(fecha_paq) as fecha, SUM(paquetes_total) total
            FROM PRODUCCION_SELLADO
            WHERE DATE(fecha_paq) BETWEEN '$desde' AND '$hasta'
            GROUP BY DATE(fecha_paq)
            ORDER BY fecha DESC";
    return mysqli_query($conexion,$sql);
}
// Producción agrupada por operario en un rango
function obtenerTablaOperariosSellado($conexion,$desde,$hasta){
    $sql = "SELECT o.nombre_operario, SUM(p.paquetes_total) total
            FROM PRODUCCION_SELLADO p
            LEFT JOIN OPERARIOS o 
            ON p.id_operario = o.id_operario
            WHERE DATE(p.fecha_paq) BETWEEN '$desde' AND '$hasta'
            GROUP BY o.nombre_operario
            ORDER BY total DESC";
    return mysqli_query($conexion,$sql);
}

/* =================================================
   IMPORTACIÓN
================================================= */
// Fecha de la última importación de paquetes
function obtenerUltimaImportacionSellado($conexion){
    $sql = "SELECT ultima_fecha 
            FROM IMPORTAR 
            WHERE nombre = 'sellado'";
    $res = mysqli_query($conexion, $sql);
    if(!$res){
        return 'Nunca';
    }
    $row = mysqli_fetch_assoc($res);
    return $row['ultima_fecha'] ?? 'Nunca';
}
