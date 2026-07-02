<?php
/* =================================================
   FUNCIÓN BASE
================================================= */
// Ejecutar consulta y retornar valor 'total'
function obtenerTotalPlana($conexion, $sql){
    $res = mysqli_query($conexion, $sql);
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
function obtenerTotalHistoricoPlana($conexion){
    $sql = "SELECT SUM(total_plana) total FROM PRODUCCION_PLANA";
    return obtenerTotalPlana($conexion, $sql);
}
// Producción de la semana actual
function obtenerProduccionSemanaPlana($conexion){
    $sql = "SELECT SUM(total_plana) total
            FROM PRODUCCION_PLANA
            WHERE YEARWEEK(fecha_plana, 1) = YEARWEEK(CURDATE(), 1)";
    return obtenerTotalPlana($conexion, $sql);
}
// Producción del mes actual
function obtenerProduccionMesPlana($conexion){
    $sql = "SELECT SUM(total_plana) total
            FROM PRODUCCION_PLANA
            WHERE MONTH(fecha_plana) = MONTH(CURDATE())
            AND YEAR(fecha_plana) = YEAR(CURDATE())";
    return obtenerTotalPlana($conexion, $sql);
}

/* =================================================
   TOP OPERARIO (MES ACTUAL)
================================================= */
// Operario con más producción en el mes actual
function obtenerTopOperarioPlana($conexion){
    $sql = "SELECT o.nombre_operario, IFNULL(SUM(p.bultos_plana),0) total
            FROM PRODUCCION_PLANA p
            LEFT JOIN OPERARIOS o ON p.id_operario = o.id_operario
            WHERE MONTH(p.fecha_plana)=MONTH(CURDATE())
            AND YEAR(p.fecha_plana)=YEAR(CURDATE())
            GROUP BY p.id_operario
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        'nombre_operario' => 'Sin datos',
        'total' => 0
    ];
}

/* =================================================
   TOTAL MES
================================================= */
// Total de paquetes del mes
function obtenerTotalMesPlana($conexion,$mes){
    $sql = "SELECT SUM(total_plana) total
            FROM PRODUCCION_PLANA
            WHERE MONTH(fecha_plana) = $mes
            AND YEAR(fecha_plana)=YEAR(CURDATE())";
    return obtenerTotalPlana($conexion, $sql);
}

/* =================================================
   RESUMEN MES
================================================= */
// Resumen de producción del mes
function obtenerResumenMesPlana($conexion,$mes){
    $sql = "SELECT SUM(peso_plana) bruto, SUM(retal_plana) retal, SUM(total_plana) neto
            FROM PRODUCCION_PLANA
            WHERE MONTH(fecha_plana) = $mes
            AND YEAR(fecha_plana)=YEAR(CURDATE())";
    $res = mysqli_query($conexion, $sql);
    if($res){
        $row = mysqli_fetch_assoc($res);
        return [
            'bruto' => $row['bruto'] ?? null,
            'retal' => $row['retal'] ?? null,
            'neto'  => $row['neto']  ?? null
        ];
    }
    return ['bruto' => null, 'retal' => null, 'neto' => null];
}

/* =================================================
   MEJOR Y PEOR DÍA
================================================= */
// Mejor y peor día de producción del mes
function obtenerMejorPeorDiaMesPlana($conexion,$mes){
    $sql = "SELECT DATE(fecha_plana) fecha, SUM(total_plana) total
            FROM PRODUCCION_PLANA
            WHERE MONTH(fecha_plana) = $mes
            AND YEAR(fecha_plana)=YEAR(CURDATE())
            GROUP BY DATE(fecha_plana)";
    $res = mysqli_query($conexion, $sql);
    $mejor = ['fecha' => 'Sin datos', 'total' => 0];
    $peor = ['fecha' => 'Sin datos', 'total' => 0];
    if($res && mysqli_num_rows($res) > 0){
        while($row = mysqli_fetch_assoc($res)){
            if($row['total'] > $mejor['total']){
                $mejor = $row;
            }
            if($peor['fecha'] === 'Sin datos' || $row['total'] < $peor['total']){
                $peor = $row;
            }
        }
    }
    return [
        'mejor' => $mejor,
        'peor' => $peor
    ];
}

/* =================================================
   TOP OPERARIO
================================================= */
// Operario con más producción en el mes
function obtenerTopOperarioMesPlana($conexion,$mes){
    $sql = "SELECT o.nombre_operario, IFNULL(SUM(p.bultos_plana),0) total
            FROM PRODUCCION_PLANA p
            LEFT JOIN OPERARIOS o ON p.id_operario = o.id_operario
            WHERE MONTH(p.fecha_plana) = $mes
            AND YEAR(p.fecha_plana)=YEAR(CURDATE())
            GROUP BY p.id_operario
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        'nombre_operario' => 'Sin datos',
        'total' => 0
    ];
}

/* =================================================
   TABLAS
================================================= */
// Producción agrupada por fecha en un rango
function obtenerTablaFechasPlana($conexion, $desde, $hasta){
    $sql = "SELECT DATE(fecha_plana) fecha, SUM(peso_plana) bruto, SUM(bultos_plana) bultos, SUM(retal_plana) retal, SUM(total_plana) neto
            FROM PRODUCCION_PLANA
            WHERE DATE(fecha_plana) BETWEEN '$desde' AND '$hasta'
            GROUP BY DATE(fecha_plana)
            ORDER BY fecha DESC";
    return mysqli_query($conexion, $sql);
}
// Producción por referencias en un rango
function obtenerTablaReferenciasPlana($conexion, $desde, $hasta){
    $sql = "SELECT r.nombre_referencia, SUM(p.peso_plana) bruto, SUM(p.bultos_plana) bultos, SUM(p.retal_plana) retal, SUM(p.total_plana) neto
            FROM PRODUCCION_PLANA p
            LEFT JOIN REFERENCIAS r ON p.id_referencia = r.id_referencia
            WHERE DATE(p.fecha_plana) BETWEEN '$desde' AND '$hasta'
            GROUP BY r.id_referencia, r.nombre_referencia
            ORDER BY neto DESC";
    return mysqli_query($conexion, $sql);
}
// Producción agrupada por máquina en un rango
function obtenerTablaMaquinasPlana($conexion, $desde, $hasta){
    $sql = "SELECT m.nombre_maquina, SUM(p.peso_plana) bruto, SUM(p.bultos_plana) bultos, SUM(p.retal_plana) retal, SUM(p.total_plana) neto
            FROM PRODUCCION_PLANA p
            LEFT JOIN MAQUINAS m ON p.id_maquina = m.id_maquina
            WHERE DATE(p.fecha_plana) BETWEEN '$desde' AND '$hasta'
            GROUP BY m.id_maquina, m.nombre_maquina
            ORDER BY neto DESC";
    return mysqli_query($conexion, $sql);
}

/* =================================================
   IMPORTACIÓN
================================================= */
// Fecha de la última importación de máquina plana
function obtenerUltimaImportacionPlana($conexion){
    $sql = "SELECT ultima_fecha 
            FROM IMPORTAR 
            WHERE nombre = 'plana'";
    $res = mysqli_query($conexion, $sql);
    if(!$res){
        return 'Nunca';
    }
    $row = mysqli_fetch_assoc($res);
    return $row['ultima_fecha'] ?? 'Nunca';
}
