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
   DIAS TRANCURRIDOS DEL MES
================================================= */
function obtenerDiasTranscurridos($conexion, $mes) {
    $anio = date('Y');
    
    $sql = "SELECT COUNT(DISTINCT DATE(fecha_sellado)) 
            FROM PRODUCCION_SELLADO 
            WHERE MONTH(fecha_sellado) = ? 
            AND YEAR(fecha_sellado) = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $mes, $anio);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_row();
    
    return ($fila[0] > 0) ? $fila[0] : 0;
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
            WHERE YEARWEEK(fecha_sellado, 1) = YEARWEEK(CURDATE(), 1)";
    return obtenerTotalSellado($conexion, $sql);
}
// Producción del mes actual
function obtenerProduccionMesSellado($conexion){
    $sql = "SELECT SUM(paquetes_total) total
            FROM PRODUCCION_SELLADO
            WHERE MONTH(fecha_sellado) = MONTH(CURDATE())
            AND YEAR(fecha_sellado) = YEAR(CURDATE())";
    return obtenerTotalSellado($conexion, $sql);
}

/* =================================================
   TOP MÁQUINA
================================================= */
// Máquina con más producción
function obtenerTopMaquinaSellado($conexion){
    $sql = "SELECT m.nombre_maquina, 
            IFNULL(SUM(s.paquetes_total),0) total
            FROM PRODUCCION_SELLADO s
            LEFT JOIN MAQUINAS m 
            ON s.id_maquina = m.id_maquina
            WHERE YEAR(s.fecha_sellado)=YEAR(CURDATE())
            GROUP BY s.id_maquina
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion,$sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        "nombre_maquina" => "Sin datos",
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
            WHERE MONTH(fecha_sellado) = $mes
            AND YEAR(fecha_sellado)=YEAR(CURDATE())";
    return obtenerTotalSellado($conexion,$sql);
}

/* =================================================
   MEJOR Y PEOR DÍA
================================================= */
// Mejor y peor día de producción del mes
function obtenerMejorPeorDiaMesSellado($conexion,$mes){
    $sql = "SELECT 
                DATE(fecha_sellado) fecha, SUM(paquetes_total) total
            FROM PRODUCCION_SELLADO
            WHERE MONTH(fecha_sellado) = $mes
            AND YEAR(fecha_sellado)=YEAR(CURDATE())
            GROUP BY DATE(fecha_sellado)";
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
   TOP MÁQUINA
================================================= */
// Máquina con más producción en el mes
function obtenerTopMaquinaMesSellado($conexion,$mes){
    $sql = "SELECT m.nombre_maquina, 
            IFNULL(SUM(s.paquetes_total),0) total
            FROM PRODUCCION_SELLADO s
            LEFT JOIN MAQUINAS m
            ON s.id_maquina = m.id_maquina
            WHERE MONTH(s.fecha_sellado) = $mes
            AND YEAR(s.fecha_sellado)=YEAR(CURDATE())
            GROUP BY s.id_maquina
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion,$sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        "nombre_maquina" => "Sin datos",
        "total" => 0
    ];
}

/* =================================================
   TOP OPERARIO
================================================= */
// Operario con más producción en el mes
function obtenerTopOperarioMesSellado($conexion,$mes){
    $sql = "SELECT o.nombre_operario, 
            IFNULL(SUM(s.paquetes_total),0) total
            FROM PRODUCCION_SELLADO s
            LEFT JOIN OPERARIOS o 
            ON s.id_operario = o.id_operario
            WHERE MONTH(s.fecha_sellado) = $mes
            AND YEAR(s.fecha_sellado)=YEAR(CURDATE())
            GROUP BY s.id_operario
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
            DATE(fecha_sellado) fecha, SUM(paquetes_total) total
            FROM PRODUCCION_SELLADO
            WHERE DATE(fecha_sellado) BETWEEN '$desde' AND '$hasta'
            GROUP BY DATE(fecha_sellado)
            ORDER BY fecha DESC";
    return mysqli_query($conexion,$sql);
}
// Producción agrupada por operario en un rango
function obtenerTablaOperariosSellado($conexion,$desde,$hasta){
    $sql = "SELECT o.nombre_operario, SUM(s.paquetes_total) total
            FROM PRODUCCION_SELLADO s
            LEFT JOIN OPERARIOS o 
            ON s.id_operario = o.id_operario
            WHERE DATE(s.fecha_sellado) BETWEEN '$desde' AND '$hasta'
            GROUP BY o.id_operario, o.nombre_operario
            ORDER BY total DESC";
    return mysqli_query($conexion,$sql);
}

/* =================================================
   IMPORTACIÓN
================================================= */
// Fecha de la última importación de paquetes
function obtenerUltimaImportacionSellado($conexion){
    $sql = "SELECT ultimo_id_sheet 
            FROM IMPORTAR 
            WHERE nombre = 'sellado'";
    $res = mysqli_query($conexion, $sql);
    if(!$res){
        return 'Ninguno';
    }
    $row = mysqli_fetch_assoc($res);
    return $row['ultimo_id_sheet'] ?? 'Ninguno';
}
